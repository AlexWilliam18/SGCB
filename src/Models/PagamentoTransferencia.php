<?php
/**
 * Pagamento por transferência bancária (forma TRANSFERENCIA: TED/DOC).
 * O banco de origem é opcional e fica na auditoria.
 */

class PagamentoTransferencia extends Pagamento
{
    private string $banco;

    public function __construct(int $parcelaId, string $valor, ?int $comprovanteArquivoId = null, ?string $pagoEm = null, string $banco = '')
    {
        // chamada do construtor da classe pai
        parent::__construct($parcelaId, $valor, $comprovanteArquivoId, $pagoEm);
        $this->banco = trim($banco);
    }

    public function getForma(): string
    {
        return 'TRANSFERENCIA';
    }

    protected function validarDados(): array
    {
        return mb_strlen($this->banco) > 100 ? ['banco' => 'Use no máximo 100 caracteres.'] : [];
    }

    protected function detalhes(): array
    {
        return $this->banco !== '' ? ['banco' => $this->banco] : [];
    }
}
