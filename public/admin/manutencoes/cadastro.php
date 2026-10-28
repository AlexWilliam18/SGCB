<?php
/**
 * Cadastro de manutenção: contêiner (unidade física), tipo, prioridade, data prevista,
 * responsável e descrição.
 * Por enquanto só front-end: as opções e o salvamento dependem de
 * /api/manutencoes/opcoes e /api/manutencoes, que virão na etapa de back-end.
 */

require __DIR__ . '/../../../src/bootstrap.php';

Autenticacao::exigirLogin();

$titulo      = 'Nova manutenção';
$css         = ['dashboard.css', 'cadastro-manutencao.css'];
$js          = ['painel.js', 'cadastro-manutencao.js'];
$classeBody  = 'maintenance-form-page';   // ajustes de layout do cadastro-manutencao.css
$paginaAtual = 'manutencoes';             // o cadastro pertence ao módulo Manutenções no menu

// Caminhos já escapados; a URL da listagem também vai para o JS via data-*.
$img      = htmlspecialchars(BASE_URL . 'assets/img/');
$urlLista = htmlspecialchars(BASE_URL . 'admin/manutencoes/index.php');

require __DIR__ . '/../../../includes/head.php';
?>
  <a class="skip-link" href="#maintenance-main">Ir para o formulário</a>
<?php require __DIR__ . '/../../../includes/nav.php'; ?>
  <div class="workspace">
    <header class="page-header"><button class="menu-toggle" id="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-sidebar" aria-label="Abrir menu"><span></span><span></span><span></span></button><div class="page-heading"><h1>Cadastro de Manutenções</h1><p>Inclua ou edite as manutenções disponíveis</p></div><div class="date-pill"><img src="<?= $img ?>dashboard/e6378.svg" alt="" width="13" height="13"><time id="today"></time></div></header>
    <main class="maintenance-main" id="maintenance-main" data-lista-url="<?= $urlLista ?>" tabindex="-1">
      <div class="maintenance-intro"><div><h2>Nova Manutenção</h2><div class="intro-subtitle" aria-hidden="true"></div></div><a class="outline-button" href="<?= $urlLista ?>"><img src="<?= $img ?>cadastro-manutencao/92d18.svg" alt="" width="15" height="15">Voltar</a></div>
      <!-- Aviso de erro/configuração e mensagem de sucesso (cadastro-manutencao.js) -->
      <div class="data-notice" id="maintenance-notice" role="status" hidden><p id="notice-text"></p><button id="retry-options" type="button" hidden>Tentar novamente</button></div>
      <div class="maintenance-success" id="maintenance-success" role="status" hidden></div>
      <form class="card maintenance-card" id="maintenance-form" novalidate>
        <section class="maintenance-section" aria-labelledby="maintenance-title">
          <h3 id="maintenance-title">Dados da manutenção</h3>
          <!-- 1ª linha: contêiner, tipo e prioridade (as opções vêm da API) -->
          <div class="maintenance-first-grid">
            <div class="maintenance-field field-unidade_id"><label for="unidade_id">Contêiner <span aria-hidden="true">*</span></label><div class="maintenance-control"><select id="unidade_id" name="unidade_id" required><option value="">Selecione o Contêiner</option></select></div><p class="field-error" id="error-unidade_id" hidden></p></div>
            <div class="maintenance-field field-tipo"><label for="tipo">Tipo de manutenção <span aria-hidden="true">*</span></label><div class="maintenance-control"><select id="tipo" name="tipo" required><option value="">Selecione o Tipo</option></select></div><p class="field-error" id="error-tipo" hidden></p></div>
            <div class="maintenance-field field-prioridade"><label for="prioridade">Prioridade <span aria-hidden="true">*</span></label><div class="maintenance-control priority-control"><select id="prioridade" name="prioridade" required><option value="">Selecione</option></select><img src="<?= $img ?>clientes/fb51c.svg" alt="" width="10" height="6"></div><p class="field-error" id="error-prioridade" hidden></p></div>
          </div>
          <!-- 2ª linha: data prevista (o ícone abre o calendário) e responsável -->
          <div class="maintenance-second-grid">
            <div class="maintenance-field field-data_prevista"><label for="data_prevista">Data Prevista <span aria-hidden="true">*</span></label><div class="date-control"><button type="button" id="choose-date" aria-label="Abrir calendário" aria-controls="data_prevista"><img src="<?= $img ?>manutencoes/fcd3e.png" alt="" width="30" height="30"></button><input type="date" id="data_prevista" name="data_prevista" min="1000-01-01" max="9999-12-31" required></div><p class="field-error" id="error-data_prevista" hidden></p></div>
            <div class="maintenance-field field-responsavel_usuario_id"><label for="responsavel_usuario_id">Responsável</label><div class="maintenance-control"><select id="responsavel_usuario_id" name="responsavel_usuario_id" aria-describedby="hint-responsavel_usuario_id"><option value="">Selecione o responsável</option></select></div><p class="field-hint" id="hint-responsavel_usuario_id">Se não selecionar, será usado o responsável pelo cadastro.</p><p class="field-error" id="error-responsavel_usuario_id" hidden></p></div>
          </div>
          <!-- Descrição livre (até 5.000 caracteres) -->
          <div class="maintenance-description"><div class="maintenance-field field-descricao"><label for="descricao">Descreva o que precisa ser feito</label><textarea id="descricao" name="descricao" maxlength="5000" placeholder="Informe os detalhes da manutenção..."></textarea><p class="field-error" id="error-descricao" hidden></p></div></div>
        </section>
        <footer class="maintenance-actions"><a class="outline-button" href="<?= $urlLista ?>">Cancelar</a><button class="save-maintenance" id="save-maintenance" type="submit" disabled><img src="<?= $img ?>cadastro-manutencao/dbcb8.svg" alt="" width="15" height="15"><span>Salvar manutenção</span></button></footer>
      </form>
    </main>
  </div>
  <!-- Diálogo "Descartar alterações?" ao sair com dados não salvos (cadastro-manutencao.js) -->
  <dialog class="info-dialog" id="leave-dialog" aria-labelledby="leave-title"><h2 id="leave-title">Descartar alterações?</h2><p>Os dados preenchidos ainda não foram salvos.</p><div class="leave-actions"><button class="outline-button" id="stay-form" type="button">Continuar preenchendo</button><button class="primary-button" id="discard-form" type="button">Descartar e voltar</button></div></dialog>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>
