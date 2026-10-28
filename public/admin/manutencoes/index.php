<?php
/**
 * Gerenciamento de manutenções: indicadores, pesquisa, filtros, paginação e detalhes.
 * Por enquanto só front-end: sem /api/manutencoes, a tabela mostra o aviso de erro.
 * "Nova manutenção" ainda abre o aviso "em desenvolvimento".
 */

require __DIR__ . '/../../../src/bootstrap.php';

Autenticacao::exigirLogin();

// clientes.css é reaproveitado: filtros, tabela, paginação e diálogo de detalhes têm o mesmo visual.
$titulo      = 'Manutenções';
$css         = ['dashboard.css', 'clientes.css', 'manutencoes.css'];
$js          = ['painel.js', 'manutencoes.js'];
$classeBody  = 'maintenance-page';
$paginaAtual = 'manutencoes';

// Caminho das imagens já escapado; também vai para o JS via data-*.
$img = htmlspecialchars(BASE_URL . 'assets/img/');

require __DIR__ . '/../../../includes/head.php';
?>
  <a class="skip-link" href="#maintenance-main">Ir para as manutenções</a>
<?php require __DIR__ . '/../../../includes/nav.php'; ?>
  <div class="workspace">
    <header class="page-header"><button class="menu-toggle" id="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-sidebar" aria-label="Abrir menu"><span></span><span></span><span></span></button><div class="page-heading"><h1>Gerenciamento de Manutenções de Contêineres</h1><p>Consulte, filtre e gerencie as manutenções</p></div><div class="date-pill"><img src="<?= $img ?>dashboard/e6378.svg" alt="" width="13" height="13"><time id="today"></time></div></header>
    <main class="maintenance-main" id="maintenance-main" data-img-url="<?= $img ?>" tabindex="-1" aria-busy="true">
      <!-- Título e "Nova manutenção" (data-module: aviso de em desenvolvimento do painel.js) -->
      <div class="page-intro"><h2>Manutenções</h2><button class="new-client" id="new-maintenance" type="button" data-module="Nova manutenção"><img src="<?= $img ?>manutencoes/bfafb.svg" alt="" width="16" height="16">Nova manutenção</button></div>
      <!-- Indicadores (preenchidos pelo manutencoes.js com data.metrics da API) -->
      <section class="stats-grid" aria-label="Indicadores gerais de manutenção">
        <article class="stat-card"><div class="stat-heading"><h2>Em manutenção</h2><span class="stat-icon ongoing"><span class="metric-image"><img src="<?= $img ?>manutencoes/27de2.png" alt=""></span></span></div><p class="stat-value" id="metric-em_manutencao">—</p><p class="stat-caption">Contêineres atualmente</p></article>
        <article class="stat-card"><div class="stat-heading"><h2>Manutenções agendadas</h2><span class="stat-icon scheduled"><span class="metric-image"><img src="<?= $img ?>manutencoes/fcd3e.png" alt=""></span></span></div><p class="stat-value" id="metric-agendadas">—</p><p class="stat-caption">para próximos 30 dias</p></article>
        <article class="stat-card"><div class="stat-heading"><h2>Urgentes</h2><span class="stat-icon urgent"><span class="metric-image"><img src="<?= $img ?>manutencoes/51c13.png" alt=""></span></span></div><p class="stat-value" id="metric-urgentes">—</p><p class="stat-caption">necessitam de atenção</p></article>
        <article class="stat-card"><div class="stat-heading"><h2>Concluídas no mês</h2><span class="stat-icon completed"><span class="metric-image"><img src="<?= $img ?>manutencoes/0e392.png" alt=""></span></span></div><p class="stat-value" id="metric-concluidas">—</p><p class="stat-caption">manutenções realizadas</p></article>
      </section>
      <!-- Filtros: pesquisa, prioridade, localidade (as localidades vêm da API) e data prevista -->
      <form class="filter-card maintenance-filters" id="maintenance-filters" role="search" aria-label="Pesquisar manutenções">
        <div class="search-field"><img src="<?= $img ?>clientes/68819.svg" alt="" width="17" height="17"><label class="sr-only" for="maintenance-search">Pesquisar contêiner por nome ou código</label><input id="maintenance-search" type="search" name="q" placeholder="Pesquisar contêiner" maxlength="200" autocomplete="off"></div>
        <div class="labeled-filter"><label for="maintenance-priority">Prioridade</label><div class="select-field"><select id="maintenance-priority" name="prioridade"><option value="">Todos</option><option value="BAIXA">Baixa</option><option value="MEDIA">Média</option><option value="ALTA">Alta</option><option value="URGENTE">Urgente</option></select><img src="<?= $img ?>clientes/fb51c.svg" alt="" width="10" height="6"></div></div>
        <div class="labeled-filter"><label for="maintenance-location">Localidade</label><div class="select-field"><select id="maintenance-location" name="localidade"><option value="">Todos</option></select><img src="<?= $img ?>clientes/fb51c.svg" alt="" width="10" height="6"></div></div>
        <div class="labeled-filter date-filter"><label for="maintenance-date">Período</label><div class="date-input"><img src="<?= $img ?>manutencoes/fcd3e.png" alt="" width="30" height="30"><input id="maintenance-date" name="data" type="date" min="0001-01-01" max="9999-12-30" aria-label="Data prevista da manutenção"><span aria-hidden="true">Selecione uma data</span></div></div>
        <button class="sr-only" type="submit">Pesquisar</button>
      </form>
      <div class="filter-feedback" id="filter-feedback" hidden><p>Filtros aplicados à lista de manutenções.</p><button id="clear-maintenance-filters" type="button">Limpar filtros</button></div>
      <div class="data-notice" id="maintenance-notice" role="status" hidden><p id="maintenance-message"></p><button id="retry-maintenance" type="button">Tentar novamente</button></div>
      <!-- Tabela de manutenções (linhas montadas pelo manutencoes.js) e paginação -->
      <section class="card maintenance-card" aria-label="Lista de manutenções"><div class="table-scroll" tabindex="0" role="region" aria-label="Tabela de manutenções, role para ver todas as colunas"><table class="clients-table maintenance-table"><caption class="sr-only">Manutenções por contêiner e unidade física</caption><colgroup><col style="width:20.25%"><col style="width:16.79%"><col style="width:14.49%"><col style="width:10.95%"><col style="width:13.75%"><col style="width:14.81%"><col style="width:8.96%"></colgroup><thead><tr><th scope="col">Contêineres</th><th scope="col">Código</th><th scope="col">Localidade</th><th scope="col">Status</th><th scope="col">Prioridade</th><th scope="col">Última vistoria</th><th scope="col">Ações</th></tr></thead><tbody id="maintenance-rows"><tr><td colspan="7" class="clients-empty"><div><img src="<?= $img ?>manutencoes/49f98.svg" alt="" width="32" height="32"><p>Carregando manutenções…</p></div></td></tr></tbody></table></div><footer class="table-footer"><p id="page-summary" role="status" aria-live="polite">Carregando…</p><nav class="pagination" aria-label="Páginas de manutenções"><button type="button" id="previous-page" aria-label="Página anterior" disabled><img src="<?= $img ?>clientes/2b1ce.svg" alt="" width="16" height="16"></button><div id="page-numbers"><button class="selected" type="button" aria-current="page" disabled>1</button></div><button type="button" id="next-page" aria-label="Próxima página" disabled><img src="<?= $img ?>clientes/d440b.svg" alt="" width="16" height="16"></button></nav></footer></section>
      <noscript><p class="data-notice">Ative o JavaScript para consultar as manutenções.</p></noscript>
    </main>
  </div>
  <!-- Diálogo "Dados da manutenção" (aberto pelo botão Ver de cada linha) -->
  <dialog class="client-dialog" id="maintenance-dialog" aria-labelledby="maintenance-detail-title"><div class="detail-heading"><h2 id="maintenance-detail-title">Dados da manutenção</h2><form method="dialog"><button class="detail-close" aria-label="Fechar manutenção">×</button></form></div><div id="maintenance-detail" aria-live="polite"></div></dialog>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>
