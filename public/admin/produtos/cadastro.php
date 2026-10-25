<?php
/**
 * Cadastro de produto: dados, informações de compra, valores de locação, estoque e limites.
 * Por enquanto só front-end: as categorias/fornecedores e o salvamento dependem de
 * /api/produtos/opcoes e /api/produtos, que virão na etapa de back-end.
 */

require __DIR__ . '/../../../src/bootstrap.php';

Autenticacao::exigirLogin();

$titulo       = 'Novo produto';
$css          = ['dashboard.css', 'cadastro-produto.css'];
$js           = ['painel.js', 'cadastro-produto.js'];
$fontesExtras = ['Inter:wght@400;600;700'];   // usada nos campos do formulário
$classeBody   = 'product-form-page';           // ajustes de layout do cadastro-produto.css
$paginaAtual  = 'produtos';                    // o cadastro pertence ao módulo Produtos no menu

// Caminhos já escapados; a URL da listagem também vai para o JS via data-*.
$img      = htmlspecialchars(BASE_URL . 'assets/img/');
$urlLista = htmlspecialchars(BASE_URL . 'admin/produtos/index.php');

require __DIR__ . '/../../../includes/head.php';
?>
  <a class="skip-link" href="#product-main">Ir para o formulário</a>
<?php require __DIR__ . '/../../../includes/nav.php'; ?>
  <div class="workspace">
    <header class="page-header"><button class="menu-toggle" id="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-sidebar" aria-label="Abrir menu"><span></span><span></span><span></span></button><div class="page-heading"><h1>Cadastro de produto</h1><p>Inclua ou edite os produtos disponíveis</p></div><div class="date-pill"><img src="<?= $img ?>dashboard/e6378.svg" alt="" width="13" height="13"><time id="today"></time></div></header>
    <main class="product-main" id="product-main" data-lista-url="<?= $urlLista ?>" tabindex="-1">
      <div class="product-intro"><div><h2>Novo produto</h2><p>Cadastre contêineres e outros itens disponíveis para locação.</p></div><a class="outline-button" href="<?= $urlLista ?>">← Voltar</a></div>
      <!-- Aviso de erro/configuração (cadastro-produto.js), com "Tentar novamente" para recarregar as opções -->
      <div class="data-notice" id="form-notice" role="status" hidden><p id="form-message"></p><button type="button" id="retry-options">Tentar novamente</button></div>
      <form id="product-form" novalidate>
        <div class="product-card">
          <!-- 1. Dados do produto: as categorias vêm da API -->
          <section class="form-section product-data" aria-labelledby="data-title"><h3 id="data-title">Dados do produto</h3><div class="data-grid">
            <div class="field field-nome"><label for="nome">Nome *</label><input id="nome" name="nome" type="text" placeholder="Ex.: Contêiner Escritório 20 pés" required maxlength="200" autocomplete="off"><p class="field-error" id="error-nome" hidden></p></div>
            <div class="field field-codigo"><label for="codigo">Código *</label><input id="codigo" name="codigo" type="text" placeholder="Ex.: CM-001 ou BM-001" required maxlength="40" autocomplete="off"><p class="field-error" id="error-codigo" hidden></p></div>
            <div class="field field-categoria_id"><label for="categoria_id">Categoria *</label><div class="select-control"><select id="categoria_id" name="categoria_id" required><option value="">Selecione a categoria</option></select><img src="<?= $img ?>cadastro-cliente/f4aa9.svg" alt=""></div><p class="field-error" id="error-categoria_id" hidden></p></div>
            <div class="field description-field"><label for="descricao">Descrição</label><div class="textarea-control"><textarea id="descricao" name="descricao" maxlength="500" placeholder="Descreva as características do produto, dimensões, diferenciais, etc." aria-describedby="description-count"></textarea><span id="description-count">0/500</span></div><p class="field-error" id="error-descricao" hidden></p></div>
          </div></section>
          <!-- 2. Informações de compra: os fornecedores vêm da API (cadastros do tipo fornecedor em Clientes) -->
          <section class="form-section purchase-section" aria-labelledby="purchase-title"><h3 id="purchase-title">Informações de compra</h3><div class="purchase-grid">
            <div class="field field-fornecedor_id"><label for="fornecedor_id">Fornecedor dos contêineres *</label><div class="select-control"><select id="fornecedor_id" name="fornecedor_id" required><option value="">Selecione o fornecedor</option></select><img src="<?= $img ?>cadastro-cliente/18e38.svg" alt=""></div><p class="field-error" id="error-fornecedor_id" hidden></p></div>
            <div class="field field-valor_total_bem"><label for="valor_total_bem">Valor total do bem *</label><input id="valor_total_bem" name="valor_total_bem" type="text" placeholder="R$ 0,00" required inputmode="decimal" maxlength="24" data-money><p class="field-error" id="error-valor_total_bem" hidden></p></div>
          </div></section>
          <!-- 3. Valores de locação por período (campos data-money são formatados em R$ pelo JS) -->
          <section class="form-section rental-section" aria-labelledby="rental-title"><h3 id="rental-title">Valores de locação</h3><p class="section-hint">Defina os valores conforme o período de cobrança.</p><div class="rental-grid">
            <div class="field field-diaria"><label for="diaria">Valor da diária *</label><input id="diaria" name="diaria" type="text" placeholder="R$ 0,00" required inputmode="decimal" maxlength="24" data-money><p class="field-error" id="error-diaria" hidden></p></div>
            <div class="field field-semanal"><label for="semanal">Valor semanal *</label><input id="semanal" name="semanal" type="text" placeholder="R$ 0,00" required inputmode="decimal" maxlength="24" data-money><p class="field-error" id="error-semanal" hidden></p></div>
            <div class="field field-quinzenal"><label for="quinzenal">Valor quinzenal *</label><input id="quinzenal" name="quinzenal" type="text" placeholder="R$ 0,00" required inputmode="decimal" maxlength="24" data-money><p class="field-error" id="error-quinzenal" hidden></p></div>
            <div class="field field-mensal"><label for="mensal">Valor mensal *</label><input id="mensal" name="mensal" type="text" placeholder="R$ 0,00" required inputmode="decimal" maxlength="24" data-money><p class="field-error" id="error-mensal" hidden></p></div>
          </div><div class="price-example"><p><strong>Exemplo:</strong> Diária R$ 200 • Semanal R$ 1.000 • Quinzenal R$ 1.800 • Mensal R$ 3.000</p><p>Os valores maiores de período podem ser promocionais e não precisam ser apenas a multiplicação da diária.</p></div></section>
          <!-- 4. Estoque e limites: a quantidade inicial gera as unidades físicas no back-end -->
          <section class="form-section stock-section" aria-labelledby="stock-title"><h3 id="stock-title">Estoque e limites</h3><div class="stock-grid">
            <div class="field field-quantidade"><label for="quantidade">Quantidade inicial de unidades *</label><input id="quantidade" name="quantidade" type="number" value="1" required min="1" max="1000" step="1" aria-describedby="hint-quantidade"><p class="hint" id="hint-quantidade">O sistema irá gerar automaticamente as unidades físicas.</p><p class="field-error" id="error-quantidade" hidden></p></div>
            <div class="field field-estado_operacional"><label for="estado_operacional">Status inicial das unidades *</label><div class="select-control"><select id="estado_operacional" name="estado_operacional" required><option value="DISPONIVEL">Disponível</option><option value="MANUTENCAO">Em manutenção</option><option value="INATIVA">Inativa</option></select><img src="<?= $img ?>cadastro-cliente/f4aa9.svg" alt=""></div><p class="field-error" id="error-estado_operacional" hidden></p></div>
            <div class="field field-limite_inicial"><label for="limite_inicial">Limite inicial *</label><input id="limite_inicial" name="limite_inicial" type="number" value="0" required min="0" max="4294967295" step="1" aria-describedby="hint-limite_inicial"><p class="hint" id="hint-limite_inicial">Quantidade mínima para alerta de estoque.</p><p class="field-error" id="error-limite_inicial" hidden></p></div>
            <div class="field field-limite_maximo"><label for="limite_maximo">Limite máximo *</label><input id="limite_maximo" name="limite_maximo" type="number" value="0" required min="0" max="4294967295" step="1" aria-describedby="hint-limite_maximo"><p class="hint" id="hint-limite_maximo">Quantidade máxima recomendada em estoque.</p><p class="field-error" id="error-limite_maximo" hidden></p></div>
            <div class="field field-maximo_estoque"><label for="maximo_estoque">Máximo de estoque *</label><input id="maximo_estoque" name="maximo_estoque" type="number" value="0" required min="0" max="4294967295" step="1" aria-describedby="hint-maximo_estoque"><p class="hint" id="hint-maximo_estoque">Quantidade máxima permitida no sistema.</p><p class="field-error" id="error-maximo_estoque" hidden></p></div>
          </div></section>
        </div>
        <footer class="form-actions"><a class="outline-button" href="<?= $urlLista ?>">Cancelar</a><button class="save-product" type="submit" id="save-product" disabled>Salvar produto</button></footer>
      </form>
    </main>
  </div>
  <!-- Diálogo "Descartar alterações?" ao sair com dados não salvos (cadastro-produto.js) -->
  <dialog class="info-dialog" id="leave-dialog" aria-labelledby="leave-title"><h2 id="leave-title">Descartar alterações?</h2><p>Os dados preenchidos ainda não foram salvos.</p><div class="form-actions"><button class="outline-button" id="stay-form" type="button">Continuar cadastro</button><button class="save-product" id="discard-form" type="button">Descartar</button></div></dialog>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>
