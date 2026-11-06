<?php
/**
 * API de Contratos de Locação (tela public/admin/locacoes/index.php).
 *
 *   GET  api/contratos                     listar (filtros q, status, data, page)
 *   GET  api/contratos/opcoes              opcoes (clientes, produtos com preços, condições + token)
 *   GET  api/contratos/{id}                buscar (itens, contêineres e parcelas)
 *   GET  api/contratos/unidades            contêineres do produto livres no período (produto_id, data_inicio, data_fim)
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

    /**
     * Contêineres do produto e se estão livres no período (seleção de unidades físicas da tela).
     * Parâmetros: produto_id, data_inicio (AAAA-MM-DD) e data_fim (vazio = prazo indeterminado).
     */
    public static function unidades(): array
    {
        Api::usuario();

        $produtoId = filter_var($_GET['produto_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $data      = static fn (string $campo): ?string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET[$campo] ?? '')) ? $_GET[$campo] : null;
        $inicio    = $data('data_inicio');
        if ($produtoId === false || $inicio === null) {
            throw new ErroValidacao('Informe o produto e a data inicial.', [], 422);
        }
        [$de, $ate] = Produto::periodoAgenda($inicio, $data('data_fim'));

        return ['items' => UnidadeFisica::situacaoNoPeriodo($produtoId, $de, $ate)];
    }

    public static function salvar(): array
    {
        $usuario = Api::permitir('contratos', 'cadastrar');
        $dados   = Api::dados();

        // "gerar": true faz rascunho + ativação na mesma transação (reservando os contêineres escolhidos).
        if (filter_var($dados['gerar'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return Api::criado(Contrato::criarEGerarContrato($dados, $usuario['id']));
        }

        return Api::criado(Contrato::buscarContrato(Contrato::criarContrato($dados, $usuario['id'])));
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
