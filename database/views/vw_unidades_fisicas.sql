-- =============================================================================
-- SGCB — View simples (Aula 09 – Views)
-- Arquivo: database/views/vw_unidades_fisicas.sql
--
-- Finalidade: lista cada container físico com o nome do produto (modelo) e da
--             categoria, sem precisar escrever os JOINs toda vez.
-- Conceitos da aula: CREATE OR REPLACE VIEW, INNER JOIN, apelidos de colunas.
-- Depende de: unidades_fisicas, produtos, categorias (migração 001).
-- =============================================================================

USE sgcb;

CREATE OR REPLACE VIEW vw_unidades_fisicas AS
SELECT
  u.id                  AS unidade_id,
  u.codigo              AS codigo_unidade,
  p.codigo              AS codigo_produto,
  p.nome                AS produto,
  c.nome                AS categoria,
  u.estado_operacional,
  u.localidade,
  u.ultima_vistoria_em
FROM unidades_fisicas u
INNER JOIN produtos p
  ON p.id = u.produto_id
INNER JOIN categorias c
  ON c.id = p.categoria_id;

-- -----------------------------------------------------------------------------
-- Exemplos de uso
-- -----------------------------------------------------------------------------
-- Todos os containers:
--   SELECT * FROM vw_unidades_fisicas;
--
-- Só os disponíveis, ordenados por produto:
--   SELECT * FROM vw_unidades_fisicas
--   WHERE estado_operacional = 'DISPONIVEL'
--   ORDER BY produto, codigo_unidade;
