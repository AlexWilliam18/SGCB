<?php
/**
 * Pagamento em dinheiro (forma DINHEIRO).
 * Se o valor entregue pelo cliente for informado, o troco é calculado e vai para a auditoria.
 */

class PagamentoDinheiro extends Pagamento
{
    private string $valorEntregue;

    public function __construct(int $parcelaId, string $valor, ?int $comprovanteArquivoId = null, ?string $pagoEm = null, string $valorEntregue = '')
    {
        // chamada do construtor da classe pai
        parent::__construct($parcelaId, $valor, $comprovanteArquivoId, $pagoEm);
        $this->valorEntregue = trim($valorEntregue);
    }

    public function getForma(): string
    {
        return 'DINHEIRO';
    }

    /** Troco a devolver ('0.00' se não informado o valor entregue). */
    public function calcularTroco(): string
    {
        $erros    = [];
        $entregue = self::campoDecimal(['v' => $this->valorEntregue], 'v', $erros);
        $valor    = self::campoDecimal(['v' => $this->valorPago], 'v', $erros);
        if ($entregue === null || $valor === null) {
            return '0.00';
        }

        return self::reais(max(0, self::centavos($entregue) - self::centavos($valor)));
    }

    protected function validarDados(): array
    {
        if ($this->valorEntregue === '') {
            return [];
        }

        $erros    = [];
        $entregue = self::campoDecimal(['valor_entregue' => $this->valorEntregue], 'valor_entregue', $erros);
        $valor    = self::campoDecimal(['valor' => $this->valorPago], 'valor', $erros);
        if ($entregue !== null && $valor !== null && self::centavos($entregue) < self::centavos($valor)) {
            $erros['valor_entregue'] = 'O valor entregue é menor que o valor pago.';
        }
        unset($erros['valor']); // o valor é validado pela classe pai

        return $erros;
    }

    protected function detalhes(): array
    {
        return $this->valorEntregue !== '' ? ['valor_entregue' => $this->valorEntregue, 'troco' => $this->calcularTroco()] : [];
    }
}
