<?php
/**
 * Pagamento por boleto (forma BOLETO), registrado quando a compensação é confirmada.
 * O "nosso número" do boleto é opcional e fica na auditoria.
 */

class PagamentoBoleto extends Pagamento
{
    private string $nossoNumero;

    public function __construct(int $parcelaId, string $valor, ?int $comprovanteArquivoId = null, ?string $pagoEm = null, string $nossoNumero = '')
    {
        // chamada do construtor da classe pai
        parent::__construct($parcelaId, $valor, $comprovanteArquivoId, $pagoEm);
        $this->nossoNumero = trim($nossoNumero);
    }

    public function getForma(): string
    {
        return 'BOLETO';
    }

    protected function validarDados(): array
    {
        return mb_strlen($this->nossoNumero) > 50 ? ['nosso_numero' => 'Use no máximo 50 caracteres.'] : [];
    }

    protected function detalhes(): array
    {
        return $this->nossoNumero !== '' ? ['nosso_numero' => $this->nossoNumero] : [];
    }
}
