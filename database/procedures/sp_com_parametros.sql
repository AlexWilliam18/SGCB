-- =============================================================================
-- SGCB — Stored Procedures com Parâmetros (Aula 14)
-- Arquivo: database/procedures/sp_com_parametros.sql
--
-- Finalidade: as 10 procedures da Lista de Exercícios (exercícios 7 a 16),
--             todas usando o banco do projeto (sgcb).
--
-- Comandos usados (todos da Aula 14): DELIMITER, CREATE PROCEDURE,
--   parâmetros IN com prefixo p_, BEGIN/END, SELECT * FROM ... WHERE,
--   AND, INSERT INTO ... VALUES, UPDATE ... SET ... WHERE,
--   DELETE FROM ... WHERE e CALL.
--
-- Únicos pontos de aulas anteriores, porque o próprio exercício exige:
--   - exercício 14 (faixa de valores): operadores >= e <=  (Aula 03)
--   - exercício 16 (ordenar resultados): ORDER BY         (Revisão CRUD)
--
-- Como executar: no phpMyAdmin, selecione o banco "sgcb" e use a aba
-- IMPORTAR com este arquivo. Para criar de novo uma procedure que já
-- existe, apague-a antes com DROP PROCEDURE NomeDaProcedure; (Aula 13).
--
-- Tabelas usadas: produtos, unidades_fisicas, contratos (migração 001).
-- =============================================================================


-- -----------------------------------------------------------------------------
-- Exercício 7 — Buscar um registro pelo ID da principal tabela do projeto.
-- Tabela principal: produtos (cada modelo de container que a empresa aluga).
-- Parâmetro: p_id = id do produto.
-- Exemplo:   CALL BuscarProdutoPorId(1);
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE PROCEDURE BuscarProdutoPorId(
  IN p_id BIGINT UNSIGNED
)
BEGIN
  SELECT *
  FROM produtos
  WHERE id = p_id;
END$$
DELIMITER ;


-- -----------------------------------------------------------------------------
-- Exercício 8 — Pesquisar registros pelo nome.
-- Busca o produto com o nome exatamente igual ao informado.
-- Parâmetro: p_nome = nome do produto.
-- Exemplo:   CALL BuscarProdutoPorNome('Container 20 pés escritório');
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE PROCEDURE BuscarProdutoPorNome(
  IN p_nome VARCHAR(200)
)
BEGIN
  SELECT *
  FROM produtos
  WHERE nome = p_nome;
END$$
DELIMITER ;


-- -----------------------------------------------------------------------------
-- Exercício 9 — Filtrar registros por uma categoria.
-- Lista todos os produtos de uma categoria.
-- Parâmetro: p_categoria_id = id da categoria.
-- Exemplo:   CALL BuscarProdutosPorCategoria(1);
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE PROCEDURE BuscarProdutosPorCategoria(
  IN p_categoria_id BIGINT UNSIGNED
)
BEGIN
  SELECT *
  FROM produtos
  WHERE categoria_id = p_categoria_id;
END$$
DELIMITER ;


-- -----------------------------------------------------------------------------
-- Exercício 10 — Inserir um novo registro usando parâmetros.
-- Cadastra um novo produto já como ativo (TRUE).
-- Parâmetros: p_categoria_id, p_codigo, p_nome, p_descricao, p_valor_total_bem.
-- Exemplo:   CALL InserirProduto(1, 'C10-ESC', 'Container 10 pés escritório',
--                                'Escritório compacto', 28000.00);
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE PROCEDURE InserirProduto(
  IN p_categoria_id    BIGINT UNSIGNED,
  IN p_codigo          VARCHAR(40),
  IN p_nome            VARCHAR(200),
  IN p_descricao       VARCHAR(500),
  IN p_valor_total_bem DECIMAL(14,2)
)
BEGIN
  INSERT INTO produtos(
    categoria_id,
    codigo,
    nome,
    descricao,
    valor_total_bem,
    ativo
  )
  VALUES(
    p_categoria_id,
    p_codigo,
    p_nome,
    p_descricao,
    p_valor_total_bem,
    TRUE
  );
END$$
DELIMITER ;


-- -----------------------------------------------------------------------------
-- Exercício 11 — Atualizar um campo específico de um registro.
-- Altera o valor do bem (valor_total_bem) de um produto.
-- Parâmetros: p_id = id do produto; p_valor_total_bem = novo valor.
-- Exemplo:   CALL AtualizarValorProduto(1, 37000.00);
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE PROCEDURE AtualizarValorProduto(
  IN p_id              BIGINT UNSIGNED,
  IN p_valor_total_bem DECIMAL(14,2)
)
BEGIN
  UPDATE produtos
  SET valor_total_bem = p_valor_total_bem
  WHERE id = p_id;
END$$
DELIMITER ;


-- -----------------------------------------------------------------------------
-- Exercício 12 — Excluir um registro pelo ID.
-- Remove um produto. Se o produto tiver containers cadastrados em
-- unidades_fisicas, a chave estrangeira impede a exclusão (integridade).
-- Parâmetro: p_id = id do produto.
-- Exemplo:   CALL ExcluirProduto(6);
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE PROCEDURE ExcluirProduto(
  IN p_id BIGINT UNSIGNED
)
BEGIN
  DELETE FROM produtos
  WHERE id = p_id;
END$$
DELIMITER ;


-- -----------------------------------------------------------------------------
-- Exercício 13 — Dois parâmetros usados juntos no WHERE.
-- Lista os containers de um produto que estão em um estado específico.
-- É a procedure mais útil para o sistema: mostra, por exemplo, quais
-- containers de um modelo estão DISPONIVEIS para alugar.
-- Parâmetros: p_produto_id = id do produto;
--             p_estado     = DISPONIVEL, LOCADA, MANUTENCAO ou INATIVA.
-- Exemplo:   CALL BuscarUnidadesPorProdutoEstado(1, 'DISPONIVEL');
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE PROCEDURE BuscarUnidadesPorProdutoEstado(
  IN p_produto_id BIGINT UNSIGNED,
  IN p_estado     VARCHAR(20)
)
BEGIN
  SELECT *
  FROM unidades_fisicas
  WHERE produto_id = p_produto_id
  AND estado_operacional = p_estado;
END$$
DELIMITER ;


-- -----------------------------------------------------------------------------
-- Exercício 14 — Listar registros dentro de uma faixa de valores.
-- Lista os produtos cujo valor do bem está entre o mínimo e o máximo.
-- Usa >= e <= (Aula 03), pois a Aula 14 não tem exemplo de faixa.
-- Parâmetros: p_valor_minimo, p_valor_maximo.
-- Exemplo:   CALL BuscarProdutosPorFaixaValor(20000.00, 40000.00);
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE PROCEDURE BuscarProdutosPorFaixaValor(
  IN p_valor_minimo DECIMAL(14,2),
  IN p_valor_maximo DECIMAL(14,2)
)
BEGIN
  SELECT *
  FROM produtos
  WHERE valor_total_bem >= p_valor_minimo
  AND valor_total_bem <= p_valor_maximo;
END$$
DELIMITER ;


-- -----------------------------------------------------------------------------
-- Exercício 15 — Filtrar registros por data.
-- Lista os contratos que começam na data informada.
-- Parâmetro: p_data = data de início, no formato 'AAAA-MM-DD'.
-- Exemplo:   CALL BuscarContratosPorDataInicio('2026-11-01');
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE PROCEDURE BuscarContratosPorDataInicio(
  IN p_data DATE
)
BEGIN
  SELECT *
  FROM contratos
  WHERE data_inicio = p_data;
END$$
DELIMITER ;


-- -----------------------------------------------------------------------------
-- Exercício 16 — Ordenar os resultados por um campo escolhido.
-- Lista os produtos de uma categoria em ordem alfabética de nome.
-- Usa ORDER BY (Revisão CRUD), pois a Aula 14 não tem exemplo de ordenação.
-- Parâmetro: p_categoria_id = id da categoria.
-- Exemplo:   CALL ListarProdutosCategoriaPorNome(1);
-- -----------------------------------------------------------------------------
DELIMITER $$
CREATE PROCEDURE ListarProdutosCategoriaPorNome(
  IN p_categoria_id BIGINT UNSIGNED
)
BEGIN
  SELECT *
  FROM produtos
  WHERE categoria_id = p_categoria_id
  ORDER BY nome;
END$$
DELIMITER ;