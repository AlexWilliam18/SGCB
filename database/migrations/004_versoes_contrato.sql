-- =============================================================================
-- SGCB - Migração 004: versões (segunda via) dos contratos de locação.
--
-- Um contrato ativo pode ter alguns dados corrigidos (observações, local de entrega,
-- responsável pela assinatura e vencimento das parcelas sem pagamento). Antes de cada
-- correção, o sistema guarda aqui uma cópia completa de como o contrato estava,
-- então a versão original nunca se perde.
--
-- Bancos criados com a 001 atual já têm esta tabela e não precisam rodar este arquivo.
-- =============================================================================

USE sgcb;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS contrato_versoes (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contrato_id   BIGINT UNSIGNED NOT NULL,
  versao        INT UNSIGNED NOT NULL,   -- 1 = original, 2, 3...
  dados         JSON NOT NULL,           -- contrato completo como estava nesta versão
  motivo        VARCHAR(500) NOT NULL,   -- por que esta versão foi substituída
  alteracoes    VARCHAR(255),            -- o que mudou (ex.: "Local de entrega, Vencimentos")
  alterado_por  BIGINT UNSIGNED,         -- quem fez a alteração que substituiu esta versão
  alterado_em   DATETIME NOT NULL,
  UNIQUE (contrato_id, versao),
  FOREIGN KEY (contrato_id) REFERENCES contratos(id),
  FOREIGN KEY (alterado_por) REFERENCES usuarios(id)
);
