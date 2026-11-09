<?php
/**
 * Categorias de produto (tabela categorias). Ex.: Container Escritório, Container Almoxarifado.
 * Usada pelo diálogo "Cadastre nova Categoria" (categorias.js) e pelos filtros de Produtos.
 */

final class Categoria extends Modelo
{
    /**
     * Todas as categorias ({ id, nome, ativo }): filtro da listagem de produtos, pop-up de categorias
     * e select do cadastro de produto (onde as inativas aparecem desabilitadas, com "(inativa)").
     */
    public static function listar(): array
    {
        $linhas = self::pdo()->query('SELECT id, nome, ativo FROM categorias ORDER BY nome')->fetchAll();

        return array_map([self::class, 'formatar'], $linhas);
    }

    public static function buscar(int $id): ?array
    {
        $consulta = self::pdo()->prepare('SELECT id, nome, ativo FROM categorias WHERE id = :id');
        $consulta->execute(['id' => $id]);
        $linha = $consulta->fetch();

        return $linha ? self::formatar($linha) : null;
    }

    /**
     * Cadastra uma categoria (POST /api/categorias). O nome não pode repetir,
     * nem com letras maiúsculas/minúsculas diferentes.
     *
     * @return array{id: int, nome: string, ativo: bool} formato que o categorias.js espera.
     */
    public static function cadastrar(string $nome, int $usuarioId): array
    {
        $erros = [];
        $nome  = self::campoTexto(['nome' => $nome], 'nome', 100, $erros, true);
        if (isset($erros['nome']) && $nome === null) {
            $erros['nome'] = 'Informe o nome da categoria.';
        }
        self::lancarSeErros($erros);
        self::exigirNomeLivre($nome);

        return self::transacao(static function (PDO $pdo) use ($nome, $usuarioId): array {
            $pdo->prepare('INSERT INTO categorias (nome, ativo) VALUES (:nome, 1)')->execute(['nome' => $nome]);
            $id = (int) $pdo->lastInsertId();
            self::auditar($usuarioId, 'categorias', $id, 'CRIAR', ['nome' => $nome]);

            return ['id' => $id, 'nome' => $nome, 'ativo' => true];
        });
    }

    public static function renomear(int $id, string $nome, int $usuarioId): void
    {
        self::exigirCategoria($id);
        $erros = [];
        $nome  = self::campoTexto(['nome' => $nome], 'nome', 100, $erros, true);
        self::lancarSeErros($erros);
        self::exigirNomeLivre($nome, $id);

        self::transacao(static function (PDO $pdo) use ($id, $nome, $usuarioId): void {
            $pdo->prepare('UPDATE categorias SET nome = :nome WHERE id = :id')->execute(['nome' => $nome, 'id' => $id]);
            self::auditar($usuarioId, 'categorias', $id, 'ALTERAR', ['nome' => $nome]);
        });
    }

    /**
     * Ativar/inativar (método inativar do diagrama): só faz UPDATE ativo, nunca DELETE.
     * Inativa, a categoria continua listada mas não pode ser escolhida em produto novo;
     * os produtos que já a usam continuam exibindo a categoria normalmente.
     */
    public static function alterarStatus(int $id, bool $ativo, int $usuarioId): void
    {
        self::exigirCategoria($id);

        self::transacao(static function (PDO $pdo) use ($id, $ativo, $usuarioId): void {
            $pdo->prepare('UPDATE categorias SET ativo = :ativo WHERE id = :id')->execute(['ativo' => $ativo ? 1 : 0, 'id' => $id]);
            self::auditar($usuarioId, 'categorias', $id, $ativo ? 'ATIVAR' : 'INATIVAR');
        });
    }

    private static function exigirNomeLivre(string $nome, int $ignorarId = 0): void
    {
        $consulta = self::pdo()->prepare('SELECT 1 FROM categorias WHERE LOWER(nome) = LOWER(:nome) AND id <> :id');
        $consulta->execute(['nome' => $nome, 'id' => $ignorarId]);
        if ($consulta->fetchColumn() !== false) {
            throw new ErroValidacao('Já existe uma categoria com este nome.', ['nome' => 'Já existe uma categoria com este nome.'], 409);
        }
    }

    private static function exigirCategoria(int $id): void
    {
        if (self::buscar($id) === null) {
            throw new ErroValidacao('Categoria não encontrada.', [], 404);
        }
    }

    private static function formatar(array $c): array
    {
        return ['id' => (int) $c['id'], 'nome' => $c['nome'], 'ativo' => (bool) $c['ativo']];
    }
}
