<?php
/**
 * Unidade física = cada contêiner real de um produto (tabela unidades_fisicas), com código próprio
 * (ex.: C20-ESC-001). A ocupação por período fica em agenda_unidades (locações e manutenções).
 *
 * Dois controles andam juntos:
 * - estado_operacional: situação AGORA (DISPONIVEL, LOCADA, MANUTENCAO, INATIVA);
 * - agenda_unidades: períodos reservados, usados para saber se a unidade está livre em datas futuras.
 */

final class UnidadeFisica extends Modelo
{
    public const ESTADOS = ['DISPONIVEL', 'LOCADA', 'MANUTENCAO', 'INATIVA'];

    /** Status da agenda que ocupam a unidade (DEVOLVIDA e CANCELADA liberam). */
    private const AGENDA_OCUPA = "('RESERVADA', 'EM_USO', 'MANUTENCAO')";

    /** Fim usado na agenda de contratos por prazo indeterminado. */
    public const FIM_INDETERMINADO = '9999-12-31 00:00:00';

    /**
     * Cria $quantidade unidades para o produto, com códigos sequenciais "<código do produto>-001".
     * Usado no cadastro do produto e quando o estoque aumenta.
     *
     * @return int quantidade criada.
     */
    public static function gerarUnidades(int $produtoId, string $codigoProduto, int $quantidade, string $estado = 'DISPONIVEL', ?string $localidade = null): int
    {
        if (!in_array($estado, ['DISPONIVEL', 'MANUTENCAO', 'INATIVA'], true)) {
            throw new ErroValidacao('Estado inicial inválido.', ['estado_operacional' => 'Estado inicial inválido.']);
        }

        return self::transacao(static function (PDO $pdo) use ($produtoId, $codigoProduto, $quantidade, $estado, $localidade): int {
            $existe = $pdo->prepare('SELECT 1 FROM unidades_fisicas WHERE codigo = :codigo');
            $insere = $pdo->prepare(
                'INSERT INTO unidades_fisicas (produto_id, codigo, estado_operacional, localidade, criado_em)
                 VALUES (:produto, :codigo, :estado, :localidade, :criado)'
            );

            // Continua a numeração das unidades que o produto já tem, pulando códigos ocupados.
            $conta = $pdo->prepare('SELECT COUNT(*) FROM unidades_fisicas WHERE produto_id = :id');
            $conta->execute(['id' => $produtoId]);
            $sequencia = (int) $conta->fetchColumn();
            $agora     = self::agora();

            for ($criadas = 0; $criadas < $quantidade; $criadas++) {
                do {
                    $sequencia++;
                    $codigo = $codigoProduto . '-' . str_pad((string) $sequencia, 3, '0', STR_PAD_LEFT);
                    $existe->execute(['codigo' => $codigo]);
                } while ($existe->fetchColumn() !== false);

                $insere->execute([
                    'produto' => $produtoId, 'codigo' => $codigo, 'estado' => $estado,
                    'localidade' => $localidade, 'criado' => $agora,
                ]);
            }

            return $quantidade;
        });
    }

    /** Unidades de um produto (opcionalmente de um estado), para a tela de detalhes. */
    public static function listarPorProduto(int $produtoId, ?string $estado = null): array
    {
        // Usa a procedure BuscarUnidadesPorProdutoEstado (database/procedures/sp_com_parametros.sql),
        // que filtra por produto + estado. Sem estado informado, chama uma vez para cada estado.
        $estados  = $estado !== null ? [$estado] : ['DISPONIVEL', 'LOCADA', 'MANUTENCAO', 'INATIVA'];
        $consulta = self::pdo()->prepare('CALL BuscarUnidadesPorProdutoEstado(:id, :estado)');
        $unidades = [];
        foreach ($estados as $e) {
            $consulta->execute(['id' => $produtoId, 'estado' => $e]);
            $unidades = array_merge($unidades, $consulta->fetchAll());
            $consulta->closeCursor(); // CALL devolve mais de um resultado; libera antes da próxima chamada
        }

        // Mesma ordem e mesmos campos que a tela de detalhes já usava.
        usort($unidades, static fn (array $a, array $b): int => strcmp($a['codigo'], $b['codigo']));

        return array_map(static fn (array $u): array => [
            'id'                 => (int) $u['id'],
            'codigo'             => $u['codigo'],
            'estado_operacional' => $u['estado_operacional'],
            'localidade'         => $u['localidade'],
            'ultima_vistoria_em' => $u['ultima_vistoria_em'],
            'observacoes'        => $u['observacoes'],
            'criado_em'          => $u['criado_em'],
        ], $unidades);
    }

    /**
     * Unidades que podem receber manutenção, para o cadastro de manutenção: { id, codigo, produto }.
     * Só as DISPONIVEL: locada, já em manutenção ou inativa não aparece no select
     * (o Manutencao::cadastrarManutencao() repete essa regra no salvamento).
     */
    public static function listarParaManutencao(): array
    {
        // A view vw_unidades_fisicas (database/views/vw_unidades_fisicas.sql) já traz o JOIN
        // unidade → produto → categoria; aqui só renomeamos as colunas para o que a tela espera.
        $linhas = self::pdo()->query(
            "SELECT unidade_id AS id, codigo_unidade AS codigo, produto
               FROM vw_unidades_fisicas
              WHERE estado_operacional = 'DISPONIVEL'
              ORDER BY codigo_unidade"
        )->fetchAll();

        return array_map(static function (array $u): array {
            $u['id'] = (int) $u['id'];
            return $u;
        }, $linhas);
    }

    /**
     * Ids das unidades do produto livres em todo o período [inicio, fim).
     * Usa a regra central: agenda, estado, preparação e bloqueios vencidos.
     * Liberações futuras são condicionadas à devolução/conclusão.
     *
     * @param string $inicio 'Y-m-d H:i:s'
     * @param string $fim    'Y-m-d H:i:s' (exclusivo)
     */
    public static function livresNoPeriodo(int $produtoId, string $inicio, string $fim, ?int $limite = null): array
    {
        $resultado = Disponibilidade::avaliar(Disponibilidade::carregar($produtoId), $inicio, $fim);
        return $limite === null ? $resultado['ids'] : array_slice($resultado['ids'], 0, max(0, $limite));
    }

    /**
     * Contêineres ativos do produto com a situação no período (tela "Novo contrato"):
     * [{ id, codigo, livre }]. "livre" = pode ser reservado nas datas pedidas.
     */
    public static function situacaoNoPeriodo(int $produtoId, string $inicio, string $fim): array
    {
        $consulta = self::pdo()->prepare(
            "SELECT id, codigo FROM unidades_fisicas WHERE produto_id = :id AND estado_operacional <> 'INATIVA' ORDER BY codigo"
        );
        $consulta->execute(['id' => $produtoId]);
        $livres = self::livresNoPeriodo($produtoId, $inicio, $fim);

        return array_map(static fn (array $u): array => [
            'id' => (int) $u['id'], 'codigo' => $u['codigo'], 'livre' => in_array((int) $u['id'], $livres, true),
        ], $consulta->fetchAll());
    }

    /**
     * Trava as unidades do produto até o fim da transação (SELECT ... FOR UPDATE).
     * Chame antes de livresNoPeriodo() ao reservar: dois contratos simultâneos
     * não conseguem pegar o mesmo contêiner.
     */
    public static function bloquearProduto(int $produtoId): void
    {
        $produto = self::pdo()->prepare('SELECT id FROM produtos WHERE id = :id FOR UPDATE');
        $produto->execute(['id' => $produtoId]);
        $produto->fetchAll();
        $consulta = self::pdo()->prepare('SELECT id FROM unidades_fisicas WHERE produto_id = :id FOR UPDATE');
        $consulta->execute(['id' => $produtoId]);
        $consulta->fetchAll();
    }

    /**
     * Grava um período na agenda da unidade (locação ou manutenção).
     *
     * @return int id da linha da agenda.
     */
    public static function agendar(array $dados): int
    {
        self::pdo()->prepare(
            'INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, manutencao_id, criado_por, inicio, fim, status)
             VALUES (:unidade, :produto, :item, :manutencao, :por, :inicio, :fim, :status)'
        )->execute([
            'unidade'    => $dados['unidade_id'],
            'produto'    => $dados['produto_id'],
            'item'       => $dados['contrato_item_id'] ?? null,
            'manutencao' => $dados['manutencao_id'] ?? null,
            'por'        => $dados['criado_por'],
            'inicio'     => $dados['inicio'],
            'fim'        => $dados['fim'],
            'status'     => $dados['status'],
        ]);

        return (int) self::pdo()->lastInsertId();
    }

    /** Muda a situação atual da unidade (ex.: tela de detalhes do produto). */
    public static function alterarEstado(int $unidadeId, string $estado, int $usuarioId): void
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new ErroValidacao('Estado inválido.', ['estado_operacional' => 'Estado inválido.']);
        }
        $unidade = self::buscar($unidadeId);
        if ($unidade === null) {
            throw new ErroValidacao('Contêiner não encontrado.', [], 404);
        }
        // Locada só por contrato: o estado LOCADA é controlado pelo Contrato.
        if ($estado === 'LOCADA' || $unidade['estado_operacional'] === 'LOCADA') {
            throw new ErroValidacao('A locação do contêiner é controlada pelos contratos.', [], 409);
        }

        self::transacao(static function (PDO $pdo) use ($unidadeId, $estado, $usuarioId, $unidade): void {
            $pdo->prepare('UPDATE unidades_fisicas SET estado_operacional = :estado WHERE id = :id')
                ->execute(['estado' => $estado, 'id' => $unidadeId]);
            self::auditar($usuarioId, 'unidades_fisicas', $unidadeId, 'ALTERAR_ESTADO', [
                'de' => $unidade['estado_operacional'], 'para' => $estado,
            ]);
        });
    }

    /** Atualiza onde o contêiner está (pátio, obra do cliente...). */
    public static function atualizarLocalidade(int $unidadeId, ?string $localidade, int $usuarioId): void
    {
        $erros      = [];
        $localidade = self::campoTexto(['localidade' => $localidade], 'localidade', 200, $erros);
        self::lancarSeErros($erros);
        if (self::buscar($unidadeId) === null) {
            throw new ErroValidacao('Contêiner não encontrado.', [], 404);
        }

        self::transacao(static function (PDO $pdo) use ($unidadeId, $localidade, $usuarioId): void {
            $pdo->prepare('UPDATE unidades_fisicas SET localidade = :local WHERE id = :id')->execute(['local' => $localidade, 'id' => $unidadeId]);
            self::auditar($usuarioId, 'unidades_fisicas', $unidadeId, 'ALTERAR_LOCALIDADE', ['localidade' => $localidade]);
        });
    }

    /** Registra uma vistoria (data de agora e observações). */
    public static function registrarVistoria(int $unidadeId, ?string $observacoes, int $usuarioId): void
    {
        if (self::buscar($unidadeId) === null) {
            throw new ErroValidacao('Contêiner não encontrado.', [], 404);
        }

        self::transacao(static function (PDO $pdo) use ($unidadeId, $observacoes, $usuarioId): void {
            $pdo->prepare('UPDATE unidades_fisicas SET ultima_vistoria_em = :agora, observacoes = COALESCE(:obs, observacoes) WHERE id = :id')
                ->execute(['agora' => self::agora(), 'obs' => $observacoes !== null && trim($observacoes) !== '' ? trim($observacoes) : null, 'id' => $unidadeId]);
            self::auditar($usuarioId, 'unidades_fisicas', $unidadeId, 'VISTORIA');
        });
    }

    public static function buscar(int $id): ?array
    {
        $consulta = self::pdo()->prepare(
            'SELECT u.id, u.produto_id, u.codigo, u.estado_operacional, u.localidade, u.ultima_vistoria_em, u.observacoes, p.nome AS produto
               FROM unidades_fisicas u JOIN produtos p ON p.id = u.produto_id WHERE u.id = :id'
        );
        $consulta->execute(['id' => $id]);
        $unidade = $consulta->fetch();
        if (!$unidade) {
            return null;
        }
        $unidade['id']         = (int) $unidade['id'];
        $unidade['produto_id'] = (int) $unidade['produto_id'];

        return $unidade;
    }
}
