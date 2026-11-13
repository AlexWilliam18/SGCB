<?php
/**
 * Entrada única da API: recebe toda URL BASE_URL . 'api/...' (ver public/api/.htaccess),
 * encontra a rota, chama o controller e devolve JSON.
 *
 * Caminho de uma requisição:
 *   tela (fetch em assets/js) → este arquivo → src/Controllers/*Controller → src/Models/* → banco.
 *
 * Como no CRUD de base, só são usados GET (ler) e POST (gravar); toda gravação exige o token CSRF.
 * Erros saem como { "error": "mensagem", "fields": { "campo": "mensagem" } } com o status HTTP certo.
 */

require __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/*
 * Rotas: [método, caminho depois de "api/", [Controller, ação]].
 * {id} aceita só números e é passado para a ação como int.
 * As mais específicas vêm antes (ex.: "produtos/opcoes" antes de "produtos/{id}").
 */
$rotas = [
    ['GET',  'dashboard',                              [DashboardController::class, 'resumo']],

    ['GET',  'cadastros/opcoes',                       [ClienteController::class, 'opcoes']],
    ['GET',  'clientes',                               [ClienteController::class, 'listar']],
    ['POST', 'clientes',                               [ClienteController::class, 'salvar']],
    ['GET',  'clientes/{id}',                          [ClienteController::class, 'buscar']],
    ['POST', 'clientes/{id}',                          [ClienteController::class, 'atualizar']],
    ['POST', 'clientes/{id}/status',                   [ClienteController::class, 'alterarStatus']],

    ['GET',  'categorias/opcoes',                      [CategoriaController::class, 'opcoes']],
    ['POST', 'categorias',                             [CategoriaController::class, 'salvar']],
    ['POST', 'categorias/{id}',                        [CategoriaController::class, 'atualizar']],
    ['POST', 'categorias/{id}/status',                 [CategoriaController::class, 'alterarStatus']],
    ['POST', 'categorias/{id}/excluir',                [CategoriaController::class, 'excluir']],

    ['GET',  'produtos',                               [ProdutoController::class, 'listar']],
    ['POST', 'produtos',                               [ProdutoController::class, 'salvar']],
    ['GET',  'produtos/opcoes',                        [ProdutoController::class, 'opcoes']],
    ['GET',  'produtos/{id}',                          [ProdutoController::class, 'buscar']],
    ['POST', 'produtos/{id}',                          [ProdutoController::class, 'atualizar']],
    ['POST', 'produtos/{id}/status',                   [ProdutoController::class, 'alterarStatus']],
    ['POST', 'produtos/{id}/estoque',                  [ProdutoController::class, 'estoque']],

    ['GET',  'manutencoes',                            [ManutencaoController::class, 'listar']],
    ['POST', 'manutencoes',                            [ManutencaoController::class, 'salvar']],
    ['GET',  'manutencoes/opcoes',                     [ManutencaoController::class, 'opcoes']],
    ['GET',  'manutencoes/{id}',                       [ManutencaoController::class, 'buscar']],
    ['POST', 'manutencoes/{id}/iniciar',               [ManutencaoController::class, 'iniciar']],
    ['POST', 'manutencoes/{id}/concluir',              [ManutencaoController::class, 'concluir']],
    ['POST', 'manutencoes/{id}/cancelar',              [ManutencaoController::class, 'cancelar']],

    ['GET',  'financeiro',                             [FinanceiroController::class, 'listar']],
    ['GET',  'financeiro/parcelas/{id}',               [FinanceiroController::class, 'parcela']],
    ['POST', 'financeiro/parcelas/{id}/pagamentos',    [FinanceiroController::class, 'registrarPagamento']],
    ['POST', 'financeiro/parcelas/{id}/ajuste',        [FinanceiroController::class, 'ajustar']],
    ['POST', 'financeiro/parcelas/{id}/cancelar',      [FinanceiroController::class, 'cancelarParcela']],
    ['POST', 'financeiro/pagamentos/{id}/estorno',     [FinanceiroController::class, 'estornar']],
    ['POST', 'financeiro/pagamentos/{id}/corrigir',    [FinanceiroController::class, 'corrigirPagamento']],
    ['GET',  'financeiro/pagamentos/{id}/recibo',      [FinanceiroController::class, 'recibo']],

    ['GET',  'contratos',                              [ContratoController::class, 'listar']],
    ['POST', 'contratos',                              [ContratoController::class, 'salvar']],
    ['GET',  'contratos/opcoes',                       [ContratoController::class, 'opcoes']],
    ['GET',  'contratos/unidades',                     [ContratoController::class, 'unidades']],
    ['POST', 'contratos/calcular',                     [ContratoController::class, 'calcular']],
    ['GET',  'contratos/{id}',                         [ContratoController::class, 'buscar']],
    ['POST', 'contratos/{id}/gerar',                   [ContratoController::class, 'gerar']],
    ['POST', 'contratos/{id}/iniciar',                 [ContratoController::class, 'iniciar']],
    ['POST', 'contratos/{id}/finalizar',               [ContratoController::class, 'finalizar']],
    ['POST', 'contratos/{id}/cancelar',                [ContratoController::class, 'cancelar']],
    ['POST', 'contratos/{id}/editar',                  [ContratoController::class, 'editar']],
    ['GET',  'contratos/{id}/versoes',                 [ContratoController::class, 'versao']],

    ['GET',  'usuarios',                               [UsuarioController::class, 'listar']],
    ['POST', 'usuarios',                               [UsuarioController::class, 'salvar']],
    ['GET',  'usuarios/opcoes',                        [UsuarioController::class, 'opcoes']],
    ['GET',  'usuarios/{id}',                          [UsuarioController::class, 'buscar']],
    ['POST', 'usuarios/{id}',                          [UsuarioController::class, 'atualizar']],
    ['POST', 'usuarios/{id}/status',                   [UsuarioController::class, 'alterarStatus']],
    ['POST', 'conta/senha',                            [UsuarioController::class, 'alterarSenha']],

    ['GET',  'empresa',                                [EmpresaController::class, 'buscar']],
    ['POST', 'empresa',                                [EmpresaController::class, 'salvar']],
    ['POST', 'empresa/preferencias',                   [EmpresaController::class, 'salvarPreferencias']],
    ['POST', 'condicoes-pagamento',                    [EmpresaController::class, 'salvarCondicao']],
];

// Caminho pedido, sem a BASE_URL e sem "api/" (ex.: /SGCB/api/clientes/5?x=1 → "clientes/5").
$caminho = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$prefixo = BASE_URL . 'api/';
$caminho = trim(str_starts_with($caminho, $prefixo) ? substr($caminho, strlen($prefixo)) : '', '/');
$metodo  = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $caminhoExiste = false;

    foreach ($rotas as [$metodoRota, $padrao, $acao]) {
        // "clientes/{id}/status" → #^clientes/(\d+)/status$#
        $regex = '#^' . str_replace('\{id\}', '(\d+)', preg_quote($padrao, '#')) . '$#';
        if (!preg_match($regex, $caminho, $partes)) {
            continue;
        }
        $caminhoExiste = true;
        if ($metodoRota !== $metodo) {
            continue;
        }

        $parametros = array_map('intval', array_slice($partes, 1));
        echo json_encode($acao(...$parametros), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    // Caminho existe com outro método → 405; caminho desconhecido → 404.
    throw new ErroValidacao($caminhoExiste ? 'Método não permitido.' : 'Rota não encontrada.', [], $caminhoExiste ? 405 : 404);
} catch (ErroValidacao $e) {
    http_response_code($e->getStatus());
    $resposta = ['error' => $e->getMessage()];
    if ($e->getCampos()) {
        $resposta['fields'] = $e->getCampos();
    }
    echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    // Detalhe técnico só no log do servidor; a tela recebe uma mensagem genérica.
    error_log('SGCB API banco: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'Não foi possível acessar o banco de dados. Tente novamente em instantes.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('SGCB API: ' . get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno. Tente novamente.'], JSON_UNESCAPED_UNICODE);
}
