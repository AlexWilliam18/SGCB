-- =============================================================================
-- SGCB — View complexa (Aula 10 – Views Complexas)
-- Arquivo: database/views/vw_resumo_parcelas_contratos.sql
--
-- Finalidade: uma linha por contrato com o resumo das parcelas: quantas são,
--             o valor total a receber e o primeiro e o último vencimento.
--
-- Depende de: contratos, pessoas, parcelas (migração 001).
--
-- Regras do cálculo:
--   - parcelas canceladas não entram (WHERE pa.cancelada = FALSE);
--   - valor devido de cada parcela = valor_original + acrescimo - desconto;
--   - só aparecem contratos que têm parcelas (porque é INNER JOIN).
-- =============================================================================

USE sgcb;

CREATE OR REPLACE VIEW vw_resumo_parcelas_contratos AS
SELECT
  c.id                                                 AS contrato_id,          -- id do contrato
  c.numero_contrato,                                                            -- número impresso no contrato
  UPPER(p.nome_razao_social)                           AS cliente,              -- nome do cliente em maiúsculas
  c.status,                                                                     -- situação do contrato
  YEAR(c.data_inicio)                                  AS ano_inicio,           -- ano em que a locação começou
  COUNT(pa.id)                                         AS total_parcelas,       -- quantidade de parcelas
  SUM(pa.valor_original + pa.acrescimo - pa.desconto)  AS valor_total,          -- soma do valor devido
  MIN(pa.vencimento)                                   AS primeiro_vencimento,  -- vencimento mais antigo
  MAX(pa.vencimento)                                   AS ultimo_vencimento     -- vencimento mais distante
FROM contratos c
-- liga o contrato ao cliente que alugou
INNER JOIN pessoas p
ON p.id = c.cliente_id
-- liga o contrato às suas parcelas
INNER JOIN parcelas pa
ON pa.contrato_id = c.id
-- filtra as linhas antes de agrupar: ignora parcelas canceladas
WHERE pa.cancelada = FALSE
-- agrupa tudo por contrato (uma linha para cada contrato)
GROUP BY
  c.id,
  c.numero_contrato,
  p.nome_razao_social,
  c.status,
  c.data_inicio;

-- -----------------------------------------------------------------------------
-- Exemplos de uso
-- -----------------------------------------------------------------------------
-- Resumo de todos os contratos:
--   SELECT * FROM vw_resumo_parcelas_contratos;
--
-- Só os contratos ativos, do maior valor para o menor:
--   SELECT * FROM vw_resumo_parcelas_contratos
--   WHERE status = 'ATIVO'
--   ORDER BY valor_total DESC;
