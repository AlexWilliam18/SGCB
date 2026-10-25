<?php
/**
 * admin/clientes/index.php — listagem de clientes (pesquisa, filtros, paginação e detalhes).
 *
 * Finalidade: tela de entrada do módulo Clientes (item "Clientes" do menu lateral).
 *             Por enquanto é só front-end: os dados viriam de /api/clientes, que será feita na etapa de back-end,
 *             então a tabela mostra o aviso "Não foi possível carregar os clientes".
 * Depende de: src/bootstrap.php, includes/head.php, includes/nav.php, includes/footer.php,
 *             public/assets/css/dashboard.css + clientes.css, public/assets/js/painel.js + clientes.js,
 *             imagens de public/assets/img/clientes/ e public/assets/img/dashboard/.
 * Usado por:  includes/nav.php (menu) e public/admin/clientes/cadastro.php (Voltar/Cancelar/após salvar).
 * Acesso:     somente usuário logado.
 */

require __DIR__ . '/../../../src/bootstrap.php';

// Página protegida: sem usuário na sessão, redireciona para login.php.
Autenticacao::exigirLogin();

// Variáveis lidas por includes/head.php, includes/nav.php e includes/footer.php.
$titulo      = 'Clientes';
$css         = ['dashboard.css', 'clientes.css'];
$js          = ['painel.js', 'clientes.js'];
$paginaAtual = 'clientes';

// Caminhos já escapados para uso direto nos atributos (também repassados ao JS via data-*).
$img         = htmlspecialchars(BASE_URL . 'assets/img/');
$urlLista    = htmlspecialchars(BASE_URL . 'admin/clientes/index.php');
$urlCadastro = htmlspecialchars(BASE_URL . 'admin/clientes/cadastro.php');

require __DIR__ . '/../../../includes/head.php';
?>
  <a class="skip-link" href="#clients-main">Ir para o painel</a>
<?php require __DIR__ . '/../../../includes/nav.php'; ?>
  <div class="workspace">
    <header class="page-header"><button class="menu-toggle" id="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-sidebar" aria-label="Abrir menu"><span></span><span></span><span></span></button><div class="page-heading"><h1>Clientes</h1><p>Cadastros, pesquisa e manutenção de clientes</p></div><div class="date-pill"><img src="<?= $img ?>dashboard/e6378.svg" alt="" width="13" height="13"><time id="today"></time></div></header>
    <main class="clients-main" id="clients-main" data-lista-url="<?= $urlLista ?>" data-img-url="<?= $img ?>" tabindex="-1" aria-busy="true">
      <div class="page-intro"><div><h2>Clientes</h2><p>Pesquise por nome, CPF ou CNPJ e gerencie os cadastros.</p></div><a class="new-client" id="new-client" href="<?= $urlCadastro ?>"><img src="<?= $img ?>clientes/99130.svg" alt="" width="16" height="16">Novo cliente</a></div>
      <form class="filter-card" id="client-filters" role="search" aria-label="Pesquisar clientes">
        <div class="search-field"><img src="<?= $img ?>clientes/68819.svg" alt="" width="17" height="17"><label class="sr-only" for="client-search">Nome, CPF ou CNPJ</label><input id="client-search" name="q" type="search" placeholder="Nome, CPF ou CNPJ" maxlength="200" autocomplete="off"></div>
        <div class="select-field status-filter"><label class="sr-only" for="client-status">Situação</label><select id="client-status" name="situacao"><option value="">Situação</option><option value="ativos">Ativos</option><option value="inativos">Inativos</option><option value="todos">Todos</option></select><img src="<?= $img ?>clientes/fb51c.svg" alt="" width="10" height="6"></div>
        <div class="select-field state-filter"><label class="sr-only" for="client-state">Estado</label><select id="client-state" name="uf"><option value="">Todos os estados</option></select><img src="<?= $img ?>clientes/fb51c.svg" alt="" width="10" height="6"></div>
        <div class="select-field city-filter"><label class="sr-only" for="client-city">Cidade</label><select id="client-city" name="cidade"><option value="">Cidades</option></select></div>
        <button class="clear-filters" id="clear-filters" type="button"><img src="<?= $img ?>clientes/ac3e7.svg" alt="" width="16" height="16">Limpar</button><button class="sr-only" type="submit">Pesquisar</button>
      </form>
      <div class="data-notice saved-notice" id="saved-notice" role="status" hidden></div>
      <div class="data-notice" id="clients-notice" role="status" hidden><p id="clients-message"></p><button id="retry-clients" type="button">Tentar novamente</button></div>
      <section class="card clients-card" aria-label="Lista de clientes"><div class="table-scroll"><table class="clients-table"><caption class="sr-only">Clientes cadastrados</caption><colgroup><col style="width:14.4%"><col style="width:17.2%"><col style="width:16.2%"><col style="width:13.6%"><col style="width:8.4%"><col style="width:17.2%"><col style="width:13%"></colgroup><thead><tr><th scope="col">Cliente</th><th scope="col">CPF / CNPJ</th><th scope="col">Telefone</th><th scope="col">Cidade</th><th scope="col">UF</th><th scope="col">Cadastro</th><th scope="col">Ações</th></tr></thead><tbody id="client-rows"><tr><td colspan="7" class="clients-empty"><div><img src="<?= $img ?>clientes/9233e.svg" alt="" width="32" height="32"><p>Carregando clientes…</p></div></td></tr></tbody></table></div><footer class="table-footer"><p id="page-summary" role="status" aria-live="polite">Carregando…</p><nav class="pagination" aria-label="Páginas de clientes"><button type="button" id="previous-page" aria-label="Página anterior" disabled><img src="<?= $img ?>clientes/2b1ce.svg" alt="" width="16" height="16"></button><div id="page-numbers"><button class="selected" type="button" aria-current="page" disabled>1</button></div><button type="button" id="next-page" aria-label="Próxima página" disabled><img src="<?= $img ?>clientes/d440b.svg" alt="" width="16" height="16"></button></nav></footer></section>
    </main>
  </div>
  <dialog class="client-dialog" id="client-dialog" aria-labelledby="client-dialog-title"><div class="detail-heading"><h2 id="client-dialog-title">Dados do cliente</h2><form method="dialog"><button class="detail-close" aria-label="Fechar cadastro">×</button></form></div><div id="client-detail" aria-live="polite"></div></dialog>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>
