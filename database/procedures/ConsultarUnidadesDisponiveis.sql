-- =============================================================================
-- SGCB — Stored Procedure (Aula 13 – Introdução às Stored Procedures)
-- Arquivo: database/procedures/ConsultarUnidadesDisponiveis.sql
--
-- Finalidade: recebe o id de um produto (modelo de container), lista os
--             containers desse produto que estão com estado DISPONIVEL e
--             devolve a quantidade em um parâmetro de saída.
--
-- Comandos usados e aula de origem:
--   DROP PROCEDURE IF EXISTS, DELIMITER, CREATE PROCEDURE,
--   BEGIN/END, parâmetro IN, parâmetro OUT, SELECT ... INTO, CALL ... Aula 13
--   INNER JOIN ... ON e apelidos ..................................... Aula 04
--   WHERE com AND .................................................... Revisão CRUD / Aula 03
--   COUNT(*) ......................................................... Aula 11
--
-- Depende de: unidades_fisicas, produtos (migração 001).
-- =============================================================================

USE sgcb;

-- Remove a versão anterior, se existir (o MySQL não tem ALTER PROCEDURE).
DROP PROCEDURE IF EXISTS ConsultarUnidadesDisponiveis;

-- Troca o delimitador para o MySQL não encerrar a procedure no primeiro ";".
DELIMITER $$

CREATE PROCEDURE ConsultarUnidadesDisponiveis(
  IN codigoProduto BIGINT UNSIGNED,   -- entrada: id do produto pesquisado
  OUT quantidade INT                  -- saída: total de containers disponíveis
)
BEGIN
  -- 1) Lista os containers disponíveis do produto informado.
  SELECT
    u.id         AS unidade_id,
    u.codigo     AS codigo_unidade,
    p.nome       AS produto,
    u.localidade
  FROM unidades_fisicas u
  INNER JOIN produtos p
  ON p.id = u.produto_id
  WHERE u.produto_id = codigoProduto
    AND u.estado_operacional = 'DISPONIVEL';

  -- 2) Conta os mesmos containers e guarda o resultado no parâmetro OUT.
  SELECT COUNT(*)
  INTO quantidade
  FROM unidades_fisicas
  WHERE produto_id = codigoProduto
    AND estado_operacional = 'DISPONIVEL';
END $$

-- Volta o delimitador para o padrão.
DELIMITER ;

-- -----------------------------------------------------------------------------
-- Exemplo de uso
-- -----------------------------------------------------------------------------
-- Containers disponíveis do produto 1:
--   CALL ConsultarUnidadesDisponiveis(1, @qtd);
--   SELECT @qtd;
--
-- Para remover a procedure:
--   DROP PROCEDURE IF EXISTS ConsultarUnidadesDisponiveis;
