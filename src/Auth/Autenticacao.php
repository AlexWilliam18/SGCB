<?php
/**
 * Login, logout e proteção das páginas do admin.
 * Valida e-mail e senha na tabela usuarios e guarda o usuário logado na sessão
 * (a sessão é aberta em src/bootstrap.php).
 */

final class Autenticacao
{
    /** Chave de $_SESSION onde fica o usuário logado. */
    private const CHAVE_SESSAO = 'usuario';

    /**
     * Tenta autenticar o usuário. Em caso de sucesso, grava os dados na sessão.
     *
     * @return bool true se e-mail e senha conferem com um usuário ativo.
     */
    public static function entrar(string $email, string $senha): bool
    {
        $pdo = Conexao::obter();

        // Busca o usuário pelo e-mail (campo UNIQUE) junto com o cargo, para exibir no painel.
        $consulta = $pdo->prepare(
            'SELECT u.id, u.nome, u.email, u.senha_hash, u.ativo, u.deve_alterar_senha,
                    c.codigo AS cargo_codigo, c.nome AS cargo_nome
               FROM usuarios u
               JOIN cargos c ON c.id = u.cargo_id
              WHERE u.email = :email
              LIMIT 1'
        );
        $consulta->execute(['email' => mb_strtolower(trim($email))]);
        $usuario = $consulta->fetch();

        if (!$usuario || !$usuario['ativo'] || !self::senhaConfere($senha, (string) $usuario['senha_hash'])) {
            return false;
        }

        /*
         * O seed (database/seeds/001_usuario_admin.sql) pode gravar a senha em texto puro.
         * Nesse caso, ou se o algoritmo do hash ficou desatualizado, a senha é convertida
         * para hash bcrypt no primeiro login bem-sucedido, e o texto puro some do banco.
         */
        if (password_needs_rehash($usuario['senha_hash'], PASSWORD_BCRYPT)) {
            $pdo->prepare('UPDATE usuarios SET senha_hash = :hash, atualizado_em = NOW() WHERE id = :id')
                ->execute(['hash' => password_hash($senha, PASSWORD_BCRYPT), 'id' => $usuario['id']]);
        }

        $pdo->prepare('UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = :id')
            ->execute(['id' => $usuario['id']]);

        // Novo ID de sessão após o login, contra fixação de sessão.
        session_regenerate_id(true);
        $_SESSION[self::CHAVE_SESSAO] = [
            'id'                 => (int) $usuario['id'],
            'nome'               => $usuario['nome'],
            'email'              => $usuario['email'],
            'cargo_codigo'       => $usuario['cargo_codigo'],
            'cargo_nome'         => $usuario['cargo_nome'],
            'deve_alterar_senha' => (bool) $usuario['deve_alterar_senha'],
        ];

        return true;
    }

    /** Encerra a sessão do usuário (dados, cookie e arquivo de sessão). */
    public static function sair(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }

        session_destroy();
    }

    /** Usuário gravado na sessão (sem consultar o banco) ou null. Use depois de exigirLogin()/usuarioValido(). */
    public static function usuario(): ?array
    {
        return $_SESSION[self::CHAVE_SESSAO] ?? null;
    }

    /**
     * Motivo da última sessão recusada por usuarioValido(): 'sessao' (usuário não existe mais
     * ou foi desativado) ou 'banco' (banco indisponível). null se não houve recusa.
     */
    private static ?string $motivoRecusa = null;

    /**
     * Confere no banco, a cada requisição, se o usuário da sessão ainda existe e está ativo.
     * Assim uma sessão antiga não dá acesso quando o usuário foi apagado/desativado
     * ou quando o banco está fora do ar. Também atualiza nome e cargo na sessão.
     *
     * @return array|null Dados do usuário, ou null (sessão encerrada) se não puder ser confirmado.
     */
    public static function usuarioValido(): ?array
    {
        $sessao = self::usuario();
        if ($sessao === null) {
            return null;
        }

        try {
            $consulta = Conexao::obter()->prepare(
                'SELECT u.nome, u.email, u.ativo, u.deve_alterar_senha,
                        c.codigo AS cargo_codigo, c.nome AS cargo_nome
                   FROM usuarios u
                   JOIN cargos c ON c.id = u.cargo_id
                  WHERE u.id = :id
                  LIMIT 1'
            );
            $consulta->execute(['id' => $sessao['id']]);
            $usuario = $consulta->fetch();
        } catch (PDOException $e) {
            // Sem banco não há como confirmar o usuário: o acesso é negado.
            error_log('SGCB sessão: ' . $e->getMessage());
            self::encerrarUsuario('banco');
            return null;
        }

        if (!$usuario || !$usuario['ativo']) {
            self::encerrarUsuario('sessao');
            return null;
        }

        // Mantém a sessão em dia com o banco (ex.: nome ou cargo alterados).
        $_SESSION[self::CHAVE_SESSAO] = [
            'id'                 => $sessao['id'],
            'nome'               => $usuario['nome'],
            'email'              => $usuario['email'],
            'cargo_codigo'       => $usuario['cargo_codigo'],
            'cargo_nome'         => $usuario['cargo_nome'],
            'deve_alterar_senha' => (bool) $usuario['deve_alterar_senha'],
        ];

        return $_SESSION[self::CHAVE_SESSAO];
    }

    /**
     * Página protegida: só segue se o usuário da sessão for confirmado no banco.
     * Caso contrário, volta para o login informando o motivo. Chame no topo das páginas do admin.
     */
    public static function exigirLogin(): void
    {
        if (self::usuarioValido() === null) {
            $motivo = self::$motivoRecusa !== null ? '?motivo=' . self::$motivoRecusa : '';
            header('Location: ' . BASE_URL . 'login.php' . $motivo);
            exit;
        }
    }

    /**
     * Tira o usuário da sessão sem destruí-la (o token CSRF continua valendo para o
     * formulário de login) e gera um novo ID de sessão.
     */
    private static function encerrarUsuario(string $motivo): void
    {
        self::$motivoRecusa = $motivo;
        unset($_SESSION[self::CHAVE_SESSAO]);
        session_regenerate_id(true);
    }

    /** Token CSRF da sessão, para os formulários (criado na primeira chamada). */
    public static function tokenCsrf(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf'];
    }

    /** Confere o token CSRF enviado pelo formulário. */
    public static function csrfValido(?string $token): bool
    {
        return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    /**
     * Compara a senha digitada com o valor do banco.
     * Aceita hash (password_hash) ou, para o seed inicial, a senha em texto puro.
     */
    private static function senhaConfere(string $senha, string $armazenada): bool
    {
        // password_get_info devolve algo = null quando o valor não é um hash conhecido.
        if (password_get_info($armazenada)['algo'] === null) {
            return $armazenada !== '' && hash_equals($armazenada, $senha);
        }

        return password_verify($senha, $armazenada);
    }
}
