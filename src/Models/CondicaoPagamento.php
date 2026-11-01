<?php
/**
 * Condições de pagamento reutilizáveis (tabela condicoes_pagamento).
 * Ex.: "30 dias" = 1 parcela vencendo 30 dias após o início;
 *      "3x (30/60/90)" = 3 parcelas, a primeira em 30 dias e as outras de 30 em 30.
 */

final class CondicaoPagamento extends Modelo
{
    /** Condições ativas para os selects (cadastro de cliente, contrato). */
    public static function listarAtivas(): array
    {
        $linhas = self::pdo()->query(
            'SELECT id, nome, dias_primeiro_vencimento, intervalo_dias, numero_parcelas_padrao, ativo
               FROM condicoes_pagamento WHERE ativo = 1 ORDER BY numero_parcelas_padrao, nome'
        )->fetchAll();

        return array_map([self::class, 'formatar'], $linhas);
    }

    public static function buscar(int $id): ?array
    {
        $consulta = self::pdo()->prepare(
            'SELECT id, nome, dias_primeiro_vencimento, intervalo_dias, numero_parcelas_padrao, ativo
               FROM condicoes_pagamento WHERE id = :id'
        );
        $consulta->execute(['id' => $id]);
        $linha = $consulta->fetch();

        return $linha ? self::formatar($linha) : null;
    }

    /**
     * Cadastra uma condição. Campos: nome, dias_primeiro_vencimento, intervalo_dias, numero_parcelas_padrao.
     *
     * @return int id criado.
     */
    public static function cadastrar(array $dados, int $usuarioId): int
    {
        $erros = [];
        $nome  = self::campoTexto($dados, 'nome', 100, $erros, true);
        $dias  = self::campoInteiro($dados, 'dias_primeiro_vencimento', $erros, true, 0, 365);
        $intervalo = self::campoInteiro($dados, 'intervalo_dias', $erros, true, 0, 365);
        $parcelas  = self::campoInteiro($dados, 'numero_parcelas_padrao', $erros, true, 1, 120);
        if ($parcelas !== null && $parcelas > 1 && $intervalo === 0) {
            $erros['intervalo_dias'] = 'Informe o intervalo entre as parcelas.';
        }
        self::lancarSeErros($erros);

        $consulta = self::pdo()->prepare('SELECT 1 FROM condicoes_pagamento WHERE nome = :nome');
        $consulta->execute(['nome' => $nome]);
        if ($consulta->fetchColumn() !== false) {
            throw new ErroValidacao('Já existe uma condição com este nome.', ['nome' => 'Nome já cadastrado.'], 409);
        }

        return self::transacao(static function (PDO $pdo) use ($nome, $dias, $intervalo, $parcelas, $usuarioId): int {
            $pdo->prepare(
                'INSERT INTO condicoes_pagamento (nome, dias_primeiro_vencimento, intervalo_dias, numero_parcelas_padrao, ativo)
                 VALUES (:nome, :dias, :intervalo, :parcelas, 1)'
            )->execute(['nome' => $nome, 'dias' => $dias, 'intervalo' => $intervalo, 'parcelas' => $parcelas]);
            $id = (int) $pdo->lastInsertId();
            self::auditar($usuarioId, 'condicoes_pagamento', $id, 'CRIAR', ['nome' => $nome]);

            return $id;
        });
    }

    public static function alterarStatus(int $id, bool $ativo, int $usuarioId): void
    {
        if (self::buscar($id) === null) {
            throw new ErroValidacao('Condição de pagamento não encontrada.', [], 404);
        }

        self::transacao(static function (PDO $pdo) use ($id, $ativo, $usuarioId): void {
            $pdo->prepare('UPDATE condicoes_pagamento SET ativo = :ativo WHERE id = :id')->execute(['ativo' => $ativo ? 1 : 0, 'id' => $id]);
            self::auditar($usuarioId, 'condicoes_pagamento', $id, $ativo ? 'ATIVAR' : 'INATIVAR');
        });
    }

    private static function formatar(array $c): array
    {
        return [
            'id'                       => (int) $c['id'],
            'nome'                     => $c['nome'],
            'dias_primeiro_vencimento' => (int) $c['dias_primeiro_vencimento'],
            'intervalo_dias'           => (int) $c['intervalo_dias'],
            'numero_parcelas_padrao'   => (int) $c['numero_parcelas_padrao'],
            'ativo'                    => (bool) $c['ativo'],
        ];
    }
}
