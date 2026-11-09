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
        $sql    = 'SELECT id, codigo, estado_operacional, localidade, ultima_vistoria_em, observacoes, criado_em
                     FROM unidades_fisicas WHERE produto_id = :id';
        $params = ['id' => $produtoId];
        if ($estado !== null) {
            $sql            .= ' AND estado_operacional = :estado';
            $params['estado'] = $estado;
        }
        $consulta = self::pdo()->prepare($sql . ' ORDER BY codigo');
        $consulta->execute($params);

        return array_map(static function (array $u): array {
            $u['id'] = (int) $u['id'];
            return $u;
        }, $consulta->fetchAll());
    }

    /**
     * Unidades que podem receber manutenção (todas, menos as locadas agora), para o
     * cadastro de manutenção: { id, codigo, produto, estado_operacional }.
     */
    public static function listarParaManutencao(): array
    {
        $linhas = self::pdo()->query(
            "SELECT u.id, u.codigo, p.nome AS produto, u.estado_operacional
               FROM unidades_fisicas u JOIN produtos p ON p.id = u.produto_id
              WHERE u.estado_operacional <> 'LOCADA'
              ORDER BY u.codigo"
        )->fetchAll();

        return array_map(static function (array $u): array {
            $u['id'] = (int) $u['id'];
            return $u;
        }, $linhas);
    }

    /**
     * Ids das unidades do produto livres em todo o período [inicio, fim).
     * Livre = não está inativa nem em manutenção agora e não tem reserva, uso ou
     * manutenção na agenda que cruze o período.
     *
     * @param string $inicio 'Y-m-d H:i:s'
     * @param string $fim    'Y-m-d H:i:s' (exclusivo)
     */
    public static function livresNoPeriodo(int $produtoId, string $inicio, string $fim, ?int $limite = null): array
    {
        $sql = 'SELECT u.id FROM unidades_fisicas u
                 WHERE u.produto_id = :produto
                   AND u.estado_operacional IN (\'DISPONIVEL\', \'LOCADA\')
                   AND NOT EXISTS (
                       SELECT 1 FROM agenda_unidades a
                        WHERE a.unidade_id = u.id
                          AND a.status IN ' . self::AGENDA_OCUPA . '
                          AND a.inicio < :fim AND a.fim > :inicio
                   )
                 ORDER BY u.codigo';
        if ($limite !== null) {
            $sql .= ' LIMIT ' . max(0, $limite);
        }

        $consulta = self::pdo()->prepare($sql);
        $consulta->execute(['produto' => $produtoId, 'fim' => $fim, 'inicio' => $inicio]);

        return array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
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
