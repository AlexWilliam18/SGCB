-- =============================================================================
-- SGCB - View simples (Aula 09 - Views)
--
-- Lista cada container físico com o nome do produto (modelo) e da categoria,
-- sem precisar escrever os JOINs toda vez.
-- Tabelas usadas: unidades_fisicas, produtos e categorias (migração 001).
-- =============================================================================

USE sgcb;

CREATE OR REPLACE VIEW vw_unidades_fisicas AS
SELECT
  u.id                  AS unidade_id,       -- id do container
  u.codigo              AS codigo_unidade,   -- código pintado no container
  p.codigo              AS codigo_produto,   -- código do modelo
  p.nome                AS produto,          -- nome do modelo
  c.nome                AS categoria,        -- categoria do modelo
  u.estado_operacional,                      -- DISPONIVEL, LOCADA, MANUTENCAO...
  u.localidade,                              -- onde o container está
  u.ultima_vistoria_em                       -- data da última vistoria
FROM unidades_fisicas u
-- liga cada container ao seu modelo (produto)
INNER JOIN produtos p
ON p.id = u.produto_id
-- liga o modelo à sua categoria
INNER JOIN categorias c
ON c.id = p.categoria_id;

-- -----------------------------------------------------------------------------
-- Exemplos de uso
-- -----------------------------------------------------------------------------
-- Todos os containers:
--   SELECT * FROM vw_unidades_fisicas;
--
-- Só os disponíveis, em ordem de código:
--   SELECT * FROM vw_unidades_fisicas
--   WHERE estado_operacional = 'DISPONIVEL'
--   ORDER BY codigo_unidade;
--
-- Ver como a view foi criada / remover a view:
--   SHOW CREATE VIEW vw_unidades_fisicas;
--   DROP VIEW vw_unidades_fisicas;
