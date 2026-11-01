<?php
/**
 * API dos dados da empresa e das preferências de contrato (futura tela de Configurações).
 *
 *   GET  api/empresa                 dados da empresa + preferências + condições de pagamento
 *   POST api/empresa                 salvar dados da empresa
 *   POST api/empresa/preferencias    salvar preferências de contrato
 *   POST api/condicoes-pagamento     cadastrar condição de pagamento
 */

final class EmpresaController
{
    public static function buscar(): array
    {
        Api::permitir('configuracoes', 'visualizar', false);
        $empresa = Empresa::obterEmpresa();

        return [
            'empresa'             => $empresa,
            'preferencias'        => $empresa !== null ? Empresa::obterPreferencias($empresa['id']) : null,
            'condicoes_pagamento' => CondicaoPagamento::listarAtivas(),
        ] + Api::dadosDoFormulario();
    }

    public static function salvar(): array
    {
        $usuario = Api::permitir('configuracoes', 'editar');
        Empresa::salvarEmpresa(Api::dados(), $usuario['id']);

        return self::buscar();
    }

    public static function salvarPreferencias(): array
    {
        $usuario = Api::permitir('configuracoes', 'editar');
        $empresa = Empresa::obterEmpresa();
        if ($empresa === null) {
            throw new ErroValidacao('Cadastre os dados da empresa primeiro.', [], 409);
        }
        Empresa::salvarPreferencias($empresa['id'], Api::dados(), $usuario['id']);

        return self::buscar();
    }

    public static function salvarCondicao(): array
    {
        $usuario = Api::permitir('configuracoes', 'editar');
        $id      = CondicaoPagamento::cadastrar(Api::dados(), $usuario['id']);

        return Api::criado(CondicaoPagamento::buscar($id));
    }
}
