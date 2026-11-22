<?php
/**
 * Teste manual das LISTAGENS e filtros (clientes e fornecedores, produtos, manutenções e contratos)
 * contra o banco de verdade.
 *
 * Cria os próprios registros (com o prefixo "LST" no nome) dentro de UMA transação e desfaz tudo no
 * final (ROLLBACK): o banco continua como estava. Funciona com ou sem os dados de exemplo (seed 003),
 * porque cada busca é limitada aos registros do teste.
 *
 * Como rodar (na raiz do projeto, com o MySQL/MariaDB do XAMPP ligado):
 *   C:\xampp\php\php.exe tests\manual\teste_listagem_cadastros.php
 */

if (PHP_SAPI !== 'cli') {
    exit('Rode pelo terminal: php tests/manual/teste_listagem_cadastros.php');
}

require __DIR__ . '/../../src/bootstrap.php';

$falhas = 0;
$total  = 0;

/** Mostra OK/FALHOU para cada verificação. */
function confere(bool $condicao, string $descricao): void
{
    global $falhas, $total;
    $total++;
    echo ($condicao ? '  OK      ' : '  FALHOU  ') . $descricao . PHP_EOL;
    if (!$condicao) {
        $falhas++;
    }
}

function secao(string $titulo): void
{
    echo PHP_EOL . "== $titulo" . PHP_EOL;
}

/** Nomes da página de resultado, para conferir conteúdo e ordem. */
function nomes(array $resultado, string $campo = 'nome'): array
{
    return array_column($resultado['items'], $campo);
}

$pdo = Conexao::obter();
$pdo->beginTransaction();

try {
    $admin = (int) $pdo->query("SELECT id FROM usuarios WHERE email = 'admin@casabarretto.local'")->fetchColumn();
    if (!$admin) {
        throw new RuntimeException('Usuário admin não encontrado. Rode o seed 001 antes do teste.');
    }
    $condicao = CondicaoPagamento::cadastrar(['nome' => 'LST condição ' . uniqid(), 'dias_primeiro_vencimento' => '0', 'intervalo_dias' => '0', 'numero_parcelas_padrao' => '1'], $admin);
    $hoje     = date('Y-m-d');

    // ------------------------------------------------------------------
    secao('Clientes e fornecedores: dados do teste');
    $endereco = static fn (string $cidade, string $uf): array => [
        'cep' => '12245000', 'rua' => 'Rua Teste', 'numero' => '1', 'bairro' => 'Centro', 'cidade' => $cidade, 'uf' => $uf,
    ];
    // CPFs/CNPJs válidos e fora dos dados de exemplo.
    $pessoas = [
        ['LST Alfa Cliente',      'CLIENTE',    '935.411.347-80', 'Jacareí',     'SP', '(12) 3951-1111'],
        ['LST Beta Cliente',      'CLIENTE',    '483.718.762-54', 'Taubaté',     'SP', '(12) 3632-2222'],
        ['LST Gama Fornecedor',   'FORNECEDOR', '11.444.777/0001-61', 'Curitiba', 'PR', '(41) 3333-3333'],
        ['LST Delta Ambos',       'AMBOS',      '27.865.757/0001-02', 'Jacareí',   'SP', '(12) 3951-4444'],
        ['LST Épsilon Inativo',   'CLIENTE',    '700.448.940-07', 'Taubaté',     'SP', '(12) 3632-5555'],
    ];
    $ids = [];
    foreach ($pessoas as [$nome, $tipo, $doc, $cidade, $uf, $telefone]) {
        $ids[$nome] = Cliente::cadastrarCliente([
            'tipo_cadastro' => $tipo, 'nome_razao_social' => $nome, 'cpf_cnpj' => $doc, 'email' => '',
            'telefone' => $telefone, 'condicao_pagamento_id' => $condicao, 'endereco' => $endereco($cidade, $uf),
        ], $admin)['id'];
    }
    Cliente::inativarCliente($ids['LST Épsilon Inativo'], $admin);
    confere(count($ids) === 5, '5 cadastros de teste (2 clientes, 1 fornecedor, 1 ambos, 1 inativo)');

    // A tela Clientes voltou a listar fornecedores (filtro "Fornecedor" no tipo de cadastro).
    secao('Clientes: tipo de cadastro (filtro exato), fornecedores incluídos');
    $todos = Cliente::listarClientes(['q' => 'LST', 'situacao' => 'todos']);
    confere($todos['total'] === 5 && in_array('LST Gama Fornecedor', nomes($todos), true), 'sem filtro de tipo: os 5 cadastros, inclusive o fornecedor');
    confere(Cliente::listarClientes(['q' => 'LST', 'situacao' => 'todos', 'tipo' => 'CLIENTE'])['total'] === 3, 'tipo CLIENTE: só os 3 clientes puros');
    confere(nomes(Cliente::listarClientes(['q' => 'LST', 'situacao' => 'todos', 'tipo' => 'FORNECEDOR'])) === ['LST Gama Fornecedor'], 'tipo FORNECEDOR: só o fornecedor');
    confere(nomes(Cliente::listarClientes(['q' => 'LST', 'tipo' => 'AMBOS'])) === ['LST Delta Ambos'], 'tipo AMBOS: só o cadastro que é os dois');
    confere(Cliente::listarClientes(['q' => 'LST', 'situacao' => 'todos', 'tipo' => 'xyz'])['total'] === 5, 'tipo desconhecido é ignorado (lista branca)');
    $linhaAmbos = Cliente::listarClientes(['q' => 'LST Delta'])['items'][0] ?? [];
    confere(($linhaAmbos['tipo_cadastro'] ?? null) === 'AMBOS', 'cada linha traz o tipo de cadastro (etiqueta "Cliente e fornecedor")');

    secao('Clientes: situação, local e busca');
    confere(Cliente::listarClientes(['q' => 'LST', 'situacao' => 'ativos'])['total'] === 4, 'situação ativos: 4 (3 clientes + o fornecedor)');
    confere(nomes(Cliente::listarClientes(['q' => 'LST', 'situacao' => 'inativos'])) === ['LST Épsilon Inativo'], 'situação inativos: só o inativo');
    confere(nomes(Cliente::listarClientes(['q' => 'LST', 'situacao' => 'todos', 'uf' => 'PR'])) === ['LST Gama Fornecedor'], 'UF PR: só o fornecedor de Curitiba');
    confere(Cliente::listarClientes(['q' => 'LST', 'situacao' => 'todos', 'uf' => 'sp', 'cidade' => 'Jacareí'])['total'] === 2, 'UF (minúscula) + cidade: 2 em Jacareí');
    confere(in_array('PR', $todos['states'], true) && in_array('Curitiba', Cliente::listarCidades('PR'), true), 'opções de UF e cidade incluem endereços de fornecedores');
    confere(Cliente::listarClientes(['q' => '935.411'])['total'] === 1, 'busca por CPF com pontuação');
    confere(Cliente::listarClientes(['q' => '27865757'])['total'] === 1, 'busca por CNPJ só com números');
    confere(Cliente::listarClientes(['q' => '(12) 3951-4444'])['total'] === 1, 'busca por telefone formatado');
    confere(Cliente::listarClientes(['q' => 'LST-nada-encontra'])['total'] === 0, 'busca sem resultado devolve lista vazia');
    $az = nomes(Cliente::listarClientes(['q' => 'LST', 'situacao' => 'todos', 'ordem' => 'az']));
    $za = nomes(Cliente::listarClientes(['q' => 'LST', 'situacao' => 'todos', 'ordem' => 'za']));
    confere($az[0] === 'LST Alfa Cliente' && $za === array_reverse($az), 'ordem A → Z e Z → A');

    // ------------------------------------------------------------------
    secao('Produtos');
    $categoria  = Categoria::cadastrar('LST Categoria', $admin);
    $fornecedor = $ids['LST Gama Fornecedor'];
    $produtos   = [];
    foreach ([['LST-200', 'LST Contêiner 2,00 m', 2], ['LST-250', 'LST Contêiner 2,50 m', 3], ['LST-300', 'LST Contêiner 3,00 m', 1]] as [$codigo, $nome, $qtd]) {
        $produtos[$codigo] = Produto::cadastrarProduto([
            'categoria_id' => $categoria['id'], 'fornecedor_id' => $fornecedor, 'codigo' => $codigo, 'nome' => $nome,
            'valor_total_bem' => '20000', 'diaria' => '80', 'semanal' => '450', 'quinzenal' => '850', 'mensal' => '1600',
            'quantidade' => (string) $qtd, 'limite_inicial' => '1', 'limite_maximo' => '5', 'maximo_estoque' => '10',
        ], $admin)['id'];
    }
    Produto::alterarStatusProduto($produtos['LST-300'], $admin); // inativa o de 3,00 m
    confere(Produto::listarProdutos(['q' => 'LST'])['total'] === 3, 'busca por nome traz os 3 produtos do teste');
    confere(Produto::listarProdutos(['q' => 'LST-250'])['total'] === 1, 'busca por código');
    confere(Produto::listarProdutos(['q' => 'LST', 'status' => 'ativo'])['total'] === 2, 'status ativo: 2');
    confere(nomes(Produto::listarProdutos(['q' => 'LST', 'status' => 'inativo'])) === ['LST Contêiner 3,00 m'], 'status inativo: só o de 3,00 m');
    confere(Produto::listarProdutos(['categoria' => (string) $categoria['id']])['total'] === 3, 'filtro por categoria');
    $linha250 = Produto::listarProdutos(['q' => 'LST-250'])['items'][0];
    confere($linha250['total'] === 3 && $linha250['disponivel'] === 3 && $linha250['diaria'] === 80.0, 'linha traz estoque (3/3) e diária');
    $recentes = nomes(Produto::listarProdutos(['q' => 'LST', 'ordem' => 'recentes']));
    confere($recentes[0] === 'LST Contêiner 3,00 m', 'ordem "recentes": o último cadastrado primeiro');

    // ------------------------------------------------------------------
    secao('Manutenções: atrasadas x próximos 30 dias');
    $unidade200 = UnidadeFisica::listarPorProduto($produtos['LST-200'])[0]['id'];
    $unidade250 = UnidadeFisica::listarPorProduto($produtos['LST-250'])[0]['id'];
    $antes      = Manutencao::indicadores();
    $mHoje      = Manutencao::cadastrarManutencao(['unidade_id' => $unidade200, 'tipo' => 'LST Vistoria', 'prioridade' => 'BAIXA',
        'data_prevista' => $hoje, 'responsavel_usuario_id' => $admin], $admin);
    $mAtrasada  = Manutencao::cadastrarManutencao(['unidade_id' => $unidade250, 'tipo' => 'LST Pintura', 'prioridade' => 'URGENTE',
        'data_prevista' => $hoje, 'responsavel_usuario_id' => $admin], $admin);
    // O cadastro não aceita data passada: o atraso é simulado mudando a data direto no banco.
    $pdo->prepare('UPDATE manutencoes SET data_prevista = :d WHERE id = :id')
        ->execute(['d' => date('Y-m-d 00:00:00', strtotime('-1 day')), 'id' => $mAtrasada]);
    $depois = Manutencao::indicadores();
    confere($depois['agendadas'] === $antes['agendadas'] + 1, 'card "agendadas" conta só a de hoje (próximos 30 dias)');
    confere($depois['atrasadas'] === $antes['atrasadas'] + 1, 'a vencida entra em "atrasadas"');
    $atrasadas = Manutencao::listarManutencoes(['status' => 'ATRASADA', 'q' => 'LST']);
    confere($atrasadas['total'] === 1 && $atrasadas['items'][0]['atrasada'] === true, 'filtro Status → Atrasada lista só a vencida, marcada como atrasada');
    confere(Manutencao::listarManutencoes(['status' => 'AGENDADA', 'q' => 'LST'])['total'] === 2, 'filtro Agendada continua trazendo as duas');
    confere(Manutencao::listarManutencoes(['prioridade' => 'URGENTE', 'q' => 'LST'])['total'] === 1, 'filtro de prioridade urgente');
    confere(Manutencao::buscarManutencao($mHoje)['atrasada'] === false, 'a de hoje não é atrasada');

    // ------------------------------------------------------------------
    secao('Contratos');
    $dadosContrato = [
        'cliente_id' => $ids['LST Alfa Cliente'], 'prazo' => 'DETERMINADO', 'entrega_origem' => 'CLIENTE',
        'data_inicio' => date('Y-m-d', strtotime('+40 days')), 'data_fim' => date('Y-m-d', strtotime('+45 days')),
        'periodicidade' => 'DIARIA', 'itens' => [['produto_id' => $produtos['LST-250'], 'quantidade' => 1]],
    ];
    $rascunho = Contrato::criarContrato($dadosContrato, $admin);
    $ativo    = Contrato::gerarContrato(Contrato::criarContrato($dadosContrato, $admin), $admin);
    confere(Contrato::listarContratos(['q' => 'LST Alfa', 'status' => 'RASCUNHO'])['total'] === 1, 'filtro de rascunhos');
    confere(Contrato::listarContratos(['q' => 'LST Alfa', 'status' => 'ATIVO'])['total'] === 1, 'filtro de ativos (inclui o reservado para daqui a 40 dias)');
    confere(Contrato::listarContratos(['q' => (string) $ativo['numero_contrato']])['total'] === 1, 'busca pelo número do contrato');
    confere(Contrato::listarContratos(['q' => '935.411.347-80'])['total'] === 2, 'busca pelo CPF do cliente');
    confere(Contrato::listarContratos(['q' => 'LST Alfa', 'data' => date('Y-m-d', strtotime('+42 days'))])['total'] >= 1, 'filtro por data dentro do período');
    confere(Contrato::buscarContrato($rascunho)['emitido'] === false && $ativo['emitido'] === true, 'prévia sabe se o contrato já foi emitido');
} catch (Throwable $e) {
    $falhas++;
    echo PHP_EOL . 'ERRO INESPERADO: ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo PHP_EOL . ($falhas ? "$falhas FALHA(S) em $total verificações (alterações desfeitas com ROLLBACK)" : "TODOS OS $total TESTES PASSARAM (alterações desfeitas com ROLLBACK)") . PHP_EOL;
exit($falhas ? 1 : 0);
