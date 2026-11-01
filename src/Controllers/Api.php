<?php
/**
 * Funções comuns dos controllers da API (o equivalente ao seguranca.php do CRUD de base):
 * exigir login, conferir o token CSRF, conferir permissão e ler os dados enviados.
 *
 * Os controllers ficam entre as telas e os models:
 *   tela (fetch) → public/api/index.php (rota) → Controller → Model → banco.
 * Todo erro sai como ErroValidacao e vira JSON { "error": "...", "fields": {...} } no index.php da API.
 */

final class Api
{
    /**
     * Usuário logado (confirmado no banco). Sem login, a API responde 401.
     * Chame no início de toda ação, como o require "seguranca.php" no topo das páginas.
     */
    public static function usuario(): array
    {
        $usuario = Autenticacao::usuarioValido();
        if ($usuario === null) {
            throw new ErroValidacao('Sua sessão expirou. Entre novamente.', [], 401);
        }

        return $usuario;
    }

    /**
     * Confere o token CSRF de toda ação que grava (como o verificarToken() do CRUD de base).
     * As telas enviam no cabeçalho X-CSRF-Token; formulários comuns podem enviar o campo "csrf".
     */
    public static function verificarToken(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? null;
        if (!Autenticacao::csrfValido($token)) {
            throw new ErroValidacao('Sessão expirada. Recarregue a página e tente novamente.', [], 403);
        }
    }

    /**
     * Usuário logado + token válido + permissão para a ação (como o apenasAdmin() do CRUD de base).
     * O cargo ADMIN pode tudo; os outros cargos seguem as permissões do banco.
     *
     * @return array o usuário logado.
     */
    public static function permitir(string $modulo, string $acao, bool $grava = true): array
    {
        $usuario = self::usuario();
        if ($grava) {
            self::verificarToken();
        }
        if (!Usuario::temPermissao($usuario['id'], $modulo, $acao)) {
            throw new ErroValidacao('Você não tem permissão para esta ação.', [], 403);
        }

        return $usuario;
    }

    /** Corpo da requisição em JSON (fetch com Content-Type: application/json) ou o $_POST do formulário. */
    public static function dados(): array
    {
        $tipo = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($tipo, 'application/json')) {
            $dados = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($dados)) {
                throw new ErroValidacao('Dados enviados em formato inválido.', [], 400);
            }
            return $dados;
        }

        return $_POST;
    }

    /** Campo de um formulário multipart que traz um JSON (ex.: "dados" do cadastro de cliente com foto). */
    public static function dadosDoCampo(string $campo): array
    {
        $dados = json_decode((string) ($_POST[$campo] ?? ''), true);
        if (!is_array($dados)) {
            throw new ErroValidacao('Dados enviados em formato inválido.', [], 400);
        }

        return $dados;
    }

    /** Dados padrão das telas de cadastro: token CSRF e se há um usuário responsável para gravar. */
    public static function dadosDoFormulario(): array
    {
        return ['csrf_token' => Autenticacao::tokenCsrf(), 'responsavel_configurado' => true];
    }

    /** Resposta 201 (registro criado). */
    public static function criado(array $dados): array
    {
        http_response_code(201);

        return $dados;
    }

    /** Registro não encontrado → 404; senão devolve o próprio registro. */
    public static function ouNaoEncontrado(?array $registro, string $mensagem = 'Registro não encontrado.'): array
    {
        if ($registro === null) {
            throw new ErroValidacao($mensagem, [], 404);
        }

        return $registro;
    }
}
