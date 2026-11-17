-- =============================================================================
-- SGCB - Seed 003: dados de EXEMPLO para ver as telas funcionando
-- (dashboard, clientes, produtos, manutenções e financeiro com informações).
--
-- Insere: 2 usuários operadores, 1 categoria, 2 fornecedores, 8 clientes, 3 produtos
-- (contêineres de 2,00 m, 2,50 m e 3,00 m; 19 unidades), 12 contratos (ativos, reservado, finalizados, cancelado e rascunho),
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
-- registro repetido, o usuário de exemplo, e nada é gravado). Para recomeçar, apague o banco sgcb e rode tudo de novo.
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
-- 1. CATEGORIA
-- Uma categoria só ("Contêiner"): os produtos se diferenciam pelo tamanho (2,00 m, 2,50 m e 3,00 m).
-- É o nome que o cadastro de produto já deixa pré-selecionado. Se ela já existir, é reaproveitada.
-- =============================================================================

INSERT INTO categorias (nome, ativo) VALUES ('Contêiner', TRUE)
ON DUPLICATE KEY UPDATE id = id;

-- Id buscado pelo nome (funciona no phpMyAdmin e no terminal).
SET @cat_cont = (SELECT id FROM categorias WHERE nome = 'Contêiner');


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
-- Padrão de tamanhos da empresa: contêineres de 2,00 m, 2,50 m e 3,00 m.
-- O estado de cada contêiner já é o final, depois dos contratos e manutenções abaixo.
-- =============================================================================

INSERT INTO produtos (categoria_id, fornecedor_id, criado_por, codigo, nome, descricao, valor_total_bem, limite_inicial, limite_maximo, maximo_estoque, ativo, criado_em)
VALUES (@cat_cont, @forn_mcb, @admin, 'CB-200', 'Contêiner 2,00 m',
        'Contêiner de 2,00 m com instalação elétrica, iluminação e porta com fechadura.', 24000, 1, 10, 14, TRUE, @hoje - INTERVAL 300 DAY);
SET @c200 = LAST_INSERT_ID();
INSERT INTO precos_produto (produto_id, periodicidade, valor) VALUES (@c200, 'DIARIA', 80), (@c200, 'SEMANAL', 450), (@c200, 'QUINZENAL', 850), (@c200, 'MENSAL', 1600);

INSERT INTO produtos (categoria_id, fornecedor_id, criado_por, codigo, nome, descricao, valor_total_bem, limite_inicial, limite_maximo, maximo_estoque, ativo, criado_em)
VALUES (@cat_cont, @forn_mcb, @admin, 'CB-250', 'Contêiner 2,50 m',
        'Contêiner de 2,50 m com instalação elétrica, iluminação e porta com fechadura.', 38000, 1, 10, 16, TRUE, @hoje - INTERVAL 300 DAY);
SET @c250 = LAST_INSERT_ID();
INSERT INTO precos_produto (produto_id, periodicidade, valor) VALUES (@c250, 'DIARIA', 120), (@c250, 'SEMANAL', 700), (@c250, 'QUINZENAL', 1300), (@c250, 'MENSAL', 2400);

INSERT INTO produtos (categoria_id, fornecedor_id, criado_por, codigo, nome, descricao, valor_total_bem, limite_inicial, limite_maximo, maximo_estoque, ativo, criado_em)
VALUES (@cat_cont, @forn_mcb, @admin, 'CB-300', 'Contêiner 3,00 m',
        'Contêiner de 3,00 m com instalação elétrica, iluminação e porta com fechadura.', 52000, 1, 8, 14, TRUE, @hoje - INTERVAL 300 DAY);
SET @c300 = LAST_INSERT_ID();
INSERT INTO precos_produto (produto_id, periodicidade, valor) VALUES (@c300, 'DIARIA', 150), (@c300, 'SEMANAL', 900), (@c300, 'QUINZENAL', 1700), (@c300, 'MENSAL', 3200);

-- 19 contêineres (7 de 2,00 m, 6 de 2,50 m e 6 de 3,00 m).
-- LOCADA = em contrato em andamento; MANUTENCAO = manutenção em andamento.
INSERT INTO unidades_fisicas (produto_id, codigo, estado_operacional, localidade, ultima_vistoria_em, criado_em) VALUES
  (@c200, 'CB-200-001', 'LOCADA',     'São José dos Campos',                             NULL, @hoje - INTERVAL 300 DAY),
  (@c200, 'CB-200-002', 'DISPONIVEL', 'Pátio Jacareí',                                   NOW(), @hoje - INTERVAL 300 DAY),
  (@c200, 'CB-200-003', 'DISPONIVEL', 'Pátio Jacareí',                                   NULL, @hoje - INTERVAL 300 DAY),
  (@c200, 'CB-200-004', 'DISPONIVEL', 'Pátio Jacareí',                                   NULL, @hoje - INTERVAL 300 DAY),
  (@c200, 'CB-200-005', 'LOCADA',     'Obra Residencial Aquarius - São José dos Campos', NULL, @hoje - INTERVAL 300 DAY),
  (@c200, 'CB-200-006', 'LOCADA',     'Taubaté',                                         NULL, @hoje - INTERVAL 300 DAY),
  (@c200, 'CB-200-007', 'DISPONIVEL', 'Pátio São José dos Campos',                       NULL, @hoje - INTERVAL 300 DAY),
  (@c250, 'CB-250-001', 'LOCADA',     'Obra Residencial Aquarius - São José dos Campos', NULL, @hoje - INTERVAL 300 DAY),
  (@c250, 'CB-250-002', 'LOCADA',     'Obra Residencial Aquarius - São José dos Campos', NULL, @hoje - INTERVAL 300 DAY),
  (@c250, 'CB-250-003', 'LOCADA',     'Taubaté',                                         NULL, @hoje - INTERVAL 300 DAY),
  (@c250, 'CB-250-004', 'MANUTENCAO', 'Pátio Jacareí',                                   NULL, @hoje - INTERVAL 300 DAY),
  (@c250, 'CB-250-005', 'DISPONIVEL', 'Pátio Jacareí',                                   NULL, @hoje - INTERVAL 300 DAY),
  (@c250, 'CB-250-006', 'DISPONIVEL', 'Pátio Jacareí',                                   NULL, @hoje - INTERVAL 300 DAY),
  (@c300, 'CB-300-001', 'LOCADA',     'Pedreira Serra Azul - Jacareí',                   NULL, @hoje - INTERVAL 300 DAY),
  (@c300, 'CB-300-002', 'LOCADA',     'Pedreira Serra Azul - Jacareí',                   NULL, @hoje - INTERVAL 300 DAY),
  (@c300, 'CB-300-003', 'DISPONIVEL', 'Pátio São José dos Campos',                       NULL, @hoje - INTERVAL 300 DAY),
  (@c300, 'CB-300-004', 'DISPONIVEL', 'Pátio São José dos Campos',                       NULL, @hoje - INTERVAL 300 DAY),
  (@c300, 'CB-300-005', 'DISPONIVEL', 'Pátio Jacareí',                                   NOW(), @hoje - INTERVAL 300 DAY),
  (@c300, 'CB-300-006', 'DISPONIVEL', 'Pátio Jacareí',                                   NULL, @hoje - INTERVAL 300 DAY);


-- =============================================================================
-- 6. CONTRATOS, ITENS, AGENDA DOS CONTÊINERES, PARCELAS E PAGAMENTOS
-- Na agenda, "fim" é o dia seguinte à data final (o período é [início, fim)).
-- Parcela: competência = 1º dia do mês do vencimento.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- Contrato 1 — FINALIZADO (8 meses atrás). Mineração: 2 contêineres de 3,00 m por 1 mês.
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
VALUES (@c, @c300, 'CB-300 - Contêiner 3,00 m', 52000, 2, 'MENSAL', 1, 3200, 0, 0, 6400);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status, devolvida_em) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-300-001'), @c300, @i, @admin, TIMESTAMP(@hoje - INTERVAL 240 DAY), TIMESTAMP(@hoje - INTERVAL 209 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 210 DAY, '17:00:00')),
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-300-002'), @c300, @i, @admin, TIMESTAMP(@hoje - INTERVAL 240 DAY), TIMESTAMP(@hoje - INTERVAL 209 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 210 DAY, '17:00:00'));
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 1, DATE_FORMAT(@hoje - INTERVAL 240 DAY, '%Y-%m-01'), @hoje - INTERVAL 240 DAY, 6400, 0, 0, FALSE);
INSERT INTO pagamentos (parcela_id, registrado_por, valor, pago_em, forma, criado_em)
VALUES (LAST_INSERT_ID(), @admin, 6400, TIMESTAMP(@hoje - INTERVAL 238 DAY, '10:30:00'), 'BOLETO', TIMESTAMP(@hoje - INTERVAL 238 DAY, '10:30:00'));

-- ---------------------------------------------------------------------------
-- Contrato 2 — FINALIZADO. Carlos: 3 contêineres de 2,00 m por 15 diárias + frete.
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
VALUES (@c, @c200, 'CB-200 - Contêiner 2,00 m', 24000, 3, 'DIARIA', 15, 80, 0, 0, 3600);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status, devolvida_em) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-200-001'), @c200, @i, @admin, TIMESTAMP(@hoje - INTERVAL 200 DAY), TIMESTAMP(@hoje - INTERVAL 184 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 185 DAY, '17:00:00')),
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-200-002'), @c200, @i, @admin, TIMESTAMP(@hoje - INTERVAL 200 DAY), TIMESTAMP(@hoje - INTERVAL 184 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 185 DAY, '17:00:00')),
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-200-003'), @c200, @i, @admin, TIMESTAMP(@hoje - INTERVAL 200 DAY), TIMESTAMP(@hoje - INTERVAL 184 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 185 DAY, '17:00:00'));
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 1, DATE_FORMAT(@hoje - INTERVAL 200 DAY, '%Y-%m-01'), @hoje - INTERVAL 200 DAY, 3800, 0, 0, FALSE);
INSERT INTO pagamentos (parcela_id, registrado_por, valor, pago_em, forma, criado_em)
VALUES (LAST_INSERT_ID(), @admin, 3800, TIMESTAMP(@hoje - INTERVAL 199 DAY, '10:30:00'), 'CARTAO_DEBITO', TIMESTAMP(@hoje - INTERVAL 199 DAY, '10:30:00'));

-- ---------------------------------------------------------------------------
-- Contrato 3 — FINALIZADO. Construtora Vale: 2 contêineres de 2,50 m por 1 mês.
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
VALUES (@c, @c250, 'CB-250 - Contêiner 2,50 m', 38000, 2, 'MENSAL', 1, 2400, 0, 0, 4800);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status, devolvida_em) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-250-001'), @c250, @i, @admin, TIMESTAMP(@hoje - INTERVAL 150 DAY), TIMESTAMP(@hoje - INTERVAL 119 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 120 DAY, '17:00:00')),
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-250-002'), @c250, @i, @admin, TIMESTAMP(@hoje - INTERVAL 150 DAY), TIMESTAMP(@hoje - INTERVAL 119 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 120 DAY, '17:00:00'));
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 1, DATE_FORMAT(@hoje - INTERVAL 150 DAY, '%Y-%m-01'), @hoje - INTERVAL 150 DAY, 4800, 0, 0, FALSE);
INSERT INTO pagamentos (parcela_id, registrado_por, valor, pago_em, forma, criado_em)
VALUES (LAST_INSERT_ID(), @admin, 4800, TIMESTAMP(@hoje - INTERVAL 149 DAY, '10:30:00'), 'PIX', TIMESTAMP(@hoje - INTERVAL 149 DAY, '10:30:00'));

-- ---------------------------------------------------------------------------
-- Contrato 4 — FINALIZADO. Litoral Eventos: 1 contêiner de 3,00 m semanal por 30 dias.
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
VALUES (@c, @c300, 'CB-300 - Contêiner 3,00 m', 52000, 1, 'SEMANAL', 4.2857, 900, 0, 0, 3857.13);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status, devolvida_em) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-300-001'), @c300, @i, @admin, TIMESTAMP(@hoje - INTERVAL 110 DAY), TIMESTAMP(@hoje - INTERVAL 79 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 80 DAY, '17:00:00'));
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 1, DATE_FORMAT(@hoje - INTERVAL 80 DAY, '%Y-%m-01'), @hoje - INTERVAL 80 DAY, 1928.56, 0, 0, FALSE);
INSERT INTO pagamentos (parcela_id, registrado_por, valor, pago_em, forma, criado_em)
VALUES (LAST_INSERT_ID(), @admin, 1928.56, TIMESTAMP(@hoje - INTERVAL 80 DAY, '10:30:00'), 'CARTAO_CREDITO', TIMESTAMP(@hoje - INTERVAL 80 DAY, '10:30:00'));
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 2, DATE_FORMAT(@hoje - INTERVAL 50 DAY, '%Y-%m-01'), @hoje - INTERVAL 50 DAY, 1928.57, 0, 0, FALSE);
INSERT INTO pagamentos (parcela_id, registrado_por, valor, pago_em, forma, criado_em)
VALUES (LAST_INSERT_ID(), @admin, 1928.57, TIMESTAMP(@hoje - INTERVAL 50 DAY, '10:30:00'), 'DINHEIRO', TIMESTAMP(@hoje - INTERVAL 50 DAY, '10:30:00'));

-- ---------------------------------------------------------------------------
-- Contrato 5 — FINALIZADO. Litoral Eventos: 2 contêineres de 2,00 m por 20 diárias + frete.
-- 80 × 2 × 20 = 3.200,00 + 300,00 = 3.500,00 à vista, transferência.
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
VALUES (@c, @c200, 'CB-200 - Contêiner 2,00 m', 24000, 2, 'DIARIA', 20, 80, 0, 0, 3200);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status, devolvida_em) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-200-005'), @c200, @i, @admin, TIMESTAMP(@hoje - INTERVAL 75 DAY), TIMESTAMP(@hoje - INTERVAL 54 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 55 DAY, '17:00:00')),
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-200-006'), @c200, @i, @admin, TIMESTAMP(@hoje - INTERVAL 75 DAY), TIMESTAMP(@hoje - INTERVAL 54 DAY), 'DEVOLVIDA', TIMESTAMP(@hoje - INTERVAL 55 DAY, '17:00:00'));
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 1, DATE_FORMAT(@hoje - INTERVAL 75 DAY, '%Y-%m-01'), @hoje - INTERVAL 75 DAY, 3500, 0, 0, FALSE);
INSERT INTO pagamentos (parcela_id, registrado_por, valor, pago_em, forma, criado_em)
VALUES (LAST_INSERT_ID(), @admin, 3500, TIMESTAMP(@hoje - INTERVAL 46 DAY, '10:30:00'), 'TRANSFERENCIA', TIMESTAMP(@hoje - INTERVAL 46 DAY, '10:30:00'));

-- ---------------------------------------------------------------------------
-- Contrato 6 — ATIVO (em andamento, entrega na obra). Construtora Vale: 2 contêineres de 2,50 m
-- + 1 de 2,00 m por 3 meses + frete. (2.400 × 2 + 1.600) × 3 = 19.200,00 + 350,00 = 19.550,00 em 3x:
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
VALUES (@c, @c250, 'CB-250 - Contêiner 2,50 m', 38000, 2, 'MENSAL', 3, 2400, 0, 0, 14400);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-250-001'), @c250, @i, @admin, TIMESTAMP(@hoje - INTERVAL 60 DAY), TIMESTAMP(@hoje + INTERVAL 31 DAY), 'EM_USO'),
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-250-002'), @c250, @i, @admin, TIMESTAMP(@hoje - INTERVAL 60 DAY), TIMESTAMP(@hoje + INTERVAL 31 DAY), 'EM_USO');
INSERT INTO contrato_itens (contrato_id, produto_id, descricao_snapshot, valor_unitario_bem_snapshot, quantidade, periodicidade, numero_periodos, valor_unitario, desconto, outros, subtotal)
VALUES (@c, @c200, 'CB-200 - Contêiner 2,00 m', 24000, 1, 'MENSAL', 3, 1600, 0, 0, 4800);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-200-005'), @c200, @i, @admin, TIMESTAMP(@hoje - INTERVAL 60 DAY), TIMESTAMP(@hoje + INTERVAL 31 DAY), 'EM_USO');
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 1, DATE_FORMAT(@hoje - INTERVAL 30 DAY, '%Y-%m-01'), @hoje - INTERVAL 30 DAY, 6516.66, 0, 0, FALSE);
INSERT INTO pagamentos (parcela_id, registrado_por, valor, pago_em, forma, criado_em)
VALUES (LAST_INSERT_ID(), @admin, 6516.66, TIMESTAMP(@hoje - INTERVAL 29 DAY, '10:30:00'), 'TRANSFERENCIA', TIMESTAMP(@hoje - INTERVAL 29 DAY, '10:30:00'));
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada) VALUES
  (@c, 2, DATE_FORMAT(@hoje, '%Y-%m-01'), @hoje, 6516.66, 0, 0, FALSE),
  (@c, 3, DATE_FORMAT(@hoje + INTERVAL 30 DAY, '%Y-%m-01'), @hoje + INTERVAL 30 DAY, 6516.68, 0, 0, FALSE);

-- ---------------------------------------------------------------------------
-- Contrato 7 — ATIVO com parcela EM ATRASO. João: 1 contêiner de 2,00 m por 60 diárias.
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
VALUES (@c, @c200, 'CB-200 - Contêiner 2,00 m', 24000, 1, 'DIARIA', 60, 80, 0, 0, 4800);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-200-001'), @c200, @i, @admin, TIMESTAMP(@hoje - INTERVAL 45 DAY), TIMESTAMP(@hoje + INTERVAL 16 DAY), 'EM_USO');
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 1, DATE_FORMAT(@hoje - INTERVAL 15 DAY, '%Y-%m-01'), @hoje - INTERVAL 15 DAY, 4800, 0, 0, FALSE);
INSERT INTO pagamentos (parcela_id, registrado_por, valor, pago_em, forma, criado_em)
VALUES (LAST_INSERT_ID(), @admin, 2000, TIMESTAMP(@hoje - INTERVAL 14 DAY, '10:30:00'), 'PIX', TIMESTAMP(@hoje - INTERVAL 14 DAY, '10:30:00'));

-- ---------------------------------------------------------------------------
-- Contrato 8 — ATIVO por PRAZO INDETERMINADO. Mineração: 2 contêineres de 3,00 m, 6 parcelas mensais.
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
VALUES (@c, @c300, 'CB-300 - Contêiner 3,00 m', 52000, 2, 'MENSAL', 6, 3200, 0, 0, 38400);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-300-001'), @c300, @i, @admin, TIMESTAMP(@hoje - INTERVAL 20 DAY), '9999-12-31 00:00:00', 'EM_USO'),
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-300-002'), @c300, @i, @admin, TIMESTAMP(@hoje - INTERVAL 20 DAY), '9999-12-31 00:00:00', 'EM_USO');
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada) VALUES
  (@c, 1, DATE_FORMAT(@hoje + INTERVAL 10 DAY, '%Y-%m-01'),  @hoje + INTERVAL 10 DAY,  6400, 0, 0, FALSE),
  (@c, 2, DATE_FORMAT(@hoje + INTERVAL 40 DAY, '%Y-%m-01'),  @hoje + INTERVAL 40 DAY,  6400, 0, 0, FALSE),
  (@c, 3, DATE_FORMAT(@hoje + INTERVAL 70 DAY, '%Y-%m-01'),  @hoje + INTERVAL 70 DAY,  6400, 0, 0, FALSE),
  (@c, 4, DATE_FORMAT(@hoje + INTERVAL 100 DAY, '%Y-%m-01'), @hoje + INTERVAL 100 DAY, 6400, 0, 0, FALSE),
  (@c, 5, DATE_FORMAT(@hoje + INTERVAL 130 DAY, '%Y-%m-01'), @hoje + INTERVAL 130 DAY, 6400, 0, 0, FALSE),
  (@c, 6, DATE_FORMAT(@hoje + INTERVAL 160 DAY, '%Y-%m-01'), @hoje + INTERVAL 160 DAY, 6400, 0, 0, FALSE);

-- ---------------------------------------------------------------------------
-- Contrato 9 — ATIVO, começa hoje. Mariana: 1 contêiner de 2,00 m + 1 de 2,50 m por 14 diárias.
-- 80 × 14 + 120 × 14 = 2.800,00 em 2x de 1.400,00 (30 e 60 dias).
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
VALUES (@c, @c200, 'CB-200 - Contêiner 2,00 m', 24000, 1, 'DIARIA', 14, 80, 0, 0, 1120);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-200-006'), @c200, @i, @admin, TIMESTAMP(@hoje), TIMESTAMP(@hoje + INTERVAL 15 DAY), 'EM_USO');
INSERT INTO contrato_itens (contrato_id, produto_id, descricao_snapshot, valor_unitario_bem_snapshot, quantidade, periodicidade, numero_periodos, valor_unitario, desconto, outros, subtotal)
VALUES (@c, @c250, 'CB-250 - Contêiner 2,50 m', 38000, 1, 'DIARIA', 14, 120, 0, 0, 1680);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-250-003'), @c250, @i, @admin, TIMESTAMP(@hoje), TIMESTAMP(@hoje + INTERVAL 15 DAY), 'EM_USO');
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada) VALUES
  (@c, 1, DATE_FORMAT(@hoje + INTERVAL 30 DAY, '%Y-%m-01'), @hoje + INTERVAL 30 DAY, 1400, 0, 0, FALSE),
  (@c, 2, DATE_FORMAT(@hoje + INTERVAL 60 DAY, '%Y-%m-01'), @hoje + INTERVAL 60 DAY, 1400, 0, 0, FALSE);

-- ---------------------------------------------------------------------------
-- Contrato 10 — ATIVO, RESERVADO (começa em 7 dias). Agropecuária: 1 contêiner de 3,00 m quinzenal por 30 dias.
-- 1.700 × 2 quinzenas = 3.400,00 + 800,00 de frete = 4.200,00 à vista (vence no início).
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
VALUES (@c, @c300, 'CB-300 - Contêiner 3,00 m', 52000, 1, 'QUINZENAL', 2, 1700, 0, 0, 3400);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-300-005'), @c300, @i, @admin, TIMESTAMP(@hoje + INTERVAL 7 DAY), TIMESTAMP(@hoje + INTERVAL 38 DAY), 'RESERVADA');
INSERT INTO parcelas (contrato_id, numero, competencia, vencimento, valor_original, acrescimo, desconto, cancelada)
VALUES (@c, 1, DATE_FORMAT(@hoje + INTERVAL 7 DAY, '%Y-%m-01'), @hoje + INTERVAL 7 DAY, 4200, 0, 0, FALSE);

-- ---------------------------------------------------------------------------
-- Contrato 11 — CANCELADO antes de começar. Carlos: 1 contêiner de 2,00 m por 7 diárias (560,00).
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
VALUES (@c, @c200, 'CB-200 - Contêiner 2,00 m', 24000, 1, 'DIARIA', 7, 80, 0, 0, 560);
SET @i = LAST_INSERT_ID();
INSERT INTO agenda_unidades (unidade_id, produto_id, contrato_item_id, criado_por, inicio, fim, status) VALUES
  ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-200-002'), @c200, @i, @admin, TIMESTAMP(@hoje + INTERVAL 3 DAY), TIMESTAMP(@hoje + INTERVAL 11 DAY), 'CANCELADA');
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
VALUES (LAST_INSERT_ID(), @c250, 'CB-250 - Contêiner 2,50 m', 38000, 1, 'MENSAL', 1, 2400, 0, 0, 2400);

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
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-250-004'), @carla, @admin, 'Preventiva', 'ALTA', 'EM_ANDAMENTO',
        TIMESTAMP(@hoje), TIMESTAMP(@hoje, '08:00:00'), 'Revisão da parte elétrica e do ar-condicionado.', TIMESTAMP(@hoje - INTERVAL 2 DAY, '14:00:00'));
INSERT INTO agenda_unidades (unidade_id, produto_id, manutencao_id, criado_por, inicio, fim, status)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-250-004'), @c250, LAST_INSERT_ID(), @admin, TIMESTAMP(@hoje), '9999-12-31 00:00:00', 'MANUTENCAO');

-- Agendada para hoje, urgente.
INSERT INTO manutencoes (unidade_id, responsavel_usuario_id, criado_por, tipo, prioridade, status, data_prevista, descricao, criado_em)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-300-003'), @rafael, @admin, 'Corretiva', 'URGENTE', 'AGENDADA',
        TIMESTAMP(@hoje), 'Porta traseira emperrada; trocar dobradiças.', TIMESTAMP(@hoje - INTERVAL 1 DAY, '16:00:00'));
INSERT INTO agenda_unidades (unidade_id, produto_id, manutencao_id, criado_por, inicio, fim, status)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-300-003'), @c300, LAST_INSERT_ID(), @admin, TIMESTAMP(@hoje), TIMESTAMP(@hoje + INTERVAL 1 DAY), 'MANUTENCAO');

-- Agendada para daqui a 5 dias.
INSERT INTO manutencoes (unidade_id, responsavel_usuario_id, criado_por, tipo, prioridade, status, data_prevista, descricao, criado_em)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-200-007'), @carla, @admin, 'Limpeza', 'BAIXA', 'AGENDADA',
        TIMESTAMP(@hoje + INTERVAL 5 DAY), 'Higienização completa antes da próxima locação.', NOW());
INSERT INTO agenda_unidades (unidade_id, produto_id, manutencao_id, criado_por, inicio, fim, status)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-200-007'), @c200, LAST_INSERT_ID(), @admin, TIMESTAMP(@hoje + INTERVAL 5 DAY), TIMESTAMP(@hoje + INTERVAL 6 DAY), 'MANUTENCAO');

-- Concluídas neste mês (contam em "Concluídas no mês"); a agenda termina na conclusão.
INSERT INTO manutencoes (unidade_id, responsavel_usuario_id, criado_por, tipo, prioridade, status, data_prevista, iniciada_em, concluida_em, descricao, custo, criado_em)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-200-002'), @admin, @admin, 'Vistoria', 'MEDIA', 'CONCLUIDA',
        TIMESTAMP(@hoje), TIMESTAMP(@hoje), NOW(), 'Vistoria de devolução.', 180, TIMESTAMP(@hoje - INTERVAL 3 DAY, '11:00:00'));
INSERT INTO agenda_unidades (unidade_id, produto_id, manutencao_id, criado_por, inicio, fim, status)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-200-002'), @c200, LAST_INSERT_ID(), @admin, TIMESTAMP(@hoje), GREATEST(NOW(), TIMESTAMP(@hoje) + INTERVAL 1 SECOND), 'MANUTENCAO');

INSERT INTO manutencoes (unidade_id, responsavel_usuario_id, criado_por, tipo, prioridade, status, data_prevista, iniciada_em, concluida_em,
                         responsavel_externo, descricao, custo, criado_em)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-300-005'), @rafael, @admin, 'Pintura', 'MEDIA', 'CONCLUIDA',
        TIMESTAMP(@hoje), TIMESTAMP(@hoje), NOW(), 'Pinturas Vale LTDA', 'Pintura externa e tratamento de ferrugem.', 950,
        TIMESTAMP(@hoje - INTERVAL 5 DAY, '09:00:00'));
INSERT INTO agenda_unidades (unidade_id, produto_id, manutencao_id, criado_por, inicio, fim, status)
VALUES ((SELECT id FROM unidades_fisicas WHERE codigo = 'CB-300-005'), @c300, LAST_INSERT_ID(), @admin, TIMESTAMP(@hoje), GREATEST(NOW(), TIMESTAMP(@hoje) + INTERVAL 1 SECOND), 'MANUTENCAO');

COMMIT;

-- Resumo (deve mostrar: 9 clientes, 3 produtos, 19 contêineres, 12 contratos, R$ 30.873,79 recebidos).
SELECT (SELECT COUNT(*) FROM pessoas WHERE tipo_cadastro IN ('CLIENTE', 'AMBOS'))     AS clientes,
       (SELECT COUNT(*) FROM produtos)                                                 AS produtos,
       (SELECT COUNT(*) FROM unidades_fisicas)                                         AS conteineres,
       (SELECT COUNT(*) FROM contratos)                                                AS contratos,
       (SELECT SUM(valor) FROM pagamentos WHERE estornado_em IS NULL)                  AS total_recebido;
