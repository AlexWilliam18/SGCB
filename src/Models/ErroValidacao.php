<?php
/**
 * Erro de regra de negócio ou de dados inválidos, lançado pelos models.
 * Carrega a mensagem geral, os erros por campo e o status HTTP sugerido,
 * para a API devolver no formato que as telas esperam: { "error": "...", "fields": { "campo": "..." } }.
 */

final class ErroValidacao extends RuntimeException
{
    /** Erros por campo (nome do campo no formulário → mensagem). */
    private array $campos;

    /** Status HTTP sugerido: 422 = dados inválidos, 404 = não encontrado, 409 = conflito (duplicado/indisponível). */
    private int $status;

    public function __construct(string $mensagem, array $campos = [], int $status = 422)
    {
        parent::__construct($mensagem);
        $this->campos = $campos;
        $this->status = $status;
    }

    public function getCampos(): array
    {
        return $this->campos;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
