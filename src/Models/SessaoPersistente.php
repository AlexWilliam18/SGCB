<?php
/**
 * "Lembrar de mim" (tabela sessoes_usuario).
 * O token aleatório vai para um cookie; no banco fica só o hash SHA-256,
 * então quem tiver acesso ao banco não consegue se passar pelo usuário.
 */

final class SessaoPersistente extends Modelo
{
    /** Cria a sessão e devolve o token que deve ir para o cookie (httponly). */
    public static function criar(int $usuarioId, int $dias = 30): string
    {
        $token = bin2hex(random_bytes(32));

        self::pdo()->prepare(
            'INSERT INTO sessoes_usuario (usuario_id, token_hash, criado_em, expira_em, persistente)
             VALUES (:usuario, :hash, :criado, :expira, 1)'
        )->execute([
            'usuario' => $usuarioId,
            'hash'    => hash('sha256', $token),
            'criado'  => self::agora(),
            'expira'  => date('Y-m-d H:i:s', strtotime("+$dias days")),
        ]);

        return $token;
    }

    /** id do usuário dono do token, se a sessão ainda vale e o usuário está ativo; senão null. */
    public static function validar(string $token): ?int
    {
        $consulta = self::pdo()->prepare(
            'SELECT s.usuario_id FROM sessoes_usuario s
               JOIN usuarios u ON u.id = s.usuario_id AND u.ativo = 1
              WHERE s.token_hash = :hash AND s.revogado_em IS NULL AND s.expira_em > :agora'
        );
        $consulta->execute(['hash' => hash('sha256', $token), 'agora' => self::agora()]);
        $id = $consulta->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** Encerra uma sessão (botão "Sair"). */
    public static function revogar(string $token): void
    {
        self::pdo()->prepare('UPDATE sessoes_usuario SET revogado_em = :agora WHERE token_hash = :hash AND revogado_em IS NULL')
            ->execute(['agora' => self::agora(), 'hash' => hash('sha256', $token)]);
    }

    /** Encerra todas as sessões do usuário (troca de senha, usuário desativado). */
    public static function revogarTodas(int $usuarioId): void
    {
        self::pdo()->prepare('UPDATE sessoes_usuario SET revogado_em = :agora WHERE usuario_id = :id AND revogado_em IS NULL')
            ->execute(['agora' => self::agora(), 'id' => $usuarioId]);
    }
}
