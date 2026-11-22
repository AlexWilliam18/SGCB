<?php /* Diálogo "Consultar disponibilidade" (aberto pelo disponibilidade.js na página Contratos de Locação). Não cria reservas. */ ?>
<dialog class="client-dialog availability-dialog" id="availability-dialog" aria-labelledby="availability-title">
  <div class="detail-heading"><h2 id="availability-title">Consultar disponibilidade</h2><form method="dialog"><button class="detail-close" aria-label="Fechar consulta">×</button></form></div>
  <p>Previsão por contêiner para todo o período. Esta consulta não cria reservas.</p>
  <form id="availability-form" class="availability-form">
    <label for="availability-product">Produto<select id="availability-product" name="produto" required><option value="">Carregando produtos…</option></select></label>
    <label for="availability-start">Data de início desejada<input id="availability-start" name="inicio" type="date" required max="9998-12-31"></label>
    <label for="availability-end">Data de fim prevista<input id="availability-end" name="fim" type="date" required max="9998-12-31"></label>
    <label for="availability-quantity">Quantidade desejada<input id="availability-quantity" name="quantidade" type="number" min="1" step="1" value="1" required></label>
    <button type="submit" class="detail-action primaria">Consultar disponibilidade</button>
  </form>
  <div id="availability-result" role="status" aria-live="polite" aria-atomic="true"></div>
  <button id="availability-retry" class="detail-action" type="button" hidden>Tentar novamente</button>
</dialog>
