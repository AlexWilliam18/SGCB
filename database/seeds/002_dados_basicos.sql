-- =============================================================================
-- SGCB - Seed 002: dados básicos para os módulos de contrato e financeiro funcionarem.
--   - condições de pagamento padrão (o cadastro de cliente exige uma);
--   - empresa (locadora) com as preferências de contrato padrão (numeração CT-000001);
--   - matriz de permissões: módulos, ações e o que o cargo Gerente (código OPERADOR) pode fazer.
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


-- =============================================================================
-- Matriz de permissões (módulos, ações e o que o cargo Gerente (código OPERADOR) pode fazer).
-- O Administrador Master (cargo ADMIN) pode tudo e não depende desta lista (Usuario::temPermissao).
-- Os códigos são os mesmos usados nos controllers: Api::permitir('modulo', 'acao').
-- Exceções por usuário (conceder/negar) ficam em usuario_permissoes.
-- =============================================================================

-- Cargo Gerente, código OPERADOR (código UNIQUE: se já existir, não duplica).
INSERT INTO cargos (codigo, nome, nivel, descricao, ativo)
VALUES ('OPERADOR', 'Gerente', 10, 'Acesso a todas as telas, exceto Usuários', TRUE)
ON DUPLICATE KEY UPDATE id = id;

-- Módulos. exclusivo_adm = TRUE: só o Administrador Master acessa, mesmo com permissão no cargo.
INSERT INTO modulos (codigo, nome, exclusivo_adm)
VALUES ('clientes',      'Clientes e fornecedores', FALSE),
       ('produtos',      'Produtos e estoque',      FALSE),
       ('contratos',     'Contratos e locações',    FALSE),
       ('manutencoes',   'Manutenções',             FALSE),
       ('financeiro',    'Financeiro',              FALSE),
       ('usuarios',      'Usuários e acessos',      TRUE),
       ('configuracoes', 'Configurações',           TRUE)
ON DUPLICATE KEY UPDATE nome = VALUES(nome), exclusivo_adm = VALUES(exclusivo_adm);

-- Ações de cada módulo. A tabela não tem chave única (modulo + ação), então o
-- NOT EXISTS evita duplicar quando o script roda de novo.
INSERT INTO permissoes (modulo_id, acao, descricao)
SELECT m.id, a.acao, a.descricao
  FROM (
        SELECT 'clientes' AS modulo, 'visualizar' AS acao, 'Consultar clientes e fornecedores' AS descricao
        UNION ALL SELECT 'clientes',      'cadastrar',  'Cadastrar clientes e fornecedores'
        UNION ALL SELECT 'clientes',      'editar',     'Editar, ativar e inativar cadastros'
        UNION ALL SELECT 'produtos',      'visualizar', 'Consultar produtos e estoque'
        UNION ALL SELECT 'produtos',      'cadastrar',  'Cadastrar produtos e categorias'
        UNION ALL SELECT 'produtos',      'editar',     'Editar produtos, categorias e ajustar estoque'
        UNION ALL SELECT 'contratos',     'visualizar', 'Consultar contratos'
        UNION ALL SELECT 'contratos',     'cadastrar',  'Criar e gerar contratos'
        UNION ALL SELECT 'contratos',     'editar',     'Editar, iniciar e finalizar locações'
        UNION ALL SELECT 'contratos',     'cancelar',   'Cancelar contratos'
        UNION ALL SELECT 'manutencoes',   'visualizar', 'Consultar manutenções'
        UNION ALL SELECT 'manutencoes',   'cadastrar',  'Agendar manutenções'
        UNION ALL SELECT 'manutencoes',   'editar',     'Iniciar, concluir e cancelar manutenções'
        UNION ALL SELECT 'financeiro',    'visualizar', 'Consultar parcelas, recibos e valores'
        UNION ALL SELECT 'financeiro',    'cadastrar',  'Registrar pagamentos'
        UNION ALL SELECT 'financeiro',    'editar',     'Ajustar e cancelar parcelas'
        UNION ALL SELECT 'financeiro',    'estornar',   'Estornar e corrigir pagamentos'
        UNION ALL SELECT 'usuarios',      'visualizar', 'Consultar usuários'
        UNION ALL SELECT 'usuarios',      'cadastrar',  'Cadastrar usuários'
        UNION ALL SELECT 'usuarios',      'editar',     'Editar usuários e permissões'
        UNION ALL SELECT 'configuracoes', 'visualizar', 'Consultar dados da empresa'
        UNION ALL SELECT 'configuracoes', 'editar',     'Alterar dados da empresa e preferências'
       ) a
  JOIN modulos m ON m.codigo = a.modulo
 WHERE NOT EXISTS (SELECT 1 FROM permissoes p WHERE p.modulo_id = m.id AND p.acao = a.acao);

-- O que o Gerente (código OPERADOR) pode fazer: todas as ações dos módulos que não são
-- exclusivos do Administrador Master (clientes, produtos, contratos, manutenções e financeiro).
-- Usuários e Configurações ficam só com o Master (exclusivo_adm = TRUE).
INSERT INTO cargo_permissoes (cargo_id, permissao_id)
SELECT c.id, p.id
  FROM cargos c
  JOIN permissoes p
  JOIN modulos m ON m.id = p.modulo_id
 WHERE c.codigo = 'OPERADOR'
   AND m.exclusivo_adm = FALSE
ON DUPLICATE KEY UPDATE cargo_id = cargo_id;
