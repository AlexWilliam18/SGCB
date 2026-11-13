<?php
/**
 * Listagem de usuários: indicadores (total, ativos e inativos), pesquisa, filtros,
 * paginação e diálogo com os dados do usuário (com o botão Ativar/Inativar).
 * Os dados vêm de /api/usuarios (usuarios.js → public/api/index.php → UsuarioController → Usuario).
 */

require __DIR__ . '/../../../src/bootstrap.php';

Autenticacao::exigirLogin();

$titulo       = 'Usuários';
$css          = ['dashboard.css', 'clientes.css', 'acoes.css', 'usuarios.css'];
$js           = ['painel.js', 'acoes.js', 'usuarios.js'];
$fontesExtras = ['Inter:wght@400;700'];   // usada no título e nos cards
$classeBody   = 'users-page';              // ajustes de layout do usuarios.css
$paginaAtual  = 'usuarios';                // item destacado no menu

// Caminhos já escapados; também vão para o JS via data-*.
$img         = htmlspecialchars(BASE_URL . 'assets/img/');
$urlLista    = htmlspecialchars(BASE_URL . 'admin/usuarios/index.php');
$urlCadastro = htmlspecialchars(BASE_URL . 'admin/usuarios/cadastro.php');

require __DIR__ . '/../../../includes/head.php';
?>
  <a class="skip-link" href="#users-main">Ir para os usuários</a>
<?php require __DIR__ . '/../../../includes/nav.php'; ?>
  <div class="workspace">
    <!-- Cabeçalho: botão do menu no celular, título e data de hoje (painel.js) -->
    <header class="page-header"><button class="menu-toggle" id="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-sidebar" aria-label="Abrir menu"><span></span><span></span><span></span></button><div class="page-heading"><h1>Controle de Usuário</h1><p>Gerencie as pessoas com acesso ao sistema</p></div><div class="date-pill"><img src="<?= $icones ?>calendario.svg" alt="" width="13" height="13"><time id="today"></time></div></header>

    <main class="users-main" id="users-main" data-lista-url="<?= $urlLista ?>" data-img-url="<?= $img ?>" tabindex="-1" aria-busy="true">
      <!-- Título da seção e botão "Novo Usuário" (abre cadastro.php) -->
      <div class="users-intro"><div><h2>Usuários</h2></div><a class="new-user" id="new-user" href="<?= $urlCadastro ?>"><img src="<?= $img ?>usuarios/novo-usuario.svg" alt="" width="16" height="16">Novo Usuário</a></div>

      <!-- Indicadores: preenchidos com o "resumo" devolvido pela API -->
      <section class="users-stats" aria-label="Indicadores gerais de usuários">
        <article class="stat-card users-stat"><div class="stat-heading"><h2>Total de usuários</h2><span class="stat-icon total"><img src="<?= $img ?>usuarios/total-usuarios.svg" alt="" width="24" height="24"></span></div><p class="stat-value" id="metric-total">—</p></article>
        <article class="stat-card users-stat"><div class="stat-heading"><h2>Usuários ativos</h2><span class="stat-icon active"><img src="<?= $img ?>usuarios/usuarios-ativos.svg" alt="" width="24" height="24"></span></div><p class="stat-value" id="metric-ativos">—</p></article>
        <article class="stat-card users-stat"><div class="stat-heading"><h2>Usuários inativos</h2><span class="stat-icon inactive"><img src="<?= $img ?>usuarios/usuarios-inativos.svg" alt="" width="24" height="24"></span></div><p class="stat-value" id="metric-inativos">—</p></article>
      </section>

      <!-- Filtros: pesquisa por nome/e-mail, perfil (código do cargo) e status -->
      <form class="filter-card users-filters" id="user-filters" role="search" aria-label="Pesquisar usuários">
        <div class="search-field"><img src="<?= $icones ?>pesquisar.svg" alt="" width="17" height="17"><label class="sr-only" for="user-search">Nome ou e-mail</label><input id="user-search" name="q" type="search" placeholder="Nome ou e-mail" maxlength="200" autocomplete="off"></div>
        <div class="users-labeled-filter"><label for="user-role">Perfil de acesso</label><div class="select-field"><select id="user-role" name="cargo"><option value="">Todos</option><option value="ADMIN">Dono</option><option value="OPERADOR">Operador</option></select><img class="icone icone--seta" src="<?= $icones ?>seta-baixo.svg" alt="" width="10" height="6"></div></div>
        <div class="users-labeled-filter"><label for="user-status">Status</label><div class="select-field"><select id="user-status" name="situacao"><option value="">Todos</option><option value="ativos">Ativos</option><option value="inativos">Inativos</option></select><img class="icone icone--seta" src="<?= $icones ?>seta-baixo.svg" alt="" width="10" height="6"></div></div>
        <!-- Ordem alfabética (A → Z / Z → A); vazio = A → Z, que já é o padrão desta tela -->
        <div class="users-labeled-filter"><label for="user-order">Ordem</label><div class="select-field"><select id="user-order" name="ordem"><option value="">A → Z</option><option value="za">Z → A</option></select><img class="icone icone--seta" src="<?= $icones ?>seta-baixo.svg" alt="" width="10" height="6"></div></div>
        <button class="clear-filters" id="clear-user-filters" type="button"><img src="<?= $icones ?>limpar-filtro.svg" alt="" width="16" height="16">Limpar</button><button class="sr-only" type="submit">Pesquisar</button>
      </form>

      <!-- Avisos: usuário salvo (vindo do cadastro) e erro ao carregar a lista -->
      <div class="data-notice saved-notice" id="saved-notice" role="status" hidden></div>
      <div class="data-notice" id="users-notice" role="status" hidden><p id="users-message"></p><button id="retry-users" type="button">Tentar novamente</button></div>

      <!-- Tabela de usuários e paginação (linhas montadas pelo usuarios.js) -->
      <section class="card users-card" aria-label="Lista de usuários"><div class="table-scroll" tabindex="0" role="region" aria-label="Tabela de usuários, role para ver todas as colunas"><table class="clients-table users-table"><caption class="sr-only">Usuários, perfis e status</caption><colgroup><col style="width:22.63%"><col style="width:21.56%"><col style="width:21.81%"><col style="width:21.48%"><col style="width:12.52%"></colgroup><thead><tr><th scope="col">Usuário</th><th scope="col">E-mail</th><th scope="col">Perfil</th><th scope="col">Status</th><th scope="col">Ações</th></tr></thead><tbody id="user-rows"><tr><td colspan="5" class="clients-empty"><div><img src="<?= $img ?>usuarios/sem-usuarios.svg" alt="" width="32" height="32"><p>Carregando usuários…</p></div></td></tr></tbody></table></div><footer class="table-footer"><p id="page-summary" role="status" aria-live="polite">Carregando…</p><nav class="pagination" aria-label="Páginas de usuários"><button type="button" id="previous-page" aria-label="Página anterior" disabled><img src="<?= $icones ?>anterior.svg" alt="" width="16" height="16"></button><div id="page-numbers"><button class="selected" type="button" aria-current="page" disabled>1</button></div><button type="button" id="next-page" aria-label="Próxima página" disabled><img src="<?= $icones ?>proximo.svg" alt="" width="16" height="16"></button></nav></footer></section>
    </main>
  </div>
  <!-- Diálogo "Dados do usuário" (aberto pelo botão "Ver usuário" de cada linha) -->
  <dialog class="client-dialog" id="user-dialog" aria-labelledby="user-dialog-title"><div class="detail-heading"><h2 id="user-dialog-title">Dados do usuário</h2><form method="dialog"><button class="detail-close" aria-label="Fechar usuário">×</button></form></div><div id="user-detail" aria-live="polite"></div></dialog>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>
