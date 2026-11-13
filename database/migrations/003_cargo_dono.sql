-- =============================================================================
-- SGCB - Migração 003: o cargo "Administrador" passa a se chamar "Dono".
--
-- Só muda o nome exibido nas telas. O código continua 'ADMIN', e é por ele que o
-- sistema libera as permissões (financeiro, usuários, exclusões), então nada mais muda.
-- Bancos criados com o seed 001 atual já nascem com "Dono" e não precisam disto.
--
-- Depois de rodar, quem estiver logado precisa sair e entrar de novo para ver o nome novo
-- no menu (o nome do cargo fica guardado na sessão).
-- =============================================================================

USE sgcb;

SET NAMES utf8mb4;

UPDATE cargos
   SET nome = 'Dono'
 WHERE codigo = 'ADMIN';
