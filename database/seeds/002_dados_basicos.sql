-- =============================================================================
-- SGCB - Seed 002: dados básicos para os módulos de contrato e financeiro funcionarem.
--   - condições de pagamento padrão (o cadastro de cliente exige uma);
--   - empresa (locadora) com as preferências de contrato padrão (numeração CT-000001).
--
-- Rode depois do 001_usuario_admin.sql. Pode ser executado de novo sem duplicar nada.
-- A empresa é criada só com o nome: complete CNPJ, endereço e contato depois.
-- =============================================================================

USE sgcb;

SET NAMES utf8mb4;

-- Condições de pagamento (nome é UNIQUE: se já existir, não duplica).
INSERT INTO condicoes_pagamento (nome, dias_primeiro_vencimento, intervalo_dias, numero_parcelas_padrao, ativo)
VALUES ('À vista',       0,  0, 1, TRUE),
       ('30 dias',       30, 0, 1, TRUE),
       ('2x (30/60)',    30, 30, 2, TRUE),
       ('3x (30/60/90)', 30, 30, 3, TRUE)
ON DUPLICATE KEY UPDATE id = id;

-- Empresa: só cria se ainda não houver nenhuma.
INSERT INTO empresas (razao_social, nome_fantasia, atualizado_em)
SELECT 'Casa Barretto', 'Casa Barretto', NOW()
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM empresas);

-- Preferências de contrato da empresa (1:1), com os mesmos padrões do src/Models/Empresa.php.
INSERT INTO preferencias_contrato (
  empresa_id, numeracao_automatica, prefixo_contrato, proximo_numero_contrato, prefixo_locacao, proximo_numero_locacao,
  regra_vencimento, dias_alerta_vencimento, contagem_diarias, moeda, casas_decimais, formato_pdf,
  exibir_produtos, exibir_diarias, exibir_subtotais, exibir_total
)
SELECT e.id, TRUE, 'CT-', 1, 'LOC-', 1, 'DATA_INICIO', 7, 'CORRIDOS', 'BRL', 2, 'A4', TRUE, TRUE, TRUE, TRUE
  FROM empresas e
 WHERE NOT EXISTS (SELECT 1 FROM preferencias_contrato p WHERE p.empresa_id = e.id)
 ORDER BY e.id
 LIMIT 1;
