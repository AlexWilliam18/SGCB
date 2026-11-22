-- =============================================================================
-- SGCB — Triggers (Lista de Exercícios – Triggers, Parte 4: Projeto Integrador)
-- Arquivo: database/triggers/trg_auditoria_pagamentos.sql
--
-- Finalidade: auditoria automática da tabela crítica PAGAMENTOS (dinheiro recebido
--             dos clientes da Casa Barretto). Tudo o que acontece com um pagamento
--             fica registrado pelo próprio banco, mesmo que a alteração não passe
--             pelo sistema em PHP (ex.: um UPDATE feito direto no phpMyAdmin).
--
--   Tabela nova:
--     auditoria_pagamentos            → histórico (quem, o quê, valores antigos e novos, quando)
--   Triggers:
--     1. trg_pagamentos_auditoria_insert  AFTER INSERT  → registra o pagamento novo
--     2. trg_pagamentos_auditoria_update  AFTER UPDATE  → registra alteração ou estorno
--     3. trg_pagamentos_impede_exclusao   BEFORE DELETE → bloqueia a exclusão (use estorno)
--
-- Comandos usados: CREATE TABLE, DELIMITER, CREATE TRIGGER, BEFORE/AFTER,
--   INSERT/UPDATE/DELETE, FOR EACH ROW, NEW, OLD, IF/ELSEIF, SIGNAL SQLSTATE,
--   USER() e NOW().
--
-- Como executar: no phpMyAdmin, selecione o banco "sgcb" e use a aba IMPORTAR
-- com este arquivo (ou o cliente mysql, como os outros scripts da pasta database).
-- Para criar de novo uma trigger que já existe, apague-a antes com
-- DROP TRIGGER nome_da_trigger;
--
-- Observação: o sistema nunca apaga pagamentos; o estorno só preenche estornado_em
-- (src/Models/Pagamento.php). Por isso a trigger 3 não atrapalha o uso normal.
-- =============================================================================

USE sgcb;


-- -----------------------------------------------------------------------------
-- Tabela de auditoria (questão 27)
-- Uma linha para cada evento em pagamentos. Guarda os valores de antes (OLD) e
-- de depois (NEW) das colunas que importam para o financeiro.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auditoria_pagamentos (
  id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pagamento_id         BIGINT UNSIGNED NOT NULL,     -- pagamento afetado
  parcela_id           BIGINT UNSIGNED,              -- parcela do pagamento
  operacao             VARCHAR(20)  NOT NULL,        -- INCLUSAO, ALTERACAO ou ESTORNO
  valor_antigo         DECIMAL(14,2),                -- OLD.valor (vazio na inclusão)
  valor_novo           DECIMAL(14,2),                -- NEW.valor
  forma_antiga         VARCHAR(20),                  -- OLD.forma (PIX, BOLETO...)
  forma_nova           VARCHAR(20),                  -- NEW.forma
  pago_em_antigo       DATETIME,                     -- OLD.pago_em
  pago_em_novo         DATETIME,                     -- NEW.pago_em
  estornado_em_antigo  DATETIME,                     -- OLD.estornado_em
  estornado_em_novo    DATETIME,                     -- NEW.estornado_em
  motivo_estorno       VARCHAR(500),                 -- motivo informado no estorno
  usuario_sistema_id   BIGINT UNSIGNED,              -- usuário do SGCB (registrado_por / estornado_por)
  usuario_banco        VARCHAR(100) NOT NULL,        -- conta do MySQL que executou (USER())
  data_hora            DATETIME     NOT NULL,        -- quando aconteceu (NOW())
  INDEX idx_aud_pag_pagamento (pagamento_id),
  INDEX idx_aud_pag_data (data_hora)
);


-- -----------------------------------------------------------------------------
-- Trigger 1 — AFTER INSERT (questão 28)
-- Depois que um pagamento é gravado, registra a inclusão com os valores novos.
-- No INSERT só existe NEW (OLD não tem valores).
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE TRIGGER trg_pagamentos_auditoria_insert
AFTER INSERT ON pagamentos
FOR EACH ROW
BEGIN
  INSERT INTO auditoria_pagamentos
    (pagamento_id, parcela_id, operacao,
     valor_novo, forma_nova, pago_em_novo, estornado_em_novo,
     usuario_sistema_id, usuario_banco, data_hora)
  VALUES
    (NEW.id, NEW.parcela_id, 'INCLUSAO',
     NEW.valor, NEW.forma, NEW.pago_em, NEW.estornado_em,
     NEW.registrado_por, USER(), NOW());
END$$
DELIMITER ;


-- -----------------------------------------------------------------------------
-- Trigger 2 — AFTER UPDATE (questão 28)
-- Depois de qualquer alteração, grava os valores de antes (OLD) e de depois (NEW).
-- Se estornado_em passou de vazio para preenchido, a operação é ESTORNO;
-- nos outros casos é ALTERACAO (ex.: valor ou forma corrigidos à mão).
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE TRIGGER trg_pagamentos_auditoria_update
AFTER UPDATE ON pagamentos
FOR EACH ROW
BEGIN
  DECLARE v_operacao VARCHAR(20);

  IF OLD.estornado_em IS NULL AND NEW.estornado_em IS NOT NULL THEN
    SET v_operacao = 'ESTORNO';
  ELSE
    SET v_operacao = 'ALTERACAO';
  END IF;

  INSERT INTO auditoria_pagamentos
    (pagamento_id, parcela_id, operacao,
     valor_antigo, valor_novo, forma_antiga, forma_nova,
     pago_em_antigo, pago_em_novo, estornado_em_antigo, estornado_em_novo,
     motivo_estorno, usuario_sistema_id, usuario_banco, data_hora)
  VALUES
    (NEW.id, NEW.parcela_id, v_operacao,
     OLD.valor, NEW.valor, OLD.forma, NEW.forma,
     OLD.pago_em, NEW.pago_em, OLD.estornado_em, NEW.estornado_em,
     NEW.motivo_estorno, COALESCE(NEW.estornado_por, NEW.registrado_por), USER(), NOW());
END$$
DELIMITER ;


-- -----------------------------------------------------------------------------
-- Trigger 3 — BEFORE DELETE (questão 28/29)
-- Pagamento não pode ser apagado: o histórico financeiro sumiria. O SIGNAL
-- cancela o DELETE com uma mensagem de erro. Para desfazer um pagamento,
-- o sistema usa o estorno (que a trigger 2 registra).
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE TRIGGER trg_pagamentos_impede_exclusao
BEFORE DELETE ON pagamentos
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Erro: pagamentos não podem ser excluídos. Use o estorno.';
END$$
DELIMITER ;


-- =============================================================================
-- Demonstração (rode depois de criar as triggers; usa os dados do seed 003)
-- Rode os comandos um por vez, na ordem, e confira com o SELECT do final.
-- =============================================================================

-- 1. Registrar um pagamento novo (dispara a trigger 1 → INCLUSAO)
-- INSERT INTO pagamentos (parcela_id, registrado_por, valor, pago_em, forma, criado_em)
-- VALUES (2, 1, 500.00, NOW(), 'PIX', NOW());

-- 2. Corrigir a forma de pagamento (dispara a trigger 2 → ALTERACAO)
-- UPDATE pagamentos SET forma = 'DINHEIRO' WHERE id = LAST_INSERT_ID();

-- 3. Estornar o pagamento (dispara a trigger 2 → ESTORNO)
-- UPDATE pagamentos
--    SET estornado_em = NOW(), estornado_por = 1, motivo_estorno = 'Pagamento lançado em duplicidade'
--  WHERE id = LAST_INSERT_ID();

-- 4. Tentar apagar (a trigger 3 bloqueia com a mensagem de erro)
-- DELETE FROM pagamentos WHERE id = LAST_INSERT_ID();

-- 5. Ver o histórico gravado automaticamente
-- SELECT id, pagamento_id, operacao, valor_antigo, valor_novo, forma_antiga, forma_nova,
--        estornado_em_novo, motivo_estorno, usuario_sistema_id, usuario_banco, data_hora
--   FROM auditoria_pagamentos
--  ORDER BY id DESC;

-- Ver as triggers criadas:
-- SHOW TRIGGERS LIKE 'pagamentos';
