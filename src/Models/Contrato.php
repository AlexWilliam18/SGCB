<?php
/**
 * Contratos de locação (tabelas contratos, contrato_enderecos_entrega, contrato_itens e agenda_unidades).
 *
 * Ciclo:
 *   criarContrato()     → RASCUNHO com os itens e valores calculados (ainda não reserva nada);
 *   gerarContrato()     → ATIVO: reserva os contêineres, numera, grava os snapshots e gera as parcelas;
 *   iniciarLocacao()    → entrega: as reservas viram EM_USO e os contêineres ficam LOCADA;
 *   finalizarContrato() → devolução: contêineres voltam para DISPONIVEL;
 *   cancelarContrato()  → libera as reservas e cancela as parcelas sem pagamento.
 *
 * Valor de cada item = valor da periodicidade × quantidade × número de períodos − desconto + outros.
 * Número de períodos (prazo determinado) = dias de locação ÷ dias da periodicidade, mínimo 1.
 * Dias de locação = data final − data inicial (mínimo 1), mesma regra da primeira versão da classe Contrato;
 * com contagem "UTEIS" nas preferências, só contam os dias de segunda a sexta.
 */

final class Contrato extends Modelo
{
    public const STATUS = ['RASCUNHO', 'ATIVO', 'FINALIZADO', 'CANCELADO'];

    /** Dias de cada periodicidade (base do número de períodos e do intervalo das parcelas). */
    public const DIAS_PERIODO = ['DIARIA' => 1, 'SEMANAL' => 7, 'QUINZENAL' => 15, 'MENSAL' => 30];

    /**
     * Calcula itens e total sem gravar nada (método calcularValorTotal do diagrama).
     * Serve para a tela mostrar o resumo antes de salvar. Recebe os mesmos dados de criarContrato().
     *
     * @return array{itens: array, numero_periodos: float, valor_itens: float, frete_deslocamento: float,
     *               outros_valores: float, caucao: float, valor_total: float}
     */
    public static function calcularValorTotal(array $dados): array
    {
        $calculo = self::validar($dados);

        return [
            'itens'              => array_map([self::class, 'itemParaTela'], $calculo['itens']),
            'numero_periodos'    => (float) ($calculo['itens'][0]['numero_periodos'] ?? 0),
            'valor_itens'        => (float) ($calculo['valor_itens_c'] / 100),
            'frete_deslocamento' => (float) $calculo['contrato']['frete_deslocamento'],
            'outros_valores'     => (float) $calculo['contrato']['outros_valores'],
            'caucao'             => (float) $calculo['contrato']['caucao'],
            'valor_total'        => (float) ($calculo['total_c'] / 100),
        ];
    }

    /**
     * Grava o contrato como RASCUNHO.
     *
     * Campos: cliente_id, entrega_origem (CLIENTE|OUTRO), entrega { cep, rua, numero, bairro, cidade, uf,
     * complemento, referencia }, condicao_pagamento_id, data_emissao, prazo (DETERMINADO|INDETERMINADO),
     * data_inicio, data_fim, periodicidade, dia_vencimento, primeiro_vencimento, numero_parcelas,
     * frete_deslocamento, outros_valores, caucao, observacoes, numero_contrato/numero_locacao (só sem numeração automática)
     * e itens[]: { produto_id, quantidade, periodicidade (opcional), desconto, outros }.
     *
     * @return int id do contrato.
     */
    public static function criarContrato(array $dados, int $usuarioId): int
    {
        $calculo = self::validar($dados);

        return self::transacao(static function (PDO $pdo) use ($calculo, $usuarioId): int {
            $contrato = $calculo['contrato'] + ['criado_por' => $usuarioId, 'criado_em' => self::agora(), 'status' => 'RASCUNHO'];
            $colunas  = array_keys($contrato);
            $pdo->prepare('INSERT INTO contratos (' . implode(', ', $colunas) . ') VALUES (:' . implode(', :', $colunas) . ')')
                ->execute($contrato);
            $id = (int) $pdo->lastInsertId();

            self::gravarItens($id, $calculo['itens']);
            self::gravarEntrega($id, $calculo['entrega']);
            self::auditar($usuarioId, 'contratos', $id, 'CRIAR', ['valor_total' => self::reais($calculo['total_c'])]);

            return $id;
        });
    }

    /**
     * Cadastro da tela "Novo contrato" com status inicial ativo: cria o rascunho e já gera,
     * tudo na mesma transação (se a geração falhar, o rascunho também é desfeito).
     * Os contêineres escolhidos na tela (itens[].unidades) são os reservados.
     *
     * @return array contrato gerado (buscarContrato).
     */
    public static function criarEGerarContrato(array $dados, int $usuarioId): array
    {
        $unidades = self::validar($dados)['unidades'];

        return self::transacao(static function () use ($dados, $usuarioId, $unidades): array {
            $id = self::criarContrato($dados, $usuarioId);
            return self::gerarContrato($id, $usuarioId, $unidades);
        });
    }

    /**
     * Ativa o contrato (método gerarContrato do diagrama). Confere cliente, limite de crédito
     * e estoque no período; reserva os contêineres; numera; grava os snapshots e gera as parcelas.
     *
     * @param array<int, int[]> $unidadesEscolhidas índice do item => ids dos contêineres escolhidos
     *                                              (itens sem escolha recebem os primeiros livres).
     * @return array resumo do contrato gerado (buscarContrato).
     */
    public static function gerarContrato(int $id, int $usuarioId, array $unidadesEscolhidas = []): array
    {
        self::transacao(static function (PDO $pdo) use ($id, $usuarioId, $unidadesEscolhidas): void {
            $contrato = self::bloquear($id, ['RASCUNHO']);
            $hoje     = self::hoje();

            // 1. Cliente ativo.
            $cliente = Cliente::buscarCliente((int) $contrato['cliente_id']);
            if ($cliente === null || !$cliente['ativo'] || $cliente['tipo_cadastro'] === 'FORNECEDOR') {
                throw new ErroValidacao('O cliente deste contrato está inativo ou não é um cliente.', ['cliente_id' => 'Cliente inativo.']);
            }

            // 2. Empresa (cabeçalho e numeração).
            $empresa = Empresa::obterEmpresa();
            if ($empresa === null) {
                throw new ErroValidacao('Cadastre os dados da empresa antes de gerar contratos.', [], 409);
            }
            $preferencias = Empresa::obterPreferencias($empresa['id']);

            // 3. Limite de crédito: saldo em aberto + este contrato não pode passar do limite.
            $itens   = self::listarItens($id);
            $totalC  = self::totalCentavos($contrato, $itens);
            $limiteC = self::centavos($cliente['limite_credito']);
            if ($limiteC > 0 && !$cliente['permite_ultrapassar_limite']) {
                $devedorC = Cliente::saldoDevedorCentavos((int) $contrato['cliente_id']);
                if ($devedorC + $totalC > $limiteC) {
                    throw new ErroValidacao(sprintf(
                        'O contrato ultrapassa o limite de crédito do cliente (limite R$ %s, em aberto R$ %s, contrato R$ %s).',
                        number_format($limiteC / 100, 2, ',', '.'), number_format($devedorC / 100, 2, ',', '.'), number_format($totalC / 100, 2, ',', '.')
                    ), [], 409);
                }
            }

            // 4. Reserva dos contêineres: trava as unidades de cada produto e escolhe as livres no período.
            [$inicio, $fim] = Produto::periodoAgenda($contrato['data_inicio'], $contrato['data_fim']);
            $statusAgenda   = $contrato['data_inicio'] <= $hoje ? 'EM_USO' : 'RESERVADA';
            foreach ($itens as $i => $item) {
                UnidadeFisica::bloquearProduto($item['produto_id']);
                $escolhidas = $unidadesEscolhidas[$i] ?? [];
                if ($escolhidas) {
                    // Contêineres escolhidos na tela: todos precisam continuar livres no período.
                    $ocupadas = array_diff($escolhidas, UnidadeFisica::livresNoPeriodo($item['produto_id'], $inicio, $fim));
                    if ($ocupadas || count($escolhidas) !== $item['quantidade']) {
                        throw new ErroValidacao(
                            "Um ou mais contêineres escolhidos de {$item['produto']} não estão mais livres no período. Atualize a seleção.",
                            ["itens.$i.unidades" => 'Contêiner indisponível no período.'],
                            409
                        );
                    }
                    $livres = $escolhidas;
                } else {
                    $livres = UnidadeFisica::livresNoPeriodo($item['produto_id'], $inicio, $fim, $item['quantidade']);
                }
                if (count($livres) < $item['quantidade']) {
                    throw new ErroValidacao(
                        "Não há {$item['quantidade']} unidade(s) de {$item['produto']} livres no período (livres: " . count($livres) . ').',
                        ["itens.$i.quantidade" => 'Estoque insuficiente no período.'],
                        409
                    );
                }
                foreach ($livres as $unidadeId) {
                    UnidadeFisica::agendar([
                        'unidade_id' => $unidadeId, 'produto_id' => $item['produto_id'], 'contrato_item_id' => $item['id'],
                        'criado_por' => $usuarioId, 'inicio' => $inicio, 'fim' => $fim, 'status' => $statusAgenda,
                    ]);
                }
                if ($statusAgenda === 'EM_USO') {
                    self::marcarUnidades($livres, 'LOCADA');
                }
            }

            // 5. Numeração automática (ou a digitada no rascunho).
            $numeroContrato = $contrato['numero_contrato'] ?: ($preferencias['numeracao_automatica'] ? Empresa::reservarNumero($empresa['id'], 'contrato') : null);
            $numeroLocacao  = $contrato['numero_locacao'] ?: ($preferencias['numeracao_automatica'] ? Empresa::reservarNumero($empresa['id'], 'locacao') : null);
            if ($numeroContrato === null) {
                throw new ErroValidacao('Informe o número do contrato (a numeração automática está desligada).', ['numero_contrato' => 'Preencha este campo.']);
            }

            // 6. Local de entrega: quando é o endereço do cliente, a cópia do contrato é atualizada
            // com o cadastro de hoje (o rascunho pode ter sido feito antes de o cliente mudar o endereço).
            if ($cliente['endereco'] !== null) {
                $pdo->prepare(
                    'UPDATE contrato_enderecos_entrega
                        SET cep = :cep, rua = :rua, numero = :numero, bairro = :bairro, cidade = :cidade, uf = :uf, complemento = :complemento
                      WHERE contrato_id = :id AND endereco_do_cliente = 1'
                )->execute($cliente['endereco'] + ['id' => $id]);
            }

            // Snapshots: o contrato guarda os dados do dia, mesmo que os cadastros mudem depois.
            $dadosCliente = array_intersect_key($cliente, array_flip([
                'id', 'tipo_pessoa', 'nome', 'nome_fantasia', 'cpf_cnpj', 'email', 'telefone', 'nacionalidade',
                'profissao', 'rg', 'inscricao_estadual', 'representante_legal', 'cpf_representante',
            ]));
            $dadosCliente['endereco'] = $cliente['endereco'];
            // Responsável pela assinatura informado na tela substitui o do cadastro.
            if ($contrato['dados_cliente_snapshot'] !== null) {
                $dadosCliente = array_merge($dadosCliente, (array) json_decode($contrato['dados_cliente_snapshot'], true));
            }

            // 7. Parcelas.
            $condicao = $contrato['condicao_pagamento_id'] ? CondicaoPagamento::buscar((int) $contrato['condicao_pagamento_id']) : null;
            Parcela::gerarParcelas($id, $totalC, self::planoVencimentos($contrato, $condicao, $preferencias));

            $pdo->prepare(
                "UPDATE contratos SET status = 'ATIVO', empresa_id = :empresa, numero_contrato = :numero, numero_locacao = :locacao,
                        condicoes_gerais_snapshot = :condicoes, dados_cliente_snapshot = :cliente, dados_empresa_snapshot = :dados_empresa
                  WHERE id = :id"
            )->execute([
                'empresa' => $empresa['id'], 'numero' => $numeroContrato, 'locacao' => $numeroLocacao,
                'condicoes' => $preferencias['condicoes_gerais'], 'cliente' => self::json($dadosCliente),
                'dados_empresa' => self::json($empresa), 'id' => $id,
            ]);

            self::auditar($usuarioId, 'contratos', $id, 'GERAR', ['numero_contrato' => $numeroContrato, 'valor_total' => self::reais($totalC)]);
        });

        return self::buscarContrato($id);
    }

    /** Entrega dos contêineres: reservas do contrato viram EM_USO e as unidades ficam LOCADA. */
    public static function iniciarLocacao(int $id, int $usuarioId): void
    {
        self::transacao(static function () use ($id, $usuarioId): void {
            self::bloquear($id, ['ATIVO']);
            $unidades = self::unidadesDaAgenda($id, ['RESERVADA']);
            if (!$unidades) {
                throw new ErroValidacao('Este contrato não tem reservas aguardando entrega.', [], 409);
            }

            self::alterarAgenda($id, ['RESERVADA'], 'EM_USO');
            self::marcarUnidades($unidades, 'LOCADA');
            self::auditar($usuarioId, 'contratos', $id, 'INICIAR_LOCACAO', ['unidades' => $unidades]);
        });
    }

    /**
     * Devolução dos contêineres (método finalizarContrato do diagrama).
     * As parcelas continuam: o que não foi pago segue em aberto no Financeiro.
     */
    public static function finalizarContrato(int $id, int $usuarioId): void
    {
        self::transacao(static function (PDO $pdo) use ($id, $usuarioId): void {
            $contrato = self::bloquear($id, ['ATIVO']);
            $agora    = self::agora();
            $unidades = self::unidadesDaAgenda($id, ['RESERVADA', 'EM_USO']);

            $pdo->prepare(
                "UPDATE agenda_unidades a JOIN contrato_itens i ON i.id = a.contrato_item_id
                    SET a.status = 'DEVOLVIDA', a.devolvida_em = :agora
                  WHERE i.contrato_id = :id AND a.status IN ('RESERVADA', 'EM_USO')"
            )->execute(['agora' => $agora, 'id' => $id]);
            self::marcarUnidades($unidades, 'DISPONIVEL', 'LOCADA');

            // Prazo indeterminado ganha data final no dia da devolução.
            $dataFim = $contrato['data_fim'] ?? max(self::hoje(), $contrato['data_inicio']);
            $pdo->prepare("UPDATE contratos SET status = 'FINALIZADO', finalizado_em = :agora, data_fim = :fim WHERE id = :id")
                ->execute(['agora' => $agora, 'fim' => $dataFim, 'id' => $id]);

            self::auditar($usuarioId, 'contratos', $id, 'FINALIZAR', ['unidades' => $unidades]);
        });
    }

    /**
     * Cancela o contrato (método cancelarContrato do diagrama): libera reservas e contêineres
     * e cancela as parcelas que não têm pagamento. Parcelas com pagamento ficam para estorno/acerto.
     */
    public static function cancelarContrato(int $id, string $motivo, int $usuarioId): void
    {
        $erros  = [];
        $motivo = self::campoTexto(['motivo' => $motivo], 'motivo', 500, $erros, true);
        self::lancarSeErros($erros);

        self::transacao(static function (PDO $pdo) use ($id, $motivo, $usuarioId): void {
            self::bloquear($id, ['RASCUNHO', 'ATIVO']);
            $unidades = self::unidadesDaAgenda($id, ['RESERVADA', 'EM_USO']);

            self::alterarAgenda($id, ['RESERVADA', 'EM_USO'], 'CANCELADA');
            self::marcarUnidades($unidades, 'DISPONIVEL', 'LOCADA');

            $pdo->prepare(
                'UPDATE parcelas pa SET pa.cancelada = 1
                  WHERE pa.contrato_id = :id
                    AND NOT EXISTS (SELECT 1 FROM pagamentos pg WHERE pg.parcela_id = pa.id AND pg.estornado_em IS NULL)'
            )->execute(['id' => $id]);

            $pdo->prepare("UPDATE contratos SET status = 'CANCELADO', cancelado_em = :agora, motivo_cancelamento = :motivo WHERE id = :id")
                ->execute(['agora' => self::agora(), 'motivo' => $motivo, 'id' => $id]);

            self::auditar($usuarioId, 'contratos', $id, 'CANCELAR', ['motivo' => $motivo, 'unidades' => $unidades]);
        });
    }

    /** Contrato completo: dados, itens com valores, contêineres reservados e parcelas com status. */
    public static function buscarContrato(int $id): ?array
    {
        $consulta = self::pdo()->prepare(
            'SELECT c.*, p.nome_razao_social AS cliente, p.cpf_cnpj, cp.nome AS condicao_pagamento
               FROM contratos c
               JOIN pessoas p ON p.id = c.cliente_id
               LEFT JOIN condicoes_pagamento cp ON cp.id = c.condicao_pagamento_id
              WHERE c.id = :id'
        );
        $consulta->execute(['id' => $id]);
        $contrato = $consulta->fetch();
        if (!$contrato) {
            return null;
        }

        foreach (['id', 'empresa_id', 'cliente_id', 'condicao_pagamento_id', 'criado_por', 'dia_vencimento', 'numero_parcelas'] as $campo) {
            $contrato[$campo] = $contrato[$campo] !== null ? (int) $contrato[$campo] : null;
        }
        foreach (['frete_deslocamento', 'outros_valores', 'caucao'] as $campo) {
            $contrato[$campo] = (float) $contrato[$campo];
        }
        foreach (['dados_cliente_snapshot', 'dados_empresa_snapshot'] as $campo) {
            $contrato[$campo] = $contrato[$campo] !== null ? json_decode($contrato[$campo], true) : null;
        }

        // Local de entrega do contrato (contrato_enderecos_entrega), usado na prévia e na impressão.
        $entrega = self::pdo()->prepare(
            'SELECT endereco_do_cliente, cep, rua, numero, bairro, cidade, uf, complemento, referencia
               FROM contrato_enderecos_entrega WHERE contrato_id = :id'
        );
        $entrega->execute(['id' => $id]);
        $contrato['entrega'] = $entrega->fetch() ?: null;
        if ($contrato['entrega'] !== null) {
            $contrato['entrega']['endereco_do_cliente'] = (bool) $contrato['entrega']['endereco_do_cliente'];
        }

        $itens                 = self::listarItens($id);
        $contrato['itens']     = array_map([self::class, 'itemParaTela'], $itens);
        $contrato['valor_total'] = (float) (self::totalCentavos($contrato, $itens) / 100);
        $contrato['parcelas']  = Parcela::listarPorContrato($id);

        $unidades = self::pdo()->prepare(
            'SELECT a.id AS agenda_id, a.status, a.inicio, a.fim, a.devolvida_em, u.id AS unidade_id, u.codigo, i.produto_id
               FROM agenda_unidades a
               JOIN contrato_itens i ON i.id = a.contrato_item_id
               LEFT JOIN unidades_fisicas u ON u.id = a.unidade_id
              WHERE i.contrato_id = :id ORDER BY u.codigo'
        );
        $unidades->execute(['id' => $id]);
        $contrato['unidades'] = $unidades->fetchAll();

        return $contrato;
    }

    /**
     * Listagem da tela de Contratos de Locação (public/admin/locacoes/index.php).
     * Filtros: q (cliente, CPF/CNPJ ou número), status, data (AAAA-MM-DD dentro do período), page.
     */
    public static function listarContratos(array $filtros = []): array
    {
        $where  = ['1 = 1'];
        $params = [];

        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            $partes = ['p.nome_razao_social LIKE :q1', 'c.numero_contrato LIKE :q2', 'c.numero_locacao LIKE :q3'];
            $params += ['q1' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%"];
            if (self::somenteDigitos($q) !== '') {
                $partes[]     = 'p.cpf_cnpj LIKE :q4';
                $params['q4'] = '%' . self::somenteDigitos($q) . '%';
            }
            $where[] = '(' . implode(' OR ', $partes) . ')';
        }
        $status = strtoupper((string) ($filtros['status'] ?? ''));
        if (in_array($status, self::STATUS, true)) {
            $where[]          = 'c.status = :status';
            $params['status'] = $status;
        }
        // Período: contratos em andamento na data escolhida (prazo indeterminado não tem data_fim).
        $data = (string) ($filtros['data'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) && checkdate((int) substr($data, 5, 2), (int) substr($data, 8, 2), (int) substr($data, 0, 4))) {
            $where[]          = 'c.data_inicio <= :data1 AND (c.data_fim IS NULL OR c.data_fim >= :data2)';
            $params['data1']  = $data;
            $params['data2']  = $data;
        }

        $resultado = self::paginar(
            'c.id, c.numero_contrato, c.numero_locacao, c.status, c.prazo, c.data_emissao, c.data_inicio, c.data_fim,
             c.periodicidade, p.nome_razao_social AS cliente, p.cpf_cnpj,
             (SELECT COALESCE(SUM(i.quantidade), 0) FROM contrato_itens i WHERE i.contrato_id = c.id) AS itens,
             (SELECT COALESCE(SUM(i.subtotal), 0) FROM contrato_itens i WHERE i.contrato_id = c.id)
               + COALESCE(c.frete_deslocamento, 0) + COALESCE(c.outros_valores, 0) AS valor_total',
            'FROM contratos c JOIN pessoas p ON p.id = c.cliente_id WHERE ' . implode(' AND ', $where),
            $params,
            'c.criado_em DESC, c.id DESC',
            (int) ($filtros['page'] ?? 1)
        );
        $resultado['items'] = array_map(static function (array $c): array {
            $c['id']          = (int) $c['id'];
            $c['itens']       = (int) $c['itens'];
            $c['valor_total'] = (float) $c['valor_total'];
            return $c;
        }, $resultado['items']);

        return $resultado;
    }

    // -------------------------------------------------------------------------
    // Validação e cálculo
    // -------------------------------------------------------------------------

    /**
     * Valida os dados do contrato e calcula os itens.
     *
     * @return array{contrato: array, itens: array, valor_itens_c: int, total_c: int}
     */
    private static function validar(array $dados): array
    {
        $erros = [];

        $clienteId     = self::campoInteiro($dados, 'cliente_id', $erros, true, 1);
        // Local de entrega: CLIENTE copia o endereço cadastral do cliente; OUTRO usa o endereço digitado na tela.
        $origemEntrega = self::campoOpcao($dados, 'entrega_origem', ['CLIENTE', 'OUTRO'], $erros) ?? 'CLIENTE';
        $condicaoId    = self::campoInteiro($dados, 'condicao_pagamento_id', $erros, false, 1);
        $prazo         = self::campoOpcao($dados, 'prazo', ['DETERMINADO', 'INDETERMINADO'], $erros) ?? 'DETERMINADO';
        $dataInicio    = self::campoData($dados, 'data_inicio', $erros, true);
        $dataFim       = self::campoData($dados, 'data_fim', $erros, $prazo === 'DETERMINADO');
        $periodicidade = self::campoOpcao($dados, 'periodicidade', Produto::PERIODICIDADES, $erros, true);
        $diaVenc       = self::campoInteiro($dados, 'dia_vencimento', $erros, false, 1, 31);
        $primeiroVenc  = self::campoData($dados, 'primeiro_vencimento', $erros);
        $parcelas      = self::campoInteiro($dados, 'numero_parcelas', $erros, false, 1, 120);
        $dataEmissao   = self::campoData($dados, 'data_emissao', $erros) ?? self::hoje();
        $observacoes   = self::campoTexto($dados, 'observacoes', 65535, $erros);

        if ($prazo === 'INDETERMINADO') {
            $dataFim = null;
        }
        if ($dataInicio !== null && $dataFim !== null && $dataFim < $dataInicio) {
            $erros['data_fim'] = 'A data final não pode ser antes da inicial.';
        }
        if ($primeiroVenc !== null && $dataInicio !== null && $primeiroVenc < $dataInicio) {
            $erros['primeiro_vencimento'] = 'O primeiro vencimento não pode ser antes do início.';
        }

        // Cliente ativo; a condição padrão dele vale quando o contrato não informa outra.
        $cliente = $clienteId !== null ? Cliente::buscarCliente($clienteId) : null;
        if ($clienteId !== null && ($cliente === null || !$cliente['ativo'] || $cliente['tipo_cadastro'] === 'FORNECEDOR')) {
            $erros['cliente_id'] = 'Selecione um cliente ativo.';
        }
        $condicaoId ??= $cliente['condicao_pagamento_id'] ?? null;
        $condicao     = $condicaoId !== null ? CondicaoPagamento::buscar($condicaoId) : null;
        if ($condicaoId !== null && ($condicao === null || !$condicao['ativo'])) {
            $erros['condicao_pagamento_id'] = 'Selecione uma condição de pagamento ativa.';
        }
        $parcelas ??= $condicao['numero_parcelas_padrao'] ?? 1;

        $empresa      = Empresa::obterEmpresa();
        $preferencias = $empresa !== null ? Empresa::obterPreferencias($empresa['id']) : null;
        $numeroContrato = self::campoTexto($dados, 'numero_contrato', 50, $erros);
        $numeroLocacao  = self::campoTexto($dados, 'numero_locacao', 50, $erros);
        foreach (['numero_contrato' => $numeroContrato, 'numero_locacao' => $numeroLocacao] as $campo => $numero) {
            if ($numero === null) {
                continue;
            }
            $existe = self::pdo()->prepare("SELECT 1 FROM contratos WHERE $campo = :n");
            $existe->execute(['n' => $numero]);
            if ($existe->fetchColumn() !== false) {
                $erros[$campo] = 'Número já usado em outro contrato.';
            }
        }

        $valores = [];
        foreach (['frete_deslocamento', 'outros_valores', 'caucao'] as $campo) {
            $valores[$campo] = self::campoDecimal($dados, $campo, $erros) ?? '0.00';
        }

        $entrega = self::validarEntrega($origemEntrega, $dados['entrega'] ?? null, $cliente, $erros);
        // Responsável pela assinatura (opcional, para pessoa jurídica): nome + CPF juntos.
        $responsavel = self::validarResponsavel($dados['responsavel'] ?? null, $erros);

        // Itens
        $itens  = [];
        $listaItens = is_array($dados['itens'] ?? null) ? array_values($dados['itens']) : [];
        if (!$listaItens) {
            $erros['itens'] = 'Adicione ao menos um produto ao contrato.';
        }
        $periodosPadrao = null;
        if ($dataInicio !== null && $periodicidade !== null && !isset($erros['data_fim'])) {
            $periodosPadrao = self::numeroPeriodos($periodicidade, $prazo, $dataInicio, $dataFim, $parcelas, $preferencias['contagem_diarias'] ?? 'CORRIDOS');
        }
        $unidadesEscolhidas = [];   // índice do item => ids das unidades físicas escolhidas na tela
        $unidadesUsadas     = [];   // evita o mesmo contêiner em dois itens
        foreach ($listaItens as $i => $item) {
            $item = is_array($item) ? $item : [];
            $p    = "itens.$i.";

            // Unidades físicas escolhidas (opcional): sem elas, o sistema escolhe as livres ao gerar.
            $unidades = is_array($item['unidades'] ?? null) ? array_values(array_unique(array_map('intval', $item['unidades']))) : [];
            if ($unidades && trim((string) ($item['quantidade'] ?? '')) === '') {
                $item['quantidade'] = count($unidades);
            }

            $produtoId = self::campoInteiro($item, 'produto_id', $erros, true, 1);
            $qtd       = self::campoInteiro($item, 'quantidade', $erros, true, 1, 1000);
            $perItem   = self::campoOpcao($item, 'periodicidade', Produto::PERIODICIDADES, $erros) ?? $periodicidade;
            $desconto  = self::campoDecimal($item, 'desconto', $erros) ?? '0.00';
            $outros    = self::campoDecimal($item, 'outros', $erros) ?? '0.00';
            // Preço da locação ajustado na tela (opcional); sem ele vale o preço do produto.
            $valorAjustado = self::campoDecimal($item, 'valor_unitario', $erros);
            if ($valorAjustado !== null && self::centavos($valorAjustado) <= 0) {
                $erros['valor_unitario'] = 'Informe um valor maior que zero.';
            }
            // Os erros dos itens voltam como "itens.N.campo".
            foreach (['produto_id', 'quantidade', 'periodicidade', 'desconto', 'outros', 'valor_unitario'] as $campo) {
                if (isset($erros[$campo])) {
                    $erros[$p . $campo] = $erros[$campo];
                    unset($erros[$campo]);
                }
            }
            if ($produtoId === null || $qtd === null || $perItem === null || $periodosPadrao === null) {
                continue;
            }

            $produto = self::pdo()->prepare('SELECT id, codigo, nome, descricao, ativo, valor_total_bem FROM produtos WHERE id = :id');
            $produto->execute(['id' => $produtoId]);
            $produto = $produto->fetch();
            if (!$produto || !$produto['ativo']) {
                $erros[$p . 'produto_id'] = 'Selecione um produto ativo.';
                continue;
            }
            $valorUnitario = $valorAjustado ?? Produto::valorPorPeriodicidade($produtoId, $perItem);
            if ($valorUnitario === null) {
                $erros[$p . 'periodicidade'] = 'O produto não tem preço para esta periodicidade.';
                continue;
            }

            // Unidades escolhidas: precisam ser do produto, não inativas, sem repetir entre itens e na mesma quantidade.
            if ($unidades) {
                $marcas  = implode(', ', array_fill(0, count($unidades), '?'));
                $validas = self::pdo()->prepare(
                    "SELECT COUNT(*) FROM unidades_fisicas WHERE produto_id = ? AND estado_operacional <> 'INATIVA' AND id IN ($marcas)"
                );
                $validas->execute(array_merge([$produtoId], $unidades));
                if ((int) $validas->fetchColumn() !== count($unidades)) {
                    $erros[$p . 'unidades'] = 'Selecione contêineres ativos deste produto.';
                    continue;
                }
                if (array_intersect($unidades, $unidadesUsadas)) {
                    $erros[$p . 'unidades'] = 'O mesmo contêiner foi escolhido em mais de um item.';
                    continue;
                }
                if (count($unidades) !== $qtd) {
                    $erros[$p . 'unidades'] = 'A quantidade precisa ser igual ao número de contêineres escolhidos.';
                    continue;
                }
                $unidadesUsadas          = array_merge($unidadesUsadas, $unidades);
                $unidadesEscolhidas[$i]  = $unidades;
            }

            $periodos = $perItem === $periodicidade
                ? $periodosPadrao
                : self::numeroPeriodos($perItem, $prazo, $dataInicio, $dataFim, $parcelas, $preferencias['contagem_diarias'] ?? 'CORRIDOS');
            // Subtotal em centavos: valor × quantidade × períodos − desconto + outros.
            $brutoC    = (int) round(self::centavos($valorUnitario) * $qtd * $periodos);
            $subtotalC = $brutoC - self::centavos($desconto) + self::centavos($outros);
            if ($subtotalC < 0) {
                $erros[$p . 'desconto'] = 'O desconto não pode ser maior que o valor do item.';
                continue;
            }

            $itens[] = [
                'produto_id'                  => $produtoId,
                'produto'                     => $produto['nome'],
                'descricao_snapshot'          => mb_substr($produto['codigo'] . ' - ' . $produto['nome'] . ($produto['descricao'] ? ' - ' . $produto['descricao'] : ''), 0, 500),
                'valor_unitario_bem_snapshot' => $produto['valor_total_bem'],
                'quantidade'                  => $qtd,
                'periodicidade'               => $perItem,
                'numero_periodos'             => number_format($periodos, 4, '.', ''),
                'valor_unitario'              => $valorUnitario,
                'desconto'                    => $desconto,
                'outros'                      => $outros,
                'subtotal'                    => self::reais($subtotalC),
            ];
        }

        self::lancarSeErros($erros);

        $valorItensC = array_sum(array_map(static fn (array $i): int => self::centavos($i['subtotal']), $itens));
        $totalC      = $valorItensC + self::centavos($valores['frete_deslocamento']) + self::centavos($valores['outros_valores']);

        return [
            'contrato' => [
                'empresa_id'            => $empresa['id'] ?? null,
                'cliente_id'            => $clienteId,
                'condicao_pagamento_id' => $condicaoId,
                'numero_contrato'       => $numeroContrato,
                'numero_locacao'        => $numeroLocacao,
                'data_emissao'          => $dataEmissao,
                'prazo'                 => $prazo,
                'data_inicio'           => $dataInicio,
                'data_fim'              => $dataFim,
                'periodicidade'         => $periodicidade,
                'dia_vencimento'        => $diaVenc,
                'primeiro_vencimento'   => $primeiroVenc,
                'numero_parcelas'       => $parcelas,
                'observacoes'           => $observacoes,
                // Gravado já no rascunho; gerarContrato() o completa com os dados do cadastro.
                'dados_cliente_snapshot' => $responsavel !== null ? self::json($responsavel) : null,
            ] + $valores,
            'entrega'       => $entrega,
            'itens'         => $itens,
            'unidades'      => $unidadesEscolhidas,
            'valor_itens_c' => $valorItensC,
            'total_c'       => $totalC,
        ];
    }

    /**
     * Local de entrega do contrato (linha de contrato_enderecos_entrega, sem o contrato_id).
     *  - CLIENTE: copia o endereço cadastral do cliente; da tela só aproveita a referência.
     *  - OUTRO:   endereço digitado na tela { cep, rua, numero, bairro, cidade, uf, complemento, referencia },
     *             todos obrigatórios menos complemento e referência.
     * Erros voltam como "entrega.campo" (ou "entrega_origem") para a tela marcar o campo certo.
     */
    private static function validarEntrega(string $origem, mixed $entrega, ?array $cliente, array &$erros): ?array
    {
        $entrega = is_array($entrega) ? $entrega : [];

        if ($origem === 'CLIENTE') {
            if ($cliente === null) {
                return null; // o erro do cliente_id já foi registrado
            }
            if ($cliente['endereco'] === null) {
                $erros['entrega_origem'] = 'Este cliente não tem endereço cadastrado. Informe outro endereço de entrega.';
                return null;
            }
            $referencia = self::campoTexto($entrega, 'referencia', 150, $erros);
            if (isset($erros['referencia'])) {
                $erros['entrega.referencia'] = $erros['referencia'];
                unset($erros['referencia']);
            }

            return ['endereco_do_cliente' => 1] + $cliente['endereco'] + ['referencia' => $referencia];
        }

        $e = ['endereco_do_cliente' => 0];
        $campos = ['rua' => 200, 'numero' => 20, 'bairro' => 100, 'cidade' => 100];
        foreach ($campos as $campo => $maximo) {
            $e[$campo] = self::campoTexto($entrega, $campo, $maximo, $erros, true);
        }
        $e['complemento'] = self::campoTexto($entrega, 'complemento', 150, $erros);
        $e['referencia']  = self::campoTexto($entrega, 'referencia', 150, $erros);

        $e['cep'] = self::somenteDigitos((string) ($entrega['cep'] ?? ''));
        if (strlen($e['cep']) !== 8) {
            $erros['cep'] = 'Informe um CEP com 8 dígitos.';
        }
        $e['uf'] = strtoupper(trim((string) ($entrega['uf'] ?? '')));
        if (!preg_match('/^(AC|AL|AP|AM|BA|CE|DF|ES|GO|MA|MT|MS|MG|PA|PB|PR|PE|PI|RJ|RN|RS|RO|RR|SC|SP|SE|TO)$/', $e['uf'])) {
            $erros['uf'] = 'Selecione o estado.';
        }

        // Prefixo "entrega." nos erros para a tela marcar o campo certo.
        foreach (['rua', 'numero', 'bairro', 'cidade', 'complemento', 'referencia', 'cep', 'uf'] as $campo) {
            if (isset($erros[$campo])) {
                $erros['entrega.' . $campo] = $erros[$campo];
                unset($erros[$campo]);
            }
        }

        return $e;
    }

    /** Responsável pela assinatura: { nome, cpf } (os dois juntos) ou nada. */
    private static function validarResponsavel(mixed $responsavel, array &$erros): ?array
    {
        $nome = is_array($responsavel) ? trim((string) ($responsavel['nome'] ?? '')) : '';
        $cpf  = is_array($responsavel) ? self::somenteDigitos((string) ($responsavel['cpf'] ?? '')) : '';
        if ($nome === '' && $cpf === '') {
            return null;
        }
        if ($nome === '' || mb_strlen($nome) > 100) {
            $erros['responsavel.nome'] = 'Informe o nome do responsável (até 100 caracteres).';
        }
        if (strlen($cpf) !== 11 || !Cliente::validarCpfCnpj($cpf)) {
            $erros['responsavel.cpf'] = 'Informe um CPF válido.';
        }

        return ['representante_legal' => $nome, 'cpf_representante' => $cpf];
    }

    /**
     * Quantos períodos são cobrados.
     * Prazo indeterminado: um período por parcela. Determinado: dias ÷ dias da periodicidade (mínimo 1).
     */
    public static function numeroPeriodos(string $periodicidade, string $prazo, string $dataInicio, ?string $dataFim, int $parcelas, string $contagem = 'CORRIDOS'): float
    {
        if ($prazo === 'INDETERMINADO' || $dataFim === null) {
            return (float) $parcelas;
        }

        $dias = self::diasLocacao($dataInicio, $dataFim, $contagem);

        return max(1.0, round($dias / self::DIAS_PERIODO[$periodicidade], 4));
    }

    /** Dias entre as datas (mínimo 1). Com 'UTEIS', conta só segunda a sexta. */
    public static function diasLocacao(string $dataInicio, string $dataFim, string $contagem = 'CORRIDOS'): int
    {
        $inicio = new DateTimeImmutable($dataInicio);
        $fim    = new DateTimeImmutable($dataFim);

        if ($contagem !== 'UTEIS') {
            return max(1, (int) $inicio->diff($fim)->days);
        }

        $dias = 0;
        for ($d = $inicio; $d < $fim; $d = $d->modify('+1 day')) {
            if ((int) $d->format('N') <= 5) {
                $dias++;
            }
        }

        return max(1, $dias);
    }

    /**
     * Regras dos vencimentos das parcelas:
     * - primeiro: informado no contrato; senão início + dias da condição de pagamento; senão a data de início;
     * - intervalo: o da condição; senão os dias da periodicidade (diária usa 30, uma parcela por mês);
     * - regra DIA_FIXO + dia_vencimento: todas no mesmo dia do mês.
     */
    private static function planoVencimentos(array $contrato, ?array $condicao, array $preferencias): array
    {
        $primeiro = $contrato['primeiro_vencimento']
            ?: date('Y-m-d', strtotime($contrato['data_inicio'] . ' +' . ($condicao['dias_primeiro_vencimento'] ?? 0) . ' days'));

        $intervalo = ($condicao['intervalo_dias'] ?? 0) ?: (self::DIAS_PERIODO[$contrato['periodicidade']] ?? 30);
        if ($contrato['periodicidade'] === 'DIARIA' && !($condicao['intervalo_dias'] ?? 0)) {
            $intervalo = 30;
        }

        $diaFixo = $preferencias['regra_vencimento'] === 'DIA_FIXO' && $contrato['dia_vencimento'] ? (int) $contrato['dia_vencimento'] : null;

        return [
            'quantidade' => max(1, (int) $contrato['numero_parcelas']),
            'primeiro'   => $primeiro,
            'intervalo'  => $intervalo,
            'dia_fixo'   => $diaFixo,
        ];
    }

    // -------------------------------------------------------------------------

    /** Lê o contrato travando a linha e confere o status. */
    private static function bloquear(int $id, array $statusPermitidos): array
    {
        $consulta = self::pdo()->prepare('SELECT * FROM contratos WHERE id = :id FOR UPDATE');
        $consulta->execute(['id' => $id]);
        $contrato = $consulta->fetch();

        if (!$contrato) {
            throw new ErroValidacao('Contrato não encontrado.', [], 404);
        }
        if (!in_array($contrato['status'], $statusPermitidos, true)) {
            throw new ErroValidacao('Esta ação não é permitida para um contrato ' . strtolower($contrato['status']) . '.', [], 409);
        }

        return $contrato;
    }

    /** Grava o local de entrega do contrato (um por contrato: contrato_id é UNIQUE). */
    private static function gravarEntrega(int $contratoId, array $e): void
    {
        self::pdo()->prepare(
            'INSERT INTO contrato_enderecos_entrega
                    (contrato_id, endereco_do_cliente, cep, rua, numero, bairro, cidade, uf, complemento, referencia)
             VALUES (:contrato, :do_cliente, :cep, :rua, :numero, :bairro, :cidade, :uf, :complemento, :referencia)'
        )->execute([
            'contrato' => $contratoId, 'do_cliente' => $e['endereco_do_cliente'], 'cep' => $e['cep'], 'rua' => $e['rua'],
            'numero' => $e['numero'], 'bairro' => $e['bairro'], 'cidade' => $e['cidade'], 'uf' => $e['uf'],
            'complemento' => $e['complemento'], 'referencia' => $e['referencia'],
        ]);
    }

    private static function gravarItens(int $contratoId, array $itens): void
    {
        $insere = self::pdo()->prepare(
            'INSERT INTO contrato_itens (contrato_id, produto_id, descricao_snapshot, valor_unitario_bem_snapshot, quantidade,
                                         periodicidade, numero_periodos, valor_unitario, desconto, outros, subtotal)
             VALUES (:contrato, :produto_id, :descricao_snapshot, :valor_unitario_bem_snapshot, :quantidade,
                     :periodicidade, :numero_periodos, :valor_unitario, :desconto, :outros, :subtotal)'
        );
        foreach ($itens as $item) {
            unset($item['produto']); // nome só para mensagens; não é coluna
            $insere->execute($item + ['contrato' => $contratoId]);
        }
    }

    private static function listarItens(int $contratoId): array
    {
        $consulta = self::pdo()->prepare(
            'SELECT i.*, p.nome AS produto FROM contrato_itens i JOIN produtos p ON p.id = i.produto_id
              WHERE i.contrato_id = :id ORDER BY i.id'
        );
        $consulta->execute(['id' => $contratoId]);

        return array_map(static function (array $i): array {
            $i['id']         = (int) $i['id'];
            $i['produto_id'] = (int) $i['produto_id'];
            $i['quantidade'] = (int) $i['quantidade'];
            return $i;
        }, $consulta->fetchAll());
    }

    /** Total a receber = itens + frete + outros valores (a caução é controlada à parte). */
    private static function totalCentavos(array $contrato, array $itens): int
    {
        return array_sum(array_map(static fn (array $i): int => self::centavos($i['subtotal']), $itens))
            + self::centavos($contrato['frete_deslocamento']) + self::centavos($contrato['outros_valores']);
    }

    private static function itemParaTela(array $i): array
    {
        return [
            'id'              => isset($i['id']) ? (int) $i['id'] : null,
            'produto_id'      => (int) $i['produto_id'],
            'produto'         => $i['produto'] ?? null,
            'descricao'       => $i['descricao_snapshot'],
            'quantidade'      => (int) $i['quantidade'],
            'periodicidade'   => $i['periodicidade'],
            'numero_periodos' => (float) $i['numero_periodos'],
            'valor_unitario'  => (float) $i['valor_unitario'],
            'desconto'        => (float) $i['desconto'],
            'outros'          => (float) $i['outros'],
            'subtotal'        => (float) $i['subtotal'],
        ];
    }

    /** Ids das unidades do contrato com a agenda em um dos status. */
    private static function unidadesDaAgenda(int $contratoId, array $status): array
    {
        $marcas   = implode(', ', array_fill(0, count($status), '?'));
        $consulta = self::pdo()->prepare(
            "SELECT DISTINCT a.unidade_id FROM agenda_unidades a JOIN contrato_itens i ON i.id = a.contrato_item_id
              WHERE i.contrato_id = ? AND a.unidade_id IS NOT NULL AND a.status IN ($marcas)"
        );
        $consulta->execute(array_merge([$contratoId], $status));

        return array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function alterarAgenda(int $contratoId, array $de, string $para): void
    {
        $marcas = implode(', ', array_fill(0, count($de), '?'));
        self::pdo()->prepare(
            "UPDATE agenda_unidades a JOIN contrato_itens i ON i.id = a.contrato_item_id
                SET a.status = ? WHERE i.contrato_id = ? AND a.status IN ($marcas)"
        )->execute(array_merge([$para, $contratoId], $de));
    }

    /** Muda o estado das unidades (opcionalmente só as que estão em $somenteDe). */
    private static function marcarUnidades(array $unidades, string $estado, ?string $somenteDe = null): void
    {
        if (!$unidades) {
            return;
        }
        $marcas = implode(', ', array_fill(0, count($unidades), '?'));
        $sql    = "UPDATE unidades_fisicas SET estado_operacional = ? WHERE id IN ($marcas)";
        $params = array_merge([$estado], $unidades);
        if ($somenteDe !== null) {
            $sql     .= ' AND estado_operacional = ?';
            $params[] = $somenteDe;
        }
        self::pdo()->prepare($sql)->execute($params);
    }
}
