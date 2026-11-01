<?php
/**
 * API de Manutenções (telas admin/manutencoes: manutencoes.js e cadastro-manutencao.js).
 *
 *   GET  api/manutencoes                   listar (filtros q, prioridade, localidade, data, page)
 *   GET  api/manutencoes/opcoes            opcoes do cadastro (contêineres, responsáveis, tipos, prioridades + token)
 *   GET  api/manutencoes/{id}              buscar
 *   POST api/manutencoes                   salvar (agenda a manutenção)
 *   POST api/manutencoes/{id}/iniciar      começa o serviço
 *   POST api/manutencoes/{id}/concluir     conclui ({ custo, responsavel_externo, descricao })
 *   POST api/manutencoes/{id}/cancelar     cancela
 */

final class ManutencaoController
{
    public static function listar(): array
    {
        Api::usuario();

        return Manutencao::listarManutencoes($_GET);
    }

    public static function buscar(int $id): array
    {
        Api::usuario();

        return Api::ouNaoEncontrado(Manutencao::buscarManutencao($id), 'Manutenção não encontrada.');
    }

    public static function opcoes(): array
    {
        Api::usuario();

        return Manutencao::opcoes() + Api::dadosDoFormulario();
    }

    /** @return array{id: int} o cadastro-manutencao.js mostra "Manutenção #id cadastrada". */
    public static function salvar(): array
    {
        $usuario = Api::permitir('manutencoes', 'cadastrar');

        return Api::criado(['id' => Manutencao::cadastrarManutencao(Api::dados(), $usuario['id'])]);
    }

    public static function iniciar(int $id): array
    {
        $usuario = Api::permitir('manutencoes', 'editar');
        Manutencao::iniciarManutencao($id, $usuario['id']);

        return Manutencao::buscarManutencao($id);
    }

    public static function concluir(int $id): array
    {
        $usuario = Api::permitir('manutencoes', 'editar');
        Manutencao::concluirManutencao($id, Api::dados(), $usuario['id']);

        return Manutencao::buscarManutencao($id);
    }

    public static function cancelar(int $id): array
    {
        $usuario = Api::permitir('manutencoes', 'editar');
        Manutencao::cancelarManutencao($id, $usuario['id']);

        return Manutencao::buscarManutencao($id);
    }
}
