<?php
/**
 * Teste manual da API (public/api → src/Controllers) pelo Apache, como as telas usam.
 *
 * Faz login com o usuário do seed, chama as rotas de leitura e confere o formato do JSON
 * que cada tela espera. As gravações testadas são só as recusadas (sem login, sem token,
 * dados inválidos), então o banco não é alterado.
 *
 * Como rodar (Apache e MySQL ligados; na raiz do projeto):
 *   C:\xampp\php\php.exe tests\manual\teste_api.php
 *   C:\xampp\php\php.exe tests\manual\teste_api.php http://localhost:8080   (outra porta)
 *
 * Senha: usa "mudar123" (seed) ou a definida em SGCB_SENHA (set SGCB_SENHA=minhasenha).
 */

if (PHP_SAPI !== 'cli') {
    exit('Rode pelo terminal: php tests/manual/teste_api.php');
}

require __DIR__ . '/../../src/bootstrap.php';

$servidor = rtrim($argv[1] ?? 'http://localhost', '/');
$base     = $servidor . BASE_URL;
$cookies  = tempnam(sys_get_temp_dir(), 'sgcb');
$falhas   = 0;

/** Requisição HTTP mantendo o cookie da sessão. Devolve [status, corpo]. */
function http(string $metodo, string $url, ?array $json = null, array $cabecalhos = [], ?array $formulario = null): array
{
    global $cookies;
    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $cookies,
        CURLOPT_COOKIEFILE     => $cookies,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($json !== null) {
        $cabecalhos[] = 'Content-Type: application/json';
        curl_setopt($c, CURLOPT_POSTFIELDS, json_encode($json));
    } elseif ($formulario !== null) {
        curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($formulario));
    }
    curl_setopt($c, CURLOPT_HTTPHEADER, $cabecalhos);
    $corpo  = (string) curl_exec($c);
    $status = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);

    return [$status, $corpo];
}

/** GET/POST na API, já decodificando o JSON. */
function api(string $metodo, string $rota, ?array $json = null, array $cabecalhos = []): array
{
    global $base;
    [$status, $corpo] = http($metodo, $base . 'api/' . $rota, $json, $cabecalhos);

    return [$status, json_decode($corpo, true)];
}

function confere(bool $condicao, string $descricao): void
{
    global $falhas;
    echo ($condicao ? '  OK      ' : '  FALHOU  ') . $descricao . PHP_EOL;
    if (!$condicao) {
        $falhas++;
    }
}

/** Confere se todas as chaves existem na resposta. */
function temChaves(?array $dados, array $chaves): bool
{
    return is_array($dados) && !array_diff($chaves, array_keys($dados));
}

echo "Servidor: $base" . PHP_EOL;

// 1. Sem login
echo PHP_EOL . '== Sem login' . PHP_EOL;
[$s, $r] = api('GET', 'dashboard');
confere($s === 401 && isset($r['error']), "GET dashboard sem login → 401 ({$s})");
[$s] = api('GET', 'nao-existe');
confere($s === 401 || $s === 404, "rota desconhecida → $s");

// 2. Login pela tela (pega o token CSRF do formulário, como o navegador)
echo PHP_EOL . '== Login' . PHP_EOL;
[, $pagina] = http('GET', $base . 'login.php');
preg_match('/name="csrf" value="([a-f0-9]+)"/', $pagina, $m);
[$s] = http('POST', $base . 'login.php', null, [], [
    'csrf' => $m[1] ?? '', 'email' => 'admin@casabarretto.local', 'password' => getenv('SGCB_SENHA') ?: 'mudar123',
]);
confere($s === 302, "login do admin (redireciona para o dashboard: $s)");

// 3. Leituras: formato que cada tela espera
echo PHP_EOL . '== Leituras' . PHP_EOL;
[$s, $r] = api('GET', 'dashboard');
confere($s === 200 && temChaves($r, ['metrics', 'monthly_revenue', 'rental_counts', 'top_products', 'top_clients', 'recent_contracts']), 'dashboard');

$listagens = [
    'clientes'    => ['items', 'cities', 'total', 'page', 'pages'],
    'produtos'    => ['items', 'categories', 'total', 'page', 'pages'],
    'manutencoes' => ['items', 'localidades', 'metrics', 'total', 'page', 'pages'],
    'financeiro'  => ['items', 'metrics', 'total', 'page', 'pages'],
    'contratos'   => ['items', 'total', 'page', 'pages'],
    'usuarios'    => ['items', 'total', 'page', 'pages'],
];
foreach ($listagens as $rota => $chaves) {
    [$s, $r] = api('GET', $rota . '?page=1');
    confere($s === 200 && temChaves($r, $chaves), "$rota (total: " . ($r['total'] ?? '?') . ')');
}

$opcoes = [
    'cadastros/opcoes'   => ['condicoes_pagamento', 'csrf_token', 'responsavel_configurado'],
    'categorias/opcoes'  => ['categorias', 'csrf_token', 'responsavel_configurado'],
    'produtos/opcoes'    => ['categorias', 'fornecedores', 'csrf_token', 'responsavel_configurado'],
    'manutencoes/opcoes' => ['unidades', 'responsaveis', 'tipos', 'prioridades', 'csrf_token', 'responsavel_configurado'],
    'contratos/opcoes'   => ['clientes', 'produtos', 'condicoes_pagamento', 'csrf_token'],
    'usuarios/opcoes'    => ['cargos', 'csrf_token'],
    'empresa'            => ['empresa', 'preferencias', 'condicoes_pagamento', 'csrf_token'],
];
$token = '';
foreach ($opcoes as $rota => $chaves) {
    [$s, $r] = api('GET', $rota);
    confere($s === 200 && temChaves($r, $chaves), $rota);
    $token = $r['csrf_token'] ?? $token;
}

[$s, $r] = api('GET', 'usuarios/1');
confere($s === 200 && ($r['email'] ?? '') !== '', 'usuarios/1 (detalhe)');
[$s, $r] = api('GET', 'clientes/999999999');
confere($s === 404 && isset($r['error']), 'cliente inexistente → 404');
[$s] = api('GET', 'rota/que/nao/existe');
confere($s === 404, 'rota desconhecida → 404');
[$s] = api('DELETE', 'clientes');
confere($s === 405, 'método não permitido → 405');

// 4. Gravações recusadas (não alteram o banco)
echo PHP_EOL . '== Gravações recusadas' . PHP_EOL;
[$s, $r] = api('POST', 'categorias', ['nome' => 'Sem token']);
confere($s === 403, 'POST sem token CSRF → 403');
[$s, $r] = api('POST', 'categorias', ['nome' => ''], ['X-CSRF-Token: ' . $token]);
confere($s === 422 && isset($r['fields']['nome']), 'categoria sem nome → 422 com fields.nome');
[$s, $r] = api('POST', 'produtos', ['codigo' => 'x y'], ['X-CSRF-Token: ' . $token]);
confere($s === 422 && isset($r['fields']['codigo'], $r['fields']['nome'], $r['fields']['diaria']), 'produto inválido → 422 com erros por campo');
[$s, $r] = api('POST', 'manutencoes', ['prioridade' => 'X'], ['X-CSRF-Token: ' . $token]);
confere($s === 422 && isset($r['fields']['unidade_id']), 'manutenção inválida → 422');
[$s, $r] = api('POST', 'contratos/calcular', ['itens' => []], ['X-CSRF-Token: ' . $token]);
confere($s === 422 && isset($r['fields']['cliente_id'], $r['fields']['itens']), 'cálculo de contrato sem dados → 422');
[$s, $r] = api('POST', 'financeiro/parcelas/999999999/pagamentos', ['forma' => 'PIX', 'valor' => '10'], ['X-CSRF-Token: ' . $token]);
confere($s === 404, 'pagamento em parcela inexistente → 404');
[$s, $r] = api('POST', 'conta/senha', ['senha_atual' => 'errada', 'nova_senha' => 'Nova12345'], ['X-CSRF-Token: ' . $token]);
confere($s === 422 && isset($r['fields']['senha_atual']), 'troca de senha com a atual errada → 422');

@unlink($cookies);
echo PHP_EOL . ($falhas === 0 ? 'TODOS OS TESTES DA API PASSARAM' : "$falhas FALHA(S)") . PHP_EOL;
exit($falhas === 0 ? 0 : 1);
