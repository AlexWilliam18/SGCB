<?php
/**
 * admin/produtos/index.php — listagem de produtos (pesquisa, filtros, paginação, detalhes e nova categoria).
 *
 * Finalidade: tela de entrada do módulo Produtos (item "Produtos" do menu lateral).
 *             Por enquanto é só front-end: os dados viriam de /api/produtos e /api/categorias, que serão feitas
 *             na etapa de back-end, então a tabela mostra o aviso "Não foi possível carregar os produtos".
 *             O botão "Novo produto" ainda abre o aviso "em desenvolvimento" (a tela de cadastro virá depois).
 * Depende de: src/bootstrap.php, includes/head.php, includes/nav.php, includes/footer.php,
 *             public/assets/css/dashboard.css + clientes.css + produtos.css,
 *             public/assets/js/painel.js + produtos.js + categorias.js,
 *             imagens de public/assets/img/produtos/, clientes/ e dashboard/.
 * Usado por:  includes/nav.php (menu).
 * Acesso:     somente usuário logado.
 */

require __DIR__ . '/../../../src/bootstrap.php';

// Página protegida: sem usuário na sessão, redireciona para login.php.
Autenticacao::exigirLogin();

// Variáveis lidas por includes/head.php, includes/nav.php e includes/footer.php.
// clientes.css é reaproveitado: filtros, tabela, paginação e diálogo de detalhes têm o mesmo visual.
$titulo      = 'Produtos';
$css         = ['dashboard.css', 'clientes.css', 'produtos.css'];
$js          = ['painel.js', 'produtos.js', 'categorias.js'];
$classeBody  = 'products-page';
$paginaAtual = 'produtos';

// Caminhos já escapados para uso direto nos atributos (também repassados ao JS via data-*).
$img      = htmlspecialchars(BASE_URL . 'assets/img/');
$urlLista = htmlspecialchars(BASE_URL . 'admin/produtos/index.php');

require __DIR__ . '/../../../includes/head.php';
?>
  <a class="skip-link" href="#products-main">Ir para os produtos</a>
<?php require __DIR__ . '/../../../includes/nav.php'; ?>
  <div class="workspace">
    <header class="page-header"><button class="menu-toggle" id="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-sidebar" aria-label="Abrir menu"><span></span><span></span><span></span></button><div class="page-heading"><h1>Produtos</h1><p>Gerencie contêineres e demais itens para locação</p></div><div class="date-pill"><img src="<?= $img ?>dashboard/e6378.svg" alt="" width="13" height="13"><time id="today"></time></div></header>
    <main class="products-main" id="products-main" data-lista-url="<?= $urlLista ?>" data-img-url="<?= $img ?>" tabindex="-1" aria-busy="true">
      <!-- Título, botão do diálogo de categoria e "Novo produto" (data-module: aviso de em desenvolvimento do painel.js) -->
      <div class="page-intro"><div><h2>Produtos</h2><p>Controle os contêineres, valores e disponibilidade de estoque.</p></div><div class="intro-actions"><button class="category-button" id="open-category" type="button" aria-haspopup="dialog" aria-controls="category-dialog">+ Categoria</button><button class="new-client" id="new-product" type="button" data-module="Cadastro de produto"><img src="<?= $img ?>produtos/novo-produto.svg" alt="" width="16" height="16">Novo produto</button></div></div>
      <!-- Filtros: pesquisa por nome/código, status e categoria (as categorias vêm da API) -->
      <form class="filter-card" id="product-filters" role="search" aria-label="Pesquisar produtos">
        <div class="search-field"><img src="<?= $img ?>clientes/68819.svg" alt="" width="17" height="17"><label class="sr-only" for="product-search">Nome ou código</label><input id="product-search" name="q" type="search" placeholder="Nome ou Código" maxlength="200" autocomplete="off"></div>
        <div class="select-field product-status"><label class="sr-only" for="product-status">Status</label><select id="product-status" name="status"><option value="">Todos os status</option><option value="ativo">Ativos</option><option value="inativo">Inativos</option></select><img src="<?= $img ?>clientes/fb51c.svg" alt="" width="10" height="6"></div>
        <div class="select-field product-category"><label class="sr-only" for="product-category">Categoria</label><select id="product-category" name="categoria"><option value="">Categorias</option></select><img src="<?= $img ?>clientes/fb51c.svg" alt="" width="10" height="6"></div>
        <button class="clear-filters" id="clear-filters" type="button"><img src="<?= $img ?>clientes/ac3e7.svg" alt="" width="16" height="16">Limpar</button><button class="sr-only" type="submit">Pesquisar</button>
      </form>
      <div class="data-notice category-success" id="category-success" role="status" hidden></div>
      <div class="data-notice" id="products-notice" role="status" hidden><p id="products-message"></p><button id="retry-products" type="button">Tentar novamente</button></div>
      <!-- Tabela de produtos (linhas montadas pelo produtos.js) e paginação -->
      <section class="card clients-card" aria-label="Lista de produtos"><div class="table-scroll" tabindex="0" role="region" aria-label="Tabela de produtos, role para ver todas as colunas"><table class="clients-table products-table"><caption class="sr-only">Produtos cadastrados e estoque atual</caption><colgroup><col style="width:13.23%"><col style="width:14.91%"><col style="width:10.77%"><col style="width:10.34%"><col style="width:15.21%"><col style="width:13.37%"><col style="width:11.33%"><col style="width:10.84%"></colgroup><thead><tr><th scope="col">Produto</th><th scope="col">Categoria</th><th scope="col">Diária</th><th scope="col">Total</th><th scope="col">Disponível</th><th scope="col">Alugado</th><th scope="col">Status</th><th scope="col">Ações</th></tr></thead><tbody id="product-rows"><tr><td colspan="8" class="clients-empty"><div><img src="<?= $img ?>produtos/sem-produtos.svg" alt="" width="32" height="32"><p>Carregando produtos…</p></div></td></tr></tbody></table></div><footer class="table-footer"><p id="page-summary" role="status" aria-live="polite">Carregando…</p><nav class="pagination" aria-label="Páginas de produtos"><button type="button" id="previous-page" aria-label="Página anterior" disabled><img src="<?= $img ?>clientes/2b1ce.svg" alt="" width="16" height="16"></button><div id="page-numbers"><button class="selected" type="button" aria-current="page" disabled>1</button></div><button type="button" id="next-page" aria-label="Próxima página" disabled><img src="<?= $img ?>clientes/d440b.svg" alt="" width="16" height="16"></button></nav></footer></section>
    </main>
  </div>
  <!-- Diálogo "Cadastre nova Categoria" (categorias.js) -->
  <dialog class="category-dialog" id="category-dialog" aria-labelledby="category-title">
    <form id="category-form" novalidate>
      <h2 id="category-title">Cadastre nova Categoria</h2>
      <label for="category-name">Nome:</label>
      <input id="category-name" name="nome" type="text" placeholder="Categoria" maxlength="100" autocomplete="off" required autofocus aria-describedby="category-error">
      <p class="category-error" id="category-error" role="alert" hidden></p>
      <div class="category-message" id="category-message" role="status" hidden><p id="category-message-text"></p><button id="retry-category" type="button" hidden>Tentar novamente</button></div>
      <div class="category-actions"><button class="category-cancel" id="cancel-category" type="button">Cancelar</button><button class="category-save" id="save-category" type="submit" disabled>Salvar</button></div>
    </form>
  </dialog>
  <!-- Diálogo "Dados do produto" (aberto pelo botão Ver produto de cada linha) -->
  <dialog class="client-dialog" id="product-dialog" aria-labelledby="product-dialog-title"><div class="detail-heading"><h2 id="product-dialog-title">Dados do produto</h2><form method="dialog"><button class="detail-close" aria-label="Fechar produto">×</button></form></div><div id="product-detail" aria-live="polite"></div></dialog>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>
