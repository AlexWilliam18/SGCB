-- =============================================================================
-- SGCB — Sistema de Gestão de Containers Barreto
-- Migração 001: criação do schema completo
--
-- Finalidade: cria todas as tabelas do sistema conforme o DER "Casa_Barretto_DER"
--             (documentação de tabelas da Wiki), com chaves primárias, chaves
--             estrangeiras, índices, unicidades e regras básicas de integridade.
-- Compatível: MySQL 8.0+ e MariaDB 10.4+ (XAMPP).
-- Destino no repositório: database/migrations/001_create_schema_casa_barretto.sql
--
-- ATENÇÃO — valores de ENUM: o DER não define as opções dos campos ENUM.
-- Os valores abaixo são uma proposta e estão marcados com "-- ENUM proposto".
-- O grupo deve confirmar na Wiki; se mudar, crie uma NOVA migração (002_...)
-- com ALTER TABLE, em vez de editar esta depois de aplicada.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS sgcb
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sgcb;

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. ACESSO: cargos, módulos, permissões e usuários
-- -----------------------------------------------------------------------------

-- Cargos (perfis) que agrupam permissões. Ex.: Administrador, Operador.
CREATE TABLE cargos (
  id          BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  codigo      VARCHAR(30)       NOT NULL,
  nome        VARCHAR(100)      NOT NULL,
  nivel       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  descricao   VARCHAR(255)      NULL,
  ativo       BOOLEAN           NOT NULL DEFAULT TRUE,
  CONSTRAINT pk_cargos PRIMARY KEY (id),
  CONSTRAINT uq_cargos_codigo UNIQUE (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Módulos do sistema (Produtos, Contratos, Financeiro...), base das permissões.
CREATE TABLE modulos (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo         VARCHAR(30)     NOT NULL,
  nome           VARCHAR(100)    NOT NULL,
  exclusivo_adm  BOOLEAN         NOT NULL DEFAULT FALSE,
  CONSTRAINT pk_modulos PRIMARY KEY (id),
  CONSTRAINT uq_modulos_codigo UNIQUE (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ações permitidas dentro de um módulo. Ex.: produtos + "cadastrar".
CREATE TABLE permissoes (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  modulo_id  BIGINT UNSIGNED NOT NULL,
  acao       VARCHAR(30)     NOT NULL,
  descricao  VARCHAR(255)    NULL,
  CONSTRAINT pk_permissoes PRIMARY KEY (id),
  CONSTRAINT uq_permissoes_modulo_acao UNIQUE (modulo_id, acao),
  CONSTRAINT fk_permissoes_modulo FOREIGN KEY (modulo_id)
    REFERENCES modulos (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Associativa N:N: quais permissões cada cargo possui.
CREATE TABLE cargo_permissoes (
  cargo_id      BIGINT UNSIGNED NOT NULL,
  permissao_id  BIGINT UNSIGNED NOT NULL,
  CONSTRAINT pk_cargo_permissoes PRIMARY KEY (cargo_id, permissao_id),
  CONSTRAINT fk_cargo_permissoes_cargo FOREIGN KEY (cargo_id)
    REFERENCES cargos (id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_cargo_permissoes_permissao FOREIGN KEY (permissao_id)
    REFERENCES permissoes (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuários que acessam o sistema. A senha é guardada só como hash (password_hash).
CREATE TABLE usuarios (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cargo_id            BIGINT UNSIGNED NOT NULL,
  nome                VARCHAR(150)    NOT NULL,
  email               VARCHAR(254)    NOT NULL,
  telefone            VARCHAR(25)     NULL,
  senha_hash          VARCHAR(255)    NOT NULL,
  ativo               BOOLEAN         NOT NULL DEFAULT TRUE,
  deve_alterar_senha  BOOLEAN         NOT NULL DEFAULT FALSE,
  ultimo_acesso       DATETIME        NULL,
  senha_alterada_em   DATETIME        NULL,
  criado_em           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_usuarios PRIMARY KEY (id),
  CONSTRAINT uq_usuarios_email UNIQUE (email),
  CONSTRAINT fk_usuarios_cargo FOREIGN KEY (cargo_id)
    REFERENCES cargos (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exceções de permissão por usuário (concede ou nega além do que o cargo dá).
CREATE TABLE usuario_permissoes (
  usuario_id    BIGINT UNSIGNED NOT NULL,
  permissao_id  BIGINT UNSIGNED NOT NULL,
  alterado_por  BIGINT UNSIGNED NULL,
  permitido     BOOLEAN         NOT NULL DEFAULT TRUE,
  alterado_em   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_usuario_permissoes PRIMARY KEY (usuario_id, permissao_id),
  CONSTRAINT fk_usuario_permissoes_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_usuario_permissoes_permissao FOREIGN KEY (permissao_id)
    REFERENCES permissoes (id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_usuario_permissoes_alterado_por FOREIGN KEY (alterado_por)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessões de login ("lembrar de mim"). Guarda só o hash do token.
CREATE TABLE sessoes_usuario (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id   BIGINT UNSIGNED NOT NULL,
  token_hash   CHAR(64)        NOT NULL,
  criado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_em    DATETIME        NOT NULL,
  revogado_em  DATETIME        NULL,
  persistente  BOOLEAN         NOT NULL DEFAULT FALSE,
  CONSTRAINT pk_sessoes_usuario PRIMARY KEY (id),
  CONSTRAINT uq_sessoes_usuario_token UNIQUE (token_hash),
  CONSTRAINT fk_sessoes_usuario_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_sessoes_usuario_expira (usuario_id, expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pedidos de recuperação de senha (token de uso único, com expiração).
CREATE TABLE recuperacoes_senha (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id    BIGINT UNSIGNED NOT NULL,
  token_hash    CHAR(64)        NOT NULL,
  criado_em     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_em     DATETIME        NOT NULL,
  utilizado_em  DATETIME        NULL,
  CONSTRAINT pk_recuperacoes_senha PRIMARY KEY (id),
  CONSTRAINT uq_recuperacoes_senha_token UNIQUE (token_hash),
  CONSTRAINT fk_recuperacoes_senha_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. ARQUIVOS e CADASTROS (pessoas, endereços, empresa)
-- -----------------------------------------------------------------------------

-- Metadados de arquivos enviados (fotos, logos, comprovantes, PDFs de contrato).
-- O arquivo em si fica em public/uploads/; aqui só o caminho e o hash.
CREATE TABLE arquivos (
  id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  enviado_por            BIGINT UNSIGNED NULL,
  nome_original          VARCHAR(255)    NOT NULL,
  caminho_armazenamento  VARCHAR(500)    NOT NULL,
  mime_type              VARCHAR(100)    NOT NULL,
  tamanho_bytes          BIGINT UNSIGNED NOT NULL,
  sha256                 CHAR(64)        NOT NULL,
  criado_em              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_arquivos PRIMARY KEY (id),
  CONSTRAINT fk_arquivos_enviado_por FOREIGN KEY (enviado_por)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_arquivos_sha256 (sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Condições de pagamento reutilizáveis. Ex.: "30 dias", "3x de 30 em 30".
CREATE TABLE condicoes_pagamento (
  id                        BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  nome                      VARCHAR(100)      NOT NULL,
  dias_primeiro_vencimento  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  intervalo_dias            SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  numero_parcelas_padrao    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  ativo                     BOOLEAN           NOT NULL DEFAULT TRUE,
  CONSTRAINT pk_condicoes_pagamento PRIMARY KEY (id),
  CONSTRAINT uq_condicoes_pagamento_nome UNIQUE (nome),
  CONSTRAINT ck_condicoes_pagamento_parcelas CHECK (numero_parcelas_padrao >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pessoas físicas ou jurídicas: clientes que locam e fornecedores de produtos.
CREATE TABLE pessoas (
  id                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  condicao_pagamento_id       BIGINT UNSIGNED NULL,
  foto_arquivo_id             BIGINT UNSIGNED NULL,
  criado_por                  BIGINT UNSIGNED NULL,
  tipo_cadastro               ENUM('CLIENTE','FORNECEDOR','AMBOS') NOT NULL DEFAULT 'CLIENTE', -- ENUM proposto
  tipo_pessoa                 ENUM('FISICA','JURIDICA')            NOT NULL,                    -- ENUM proposto
  nome_razao_social           VARCHAR(200)    NOT NULL,
  nome_fantasia               VARCHAR(200)    NULL,
  cpf_cnpj                    VARCHAR(14)     NOT NULL,   -- só dígitos: 11 (CPF) ou 14 (CNPJ)
  email                       VARCHAR(254)    NULL,
  telefone                    VARCHAR(25)     NULL,
  data_nascimento             DATE            NULL,
  nacionalidade               VARCHAR(80)     NULL,
  profissao                   VARCHAR(100)    NULL,
  rg                          VARCHAR(30)     NULL,
  inscricao_estadual          VARCHAR(30)     NULL,
  representante_legal         VARCHAR(150)    NULL,
  cpf_representante           VARCHAR(11)     NULL,
  limite_credito              DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  permite_ultrapassar_limite  BOOLEAN         NOT NULL DEFAULT FALSE,
  ativo                       BOOLEAN         NOT NULL DEFAULT TRUE,
  criado_em                   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em               DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_pessoas PRIMARY KEY (id),
  CONSTRAINT uq_pessoas_cpf_cnpj UNIQUE (cpf_cnpj),
  CONSTRAINT ck_pessoas_documento CHECK (
    (tipo_pessoa = 'FISICA'   AND CHAR_LENGTH(cpf_cnpj) = 11) OR
    (tipo_pessoa = 'JURIDICA' AND CHAR_LENGTH(cpf_cnpj) = 14)
  ),
  CONSTRAINT ck_pessoas_limite CHECK (limite_credito >= 0),
  CONSTRAINT fk_pessoas_condicao_pagamento FOREIGN KEY (condicao_pagamento_id)
    REFERENCES condicoes_pagamento (id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_pessoas_foto FOREIGN KEY (foto_arquivo_id)
    REFERENCES arquivos (id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_pessoas_criado_por FOREIGN KEY (criado_por)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_pessoas_nome (nome_razao_social),
  INDEX idx_pessoas_tipo_cadastro (tipo_cadastro, ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Associativa N:N: documentos anexados ao cadastro da pessoa (RG, contrato social...).
CREATE TABLE pessoa_anexos (
  pessoa_id   BIGINT UNSIGNED NOT NULL,
  arquivo_id  BIGINT UNSIGNED NOT NULL,
  descricao   VARCHAR(255)    NULL,
  CONSTRAINT pk_pessoa_anexos PRIMARY KEY (pessoa_id, arquivo_id),
  CONSTRAINT fk_pessoa_anexos_pessoa FOREIGN KEY (pessoa_id)
    REFERENCES pessoas (id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_pessoa_anexos_arquivo FOREIGN KEY (arquivo_id)
    REFERENCES arquivos (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Endereços de uma pessoa. Só UM endereço principal ativo por pessoa:
-- principal_pessoa_id é calculado (= pessoa_id quando principal e ativo, senão NULL)
-- e tem índice UNIQUE. Como NULL não se repete no UNIQUE, os não principais passam.
CREATE TABLE enderecos_pessoa (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pessoa_id            BIGINT UNSIGNED NOT NULL,
  tipo                 ENUM('RESIDENCIAL','COMERCIAL','ENTREGA','COBRANCA') NOT NULL DEFAULT 'ENTREGA', -- ENUM proposto
  descricao            VARCHAR(100)    NULL,
  principal            BOOLEAN         NOT NULL DEFAULT FALSE,
  cep                  VARCHAR(8)      NOT NULL,
  rua                  VARCHAR(200)    NOT NULL,
  numero               VARCHAR(20)     NOT NULL,
  bairro               VARCHAR(100)    NOT NULL,
  cidade               VARCHAR(100)    NOT NULL,
  uf                   CHAR(2)         NOT NULL,
  complemento          VARCHAR(150)    NULL,
  ativo                BOOLEAN         NOT NULL DEFAULT TRUE,
  principal_pessoa_id  BIGINT UNSIGNED
    GENERATED ALWAYS AS (IF(principal = 1 AND ativo = 1, pessoa_id, NULL)) STORED,
  CONSTRAINT pk_enderecos_pessoa PRIMARY KEY (id),
  CONSTRAINT uq_enderecos_pessoa_principal UNIQUE (principal_pessoa_id),
  CONSTRAINT fk_enderecos_pessoa_pessoa FOREIGN KEY (pessoa_id)
    REFERENCES pessoas (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  INDEX idx_enderecos_pessoa_cidade (cidade, uf)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dados da própria Casas Barreto (locadora), usados no cabeçalho dos contratos.
CREATE TABLE empresas (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  logo_arquivo_id  BIGINT UNSIGNED NULL,
  atualizado_por   BIGINT UNSIGNED NULL,
  razao_social     VARCHAR(200)    NOT NULL,
  nome_fantasia    VARCHAR(200)    NULL,
  cnpj             VARCHAR(14)     NOT NULL,
  email            VARCHAR(254)    NULL,
  telefone         VARCHAR(25)     NULL,
  cep              VARCHAR(8)      NULL,
  rua              VARCHAR(200)    NULL,
  numero           VARCHAR(20)     NULL,
  bairro           VARCHAR(100)    NULL,
  cidade           VARCHAR(100)    NULL,
  uf               CHAR(2)         NULL,
  complemento      VARCHAR(150)    NULL,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_empresas PRIMARY KEY (id),
  CONSTRAINT uq_empresas_cnpj UNIQUE (cnpj),
  CONSTRAINT fk_empresas_logo FOREIGN KEY (logo_arquivo_id)
    REFERENCES arquivos (id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_empresas_atualizado_por FOREIGN KEY (atualizado_por)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações de contrato da empresa (1:1 com empresas).
CREATE TABLE preferencias_contrato (
  empresa_id               BIGINT UNSIGNED  NOT NULL,
  numeracao_automatica     BOOLEAN          NOT NULL DEFAULT TRUE,
  prefixo_contrato         VARCHAR(20)      NULL,
  proximo_numero_contrato  BIGINT UNSIGNED  NOT NULL DEFAULT 1,
  prefixo_locacao          VARCHAR(20)      NULL,
  proximo_numero_locacao   BIGINT UNSIGNED  NOT NULL DEFAULT 1,
  condicoes_gerais         TEXT             NULL,
  regra_vencimento         ENUM('DIA_FIXO','DATA_INICIO')   NOT NULL DEFAULT 'DIA_FIXO', -- ENUM proposto
  dias_alerta_vencimento   SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  contagem_diarias         ENUM('CORRIDOS','UTEIS')         NOT NULL DEFAULT 'CORRIDOS', -- ENUM proposto
  moeda                    CHAR(3)          NOT NULL DEFAULT 'BRL',
  casas_decimais           TINYINT UNSIGNED NOT NULL DEFAULT 2,
  formato_pdf              ENUM('A4','CARTA')               NOT NULL DEFAULT 'A4',       -- ENUM proposto
  exibir_produtos          BOOLEAN          NOT NULL DEFAULT TRUE,
  exibir_diarias           BOOLEAN          NOT NULL DEFAULT TRUE,
  exibir_subtotais         BOOLEAN          NOT NULL DEFAULT TRUE,
  exibir_total             BOOLEAN          NOT NULL DEFAULT TRUE,
  CONSTRAINT pk_preferencias_contrato PRIMARY KEY (empresa_id),
  CONSTRAINT ck_preferencias_casas_decimais CHECK (casas_decimais <= 4),
  CONSTRAINT fk_preferencias_contrato_empresa FOREIGN KEY (empresa_id)
    REFERENCES empresas (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. ESTOQUE: categorias, produtos, preços, unidades físicas e manutenções
-- -----------------------------------------------------------------------------

-- Categorias de produto. Ex.: Container Escritório, Container Almoxarifado.
CREATE TABLE categorias (
  id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome   VARCHAR(100)    NOT NULL,
  ativo  BOOLEAN         NOT NULL DEFAULT TRUE,
  CONSTRAINT pk_categorias PRIMARY KEY (id),
  CONSTRAINT uq_categorias_nome UNIQUE (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Produto = MODELO locável (ex.: "Container 20 pés escritório").
-- As peças reais desse modelo ficam em unidades_fisicas.
CREATE TABLE produtos (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria_id     BIGINT UNSIGNED NOT NULL,
  fornecedor_id    BIGINT UNSIGNED NULL,
  criado_por       BIGINT UNSIGNED NULL,
  codigo           VARCHAR(40)     NOT NULL,
  nome             VARCHAR(200)    NOT NULL,
  descricao        VARCHAR(500)    NULL,
  valor_total_bem  DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  limite_inicial   INT UNSIGNED    NOT NULL DEFAULT 0,
  limite_maximo    INT UNSIGNED    NOT NULL DEFAULT 0,
  maximo_estoque   INT UNSIGNED    NOT NULL DEFAULT 0,
  ativo            BOOLEAN         NOT NULL DEFAULT TRUE,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_produtos PRIMARY KEY (id),
  CONSTRAINT uq_produtos_codigo UNIQUE (codigo),
  CONSTRAINT ck_produtos_valor CHECK (valor_total_bem >= 0),
  CONSTRAINT ck_produtos_limites CHECK (limite_maximo >= limite_inicial),
  CONSTRAINT fk_produtos_categoria FOREIGN KEY (categoria_id)
    REFERENCES categorias (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_produtos_fornecedor FOREIGN KEY (fornecedor_id)
    REFERENCES pessoas (id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_produtos_criado_por FOREIGN KEY (criado_por)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_produtos_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de preços do produto por periodicidade (diária, mensal...).
CREATE TABLE precos_produto (
  produto_id    BIGINT UNSIGNED NOT NULL,
  periodicidade ENUM('DIARIA','SEMANAL','QUINZENAL','MENSAL') NOT NULL, -- ENUM proposto
  valor         DECIMAL(14,2)   NOT NULL,
  CONSTRAINT pk_precos_produto PRIMARY KEY (produto_id, periodicidade),
  CONSTRAINT ck_precos_produto_valor CHECK (valor >= 0),
  CONSTRAINT fk_precos_produto_produto FOREIGN KEY (produto_id)
    REFERENCES produtos (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Unidade física = cada container real, com código próprio (ex.: CB-020-001).
CREATE TABLE unidades_fisicas (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  produto_id          BIGINT UNSIGNED NOT NULL,
  codigo              VARCHAR(50)     NOT NULL,
  estado_operacional  ENUM('DISPONIVEL','LOCADA','MANUTENCAO','INATIVA') NOT NULL DEFAULT 'DISPONIVEL', -- ENUM proposto
  localidade          VARCHAR(200)    NULL,
  ultima_vistoria_em  DATETIME        NULL,
  observacoes         TEXT            NULL,
  criado_em           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_unidades_fisicas PRIMARY KEY (id),
  CONSTRAINT uq_unidades_fisicas_codigo UNIQUE (codigo),
  CONSTRAINT fk_unidades_fisicas_produto FOREIGN KEY (produto_id)
    REFERENCES produtos (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_unidades_fisicas_estado (produto_id, estado_operacional)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Manutenções de uma unidade física (preventiva, corretiva...).
CREATE TABLE manutencoes (
  id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  unidade_id              BIGINT UNSIGNED NOT NULL,
  responsavel_usuario_id  BIGINT UNSIGNED NULL,
  criado_por              BIGINT UNSIGNED NULL,
  tipo                    VARCHAR(100)    NOT NULL,
  prioridade              ENUM('BAIXA','MEDIA','ALTA','URGENTE')                     NOT NULL DEFAULT 'MEDIA',    -- ENUM proposto
  status                  ENUM('AGENDADA','EM_ANDAMENTO','CONCLUIDA','CANCELADA')    NOT NULL DEFAULT 'AGENDADA', -- ENUM proposto
  data_prevista           DATETIME        NULL,
  iniciada_em             DATETIME        NULL,
  concluida_em            DATETIME        NULL,
  responsavel_externo     VARCHAR(150)    NULL,
  descricao               TEXT            NULL,
  custo                   DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  criado_em               DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_manutencoes PRIMARY KEY (id),
  CONSTRAINT ck_manutencoes_custo CHECK (custo >= 0),
  CONSTRAINT ck_manutencoes_datas CHECK (concluida_em IS NULL OR iniciada_em IS NULL OR concluida_em >= iniciada_em),
  CONSTRAINT fk_manutencoes_unidade FOREIGN KEY (unidade_id)
    REFERENCES unidades_fisicas (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_manutencoes_responsavel FOREIGN KEY (responsavel_usuario_id)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_manutencoes_criado_por FOREIGN KEY (criado_por)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_manutencoes_status (status, data_prevista)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. LOCAÇÃO: contratos, itens, agenda das unidades e documentos
-- -----------------------------------------------------------------------------

-- Contrato de locação. Os campos *_snapshot guardam uma cópia dos dados no dia
-- da assinatura, para o contrato não mudar se o cadastro for editado depois.
CREATE TABLE contratos (
  id                         BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  empresa_id                 BIGINT UNSIGNED   NOT NULL,
  cliente_id                 BIGINT UNSIGNED   NOT NULL,
  endereco_entrega_id        BIGINT UNSIGNED   NULL,
  condicao_pagamento_id      BIGINT UNSIGNED   NULL,
  criado_por                 BIGINT UNSIGNED   NULL,
  numero_locacao             VARCHAR(50)       NOT NULL,
  numero_contrato            VARCHAR(50)       NOT NULL,
  data_emissao               DATE              NOT NULL,
  prazo                      ENUM('DETERMINADO','INDETERMINADO')                    NOT NULL DEFAULT 'DETERMINADO', -- ENUM proposto
  data_inicio                DATE              NOT NULL,
  data_fim                   DATE              NULL,      -- NULL quando o prazo é indeterminado
  periodicidade              ENUM('DIARIA','SEMANAL','QUINZENAL','MENSAL')          NOT NULL DEFAULT 'MENSAL',      -- ENUM proposto
  status                     ENUM('RASCUNHO','ATIVO','FINALIZADO','CANCELADO')      NOT NULL DEFAULT 'RASCUNHO',    -- ENUM proposto
  dia_vencimento             TINYINT UNSIGNED  NULL,
  primeiro_vencimento        DATE              NULL,
  numero_parcelas            SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  frete_deslocamento         DECIMAL(14,2)     NOT NULL DEFAULT 0.00,
  outros_valores             DECIMAL(14,2)     NOT NULL DEFAULT 0.00,
  caucao                     DECIMAL(14,2)     NOT NULL DEFAULT 0.00,
  observacoes                TEXT              NULL,
  condicoes_gerais_snapshot  TEXT              NULL,
  dados_cliente_snapshot     JSON              NULL,
  dados_empresa_snapshot     JSON              NULL,
  dados_entrega_snapshot     JSON              NULL,
  criado_em                  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finalizado_em              DATETIME          NULL,
  cancelado_em               DATETIME          NULL,
  motivo_cancelamento        VARCHAR(500)      NULL,
  CONSTRAINT pk_contratos PRIMARY KEY (id),
  CONSTRAINT uq_contratos_numero_contrato UNIQUE (numero_contrato),
  CONSTRAINT uq_contratos_numero_locacao UNIQUE (numero_locacao),
  CONSTRAINT ck_contratos_datas CHECK (data_fim IS NULL OR data_fim >= data_inicio),
  CONSTRAINT ck_contratos_dia_vencimento CHECK (dia_vencimento IS NULL OR dia_vencimento BETWEEN 1 AND 31),
  CONSTRAINT ck_contratos_valores CHECK (frete_deslocamento >= 0 AND outros_valores >= 0 AND caucao >= 0),
  CONSTRAINT ck_contratos_parcelas CHECK (numero_parcelas >= 1),
  CONSTRAINT fk_contratos_empresa FOREIGN KEY (empresa_id)
    REFERENCES empresas (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_contratos_cliente FOREIGN KEY (cliente_id)
    REFERENCES pessoas (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_contratos_endereco_entrega FOREIGN KEY (endereco_entrega_id)
    REFERENCES enderecos_pessoa (id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_contratos_condicao_pagamento FOREIGN KEY (condicao_pagamento_id)
    REFERENCES condicoes_pagamento (id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_contratos_criado_por FOREIGN KEY (criado_por)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_contratos_cliente_status (cliente_id, status),
  INDEX idx_contratos_periodo (data_inicio, data_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Itens do contrato: quais produtos, quantidade e cálculo do valor.
CREATE TABLE contrato_itens (
  id                           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contrato_id                  BIGINT UNSIGNED NOT NULL,
  produto_id                   BIGINT UNSIGNED NOT NULL,
  descricao_snapshot           VARCHAR(500)    NOT NULL,
  valor_unitario_bem_snapshot  DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  quantidade                   INT UNSIGNED    NOT NULL DEFAULT 1,
  periodicidade                ENUM('DIARIA','SEMANAL','QUINZENAL','MENSAL') NOT NULL, -- ENUM proposto
  numero_periodos              DECIMAL(10,4)   NOT NULL DEFAULT 1.0000,
  valor_unitario               DECIMAL(14,2)   NOT NULL,
  desconto                     DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  outros                       DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  subtotal                     DECIMAL(14,2)   NOT NULL,
  CONSTRAINT pk_contrato_itens PRIMARY KEY (id),
  CONSTRAINT ck_contrato_itens_quantidade CHECK (quantidade >= 1),
  CONSTRAINT ck_contrato_itens_valores CHECK (valor_unitario >= 0 AND desconto >= 0 AND outros >= 0 AND subtotal >= 0),
  CONSTRAINT fk_contrato_itens_contrato FOREIGN KEY (contrato_id)
    REFERENCES contratos (id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_contrato_itens_produto FOREIGN KEY (produto_id)
    REFERENCES produtos (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agenda de ocupação das unidades físicas (locação ou manutenção).
-- É a tabela usada para saber se um container está livre num período.
CREATE TABLE agenda_unidades (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  unidade_id        BIGINT UNSIGNED NULL,      -- pode ficar NULL até a unidade ser escolhida
  produto_id        BIGINT UNSIGNED NOT NULL,
  contrato_item_id  BIGINT UNSIGNED NULL,
  manutencao_id     BIGINT UNSIGNED NULL,
  criado_por        BIGINT UNSIGNED NULL,
  inicio            DATETIME        NOT NULL,
  fim               DATETIME        NOT NULL,
  status            ENUM('RESERVADA','EM_USO','DEVOLVIDA','MANUTENCAO','CANCELADA') NOT NULL DEFAULT 'RESERVADA', -- ENUM proposto
  devolvida_em      DATETIME        NULL,
  CONSTRAINT pk_agenda_unidades PRIMARY KEY (id),
  CONSTRAINT ck_agenda_unidades_periodo CHECK (fim > inicio),
  CONSTRAINT fk_agenda_unidades_unidade FOREIGN KEY (unidade_id)
    REFERENCES unidades_fisicas (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_agenda_unidades_produto FOREIGN KEY (produto_id)
    REFERENCES produtos (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_agenda_unidades_contrato_item FOREIGN KEY (contrato_item_id)
    REFERENCES contrato_itens (id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_agenda_unidades_manutencao FOREIGN KEY (manutencao_id)
    REFERENCES manutencoes (id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_agenda_unidades_criado_por FOREIGN KEY (criado_por)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_agenda_unidades_periodo (unidade_id, inicio, fim),
  INDEX idx_agenda_unidades_produto_periodo (produto_id, inicio, fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documentos gerados para o contrato (PDF do contrato, aditivos...), com versão.
CREATE TABLE contrato_documentos (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contrato_id  BIGINT UNSIGNED NOT NULL,
  arquivo_id   BIGINT UNSIGNED NOT NULL,
  gerado_por   BIGINT UNSIGNED NULL,
  versao       INT UNSIGNED    NOT NULL DEFAULT 1,
  tipo         ENUM('CONTRATO','ADITIVO','RECIBO','TERMO_DEVOLUCAO') NOT NULL DEFAULT 'CONTRATO', -- ENUM proposto
  gerado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_contrato_documentos PRIMARY KEY (id),
  CONSTRAINT uq_contrato_documentos_versao UNIQUE (contrato_id, tipo, versao),
  CONSTRAINT fk_contrato_documentos_contrato FOREIGN KEY (contrato_id)
    REFERENCES contratos (id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_contrato_documentos_arquivo FOREIGN KEY (arquivo_id)
    REFERENCES arquivos (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_contrato_documentos_gerado_por FOREIGN KEY (gerado_por)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. FINANCEIRO: parcelas e pagamentos
-- -----------------------------------------------------------------------------

-- Parcelas a receber de cada contrato.
-- Valor devido = valor_original + acrescimo - desconto.
CREATE TABLE parcelas (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contrato_id     BIGINT UNSIGNED NOT NULL,
  numero          INT UNSIGNED    NOT NULL,
  competencia     DATE            NOT NULL,
  vencimento      DATE            NOT NULL,
  valor_original  DECIMAL(14,2)   NOT NULL,
  acrescimo       DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  desconto        DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  cancelada       BOOLEAN         NOT NULL DEFAULT FALSE,
  observacoes     VARCHAR(500)    NULL,
  CONSTRAINT pk_parcelas PRIMARY KEY (id),
  CONSTRAINT uq_parcelas_contrato_numero UNIQUE (contrato_id, numero),
  CONSTRAINT ck_parcelas_valores CHECK (valor_original >= 0 AND acrescimo >= 0 AND desconto >= 0),
  CONSTRAINT fk_parcelas_contrato FOREIGN KEY (contrato_id)
    REFERENCES contratos (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_parcelas_vencimento (vencimento, cancelada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pagamentos recebidos. Um estorno não apaga a linha: preenche estornado_em.
CREATE TABLE pagamentos (
  id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  parcela_id              BIGINT UNSIGNED NOT NULL,
  comprovante_arquivo_id  BIGINT UNSIGNED NULL,
  registrado_por          BIGINT UNSIGNED NULL,
  estornado_por           BIGINT UNSIGNED NULL,
  valor                   DECIMAL(14,2)   NOT NULL,
  pago_em                 DATETIME        NOT NULL,
  forma                   ENUM('DINHEIRO','PIX','CARTAO_CREDITO','CARTAO_DEBITO','BOLETO','TRANSFERENCIA') NOT NULL, -- ENUM proposto
  estornado_em            DATETIME        NULL,
  motivo_estorno          VARCHAR(500)    NULL,
  criado_em               DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_pagamentos PRIMARY KEY (id),
  CONSTRAINT ck_pagamentos_valor CHECK (valor > 0),
  CONSTRAINT fk_pagamentos_parcela FOREIGN KEY (parcela_id)
    REFERENCES parcelas (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_pagamentos_comprovante FOREIGN KEY (comprovante_arquivo_id)
    REFERENCES arquivos (id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_pagamentos_registrado_por FOREIGN KEY (registrado_por)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_pagamentos_estornado_por FOREIGN KEY (estornado_por)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_pagamentos_parcela (parcela_id, estornado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. AUDITORIA
-- -----------------------------------------------------------------------------

-- Registro de quem fez o quê: entidade, id do registro, ação e detalhes em JSON.
CREATE TABLE auditoria (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id   BIGINT UNSIGNED NULL,
  entidade     VARCHAR(80)     NOT NULL,
  registro_id  BIGINT UNSIGNED NULL,
  acao         VARCHAR(40)     NOT NULL,
  detalhes     JSON            NULL,
  criado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_auditoria PRIMARY KEY (id),
  CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_auditoria_entidade (entidade, registro_id),
  INDEX idx_auditoria_usuario_data (usuario_id, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
