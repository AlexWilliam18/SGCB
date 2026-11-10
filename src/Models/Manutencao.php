<?php
/**
 * Manutenções dos contêineres (tabela manutencoes), com o período bloqueado na agenda_unidades.
 *
 * Ciclo: AGENDADA → EM_ANDAMENTO → CONCLUIDA (ou CANCELADA).
 * A tela (manutencoes.js) chama EM_ANDAMENTO de "EM_MANUTENCAO": a conversão é feita aqui.
 */

final class Manutencao extends Modelo
{
    public const PRIORIDADES = ['BAIXA' => 'Baixa', 'MEDIA' => 'Média', 'ALTA' => 'Alta', 'URGENTE' => 'Urgente'];

    /** Tipos oferecidos no cadastro (a coluna tipo é texto livre; aqui fica a lista padrão). */
    public const TIPOS = ['Preventiva', 'Corretiva', 'Vistoria', 'Limpeza', 'Pintura', 'Reforma'];

    /** Status do banco → status usado pela tela. */
    private const STATUS_TELA = ['EM_ANDAMENTO' => 'EM_MANUTENCAO'];

    /** Colunas comuns à listagem e aos detalhes. */
    private const COLUNAS = 'm.id, u.codigo, p.nome AS produto, u.localidade, m.tipo, m.status, m.prioridade, m.data_prevista,
                             u.ultima_vistoria_em, m.iniciada_em, m.concluida_em, r.nome AS responsavel_usuario,
                             m.responsavel_usuario_id, m.responsavel_externo, m.custo, m.descricao, m.unidade_id, m.criado_em';

    private const JUNCOES = 'FROM manutencoes m
                             JOIN unidades_fisicas u ON u.id = m.unidade_id
                             JOIN produtos p ON p.id = u.produto_id
                             LEFT JOIN usuarios r ON r.id = m.responsavel_usuario_id';

    /**
     * Listagem da tela Manutenções.
     * Filtros: q (código, contêiner, tipo, descrição ou responsável), prioridade, status, localidade, data (dia previsto), page.
     *
     * @return array{items: array, localidades: array, metrics: array, total: int, page: int, pages: int}
     */
    public static function listarManutencoes(array $filtros = []): array
    {
        $where  = ['1 = 1'];
        $params = [];

        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(u.codigo LIKE :q1 OR p.nome LIKE :q2 OR m.tipo LIKE :q3 OR m.descricao LIKE :q4 OR r.nome LIKE :q5 OR m.responsavel_externo LIKE :q6)';
            for ($i = 1; $i <= 6; $i++) {
                $params["q$i"] = "%$q%";
            }
        }
        $prioridade = strtoupper((string) ($filtros['prioridade'] ?? ''));
        if (isset(self::PRIORIDADES[$prioridade])) {
            $where[]              = 'm.prioridade = :prioridade';
            $params['prioridade'] = $prioridade;
        }
        // Status: só os valores do ENUM da tabela (lista branca); qualquer outro valor é ignorado.
        $status = strtoupper((string) ($filtros['status'] ?? ''));
        if (in_array($status, ['AGENDADA', 'EM_ANDAMENTO', 'CONCLUIDA', 'CANCELADA'], true)) {
            $where[]          = 'm.status = :status';
            $params['status'] = $status;
        }
        $localidade = trim((string) ($filtros['localidade'] ?? ''));
        if ($localidade !== '') {
            $where[]              = 'u.localidade = :localidade';
            $params['localidade'] = $localidade;
        }
        $erros = [];
        $data  = self::campoData($filtros, 'data', $erros);
        if ($data !== null) {
            // Intervalo do dia inteiro (usa o índice da coluna melhor que DATE(m.data_prevista)).
            $where[]           = 'm.data_prevista >= :dia1 AND m.data_prevista < :dia2';
            $params['dia1']    = $data . ' 00:00:00';
            $params['dia2']    = date('Y-m-d 00:00:00', strtotime($data . ' +1 day'));
        }

        // Abertas primeiro (urgentes no topo), depois as mais recentes.
        $resultado = self::paginar(
            self::COLUNAS,
            self::JUNCOES . ' WHERE ' . implode(' AND ', $where),
            $params,
            "FIELD(m.status, 'EM_ANDAMENTO', 'AGENDADA', 'CONCLUIDA', 'CANCELADA'),
             FIELD(m.prioridade, 'URGENTE', 'ALTA', 'MEDIA', 'BAIXA'), m.data_prevista DESC, m.id DESC",
            (int) ($filtros['page'] ?? 1)
        );
        $resultado['items']       = array_map([self::class, 'formatar'], $resultado['items']);
        $resultado['localidades'] = self::listarLocalidades();
        $resultado['metrics']     = self::indicadores();

        return $resultado;
    }

    /** Indicadores dos cards (globais, sem os filtros da lista). */
    public static function indicadores(): array
    {
        $consulta = self::pdo()->prepare(
            "SELECT COALESCE(SUM(status = 'EM_ANDAMENTO'), 0) AS em_manutencao,
                    COALESCE(SUM(status = 'AGENDADA'), 0) AS agendadas,
                    COALESCE(SUM(prioridade = 'URGENTE' AND status IN ('AGENDADA', 'EM_ANDAMENTO')), 0) AS urgentes,
                    COALESCE(SUM(status = 'CONCLUIDA' AND concluida_em >= :mes1 AND concluida_em < :mes2), 0) AS concluidas
               FROM manutencoes"
        );
        $consulta->execute(['mes1' => date('Y-m-01 00:00:00'), 'mes2' => date('Y-m-01 00:00:00', strtotime('first day of next month'))]);

        return array_map('intval', $consulta->fetch());
    }

    /** Localidades dos contêineres que têm manutenção (filtro da listagem). */
    public static function listarLocalidades(): array
    {
        return self::pdo()->query(
            "SELECT DISTINCT u.localidade FROM manutencoes m JOIN unidades_fisicas u ON u.id = m.unidade_id
              WHERE u.localidade IS NOT NULL AND u.localidade <> '' ORDER BY u.localidade"
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function buscarManutencao(int $id): ?array
    {
        $consulta = self::pdo()->prepare('SELECT ' . self::COLUNAS . ' ' . self::JUNCOES . ' WHERE m.id = :id');
        $consulta->execute(['id' => $id]);
        $linha = $consulta->fetch();

        return $linha ? self::formatar($linha) : null;
    }

    /** Opções do cadastro de manutenção (cadastro-manutencao.js), sem o csrf_token, que a API acrescenta. */
    public static function opcoes(): array
    {
        $comoOpcao = static fn (string $valor, string $nome): array => ['valor' => $valor, 'nome' => $nome];

        return [
            'unidades'     => UnidadeFisica::listarParaManutencao(),
            'responsaveis' => Usuario::listarResponsaveis(),
            'tipos'        => array_map($comoOpcao, self::TIPOS, self::TIPOS),
            'prioridades'  => array_map($comoOpcao, array_keys(self::PRIORIDADES), self::PRIORIDADES),
        ];
    }

    /**
     * Agenda uma manutenção (POST /api/manutencoes).
     * Campos: unidade_id, tipo, prioridade, data_prevista (AAAA-MM-DD), responsavel_usuario_id, descricao.
     * Bloqueia o dia previsto na agenda do contêiner. 409 se já houver manutenção aberta igual
     * ou se o contêiner estiver reservado/locado nesse dia.
     *
     * @return int id da manutenção.
     */
    public static function cadastrarManutencao(array $dados, int $usuarioId): int
    {
        $erros = [];
        $unidadeId     = self::campoInteiro($dados, 'unidade_id', $erros, true, 1);
        $tipo          = self::campoTexto($dados, 'tipo', 100, $erros, true);
        $prioridade    = self::campoOpcao($dados, 'prioridade', array_keys(self::PRIORIDADES), $erros, true);
        $dataPrevista  = self::campoData($dados, 'data_prevista', $erros, true);
        $responsavelId = self::campoInteiro($dados, 'responsavel_usuario_id', $erros, true, 1);
        $externo       = self::campoTexto($dados, 'responsavel_externo', 150, $erros);
        $descricao     = self::campoTexto($dados, 'descricao', 5000, $erros);

        if ($dataPrevista !== null && $dataPrevista < self::hoje()) {
            $erros['data_prevista'] = 'A data prevista não pode ser anterior a hoje.';
        }
        $unidade = $unidadeId !== null ? UnidadeFisica::buscar($unidadeId) : null;
        if ($unidadeId !== null && $unidade === null) {
            $erros['unidade_id'] = 'Selecione um contêiner válido.';
        }
        if ($responsavelId !== null) {
            $responsavel = Usuario::buscarPorId($responsavelId);
            if ($responsavel === null || !$responsavel['ativo']) {
                $erros['responsavel_usuario_id'] = 'Selecione um responsável ativo.';
            }
        }
        self::lancarSeErros($erros, 'Confira os campos destacados.');

        return self::transacao(static function (PDO $pdo) use ($unidade, $tipo, $prioridade, $dataPrevista, $responsavelId, $externo, $descricao, $usuarioId): int {
            UnidadeFisica::bloquearProduto($unidade['produto_id']);

            // Mesma manutenção já aberta para o contêiner = provável clique duplo ou duplicidade.
            $duplicada = $pdo->prepare(
                "SELECT 1 FROM manutencoes WHERE unidade_id = :u AND tipo = :t AND status IN ('AGENDADA', 'EM_ANDAMENTO')"
            );
            $duplicada->execute(['u' => $unidade['id'], 't' => $tipo]);
            if ($duplicada->fetchColumn() !== false) {
                throw new ErroValidacao('Já existe uma manutenção deste tipo aberta para este contêiner.', ['tipo' => 'Manutenção já aberta.'], 409);
            }

            [$inicio, $fim] = Produto::periodoAgenda($dataPrevista, $dataPrevista);
            $ocupado = $pdo->prepare(
                "SELECT 1 FROM agenda_unidades WHERE unidade_id = :u AND status IN ('RESERVADA', 'EM_USO') AND inicio < :fim AND fim > :inicio"
            );
            $ocupado->execute(['u' => $unidade['id'], 'fim' => $fim, 'inicio' => $inicio]);
            if ($ocupado->fetchColumn() !== false) {
                throw new ErroValidacao('O contêiner está reservado ou locado nesta data.', ['data_prevista' => 'Contêiner ocupado nesta data.'], 409);
            }

            $agora = self::agora();
            $pdo->prepare(
                "INSERT INTO manutencoes (unidade_id, responsavel_usuario_id, criado_por, tipo, prioridade, status, data_prevista,
                                          responsavel_externo, descricao, criado_em)
                 VALUES (:unidade, :responsavel, :por, :tipo, :prioridade, 'AGENDADA', :prevista, :externo, :descricao, :criado)"
            )->execute([
                'unidade' => $unidade['id'], 'responsavel' => $responsavelId, 'por' => $usuarioId, 'tipo' => $tipo,
                'prioridade' => $prioridade, 'prevista' => $inicio, 'externo' => $externo, 'descricao' => $descricao, 'criado' => $agora,
            ]);
            $id = (int) $pdo->lastInsertId();

            UnidadeFisica::agendar([
                'unidade_id' => $unidade['id'], 'produto_id' => $unidade['produto_id'], 'manutencao_id' => $id,
                'criado_por' => $usuarioId, 'inicio' => $inicio, 'fim' => $fim, 'status' => 'MANUTENCAO',
            ]);
            self::auditar($usuarioId, 'manutencoes', $id, 'CRIAR', ['unidade' => $unidade['codigo'], 'tipo' => $tipo, 'prioridade' => $prioridade]);

            return $id;
        });
    }

    /** Começa o serviço: status EM_ANDAMENTO e o contêiner passa para MANUTENCAO. */
    public static function iniciarManutencao(int $id, int $usuarioId): void
    {
        self::transacao(static function (PDO $pdo) use ($id, $usuarioId): void {
            $m = self::bloquear($id, ['AGENDADA']);

            $unidade = UnidadeFisica::buscar((int) $m['unidade_id']);
            if ($unidade['estado_operacional'] === 'LOCADA') {
                throw new ErroValidacao('O contêiner está locado. Finalize a locação antes de iniciar a manutenção.', [], 409);
            }

            $agora = self::agora();
            $pdo->prepare("UPDATE manutencoes SET status = 'EM_ANDAMENTO', iniciada_em = :agora WHERE id = :id")
                ->execute(['agora' => $agora, 'id' => $id]);
            $pdo->prepare("UPDATE unidades_fisicas SET estado_operacional = 'MANUTENCAO' WHERE id = :id")
                ->execute(['id' => $m['unidade_id']]);
            // Enquanto não termina, a agenda bloqueia o contêiner de agora em diante.
            $pdo->prepare('UPDATE agenda_unidades SET inicio = LEAST(inicio, :agora), fim = :fim WHERE manutencao_id = :id')
                ->execute(['agora' => $agora, 'fim' => UnidadeFisica::FIM_INDETERMINADO, 'id' => $id]);

            self::auditar($usuarioId, 'manutencoes', $id, 'INICIAR');
        });
    }

    /**
     * Conclui o serviço: grava custo e observações, libera o contêiner (DISPONIVEL)
     * e registra a data como última vistoria.
     * Campos opcionais: custo, responsavel_externo, descricao (substitui a descrição se enviada).
     */
    public static function concluirManutencao(int $id, array $dados, int $usuarioId): void
    {
        $erros   = [];
        $custo   = self::campoDecimal($dados, 'custo', $erros);
        $externo = self::campoTexto($dados, 'responsavel_externo', 150, $erros);
        $texto   = self::campoTexto($dados, 'descricao', 5000, $erros);
        self::lancarSeErros($erros);

        self::transacao(static function (PDO $pdo) use ($id, $custo, $externo, $texto, $usuarioId): void {
            $m     = self::bloquear($id, ['AGENDADA', 'EM_ANDAMENTO']);
            $agora = self::agora();

            // Sem início registrado, considera que começou agora (o CHECK exige concluida_em >= iniciada_em).
            $pdo->prepare(
                "UPDATE manutencoes SET status = 'CONCLUIDA', iniciada_em = COALESCE(iniciada_em, :agora1), concluida_em = :agora2,
                        custo = COALESCE(:custo, custo), responsavel_externo = COALESCE(:externo, responsavel_externo),
                        descricao = COALESCE(:descricao, descricao)
                  WHERE id = :id"
            )->execute(['agora1' => $agora, 'agora2' => $agora, 'custo' => $custo, 'externo' => $externo, 'descricao' => $texto, 'id' => $id]);

            self::encerrarAgenda($id, $agora);
            self::liberarUnidade((int) $m['unidade_id'], $id, true);
            self::auditar($usuarioId, 'manutencoes', $id, 'CONCLUIR', ['custo' => $custo]);
        });
    }

    /** Cancela uma manutenção ainda aberta e libera a agenda do contêiner. */
    public static function cancelarManutencao(int $id, int $usuarioId): void
    {
        self::transacao(static function (PDO $pdo) use ($id, $usuarioId): void {
            $m = self::bloquear($id, ['AGENDADA', 'EM_ANDAMENTO']);

            $pdo->prepare("UPDATE manutencoes SET status = 'CANCELADA' WHERE id = :id")->execute(['id' => $id]);
            $pdo->prepare("UPDATE agenda_unidades SET status = 'CANCELADA' WHERE manutencao_id = :id")->execute(['id' => $id]);
            self::liberarUnidade((int) $m['unidade_id'], $id, false);
            self::auditar($usuarioId, 'manutencoes', $id, 'CANCELAR');
        });
    }

    // -------------------------------------------------------------------------

    /** Lê a manutenção travando a linha e confere se o status atual permite a ação. */
    private static function bloquear(int $id, array $statusPermitidos): array
    {
        $consulta = self::pdo()->prepare('SELECT id, unidade_id, status FROM manutencoes WHERE id = :id FOR UPDATE');
        $consulta->execute(['id' => $id]);
        $m = $consulta->fetch();

        if (!$m) {
            throw new ErroValidacao('Manutenção não encontrada.', [], 404);
        }
        if (!in_array($m['status'], $statusPermitidos, true)) {
            throw new ErroValidacao('Esta ação não é permitida para uma manutenção ' . strtolower(str_replace('_', ' ', $m['status'])) . '.', [], 409);
        }

        return $m;
    }

    /**
     * Termina o bloqueio da agenda no momento da conclusão.
     * Se concluiu antes do período previsto começar, o bloqueio é cancelado.
     */
    private static function encerrarAgenda(int $manutencaoId, string $agora): void
    {
        $pdo = self::pdo();
        $pdo->prepare("UPDATE agenda_unidades SET status = 'CANCELADA' WHERE manutencao_id = :id AND inicio >= :agora")
            ->execute(['id' => $manutencaoId, 'agora' => $agora]);
        $pdo->prepare("UPDATE agenda_unidades SET fim = :agora1 WHERE manutencao_id = :id AND status = 'MANUTENCAO' AND inicio < :agora2")
            ->execute(['agora1' => $agora, 'id' => $manutencaoId, 'agora2' => $agora]);
    }

    /** Volta o contêiner para DISPONIVEL se ele estava em manutenção e não há outra em andamento. */
    private static function liberarUnidade(int $unidadeId, int $manutencaoId, bool $vistoria): void
    {
        $pdo = self::pdo();
        $outra = $pdo->prepare("SELECT 1 FROM manutencoes WHERE unidade_id = :u AND id <> :m AND status = 'EM_ANDAMENTO'");
        $outra->execute(['u' => $unidadeId, 'm' => $manutencaoId]);
        if ($outra->fetchColumn() === false) {
            $pdo->prepare("UPDATE unidades_fisicas SET estado_operacional = 'DISPONIVEL' WHERE id = :id AND estado_operacional = 'MANUTENCAO'")
                ->execute(['id' => $unidadeId]);
        }
        if ($vistoria) {
            $pdo->prepare('UPDATE unidades_fisicas SET ultima_vistoria_em = :agora WHERE id = :id')
                ->execute(['agora' => self::agora(), 'id' => $unidadeId]);
        }
    }

    private static function formatar(array $m): array
    {
        $m['id']                     = (int) $m['id'];
        $m['unidade_id']             = (int) $m['unidade_id'];
        $m['responsavel_usuario_id'] = $m['responsavel_usuario_id'] !== null ? (int) $m['responsavel_usuario_id'] : null;
        $m['status']                 = self::STATUS_TELA[$m['status']] ?? $m['status'];
        $m['custo']                  = $m['custo'] !== null ? (float) $m['custo'] : null;

        return $m;
    }
}
