<?php
/**
 * Pagamento via Pix (forma PIX).
 * A chave é opcional: a tabela pagamentos não tem coluna para ela, então fica registrada na auditoria.
 */

class PagamentoPix extends Pagamento
{
    private string $chavePix;

    public function __construct(int $parcelaId, string $valor, ?int $comprovanteArquivoId = null, ?string $pagoEm = null, string $chavePix = '')
    {
        // chamada do construtor da classe pai
        parent::__construct($parcelaId, $valor, $comprovanteArquivoId, $pagoEm);
        $this->chavePix = trim($chavePix);
    }

    public function getForma(): string
    {
        return 'PIX';
    }

    protected function validarDados(): array
    {
        // Chave Pix tem no máximo 77 caracteres (chave aleatória, e-mail, telefone ou documento).
        return mb_strlen($this->chavePix) > 77 ? ['chave_pix' => 'Chave Pix inválida.'] : [];
    }

    protected function detalhes(): array
    {
        return $this->chavePix !== '' ? ['chave_pix' => $this->chavePix] : [];
    }
}
