<?php
/**
 * API de Usuários (futura tela admin/usuarios). Gerenciar usuários exige a permissão do
 * módulo "usuarios" (o cargo ADMIN tem), como o apenasAdmin() do CRUD de base.
 *
 *   GET  api/usuarios                 listar (filtros q, situacao, page)
 *   GET  api/usuarios/opcoes          cargos + token
 *   GET  api/usuarios/{id}            buscar
 *   POST api/usuarios                 salvar (com senha provisória)
 *   POST api/usuarios/{id}            atualizar (nome, e-mail, telefone, cargo)
 *   POST api/usuarios/{id}/status     ativar/desativar ({ "ativo": false })
 *   POST api/conta/senha              o próprio usuário troca a senha ({ senha_atual, nova_senha })
 */

final class UsuarioController
{
    public static function listar(): array
    {
        Api::permitir('usuarios', 'visualizar', false);

        return Usuario::listarUsuarios($_GET);
    }

    public static function buscar(int $id): array
    {
        Api::permitir('usuarios', 'visualizar', false);

        return Api::ouNaoEncontrado(Usuario::buscarPorId($id), 'Usuário não encontrado.');
    }

    public static function opcoes(): array
    {
        Api::permitir('usuarios', 'visualizar', false);

        return ['cargos' => Usuario::listarCargos()] + Api::dadosDoFormulario();
    }

    public static function salvar(): array
    {
        $usuario = Api::permitir('usuarios', 'cadastrar');
        $id      = Usuario::cadastrarUsuario(Api::dados(), $usuario['id']);

        return Api::criado(Usuario::buscarPorId($id));
    }

    public static function atualizar(int $id): array
    {
        $usuario = Api::permitir('usuarios', 'editar');
        Usuario::atualizarUsuario($id, Api::dados(), $usuario['id']);

        return Usuario::buscarPorId($id);
    }

    /** O "excluir" do CRUD aqui é desativar: o usuário aparece no histórico de contratos e na auditoria. */
    public static function alterarStatus(int $id): array
    {
        $usuario = Api::permitir('usuarios', 'editar');
        $ativo   = filter_var(Api::dados()['ativo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        Usuario::alterarStatus($id, $ativo, $usuario['id']);

        return Usuario::buscarPorId($id);
    }

    /** Troca da própria senha (vale para qualquer usuário logado). */
    public static function alterarSenha(): array
    {
        $usuario = Api::usuario();
        Api::verificarToken();
        $dados = Api::dados();
        Usuario::alterarSenha($usuario['id'], (string) ($dados['senha_atual'] ?? ''), (string) ($dados['nova_senha'] ?? ''));

        return ['ok' => true];
    }
}
