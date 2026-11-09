<?php
/**
 * Pagamento com cartão de crédito ou débito (formas CARTAO_CREDITO e CARTAO_DEBITO).
 *
 * O cartão é passado na maquininha; o sistema NÃO recebe nem guarda o número completo.
 * Só os 4 últimos dígitos, a bandeira e o código de autorização (opcionais) vão para a auditoria.
 */

class PagamentoCartao extends Pagamento
{
    private string $tipo;            // CREDITO ou DEBITO
    private int $parcelas;
    private string $ultimosDigitos;
    private string $bandeira;
    private string $codigoAutorizacao;

    public function __construct(int $parcelaId, string $valor, ?int $comprovanteArquivoId = null, ?string $pagoEm = null,
        string $tipo = 'CREDITO', int $parcelas = 1, string $ultimosDigitos = '', string $bandeira = '', string $codigoAutorizacao = '')
    {
        // chamada do construtor da classe pai
        parent::__construct($parcelaId, $valor, $comprovanteArquivoId, $pagoEm);
        $this->tipo              = strtoupper($tipo);
        $this->parcelas          = $parcelas;
        $this->ultimosDigitos    = trim($ultimosDigitos);
        $this->bandeira          = trim($bandeira);
        $this->codigoAutorizacao = trim($codigoAutorizacao);
    }

    public function getForma(): string
    {
        return $this->tipo === 'DEBITO' ? 'CARTAO_DEBITO' : 'CARTAO_CREDITO';
    }

    /** Ex.: "Cartão de crédito final 1234 em 3x". */
    public function getFormaPagamento(): string
    {
        $texto = parent::getFormaPagamento();
        if ($this->ultimosDigitos !== '') {
            $texto .= ' final ' . $this->ultimosDigitos;
        }

        return $this->tipo === 'CREDITO' && $this->parcelas > 1 ? $texto . " em {$this->parcelas}x" : $texto;
    }

    protected function validarDados(): array
    {
        $erros = [];
        if (!in_array($this->tipo, ['CREDITO', 'DEBITO'], true)) {
            $erros['forma'] = 'Escolha crédito ou débito.';
        }
        // Débito é sempre à vista; crédito de 1 a 12 vezes.
        if ($this->parcelas < 1 || $this->parcelas > 12 || ($this->tipo === 'DEBITO' && $this->parcelas !== 1)) {
            $erros['parcelas'] = $this->tipo === 'DEBITO' ? 'Débito é sempre em 1 vez.' : 'Informe de 1 a 12 parcelas.';
        }
        if ($this->ultimosDigitos !== '' && !preg_match('/^\d{4}$/', $this->ultimosDigitos)) {
            $erros['ultimos_digitos'] = 'Informe só os 4 últimos dígitos do cartão.';
        }
        if (mb_strlen($this->bandeira) > 30) {
            $erros['bandeira'] = 'Use no máximo 30 caracteres.';
        }
        if (mb_strlen($this->codigoAutorizacao) > 40) {
            $erros['codigo_autorizacao'] = 'Use no máximo 40 caracteres.';
        }

        return $erros;
    }

    protected function detalhes(): array
    {
        return array_filter([
            'parcelas_cartao'    => $this->parcelas,
            'ultimos_digitos'    => $this->ultimosDigitos,
            'bandeira'           => $this->bandeira,
            'codigo_autorizacao' => $this->codigoAutorizacao,
        ], static fn ($v): bool => $v !== '');
    }
}
