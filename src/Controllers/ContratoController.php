<?php
/**
 * API de Contratos de Locação (tela public/admin/locacoes/index.php).
 *
 *   GET  api/contratos                     listar (filtros q, status, data, page)
 *   GET  api/contratos/opcoes              opcoes (clientes, produtos com preços, condições + token)
 *   GET  api/contratos/{id}                buscar (itens, contêineres e parcelas)
 *   POST api/contratos/calcular            prévia do valor, sem gravar
 *   POST api/contratos                     salvar rascunho ({ ..., "gerar": true } já ativa o contrato)
 *   POST api/contratos/{id}/gerar          ativa: reserva contêineres, numera e gera parcelas
 *   POST api/contratos/{id}/iniciar        entrega dos contêineres
 *   POST api/contratos/{id}/finalizar      devolução dos contêineres
 *   POST api/contratos/{id}/cancelar       cancela ({ motivo })
 */

final class ContratoController
{
    public static function listar(): array
    {
        Api::usuario();

        return Contrato::listarContratos($_GET);
    }

    public static function buscar(int $id): array
    {
        Api::usuario();

        return Api::ouNaoEncontrado(Contrato::buscarContrato($id), 'Contrato não encontrado.');
    }

    public static function opcoes(): array
    {
        Api::usuario();

        return [
            'clientes'            => Cliente::listarClientesAtivos(),
            'produtos'            => Produto::listarAtivos(),
            'condicoes_pagamento' => CondicaoPagamento::listarAtivas(),
            'periodicidades'      => Produto::PERIODICIDADES,
        ] + Api::dadosDoFormulario();
    }

    public static function calcular(): array
    {
        Api::usuario();

        return Contrato::calcularValorTotal(Api::dados());
    }

    public static function salvar(): array
    {
        $usuario = Api::permitir('contratos', 'cadastrar');
        $dados   = Api::dados();
        $id      = Contrato::criarContrato($dados, $usuario['id']);

        // "gerar": true faz rascunho + ativação na mesma chamada.
        if (filter_var($dados['gerar'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return Api::criado(Contrato::gerarContrato($id, $usuario['id']));
        }

        return Api::criado(Contrato::buscarContrato($id));
    }

    public static function gerar(int $id): array
    {
        $usuario = Api::permitir('contratos', 'cadastrar');

        return Contrato::gerarContrato($id, $usuario['id']);
    }

    public static function iniciar(int $id): array
    {
        $usuario = Api::permitir('contratos', 'editar');
        Contrato::iniciarLocacao($id, $usuario['id']);

        return Contrato::buscarContrato($id);
    }

    public static function finalizar(int $id): array
    {
        $usuario = Api::permitir('contratos', 'editar');
        Contrato::finalizarContrato($id, $usuario['id']);

        return Contrato::buscarContrato($id);
    }

    public static function cancelar(int $id): array
    {
        $usuario = Api::permitir('contratos', 'cancelar');
        Contrato::cancelarContrato($id, (string) (Api::dados()['motivo'] ?? ''), $usuario['id']);

        return Contrato::buscarContrato($id);
    }
}
