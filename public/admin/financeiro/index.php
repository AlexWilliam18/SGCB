<?php
/**
 * Gerenciamento financeiro: indicadores, pesquisa, filtros, paginação e detalhes das parcelas.
 * Por enquanto só front-end: sem /api/financeiro, a tabela mostra o aviso de erro.
 */

require __DIR__ . '/../../../src/bootstrap.php';

Autenticacao::exigirLogin();

// clientes.css (filtros, tabela, paginação, diálogo) e manutencoes.css (cards, filtros com rótulo,
// campo de data) são reaproveitados; financeiro.css só traz o que é próprio desta tela.
$titulo      = 'Financeiro';
$css         = ['dashboard.css', 'clientes.css', 'manutencoes.css', 'financeiro.css'];
$js          = ['painel.js', 'financeiro.js'];
$classeBody  = 'maintenance-page finance-page';   // maintenance-page ativa os estilos do manutencoes.css
$paginaAtual = 'financeiro';

// Caminho das imagens já escapado; também vai para o JS via data-*.
$img = htmlspecialchars(BASE_URL . 'assets/img/');

require __DIR__ . '/../../../includes/head.php';
?>
  <a class="skip-link" href="#finance-main">Ir para o financeiro</a>
<?php require __DIR__ . '/../../../includes/nav.php'; ?>
  <div class="workspace">
    <header class="page-header"><button class="menu-toggle" id="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-sidebar" aria-label="Abrir menu"><span></span><span></span><span></span></button><div class="page-heading"><h1>Gerenciamento de Finanças</h1><p>Consulte, filtre e gerencie as finanças</p></div><div class="date-pill"><img src="<?= $img ?>dashboard/e6378.svg" alt="" width="13" height="13"><time id="today"></time></div></header>
    <main class="maintenance-main finance-main" id="finance-main" data-img-url="<?= $img ?>" tabindex="-1" aria-busy="true">
      <div class="page-intro"><h2>Financeiro</h2></div>
      <!-- Indicadores gerais (preenchidos pelo financeiro.js com data.metrics da API; não mudam com os filtros) -->
      <section class="stats-grid" aria-label="Indicadores financeiros gerais">
        <article class="stat-card" title="Recebimentos confirmados neste mês"><div class="stat-heading"><h2>Faturamento do mês</h2><span class="stat-icon received"><span class="revenue-icon"><img src="<?= $img ?>financeiro/610c9.svg" alt="" width="36" height="25"></span></span></div><p class="stat-value" id="metric-faturamento_mes">—</p><p class="stat-caption" id="metric-pagamentos_recebidos">— pagamentos recebidos</p></article>
        <article class="stat-card" title="Contratos com parcelas não canceladas e saldo a receber"><div class="stat-heading"><h2>Em aberto</h2><span class="stat-icon open"><span class="calendar-icon"><img src="<?= $img ?>manutencoes/fcd3e.png" alt="" width="35" height="35"></span></span></div><p class="stat-value" id="metric-em_aberto">—</p><p class="stat-caption">Contratos</p></article>
        <article class="stat-card" title="Parcelas com saldo pendente e vencimento anterior a hoje"><div class="stat-heading"><h2>Em atraso</h2><span class="stat-icon overdue"><span class="overdue-icon"><img src="<?= $img ?>manutencoes/51c13.png" alt="" width="28" height="28"></span></span></div><p class="stat-value" id="metric-em_atraso">—</p><p class="stat-caption" id="metric-clientes_atraso">— clientes em atraso</p></article>
        <article class="stat-card" title="Contratos com saldo pendente vencendo de hoje até os próximos 7 dias"><div class="stat-heading"><h2>Prestes a vencer</h2><span class="stat-icon upcoming"><span class="upcoming-icon"><img src="<?= $img ?>manutencoes/0e392.png" alt="" width="28" height="24"></span></span></div><p class="stat-value" id="metric-prestes_vencer">—</p><p class="stat-caption">Contratos</p></article>
      </section>
      <!-- Filtros: pesquisa por cliente, prioridade (calculada pelo vencimento), status e dia de vencimento -->
      <form class="filter-card maintenance-filters" id="finance-filters" role="search" aria-label="Pesquisar parcelas financeiras">
        <div class="search-field"><img src="<?= $img ?>clientes/68819.svg" alt="" width="17" height="17"><label class="sr-only" for="finance-search">Nome, CPF, CNPJ ou e-mail</label><input id="finance-search" type="search" name="q" placeholder="Nome, CPF, CNPJ ou e-mail" maxlength="200" autocomplete="off"></div>
        <div class="labeled-filter"><label for="finance-priority">Prioridade</label><div class="select-field"><select id="finance-priority" name="prioridade" aria-describedby="priority-help"><option value="">Todos</option><option value="URGENTE">Urgente</option><option value="ALTA">Alta</option><option value="MEDIA">Média</option><option value="BAIXA">Baixa</option></select><img src="<?= $img ?>clientes/fb51c.svg" alt="" width="10" height="6"></div></div>
        <div class="labeled-filter"><label for="finance-status">Status</label><div class="select-field"><select id="finance-status" name="status"><option value="">Todos</option><option value="ABERTA">Em aberto</option><option value="PARCIAL">Parcial</option><option value="ATRASADA">Em atraso</option><option value="PAGA">Paga</option><option value="CANCELADA">Cancelada</option></select><img src="<?= $img ?>clientes/fb51c.svg" alt="" width="10" height="6"></div></div>
        <div class="labeled-filter date-filter"><label for="finance-date">Período</label><div class="date-input"><img src="<?= $img ?>manutencoes/fcd3e.png" alt="" width="30" height="30"><input id="finance-date" name="data" type="date" min="1000-01-01" max="9999-12-31" aria-label="Dia de vencimento"><span aria-hidden="true">Selecione uma data</span></div></div>
        <button class="sr-only" type="submit">Pesquisar</button>
      </form>
      <!-- Explicação das prioridades para leitores de tela (ligada ao select pelo aria-describedby) -->
      <p class="sr-only" id="priority-help">Prioridade calculada apenas para parcelas com saldo: urgente em atraso; alta vence de hoje a 7 dias; média vence após 7 e até 30 dias; baixa vence após 30 dias. Período seleciona o dia de vencimento. Os indicadores são globais.</p>
      <div class="filter-feedback" id="filter-feedback" hidden><p id="filter-summary">Filtros aplicados à lista de parcelas.</p><button id="clear-finance-filters" type="button">Limpar filtros</button></div>
      <div class="data-notice" id="finance-notice" role="status" hidden><p id="finance-message"></p><button id="retry-finance" type="button">Tentar novamente</button></div>
      <!-- Tabela de parcelas (linhas montadas pelo financeiro.js) e paginação -->
      <section class="card maintenance-card" aria-label="Lista de parcelas"><div class="table-scroll" tabindex="0" role="region" aria-label="Tabela financeira, role para ver todas as colunas"><table class="clients-table maintenance-table finance-table"><caption class="sr-only">Parcelas por cliente e contrato</caption><colgroup><col style="width:20.25%"><col style="width:16.79%"><col style="width:14.49%"><col style="width:10.95%"><col style="width:9.63%"><col style="width:19.5%"><col style="width:8.39%"></colgroup><thead><tr><th scope="col">Cliente</th><th scope="col">Contrato</th><th scope="col">Vencimento</th><th scope="col">Valor</th><th scope="col">Status</th><th scope="col">Última forma de pagamento</th><th scope="col">Ações</th></tr></thead><tbody id="finance-rows"><tr><td colspan="7" class="clients-empty"><div><img src="<?= $img ?>manutencoes/49f98.svg" alt="" width="32" height="32"><p>Carregando parcelas…</p></div></td></tr></tbody></table></div><footer class="table-footer"><p id="page-summary" role="status" aria-live="polite">Carregando…</p><nav class="pagination" aria-label="Páginas do financeiro"><button type="button" id="previous-page" aria-label="Página anterior" disabled><img src="<?= $img ?>clientes/2b1ce.svg" alt="" width="16" height="16"></button><div id="page-numbers"><button class="selected" type="button" aria-current="page" disabled>1</button></div><button type="button" id="next-page" aria-label="Próxima página" disabled><img src="<?= $img ?>clientes/d440b.svg" alt="" width="16" height="16"></button></nav></footer></section>
      <noscript><p class="data-notice">Ative o JavaScript para consultar o financeiro.</p></noscript>
    </main>
  </div>
  <!-- Diálogo "Detalhes da parcela" (aberto pelo botão Ver parcela de cada linha) -->
  <dialog class="client-dialog" id="finance-dialog" aria-labelledby="finance-detail-title"><div class="detail-heading"><h2 id="finance-detail-title">Detalhes da parcela</h2><form method="dialog"><button class="detail-close" aria-label="Fechar parcela">×</button></form></div><div id="finance-detail" aria-live="polite"></div></dialog>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>
