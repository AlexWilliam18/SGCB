<?php
/**
 * API de Categorias (diálogo "Cadastre nova Categoria" da tela de Produtos: categorias.js).
 *
 *   GET  api/categorias/opcoes        opcoes (lista + token)
 *   POST api/categorias               salvar ({ "nome": "..." })
 *   POST api/categorias/{id}          atualizar ({ "nome": "..." })
 *   POST api/categorias/{id}/status   ativar/inativar ({ "ativo": false }): qualquer usuário com permissão de editar produtos
 *   POST api/categorias/{id}/excluir  excluir de vez: só o ADMIN, categoria inativa e sem produtos
 */

final class CategoriaController
{
    public static function opcoes(): array
    {
        $usuario = Api::usuario();

        // pode_excluir: a tela só mostra o botão Excluir para o administrador (o servidor confere de novo).
        return ['categorias' => Categoria::listarComUso(), 'pode_excluir' => Api::ehAdmin($usuario)] + Api::dadosDoFormulario();
    }

    /** @return array{id: int, nome: string, ativo: bool} o categorias.js exige id e nome. */
    public static function salvar(): array
    {
        $usuario = Api::permitir('produtos', 'cadastrar');

        return Api::criado(Categoria::cadastrar((string) (Api::dados()['nome'] ?? ''), $usuario['id']));
    }

    public static function atualizar(int $id): array
    {
        $usuario = Api::permitir('produtos', 'editar');
        Categoria::renomear($id, (string) (Api::dados()['nome'] ?? ''), $usuario['id']);

        return Categoria::buscar($id);
    }

    public static function excluir(int $id): array
    {
        $usuario = Api::apenasAdmin();
        Categoria::excluir($id, $usuario['id']);

        return ['id' => $id, 'excluida' => true];
    }

    public static function alterarStatus(int $id): array
    {
        $usuario = Api::permitir('produtos', 'editar');
        $ativo   = filter_var(Api::dados()['ativo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        Categoria::alterarStatus($id, $ativo, $usuario['id']);

        return Categoria::buscar($id);
    }
}
