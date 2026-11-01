<?php
/**
 * API do Financeiro (tela admin/financeiro: financeiro.js): parcelas e pagamentos.
 *
 *   GET  api/financeiro                               listar parcelas (filtros q, prioridade, status, data, page)
 *   GET  api/financeiro/parcelas/{id}                 buscar parcela (com histórico de pagamentos)
 *   POST api/financeiro/parcelas/{id}/pagamentos      registrar pagamento ({ forma, valor, pago_em, ... })
 *   POST api/financeiro/parcelas/{id}/ajuste          acréscimo/desconto ({ acrescimo, desconto, observacoes })
 *   POST api/financeiro/parcelas/{id}/cancelar        cancelar parcela sem pagamento ({ motivo })
 *   POST api/financeiro/pagamentos/{id}/estorno       estornar ({ motivo_estorno })
 *   GET  api/financeiro/pagamentos/{id}/recibo        dados do recibo
 */

final class FinanceiroController
{
    public static function listar(): array
    {
        Api::usuario();

        return Parcela::listarParcelas($_GET);
    }

    public static function parcela(int $id): array
    {
        Api::usuario();

        return Api::ouNaoEncontrado(Parcela::buscarParcela($id), 'Parcela não encontrada.');
    }

    /**
     * Registra o pagamento com a classe da forma escolhida (PagamentoPix, PagamentoCartao...).
     * Devolve o pagamento e a parcela já atualizada (status e saldo novos).
     */
    public static function registrarPagamento(int $id): array
    {
        $usuario   = Api::permitir('financeiro', 'cadastrar');
        $pagamento = Pagamento::criarPorForma(['parcela_id' => $id] + Api::dados());
        $pagamento->processarPagamento($usuario['id']);

        return Api::criado([
            'id'      => $pagamento->getId(),
            'status'  => $pagamento->verificarStatusPagamento(),
            'forma'   => $pagamento->getFormaPagamento(),
            'parcela' => Parcela::buscarParcela($id),
        ]);
    }

    public static function ajustar(int $id): array
    {
        $usuario = Api::permitir('financeiro', 'editar');
        Parcela::aplicarAjuste($id, Api::dados(), $usuario['id']);

        return Parcela::buscarParcela($id);
    }

    public static function cancelarParcela(int $id): array
    {
        $usuario = Api::permitir('financeiro', 'editar');
        Parcela::cancelarParcela($id, (string) (Api::dados()['motivo'] ?? ''), $usuario['id']);

        return Parcela::buscarParcela($id);
    }

    public static function estornar(int $id): array
    {
        $usuario = Api::permitir('financeiro', 'estornar');
        Pagamento::estornar($id, (string) (Api::dados()['motivo_estorno'] ?? ''), $usuario['id']);

        return ['id' => $id, 'estornado' => true];
    }

    public static function recibo(int $id): array
    {
        Api::usuario();

        return Api::ouNaoEncontrado(Pagamento::emitirRecibo($id), 'Recibo indisponível: pagamento não encontrado ou estornado.');
    }
}
