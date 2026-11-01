<?php
/**
 * API de Clientes (telas admin/clientes: clientes.js e cadastro-cliente.js).
 *
 *   GET  api/clientes               listar (filtros q, situacao, uf, cidade, page)
 *   GET  api/clientes/{id}          buscar
 *   GET  api/cadastros/opcoes       opcoes do cadastro (condições de pagamento + token)
 *   POST api/clientes               salvar (multipart: "dados" em JSON, "foto" e "anexos[]")
 *   POST api/clientes/{id}          atualizar (mesmo multipart do cadastro, ou JSON)
 *   POST api/clientes/{id}/status   ativar/inativar ({ "ativo": false })
 *
 * Não existe exclusão física: contratos e parcelas apontam para o cliente,
 * então o "excluir" do CRUD aqui é inativar.
 */

final class ClienteController
{
    public static function listar(): array
    {
        Api::usuario();

        return Cliente::listarClientes($_GET);
    }

    public static function buscar(int $id): array
    {
        Api::usuario();

        return Api::ouNaoEncontrado(Cliente::buscarCliente($id), 'Cliente não encontrado.');
    }

    public static function opcoes(): array
    {
        Api::usuario();

        return ['condicoes_pagamento' => CondicaoPagamento::listarAtivas()] + Api::dadosDoFormulario();
    }

    /** @return array{id: int, tipo_cadastro: string} o cadastro-cliente.js redireciona com esses dados. */
    public static function salvar(): array
    {
        $usuario = Api::permitir('clientes', 'cadastrar');
        $foto    = $_FILES['foto'] ?? null;
        $anexos  = Arquivo::normalizarLista($_FILES['anexos'] ?? null);

        return Api::criado(Cliente::cadastrarCliente(Api::dadosDoCampo('dados'), $usuario['id'], $foto, $anexos));
    }

    /**
     * Edição: o cadastro-cliente.js envia o mesmo multipart do cadastro ("dados" + foto/anexos novos);
     * um JSON simples também é aceito.
     */
    public static function atualizar(int $id): array
    {
        $usuario = Api::permitir('clientes', 'editar');
        $dados   = isset($_POST['dados']) ? Api::dadosDoCampo('dados') : Api::dados();
        $foto    = $_FILES['foto'] ?? null;
        $anexos  = Arquivo::normalizarLista($_FILES['anexos'] ?? null);
        Cliente::atualizarDados($id, $dados, $usuario['id'], $foto, $anexos);

        return Cliente::buscarCliente($id);
    }

    public static function alterarStatus(int $id): array
    {
        $usuario = Api::permitir('clientes', 'editar');
        $ativo   = filter_var(Api::dados()['ativo'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $ativo ? Cliente::ativarCliente($id, $usuario['id']) : Cliente::inativarCliente($id, $usuario['id']);

        return ['id' => $id, 'ativo' => $ativo];
    }
}
