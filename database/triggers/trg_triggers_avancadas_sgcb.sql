-- =============================================================================
-- SGCB — Triggers Avançadas (Lista de Exercícios – Triggers Avançadas, questão 30)
-- Arquivo: database/triggers/trg_triggers_avancadas_sgcb.sql
--
-- Finalidade: 5 triggers avançadas com regras de negócio da locação de contêineres
--             da Casa Barretto, usando as tabelas do banco do projeto (sgcb).
--
--   ★ USADA NO PROJETO (seção A, separada das demais):
--     1. trg_unidades_historico_estado   AFTER UPDATE  unidades_fisicas → histórico de estado do contêiner
--   Demais triggers (seção B):
--     2. trg_precos_produto_historico    AFTER UPDATE  precos_produto   → histórico de preços (com variação %)
--     3. trg_pagamentos_valida_saldo     BEFORE INSERT pagamentos       → bloqueia pagamento acima do saldo
--     4. trg_contratos_regras_status     BEFORE UPDATE contratos        → status final, motivo e datas
--     5. trg_manutencoes_regras_status   BEFORE UPDATE manutencoes      → status final e datas automáticas
--
-- O que torna as triggers "avançadas": comparação de OLD e NEW, condições IF/ELSEIF,
--   variáveis (DECLARE / SELECT ... INTO), consulta a outras tabelas, bloqueio com
--   SIGNAL SQLSTATE '45000' com mensagem montada (CONCAT), ajuste de valores no
--   BEFORE (SET NEW.coluna) e gravação de histórico no AFTER, com CURRENT_USER() e NOW().
--
-- As regras 3, 4 e 5 já são conferidas pelo sistema em PHP (src/Models). Aqui elas
-- ficam também no banco: valem mesmo para comandos feitos fora do sistema
-- (phpMyAdmin, scripts), e o uso normal das telas não é afetado.
--
-- Como executar: no phpMyAdmin, selecione o banco "sgcb" e use a aba IMPORTAR
-- com este arquivo (ou o cliente mysql, como os outros scripts da pasta database).
-- Para criar de novo uma trigger que já existe, apague-a antes com
-- DROP TRIGGER nome_da_trigger;
-- =============================================================================

USE sgcb;


-- #############################################################################
-- A. ★ TRIGGER USADA NO PROJETO
--    Fica ativa no sistema: toda vez que o SGCB muda o estado de um contêiner
--    (emitir contrato → LOCADA, devolver → DISPONIVEL, iniciar manutenção →
--    MANUTENCAO, inativar → INATIVA) o histórico é gravado automaticamente.
-- #############################################################################

-- Tabela de histórico: uma linha por mudança de estado ou de local do contêiner.
CREATE TABLE IF NOT EXISTS historico_estado_unidades (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unidade_id         BIGINT UNSIGNED NOT NULL,   -- contêiner (unidades_fisicas.id)
  codigo_unidade     VARCHAR(50),                -- código pintado no contêiner
  produto_id         BIGINT UNSIGNED,            -- modelo (2,00 m, 2,50 m, 3,00 m...)
  estado_antigo      VARCHAR(20),                -- OLD.estado_operacional
  estado_novo        VARCHAR(20),                -- NEW.estado_operacional
  localidade_antiga  VARCHAR(200),               -- OLD.localidade
  localidade_nova    VARCHAR(200),               -- NEW.localidade
  usuario_banco      VARCHAR(100) NOT NULL,      -- CURRENT_USER()
  data_hora          DATETIME     NOT NULL,      -- NOW()
  INDEX idx_hist_estado_unidade (unidade_id, data_hora)
);

-- -----------------------------------------------------------------------------
-- Trigger 1 — AFTER UPDATE em unidades_fisicas
-- Objetivo: guardar a "vida" de cada contêiner. Só grava quando o estado ou a
-- localidade realmente mudaram (UPDATE que não muda nada não gera linha).
-- <=> é a comparação do MySQL que também funciona com NULL.
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE TRIGGER trg_unidades_historico_estado
AFTER UPDATE ON unidades_fisicas
FOR EACH ROW
BEGIN
  IF NOT (OLD.estado_operacional <=> NEW.estado_operacional)
     OR NOT (OLD.localidade <=> NEW.localidade) THEN
    INSERT INTO historico_estado_unidades
      (unidade_id, codigo_unidade, produto_id,
       estado_antigo, estado_novo, localidade_antiga, localidade_nova,
       usuario_banco, data_hora)
    VALUES
      (NEW.id, NEW.codigo, NEW.produto_id,
       OLD.estado_operacional, NEW.estado_operacional, OLD.localidade, NEW.localidade,
       CURRENT_USER(), NOW());
  END IF;
END$$
DELIMITER ;


-- #############################################################################
-- B. DEMAIS TRIGGERS AVANÇADAS
-- #############################################################################

-- Tabela de histórico de preços de locação (diária, semanal, quinzenal, mensal).
CREATE TABLE IF NOT EXISTS historico_precos_produto (
  id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  produto_id           BIGINT UNSIGNED NOT NULL,
  periodicidade        VARCHAR(20)  NOT NULL,    -- DIARIA, SEMANAL, QUINZENAL ou MENSAL
  valor_antigo         DECIMAL(14,2),            -- OLD.valor
  valor_novo           DECIMAL(14,2),            -- NEW.valor
  variacao_percentual  DECIMAL(9,2),             -- (novo - antigo) / antigo × 100
  usuario_banco        VARCHAR(100) NOT NULL,    -- CURRENT_USER()
  data_hora            DATETIME     NOT NULL,    -- NOW()
  INDEX idx_hist_preco_produto (produto_id, data_hora)
);

-- -----------------------------------------------------------------------------
-- Trigger 2 — AFTER UPDATE em precos_produto
-- Objetivo: registrar cada mudança de preço com o valor antigo, o novo e a
-- variação em %. O sistema grava preços com INSERT ... ON DUPLICATE KEY UPDATE;
-- quando o preço já existe, esse comando é um UPDATE e dispara esta trigger.
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE TRIGGER trg_precos_produto_historico
AFTER UPDATE ON precos_produto
FOR EACH ROW
BEGIN
  DECLARE v_variacao DECIMAL(9,2);

  IF NOT (OLD.valor <=> NEW.valor) THEN
    -- Sem valor antigo (ou zero) não dá para calcular percentual.
    IF OLD.valor IS NULL OR OLD.valor = 0 THEN
      SET v_variacao = NULL;
    ELSE
      SET v_variacao = ROUND((NEW.valor - OLD.valor) / OLD.valor * 100, 2);
    END IF;

    INSERT INTO historico_precos_produto
      (produto_id, periodicidade, valor_antigo, valor_novo, variacao_percentual, usuario_banco, data_hora)
    VALUES
      (NEW.produto_id, NEW.periodicidade, OLD.valor, NEW.valor, v_variacao, CURRENT_USER(), NOW());
  END IF;
END$$
DELIMITER ;


-- -----------------------------------------------------------------------------
-- Trigger 3 — BEFORE INSERT em pagamentos
-- Objetivo: impedir pagamento em parcela cancelada ou com valor maior que o saldo.
-- Saldo = valor original + acréscimo − desconto − pagamentos não estornados.
-- A mensagem de erro informa o saldo, montada com CONCAT.
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE TRIGGER trg_pagamentos_valida_saldo
BEFORE INSERT ON pagamentos
FOR EACH ROW
BEGIN
  DECLARE v_devido    DECIMAL(14,2);
  DECLARE v_pago      DECIMAL(14,2);
  DECLARE v_cancelada BOOLEAN;
  DECLARE v_mensagem  VARCHAR(255);

  -- Valor devido e situação da parcela que está sendo paga.
  SELECT valor_original + acrescimo - desconto, cancelada
    INTO v_devido, v_cancelada
    FROM parcelas
   WHERE id = NEW.parcela_id;

  -- Quanto já foi pago nela (estornos não contam).
  SELECT COALESCE(SUM(valor), 0)
    INTO v_pago
    FROM pagamentos
   WHERE parcela_id = NEW.parcela_id
     AND estornado_em IS NULL;

  IF v_devido IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Erro: parcela não encontrada.';
  ELSEIF v_cancelada THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Erro: não é permitido pagar uma parcela cancelada.';
  ELSEIF NEW.valor > v_devido - v_pago THEN
    SET v_mensagem = CONCAT('Erro: o valor ultrapassa o saldo da parcela (saldo: R$ ',
                            v_devido - v_pago, ').');
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = v_mensagem;
  END IF;
END$$
DELIMITER ;


-- -----------------------------------------------------------------------------
-- Trigger 4 — BEFORE UPDATE em contratos
-- Objetivo: proteger o ciclo de vida do contrato.
--   - FINALIZADO e CANCELADO são estados finais: o status não pode mais mudar;
--   - cancelar exige motivo;
--   - ao finalizar/cancelar, a data é preenchida automaticamente se vier vazia.
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE TRIGGER trg_contratos_regras_status
BEFORE UPDATE ON contratos
FOR EACH ROW
BEGIN
  IF OLD.status IN ('FINALIZADO', 'CANCELADO') AND NOT (NEW.status <=> OLD.status) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Erro: contrato finalizado ou cancelado não pode mudar de status.';
  END IF;

  IF NEW.status = 'CANCELADO' AND OLD.status <> 'CANCELADO' THEN
    IF NEW.motivo_cancelamento IS NULL OR TRIM(NEW.motivo_cancelamento) = '' THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erro: informe o motivo do cancelamento.';
    END IF;
    IF NEW.cancelado_em IS NULL THEN
      SET NEW.cancelado_em = NOW();
    END IF;
  ELSEIF NEW.status = 'FINALIZADO' AND OLD.status <> 'FINALIZADO' THEN
    IF NEW.finalizado_em IS NULL THEN
      SET NEW.finalizado_em = NOW();
    END IF;
  END IF;
END$$
DELIMITER ;


-- -----------------------------------------------------------------------------
-- Trigger 5 — BEFORE UPDATE em manutencoes
-- Objetivo: manter as datas da manutenção sempre coerentes com o status.
--   - CONCLUIDA e CANCELADA são estados finais (não reabrem);
--   - ao passar para EM_ANDAMENTO, preenche iniciada_em se vier vazia;
--   - ao passar para CONCLUIDA, preenche concluida_em (e iniciada_em, se faltar).
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE TRIGGER trg_manutencoes_regras_status
BEFORE UPDATE ON manutencoes
FOR EACH ROW
BEGIN
  IF OLD.status IN ('CONCLUIDA', 'CANCELADA') AND NOT (NEW.status <=> OLD.status) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Erro: manutenção concluída ou cancelada não pode ser reaberta.';
  END IF;

  IF NEW.status = 'EM_ANDAMENTO' AND NEW.iniciada_em IS NULL THEN
    SET NEW.iniciada_em = NOW();
  ELSEIF NEW.status = 'CONCLUIDA' THEN
    IF NEW.iniciada_em IS NULL THEN
      SET NEW.iniciada_em = NOW();
    END IF;
    IF NEW.concluida_em IS NULL THEN
      SET NEW.concluida_em = NOW();
    END IF;
  END IF;
END$$
DELIMITER ;


-- =============================================================================
-- Demonstração (rode depois de criar as triggers; usa os dados do seed 003)
-- Confira os ids no seu banco antes; os comentários mostram o resultado esperado.
-- =============================================================================

-- 1. Histórico de estado (★ usada no projeto)
-- UPDATE unidades_fisicas SET estado_operacional = 'MANUTENCAO' WHERE id = 1;
-- UPDATE unidades_fisicas SET estado_operacional = 'DISPONIVEL' WHERE id = 1;
-- SELECT * FROM historico_estado_unidades WHERE unidade_id = 1 ORDER BY id;
--   → 2 linhas: DISPONIVEL → MANUTENCAO e MANUTENCAO → DISPONIVEL

-- 2. Histórico de preço
-- UPDATE precos_produto SET valor = valor * 1.10 WHERE produto_id = 1 AND periodicidade = 'MENSAL';
-- SELECT * FROM historico_precos_produto ORDER BY id DESC;
--   → valor antigo, valor novo e variação de 10.00 %

-- 3. Pagamento acima do saldo
-- INSERT INTO pagamentos (parcela_id, registrado_por, valor, pago_em, forma, criado_em)
-- VALUES (2, 1, 999999.00, NOW(), 'PIX', NOW());
--   → ERROR 1644 (45000): Erro: o valor ultrapassa o saldo da parcela (saldo: R$ ...).

-- 4. Reabrir um contrato finalizado / cancelar sem motivo
-- UPDATE contratos SET status = 'ATIVO' WHERE status = 'FINALIZADO' LIMIT 1;
--   → ERROR 1644 (45000): Erro: contrato finalizado ou cancelado não pode mudar de status.
-- UPDATE contratos SET status = 'CANCELADO' WHERE status = 'ATIVO' LIMIT 1;
--   → ERROR 1644 (45000): Erro: informe o motivo do cancelamento.

-- 5. Datas automáticas e manutenção concluída
-- UPDATE manutencoes SET status = 'EM_ANDAMENTO' WHERE status = 'AGENDADA' LIMIT 1;
--   → iniciada_em preenchida com NOW() pela trigger
-- UPDATE manutencoes SET status = 'AGENDADA' WHERE status = 'CONCLUIDA' LIMIT 1;
--   → ERROR 1644 (45000): Erro: manutenção concluída ou cancelada não pode ser reaberta.

-- Ver as triggers criadas:
-- SHOW TRIGGERS;
