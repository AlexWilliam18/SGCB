<?php
/**
 * Cadastro de contrato de locação ("Novo contrato").
 * Etapas: cliente, local de entrega, período, produtos (com os contêineres escolhidos), condições
 * comerciais e observações; o resumo ao lado mostra o valor calculado pelo servidor.
 * cadastro-contrato.js busca as opções em /api/contratos/opcoes, os dados do cliente em /api/clientes/{id},
 * os contêineres livres em /api/contratos/unidades, o valor em /api/contratos/calcular
 * e grava em /api/contratos (POST, com o token CSRF). Os ícones da tela são feitos em CSS.
 */

require __DIR__ . '/../../../src/bootstrap.php';

Autenticacao::exigirLogin();

$titulo       = 'Novo contrato';
$css          = ['dashboard.css', 'contratos.css', 'cadastro-contrato.css'];
$js           = ['painel.js', 'cadastro-contrato.js'];
$fontesExtras = ['Inter:wght@400;700'];   // usada no formulário
$classeBody   = 'contract-form-page';      // ajustes de layout do cadastro-contrato.css
$paginaAtual  = 'locacoes';                // o cadastro pertence ao módulo Contratos no menu

// Caminho da listagem (botão Voltar, "Descartar e sair" e "Visualizar documento").
$urlLista = htmlspecialchars(BASE_URL . 'admin/locacoes/index.php');
// Ícones SVG oficiais (classe .icone, tamanhos no dashboard.css).
$icones   = htmlspecialchars(BASE_URL . 'assets/img/icones/');

require __DIR__ . '/../../../includes/head.php';
?>
  <a class="skip-link" href="#contract-form-main">Ir para o formulário</a>
<?php require __DIR__ . '/../../../includes/nav.php'; ?>
  <div class="workspace">
    <!-- Cabeçalho: botão do menu no celular, título e data de hoje (painel.js) -->
    <header class="page-header"><button class="menu-toggle" id="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-sidebar" aria-label="Abrir menu"><span></span><span></span><span></span></button><div class="page-heading"><h1>Contratos de Locação</h1><p>Cadastro de contrato</p></div><div class="date-pill"><img class="icone icone--p" src="<?= $icones ?>calendario.svg" alt="" width="13" height="13"><time id="today"></time></div></header>

    <main class="contract-form-main" id="contract-form-main" data-lista-url="<?= $urlLista ?>" tabindex="-1">
      <!-- Título, caminho e botão Voltar -->
      <div class="contract-intro">
        <div><p class="contract-breadcrumb">CONTRATOS DE LOCAÇÃO / NOVO CONTRATO</p><h2>Novo contrato</h2><p>Preencha os dados da locação e confira o resumo antes de salvar.</p></div>
        <a class="outline-button" href="<?= $urlLista ?>">← Voltar</a>
      </div>

      <!-- Aviso quando as opções (clientes, produtos, condições) não carregam -->
      <div class="data-notice" id="options-notice" role="status" hidden><p id="options-message"></p><button id="retry-options" type="button">Tentar novamente</button></div>

      <form id="contract-form" class="contract-layout" novalidate>
        <div class="contract-fields">
          <!-- 1. Cliente: dados carregados do cadastro e responsável pela assinatura (pessoa jurídica) -->
          <section class="contract-card" aria-labelledby="client-title">
            <div class="step-heading"><span class="step-number" aria-hidden="true">1</span><h3 id="client-title">Cliente</h3></div>
            <p class="section-help">Selecione um cliente para carregar seus dados cadastrais e endereços.</p>
            <label class="contract-field client-search">Cliente *
              <select id="client" name="client" required><option value="">Carregando clientes…</option></select>
            </label>
            <div class="client-data" id="client-data" hidden>
              <div class="client-data-heading"><strong id="client-name"></strong><span class="source-tag" id="client-type"></span></div>
              <dl class="client-data-grid">
                <div><dt>Nome fantasia</dt><dd id="client-trade"></dd></div>
                <div><dt id="client-document-label">CPF / CNPJ</dt><dd id="client-document"></dd></div>
                <div><dt>Cidade / UF</dt><dd id="client-city"></dd></div>
                <div><dt>Telefone</dt><dd id="client-phone"></dd></div>
                <div class="data-wide"><dt>E-mail</dt><dd id="client-email"></dd></div>
                <div><dt>Nacionalidade</dt><dd id="client-nationality"></dd></div>
                <div><dt>Profissão</dt><dd id="client-profession"></dd></div>
              </dl>
              <div class="registered-address"><span>Endereço cadastral do locatário</span><p id="registered-address"></p></div>
            </div>
            <p class="selection-empty" id="client-empty">Selecione um cliente para conferir seus dados.</p>
            <fieldset class="signatory-fields" id="signatory-fields" hidden>
              <legend>Responsável pela assinatura <span class="optional-label">· opcional</span></legend>
              <p class="field-hint">Identifique a pessoa que assinará pela empresa, se necessário.</p>
              <div class="field-grid two-columns"><label class="contract-field">Nome completo<input name="signerName" maxlength="100" autocomplete="off"></label><label class="contract-field">CPF<input name="signerDocument" maxlength="14" inputmode="numeric" placeholder="000.000.000-00" autocomplete="off"></label></div>
            </fieldset>
          </section>

          <!-- 2. Local de entrega: cartões com os endereços do cliente + campos editáveis só para este contrato -->
          <section class="contract-card" aria-labelledby="delivery-title">
            <div class="step-heading"><span class="step-number" aria-hidden="true">2</span><h3 id="delivery-title">Local de entrega / instalação</h3></div>
            <p class="section-help">Escolha um endereço do cliente. Ajustes feitos aqui valem só para este contrato.</p>
            <fieldset class="address-options" id="address-options"><legend class="sr-only">Endereço para entrega</legend></fieldset>
            <p class="use-client-address" id="use-client-address" hidden><span class="icon icon-check" aria-hidden="true"></span>Usar endereço do cliente</p>
            <div class="field-grid address-grid">
              <label class="contract-field">CEP *<input name="deliveryPostalCode" required maxlength="9" inputmode="numeric" placeholder="00000-000"></label>
              <label class="contract-field street-field">Rua *<input name="deliveryStreet" required maxlength="200" autocomplete="off"></label>
              <label class="contract-field">Número *<input name="deliveryNumber" required maxlength="20" autocomplete="off"></label>
              <label class="contract-field">Bairro *<input name="deliveryDistrict" required maxlength="100" autocomplete="off"></label>
            </div>
            <div class="field-grid address-bottom-grid">
              <label class="contract-field">Cidade *<input name="deliveryCity" required maxlength="100" autocomplete="off"></label>
              <label class="contract-field">Estado *<select name="deliveryState" required></select></label>
              <label class="contract-field">Complemento<input name="deliveryComplement" maxlength="150" placeholder="Apartamento, bloco…"></label>
              <label class="contract-field">Referência<input name="deliveryReference" maxlength="150" placeholder="Ponto de referência"></label>
            </div>
          </section>

          <!-- 3. Período: números automáticos, datas, periodicidade e status inicial -->
          <section class="contract-card" aria-labelledby="period-title">
            <div class="step-heading"><span class="step-number" aria-hidden="true">3</span><h3 id="period-title">Período e identificação</h3></div>
            <p class="section-help">Defina as datas, a frequência da cobrança e se o contrato já será gerado.</p>
            <div class="field-grid four-columns">
              <label class="contract-field">Nº da locação<input name="rentalNumber" value="Automático" readonly></label>
              <label class="contract-field">Nº do contrato<input name="contractNumber" value="Automático" readonly></label>
              <label class="contract-field">Data de emissão<input name="issueDate" type="date" readonly></label>
              <label class="contract-field">Prazo da locação<select name="term"><option value="DETERMINADO">Determinado</option><option value="INDETERMINADO">Indeterminado</option></select></label>
              <label class="contract-field">Data inicial *<input name="start" type="date" required></label>
              <label class="contract-field"><span id="end-label">Data final *</span><input name="end" type="date" required aria-describedby="end-help"></label>
              <label class="contract-field">Periodicidade *<select name="billing" required><option value="DIARIA">Diária</option><option value="SEMANAL">Semanal</option><option value="QUINZENAL">Quinzenal</option><option value="MENSAL" selected>Mensal</option></select></label>
              <label class="contract-field">Status inicial<select name="status"><option value="ATIVO">Ativo (reserva os contêineres)</option><option value="RASCUNHO">Rascunho</option></select></label>
            </div>
            <p class="field-hint" id="end-help">Os números do contrato e da locação são gerados pelo sistema ao gerar o contrato. A data final é obrigatória no prazo determinado.</p>
          </section>

          <!-- 4. Produtos: um bloco por item (modelo #item-template), com os contêineres livres no período -->
          <section class="contract-card" aria-labelledby="products-title">
            <div class="step-heading"><span class="step-number" aria-hidden="true">4</span><h3 id="products-title">Produtos da locação</h3><button type="button" class="add-item" id="add-item"><svg class="icone icone--p" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3.33333 8H12.6667"/><path d="M8 3.33333V12.6667"/></svg>Adicionar item</button></div>
            <p class="section-help">Selecione os equipamentos, os contêineres e os valores acordados.</p>
            <div id="rental-items" class="rental-items"></div>
            <p class="field-hint">O valor do bem identifica o patrimônio. O preço da locação entra no cálculo da cobrança.</p>
          </section>

          <!-- 5. Condições comerciais -->
          <section class="contract-card" aria-labelledby="commercial-title">
            <div class="step-heading"><span class="step-number" aria-hidden="true">5</span><h3 id="commercial-title">Condições comerciais</h3></div>
            <p class="section-help">Confira os valores e as condições acordadas com o cliente.</p>
            <div class="field-grid three-columns">
              <label class="contract-field">Condição de pagamento *<select name="paymentCondition" required><option value="">Carregando…</option></select></label>
              <label class="contract-field">Primeiro vencimento *<input name="firstDue" type="date" required></label>
              <label class="contract-field">Quantidade de parcelas *<input name="installments" type="number" min="1" max="120" step="1" value="1" required></label>
              <label class="contract-field">Dia recorrente de cobrança<input name="dueDay" type="number" min="1" max="31" step="1" placeholder="Opcional"></label>
              <label class="contract-field">Frete / deslocamento (R$)<input name="freight" type="number" min="0" max="999999999" step="0.01" value="0.00"></label>
              <label class="contract-field">Outras despesas gerais (R$)<input name="other" type="number" min="0" max="999999999" step="0.01" value="0.00"></label>
              <label class="contract-field">Caução (R$)<input name="deposit" type="number" min="0" max="999999999" step="0.01" value="0.00"></label>
            </div>
            <p class="field-hint">Frete e despesas gerais entram uma vez no total. A caução é apresentada separadamente.</p>
          </section>

          <!-- 6. Observações -->
          <section class="contract-card" aria-labelledby="notes-title">
            <div class="step-heading"><span class="step-number" aria-hidden="true">6</span><h3 id="notes-title">Observações do contrato</h3></div>
            <p class="section-help">Informações complementares que acompanham o contrato.</p>
            <label class="contract-field"><span>Observações <span class="optional-label">· opcional</span></span><textarea name="notes" maxlength="800" rows="4" placeholder="Ex.: orientações de acesso ao local e condições específicas da entrega."></textarea></label>
            <p class="character-count"><span id="notes-count">0</span>/800 caracteres</p>
          </section>
        </div>

        <!-- Resumo fixo ao lado: dados conferidos, total calculado pelo servidor e pendências -->
        <aside class="contract-summary contract-card" aria-labelledby="summary-title">
          <div><h3 id="summary-title">Resumo do contrato</h3><p class="section-help">Confira os dados antes de continuar.</p></div>
          <div class="summary-details" tabindex="0" aria-label="Dados completos do resumo do contrato">
            <dl class="summary-list" id="summary-identification"></dl>
            <div class="summary-section"><h4>Cliente e entrega</h4><dl class="summary-list" id="summary-client"></dl></div>
            <div class="summary-section"><h4>Contêineres selecionados</h4><ul class="summary-products" id="summary-products"></ul></div>
            <div class="summary-section"><h4>Valores e pagamento</h4><dl class="summary-list" id="summary-finance"></dl></div>
            <div class="summary-section" id="summary-notes-section" hidden><h4>Observações</h4><p class="summary-notes" id="summary-notes"></p></div>
          </div>
          <div class="summary-footer">
            <div class="summary-total"><span id="total-label">Total do contrato</span><strong id="summary-total">R$ 0,00</strong></div>
            <p class="field-hint" id="total-hint">Itens × períodos do contrato, mais frete e despesas gerais. Caução separada do total.</p>
            <div class="completeness" id="completeness"><strong id="completeness-title"></strong><ul id="pending-fields"></ul><p id="completeness-description"></p></div>
            <p id="form-error" class="contract-error" role="alert" hidden></p>
            <button type="submit" class="contract-button contract-primary" id="save-contract">Conferir e salvar</button>
            <button type="button" class="contract-button" id="cancel-contract">Cancelar</button>
          </div>
          <span class="sr-only" id="summary-announcement" role="status"></span>
        </aside>
      </form>
    </main>
  </div>

  <!-- Modelo de um item da locação (copiado pelo cadastro-contrato.js a cada "Adicionar item") -->
  <template id="item-template">
    <fieldset class="rental-item">
      <legend class="item-legend">Item <span class="item-position"></span></legend>
      <div class="field-grid product-top-grid"><label class="contract-field">Produto *<select data-field="product" required></select></label><div class="contract-field">Quantidade<output data-field="quantity" class="readonly-output">0</output></div></div>
      <fieldset class="physical-units"><legend>Unidades físicas *</legend><div class="unit-options" data-field="units"></div><p class="unit-availability field-hint" data-field="availability"></p></fieldset>
      <div class="field-grid three-columns">
        <div class="contract-field">Cobrança<output data-field="billing" class="readonly-output"></output></div>
        <div class="contract-field">Valor unitário do bem<output data-field="assetValue" class="readonly-output"></output></div>
        <label class="contract-field">Locação unitária (R$) *<input data-field="price" type="number" min="0.01" max="999999999" step="0.01" required></label>
        <label class="contract-field">Outras despesas do item (R$)<input data-field="other" type="number" min="0" max="999999999" step="0.01" value="0"></label>
        <label class="contract-field">Desconto do item (R$)<input data-field="discount" type="number" min="0" max="999999999" step="0.01" value="0"></label>
        <div class="contract-field">Subtotal do item<output data-field="subtotal" class="item-subtotal">—</output></div>
      </div>
      <div class="item-bottom"><span class="field-hint">Preço sugerido pelo produto; pode ser ajustado.</span><button class="remove-item" type="button"><span class="icon icon-close-small" aria-hidden="true"></span>Remover item</button></div>
    </fieldset>
  </template>

  <!-- Diálogo "Prévia do contrato" (antes de salvar) -->
  <dialog class="contract-modal" id="preview-dialog" aria-labelledby="preview-title"><h2 id="preview-title">Prévia do contrato</h2><div class="contract-sheet" id="preview-sheet"></div><p class="modal-error" id="save-error" role="alert" hidden></p><div class="modal-actions"><button class="contract-button contract-primary" type="button" id="confirm-save">Confirmar e salvar</button><button class="contract-button" type="button" id="close-preview">Fechar prévia</button></div></dialog>

  <!-- Diálogo "Contrato gerado com sucesso" (depois de salvar) -->
  <dialog class="contract-modal" id="success-dialog" aria-labelledby="success-title"><h2 id="success-title">Contrato gerado com sucesso</h2><span class="icon icon-success" aria-hidden="true"></span><p class="modal-text" id="success-text">O documento está pronto para conferência.</p><div class="modal-actions"><a class="contract-button contract-primary" id="view-document" href="<?= $urlLista ?>">Visualizar documento</a><button class="contract-button" type="button" id="new-contract">Voltar ao cadastro</button></div></dialog>

  <!-- Diálogo "Cancelar cadastro?" -->
  <dialog class="contract-modal" id="cancel-dialog" aria-labelledby="cancel-title"><h2 id="cancel-title">Cancelar cadastro?</h2><p class="modal-text">Os dados preenchidos neste contrato ainda não foram salvos.</p><div class="modal-actions"><button class="contract-button contract-primary" type="button" id="keep-editing">Continuar preenchendo</button><a class="contract-button" href="<?= $urlLista ?>">Descartar e sair</a></div></dialog>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>
