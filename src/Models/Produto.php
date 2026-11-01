<?php
/**
 * Produto = MODELO de contêiner que pode ser alugado (tabela produtos), com preços por
 * periodicidade (precos_produto). Cada contêiner real fica em unidades_fisicas (classe UnidadeFisica).
 *
 * No diagrama de classes o Produto tinha a quantidade em estoque em um campo;
 * agora o estoque é a contagem das unidades físicas de cada estado.
 */

final class Produto extends Modelo
{
    public const PERIODICIDADES = ['DIARIA', 'SEMANAL', 'QUINZENAL', 'MENSAL'];

    /** Campo do formulário (cadastro-produto) → periodicidade gravada em precos_produto. */
    private const CAMPOS_PRECO = [
        'diaria'    => 'DIARIA',
        'semanal'   => 'SEMANAL',
        'quinzenal' => 'QUINZENAL',
        'mensal'    => 'MENSAL',
    ];

    /** Contagem das unidades por estado, juntada às consultas de produto. */
    private const SQL_ESTOQUE = "LEFT JOIN (
            SELECT produto_id,
                   COUNT(*)                                  AS total,
                   SUM(estado_operacional = 'DISPONIVEL')   AS disponivel,
                   SUM(estado_operacional = 'LOCADA')       AS alugado,
                   SUM(estado_operacional = 'MANUTENCAO')   AS manutencao,
                   SUM(estado_operacional = 'INATIVA')      AS inativa
              FROM unidades_fisicas GROUP BY produto_id
        ) est ON est.produto_id = p.id";

    /**
     * Listagem da tela Produtos (produtos.js).
     * Filtros: q (nome ou código), status (ativo|inativo), categoria (id), page.
     *
     * @return array{items: array, categories: array, total: int, page: int, pages: int}
     */
    public static function listarProdutos(array $filtros = []): array
    {
        $where  = ['1 = 1'];
        $params = [];

        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.nome LIKE :q1 OR p.codigo LIKE :q2)';
            $params += ['q1' => "%$q%", 'q2' => "%$q%"];
        }
        if (($filtros['status'] ?? '') === 'ativo') {
            $where[] = 'p.ativo = 1';
        } elseif (($filtros['status'] ?? '') === 'inativo') {
            $where[] = 'p.ativo = 0';
        }
        if (ctype_digit((string) ($filtros['categoria'] ?? ''))) {
            $where[]             = 'p.categoria_id = :categoria';
            $params['categoria'] = (int) $filtros['categoria'];
        }

        $resultado = self::paginar(
            'p.id, p.codigo, p.nome, p.ativo, c.nome AS categoria, pr.valor AS diaria,
             COALESCE(est.total, 0) AS total, COALESCE(est.disponivel, 0) AS disponivel, COALESCE(est.alugado, 0) AS alugado',
            "FROM produtos p
             LEFT JOIN categorias c ON c.id = p.categoria_id
             LEFT JOIN precos_produto pr ON pr.produto_id = p.id AND pr.periodicidade = 'DIARIA'
             " . self::SQL_ESTOQUE . '
             WHERE ' . implode(' AND ', $where),
            $params,
            'p.nome, p.id',
            (int) ($filtros['page'] ?? 1)
        );

        $resultado['items'] = array_map(static fn (array $p): array => [
            'id'         => (int) $p['id'],
            'codigo'     => $p['codigo'],
            'nome'       => $p['nome'],
            'categoria'  => $p['categoria'],
            'diaria'     => $p['diaria'] !== null ? (float) $p['diaria'] : null,
            'total'      => (int) $p['total'],
            'disponivel' => (int) $p['disponivel'],
            'alugado'    => (int) $p['alugado'],
            'ativo'      => (bool) $p['ativo'],
        ], $resultado['items']);
        $resultado['categories'] = Categoria::listar();

        return $resultado;
    }

    /**
     * Detalhes do produto (diálogo "Dados do produto"): estoque por estado,
     * reservas futuras e preços de todas as periodicidades.
     */
    public static function buscarProduto(int $id): ?array
    {
        $consulta = self::pdo()->prepare(
            'SELECT p.id, p.codigo, p.nome, p.descricao, p.ativo, p.categoria_id, c.nome AS categoria,
                    p.fornecedor_id, COALESCE(NULLIF(f.nome_fantasia, \'\'), f.nome_razao_social) AS fornecedor,
                    p.valor_total_bem, p.limite_inicial, p.limite_maximo, p.maximo_estoque, p.criado_em,
                    COALESCE(est.total, 0) AS total, COALESCE(est.disponivel, 0) AS disponivel,
                    COALESCE(est.alugado, 0) AS alugado, COALESCE(est.manutencao, 0) AS manutencao,
                    COALESCE(est.inativa, 0) AS inativa
               FROM produtos p
               LEFT JOIN categorias c ON c.id = p.categoria_id
               LEFT JOIN pessoas f ON f.id = p.fornecedor_id
               ' . self::SQL_ESTOQUE . '
              WHERE p.id = :id'
        );
        $consulta->execute(['id' => $id]);
        $p = $consulta->fetch();
        if (!$p) {
            return null;
        }

        // Reservadas = unidades com reserva ainda não iniciada (o contêiner ainda está no pátio).
        $reservas = self::pdo()->prepare(
            "SELECT COUNT(DISTINCT unidade_id) FROM agenda_unidades
              WHERE produto_id = :id AND status = 'RESERVADA' AND fim > :agora"
        );
        $reservas->execute(['id' => $id, 'agora' => self::agora()]);

        foreach (['id', 'categoria_id', 'fornecedor_id', 'limite_inicial', 'limite_maximo', 'maximo_estoque',
                  'total', 'disponivel', 'alugado', 'manutencao', 'inativa'] as $campo) {
            $p[$campo] = $p[$campo] !== null ? (int) $p[$campo] : null;
        }
        $p['ativo']           = (bool) $p['ativo'];
        $p['valor_total_bem'] = $p['valor_total_bem'] !== null ? (float) $p['valor_total_bem'] : null;
        $p['reservado']       = (int) $reservas->fetchColumn();
        $p['precos']          = self::listarPrecos($id);

        return $p;
    }

    /** Preços do produto na ordem diária → mensal: [{ periodicidade, valor }]. */
    public static function listarPrecos(int $produtoId): array
    {
        $consulta = self::pdo()->prepare(
            "SELECT periodicidade, valor FROM precos_produto WHERE produto_id = :id
              ORDER BY FIELD(periodicidade, 'DIARIA', 'SEMANAL', 'QUINZENAL', 'MENSAL')"
        );
        $consulta->execute(['id' => $produtoId]);

        return array_map(static fn (array $l): array => ['periodicidade' => $l['periodicidade'], 'valor' => (float) $l['valor']], $consulta->fetchAll());
    }

    /** Produtos ativos com os preços, para o select de itens do contrato: [{ id, codigo, nome, precos }]. */
    public static function listarAtivos(): array
    {
        $linhas = self::pdo()->query('SELECT id, codigo, nome FROM produtos WHERE ativo = 1 ORDER BY nome')->fetchAll();

        return array_map(static fn (array $p): array => [
            'id' => (int) $p['id'], 'codigo' => $p['codigo'], 'nome' => $p['nome'], 'precos' => self::listarPrecos((int) $p['id']),
        ], $linhas);
    }

    /** Valor de uma periodicidade ('123.45') ou null se o produto não tem esse preço. */
    public static function valorPorPeriodicidade(int $produtoId, string $periodicidade): ?string
    {
        $consulta = self::pdo()->prepare('SELECT valor FROM precos_produto WHERE produto_id = :id AND periodicidade = :per');
        $consulta->execute(['id' => $produtoId, 'per' => $periodicidade]);
        $valor = $consulta->fetchColumn();

        return $valor === false ? null : (string) $valor;
    }

    /** Opções do cadastro de produto: categorias ativas e fornecedores ativos. */
    public static function opcoes(): array
    {
        return ['categorias' => Categoria::listarAtivas(), 'fornecedores' => Cliente::listarFornecedores()];
    }

    /**
     * Cadastro de produto (POST /api/produtos do cadastro-produto.js). Grava o produto,
     * os quatro preços e gera as unidades físicas da quantidade inicial.
     *
     * Campos: categoria_id, fornecedor_id, codigo, nome, descricao, valor_total_bem,
     * diaria, semanal, quinzenal, mensal, quantidade, estado_operacional,
     * limite_inicial, limite_maximo, maximo_estoque (0 = sem limite) e localidade (opcional).
     *
     * @return array{id: int, nome: string, unidades_criadas: int}
     */
    public static function cadastrarProduto(array $dados, int $usuarioId): array
    {
        $erros  = [];
        $campos = self::validarDados($dados, $erros);
        $precos = self::validarPrecos($dados, $erros, true);

        $quantidade = self::campoInteiro($dados, 'quantidade', $erros, true, 1, 1000);
        $estado     = self::campoOpcao($dados, 'estado_operacional', ['DISPONIVEL', 'MANUTENCAO', 'INATIVA'], $erros) ?? 'DISPONIVEL';
        $localidade = self::campoTexto($dados, 'localidade', 200, $erros);
        if ($quantidade !== null && $campos['maximo_estoque'] && $quantidade > $campos['maximo_estoque']) {
            $erros['maximo_estoque'] = 'O máximo de estoque deve comportar os limites e a quantidade inicial.';
        }
        self::lancarSeErros($erros);
        self::exigirCodigoLivre($campos['codigo']);

        return self::transacao(static function (PDO $pdo) use ($campos, $precos, $quantidade, $estado, $localidade, $usuarioId): array {
            $colunas = array_keys($campos);
            $pdo->prepare(
                'INSERT INTO produtos (' . implode(', ', $colunas) . ', criado_por, ativo, criado_em)
                 VALUES (:' . implode(', :', $colunas) . ', :criado_por, 1, :criado)'
            )->execute($campos + ['criado_por' => $usuarioId, 'criado' => self::agora()]);
            $id = (int) $pdo->lastInsertId();

            self::gravarPrecos($id, $precos);
            $criadas = UnidadeFisica::gerarUnidades($id, $campos['codigo'], $quantidade, $estado, $localidade);

            self::auditar($usuarioId, 'produtos', $id, 'CRIAR', [
                'codigo' => $campos['codigo'], 'precos' => $precos, 'unidades_criadas' => $criadas,
            ]);

            return ['id' => $id, 'nome' => $campos['nome'], 'unidades_criadas' => $criadas];
        });
    }

    /** Atualiza os dados e os preços enviados (a quantidade muda por atualizarQuantidade). */
    public static function atualizarProduto(int $id, array $dados, int $usuarioId): void
    {
        $atual = self::buscarProduto($id);
        if ($atual === null) {
            throw new ErroValidacao('Produto não encontrado.', [], 404);
        }

        $erros  = [];
        $campos = self::validarDados($dados, $erros);
        $precos = self::validarPrecos($dados, $erros, false);
        if ($campos['maximo_estoque'] && $atual['total'] - $atual['inativa'] > $campos['maximo_estoque']) {
            $erros['maximo_estoque'] = 'O produto já tem mais unidades ativas que esse máximo.';
        }
        self::lancarSeErros($erros);
        self::exigirCodigoLivre($campos['codigo'], $id);

        self::transacao(static function (PDO $pdo) use ($id, $campos, $precos, $usuarioId): void {
            $sets = implode(', ', array_map(static fn (string $c): string => "$c = :$c", array_keys($campos)));
            $pdo->prepare("UPDATE produtos SET $sets WHERE id = :id")->execute($campos + ['id' => $id]);
            self::gravarPrecos($id, $precos);
            self::auditar($usuarioId, 'produtos', $id, 'ALTERAR', $campos + ['precos' => $precos]);
        });
    }

    /**
     * Aumenta ou diminui o estoque (método atualizarQuantidade do diagrama).
     * Positivo: cria unidades novas. Negativo: inativa unidades disponíveis e sem reservas futuras.
     *
     * @return int quantidade de unidades afetadas.
     */
    public static function atualizarQuantidade(int $produtoId, int $quantidade, int $usuarioId): int
    {
        $produto = self::buscarProduto($produtoId);
        if ($produto === null) {
            throw new ErroValidacao('Produto não encontrado.', [], 404);
        }
        if ($quantidade === 0) {
            return 0;
        }

        return self::transacao(static function (PDO $pdo) use ($produto, $quantidade, $usuarioId): int {
            if ($quantidade > 0) {
                $ativas = $produto['total'] - $produto['inativa'];
                if ($produto['maximo_estoque'] && $ativas + $quantidade > $produto['maximo_estoque']) {
                    throw new ErroValidacao("O máximo de estoque deste produto é {$produto['maximo_estoque']} unidade(s).", ['quantidade' => 'Acima do máximo de estoque.']);
                }
                $afetadas = UnidadeFisica::gerarUnidades($produto['id'], $produto['codigo'], $quantidade);
            } else {
                // Só sai do estoque o que está no pátio e sem nenhuma reserva pela frente.
                UnidadeFisica::bloquearProduto($produto['id']);
                $livres = UnidadeFisica::livresNoPeriodo($produto['id'], self::agora(), UnidadeFisica::FIM_INDETERMINADO);
                $livres = array_values(array_filter($livres, static function (int $id) use ($pdo): bool {
                    $c = $pdo->prepare("SELECT estado_operacional = 'DISPONIVEL' FROM unidades_fisicas WHERE id = :id");
                    $c->execute(['id' => $id]);
                    return (bool) $c->fetchColumn();
                }));
                $livres = array_slice($livres, 0, -$quantidade);
                if (count($livres) < -$quantidade) {
                    throw new ErroValidacao('Não há unidades disponíveis e sem reserva suficientes para retirar do estoque.', ['quantidade' => 'Quantidade maior que a disponível.'], 409);
                }
                $marcas = implode(', ', array_fill(0, count($livres), '?'));
                $pdo->prepare("UPDATE unidades_fisicas SET estado_operacional = 'INATIVA' WHERE id IN ($marcas)")->execute($livres);
                $afetadas = count($livres);
            }

            self::auditar($usuarioId, 'produtos', $produto['id'], 'ALTERAR_ESTOQUE', ['quantidade' => $quantidade]);

            return $afetadas;
        });
    }

    /**
     * Confere se há $quantidade unidades livres no período (método verificarDisponibilidade do diagrama).
     * Sem datas, considera de agora em diante.
     */
    public static function verificarDisponibilidade(int $produtoId, int $quantidade, ?string $dataInicio = null, ?string $dataFim = null): bool
    {
        $consulta = self::pdo()->prepare('SELECT ativo FROM produtos WHERE id = :id');
        $consulta->execute(['id' => $produtoId]);
        if (!$consulta->fetchColumn()) {
            return false; // produto inexistente ou inativo
        }

        [$inicio, $fim] = self::periodoAgenda($dataInicio, $dataFim);

        return count(UnidadeFisica::livresNoPeriodo($produtoId, $inicio, $fim, $quantidade)) >= $quantidade;
    }

    /** Quantas unidades estão livres no período (para mostrar na tela do contrato). */
    public static function quantidadeDisponivel(int $produtoId, ?string $dataInicio = null, ?string $dataFim = null): int
    {
        [$inicio, $fim] = self::periodoAgenda($dataInicio, $dataFim);

        return count(UnidadeFisica::livresNoPeriodo($produtoId, $inicio, $fim));
    }

    /** Ativa ↔ inativa (método alterarStatusProduto do diagrama). Devolve o novo status. */
    public static function alterarStatusProduto(int $id, int $usuarioId): bool
    {
        $produto = self::buscarProduto($id);
        if ($produto === null) {
            throw new ErroValidacao('Produto não encontrado.', [], 404);
        }
        $novo = !$produto['ativo'];

        self::transacao(static function (PDO $pdo) use ($id, $novo, $usuarioId): void {
            $pdo->prepare('UPDATE produtos SET ativo = :ativo WHERE id = :id')->execute(['ativo' => $novo ? 1 : 0, 'id' => $id]);
            self::auditar($usuarioId, 'produtos', $id, $novo ? 'ATIVAR' : 'INATIVAR');
        });

        return $novo;
    }

    /**
     * Datas do contrato (Y-m-d, fim inclusivo) → período da agenda [inicio, fim) em DATETIME.
     * Sem data final, o período vai até o fim indeterminado.
     */
    public static function periodoAgenda(?string $dataInicio, ?string $dataFim): array
    {
        $inicio = $dataInicio !== null ? $dataInicio . ' 00:00:00' : self::agora();
        $fim    = $dataFim !== null ? date('Y-m-d 00:00:00', strtotime($dataFim . ' +1 day')) : UnidadeFisica::FIM_INDETERMINADO;

        return [$inicio, $fim];
    }

    // -------------------------------------------------------------------------

    /** Campos da tabela produtos, validados. */
    private static function validarDados(array $dados, array &$erros): array
    {
        $campos = [
            'categoria_id'    => self::campoInteiro($dados, 'categoria_id', $erros, true, 1),
            'fornecedor_id'   => self::campoInteiro($dados, 'fornecedor_id', $erros, true, 1),
            'codigo'          => strtoupper((string) self::campoTexto($dados, 'codigo', 40, $erros, true)),
            'nome'            => self::campoTexto($dados, 'nome', 200, $erros, true),
            'descricao'       => self::campoTexto($dados, 'descricao', 500, $erros),
            'valor_total_bem' => self::campoDecimal($dados, 'valor_total_bem', $erros, true),
            'limite_inicial'  => self::campoInteiro($dados, 'limite_inicial', $erros, true, 0, 4294967295),
            'limite_maximo'   => self::campoInteiro($dados, 'limite_maximo', $erros, true, 0, 4294967295),
            'maximo_estoque'  => self::campoInteiro($dados, 'maximo_estoque', $erros, true, 0, 4294967295),
        ];

        // Código vira parte do código das unidades: só letras, números, ponto, hífen e sublinhado.
        if ($campos['codigo'] !== '' && !preg_match('/^[A-Z0-9._-]+$/', $campos['codigo'])) {
            $erros['codigo'] = 'Use apenas letras, números, ponto, hífen ou sublinhado.';
        }
        // 0 = sem limite, igual à regra do cadastro-produto.js.
        if ($campos['limite_maximo'] && $campos['limite_inicial'] > $campos['limite_maximo']) {
            $erros['limite_maximo'] = 'O limite máximo deve ser maior ou igual ao limite inicial.';
        }
        if ($campos['maximo_estoque'] && ($campos['limite_inicial'] > $campos['maximo_estoque'] || $campos['limite_maximo'] > $campos['maximo_estoque'])) {
            $erros['maximo_estoque'] = 'O máximo de estoque deve comportar os limites e a quantidade inicial.';
        }
        // O CHECK do banco exige limite_maximo >= limite_inicial; com "0 = sem limite" isso falharia.
        if ($campos['limite_maximo'] === 0 && $campos['limite_inicial'] > 0) {
            $campos['limite_maximo'] = $campos['maximo_estoque'] ?: $campos['limite_inicial'];
        }

        if ($campos['categoria_id'] !== null) {
            $categoria = Categoria::buscar($campos['categoria_id']);
            if ($categoria === null || !$categoria['ativo']) {
                $erros['categoria_id'] = 'Selecione uma categoria ativa.';
            }
        }
        if ($campos['fornecedor_id'] !== null) {
            $consulta = self::pdo()->prepare("SELECT 1 FROM pessoas WHERE id = :id AND ativo = 1 AND tipo_cadastro IN ('FORNECEDOR', 'AMBOS')");
            $consulta->execute(['id' => $campos['fornecedor_id']]);
            if ($consulta->fetchColumn() === false) {
                $erros['fornecedor_id'] = 'Selecione um fornecedor ativo.';
            }
        }

        return $campos;
    }

    /** Preços enviados: [periodicidade => '123.45']. No cadastro, os quatro são obrigatórios. */
    private static function validarPrecos(array $dados, array &$erros, bool $obrigatorios): array
    {
        $precos = [];
        foreach (self::CAMPOS_PRECO as $campo => $periodicidade) {
            $valor = self::campoDecimal($dados, $campo, $erros, $obrigatorios);
            if ($valor !== null) {
                $precos[$periodicidade] = $valor;
            }
        }

        return $precos;
    }

    /** Grava/atualiza os preços (chave primária produto + periodicidade). */
    private static function gravarPrecos(int $produtoId, array $precos): void
    {
        $grava = self::pdo()->prepare(
            'INSERT INTO precos_produto (produto_id, periodicidade, valor) VALUES (:id, :per, :valor)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        );
        foreach ($precos as $periodicidade => $valor) {
            $grava->execute(['id' => $produtoId, 'per' => $periodicidade, 'valor' => $valor]);
        }
    }

    private static function exigirCodigoLivre(string $codigo, int $ignorarId = 0): void
    {
        $consulta = self::pdo()->prepare('SELECT 1 FROM produtos WHERE codigo = :codigo AND id <> :id');
        $consulta->execute(['codigo' => $codigo, 'id' => $ignorarId]);
        if ($consulta->fetchColumn() !== false) {
            throw new ErroValidacao('Já existe um produto com este código.', ['codigo' => 'Código já cadastrado.'], 409);
        }
    }
}
