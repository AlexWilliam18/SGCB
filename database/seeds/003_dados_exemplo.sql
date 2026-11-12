-- =============================================================================
-- SGCB - Seed 003: dados de EXEMPLO para ver as telas funcionando
-- (dashboard, clientes, produtos, manutenções e financeiro com informações).
--
-- Insere: 2 usuários operadores, 4 categorias, 2 fornecedores, 8 clientes, 5 produtos
-- (19 contêineres), 12 contratos (ativos, reservado, finalizados, cancelado e rascunho),
-- parcelas e pagamentos em várias formas e 5 manutenções.
--
-- As datas são relativas ao dia em que o script roda (CURDATE()): sempre haverá contratos
-- em andamento, parcelas vencendo e meses anteriores com faturamento no gráfico.
-- Os valores seguem as mesmas regras do sistema (src/Models/Contrato.php):
--   item = preço da periodicidade × quantidade × períodos; períodos = dias ÷ dias da periodicidade.
--
-- Pré-requisitos: migração 001 e seed 001 (usuário admin). O conteúdo do seed 002
-- (condições de pagamento e empresa) é aplicado aqui se ainda não existir.
--
-- Rode UMA vez, em um banco sem os dados de exemplo (se rodar de novo, para no primeiro
-- registro repetido e nada é gravado). Para recomeçar, apague o banco sgcb e rode tudo de novo.
-- Usuários de exemplo: carla.mendes@casabarretto.exemplo.com.br e
-- rafael.souza@casabarretto.exemplo.com.br, senha Exemplo123.
--
-- Dados fictícios: não use em produção.
-- =============================================================================

USE sgcb;

SET NAMES utf8mb4;

-- Tudo ou nada: se algum comando falhar, nada fica gravado pela metade.
START TRANSACTION;

SET @hoje  = CURDATE();
SET @admin = (SELECT id FROM usuarios WHERE email = 'admin@casabarretto.local');


-- =============================================================================
-- 1. CATEGORIAS (primeiro bloco: se os exemplos já existirem, o script para aqui)
-- =============================================================================

INSERT INTO categorias (nome, ativo)
VALUES ('Container Escritório', TRUE), ('Container Almoxarifado', TRUE), ('Container Sanitário', TRUE), ('Container Refrigerado', TRUE);

-- Ids buscados pelo nome (funciona no phpMyAdmin e no terminal).
SET @cat_esc = (SELECT id FROM categorias WHERE nome = 'Container Escritório');
SET @cat_alm = (SELECT id FROM categorias WHERE nome = 'Container Almoxarifado');
SET @cat_san = (SELECT id FROM categorias WHERE nome = 'Container Sanitário');
SET @cat_ref = (SELECT id FROM categorias WHERE nome = 'Container Refrigerado');


-- =============================================================================
-- 2. EMPRESA E CONDIÇÕES DE PAGAMENTO (mesmo conteúdo do seed 002, sem duplicar)
-- =============================================================================

INSERT INTO condicoes_pagamento (nome, dias_primeiro_vencimento, intervalo_dias, numero_parcelas_padrao, ativo)
VALUES ('À vista', 0, 0, 1, TRUE), ('30 dias', 30, 0, 1, TRUE), ('2x (30/60)', 30, 30, 2, TRUE), ('3x (30/60/90)', 30, 30, 3, TRUE)
ON DUPLICATE KEY UPDATE id = id;

SET @vista = (SELECT id FROM condicoes_pagamento WHERE nome = 'À vista');
SET @d30   = (SELECT id FROM condicoes_pagamento WHERE nome = '30 dias');
SET @x2    = (SELECT id FROM condicoes_pagamento WHERE nome = '2x (30/60)');
SET @x3    = (SELECT id FROM condicoes_pagamento WHERE nome = '3x (30/60/90)');

INSERT INTO empresas (razao_social, nome_fantasia, atualizado_em)
SELECT 'Casa Barretto', 'Casa Barretto', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM empresas);

SET @emp = (SELECT MIN(id) FROM empresas);

-- Completa os dados da empresa (mantém o que já estiver preenchido).
UPDATE empresas
   SET cnpj = COALESCE(cnpj, '45123098000102'), email = COALESCE(email, 'contato@casabarretto.exemplo.com.br'),
       telefone = COALESCE(telefone, '1239510000'), cep = COALESCE(cep, '12300000'),
       rua = COALESCE(rua, 'Rodovia Presidente Dutra'), numero = COALESCE(numero, 'km 160'),
       bairro = COALESCE(bairro, 'Distrito Industrial'), cidade = COALESCE(cidade, 'Jacareí'), uf = COALESCE(uf, 'SP'),
       atualizado_por = @admin, atualizado_em = NOW()
 WHERE id = @emp;

INSERT INTO preferencias_contrato (
  empresa_id, numeracao_automatica, prefixo_contrato, proximo_numero_contrato, prefixo_locacao, proximo_numero_locacao,
  regra_vencimento, dias_alerta_vencimento, contagem_diarias, moeda, casas_decimais, formato_pdf,
  exibir_produtos, exibir_diarias, exibir_subtotais, exibir_total
)
SELECT @emp, TRUE, 'CT-', 1, 'LOC-', 1, 'DATA_INICIO', 7, 'CORRIDOS', 'BRL', 2, 'A4', TRUE, TRUE, TRUE, TRUE
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM preferencias_contrato WHERE empresa_id = @emp);

-- Cópia dos dados da empresa guardada em cada contrato.
SET @snap_empresa = (SELECT JSON_OBJECT('id', id, 'razao_social', razao_social, 'nome_fantasia', nome_fantasia, 'cnpj', cnpj,
                                        'cidade', cidade, 'uf', uf) FROM empresas WHERE id = @emp);


-- =============================================================================
-- 3. USUÁRIOS OPERADORES (senha Exemplo123, já em hash bcrypt)
-- =============================================================================

INSERT INTO cargos (codigo, nome, nivel, descricao, ativo)
VALUES ('OPERADOR', 'Operador', 10, 'Cadastros e acompanhamento das locações', TRUE)
ON DUPLICATE KEY UPDATE id = id;

SET @cargo_op = (SELECT id FROM cargos WHERE codigo = 'OPERADOR');

INSERT INTO usuarios (cargo_id, nome, email, telefone, senha_hash, ativo, deve_alterar_senha, criado_em, atualizado_em)
VALUES (@cargo_op, 'Carla Mendes', 'carla.mendes@casabarretto.exemplo.com.br', '12988887777',
        '$2y$12$bAuHqCF7RzpSbfeVlG3DRufD7j67myt4dXSissArgjxjBJeMCcALO', TRUE, FALSE, NOW(), NOW());
SET @carla = LAST_INSERT_ID();

INSERT INTO usuarios (cargo_id, nome, email, telefone, senha_hash, ativo, deve_alterar_senha, criado_em, atualizado_em)
VALUES (@cargo_op, 'Rafael Souza', 'rafael.souza@casabarretto.exemplo.com.br', '12988886666',
        '$2y$12$bAuHqCF7RzpSbfeVlG3DRufD7j67myt4dXSissArgjxjBJeMCcALO', TRUE, FALSE, NOW(), NOW());
SET @rafael = LAST_INSERT_ID();


-- =============================================================================
-- 4. FORNECEDORES E CLIENTES (CPF/CNPJ válidos, só números) + endereço cadastral (um por pessoa)
-- =============================================================================

-- Fornecedor exclusivo (não aparece na listagem de clientes).
INSERT INTO pessoas (condicao_pagamento_id, criado_por, tipo_cadastro, tipo_pessoa, nome_razao_social, nome_fantasia, cpf_cnpj,
                     email, telefone, nacionalidade, limite_credito, permite_ultrapassar_limite, ativo, criado_em, atualizado_em)
VALUES (@d30, @admin, 'FORNECEDOR', 'JURIDICA', 'Metalúrgica Contêiner Brasil LTDA', 'MCB Contêineres', '11222333000181',
        'vendas@mcb.exemplo.com.br', '1133334444', 'Brasileira', 0, FALSE, TRUE, @hoje - INTERVAL 300 DAY, @hoje - INTERVAL 300 DAY);
SET @forn_mcb = LAST_INSERT_ID();
INSERT INTO enderecos_pessoa (pessoa_id, cep, rua, numero, bairro, cidade, uf)
VALUES (@forn_mcb, '07034000', 'Av. das Indústrias', '1500', 'Distrito Industrial', 'Guarulhos', 'SP');

-- Fornecedor que também é cliente (AMBOS).
INSERT INTO pessoas (condicao_pagamento_id, criado_por, tipo_cadastro, tipo_pessoa, nome_razao_social, nome_fantasia, cpf_cnpj,
                     email, telefone, nacionalidade, limite_credito, permite_ultrapassar_limite, ativo, criado_em, atualizado_em)
VALUES (@d30, @admin, 'AMBOS', 'JURIDICA', 'Frio Vale Equipamentos LTDA', 'Frio Vale', '22333444000181',
        'comercial@friovale.exemplo.com.br', '1233221100', 'Brasileira', 0, FALSE, TRUE, @hoje - INTERVAL 290 DAY, @hoje - INTERVAL 290 DAY);
SET @forn_frio = LAST_INSERT_ID();
INSERT INTO enderecos_pessoa (pessoa_id, cep, rua, numero, bairro, cidade, uf)
VALUES (@forn_frio, '12020000', 'Rua do Comércio', '320', 'Centro', 'Taubaté', 'SP');

-- Clientes pessoa jurídica
INSERT INTO pessoas (condicao_pagamento_id, criado_por, tipo_cadastro, tipo_pessoa, nome_razao_social, nome_fantasia, cpf_cnpj,
                     email, telefone, nacionalidade, representante_legal, limite_credito, permite_ultrapassar_limite, ativo, criado_em, atualizado_em)
VALUES (@x3, @admin, 'CLIENTE', 'JURIDICA', 'Construtora Vale do Paraíba LTDA', 'Construtora Vale', '33444555000181',
        'contato@construtoravale.exemplo.com.br', '1239001122', 'Brasileira', 'Marcos Antunes', 80000, FALSE, TRUE,
        @hoje - INTERVAL 280 DAY, @hoje - INTERVAL 280 DAY);
SET @cli_vale = LAST_INSERT_ID();
INSERT INTO enderecos_pessoa (pessoa_id, cep, rua, numero, bairro, cidade, uf)
VALUES (@cli_vale, '12243000', 'Av. Nove de Julho', '850', 'Jardim Apolo', 'São José dos Campos', 'SP');
-- A entrega na obra (Residencial Aquarius) fica no próprio contrato 6 (contrato_enderecos_entrega).

INSERT INTO pessoas (condicao_pagamento_id, criado_por, tipo_cadastro, tipo_pessoa, nome_razao_social, nome_fantasia, cpf_cnpj,
                     email, telefone, nacionalidade, limite_credito, permite_ultrapassar_limite, ativo, criado_em, atualizado_em)
VALUES (@vista, @admin, 'CLIENTE', 'JURIDICA', 'Litoral Eventos e Feiras LTDA', 'Litoral Eventos', '44555666000181',
        'eventos@litoraleventos.exemplo.com.br', '1238887766', 'Brasileira', 0, FALSE, TRUE, @hoje - INTERVAL 260 DAY, @hoje - INTERVAL 260 DAY);
SET @cli_eventos = LAST_INSERT_ID();
INSERT INTO enderecos_pessoa (pessoa_id, cep, rua, numero, bairro, cidade, uf)
VALUES (@cli_eventos, '11660000', 'Av. da Praia', '1200', 'Centro', 'Caraguatatuba', 'SP');

INSERT INTO pessoas (condicao_pagamento_id, criado_por, tipo_cadastro, tipo_pessoa, nome_razao_social, nome_fantasia, cpf_cnpj,
                     email, telefone, nacionalidade, limite_credito, permite_ultrapassar_limite, ativo, criado_em, atualizado_em)
VALUES (@x2, @admin, 'CLIENTE', 'JURIDICA', 'Mineração Serra Azul S.A.', 'Serra Azul', '55666777000181',
        'suprimentos@serraazul.exemplo.com.br', '1236541200', 'Brasileira', 0, FALSE, TRUE, @hoje - INTERVAL 250 DAY, @hoje - INTERVAL 250 DAY);
SET @cli_mineracao = LAST_INSERT_ID();
INSERT INTO enderecos_pessoa (pessoa_id, cep, rua, numero, bairro, cidade, uf)
VALUES (@cli_mineracao, '12300500', 'Estrada da Pedreira', '5000', 'Zona Rural', 'Jacareí', 'SP');

INSERT INTO pessoas (condicao_pagamento_id, criado_por, tipo_cadastro, tipo_pessoa, nome_razao_social, cpf_cnpj,
                     email, telefone, nacionalidade, limite_credito, permite_ultrapassar_limite, ativo, criado_em, atualizado_em)
VALUES (@d30, @admin, 'CLIENTE', 'JURIDICA', 'Agropecuária Bom Retiro LTDA', '66777888000181',
        'financeiro@bomretiro.exemplo.com.br', '1236320011', 'Brasileira', 0, FALSE, TRUE, @hoje - INTERVAL 120 DAY, @hoje - INTERVAL 120 DAY);
SET @cli_agro = LAST_INSERT_ID();
INSERT INTO enderecos_pessoa (pessoa_id, cep, rua, numero, bairro, cidade, uf)
VALUES (@cli_agro, '12280000', 'Rodovia SP-66', 'km 12', 'Bom Retiro', 'Caçapava', 'SP');

-- Cliente inativo (para testar o filtro "Inativos").
INSERT INTO pessoas (condicao_pagamento_id, criado_por, tipo_cadastro, tipo_pessoa, nome_razao_social, cpf_cnpj,
                     email, telefone, nacionalidade, limite_credito, permite_ultrapassar_limite, ativo, criado_em, atualizado_em)
VALUES (@d30, @admin, 'CLIENTE', 'JURIDICA', 'Comércio Antigo ME', '77888999000181',
        'contato@comercioantigo.exemplo.com.br', '1232105544', 'Brasileira', 0, FALSE, FALSE, @hoje - INTERVAL 400 DAY, @hoje - INTERVAL 30 DAY);
SET @cli_inativo = LAST_INSERT_ID();
INSERT INTO enderecos_pessoa (pessoa_id, cep, rua, numero, bairro, cidade, uf)
VALUES (@cli_inativo, '12400000', 'Rua Velha', '10', 'Centro', 'Pindamonhangaba', 'SP');

-- Clientes pessoa física
INSERT INTO pessoas (condicao_pagamento_id, criado_por, tipo_cadastro, tipo_pessoa, nome_razao_social, cpf_cnpj, email, telefone,
                     data_nascimento, nacionalidade, profissao, limite_credito, permite_ultrapassar_limite, ativo, criado_em, atualizado_em)
VALUES (@d30, @admin, 'CLIENTE', 'FISICA', 'João Pedro Almeida', '12345678062', 'joao.almeida@exemplo.com.br', '12991234567',
        '1985-04-12', 'Brasileira', 'Engenheiro civil', 0, FALSE, TRUE, @hoje - INTERVAL 60 DAY, @hoje - INTERVAL 60 DAY);
SET @cli_joao = LAST_INSERT_ID();
INSERT INTO enderecos_pessoa (pessoa_id, cep, rua, numero, bairro, cidade, uf)
VALUES (@cli_joao, '12243100', 'Rua das Acácias', '45', 'Vila Ema', 'São José dos Campos', 'SP');

INSERT INTO pessoas (condicao_pagamento_id, criado_por, tipo_cadastro, tipo_pessoa, nome_razao_social, cpf_cnpj, email, telefone,
                     data_nascimento, nacionalidade, profissao, limite_credito, permite_ultrapassar_limite, ativo, criado_em, atualizado_em)
VALUES (@x2, @admin, 'CLIENTE', 'FISICA', 'Mariana Costa Ribeiro', '23456789173', 'mariana.ribeiro@exemplo.com.br', '12997654321',
        '1990-09-30', 'Brasileira', 'Arquiteta', 0, FALSE, TRUE, @hoje - INTERVAL 10 DAY, @hoje - INTERVAL 10 DAY);
SET @cli_mariana = LAST_INSERT_ID();
INSERT INTO enderecos_pessoa (pessoa_id, cep, rua, numero, bairro, cidade, uf)
VALUES (@cli_mariana, '12020010', 'Rua Sete de Setembro', '210', 'Centro', 'Taubaté', 'SP');

INSERT INTO pessoas (condicao_pagamento_id, criado_por, tipo_cadastro, tipo_pessoa, nome_razao_social, cpf_cnpj, email, telefone,
                     nacionalidade, profissao, limite_credito, permite_ultrapassar_limite, ativo, criado_em, atualizado_em)
VALUES (@d30, @admin, 'CLIENTE', 'FISICA', 'Carlos Eduardo Lima', '34567891228', 'carlos.lima@exemplo.com.br', '12996543210',
        'Brasileira', 'Mestre de obras', 0, FALSE, TRUE, @hoje - INTERVAL 220 DAY, @hoje - INTERVAL 220 DAY);
SET @cli_carlos = LAST_INSERT_ID();
INSERT INTO enderecos_pessoa (pessoa_id, cep, rua, numero, bairro, cidade, uf)
VALUES (@cli_carlos, '12327000', 'Av. Brasil', '1800', 'Jardim Paraíba', 'Jacareí', 'SP');


-- =============================================================================
-- 5. PRODUTOS, PREÇOS E CONTÊINERES (unidades físicas)
-- O estado de cada contêiner já é o final, depois dos contratos e manutenções abaixo.
-- =============================================================================

INSERT INTO produtos (categoria_id, fornecedor_id, criado_por, codigo, nome, descricao, valor_total_bem, limite_inicial, limite_maximo, maximo_estoque, ativo, criado_em)
VALUES (@cat_esc, @forn_mcb, @admin, 'C20-ESC', 'Contêiner Escritório 20 pés',
        'Contêiner Escritório 20 pés com instalação elétrica, iluminação e porta com fechadura.', 38000, 1, 10, 16, TRUE, @hoje - INTERVAL 300 DAY);
SET @esc20 = LAST_INSERT_ID();
INSERT INTO precos_produto (produto_id, periodicidade, valor) VALUES (@esc20, 'DIARIA', 120), (@esc20, 'SEMANAL', 700), (@esc20, 'QUINZENAL', 1300), (@esc20, 'MENSAL', 2400);

INSERT INTO produtos (categoria_id, fornecedor_id, criado_por, codigo, nome, descricao, valor_total_bem, limite_inicial, limite_maximo, maximo_estoque, ativo, criado_em)
VALUES (@cat_esc, @forn_mcb, @admin, 'C10-ESC', 'Contêiner Escritório 10 pés',
        'Contêiner Escritório 10 pés com instalação elétrica, iluminação e porta com fechadura.', 24000, 1, 8, 14, TRUE, @hoje - INTERVAL 300 DAY);
SET @esc10 = LAST_INSERT_ID();
INSERT INTO precos_produto (produto_id, periodicidade, valor) VALUES (@esc10, 'DIARIA', 80), (@esc10, 'SEMANAL', 450), (@esc10, 'QUINZENAL', 850), (@esc10, 'MENSAL', 1600);

INSERT INTO produtos (categoria_id, fornecedor_id, criado_por, codigo, nome, descricao, valor_total_bem, limite_inicial, limite_maximo, maximo_estoque, ativo, criado_em)
VALUES (@cat_alm, @forn_mcb, @admin, 'C40-ALM', 'Contêiner Almoxarifado 40 pés',
        'Contêiner Almoxarifado 40 pés com instalação elétrica, iluminação e porta com fechadura.', 52000, 1, 8, 14, TRUE, @hoje - INTERVAL 300 DAY);
SET @alm40 = LAST_INSERT_ID();
INSERT INTO precos_produto (produto_id, periodicidade, valor) VALUES (@alm40, 'DIARIA', 150), (@alm40, 'SEMANAL', 900), (@alm40, 'QUINZENAL', 1700), (@alm40, 'MENSAL', 3200);

INSERT INTO produtos (categoria_id, fornecedor_id, criado_por, codigo, nome, descricao, valor_total_bem, limite_inicial, limite_maximo, maximo_estoque, ativo, criado_em)
VALUES (@cat_san, @forn_mcb, @admin, 'C20-SAN', 'Contêiner Sanitário 20 pés',
        'Contêiner Sanitário 20 pés com instalação elétrica, iluminação e porta com fechadura.', 30000, 1, 7, 13, TRUE, @hoje - INTERVAL 300 DAY);
SET @san20 = LAST_INSERT_ID();
INSERT INTO precos_produto (produto_id, periodicidade, valor) VALUES (@san20, 'DIARIA', 90), (@san20, 'SEMANAL', 550), (@san20, 'QUINZENAL', 1000), (@san20, 'MENSAL', 1900);

INSERT INTO produtos (categoria_id, fornecedor_id, criado_por, codigo, nome, descricao, valor_total_bem, limite_inicial, limite_maximo, maximo_estoque, ativo, criado_em)
VALUES (@cat_ref, @forn_frio, @admin, 'C40-REF', 'Contêiner Refrigerado 40 pés',
        'Contêiner Refrigerado 40 pés com instalação elétrica, iluminação e porta com fechadura.', 120000, 1, 6, 12, TRUE, @hoje - INTERVAL 300 DAY);
SET @ref40 = LAST_INSERT_ID();
INSERT INTO precos_produto (produto_id, periodicidade, valor) VALUES (@ref40, 'DIARIA', 300), (@ref40, 'SEMANAL', 1900), (@ref40, 'QUINZENAL', 3600), (@ref40, 'MENSAL', 6800);

-- 19 contêineres. LOCADA = em contrato em andamento; MANUTENCAO = manutenção em andamento.
INSERT INTO unidades_fisicas (produto_id, codigo, estado_operacional, localidade, ultima_vistoria_em, criado_em) VALUES
  (@esc20, 'C20-ESC-001', 'LOCADA',     'Obra Residencial Aquarius - São José dos Campos', NULL, @hoje - INTERVAL 300 DAY),
  (@esc20, 'C20-ESC-002', 'LOCADA',     'Obra Residencial Aquarius - São José dos Campos', NULL, @hoje - INTERVAL 300 DAY),
  (@esc20, 'C20-ESC-003', 'LOCADA',     'Taubaté',                                         NULL, @hoje - INTERVAL 300 DAY),
  (@esc20, 'C20-ESC-004', 'MANUTENCAO', 'Pátio Jacareí',                                   NULL, @hoje - INTERVAL 300 DAY),
  (@esc20, 'C20-ESC-005', 'DISPONIVEL', 'Pátio Jacareí',                                   NULL, @hoje - INTERVAL 300 DAY),
  (@esc20, 'C20-ESC-006', 'DISPONIVEL', 'Pátio Jacareí',                                   NULL, @hoje - INTERVAL 300 DAY),
  (@esc10, 'C10-ESC-001', 'LOCADA',     'São José dos Campos',                             NULL, @hoje - INTERVAL 300 DAY),
  (@esc10, 'C10-ESC-002', 'DISPONIVEL', 'Pátio Jacareí',                                   NOW(), @hoje - INTERVAL 300 DAY),
  (@esc10, 'C10-ESC-003', 'DISPONIVEL', 'Pátio Jacareí',                                   NULL, @hoje - INTERVAL 300 DAY),
  (@esc10, 'C10-ESC-004', 'DISPONIVEL', 'Pátio Jacareí',                                   NULL, @hoje - INTERVAL 300 DAY),
  (@alm40, 'C40-ALM-001', 'LOCADA',     'Pedreira Serra Azul - Jacareí',                   NULL, @hoje - INTERVAL 300 DAY),
  (@alm40, 'C40-ALM-002', 'LOCADA',     'Pedreira Serra Azul - Jacareí',                   NULL, @hoje - INTERVAL 300 DAY),
  (@alm40, 'C40-ALM-003', 'DISPONIVEL', 'Pátio São José dos Campos',                       NULL, @hoje - INTERVAL 300 DAY),
  (@alm40, 'C40-ALM-004', 'DISPONIVEL', 'Pátio São José dos Campos',                       NULL, @hoje - INTERVAL 300 DAY),
  (@san20, 'C20-SAN-001', 'LOCADA',     'Obra Residencial Aquarius - São José dos Campos', NULL, @hoje - INTERVAL 300 DAY),
  (@san20, 'C20-SAN-002', 'LOCADA',     'Taubaté',                                         NULL, @hoje - INTERVAL 300 DAY),
  (@san20, 'C20-SAN-003', 'DISPONIVEL', 'Pátio São José dos Campos',                       NULL, @hoje - INTERVAL 300 DAY),
  (@ref40, 'C40-REF-001', 'DISPONIVEL', 'Pátio Jacareí',                                   NOW(), @hoje - INTERVAL 300 DAY),
  (@ref40, 'C40-REF-002', 'DISPONIVEL', 'Pátio Jacareí',                                   NULL, @hoje - INTERVAL 300 DAY);


-- =============================================================================
-- 6. CONTRATOS, ITENS, AGENDA DOS CONTÊINERES, PARCELAS E PAGAMENTOS
-- Na agenda, "fim" é o dia seguinte à data final (o período é [início, fim)).
-- Parcela: competência = 1º dia do mês do vencimento.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- Contrato 1 — FINALIZADO (8 meses atrás). Mineração: 2 almoxarifados por 1 mês.
-- 3.200 × 2 × 1 mês = 6.400,00 à vista, pago por boleto.
-- ---------------------------------------------------------------------------
INSERT INTO contratos (empresa_id, cliente_id, condicao_pagamento_id, criado_por, numero_locacao, numero_contrato, data_emissao, prazo,
                       data_inicio, data_fim, periodicidade, status, numero_parcelas, frete_deslocamento, outros_valores, caucao,
                       dados_cliente_snapshot, dados_empresa_snapshot, criado_em, finalizado_em)
VALUES (@emp, @cli_mineracao, @vista, @admin, 'LOC-000001', 'CT-000001', @hoje - INTERVAL 240 DAY, 'DETERMINADO',
        @hoje - INTERVAL 240 DAY, @hoje - INTERVAL 210 DAY, 'MENSAL', 'FINALIZADO', 1, 0, 0, 0,
        (SELECT JSON_OBJECT('id', id, 'nome', nome_razao_social, 'cpf_cnpj', cpf_cnpj, 'email', email) FROM pessoas WHERE id = @cli_mineracao),
        @snap_empresa, TIMESTAMP(@hoje - INTERVAL 240 DAY, '09:00:00'), TIMESTAMP(@hoje - INTERVAL 210 DAY, '17:00:00'));
SET @c = LAST_INSERT_ID();
INSERT INTO contrato_itens (contrato_id, produto_id, descricao_snapshot, valor_unitario_bem_snapshot, quantidade, periodicidade, numero_periodos, valor_unitario, desconto, outros, subtotal)
VALUES (@c, @alm40, 'C40-ALM - Contêiner Almoxarifado 40 pés', 52000, 2, 'MENSAL', 1, 3200, 0, 0, 6400);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status, devolvida_em) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C40-ALM-001'), @alm40, @i, @admin, TIMESTAMP(@hoje - INTERVAL 240 DAY), TIMESTAMP(@hoje - INTERVAL 209 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 210 DAY, '17:00:00')),
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C40-ALM-002'), @alm40, @i, @admin, TIMESTAMP(@hoje - INTERVAL 240 DAY), TIMESTAMP(@hoje - INTERVAL 209 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 210 DAY, '17:00:00'));
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 1, DATE_FORMAT(@hoje - INTERVAL 240 DAY, '%Y-%m-01'), @hoje - INTERVAL 240 DAY, 6400, 0, 0, FALSE);
INSERT INTO pagamentos (parcela_id, registrado_por, valor, pago_em, forma, criado_em)
VALUES (LAST_INSERT_ID(), @admin, 6400, TIMESTAMP(@hoje - INTERVAL 238 DAY, '10:30:00'), 'BOLETO', TIMESTAMP(@hoje - INTERVAL 238 DAY, '10:30:00'));

-- ---------------------------------------------------------------------------
-- Contrato 2 — FINALIZADO. Carlos: 3 escritórios 10 pés por 15 diárias + frete.
-- 80 × 3 × 15 = 3.600,00 + 200,00 de frete = 3.800,00 à vista, cartão de débito.
-- ---------------------------------------------------------------------------
INSERT INTO contratos (empresa_id, cliente_id, condicao_pagamento_id, criado_por, numero_locacao, numero_contrato, data_emissao, prazo,
                       data_inicio, data_fim, periodicidade, status, numero_parcelas, frete_deslocamento, outros_valores, caucao,
                       dados_cliente_snapshot, dados_empresa_snapshot, criado_em, finalizado_em)
VALUES (@emp, @cli_carlos, @vista, @admin, 'LOC-000002', 'CT-000002', @hoje - INTERVAL 200 DAY, 'DETERMINADO',
        @hoje - INTERVAL 200 DAY, @hoje - INTERVAL 185 DAY, 'DIARIA', 'FINALIZADO', 1, 200, 0, 0,
        (SELECT JSON_OBJECT('id', id, 'nome', nome_razao_social, 'cpf_cnpj', cpf_cnpj, 'email', email) FROM pessoas WHERE id = @cli_carlos),
        @snap_empresa, TIMESTAMP(@hoje - INTERVAL 200 DAY, '09:00:00'), TIMESTAMP(@hoje - INTERVAL 185 DAY, '17:00:00'));
SET @c = LAST_INSERT_ID();
INSERT INTO contrato_itens (contrato_id, produto_id, descricao_snapshot, valor_unitario_bem_snapshot, quantidade, periodicidade, numero_periodos, valor_unitario, desconto, outros, subtotal)
VALUES (@c, @esc10, 'C10-ESC - Contêiner Escritório 10 pés', 24000, 3, 'DIARIA', 15, 80, 0, 0, 3600);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status, devolvida_em) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C10-ESC-001'), @esc10, @i, @admin, TIMESTAMP(@hoje - INTERVAL 200 DAY), TIMESTAMP(@hoje - INTERVAL 184 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 185 DAY, '17:00:00')),
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C10-ESC-002'), @esc10, @i, @admin, TIMESTAMP(@hoje - INTERVAL 200 DAY), TIMESTAMP(@hoje - INTERVAL 184 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 185 DAY, '17:00:00')),
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C10-ESC-003'), @esc10, @i, @admin, TIMESTAMP(@hoje - INTERVAL 200 DAY), TIMESTAMP(@hoje - INTERVAL 184 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 185 DAY, '17:00:00'));
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 1, DATE_FORMAT(@hoje - INTERVAL 200 DAY, '%Y-%m-01'), @hoje - INTERVAL 200 DAY, 3800, 0, 0, FALSE);
INSERT INTO pagamentos (parcela_id, registrado_por, valor, pago_em, forma, criado_em)
VALUES (LAST_INSERT_ID(), @admin, 3800, TIMESTAMP(@hoje - INTERVAL 199 DAY, '10:30:00'), 'CARTAO_DEBITO', TIMESTAMP(@hoje - INTERVAL 199 DAY, '10:30:00'));

-- ---------------------------------------------------------------------------
-- Contrato 3 — FINALIZADO. Construtora Vale: 2 escritórios 20 pés por 1 mês.
-- 2.400 × 2 × 1 = 4.800,00 à vista, Pix.
-- ---------------------------------------------------------------------------
INSERT INTO contratos (empresa_id, cliente_id, condicao_pagamento_id, criado_por, numero_locacao, numero_contrato, data_emissao, prazo,
                       data_inicio, data_fim, periodicidade, status, numero_parcelas, frete_deslocamento, outros_valores, caucao,
                       dados_cliente_snapshot, dados_empresa_snapshot, criado_em, finalizado_em)
VALUES (@emp, @cli_vale, @vista, @admin, 'LOC-000003', 'CT-000003', @hoje - INTERVAL 150 DAY, 'DETERMINADO',
        @hoje - INTERVAL 150 DAY, @hoje - INTERVAL 120 DAY, 'MENSAL', 'FINALIZADO', 1, 0, 0, 0,
        (SELECT JSON_OBJECT('id', id, 'nome', nome_razao_social, 'cpf_cnpj', cpf_cnpj, 'email', email) FROM pessoas WHERE id = @cli_vale),
        @snap_empresa, TIMESTAMP(@hoje - INTERVAL 150 DAY, '09:00:00'), TIMESTAMP(@hoje - INTERVAL 120 DAY, '17:00:00'));
SET @c = LAST_INSERT_ID();
INSERT INTO contrato_itens (contrato_id, produto_id, descricao_snapshot, valor_unitario_bem_snapshot, quantidade, periodicidade, numero_periodos, valor_unitario, desconto, outros, subtotal)
VALUES (@c, @esc20, 'C20-ESC - Contêiner Escritório 20 pés', 38000, 2, 'MENSAL', 1, 2400, 0, 0, 4800);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status, devolvida_em) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C20-ESC-001'), @esc20, @i, @admin, TIMESTAMP(@hoje - INTERVAL 150 DAY), TIMESTAMP(@hoje - INTERVAL 119 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 120 DAY, '17:00:00')),
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C20-ESC-002'), @esc20, @i, @admin, TIMESTAMP(@hoje - INTERVAL 150 DAY), TIMESTAMP(@hoje - INTERVAL 119 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 120 DAY, '17:00:00'));
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 1, DATE_FORMAT(@hoje - INTERVAL 150 DAY, '%Y-%m-01'), @hoje - INTERVAL 150 DAY, 4800, 0, 0, FALSE);
INSERT INTO pagamentos (parcela_id, registrado_por, valor, pago_em, forma, criado_em)
VALUES (LAST_INSERT_ID(), @admin, 4800, TIMESTAMP(@hoje - INTERVAL 149 DAY, '10:30:00'), 'PIX', TIMESTAMP(@hoje - INTERVAL 149 DAY, '10:30:00'));

-- ---------------------------------------------------------------------------
-- Contrato 4 — FINALIZADO. Litoral Eventos: 1 almoxarifado semanal por 30 dias.
-- 30 dias ÷ 7 = 4,2857 semanas × 900 = 3.857,13 em 2x (1.928,56 + 1.928,57): cartão de crédito e dinheiro.
-- ---------------------------------------------------------------------------
INSERT INTO contratos (empresa_id, cliente_id, condicao_pagamento_id, criado_por, numero_locacao, numero_contrato, data_emissao, prazo,
                       data_inicio, data_fim, periodicidade, status, numero_parcelas, frete_deslocamento, outros_valores, caucao,
                       dados_cliente_snapshot, dados_empresa_snapshot, criado_em, finalizado_em)
VALUES (@emp, @cli_eventos, @x2, @admin, 'LOC-000004', 'CT-000004', @hoje - INTERVAL 110 DAY, 'DETERMINADO',
        @hoje - INTERVAL 110 DAY, @hoje - INTERVAL 80 DAY, 'SEMANAL', 'FINALIZADO', 2, 0, 0, 0,
        (SELECT JSON_OBJECT('id', id, 'nome', nome_razao_social, 'cpf_cnpj', cpf_cnpj, 'email', email) FROM pessoas WHERE id = @cli_eventos),
        @snap_empresa, TIMESTAMP(@hoje - INTERVAL 110 DAY, '09:00:00'), TIMESTAMP(@hoje - INTERVAL 80 DAY, '17:00:00'));
SET @c = LAST_INSERT_ID();
INSERT INTO contrato_itens (contrato_id, produto_id, descricao_snapshot, valor_unitario_bem_snapshot, quantidade, periodicidade, numero_periodos, valor_unitario, desconto, outros, subtotal)
VALUES (@c, @alm40, 'C40-ALM - Contêiner Almoxarifado 40 pés', 52000, 1, 'SEMANAL', 4.2857, 900, 0, 0, 3857.13);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status, devolvida_em) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C40-ALM-001'), @alm40, @i, @admin, TIMESTAMP(@hoje - INTERVAL 110 DAY), TIMESTAMP(@hoje - INTERVAL 79 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 80 DAY, '17:00:00'));
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 1, DATE_FORMAT(@hoje - INTERVAL 80 DAY, '%Y-%m-01'), @hoje - INTERVAL 80 DAY, 1928.56, 0, 0, FALSE);
INSERT INTO pagamentos (parcela_id, registrado_por, valor, pago_em, forma, criado_em)
VALUES (LAST_INSERT_ID(), @admin, 1928.56, TIMESTAMP(@hoje - INTERVAL 80 DAY, '10:30:00'), 'CARTAO_CREDITO', TIMESTAMP(@hoje - INTERVAL 80 DAY, '10:30:00'));
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 2, DATE_FORMAT(@hoje - INTERVAL 50 DAY, '%Y-%m-01'), @hoje - INTERVAL 50 DAY, 1928.57, 0, 0, FALSE);
INSERT INTO pagamentos (parcela_id, registrado_por, valor, pago_em, forma, criado_em)
VALUES (LAST_INSERT_ID(), @admin, 1928.57, TIMESTAMP(@hoje - INTERVAL 50 DAY, '10:30:00'), 'DINHEIRO', TIMESTAMP(@hoje - INTERVAL 50 DAY, '10:30:00'));

-- ---------------------------------------------------------------------------
-- Contrato 5 — FINALIZADO. Litoral Eventos: 2 sanitários por 20 diárias + frete.
-- 90 × 2 × 20 = 3.600,00 + 300,00 = 3.900,00 à vista, transferência.
-- ---------------------------------------------------------------------------
INSERT INTO contratos (empresa_id, cliente_id, condicao_pagamento_id, criado_por, numero_locacao, numero_contrato, data_emissao, prazo,
                       data_inicio, data_fim, periodicidade, status, numero_parcelas, frete_deslocamento, outros_valores, caucao,
                       dados_cliente_snapshot, dados_empresa_snapshot, criado_em, finalizado_em)
VALUES (@emp, @cli_eventos, @vista, @admin, 'LOC-000005', 'CT-000005', @hoje - INTERVAL 75 DAY, 'DETERMINADO',
        @hoje - INTERVAL 75 DAY, @hoje - INTERVAL 55 DAY, 'DIARIA', 'FINALIZADO', 1, 300, 0, 0,
        (SELECT JSON_OBJECT('id', id, 'nome', nome_razao_social, 'cpf_cnpj', cpf_cnpj, 'email', email) FROM pessoas WHERE id = @cli_eventos),
        @snap_empresa, TIMESTAMP(@hoje - INTERVAL 75 DAY, '09:00:00'), TIMESTAMP(@hoje - INTERVAL 55 DAY, '17:00:00'));
SET @c = LAST_INSERT_ID();
INSERT INTO contrato_itens (contrato_id, produto_id, descricao_snapshot, valor_unitario_bem_snapshot, quantidade, periodicidade, numero_periodos, valor_unitario, desconto, outros, subtotal)
VALUES (@c, @san20, 'C20-SAN - Contêiner Sanitário 20 pés', 30000, 2, 'DIARIA', 20, 90, 0, 0, 3600);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status, devolvida_em) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C20-SAN-001'), @san20, @i, @admin, TIMESTAMP(@hoje - INTERVAL 75 DAY), TIMESTAMP(@hoje - INTERVAL 54 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 55 DAY, '17:00:00')),
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C20-SAN-002'), @san20, @i, @admin, TIMESTAMP(@hoje - INTERVAL 75 DAY), TIMESTAMP(@hoje - INTERVAL 54 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 55 DAY, '17:00:00'));
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 1, DATE_FORMAT(@hoje - INTERVAL 75 DAY, '%Y-%m-01'), @hoje - INTERVAL 75 DAY, 3900, 0, 0, FALSE);
INSERT INTO pagamentos (parcela_id, registrado_por, valor, pago_em, forma, criado_em)
VALUES (LAST_INSERT_ID(), @admin, 3900, TIMESTAMP(@hoje - INTERVAL 46 DAY, '10:30:00'), 'TRANSFERENCIA', TIMESTAMP(@hoje - INTERVAL 46 DAY, '10:30:00'));

-- ---------------------------------------------------------------------------
-- Contrato 6 — ATIVO (em andamento, entrega na obra). Construtora Vale: 2 escritórios 20 pés
-- + 1 sanitário por 3 meses + frete. (2.400 × 2 + 1.900) × 3 = 20.100,00 + 350,00 = 20.450,00 em 3x:
-- 1ª paga (transferência), 2ª vence hoje, 3ª em 30 dias.
-- ---------------------------------------------------------------------------
INSERT INTO contratos (empresa_id, cliente_id, condicao_pagamento_id, criado_por, numero_locacao, numero_contrato,
                       data_emissao, prazo, data_inicio, data_fim, periodicidade, status, numero_parcelas, frete_deslocamento, outros_valores,
                       caucao, observacoes, dados_cliente_snapshot, dados_empresa_snapshot, criado_em)
VALUES (@emp, @cli_vale, @x3, @admin, 'LOC-000006', 'CT-000006', @hoje - INTERVAL 60 DAY, 'DETERMINADO',
        @hoje - INTERVAL 60 DAY, @hoje + INTERVAL 30 DAY, 'MENSAL', 'ATIVO', 3, 350, 0, 0, 'Entrega na obra do Residencial Aquarius.',
        (SELECT JSON_OBJECT('id', id, 'nome', nome_razao_social, 'cpf_cnpj', cpf_cnpj, 'email', email) FROM pessoas WHERE id = @cli_vale),
        @snap_empresa,
        TIMESTAMP(@hoje - INTERVAL 60 DAY, '09:00:00'));
SET @c = LAST_INSERT_ID();
-- Local de entrega digitado no contrato (a obra), diferente do endereço cadastral do cliente.
INSERT INTO contrato_enderecos_entrega (contrato_id, endereco_do_cliente, cep, rua, numero, bairro, cidade, uf, referencia)
VALUES (@c, FALSE, '12245100', 'Rua Obra Residencial Aquarius', 'S/N', 'Jardim Aquarius', 'São José dos Campos', 'SP', 'Portão da obra');
INSERT INTO contrato_itens (contrato_id, produto_id, descricao_snapshot, valor_unitario_bem_snapshot, quantidade, periodicidade, numero_periodos, valor_unitario, desconto, outros, subtotal)
VALUES (@c, @esc20, 'C20-ESC - Contêiner Escritório 20 pés', 38000, 2, 'MENSAL', 3, 2400, 0, 0, 14400);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C20-ESC-001'), @esc20, @i, @admin, TIMESTAMP(@hoje - INTERVAL 60 DAY), TIMESTAMP(@hoje + INTERVAL 31 DAY), 'EM_USO'),
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C20-ESC-002'), @esc20, @i, @admin, TIMESTAMP(@hoje - INTERVAL 60 DAY), TIMESTAMP(@hoje + INTERVAL 31 DAY), 'EM_USO');
INSERT INTO contrato_itens (contrato_id, produto_id, descricao_snapshot, valor_unitario_bem_snapshot, quantidade, periodicidade, numero_periodos, valor_unitario, desconto, outros, subtotal)
VALUES (@c, @san20, 'C20-SAN - Contêiner Sanitário 20 pés', 30000, 1, 'MENSAL', 3, 1900, 0, 0, 5700);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C20-SAN-001'), @san20, @i, @admin, TIMESTAMP(@hoje - INTERVAL 60 DAY), TIMESTAMP(@hoje + INTERVAL 31 DAY), 'EM_USO');
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 1, DATE_FORMAT(@hoje - INTERVAL 30 DAY, '%Y-%m-01'), @hoje - INTERVAL 30 DAY, 6816.66, 0, 0, FALSE);
INSERT INTO pagamentos (parcela_id, registrado_por, valor, pago_em, forma, criado_em)
VALUES (LAST_INSERT_ID(), @admin, 6816.66, TIMESTAMP(@hoje - INTERVAL 29 DAY, '10:30:00'), 'TRANSFERENCIA', TIMESTAMP(@hoje - INTERVAL 29 DAY, '10:30:00'));
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada) VALUES
  (@c, 2, DATE_FORMAT(@hoje, '%Y-%m-01'), @hoje, 6816.66, 0, 0, FALSE),
  (@c, 3, DATE_FORMAT(@hoje + INTERVAL 30 DAY, '%Y-%m-01'), @hoje + INTERVAL 30 DAY, 6816.68, 0, 0, FALSE);

-- ---------------------------------------------------------------------------
-- Contrato 7 — ATIVO com parcela EM ATRASO. João: 1 escritório 10 pés por 60 diárias.
-- 80 × 1 × 60 = 4.800,00 em 30 dias (venceu há 15 dias); pagou 2.000,00 por Pix.
-- ---------------------------------------------------------------------------
INSERT INTO contratos (empresa_id, cliente_id, condicao_pagamento_id, criado_por, numero_locacao, numero_contrato, data_emissao, prazo,
                       data_inicio, data_fim, periodicidade, status, numero_parcelas, frete_deslocamento, outros_valores, caucao,
                       dados_cliente_snapshot, dados_empresa_snapshot, criado_em)
VALUES (@emp, @cli_joao, @d30, @admin, 'LOC-000007', 'CT-000007', @hoje - INTERVAL 45 DAY, 'DETERMINADO',
        @hoje - INTERVAL 45 DAY, @hoje + INTERVAL 15 DAY, 'DIARIA', 'ATIVO', 1, 0, 0, 0,
        (SELECT JSON_OBJECT('id', id, 'nome', nome_razao_social, 'cpf_cnpj', cpf_cnpj, 'email', email) FROM pessoas WHERE id = @cli_joao),
        @snap_empresa, TIMESTAMP(@hoje - INTERVAL 45 DAY, '09:00:00'));
SET @c = LAST_INSERT_ID();
INSERT INTO contrato_itens (contrato_id, produto_id, descricao_snapshot, valor_unitario_bem_snapshot, quantidade, periodicidade, numero_periodos, valor_unitario, desconto, outros, subtotal)
VALUES (@c, @esc10, 'C10-ESC - Contêiner Escritório 10 pés', 24000, 1, 'DIARIA', 60, 80, 0, 0, 4800);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C10-ESC-001'), @esc10, @i, @admin, TIMESTAMP(@hoje - INTERVAL 45 DAY), TIMESTAMP(@hoje + INTERVAL 16 DAY), 'EM_USO');
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 1, DATE_FORMAT(@hoje - INTERVAL 15 DAY, '%Y-%m-01'), @hoje - INTERVAL 15 DAY, 4800, 0, 0, FALSE);
INSERT INTO pagamentos (parcela_id, registrado_por, valor, pago_em, forma, criado_em)
VALUES (LAST_INSERT_ID(), @admin, 2000, TIMESTAMP(@hoje - INTERVAL 14 DAY, '10:30:00'), 'PIX', TIMESTAMP(@hoje - INTERVAL 14 DAY, '10:30:00'));

-- ---------------------------------------------------------------------------
-- Contrato 8 — ATIVO por PRAZO INDETERMINADO. Mineração: 2 almoxarifados, 6 parcelas mensais.
-- 3.200 × 2 × 6 meses = 38.400,00 → 6 × 6.400,00 (a 1ª vence 30 dias após o início).
-- ---------------------------------------------------------------------------
INSERT INTO contratos (empresa_id, cliente_id, condicao_pagamento_id, criado_por, numero_locacao, numero_contrato, data_emissao, prazo,
                       data_inicio, data_fim, periodicidade, status, numero_parcelas, frete_deslocamento, outros_valores, caucao,
                       dados_cliente_snapshot, dados_empresa_snapshot, criado_em)
VALUES (@emp, @cli_mineracao, @d30, @admin, 'LOC-000008', 'CT-000008', @hoje - INTERVAL 20 DAY, 'INDETERMINADO',
        @hoje - INTERVAL 20 DAY, NULL, 'MENSAL', 'ATIVO', 6, 0, 0, 0,
        (SELECT JSON_OBJECT('id', id, 'nome', nome_razao_social, 'cpf_cnpj', cpf_cnpj, 'email', email) FROM pessoas WHERE id = @cli_mineracao),
        @snap_empresa, TIMESTAMP(@hoje - INTERVAL 20 DAY, '09:00:00'));
SET @c = LAST_INSERT_ID();
INSERT INTO contrato_itens (contrato_id, produto_id, descricao_snapshot, valor_unitario_bem_snapshot, quantidade, periodicidade, numero_periodos, valor_unitario, desconto, outros, subtotal)
VALUES (@c, @alm40, 'C40-ALM - Contêiner Almoxarifado 40 pés', 52000, 2, 'MENSAL', 6, 3200, 0, 0, 38400);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C40-ALM-001'), @alm40, @i, @admin, TIMESTAMP(@hoje - INTERVAL 20 DAY), '9999-12-31 00:00:00', 'EM_USO'),
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C40-ALM-002'), @alm40, @i, @admin, TIMESTAMP(@hoje - INTERVAL 20 DAY), '9999-12-31 00:00:00', 'EM_USO');
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada) VALUES
  (@c, 1, DATE_FORMAT(@hoje + INTERVAL 10 DAY, '%Y-%m-01'),  @hoje + INTERVAL 10 DAY,  6400, 0, 0, FALSE),
  (@c, 2, DATE_FORMAT(@hoje + INTERVAL 40 DAY, '%Y-%m-01'),  @hoje + INTERVAL 40 DAY,  6400, 0, 0, FALSE),
  (@c, 3, DATE_FORMAT(@hoje + INTERVAL 70 DAY, '%Y-%m-01'),  @hoje + INTERVAL 70 DAY,  6400, 0, 0, FALSE),
  (@c, 4, DATE_FORMAT(@hoje + INTERVAL 100 DAY, '%Y-%m-01'), @hoje + INTERVAL 100 DAY, 6400, 0, 0, FALSE),
  (@c, 5, DATE_FORMAT(@hoje + INTERVAL 130 DAY, '%Y-%m-01'), @hoje + INTERVAL 130 DAY, 6400, 0, 0, FALSE),
  (@c, 6, DATE_FORMAT(@hoje + INTERVAL 160 DAY, '%Y-%m-01'), @hoje + INTERVAL 160 DAY, 6400, 0, 0, FALSE);

-- ---------------------------------------------------------------------------
-- Contrato 9 — ATIVO, começa hoje. Mariana: 1 sanitário + 1 escritório 20 pés por 14 diárias.
-- 90 × 14 + 120 × 14 = 2.940,00 em 2x de 1.470,00 (30 e 60 dias).
-- ---------------------------------------------------------------------------
INSERT INTO contratos (empresa_id, cliente_id, condicao_pagamento_id, criado_por, numero_locacao, numero_contrato, data_emissao, prazo,
                       data_inicio, data_fim, periodicidade, status, numero_parcelas, frete_deslocamento, outros_valores, caucao,
                       dados_cliente_snapshot, dados_empresa_snapshot, criado_em)
VALUES (@emp, @cli_mariana, @x2, @admin, 'LOC-000009', 'CT-000009', @hoje, 'DETERMINADO',
        @hoje, @hoje + INTERVAL 14 DAY, 'DIARIA', 'ATIVO', 2, 0, 0, 0,
        (SELECT JSON_OBJECT('id', id, 'nome', nome_razao_social, 'cpf_cnpj', cpf_cnpj, 'email', email) FROM pessoas WHERE id = @cli_mariana),
        @snap_empresa, TIMESTAMP(@hoje, '08:00:00'));
SET @c = LAST_INSERT_ID();
INSERT INTO contrato_itens (contrato_id, produto_id, descricao_snapshot, valor_unitario_bem_snapshot, quantidade, periodicidade, numero_periodos, valor_unitario, desconto, outros, subtotal)
VALUES (@c, @san20, 'C20-SAN - Contêiner Sanitário 20 pés', 30000, 1, 'DIARIA', 14, 90, 0, 0, 1260);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C20-SAN-002'), @san20, @i, @admin, TIMESTAMP(@hoje), TIMESTAMP(@hoje + INTERVAL 15 DAY), 'EM_USO');
INSERT INTO contrato_itens (contrato_id, produto_id, descricao_snapshot, valor_unitario_bem_snapshot, quantidade, periodicidade, numero_periodos, valor_unitario, desconto, outros, subtotal)
VALUES (@c, @esc20, 'C20-ESC - Contêiner Escritório 20 pés', 38000, 1, 'DIARIA', 14, 120, 0, 0, 1680);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C20-ESC-003'), @esc20, @i, @admin, TIMESTAMP(@hoje), TIMESTAMP(@hoje + INTERVAL 15 DAY), 'EM_USO');
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada) VALUES
  (@c, 1, DATE_FORMAT(@hoje + INTERVAL 30 DAY, '%Y-%m-01'), @hoje + INTERVAL 30 DAY, 1470, 0, 0, FALSE),
  (@c, 2, DATE_FORMAT(@hoje + INTERVAL 60 DAY, '%Y-%m-01'), @hoje + INTERVAL 60 DAY, 1470, 0, 0, FALSE);

-- ---------------------------------------------------------------------------
-- Contrato 10 — ATIVO, RESERVADO (começa em 7 dias). Agropecuária: 1 refrigerado quinzenal por 30 dias.
-- 3.600 × 2 quinzenas = 7.200,00 + 800,00 de frete = 8.000,00 à vista (vence no início).
-- ---------------------------------------------------------------------------
INSERT INTO contratos (empresa_id, cliente_id, condicao_pagamento_id, criado_por, numero_locacao, numero_contrato, data_emissao, prazo,
                       data_inicio, data_fim, periodicidade, status, numero_parcelas, frete_deslocamento, outros_valores, caucao,
                       dados_cliente_snapshot, dados_empresa_snapshot, criado_em)
VALUES (@emp, @cli_agro, @vista, @admin, 'LOC-000010', 'CT-000010', @hoje, 'DETERMINADO',
        @hoje + INTERVAL 7 DAY, @hoje + INTERVAL 37 DAY, 'QUINZENAL', 'ATIVO', 1, 800, 0, 0,
        (SELECT JSON_OBJECT('id', id, 'nome', nome_razao_social, 'cpf_cnpj', cpf_cnpj, 'email', email) FROM pessoas WHERE id = @cli_agro),
        @snap_empresa, TIMESTAMP(@hoje, '09:00:00'));
SET @c = LAST_INSERT_ID();
INSERT INTO contrato_itens (contrato_id, produto_id, descricao_snapshot, valor_unitario_bem_snapshot, quantidade, periodicidade, numero_periodos, valor_unitario, desconto, outros, subtotal)
VALUES (@c, @ref40, 'C40-REF - Contêiner Refrigerado 40 pés', 120000, 1, 'QUINZENAL', 2, 3600, 0, 0, 7200);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C40-REF-001'), @ref40, @i, @admin, TIMESTAMP(@hoje + INTERVAL 7 DAY), TIMESTAMP(@hoje + INTERVAL 38 DAY), 'RESERVADA');
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 1, DATE_FORMAT(@hoje + INTERVAL 7 DAY, '%Y-%m-01'), @hoje + INTERVAL 7 DAY, 8000, 0, 0, FALSE);

-- ---------------------------------------------------------------------------
-- Contrato 11 — CANCELADO antes de começar. Carlos: 1 escritório 10 pés por 7 diárias (560,00).
-- A reserva e a parcela ficam canceladas.
-- ---------------------------------------------------------------------------
INSERT INTO contratos (empresa_id, cliente_id, condicao_pagamento_id, criado_por, numero_locacao, numero_contrato, data_emissao, prazo,
                       data_inicio, data_fim, periodicidade, status, numero_parcelas, frete_deslocamento, outros_valores, caucao,
                       dados_cliente_snapshot, dados_empresa_snapshot, criado_em, cancelado_em, motivo_cancelamento)
VALUES (@emp, @cli_carlos, @d30, @admin, 'LOC-000011', 'CT-000011', @hoje, 'DETERMINADO',
        @hoje + INTERVAL 3 DAY, @hoje + INTERVAL 10 DAY, 'DIARIA', 'CANCELADO', 1, 0, 0, 0,
        (SELECT JSON_OBJECT('id', id, 'nome', nome_razao_social, 'cpf_cnpj', cpf_cnpj, 'email', email) FROM pessoas WHERE id = @cli_carlos),
        @snap_empresa, TIMESTAMP(@hoje, '10:00:00'), NOW(), 'Cliente desistiu da locação.');
SET @c = LAST_INSERT_ID();
INSERT INTO contrato_itens (contrato_id, produto_id, descricao_snapshot, valor_unitario_bem_snapshot, quantidade, periodicidade, numero_periodos, valor_unitario, desconto, outros, subtotal)
VALUES (@c, @esc10, 'C10-ESC - Contêiner Escritório 10 pés', 24000, 1, 'DIARIA', 7, 80, 0, 0, 560);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'C10-ESC-002'), @esc10, @i, @admin, TIMESTAMP(@hoje + INTERVAL 3 DAY), TIMESTAMP(@hoje + INTERVAL 11 DAY), 'CANCELADA');
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 1, DATE_FORMAT(@hoje + INTERVAL 33 DAY, '%Y-%m-01'), @hoje + INTERVAL 33 DAY, 560, 0, 0, TRUE);

-- ---------------------------------------------------------------------------
-- Contrato 12 — RASCUNHO (ainda não gerado: sem número, sem reserva e sem parcelas).
-- ---------------------------------------------------------------------------
INSERT INTO contratos (empresa_id, cliente_id, condicao_pagamento_id, criado_por, data_emissao, prazo, data_inicio, data_fim,
                       periodicidade, status, numero_parcelas, frete_deslocamento, outros_valores, caucao, criado_em)
VALUES (@emp, @cli_agro, @d30, @admin, @hoje, 'DETERMINADO', @hoje + INTERVAL 20 DAY, @hoje + INTERVAL 50 DAY,
        'MENSAL', 'RASCUNHO', 1, 0, 0, 0, NOW());
INSERT INTO contrato_itens (contrato_id, produto_id, descricao_snapshot, valor_unitario_bem_snapshot, quantidade, periodicidade, numero_periodos, valor_unitario, desconto, outros, subtotal)
VALUES (LAST_INSERT_ID(), @esc20, 'C20-ESC - Contêiner Escritório 20 pés', 38000, 1, 'MENSAL', 1, 2400, 0, 0, 2400);

-- Local de entrega dos demais contratos: cópia do endereço cadastral do cliente
-- (só o contrato 6 tem entrega em outro endereço, gravada acima).
INSERT INTO contrato_enderecos_entrega (contrato_id, endereco_do_cliente, cep, rua, numero, bairro, cidade, uf, complemento)
SELECT c.id, TRUE, e.cep, e.rua, e.numero, e.bairro, e.cidade, e.uf, e.complemento
  FROM contratos c
  JOIN enderecos_pessoa e ON e.pessoa_id = c.cliente_id
 WHERE NOT EXISTS (SELECT 1 FROM contrato_enderecos_entrega x WHERE x.contrato_id = c.id);

-- A numeração automática continua depois dos 11 contratos gerados (próximo: CT-000012).
UPDATE preferencias_contrato
   SET proximo_numero_contrato = GREATEST(COALESCE(proximo_numero_contrato, 1), 12),
       proximo_numero_locacao  = GREATEST(COALESCE(proximo_numero_locacao, 1), 12)
 WHERE empresa_id = @emp;


-- =============================================================================
-- 7. MANUTENÇÕES (com o período bloqueado na agenda do contêiner)
-- =============================================================================

-- Em andamento: o contêiner fica em MANUTENCAO e bloqueado até a conclusão.
INSERT INTO manutencoes (unidade_id, responsavel_usuario_id, criado_por, tipo, prioridade, status, data_prevista, iniciada_em, descricao, criado_em)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'C20-ESC-004'), @carla, @admin, 'Preventiva', 'ALTA', 'EM_ANDAMENTO',
        TIMESTAMP(@hoje), TIMESTAMP(@hoje, '08:00:00'), 'Revisão da parte elétrica e do ar-condicionado.', TIMESTAMP(@hoje - INTERVAL 2 DAY, '14:00:00'));
INSERT INTO agenda_unidades (unidade_id, produto_id, manutencao_id, criado_por, inicio, fim, status)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'C20-ESC-004'), @esc20, LAST_INSERT_ID(), @admin, TIMESTAMP(@hoje), '9999-12-31 00:00:00', 'MANUTENCAO');

-- Agendada para hoje, urgente.
INSERT INTO manutencoes (unidade_id, responsavel_usuario_id, criado_por, tipo, prioridade, status, data_prevista, descricao, criado_em)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'C40-ALM-003'), @rafael, @admin, 'Corretiva', 'URGENTE', 'AGENDADA',
        TIMESTAMP(@hoje), 'Porta traseira emperrada; trocar dobradiças.', TIMESTAMP(@hoje - INTERVAL 1 DAY, '16:00:00'));
INSERT INTO agenda_unidades (unidade_id, produto_id, manutencao_id, criado_por, inicio, fim, status)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'C40-ALM-003'), @alm40, LAST_INSERT_ID(), @admin, TIMESTAMP(@hoje), TIMESTAMP(@hoje + INTERVAL 1 DAY), 'MANUTENCAO');

-- Agendada para daqui a 5 dias.
INSERT INTO manutencoes (unidade_id, responsavel_usuario_id, criado_por, tipo, prioridade, status, data_prevista, descricao, criado_em)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'C20-SAN-003'), @carla, @admin, 'Limpeza', 'BAIXA', 'AGENDADA',
        TIMESTAMP(@hoje + INTERVAL 5 DAY), 'Higienização completa antes da próxima locação.', NOW());
INSERT INTO agenda_unidades (unidade_id, produto_id, manutencao_id, criado_por, inicio, fim, status)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'C20-SAN-003'), @san20, LAST_INSERT_ID(), @admin, TIMESTAMP(@hoje + INTERVAL 5 DAY), TIMESTAMP(@hoje + INTERVAL 6 DAY), 'MANUTENCAO');

-- Concluídas neste mês (contam em "Concluídas no mês"); a agenda termina na conclusão.
INSERT INTO manutencoes (unidade_id, responsavel_usuario_id, criado_por, tipo, prioridade, status, data_prevista, iniciada_em, concluida_em, descricao, custo, criado_em)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'C10-ESC-002'), @admin, @admin, 'Vistoria', 'MEDIA', 'CONCLUIDA',
        TIMESTAMP(@hoje), TIMESTAMP(@hoje), NOW(), 'Vistoria de devolução.', 180, TIMESTAMP(@hoje - INTERVAL 3 DAY, '11:00:00'));
INSERT INTO agenda_unidades (unidade_id, produto_id, manutencao_id, criado_por, inicio, fim, status)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'C10-ESC-002'), @esc10, LAST_INSERT_ID(), @admin, TIMESTAMP(@hoje), GREATEST(NOW(), TIMESTAMP(@hoje) + INTERVAL 1 SECOND), 'MANUTENCAO');

INSERT INTO manutencoes (unidade_id, responsavel_usuario_id, criado_por, tipo, prioridade, status, data_prevista, iniciada_em, concluida_em,
                         responsavel_externo, descricao, custo, criado_em)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'C40-REF-001'), @rafael, @admin, 'Pintura', 'MEDIA', 'CONCLUIDA',
        TIMESTAMP(@hoje), TIMESTAMP(@hoje), NOW(), 'Pinturas Vale LTDA', 'Pintura externa e tratamento de ferrugem.', 950,
        TIMESTAMP(@hoje - INTERVAL 5 DAY, '09:00:00'));
INSERT INTO agenda_unidades (unidade_id, produto_id, manutencao_id, criado_por, inicio, fim, status)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'C40-REF-001'), @ref40, LAST_INSERT_ID(), @admin, TIMESTAMP(@hoje), GREATEST(NOW(), TIMESTAMP(@hoje) + INTERVAL 1 SECOND), 'MANUTENCAO');

COMMIT;

-- Resumo (deve mostrar: 9 clientes, 5 produtos, 19 contêineres, 12 contratos, R$ 31.573,79 recebidos).
SELECT (SELECT COUNT(*) FROM pessoas WHERE tipo_cadastro IN ('CLIENTE', 'AMBOS'))     AS clientes,
       (SELECT COUNT(*) FROM produtos)                                                 AS produtos,
       (SELECT COUNT(*) FROM unidades_fisicas)                                         AS conteineres,
       (SELECT COUNT(*) FROM contratos)                                                AS contratos,
       (SELECT SUM(valor) FROM pagamentos WHERE estornado_em IS NULL)                  AS total_recebido;
