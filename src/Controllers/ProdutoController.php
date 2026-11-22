<?php
/**
 * API de Produtos (telas admin/produtos: produtos.js e cadastro-produto.js).
 *
 *   GET  api/produtos                  listar (filtros q, status, categoria, page)
 *   GET  api/produtos/opcoes           opcoes do cadastro (categorias, fornecedores + token)
 *   GET  api/produtos/{id}             buscar (com preços e unidades físicas)
 *   POST api/produtos                  salvar (gera as unidades físicas)
 *   POST api/produtos/{id}             atualizar
 *   POST api/produtos/{id}/status      ativar ↔ inativar
 *   POST api/produtos/{id}/estoque     aumentar/diminuir unidades ({ "quantidade": 2 } ou -1)
 */

final class ProdutoController
{
    /**
     * GET /api/produtos/disponibilidade/opcoes
     * Produtos ativos para o campo "Produto" da consulta de disponibilidade, e a data de hoje
     * (data mínima do campo início). Exige permissão de visualizar contratos.
     */
    public static function disponibilidadeOpcoes(): array
    {
        Api::permitir('contratos', 'visualizar', false);
        $produtos = Conexao::obter()->query('SELECT id, codigo, nome FROM produtos WHERE ativo = 1 ORDER BY nome')->fetchAll();
        return ['items' => $produtos, 'hoje' => date('Y-m-d')];
    }

    /**
     * GET /api/produtos/{id}/disponibilidade?data_inicio=&data_fim=&quantidade=
     * Previsão para o período (Disponibilidade::consultar). Não reserva nada.
     */
    public static function disponibilidade(int $id): array
    {
        Api::permitir('contratos', 'visualizar', false);
        $q = filter_var($_GET['quantidade'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($q === false) throw new ErroValidacao('Informe uma quantidade inteira positiva.', [], 422);
        return Disponibilidade::consultar($id, is_string($_GET['data_inicio'] ?? null) ? $_GET['data_inicio'] : '',
            is_string($_GET['data_fim'] ?? null) ? $_GET['data_fim'] : '', $q);
    }

    /**
     * GET /api/produtos/{id}/proximas-disponibilidades
     * Linha do tempo dos detalhes do produto. Produto inativo devolve lista vazia.
     */
    public static function proximas(int $id): array
    {
        Api::permitir('produtos', 'visualizar', false);
        $p = Api::ouNaoEncontrado(Produto::buscarProduto($id));
        if (!$p['ativo']) return ['items' => [], 'limite_busca' => null];
        return Disponibilidade::proximas($id);
    }

    public static function listar(): array
    {
        Api::usuario();

        return Produto::listarProdutos($_GET);
    }

    public static function buscar(int $id): array
    {
        Api::usuario();
        $produto             = Api::ouNaoEncontrado(Produto::buscarProduto($id), 'Produto não encontrado.');
        $produto['unidades'] = UnidadeFisica::listarPorProduto($id);

        return $produto;
    }

    public static function opcoes(): array
    {
        Api::usuario();

        return Produto::opcoes() + Api::dadosDoFormulario();
    }

    /** @return array{id: int, nome: string, unidades_criadas: int} o cadastro-produto.js exige o nome. */
    public static function salvar(): array
    {
        $usuario = Api::permitir('produtos', 'cadastrar');

        return Api::criado(Produto::cadastrarProduto(Api::dados(), $usuario['id']));
    }

    public static function atualizar(int $id): array
    {
        $usuario = Api::permitir('produtos', 'editar');
        Produto::atualizarProduto($id, Api::dados(), $usuario['id']);

        return Produto::buscarProduto($id);
    }

    public static function alterarStatus(int $id): array
    {
        $usuario = Api::permitir('produtos', 'editar');

        return ['id' => $id, 'ativo' => Produto::alterarStatusProduto($id, $usuario['id'])];
    }

    public static function estoque(int $id): array
    {
        $usuario    = Api::permitir('produtos', 'editar');
        $quantidade = filter_var(Api::dados()['quantidade'] ?? null, FILTER_VALIDATE_INT);
        if ($quantidade === false || $quantidade === 0) {
            throw new ErroValidacao('Informe quantas unidades entram (positivo) ou saem (negativo).', ['quantidade' => 'Quantidade inválida.']);
        }

        return ['afetadas' => Produto::atualizarQuantidade($id, $quantidade, $usuario['id'])] + Produto::buscarProduto($id);
    }
}
