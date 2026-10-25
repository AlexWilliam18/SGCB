<?php
/**
 * Conexão PDO com o MySQL/MariaDB, aberta uma vez por requisição e reaproveitada.
 * Usa as constantes DB_* do config/config.php.
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
