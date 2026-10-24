-- =============================================================================
-- SGCB — View complexa (Aula 10 – Views Complexas)
-- Arquivo: database/views/vw_resumo_financeiro_contratos.sql
--
-- Finalidade: uma linha por contrato com o resumo financeiro: quanto vale,
--             quanto já foi pago, quanto falta e quantas parcelas estão em atraso.
-- Conceitos da aula: vários JOINs (INNER e LEFT), subconsulta, funções de
--             agregação (COUNT, SUM), GROUP BY, expressões matemáticas, CASE e
--             funções de texto/data (COALESCE, CURDATE).
-- Depende de: contratos, pessoas, parcelas, pagamentos (migração 001).
--
-- Regras usadas no cálculo:
--   - parcelas canceladas não entram;
--   - valor devido da parcela = valor_original + acrescimo - desconto;
--   - pagamentos estornados (estornado_em preenchido) não contam como pagos;
--   - parcela em atraso = vencimento já passou e o pago é menor que o devido.
-- =============================================================================

USE sgcb;

CREATE OR REPLACE VIEW vw_resumo_financeiro_contratos AS
SELECT
  c.id                                                  AS contrato_id,
  c.numero_contrato,
  COALESCE(cli.nome_fantasia, cli.nome_razao_social)    AS cliente,
  c.status,
  c.data_inicio,
  c.data_fim,

  -- quantidade de parcelas válidas do contrato
  COUNT(pa.id)                                          AS total_parcelas,

  -- valor total devido
  COALESCE(SUM(pa.valor_original + pa.acrescimo - pa.desconto), 0)  AS valor_total,

  -- valor efetivamente pago (sem estornos)
  COALESCE(SUM(pg.total_pago), 0)                       AS valor_pago,

  -- quanto ainda falta receber
  COALESCE(SUM(pa.valor_original + pa.acrescimo - pa.desconto), 0)
    - COALESCE(SUM(pg.total_pago), 0)                   AS saldo_devedor,

  -- parcelas vencidas e não quitadas
  COALESCE(SUM(
    CASE
      WHEN pa.vencimento < CURDATE()
       AND COALESCE(pg.total_pago, 0) < (pa.valor_original + pa.acrescimo - pa.desconto)
      THEN 1 ELSE 0
    END
  ), 0)                                                 AS parcelas_em_atraso

FROM contratos c
INNER JOIN pessoas cli
  ON cli.id = c.cliente_id
LEFT JOIN parcelas pa
  ON pa.contrato_id = c.id
 AND pa.cancelada = FALSE
-- Subconsulta: soma os pagamentos de cada parcela ANTES do JOIN.
-- Assim cada parcela aparece uma única vez e os valores não são somados em dobro.
LEFT JOIN (
  SELECT
    parcela_id,
    SUM(valor) AS total_pago
  FROM pagamentos
  WHERE estornado_em IS NULL
  GROUP BY parcela_id
) pg
  ON pg.parcela_id = pa.id
GROUP BY
  c.id,
  c.numero_contrato,
  cli.nome_fantasia,
  cli.nome_razao_social,
  c.status,
  c.data_inicio,
  c.data_fim;

-- -----------------------------------------------------------------------------
-- Exemplos de uso
-- -----------------------------------------------------------------------------
-- Resumo de todos os contratos:
--   SELECT * FROM vw_resumo_financeiro_contratos;
--
-- Contratos ativos com saldo em aberto, do maior para o menor:
--   SELECT cliente, numero_contrato, saldo_devedor, parcelas_em_atraso
--   FROM vw_resumo_financeiro_contratos
--   WHERE status = 'ATIVO' AND saldo_devedor > 0
--   ORDER BY saldo_devedor DESC;
