-- =============================================================================
-- SGCB - Seed 004: novos nomes e permissões dos cargos
--
--   Dono     (código ADMIN)    → Administrador Master: acesso a tudo, inclusive Usuários.
--   Operador (código OPERADOR) → Gerente: acessa todas as telas, da Visão Geral ao
--                                Financeiro (todas as ações), mas NÃO acessa Usuários.
--
-- Os códigos ADMIN e OPERADOR não mudam: o sistema em PHP usa o código (ex.: Usuario::CARGO_ADMIN),
-- então só o nome exibido e a matriz de permissões do Gerente são alterados.
-- Usuários e Configurações continuam exclusivos do Administrador Master (modulos.exclusivo_adm),
-- assim como as exclusões definitivas (Api::apenasAdmin confere o cargo ADMIN).
--
-- Pode rodar mais de uma vez (UPDATE + ON DUPLICATE KEY). Rode depois dos seeds 001 e 002.
-- =============================================================================

USE sgcb;

-- Nomes e descrições exibidos nas telas.
UPDATE cargos
   SET nome = 'Administrador Master', descricao = 'Acesso total, incluindo a gestão de usuários'
 WHERE codigo = 'ADMIN';

UPDATE cargos
   SET nome = 'Gerente', descricao = 'Acesso a todas as telas, exceto Usuários'
 WHERE codigo = 'OPERADOR';

-- Gerente recebe todas as ações dos módulos que não são exclusivos do Administrador Master
-- (clientes, produtos, contratos, manutenções e financeiro, incluindo cancelar contrato,
-- registrar, ajustar e estornar pagamentos).
INSERT INTO cargo_permissoes (cargo_id, permissao_id)
SELECT c.id, p.id
  FROM cargos c
  JOIN permissoes p
  JOIN modulos m ON m.id = p.modulo_id
 WHERE c.codigo = 'OPERADOR'
   AND m.exclusivo_adm = FALSE
ON DUPLICATE KEY UPDATE cargo_id = cargo_id;

-- Conferência:
--   SELECT c.nome AS cargo, m.codigo AS modulo, p.acao
--     FROM cargo_permissoes cp JOIN cargos c ON c.id = cp.cargo_id
--     JOIN permissoes p ON p.id = cp.permissao_id JOIN modulos m ON m.id = p.modulo_id
--    ORDER BY c.nome, m.codigo, p.acao;
