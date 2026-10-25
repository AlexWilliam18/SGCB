-- =============================================================================
-- SGCB — Sistema de Gestão de Containers Barreto
-- Seed 001: cargo Administrador e usuário admin padrão
--
-- Finalidade: garante um usuário para o primeiro login após montar o banco do zero.
-- Pré-requisito: database/migrations/001_create_schema_casa_barretto.sql já aplicada.
-- Compatível: MySQL 8.0+ e MariaDB 10.4+ (XAMPP).
-- Destino no repositório: database/seeds/001_usuario_admin.sql
--
-- Credenciais iniciais:
--   E-mail: admin@casabarretto.local
--   Senha:  mudar123
--
-- ATENÇÃO — a senha acima é pública (está no repositório). O usuário é criado com
-- deve_alterar_senha = TRUE para forçar a troca no primeiro acesso. Nunca use
-- essa senha em produção.
--
-- Pode ser executado mais de uma vez: não duplica registros nem sobrescreve uma
-- senha que já tenha sido alterada.
-- =============================================================================

USE sgcb;

SET NAMES utf8mb4;

-- Cargo Administrador: o usuário depende dele (usuarios.cargo_id é obrigatório).
-- Se o cargo já existir (mesmo código), só mantém o registro atual.
INSERT INTO cargos (codigo, nome, nivel, descricao, ativo)
VALUES ('ADMIN', 'Administrador', 100, 'Acesso total ao sistema', TRUE)
ON DUPLICATE KEY UPDATE id = id;

-- Usuário admin padrão. A senha fica em texto puro aqui e é convertida para hash bcrypt
-- (password_hash) no primeiro login, por src/Auth/Autenticacao.php. Se preferir já gravar o hash,
-- gere com: php -r "echo password_hash('mudar123', PASSWORD_BCRYPT);" (validado com password_verify()).
-- O cargo é buscado pelo código, para não depender do id gerado pelo AUTO_INCREMENT.
INSERT INTO usuarios (cargo_id, nome, email, senha_hash, ativo, deve_alterar_senha)
SELECT c.id,
       'Administrador',
       'admin@casabarretto.local',
       'mudar123',
       TRUE,
       TRUE
  FROM cargos c
 WHERE c.codigo = 'ADMIN'
ON DUPLICATE KEY UPDATE id = usuarios.id;
