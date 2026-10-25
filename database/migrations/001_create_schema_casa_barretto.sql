-- =============================================================================
-- SGCB - Migração 001: criação do banco e das 27 tabelas do DER "Casa_Barretto_DER" (Wiki).
--
-- Os tipos de dados seguem a documentação do DER (BIGINT UNSIGNED, VARCHAR, DECIMAL, DATETIME, ENUM...).
--
-- Atenção: o DER não define as opções dos campos ENUM.
-- Os valores usados são uma proposta, marcados com "ENUM proposto".
--
-- Ordem: as tabelas estão criadas de forma que toda tabela referenciada por
-- uma FOREIGN KEY já exista antes (ex.: cargos antes de usuarios).
-- =============================================================================

-- Cria o banco (se ainda não existir) e passa a usá-lo.
CREATE DATABASE IF NOT EXISTS sgcb;

USE sgcb;

-- =============================================================================
-- 1. ACESSO: cargos, módulos, permissões e usuários
-- =============================================================================

-- Cargos (perfis de acesso). Ex.: Administrador, Operador.
CREATE TABLE cargos (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo     VARCHAR(30) UNIQUE,      -- código curto do cargo, não pode repetir (ex.: ADM)
  nome       VARCHAR(100),
  nivel      SMALLINT UNSIGNED,       -- nível hierárquico do cargo
  descricao  VARCHAR(255),
  ativo      BOOLEAN
);

-- Módulos do sistema (Produtos, Contratos, Financeiro...). Base das permissões.
CREATE TABLE modulos (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo         VARCHAR(30) UNIQUE,  -- código do módulo, não pode repetir
  nome           VARCHAR(100),
  exclusivo_adm  BOOLEAN              -- TRUE = só administradores acessam
);

-- Permissões: cada ação permitida dentro de um módulo (ex.: produtos + "cadastrar").
-- Relacionamento 1:N: um módulo possui várias permissões.
CREATE TABLE permissoes (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  modulo_id  BIGINT UNSIGNED,
  acao       VARCHAR(30),
  descricao  VARCHAR(255),
  FOREIGN KEY (modulo_id) REFERENCES modulos(id)
);

-- Tabela intermediária (N:N) entre cargos e permissões:
-- um cargo tem várias permissões e uma permissão pode estar em vários cargos.
-- A chave primária é a combinação dos dois campos.
CREATE TABLE cargo_permissoes (
  cargo_id      BIGINT UNSIGNED,
  permissao_id  BIGINT UNSIGNED,
  PRIMARY KEY (cargo_id, permissao_id),
  FOREIGN KEY (cargo_id) REFERENCES cargos(id),
  FOREIGN KEY (permissao_id) REFERENCES permissoes(id)
);

-- Usuários que acessam o sistema.
-- A senha nunca é guardada em texto: o PHP grava apenas o hash (password_hash).
CREATE TABLE usuarios (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cargo_id            BIGINT UNSIGNED,
  nome                VARCHAR(150),
  email               VARCHAR(254) UNIQUE,   -- login do usuário, não pode repetir
  telefone            VARCHAR(25),
  senha_hash          VARCHAR(255),
  ativo               BOOLEAN,
  deve_alterar_senha  BOOLEAN,               -- TRUE = obriga trocar a senha no próximo acesso
  ultimo_acesso       DATETIME,
  senha_alterada_em   DATETIME,
  criado_em           DATETIME,
  atualizado_em       DATETIME,
  FOREIGN KEY (cargo_id) REFERENCES cargos(id)
);

-- Tabela intermediária (N:N) entre usuários e permissões:
-- exceções individuais (concede ou nega uma permissão além do que o cargo dá).
CREATE TABLE usuario_permissoes (
  usuario_id    BIGINT UNSIGNED,
  permissao_id  BIGINT UNSIGNED,
  alterado_por  BIGINT UNSIGNED,   -- usuário que fez a alteração
  permitido     BOOLEAN,           -- TRUE = concedida, FALSE = negada
  alterado_em   DATETIME,
  PRIMARY KEY (usuario_id, permissao_id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (permissao_id) REFERENCES permissoes(id),
  FOREIGN KEY (alterado_por) REFERENCES usuarios(id)
);

-- Sessões de login (ex.: "lembrar de mim"). Guarda só o hash do token.
CREATE TABLE sessoes_usuario (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id   BIGINT UNSIGNED,
  token_hash   CHAR(64) UNIQUE,   -- hash do token da sessão, não pode repetir
  criado_em    DATETIME,
  expira_em    DATETIME,
  revogado_em  DATETIME,          -- preenchido quando a sessão é encerrada
  persistente  BOOLEAN,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Pedidos de recuperação de senha (token de uso único, com prazo de expiração).
CREATE TABLE recuperacoes_senha (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id    BIGINT UNSIGNED,
  token_hash    CHAR(64) UNIQUE,
  criado_em     DATETIME,
  expira_em     DATETIME,
  utilizado_em  DATETIME,          -- preenchido quando o token é usado
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);


-- =============================================================================
-- 2. ARQUIVOS e CADASTROS: arquivos, pessoas, endereços e empresa
-- =============================================================================

-- Informações dos arquivos enviados (fotos, logos, comprovantes, PDFs).
-- O arquivo em si fica na pasta public/uploads/; aqui fica só o caminho.
CREATE TABLE arquivos (
  id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  enviado_por            BIGINT UNSIGNED,   -- usuário que enviou o arquivo
  nome_original          VARCHAR(255),
  caminho_armazenamento  VARCHAR(500),
  mime_type              VARCHAR(100),      -- tipo do arquivo (ex.: image/png)
  tamanho_bytes          BIGINT UNSIGNED,
  sha256                 CHAR(64),          -- hash para conferir a integridade
  criado_em              DATETIME,
  FOREIGN KEY (enviado_por) REFERENCES usuarios(id)
);

-- Condições de pagamento reutilizáveis. Ex.: "30 dias", "3x de 30 em 30 dias".
CREATE TABLE condicoes_pagamento (
  id                        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome                      VARCHAR(100) UNIQUE,
  dias_primeiro_vencimento  SMALLINT UNSIGNED,
  intervalo_dias            SMALLINT UNSIGNED,
  numero_parcelas_padrao    SMALLINT UNSIGNED,
  ativo                     BOOLEAN,
  CHECK (numero_parcelas_padrao >= 1)     -- precisa ter pelo menos 1 parcela
);

-- Pessoas físicas ou jurídicas: clientes que alugam e fornecedores de produtos.
CREATE TABLE pessoas (
  id                          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  condicao_pagamento_id       BIGINT UNSIGNED,
  foto_arquivo_id             BIGINT UNSIGNED,
  criado_por                  BIGINT UNSIGNED,
  tipo_cadastro               ENUM('CLIENTE','FORNECEDOR','AMBOS'),   -- ENUM proposto
  tipo_pessoa                 ENUM('FISICA','JURIDICA'),              -- ENUM proposto
  nome_razao_social           VARCHAR(200),
  nome_fantasia               VARCHAR(200),
  cpf_cnpj                    VARCHAR(14) UNIQUE,   -- só números; não pode repetir
  email                       VARCHAR(254),
  telefone                    VARCHAR(25),
  data_nascimento             DATE,
  nacionalidade               VARCHAR(80),
  profissao                   VARCHAR(100),
  rg                          VARCHAR(30),
  inscricao_estadual          VARCHAR(30),
  representante_legal         VARCHAR(150),
  cpf_representante           VARCHAR(11),
  limite_credito              DECIMAL(14,2),
  permite_ultrapassar_limite  BOOLEAN,
  ativo                       BOOLEAN,
  criado_em                   DATETIME,
  atualizado_em               DATETIME,
  CHECK (limite_credito >= 0),                    -- limite não pode ser negativo
  FOREIGN KEY (condicao_pagamento_id) REFERENCES condicoes_pagamento(id),
  FOREIGN KEY (foto_arquivo_id) REFERENCES arquivos(id),
  FOREIGN KEY (criado_por) REFERENCES usuarios(id)
);

-- Tabela intermediária (N:N) entre pessoas e arquivos:
-- documentos anexados ao cadastro (RG, contrato social...).
CREATE TABLE pessoa_anexos (
  pessoa_id   BIGINT UNSIGNED,
  arquivo_id  BIGINT UNSIGNED,
  descricao   VARCHAR(255),
  PRIMARY KEY (pessoa_id, arquivo_id),
  FOREIGN KEY (pessoa_id) REFERENCES pessoas(id),
  FOREIGN KEY (arquivo_id) REFERENCES arquivos(id)
);

-- Endereços de uma pessoa (1:N: uma pessoa pode ter vários endereços).
-- principal_pessoa_id garante só UM endereço principal por pessoa:
-- o sistema grava nele o id da pessoa quando o endereço é o principal e deixa
-- vazio (NULL) nos demais. Como o campo é UNIQUE, não aceita dois principais.
CREATE TABLE enderecos_pessoa (
  id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pessoa_id            BIGINT UNSIGNED,
  tipo                 ENUM('RESIDENCIAL','COMERCIAL','ENTREGA','COBRANCA'),   -- ENUM proposto
  descricao            VARCHAR(100),
  principal            BOOLEAN,
  cep                  VARCHAR(8),
  rua                  VARCHAR(200),
  numero               VARCHAR(20),
  bairro               VARCHAR(100),
  cidade               VARCHAR(100),
  uf                   CHAR(2),
  complemento          VARCHAR(150),
  ativo                BOOLEAN,
  principal_pessoa_id  BIGINT UNSIGNED UNIQUE,
  FOREIGN KEY (pessoa_id) REFERENCES pessoas(id)
);

-- Dados da própria Casas Barreto (locadora), usados no cabeçalho dos contratos.
CREATE TABLE empresas (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  logo_arquivo_id  BIGINT UNSIGNED,
  atualizado_por   BIGINT UNSIGNED,
  razao_social     VARCHAR(200),
  nome_fantasia    VARCHAR(200),
  cnpj             VARCHAR(14) UNIQUE,
  email            VARCHAR(254),
  telefone         VARCHAR(25),
  cep              VARCHAR(8),
  rua              VARCHAR(200),
  numero           VARCHAR(20),
  bairro           VARCHAR(100),
  cidade           VARCHAR(100),
  uf               CHAR(2),
  complemento      VARCHAR(150),
  atualizado_em    DATETIME,
  FOREIGN KEY (logo_arquivo_id) REFERENCES arquivos(id),
  FOREIGN KEY (atualizado_por) REFERENCES usuarios(id)
);

-- Configurações dos contratos da empresa.
-- Relacionamento 1:1 com empresas: empresa_id é ao mesmo tempo PK e FK.
CREATE TABLE preferencias_contrato (
  empresa_id               BIGINT UNSIGNED PRIMARY KEY,
  numeracao_automatica     BOOLEAN,
  prefixo_contrato         VARCHAR(20),
  proximo_numero_contrato  BIGINT UNSIGNED,
  prefixo_locacao          VARCHAR(20),
  proximo_numero_locacao   BIGINT UNSIGNED,
  condicoes_gerais         TEXT,              -- texto padrão das condições do contrato
  regra_vencimento         ENUM('DIA_FIXO','DATA_INICIO'),   -- ENUM proposto
  dias_alerta_vencimento   SMALLINT UNSIGNED,
  contagem_diarias         ENUM('CORRIDOS','UTEIS'),         -- ENUM proposto
  moeda                    CHAR(3),           -- ex.: BRL
  casas_decimais           TINYINT UNSIGNED,
  formato_pdf              ENUM('A4','CARTA'),               -- ENUM proposto
  exibir_produtos          BOOLEAN,
  exibir_diarias           BOOLEAN,
  exibir_subtotais         BOOLEAN,
  exibir_total             BOOLEAN,
  CHECK (casas_decimais <= 4),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id)
);


-- =============================================================================
-- 3. ESTOQUE: categorias, produtos, preços, unidades físicas e manutenções
-- =============================================================================

-- Categorias de produto. Ex.: Container Escritório, Container Almoxarifado.
CREATE TABLE categorias (
  id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome   VARCHAR(100) UNIQUE,
  ativo  BOOLEAN
);

-- Produto = MODELO que pode ser alugado (ex.: "Container 20 pés escritório").
-- Cada container real desse modelo fica na tabela unidades_fisicas.
CREATE TABLE produtos (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  categoria_id     BIGINT UNSIGNED,
  fornecedor_id    BIGINT UNSIGNED,         -- pessoa cadastrada como fornecedora
  criado_por       BIGINT UNSIGNED,
  codigo           VARCHAR(40) UNIQUE,
  nome             VARCHAR(200),
  descricao        VARCHAR(500),
  valor_total_bem  DECIMAL(14,2),           -- valor estimado do bem
  limite_inicial   INT UNSIGNED,
  limite_maximo    INT UNSIGNED,
  maximo_estoque   INT UNSIGNED,
  ativo            BOOLEAN,
  criado_em        DATETIME,
  CHECK (valor_total_bem >= 0),
  CHECK (limite_maximo >= limite_inicial),  -- o máximo não pode ser menor que o inicial
  FOREIGN KEY (categoria_id) REFERENCES categorias(id),
  FOREIGN KEY (fornecedor_id) REFERENCES pessoas(id),
  FOREIGN KEY (criado_por) REFERENCES usuarios(id)
);

-- Preços do produto por periodicidade (diária, semanal, mensal...).
-- A chave primária é a combinação produto + periodicidade:
-- cada produto tem no máximo um preço para cada periodicidade.
CREATE TABLE precos_produto (
  produto_id     BIGINT UNSIGNED,
  periodicidade  ENUM('DIARIA','SEMANAL','QUINZENAL','MENSAL'),   -- ENUM proposto
  valor          DECIMAL(14,2),
  PRIMARY KEY (produto_id, periodicidade),
  CHECK (valor >= 0),
  FOREIGN KEY (produto_id) REFERENCES produtos(id)
);

-- Unidade física = cada container real, com código próprio (ex.: CB-020-001).
CREATE TABLE unidades_fisicas (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  produto_id          BIGINT UNSIGNED,
  codigo              VARCHAR(50) UNIQUE,   -- identificação pintada no container
  estado_operacional  ENUM('DISPONIVEL','LOCADA','MANUTENCAO','INATIVA'),   -- ENUM proposto
  localidade          VARCHAR(200),         -- onde o container está agora
  ultima_vistoria_em  DATETIME,
  observacoes         TEXT,
  criado_em           DATETIME,
  FOREIGN KEY (produto_id) REFERENCES produtos(id)
);

-- Manutenções de um container (preventiva, corretiva...).
CREATE TABLE manutencoes (
  id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unidade_id              BIGINT UNSIGNED,
  responsavel_usuario_id  BIGINT UNSIGNED,
  criado_por              BIGINT UNSIGNED,
  tipo                    VARCHAR(100),
  prioridade              ENUM('BAIXA','MEDIA','ALTA','URGENTE'),                    -- ENUM proposto
  status                  ENUM('AGENDADA','EM_ANDAMENTO','CONCLUIDA','CANCELADA'),   -- ENUM proposto
  data_prevista           DATETIME,
  iniciada_em             DATETIME,
  concluida_em            DATETIME,
  responsavel_externo     VARCHAR(150),     -- empresa ou pessoa de fora que fez o serviço
  descricao               TEXT,
  custo                   DECIMAL(14,2),
  criado_em               DATETIME,
  CHECK (custo >= 0),
  CHECK (concluida_em >= iniciada_em),      -- não pode terminar antes de começar
  FOREIGN KEY (unidade_id) REFERENCES unidades_fisicas(id),
  FOREIGN KEY (responsavel_usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (criado_por) REFERENCES usuarios(id)
);


-- =============================================================================
-- 4. LOCAÇÃO: contratos, itens, agenda dos containers e documentos
-- =============================================================================

-- Contrato de locação.
-- Os campos *_snapshot guardam uma cópia dos dados no dia da assinatura,
-- para o contrato não mudar se o cadastro do cliente ou da empresa mudar depois.
CREATE TABLE contratos (
  id                         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empresa_id                 BIGINT UNSIGNED,
  cliente_id                 BIGINT UNSIGNED,     -- pessoa que aluga
  endereco_entrega_id        BIGINT UNSIGNED,
  condicao_pagamento_id      BIGINT UNSIGNED,
  criado_por                 BIGINT UNSIGNED,
  numero_locacao             VARCHAR(50) UNIQUE,
  numero_contrato            VARCHAR(50) UNIQUE,
  data_emissao               DATE,
  prazo                      ENUM('DETERMINADO','INDETERMINADO'),                 -- ENUM proposto
  data_inicio                DATE,
  data_fim                   DATE,                -- vazio quando o prazo é indeterminado
  periodicidade              ENUM('DIARIA','SEMANAL','QUINZENAL','MENSAL'),       -- ENUM proposto
  status                     ENUM('RASCUNHO','ATIVO','FINALIZADO','CANCELADO'),   -- ENUM proposto
  dia_vencimento             TINYINT UNSIGNED,
  primeiro_vencimento        DATE,
  numero_parcelas            SMALLINT UNSIGNED,
  frete_deslocamento         DECIMAL(14,2),
  outros_valores             DECIMAL(14,2),
  caucao                     DECIMAL(14,2),
  observacoes                TEXT,
  condicoes_gerais_snapshot  TEXT,
  dados_cliente_snapshot     JSON,
  dados_empresa_snapshot     JSON,
  dados_entrega_snapshot     JSON,
  criado_em                  DATETIME,
  finalizado_em              DATETIME,
  cancelado_em               DATETIME,
  motivo_cancelamento        VARCHAR(500),
  CHECK (data_fim >= data_inicio),                          -- não termina antes de começar
  CHECK (dia_vencimento >= 1 AND dia_vencimento <= 31),     -- dia válido do mês
  CHECK (frete_deslocamento >= 0 AND outros_valores >= 0 AND caucao >= 0),
  CHECK (numero_parcelas >= 1),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (cliente_id) REFERENCES pessoas(id),
  FOREIGN KEY (endereco_entrega_id) REFERENCES enderecos_pessoa(id),
  FOREIGN KEY (condicao_pagamento_id) REFERENCES condicoes_pagamento(id),
  FOREIGN KEY (criado_por) REFERENCES usuarios(id)
);

-- Itens do contrato: quais produtos foram alugados, quantidade e valores.
-- Funciona como a tabela intermediária entre contratos e produtos (N:N).
CREATE TABLE contrato_itens (
  id                           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contrato_id                  BIGINT UNSIGNED,
  produto_id                   BIGINT UNSIGNED,
  descricao_snapshot           VARCHAR(500),     -- descrição do produto no dia do contrato
  valor_unitario_bem_snapshot  DECIMAL(14,2),
  quantidade                   INT UNSIGNED,
  periodicidade                ENUM('DIARIA','SEMANAL','QUINZENAL','MENSAL'),   -- ENUM proposto
  numero_periodos              DECIMAL(10,4),    -- quantos períodos foram cobrados
  valor_unitario               DECIMAL(14,2),
  desconto                     DECIMAL(14,2),
  outros                       DECIMAL(14,2),
  subtotal                     DECIMAL(14,2),
  CHECK (quantidade > 0),
  CHECK (valor_unitario >= 0 AND desconto >= 0 AND outros >= 0 AND subtotal >= 0),
  FOREIGN KEY (contrato_id) REFERENCES contratos(id),
  FOREIGN KEY (produto_id) REFERENCES produtos(id)
);

-- Agenda de ocupação dos containers (por locação ou por manutenção).
-- É por esta tabela que se sabe se um container está livre em um período.
CREATE TABLE agenda_unidades (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unidade_id        BIGINT UNSIGNED,     -- pode ficar vazio até o container ser escolhido
  produto_id        BIGINT UNSIGNED,
  contrato_item_id  BIGINT UNSIGNED,     -- preenchido quando a ocupação é uma locação
  manutencao_id     BIGINT UNSIGNED,     -- preenchido quando a ocupação é uma manutenção
  criado_por        BIGINT UNSIGNED,
  inicio            DATETIME,
  fim               DATETIME,
  status            ENUM('RESERVADA','EM_USO','DEVOLVIDA','MANUTENCAO','CANCELADA'),   -- ENUM proposto
  devolvida_em      DATETIME,
  CHECK (fim > inicio),                  -- o fim precisa ser depois do início
  FOREIGN KEY (unidade_id) REFERENCES unidades_fisicas(id),
  FOREIGN KEY (produto_id) REFERENCES produtos(id),
  FOREIGN KEY (contrato_item_id) REFERENCES contrato_itens(id),
  FOREIGN KEY (manutencao_id) REFERENCES manutencoes(id),
  FOREIGN KEY (criado_por) REFERENCES usuarios(id)
);

-- Documentos gerados para o contrato (PDF do contrato, aditivos...), com versão.
CREATE TABLE contrato_documentos (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contrato_id  BIGINT UNSIGNED,
  arquivo_id   BIGINT UNSIGNED,
  gerado_por   BIGINT UNSIGNED,
  versao       INT UNSIGNED,
  tipo         ENUM('CONTRATO','ADITIVO','RECIBO','TERMO_DEVOLUCAO'),   -- ENUM proposto
  gerado_em    DATETIME,
  CHECK (versao >= 1),
  FOREIGN KEY (contrato_id) REFERENCES contratos(id),
  FOREIGN KEY (arquivo_id) REFERENCES arquivos(id),
  FOREIGN KEY (gerado_por) REFERENCES usuarios(id)
);


-- =============================================================================
-- 5. FINANCEIRO: parcelas e pagamentos
-- =============================================================================

-- Parcelas a receber de cada contrato.
-- Valor devido da parcela = valor_original + acrescimo - desconto.
CREATE TABLE parcelas (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contrato_id     BIGINT UNSIGNED,
  numero          INT UNSIGNED,       -- 1ª, 2ª, 3ª parcela...
  competencia     DATE,               -- mês a que a parcela se refere
  vencimento      DATE,
  valor_original  DECIMAL(14,2),
  acrescimo       DECIMAL(14,2),
  desconto        DECIMAL(14,2),
  cancelada       BOOLEAN,
  observacoes     VARCHAR(500),
  CHECK (valor_original >= 0 AND acrescimo >= 0 AND desconto >= 0),
  FOREIGN KEY (contrato_id) REFERENCES contratos(id)
);

-- Pagamentos recebidos.
-- Um estorno não apaga o pagamento: apenas preenche estornado_em.
CREATE TABLE pagamentos (
  id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parcela_id              BIGINT UNSIGNED,
  comprovante_arquivo_id  BIGINT UNSIGNED,
  registrado_por          BIGINT UNSIGNED,
  estornado_por           BIGINT UNSIGNED,
  valor                   DECIMAL(14,2),
  pago_em                 DATETIME,
  forma                   ENUM('DINHEIRO','PIX','CARTAO_CREDITO','CARTAO_DEBITO','BOLETO','TRANSFERENCIA'),   -- ENUM proposto
  estornado_em            DATETIME,
  motivo_estorno          VARCHAR(500),
  criado_em               DATETIME,
  CHECK (valor > 0),                  -- pagamento precisa ter valor positivo
  FOREIGN KEY (parcela_id) REFERENCES parcelas(id),
  FOREIGN KEY (comprovante_arquivo_id) REFERENCES arquivos(id),
  FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
  FOREIGN KEY (estornado_por) REFERENCES usuarios(id)
);


-- =============================================================================
-- 6. AUDITORIA
-- =============================================================================

-- Registro de quem fez o quê no sistema: tabela afetada, id do registro,
-- ação realizada e detalhes em formato JSON.
CREATE TABLE auditoria (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id   BIGINT UNSIGNED,
  entidade     VARCHAR(80),         -- nome da tabela afetada
  registro_id  BIGINT UNSIGNED,     -- id do registro afetado
  acao         VARCHAR(40),         -- ex.: CRIAR, ALTERAR, EXCLUIR
  detalhes     JSON,
  criado_em    DATETIME,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);
