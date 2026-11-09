<?php
/**
 * Teste manual dos models (src/Models) contra o banco de verdade.
 *
 * Roda um fluxo completo — empresa, cliente, produto, contrato, pagamentos, manutenção,
 * dashboard e usuário — dentro de UMA transação e desfaz tudo no final (ROLLBACK).
 * O banco continua como estava (só os contadores AUTO_INCREMENT avançam).
 *
 * Como rodar (na raiz do projeto):
 *   C:\xampp\php\php.exe tests\manual\teste_models.php
 */

if (PHP_SAPI !== 'cli') {
    exit('Rode pelo terminal: php tests/manual/teste_models.php');
}

require __DIR__ . '/../../src/bootstrap.php';

$falhas = 0;

/** Mostra OK/FALHOU para cada verificação. */
function confere(bool $condicao, string $descricao): void
{
    global $falhas;
    echo ($condicao ? '  OK      ' : '  FALHOU  ') . $descricao . PHP_EOL;
    if (!$condicao) {
        $falhas++;
    }
}

/** Confere se a ação é recusada com ErroValidacao (e o status HTTP esperado). */
function esperaErro(callable $acao, int $status, string $descricao): void
{
    try {
        $acao();
        confere(false, "$descricao (não deu erro)");
    } catch (ErroValidacao $e) {
        confere($e->getStatus() === $status, "$descricao → {$e->getStatus()}: {$e->getMessage()}");
    }
}

function secao(string $titulo): void
{
    echo PHP_EOL . "== $titulo" . PHP_EOL;
}

$pdo = Conexao::obter();
$pdo->beginTransaction();

try {
    $admin = (int) $pdo->query("SELECT id FROM usuarios WHERE email = 'admin@casabarretto.local'")->fetchColumn();
    confere($admin > 0, 'usuário admin do seed existe');

    $hoje   = date('Y-m-d');
    $em10   = date('Y-m-d', strtotime('+10 days'));

    // ------------------------------------------------------------------
    secao('Empresa e condições de pagamento');
    $empresaId = Empresa::obterEmpresa()['id'] ?? Empresa::salvarEmpresa(['razao_social' => 'Casa Barretto Teste', 'uf' => 'SP'], $admin);
    confere($empresaId > 0, 'empresa disponível');
    Empresa::salvarPreferencias($empresaId, ['prefixo_contrato' => 'TST-', 'dias_alerta_vencimento' => '7'], $admin);
    confere(Empresa::obterPreferencias($empresaId)['prefixo_contrato'] === 'TST-', 'preferências gravadas');

    $condicao = CondicaoPagamento::cadastrar([
        'nome' => 'Teste 2x ' . uniqid(), 'dias_primeiro_vencimento' => '0', 'intervalo_dias' => '30', 'numero_parcelas_padrao' => '2',
    ], $admin);
    confere($condicao > 0, 'condição de pagamento 2x cadastrada');

    // ------------------------------------------------------------------
    secao('Categoria');
    $categoria = Categoria::cadastrar('Escritório Teste', $admin);
    confere($categoria['id'] > 0 && $categoria['nome'] === 'Escritório Teste', 'categoria cadastrada');
    esperaErro(fn () => Categoria::cadastrar('escritório teste', $admin), 409, 'categoria duplicada (maiúsculas diferentes)');

    // ------------------------------------------------------------------
    secao('Clientes e fornecedores');
    $endereco = ['principal' => true, 'tipo' => 'COMERCIAL', 'cep' => '12245-000', 'rua' => 'Rua das Flores', 'numero' => '100',
                 'bairro' => 'Centro', 'cidade' => 'São José dos Campos', 'uf' => 'SP', 'complemento' => ''];

    $fornecedor = Cliente::cadastrarCliente([
        'tipo_cadastro' => 'FORNECEDOR', 'nome_razao_social' => 'Fornecedora de Contêineres LTDA', 'cpf_cnpj' => '99.888.777/0001-00',
        'email' => 'vendas@fornecedora.com', 'telefone' => '(11) 3333-4444', 'condicao_pagamento_id' => $condicao,
        'limite_credito' => '0', 'enderecos' => [$endereco],
    ], $admin);
    confere($fornecedor['tipo_cadastro'] === 'FORNECEDOR', 'fornecedor (CNPJ) cadastrado');

    $cliente = Cliente::cadastrarCliente([
        'tipo_cadastro' => 'CLIENTE', 'nome_razao_social' => 'João da Silva', 'cpf_cnpj' => '529.982.247-25',
        'email' => 'JOAO@EMAIL.COM', 'telefone' => '(12) 99999-8888', 'nacionalidade' => 'Brasileira', 'profissao' => 'Construção civil',
        'condicao_pagamento_id' => $condicao, 'limite_credito' => '50000,00', 'permite_ultrapassar_limite' => false,
        'enderecos' => [$endereco, ['principal' => false, 'tipo' => 'ENTREGA', 'cep' => '12245001', 'rua' => 'Av. Obra', 'numero' => 'S/N',
                                    'bairro' => 'Distrito', 'cidade' => 'Jacareí', 'uf' => 'sp']],
    ], $admin);
    confere($cliente['id'] > 0, 'cliente (CPF) com 2 endereços cadastrado');

    esperaErro(fn () => Cliente::cadastrarCliente(['tipo_cadastro' => 'CLIENTE', 'nome_razao_social' => 'X', 'cpf_cnpj' => '529.982.247-25',
        'email' => 'x@x.com', 'telefone' => '12999998888', 'condicao_pagamento_id' => $condicao, 'enderecos' => [$endereco]], $admin), 409, 'CPF duplicado');
    try {
        Cliente::cadastrarCliente(['tipo_cadastro' => 'CLIENTE', 'cpf_cnpj' => '123.456.789-00', 'enderecos' => [['cep' => '1']]], $admin);
        confere(false, 'dados inválidos recusados');
    } catch (ErroValidacao $e) {
        $campos = $e->getCampos();
        confere(isset($campos['cpf_cnpj'], $campos['email'], $campos['enderecos.0.cep'], $campos['enderecos.0.rua']), 'erros por campo no formato da tela (cpf_cnpj, email, enderecos.0.cep...)');
    }

    $detalhe = Cliente::buscarCliente($cliente['id']);
    confere($detalhe['email'] === 'joao@email.com' && $detalhe['cpf_cnpj'] === '52998224725', 'e-mail em minúsculas e CPF só com números');
    confere(count($detalhe['enderecos']) === 2 && $detalhe['enderecos'][0]['principal'] === true, 'endereço principal vem primeiro');

    $lista = Cliente::listarClientes(['q' => '529.982', 'situacao' => 'ativos']);
    confere($lista['total'] === 1 && $lista['items'][0]['cidade'] === 'São José dos Campos', 'listagem busca por CPF formatado e traz a cidade principal');
    confere(in_array('São José dos Campos', $lista['cities'], true), 'lista de cidades para o filtro');
    confere(Cliente::listarClientes(['q' => 'Fornecedora'])['total'] === 0, 'fornecedor exclusivo não aparece na listagem de clientes');
    confere(in_array($fornecedor['id'], array_column(Cliente::listarFornecedores(), 'id'), true), 'fornecedor aparece em listarFornecedores');

    // ------------------------------------------------------------------
    secao('Produto e unidades físicas');
    $produto = Produto::cadastrarProduto([
        'categoria_id' => $categoria['id'], 'fornecedor_id' => $fornecedor['id'], 'codigo' => 'tst-20', 'nome' => 'Container 20 pés Teste',
        'descricao' => 'Escritório', 'valor_total_bem' => '35.000,00', 'diaria' => '100.00', 'semanal' => '600.00',
        'quinzenal' => '1100.00', 'mensal' => '2000.00', 'quantidade' => '3', 'limite_inicial' => '1', 'limite_maximo' => '5', 'maximo_estoque' => '10',
    ], $admin);
    confere($produto['unidades_criadas'] === 3, 'produto cadastrado com 3 unidades');
    $unidades = UnidadeFisica::listarPorProduto($produto['id']);
    confere(array_column($unidades, 'codigo') === ['TST-20-001', 'TST-20-002', 'TST-20-003'], 'códigos das unidades sequenciais (TST-20-001...)');
    esperaErro(fn () => Produto::cadastrarProduto(['categoria_id' => $categoria['id'], 'fornecedor_id' => $fornecedor['id'], 'codigo' => 'TST-20',
        'nome' => 'Dup', 'valor_total_bem' => '1', 'diaria' => '1', 'semanal' => '1', 'quinzenal' => '1', 'mensal' => '1', 'quantidade' => '1',
        'limite_inicial' => '0', 'limite_maximo' => '0', 'maximo_estoque' => '0'], $admin), 409, 'código de produto duplicado');

    $listaProdutos = Produto::listarProdutos(['q' => 'TST-20']);
    $item          = $listaProdutos['items'][0];
    confere($item['total'] === 3 && $item['disponivel'] === 3 && $item['diaria'] === 100.0, 'listagem: total 3, disponível 3, diária 100');
    confere(count(Produto::buscarProduto($produto['id'])['precos']) === 4, 'detalhes trazem os 4 preços');

    // ------------------------------------------------------------------
    secao('Contrato');
    $dadosContrato = [
        'cliente_id' => $cliente['id'], 'endereco_entrega_id' => $detalhe['enderecos'][1]['id'], 'prazo' => 'DETERMINADO',
        'data_inicio' => $hoje, 'data_fim' => $em10, 'periodicidade' => 'DIARIA', 'frete_deslocamento' => '50.00',
        'itens' => [['produto_id' => $produto['id'], 'quantidade' => 2, 'desconto' => '0']],
    ];
    $previa = Contrato::calcularValorTotal($dadosContrato);
    // 100 (diária) × 2 contêineres × 10 dias + 50 de frete = 2.050,00
    confere($previa['valor_total'] === 2050.0, 'calcularValorTotal: 100 × 2 × 10 dias + 50 frete = 2050');

    $contratoId = Contrato::criarContrato($dadosContrato, $admin);
    confere(Contrato::buscarContrato($contratoId)['status'] === 'RASCUNHO', 'contrato criado como RASCUNHO');
    confere(Produto::verificarDisponibilidade($produto['id'], 3, $hoje, $em10), 'rascunho ainda não reserva contêineres');

    $gerado = Contrato::gerarContrato($contratoId, $admin);
    confere($gerado['status'] === 'ATIVO' && str_starts_with((string) $gerado['numero_contrato'], 'TST-'), "contrato gerado: {$gerado['numero_contrato']}");
    confere(count($gerado['parcelas']) === 2 && $gerado['parcelas'][0]['valor_original'] === 1025.0, '2 parcelas de 1025 (condição do cliente)');
    confere($gerado['parcelas'][1]['vencimento'] === date('Y-m-d', strtotime('+30 days')), '2ª parcela vence 30 dias depois');
    confere(count($gerado['unidades']) === 2 && $gerado['unidades'][0]['status'] === 'EM_USO', '2 contêineres EM_USO (começa hoje)');
    confere(!Produto::verificarDisponibilidade($produto['id'], 2, $hoje, $em10), 'só 1 contêiner livre no período');
    confere(Produto::verificarDisponibilidade($produto['id'], 3, date('Y-m-d', strtotime('+20 days')), date('Y-m-d', strtotime('+25 days'))), 'depois do contrato, os 3 estão livres');
    esperaErro(fn () => Contrato::gerarContrato($contratoId, $admin), 409, 'gerar de novo um contrato ativo');

    $segundo = Contrato::criarContrato(array_merge($dadosContrato, ['itens' => [['produto_id' => $produto['id'], 'quantidade' => 2]]]), $admin);
    esperaErro(fn () => Contrato::gerarContrato($segundo, $admin), 409, 'segundo contrato sem estoque no período');

    // ------------------------------------------------------------------
    secao('Pagamentos e financeiro');
    $parcela1 = $gerado['parcelas'][0]['id'];

    $pix = new PagamentoPix($parcela1, '500,00', chavePix: 'joao@email.com');
    $pix->processarPagamento($admin);
    confere($pix->verificarStatusPagamento() === 'PAGO' && $pix->getFormaPagamento() === 'Pix', 'Pix de 500 registrado');
    confere(Parcela::buscarParcela($parcela1)['status'] === 'PARCIAL', 'parcela fica PARCIAL');

    $acima = new PagamentoCartao($parcela1, '600.00', tipo: 'CREDITO', parcelas: 2, ultimosDigitos: '1111');
    esperaErro(fn () => $acima->processarPagamento($admin), 422, 'pagamento acima do saldo (525)');
    confere($acima->verificarStatusPagamento() === 'RECUSADO', 'status RECUSADO no objeto');
    esperaErro(fn () => (new PagamentoCartao($parcela1, '10', tipo: 'DEBITO', parcelas: 3))->processarPagamento($admin), 422, 'débito parcelado');

    $cartao = Pagamento::criarPorForma(['forma' => 'CARTAO_CREDITO', 'parcela_id' => $parcela1, 'valor' => '525.00', 'parcelas' => 3, 'ultimos_digitos' => '1111']);
    $idCartao = $cartao->processarPagamento($admin);
    confere($cartao->getFormaPagamento() === 'Cartão de crédito final 1111 em 3x', 'fábrica criarPorForma → ' . $cartao->getFormaPagamento());
    $detalheParcela = Parcela::buscarParcela($parcela1);
    confere($detalheParcela['status'] === 'PAGA' && $detalheParcela['saldo'] === 0.0, 'parcela PAGA, saldo 0');
    confere($detalheParcela['ultima_forma'] === 'CREDITO' && count($detalheParcela['pagamentos']) === 2, 'histórico com 2 pagamentos e forma no código da tela (CREDITO)');
    esperaErro(fn () => (new PagamentoDinheiro($parcela1, '1'))->processarPagamento($admin), 409, 'parcela já paga');

    $recibo = Pagamento::emitirRecibo($idCartao);
    confere($recibo !== null && $recibo['valor'] === 525.0 && $recibo['cliente'] === 'João da Silva', 'recibo emitido');
    Pagamento::estornar($idCartao, 'Cobrança em duplicidade', $admin);
    confere(Pagamento::emitirRecibo($idCartao) === null, 'pagamento estornado não tem recibo');
    confere(Parcela::buscarParcela($parcela1)['status'] === 'PARCIAL', 'depois do estorno a parcela volta a PARCIAL');

    $troco = new PagamentoDinheiro($parcela1, '525.00', valorEntregue: '600.00');
    confere($troco->calcularTroco() === '75.00', 'dinheiro: troco de 75,00');
    $troco->processarPagamento($admin);

    $financeiro = Parcela::listarParcelas(['q' => '52998224725']);
    confere($financeiro['total'] === 2, 'financeiro lista as 2 parcelas do cliente');
    $metricas = $financeiro['metrics'];
    confere(is_int($metricas['em_aberto']) && is_int($metricas['prestes_vencer']) && $metricas['faturamento_mes'] >= 1025.0, 'indicadores no formato do financeiro.js');
    confere(Parcela::listarParcelas(['status' => 'PAGA', 'q' => '52998224725'])['total'] === 1, 'filtro por status PAGA');
    confere(Parcela::listarParcelas(['prioridade' => 'MEDIA', 'q' => '52998224725'])['total'] === 1, 'filtro por prioridade (2ª parcela vence em 30 dias = MEDIA)');

    // ------------------------------------------------------------------
    secao('Manutenção');
    $livre = UnidadeFisica::livresNoPeriodo($produto['id'], $hoje . ' 00:00:00', $em10 . ' 00:00:00')[0];
    $opcoes = Manutencao::opcoes();
    confere(count($opcoes['unidades']) >= 1 && count($opcoes['tipos']) > 0 && count($opcoes['prioridades']) === 4, 'opções do cadastro de manutenção');

    $manutencao = Manutencao::cadastrarManutencao([
        'unidade_id' => $livre, 'tipo' => 'Preventiva', 'prioridade' => 'URGENTE', 'data_prevista' => $hoje,
        'responsavel_usuario_id' => $admin, 'descricao' => 'Troca da fechadura',
    ], $admin);
    confere($manutencao > 0, 'manutenção agendada');
    esperaErro(fn () => Manutencao::cadastrarManutencao(['unidade_id' => $livre, 'tipo' => 'Preventiva', 'prioridade' => 'BAIXA',
        'data_prevista' => $hoje, 'responsavel_usuario_id' => $admin], $admin), 409, 'manutenção duplicada');
    confere(!Produto::verificarDisponibilidade($produto['id'], 1, $hoje, $hoje), 'contêiner em manutenção não fica disponível no dia');

    Manutencao::iniciarManutencao($manutencao, $admin);
    confere(Manutencao::buscarManutencao($manutencao)['status'] === 'EM_MANUTENCAO', 'iniciada (status da tela EM_MANUTENCAO)');
    confere(UnidadeFisica::buscar($livre)['estado_operacional'] === 'MANUTENCAO', 'contêiner em MANUTENCAO');
    $indicadores = Manutencao::listarManutencoes(['prioridade' => 'URGENTE'])['metrics'];
    confere($indicadores['em_manutencao'] >= 1 && $indicadores['urgentes'] >= 1, 'indicadores da manutenção');

    Manutencao::concluirManutencao($manutencao, ['custo' => '150,00'], $admin);
    $concluida = Manutencao::buscarManutencao($manutencao);
    confere($concluida['status'] === 'CONCLUIDA' && $concluida['custo'] === 150.0, 'concluída com custo 150');
    confere(UnidadeFisica::buscar($livre)['estado_operacional'] === 'DISPONIVEL', 'contêiner volta a DISPONIVEL');

    // ------------------------------------------------------------------
    secao('Estoque');
    confere(Produto::atualizarQuantidade($produto['id'], 2, $admin) === 2, 'estoque +2 (5 unidades)');
    confere(Produto::atualizarQuantidade($produto['id'], -1, $admin) === 1, 'estoque −1 (inativa uma disponível)');
    esperaErro(fn () => Produto::atualizarQuantidade($produto['id'], -10, $admin), 409, 'retirar mais do que há livre');

    // ------------------------------------------------------------------
    secao('Dashboard');
    $painel = Dashboard::resumo();
    confere(count($painel['monthly_revenue']) === 12 && $painel['monthly_revenue'][11]['month'] === date('Y-m'), '12 meses no gráfico, terminando no mês atual');
    confere($painel['metrics']['active_contracts'] >= 1 && $painel['metrics']['rented_units'] >= 2, 'indicadores do dashboard');
    // Procura o contrato do teste pelo número (o banco pode ter outros contratos, ex.: dados de exemplo).
    $doTeste = array_values(array_filter($painel['recent_contracts'], fn ($c) => $c['number'] === $gerado['numero_contrato']));
    confere(($doTeste[0]['days'] ?? null) === 10, 'locação do teste aparece nas últimas, com 10 dias');

    // ------------------------------------------------------------------
    secao('Fim do contrato');
    Contrato::finalizarContrato($contratoId, $admin);
    confere(Contrato::buscarContrato($contratoId)['status'] === 'FINALIZADO', 'contrato finalizado');
    confere(Produto::buscarProduto($produto['id'])['alugado'] === 0, 'nenhum contêiner locado depois da devolução');
    Contrato::cancelarContrato($segundo, 'Teste de cancelamento', $admin);
    confere(Contrato::buscarContrato($segundo)['status'] === 'CANCELADO', 'rascunho cancelado');
    esperaErro(fn () => Contrato::cancelarContrato($contratoId, 'x', $admin), 409, 'cancelar contrato finalizado');

    // ------------------------------------------------------------------
    secao('Usuário, senha e sessões');
    confere(Usuario::temPermissao($admin, 'qualquer', 'coisa'), 'cargo ADMIN tem todas as permissões');
    esperaErro(fn () => Usuario::alterarSenha($admin, 'senha-errada', 'NovaSenha123'), 422, 'troca de senha com a atual errada');
    esperaErro(fn () => Usuario::alterarStatus($admin, false, $admin), 422, 'desativar o próprio usuário');
    confere(Usuario::gerarRecuperacaoSenha('naoexiste@x.com') === null, 'recuperação para e-mail inexistente devolve null');
    $token = Usuario::gerarRecuperacaoSenha('admin@casabarretto.local');
    confere(is_string($token) && strlen($token) === 64, 'token de recuperação gerado');
    $sessao = SessaoPersistente::criar($admin);
    confere(SessaoPersistente::validar($sessao) === $admin, '"lembrar de mim" válido');
    Usuario::redefinirSenhaComToken($token, 'NovaSenha123');
    confere(SessaoPersistente::validar($sessao) === null, 'trocar a senha encerra os "lembrar de mim"');
    esperaErro(fn () => Usuario::redefinirSenhaComToken($token, 'OutraSenha123'), 410, 'token de recuperação é de uso único');

    $historico = Auditoria::listar('contratos', $contratoId);
    confere(in_array('GERAR', array_column($historico, 'acao'), true) && in_array('FINALIZAR', array_column($historico, 'acao'), true), 'auditoria registrou GERAR e FINALIZAR');
} catch (Throwable $e) {
    $falhas++;
    echo PHP_EOL . 'ERRO INESPERADO: ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
} finally {
    // Desfaz tudo o que o teste gravou.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo PHP_EOL . ($falhas === 0 ? 'TODOS OS TESTES PASSARAM' : "$falhas FALHA(S)") . ' (alterações desfeitas com ROLLBACK)' . PHP_EOL;
exit($falhas === 0 ? 0 : 1);
