-- =============================================================================
-- SGCB — Functions (Atividade para Entrega — Aula de Functions)
-- Arquivo: database/functions/fn_funcoes_sgcb.sql
--
-- Finalidade: 5 Functions ligadas ao tema do projeto (locação de contêineres),
--             todas usando as tabelas do banco do projeto (sgcb).
--   1. fn_valor_total_contrato   → DECIMAL : valor total a receber de um contrato
--   2. fn_unidades_disponiveis   → INT     : contêineres de um produto disponíveis no pátio
--   3. fn_situacao_parcela       → VARCHAR : situação de uma parcela (Paga, Em atraso...)
--   4. fn_proximo_vencimento     → DATE    : próxima parcela a vencer de um contrato
--   5. fn_saldo_devedor_cliente  → DECIMAL : quanto um cliente ainda deve
--
-- Comandos usados: DELIMITER, CREATE FUNCTION, parâmetros com prefixo p_,
--   RETURNS, DECLARE, SELECT ... INTO, IF/ELSEIF e RETURN.
--   READS SQL DATA avisa ao banco que a função só lê as tabelas (não altera nada).
--
-- Como executar: no phpMyAdmin, selecione o banco "sgcb" e use a aba IMPORTAR
-- com este arquivo (ou o cliente mysql, como os outros scripts da pasta database).
-- Para criar de novo uma function que já existe, apague-a antes com
-- DROP FUNCTION nome_da_funcao;
--
-- Os exemplos de SELECT usam os dados de exemplo (seed 003). As regras de cálculo
-- são as mesmas do sistema em PHP (src/Models/Contrato.php, Parcela.php e Cliente.php).
-- =============================================================================

USE sgcb;


-- -----------------------------------------------------------------------------
-- Function 1 — Valor total do contrato.
-- Total a receber = soma dos itens (subtotal) + frete/deslocamento + outros valores.
-- A caução não entra, porque é devolvida ao cliente no fim da locação.
-- Parâmetro: p_contrato_id = id do contrato.
-- Exemplo:   SELECT fn_valor_total_contrato(6);
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE FUNCTION fn_valor_total_contrato(
  p_contrato_id BIGINT UNSIGNED
)
RETURNS DECIMAL(14,2)
READS SQL DATA
BEGIN
  DECLARE v_itens DECIMAL(14,2);
  DECLARE v_extras DECIMAL(14,2);

  SELECT COALESCE(SUM(subtotal), 0)
    INTO v_itens
    FROM contrato_itens
   WHERE contrato_id = p_contrato_id;

  SELECT COALESCE(frete_deslocamento, 0) + COALESCE(outros_valores, 0)
    INTO v_extras
    FROM contratos
   WHERE id = p_contrato_id;

  RETURN v_itens + COALESCE(v_extras, 0);
END$$
DELIMITER ;


-- -----------------------------------------------------------------------------
-- Function 2 — Contêineres disponíveis de um produto.
-- Conta as unidades físicas que estão hoje no pátio (estado DISPONIVEL).
-- Parâmetro: p_produto_id = id do produto (ex.: Contêiner 2,50 m).
-- Exemplo:   SELECT fn_unidades_disponiveis(2);
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE FUNCTION fn_unidades_disponiveis(
  p_produto_id BIGINT UNSIGNED
)
RETURNS INT
READS SQL DATA
BEGIN
  DECLARE v_total INT;

  SELECT COUNT(*)
    INTO v_total
    FROM unidades_fisicas
   WHERE produto_id = p_produto_id
     AND estado_operacional = 'DISPONIVEL';

  RETURN v_total;
END$$
DELIMITER ;


-- -----------------------------------------------------------------------------
-- Function 3 — Situação de uma parcela.
-- Valor devido = valor original + acréscimo − desconto; recebido = pagamentos não estornados.
-- Retorna: 'Cancelada', 'Paga', 'Em atraso', 'Parcial' ou 'A vencer'.
-- Parâmetro: p_parcela_id = id da parcela.
-- Exemplo:   SELECT fn_situacao_parcela(1);
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE FUNCTION fn_situacao_parcela(
  p_parcela_id BIGINT UNSIGNED
)
RETURNS VARCHAR(20)
READS SQL DATA
BEGIN
  DECLARE v_devido DECIMAL(14,2);
  DECLARE v_recebido DECIMAL(14,2);
  DECLARE v_vencimento DATE;
  DECLARE v_cancelada BOOLEAN;

  SELECT valor_original + acrescimo - desconto, vencimento, cancelada
    INTO v_devido, v_vencimento, v_cancelada
    FROM parcelas
   WHERE id = p_parcela_id;

  SELECT COALESCE(SUM(valor), 0)
    INTO v_recebido
    FROM pagamentos
   WHERE parcela_id = p_parcela_id
     AND estornado_em IS NULL;

  IF v_devido IS NULL THEN
    RETURN 'Não encontrada';
  ELSEIF v_cancelada THEN
    RETURN 'Cancelada';
  ELSEIF v_recebido >= v_devido THEN
    RETURN 'Paga';
  ELSEIF v_vencimento < CURDATE() THEN
    RETURN 'Em atraso';
  ELSEIF v_recebido > 0 THEN
    RETURN 'Parcial';
  ELSE
    RETURN 'A vencer';
  END IF;
END$$
DELIMITER ;


-- -----------------------------------------------------------------------------
-- Function 4 — Próximo vencimento do contrato.
-- Menor data de vencimento entre as parcelas não canceladas que ainda têm saldo.
-- Retorna NULL quando não há nada em aberto (contrato quitado ou cancelado).
-- Parâmetro: p_contrato_id = id do contrato.
-- Exemplo:   SELECT fn_proximo_vencimento(8);
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE FUNCTION fn_proximo_vencimento(
  p_contrato_id BIGINT UNSIGNED
)
RETURNS DATE
READS SQL DATA
BEGIN
  DECLARE v_data DATE;

  SELECT MIN(pa.vencimento)
    INTO v_data
    FROM parcelas pa
   WHERE pa.contrato_id = p_contrato_id
     AND pa.cancelada = FALSE
     AND pa.valor_original + pa.acrescimo - pa.desconto >
         (SELECT COALESCE(SUM(pg.valor), 0)
            FROM pagamentos pg
           WHERE pg.parcela_id = pa.id
             AND pg.estornado_em IS NULL);

  RETURN v_data;
END$$
DELIMITER ;


-- -----------------------------------------------------------------------------
-- Function 5 — Saldo devedor do cliente.
-- Soma o que falta pagar nas parcelas não canceladas dos contratos ATIVO ou FINALIZADO
-- do cliente (mesma regra usada para conferir o limite de crédito ao gerar contrato).
-- Parâmetro: p_cliente_id = id do cliente (tabela pessoas).
-- Exemplo:   SELECT fn_saldo_devedor_cliente(5);
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE FUNCTION fn_saldo_devedor_cliente(
  p_cliente_id BIGINT UNSIGNED
)
RETURNS DECIMAL(14,2)
READS SQL DATA
BEGIN
  DECLARE v_saldo DECIMAL(14,2);

  SELECT COALESCE(SUM(pa.valor_original + pa.acrescimo - pa.desconto
                      - (SELECT COALESCE(SUM(pg.valor), 0)
                           FROM pagamentos pg
                          WHERE pg.parcela_id = pa.id
                            AND pg.estornado_em IS NULL)), 0)
    INTO v_saldo
    FROM parcelas pa
    JOIN contratos c ON c.id = pa.contrato_id
   WHERE c.cliente_id = p_cliente_id
     AND pa.cancelada = FALSE
     AND c.status IN ('ATIVO', 'FINALIZADO');

  IF v_saldo < 0 THEN
    RETURN 0;
  END IF;

  RETURN v_saldo;
END$$
DELIMITER ;


-- =============================================================================
-- Demonstração (rode depois de criar as functions; usa os dados do seed 003)
-- =============================================================================

-- 1. Valor total de cada contrato gerado
SELECT numero_contrato, status, fn_valor_total_contrato(id) AS valor_total
  FROM contratos
 WHERE numero_contrato IS NOT NULL
 ORDER BY numero_contrato;

-- 2. Contêineres disponíveis por produto
SELECT codigo, nome, fn_unidades_disponiveis(id) AS disponiveis
  FROM produtos
 ORDER BY codigo;

-- 3. Situação das parcelas de um contrato (CT-000006)
SELECT pa.numero, pa.vencimento, pa.valor_original, fn_situacao_parcela(pa.id) AS situacao
  FROM parcelas pa
  JOIN contratos c ON c.id = pa.contrato_id
 WHERE c.numero_contrato = 'CT-000006'
 ORDER BY pa.numero;

-- 4. Próximo vencimento dos contratos ativos
SELECT numero_contrato, fn_proximo_vencimento(id) AS proximo_vencimento
  FROM contratos
 WHERE status = 'ATIVO'
 ORDER BY proximo_vencimento;

-- 5. Clientes com saldo devedor
SELECT nome_razao_social AS cliente, fn_saldo_devedor_cliente(id) AS saldo_devedor
  FROM pessoas
 WHERE tipo_cadastro IN ('CLIENTE', 'AMBOS')
   AND fn_saldo_devedor_cliente(id) > 0
 ORDER BY saldo_devedor DESC;
