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
--   Senha:  Admin@123
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

-- Usuário admin padrão. O hash foi gerado com password_hash('Admin@123', PASSWORD_BCRYPT)
-- e é validado no login com password_verify(). Para trocar a senha inicial, gere um
-- novo hash com: php -r "echo password_hash('NovaSenha', PASSWORD_BCRYPT);"
-- O cargo é buscado pelo código, para não depender do id gerado pelo AUTO_INCREMENT.
INSERT INTO usuarios (cargo_id, nome, email, senha_hash, ativo, deve_alterar_senha)
SELECT c.id,
       'Administrador',
       'admin@casabarretto.local',
       '$2y$10$vHbt451mKfp1IA8xsh.ape0RgAKA.rnDsojGoQEHh8KJ2mg2.awc6',
       TRUE,
       TRUE
  FROM cargos c
 WHERE c.codigo = 'ADMIN'
ON DUPLICATE KEY UPDATE id = usuarios.id;
