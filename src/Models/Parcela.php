<?php
/**
 * Parcelas a receber dos contratos (tabela parcelas) e a tela Financeiro (financeiro.js).
 *
 * O status não fica gravado: é calculado a cada consulta a partir dos pagamentos não estornados.
 *   CANCELADA  parcela cancelada;
 *   PAGA       recebido ≥ valor devido (valor_original + acrescimo − desconto);
 *   ATRASADA   vencida e com saldo;
 *   PARCIAL    recebeu parte e ainda não venceu;
 *   ABERTA     nada recebido e ainda não venceu.
 * Prioridade (só com saldo): URGENTE vencida; ALTA vence em até 7 dias; MEDIA até 30; BAIXA depois.
 */

final class Parcela extends Modelo
{
    public const STATUS      = ['ABERTA', 'PARCIAL', 'ATRASADA', 'PAGA', 'CANCELADA'];
    public const PRIORIDADES = ['URGENTE', 'ALTA', 'MEDIA', 'BAIXA'];

    /**
     * Consulta base: uma linha por parcela, com cliente, contrato, valores, status e prioridade.
     * :hoje entra uma única vez (tabela "d"), porque o mesmo placeholder não pode se repetir no SQL.
     */
    private const SQL_BASE = "(
        SELECT b.*,
               b.valor_devido - b.valor_recebido AS saldo,
               CASE WHEN b.cancelada = 1 THEN 'CANCELADA'
                    WHEN b.valor_recebido >= b.valor_devido THEN 'PAGA'
                    WHEN b.vencimento < b.hoje THEN 'ATRASADA'
                    WHEN b.valor_recebido > 0 THEN 'PARCIAL'
                    ELSE 'ABERTA' END AS status,
               CASE WHEN b.cancelada = 1 OR b.valor_recebido >= b.valor_devido THEN NULL
                    WHEN b.vencimento < b.hoje THEN 'URGENTE'
                    WHEN b.vencimento <= DATE_ADD(b.hoje, INTERVAL 7 DAY) THEN 'ALTA'
                    WHEN b.vencimento <= DATE_ADD(b.hoje, INTERVAL 30 DAY) THEN 'MEDIA'
                    ELSE 'BAIXA' END AS prioridade
          FROM (
            SELECT pa.id, pa.contrato_id, pa.numero, pa.competencia, pa.vencimento, pa.valor_original, pa.acrescimo,
                   pa.desconto, pa.cancelada, pa.observacoes,
                   c.numero_contrato AS contrato, c.status AS contrato_status,
                   pe.id AS cliente_id, pe.nome_razao_social AS cliente, pe.cpf_cnpj, pe.email,
                   pa.valor_original + pa.acrescimo - pa.desconto AS valor_devido,
                   COALESCE(pg.recebido, 0) AS valor_recebido,
                   pg.ultimo_pagamento_em,
                   (SELECT u.forma FROM pagamentos u WHERE u.parcela_id = pa.id AND u.estornado_em IS NULL
                     ORDER BY u.pago_em DESC, u.id DESC LIMIT 1) AS ultima_forma,
                   d.hoje
              FROM parcelas pa
              JOIN contratos c ON c.id = pa.contrato_id
              JOIN pessoas pe ON pe.id = c.cliente_id
              LEFT JOIN (SELECT parcela_id, SUM(valor) AS recebido, MAX(pago_em) AS ultimo_pagamento_em
                           FROM pagamentos WHERE estornado_em IS NULL GROUP BY parcela_id) pg ON pg.parcela_id = pa.id
              CROSS JOIN (SELECT CAST(:hoje AS DATE) AS hoje) d
          ) b
    ) t";

    /** Formas do banco → códigos usados pelo financeiro.js. */
    private const FORMAS_TELA = ['CARTAO_CREDITO' => 'CREDITO', 'CARTAO_DEBITO' => 'DEBITO'];

    /**
     * Cria as parcelas de um contrato (chamado por Contrato::gerarContrato).
     * O total é dividido igualmente; os centavos que sobram vão para a última parcela.
     *
     * @param array $plano { quantidade, primeiro (Y-m-d), intervalo (dias), dia_fixo (1-31 ou null) }
     */
    public static function gerarParcelas(int $contratoId, int $totalCentavos, array $plano): void
    {
        $quantidade = max(1, (int) $plano['quantidade']);
        $baseC      = intdiv($totalCentavos, $quantidade);
        $primeiro   = new DateTimeImmutable($plano['primeiro']);

        $insere = self::pdo()->prepare(
            'INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
             VALUES (:contrato, :numero, :competencia, :vencimento, :valor, 0, 0, 0)'
        );

        for ($n = 1; $n <= $quantidade; $n++) {
            $vencimento = $plano['dia_fixo']
                ? self::diaDoMes($primeiro->modify('first day of this month')->modify('+' . ($n - 1) . ' month'), (int) $plano['dia_fixo'])
                : $primeiro->modify('+' . (($n - 1) * (int) $plano['intervalo']) . ' days');
            $valorC = $n === $quantidade ? $totalCentavos - $baseC * ($quantidade - 1) : $baseC;

            $insere->execute([
                'contrato'    => $contratoId,
                'numero'      => $n,
                'competencia' => $vencimento->format('Y-m-01'),
                'vencimento'  => $vencimento->format('Y-m-d'),
                'valor'       => self::reais($valorC),
            ]);
        }
    }

    /**
     * Listagem da tela Financeiro.
     * Filtros: q (cliente, CPF/CNPJ, e-mail ou número do contrato), prioridade, status, data (vencimento), page.
     *
     * @return array{items: array, metrics: array, total: int, page: int, pages: int}
     */
    public static function listarParcelas(array $filtros = []): array
    {
        $where  = ['1 = 1'];
        $params = ['hoje' => self::hoje()];

        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            $partes = ['t.cliente LIKE :q1', 't.email LIKE :q2', 't.contrato LIKE :q3'];
            $params += ['q1' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%"];
            if (self::somenteDigitos($q) !== '') {
                $partes[]     = 't.cpf_cnpj LIKE :q4';
                $params['q4'] = '%' . self::somenteDigitos($q) . '%';
            }
            $where[] = '(' . implode(' OR ', $partes) . ')';
        }
        $status = strtoupper((string) ($filtros['status'] ?? ''));
        if (in_array($status, self::STATUS, true)) {
            $where[]          = 't.status = :status';
            $params['status'] = $status;
        }
        $prioridade = strtoupper((string) ($filtros['prioridade'] ?? ''));
        if (in_array($prioridade, self::PRIORIDADES, true)) {
            $where[]              = 't.prioridade = :prioridade';
            $params['prioridade'] = $prioridade;
        }
        $erros = [];
        $data  = self::campoData($filtros, 'data', $erros);
        if ($data !== null) {
            $where[]        = 't.vencimento = :data';
            $params['data'] = $data;
        }

        // Atrasadas primeiro, depois as em aberto por vencimento; pagas e canceladas no fim.
        $resultado = self::paginar(
            't.*',
            'FROM ' . self::SQL_BASE . ' WHERE ' . implode(' AND ', $where),
            $params,
            "FIELD(t.status, 'ATRASADA', 'PARCIAL', 'ABERTA', 'PAGA', 'CANCELADA'), t.vencimento, t.id",
            (int) ($filtros['page'] ?? 1)
        );
        $resultado['items']   = array_map([self::class, 'formatar'], $resultado['items']);
        $resultado['metrics'] = self::indicadores();

        return $resultado;
    }

    /**
     * Indicadores dos cards do Financeiro (globais, sem filtros).
     * prestes_vencer usa os "dias de alerta" das preferências do contrato (padrão 7).
     */
    public static function indicadores(): array
    {
        $empresa = Empresa::obterEmpresa();
        $alerta  = $empresa !== null ? Empresa::obterPreferencias($empresa['id'])['dias_alerta_vencimento'] : 7;
        $hoje    = self::hoje();

        $consulta = self::pdo()->prepare(
            "SELECT COALESCE(SUM(t.status IN ('ABERTA', 'PARCIAL')), 0) AS em_aberto,
                    COALESCE(SUM(t.status = 'ATRASADA'), 0) AS em_atraso,
                    COUNT(DISTINCT CASE WHEN t.status = 'ATRASADA' THEN t.cliente_id END) AS clientes_atraso,
                    COALESCE(SUM(t.status IN ('ABERTA', 'PARCIAL') AND t.vencimento <= DATE_ADD(t.hoje, INTERVAL $alerta DAY)), 0) AS prestes_vencer
               FROM " . self::SQL_BASE
        );
        $consulta->execute(['hoje' => $hoje]);
        $metricas = array_map('intval', $consulta->fetch());

        // Faturamento do mês = pagamentos não estornados recebidos no mês atual.
        $mes = self::pdo()->prepare(
            'SELECT COALESCE(SUM(valor), 0) AS total, COUNT(*) AS quantidade FROM pagamentos
              WHERE estornado_em IS NULL AND pago_em >= :inicio AND pago_em < :fim'
        );
        $mes->execute(['inicio' => date('Y-m-01 00:00:00'), 'fim' => date('Y-m-01 00:00:00', strtotime('first day of next month'))]);
        $faturamento = $mes->fetch();

        return [
            'faturamento_mes'      => (float) $faturamento['total'],
            'pagamentos_recebidos' => (int) $faturamento['quantidade'],
        ] + $metricas;
    }

    /** Detalhes da parcela (diálogo "Detalhes da parcela"), com o histórico de pagamentos e estornos. */
    public static function buscarParcela(int $id): ?array
    {
        $consulta = self::pdo()->prepare('SELECT t.* FROM ' . self::SQL_BASE . ' WHERE t.id = :id');
        $consulta->execute(['hoje' => self::hoje(), 'id' => $id]);
        $linha = $consulta->fetch();
        if (!$linha) {
            return null;
        }

        $parcela               = self::formatar($linha);
        $parcela['pagamentos'] = array_map(static function (array $p): array {
            $p['forma'] = self::FORMAS_TELA[$p['forma']] ?? $p['forma'];
            return $p;
        }, Pagamento::listarPorParcela($id));

        return $parcela;
    }

    /** Parcelas de um contrato, na ordem, com status (detalhes do contrato). */
    public static function listarPorContrato(int $contratoId): array
    {
        $consulta = self::pdo()->prepare('SELECT t.* FROM ' . self::SQL_BASE . ' WHERE t.contrato_id = :id ORDER BY t.numero');
        $consulta->execute(['hoje' => self::hoje(), 'id' => $contratoId]);

        return array_map([self::class, 'formatar'], $consulta->fetchAll());
    }

    /**
     * Situação financeira da parcela, em centavos, para o registro de pagamento.
     * Com $bloquear, trava a parcela até o fim da transação (dois pagamentos ao mesmo tempo
     * não conseguem passar do saldo).
     *
     * @return array{id: int, devido_c: int, recebido_c: int, saldo_c: int, cancelada: bool, contrato_status: string}|null
     */
    public static function situacao(int $id, bool $bloquear = false): ?array
    {
        $pdo      = self::pdo();
        $consulta = $pdo->prepare(
            'SELECT pa.id, pa.valor_original + pa.acrescimo - pa.desconto AS devido, pa.cancelada, c.status AS contrato_status
               FROM parcelas pa JOIN contratos c ON c.id = pa.contrato_id
              WHERE pa.id = :id' . ($bloquear ? ' FOR UPDATE' : '')
        );
        $consulta->execute(['id' => $id]);
        $parcela = $consulta->fetch();
        if (!$parcela) {
            return null;
        }

        $recebido = $pdo->prepare('SELECT COALESCE(SUM(valor), 0) FROM pagamentos WHERE parcela_id = :id AND estornado_em IS NULL');
        $recebido->execute(['id' => $id]);
        $devidoC   = self::centavos($parcela['devido']);
        $recebidoC = self::centavos($recebido->fetchColumn());

        return [
            'id'              => (int) $parcela['id'],
            'devido_c'        => $devidoC,
            'recebido_c'      => $recebidoC,
            'saldo_c'         => max(0, $devidoC - $recebidoC),
            'cancelada'       => (bool) $parcela['cancelada'],
            'contrato_status' => $parcela['contrato_status'],
        ];
    }

    /**
     * Ajusta acréscimo (juros/multa), desconto e observações.
     * O valor devido não pode ficar abaixo do que já foi recebido.
     */
    public static function aplicarAjuste(int $id, array $dados, int $usuarioId): void
    {
        $erros       = [];
        $acrescimo   = self::campoDecimal($dados, 'acrescimo', $erros);
        $desconto    = self::campoDecimal($dados, 'desconto', $erros);
        $observacoes = self::campoTexto($dados, 'observacoes', 500, $erros);
        self::lancarSeErros($erros);

        self::transacao(static function (PDO $pdo) use ($id, $acrescimo, $desconto, $observacoes, $usuarioId): void {
            $situacao = self::situacao($id, true);
            if ($situacao === null) {
                throw new ErroValidacao('Parcela não encontrada.', [], 404);
            }
            if ($situacao['cancelada']) {
                throw new ErroValidacao('Parcela cancelada não pode ser ajustada.', [], 409);
            }

            $atual = $pdo->prepare('SELECT valor_original, acrescimo, desconto FROM parcelas WHERE id = :id');
            $atual->execute(['id' => $id]);
            $atual = $atual->fetch();

            $acrescimo ??= $atual['acrescimo'];
            $desconto  ??= $atual['desconto'];
            $devidoC = self::centavos($atual['valor_original']) + self::centavos($acrescimo) - self::centavos($desconto);
            if ($devidoC < $situacao['recebido_c']) {
                throw new ErroValidacao('Com esse desconto, o valor da parcela ficaria menor que o já recebido.', ['desconto' => 'Desconto maior que o permitido.']);
            }

            $pdo->prepare('UPDATE parcelas SET acrescimo = :a, desconto = :d, observacoes = COALESCE(:o, observacoes) WHERE id = :id')
                ->execute(['a' => $acrescimo, 'd' => $desconto, 'o' => $observacoes, 'id' => $id]);
            self::auditar($usuarioId, 'parcelas', $id, 'AJUSTAR', ['acrescimo' => $acrescimo, 'desconto' => $desconto]);
        });
    }

    /** Cancela uma parcela que não tem pagamento válido (ex.: renegociação). */
    public static function cancelarParcela(int $id, string $motivo, int $usuarioId): void
    {
        self::transacao(static function (PDO $pdo) use ($id, $motivo, $usuarioId): void {
            $situacao = self::situacao($id, true);
            if ($situacao === null) {
                throw new ErroValidacao('Parcela não encontrada.', [], 404);
            }
            if ($situacao['recebido_c'] > 0) {
                throw new ErroValidacao('A parcela tem pagamento registrado. Estorne o pagamento antes de cancelar.', [], 409);
            }

            $pdo->prepare('UPDATE parcelas SET cancelada = 1, observacoes = :motivo WHERE id = :id')
                ->execute(['motivo' => mb_substr(trim($motivo), 0, 500) ?: null, 'id' => $id]);
            self::auditar($usuarioId, 'parcelas', $id, 'CANCELAR', ['motivo' => $motivo]);
        });
    }

    // -------------------------------------------------------------------------

    /** Dia $dia do mês de $mes; em meses mais curtos usa o último dia (ex.: 31 → 30/02 vira 28/02). */
    private static function diaDoMes(DateTimeImmutable $mes, int $dia): DateTimeImmutable
    {
        return $mes->setDate((int) $mes->format('Y'), (int) $mes->format('m'), min($dia, (int) $mes->format('t')));
    }

    private static function formatar(array $p): array
    {
        return [
            'id'                  => (int) $p['id'],
            'contrato_id'         => (int) $p['contrato_id'],
            'contrato'            => $p['contrato'],
            'cliente_id'          => (int) $p['cliente_id'],
            'cliente'             => $p['cliente'],
            'cpf_cnpj'            => $p['cpf_cnpj'],
            'email'               => $p['email'],
            'numero'              => (int) $p['numero'],
            'competencia'         => $p['competencia'],
            'vencimento'          => $p['vencimento'],
            'valor_original'      => (float) $p['valor_original'],
            'acrescimo'           => (float) $p['acrescimo'],
            'desconto'            => (float) $p['desconto'],
            'valor_devido'        => (float) $p['valor_devido'],
            'valor_recebido'      => (float) $p['valor_recebido'],
            'saldo'               => max(0.0, (float) $p['saldo']),
            'status'              => $p['status'],
            'prioridade'          => $p['prioridade'],
            'ultima_forma'        => $p['ultima_forma'] !== null ? (self::FORMAS_TELA[$p['ultima_forma']] ?? $p['ultima_forma']) : null,
            'ultimo_pagamento_em' => $p['ultimo_pagamento_em'],
            'observacoes'         => $p['observacoes'],
        ];
    }
}
