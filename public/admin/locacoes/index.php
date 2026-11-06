<?php
/**
 * Listagem de Contratos de Locação: pesquisa (cliente, CPF/CNPJ ou número), filtro de status,
 * filtro de período (data dentro da locação), paginação e prévia do contrato com impressão/PDF.
 * Os dados vêm de /api/contratos (contratos.js → public/api/index.php → ContratoController → Contrato).
 * Os ícones desta tela são desenhados em CSS (contratos.css), sem arquivos de imagem.
 * O cadastro ("Novo contrato") ainda está em desenvolvimento e abre o aviso padrão do painel.js.
 */

require __DIR__ . '/../../../src/bootstrap.php';

Autenticacao::exigirLogin();

$titulo      = 'Contratos de Locação';
$css         = ['dashboard.css', 'clientes.css', 'acoes.css', 'contratos.css'];
$js          = ['painel.js', 'contratos.js'];
$classeBody  = 'contracts-page';   // ajustes de layout do contratos.css
$paginaAtual = 'locacoes';         // item destacado no menu

require __DIR__ . '/../../../includes/head.php';
?>
  <a class="skip-link" href="#contracts-main">Ir para os contratos</a>
<?php require __DIR__ . '/../../../includes/nav.php'; ?>
  <div class="workspace">
    <!-- Cabeçalho: botão do menu no celular, título e data de hoje (painel.js) -->
    <header class="page-header"><button class="menu-toggle" id="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-sidebar" aria-label="Abrir menu"><span></span><span></span><span></span></button><div class="page-heading"><h1>Contratos de Locação</h1><p>Consulte, filtre e gerencie os contratos</p></div><div class="date-pill"><span class="icon icon-calendar" aria-hidden="true"></span><time id="today"></time></div></header>

    <main class="clients-main contracts-main" id="contracts-main" tabindex="-1" aria-busy="true">
      <!-- Título da seção e botão "Novo contrato" (cadastro ainda em desenvolvimento: abre o aviso) -->
      <div class="page-intro"><div><h2>Contratos de Locação</h2><p>Consulte contratos, abra a prévia em PDF e acompanhe o status das locações.</p></div><button class="new-client" id="new-contract" type="button" data-module="Novo contrato"><span class="icon icon-plus" aria-hidden="true"></span>Novo contrato</button></div>

      <!-- Filtros: pesquisa, status do contrato, data dentro do período e Limpar -->
      <form class="filter-card contracts-filters" id="contract-filters" role="search" aria-label="Pesquisar contratos">
        <div class="search-field"><span class="icon icon-search" aria-hidden="true"></span><label class="sr-only" for="contract-search">Cliente, CPF/CNPJ ou número do contrato</label><input id="contract-search" name="q" type="search" placeholder="Nome, CPF, CNPJ ou nº do contrato" maxlength="200" autocomplete="off"></div>
        <div class="select-field contract-status-filter"><label class="sr-only" for="contract-status">Status do contrato</label><select id="contract-status" name="status"><option value="">Todos os status</option><option value="ATIVO">Ativo</option><option value="RASCUNHO">Rascunho</option><option value="FINALIZADO">Finalizado</option><option value="CANCELADO">Cancelado</option></select><span class="icon icon-chevron" aria-hidden="true"></span></div>
        <div class="period-filter"><label for="contract-date">Período</label><div class="date-field" id="date-field"><span class="icon icon-calendar" aria-hidden="true"></span><input id="contract-date" name="data" type="date"><span class="date-placeholder" id="date-placeholder" aria-hidden="true">Selecione uma data</span></div></div>
        <button class="clear-filters" id="clear-contract-filters" type="button"><span class="icon icon-clear" aria-hidden="true"></span>Limpar</button><button class="sr-only" type="submit">Pesquisar</button>
      </form>

      <!-- Aviso de erro ao carregar a lista -->
      <div class="data-notice" id="contracts-notice" role="status" hidden><p id="contracts-message"></p><button id="retry-contracts" type="button">Tentar novamente</button></div>

      <!-- Tabela de contratos e paginação (linhas montadas pelo contratos.js) -->
      <section class="card clients-card contracts-card" aria-label="Lista de contratos"><div class="table-scroll" tabindex="0" role="region" aria-label="Tabela de contratos, role para ver todas as colunas"><table class="clients-table contracts-table"><caption class="sr-only">Contratos de locação</caption><colgroup><col style="width:14%"><col style="width:20%"><col style="width:11%"><col style="width:11%"><col style="width:8%"><col style="width:13%"><col style="width:11%"><col style="width:12%"></colgroup><thead><tr><th scope="col">Contrato</th><th scope="col">Cliente</th><th scope="col">Início</th><th scope="col">Fim</th><th scope="col">Itens</th><th scope="col">Valor</th><th scope="col">Status</th><th scope="col">Ações</th></tr></thead><tbody id="contract-rows"><tr><td colspan="8" class="clients-empty"><div><span class="icon icon-document" aria-hidden="true"></span><p>Carregando contratos…</p></div></td></tr></tbody></table></div><footer class="table-footer"><p id="page-summary" role="status" aria-live="polite">Carregando…</p><nav class="pagination" aria-label="Páginas de contratos"><button type="button" id="previous-page" aria-label="Página anterior" disabled><span class="icon icon-arrow-left" aria-hidden="true"></span></button><div id="page-numbers"><button class="selected" type="button" aria-current="page" disabled>1</button></div><button type="button" id="next-page" aria-label="Próxima página" disabled><span class="icon icon-arrow-right" aria-hidden="true"></span></button></nav></footer></section>
    </main>
  </div>

  <!-- Diálogo "Prévia do contrato" (aberto pelo número ou pelo botão "Ver" de cada linha); imprime / salva em PDF -->
  <dialog class="client-dialog contract-dialog" id="contract-dialog" aria-labelledby="contract-dialog-title"><div class="detail-heading"><h2 id="contract-dialog-title">Prévia do contrato</h2><form method="dialog"><button class="detail-close" aria-label="Fechar prévia">×</button></form></div><div id="contract-detail" aria-live="polite"></div></dialog>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>
