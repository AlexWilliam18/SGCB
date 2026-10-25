<?php
/**
 * Conexao.php — conexão única (PDO) com o banco do SGCB.
 *
 * Finalidade: abrir a conexão com o MySQL/MariaDB uma única vez por requisição e
 *             reaproveitá-la em todo o sistema (padrão "singleton").
 * Depende de: constantes DB_HOST, DB_PORT, DB_NAME, DB_USER e DB_PASS (config/config.php).
 * Usado por:  src/Auth/Autenticacao.php e, futuramente, os Models do CRUD.
 * Exemplo:    $pdo = Conexao::obter();
 */

final class Conexao
{
    /** Conexão já aberta nesta requisição (null até a primeira chamada de obter()). */
    private static ?PDO $pdo = null;

    /**
     * Devolve a conexão PDO, criando-a na primeira chamada.
     *
     * @throws PDOException se o banco estiver fora do ar ou as credenciais estiverem erradas.
     */
    public static function obter(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // erros viram exceções
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // linhas como array associativo
                PDO::ATTR_EMULATE_PREPARES   => false,                  // prepared statements reais no servidor
            ]);
        }

        return self::$pdo;
    }
}
