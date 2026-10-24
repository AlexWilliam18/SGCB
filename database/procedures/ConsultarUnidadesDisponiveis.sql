-- =============================================================================
-- SGCB — Stored Procedure (Aula 13 – Introdução às Stored Procedures)
-- Arquivo: database/procedures/ConsultarUnidadesDisponiveis.sql
--
-- Finalidade: dado um produto (modelo de container) e um período, lista os
--             containers físicos livres para locação nesse período e devolve
--             a quantidade em um parâmetro de saída.
-- Conceitos da aula: DELIMITER, CREATE PROCEDURE, BEGIN/END, parâmetros IN e
--             OUT, CALL e DROP PROCEDURE.
-- Depende de: unidades_fisicas, produtos, agenda_unidades (migração 001).
--
-- Uma unidade está disponível quando:
--   1. pertence ao produto informado;
--   2. não está em manutenção nem inativa (estado_operacional);
--   3. não tem nenhum compromisso na agenda que se sobreponha ao período.
--      Sobreposição: o compromisso começa antes do fim pedido E termina depois
--      do início pedido. Compromissos cancelados ou já devolvidos não contam.
-- =============================================================================

USE sgcb;

DROP PROCEDURE IF EXISTS ConsultarUnidadesDisponiveis;

DELIMITER $$

CREATE PROCEDURE ConsultarUnidadesDisponiveis(
  IN  p_produto_id          BIGINT UNSIGNED,  -- produto (modelo) desejado
  IN  p_inicio              DATETIME,         -- início do período da locação
  IN  p_fim                 DATETIME,         -- fim do período da locação
  OUT p_total_disponiveis   INT               -- quantidade de unidades livres
)
BEGIN
  -- Validação: o período precisa ser coerente.
  IF p_inicio IS NULL OR p_fim IS NULL OR p_fim <= p_inicio THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Periodo invalido: a data final deve ser maior que a inicial.';
  END IF;

  -- 1) Lista das unidades livres no período.
  SELECT
    u.id          AS unidade_id,
    u.codigo      AS codigo_unidade,
    p.nome        AS produto,
    u.localidade,
    u.ultima_vistoria_em
  FROM unidades_fisicas u
  INNER JOIN produtos p
    ON p.id = u.produto_id
  WHERE u.produto_id = p_produto_id
    AND u.estado_operacional NOT IN ('MANUTENCAO', 'INATIVA')
    AND NOT EXISTS (
      SELECT 1
      FROM agenda_unidades a
      WHERE a.unidade_id = u.id
        AND a.status IN ('RESERVADA', 'EM_USO', 'MANUTENCAO')
        AND a.inicio < p_fim
        AND a.fim    > p_inicio
    )
  ORDER BY u.codigo;

  -- 2) Mesma regra, agora só contando, para o parâmetro de saída.
  SELECT COUNT(*)
    INTO p_total_disponiveis
  FROM unidades_fisicas u
  WHERE u.produto_id = p_produto_id
    AND u.estado_operacional NOT IN ('MANUTENCAO', 'INATIVA')
    AND NOT EXISTS (
      SELECT 1
      FROM agenda_unidades a
      WHERE a.unidade_id = u.id
        AND a.status IN ('RESERVADA', 'EM_USO', 'MANUTENCAO')
        AND a.inicio < p_fim
        AND a.fim    > p_inicio
    );
END $$

DELIMITER ;

-- -----------------------------------------------------------------------------
-- Exemplo de uso
-- -----------------------------------------------------------------------------
-- Containers do produto 1 livres entre 01/11 e 30/11/2026:
--   CALL ConsultarUnidadesDisponiveis(1, '2026-11-01 08:00:00', '2026-11-30 18:00:00', @total);
--   SELECT @total AS total_disponiveis;
--
-- Para remover a procedure:
--   DROP PROCEDURE IF EXISTS ConsultarUnidadesDisponiveis;
