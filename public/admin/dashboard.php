<?php
/**
 * Visão Geral: indicadores das locações, primeira tela depois do login.
 * Os números são carregados pelo dashboard.js.
 */

require __DIR__ . '/../../src/bootstrap.php';

Autenticacao::exigirLogin();

$titulo = 'Visão Geral';
$css    = ['dashboard.css'];
$js     = ['painel.js', 'dashboard.js'];

// Item destacado no menu lateral (chave de $itensMenu em includes/nav.php).
$paginaAtual = 'visao-geral';

$img = htmlspecialchars(BASE_URL . 'assets/img/');

require __DIR__ . '/../../includes/head.php';
?>
  <a class="skip-link" href="#dashboard">Ir para o painel</a>
<?php require __DIR__ . '/../../includes/nav.php'; ?>
  <div class="workspace">
    <header class="page-header"><button class="menu-toggle" id="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-sidebar" aria-label="Abrir menu"><span></span><span></span><span></span></button><div class="page-heading"><h1>Visão Geral</h1><p>Visão geral das locações e indicadores</p></div><div class="date-pill"><img src="<?= $img ?>dashboard/e6378.svg" alt="" width="13" height="13"><time id="today"></time></div></header>
    <main class="dashboard" id="dashboard" tabindex="-1" aria-busy="true">
      <div class="data-notice" id="data-notice" role="status" hidden><p id="notice-message"></p><button id="retry-data" type="button">Tentar novamente</button></div>
      <section class="stats-grid" aria-label="Indicadores das locações">
        <article class="stat-card"><div class="stat-heading"><h2>Faturamento total</h2><span class="stat-icon"><img src="<?= $img ?>dashboard/d69a9.svg" alt="" width="18" height="18"></span></div><p class="stat-value" id="revenue-total">—</p><p class="stat-caption">Contratos não cancelados</p></article>
        <article class="stat-card"><div class="stat-heading"><h2>Contratos ativos</h2><span class="stat-icon"><img src="<?= $img ?>dashboard/261d2.svg" alt="" width="18" height="18"></span></div><p class="stat-value" id="active-contracts">—</p><p class="stat-caption">Locações em andamento</p></article>
        <article class="stat-card"><div class="stat-heading"><h2>Produtos disponíveis</h2><span class="stat-icon"><img src="<?= $img ?>dashboard/3d53b.svg" alt="" width="18" height="18"></span></div><p class="stat-value" id="available-units">—</p><p class="stat-caption">Unidades livres para locação</p></article>
        <article class="stat-card"><div class="stat-heading"><h2>Produtos alugados</h2><span class="stat-icon"><img src="<?= $img ?>dashboard/3f812.svg" alt="" width="18" height="18"></span></div><p class="stat-value" id="rented-units">—</p><p class="stat-caption">Unidades atualmente comprometidas</p></article>
      </section>
      <section class="charts-grid" aria-label="Evolução das locações nos últimos 12 meses">
        <article class="card chart-card"><div class="card-heading"><h2>Faturamento mensal</h2><span>Últimos 12 meses</span></div><div class="chart" id="revenue-chart" role="group" aria-label="Gráfico de faturamento mensal"><div class="chart-axis" aria-hidden="true"><span>1,0</span><span>0,8</span><span>0,6</span><span>0,4</span><span>0,2</span><span>0</span></div><div class="chart-plot"><div class="gridlines" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></div><div class="chart-bars"></div></div><p class="sr-only chart-description">Carregando faturamento mensal.</p></div></article>
        <article class="card chart-card"><div class="card-heading"><h2>Quantidade de locações</h2><span>Últimos 12 meses</span></div><div class="chart" id="rentals-chart" role="group" aria-label="Gráfico de quantidade de locações"><div class="chart-axis" aria-hidden="true"><span>1</span><span>0</span></div><div class="chart-plot"><div class="gridlines" aria-hidden="true"><i></i><i></i></div><div class="chart-bars"></div></div><p class="sr-only chart-description">Carregando quantidade de locações.</p></div></article>
      </section>
      <section class="rankings-grid" aria-label="Rankings de locações">
        <article class="card ranking-card"><div class="card-heading"><h2>Produtos mais alugados</h2><span>Top 5</span></div><div class="ranking-content" id="top-products"><p class="ranking-empty">Carregando dados…</p></div></article>
        <article class="card ranking-card"><div class="card-heading"><h2>Clientes com mais locações</h2><span>Top 5</span></div><div class="ranking-content" id="top-clients"><p class="ranking-empty">Carregando dados…</p></div></article>
      </section>
      <section class="card recent-card" aria-labelledby="recent-title"><div class="recent-heading"><h2 id="recent-title">15 locações mais recentes</h2><button class="outline-button" type="button" data-module="Contratos de Locação">Ver todas</button></div><div class="table-scroll"><table><caption class="sr-only">Contratos de locação mais recentes</caption><colgroup><col class="col-contract"><col class="col-client"><col class="col-start"><col class="col-end"><col class="col-days"><col class="col-value"><col class="col-status"></colgroup><thead><tr><th scope="col">Contrato</th><th scope="col">Cliente</th><th scope="col">Início</th><th scope="col">Fim</th><th scope="col">Dias</th><th scope="col">Valor</th><th scope="col">Status</th></tr></thead><tbody id="recent-contracts"><tr class="empty-row"><td colspan="7">Carregando locações…</td></tr></tbody></table></div></section>
      <noscript><p class="data-notice">Ative o JavaScript para carregar os indicadores do painel.</p></noscript>
    </main>
  </div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
