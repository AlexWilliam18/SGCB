<?php
/**
 * Registro de quem fez o quê no sistema (tabela auditoria).
 * Os models chamam registrar() em toda gravação importante (via Modelo::auditar).
 */

final class Auditoria extends Modelo
{
    /**
     * Grava uma linha de auditoria.
     *
     * @param string $entidade   Tabela afetada (ex.: 'pessoas').
     * @param string $acao       Ex.: CRIAR, ALTERAR, INATIVAR, CANCELAR, ESTORNAR.
     * @param array  $detalhes   Dados extras, gravados como JSON.
     */
    public static function registrar(?int $usuarioId, string $entidade, int $registroId, string $acao, array $detalhes = []): void
    {
        self::pdo()->prepare(
            'INSERT INTO auditoria (usuario_id, entidade, registro_id, acao, detalhes, criado_em)
             VALUES (:usuario, :entidade, :registro, :acao, :detalhes, :criado)'
        )->execute([
            'usuario'  => $usuarioId,
            'entidade' => $entidade,
            'registro' => $registroId,
            'acao'     => $acao,
            'detalhes' => $detalhes ? self::json($detalhes) : null,
            'criado'   => self::agora(),
        ]);
    }

    /** Histórico de um registro (mais recente primeiro), com o nome de quem fez. */
    public static function listar(string $entidade, int $registroId, int $limite = 50): array
    {
        $limite = max(1, min($limite, 500));

        $consulta = self::pdo()->prepare(
            "SELECT a.id, a.acao, a.detalhes, a.criado_em, u.nome AS usuario
               FROM auditoria a
               LEFT JOIN usuarios u ON u.id = a.usuario_id
              WHERE a.entidade = :entidade AND a.registro_id = :registro
              ORDER BY a.criado_em DESC, a.id DESC
              LIMIT $limite"
        );
        $consulta->execute(['entidade' => $entidade, 'registro' => $registroId]);

        return array_map(static function (array $linha): array {
            $linha['id']       = (int) $linha['id'];
            $linha['detalhes'] = $linha['detalhes'] !== null ? json_decode($linha['detalhes'], true) : null;
            return $linha;
        }, $consulta->fetchAll());
    }
}
