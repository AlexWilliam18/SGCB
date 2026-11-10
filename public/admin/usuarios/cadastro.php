<?php
/**
 * Cadastro de usuário: dados pessoais, senha inicial, status e perfil de acesso (cargo).
 * cadastro-usuario.js valida os campos, busca os cargos em /api/usuarios/opcoes e
 * grava em /api/usuarios (POST) com o token CSRF. A senha vira hash bcrypt no model Usuario.
 * Com ?id=5 abre em modo edição: carrega /api/usuarios/5 e salva em /api/usuarios/5
 * (a senha não é alterada na edição, por isso a seção "Dados de acesso" fica escondida).
 */

require __DIR__ . '/../../../src/bootstrap.php';

Autenticacao::exigirLogin();

// Id do usuário em edição (0 = cadastro novo).
$idEdicao = max(0, (int) ($_GET['id'] ?? 0));
$editando = $idEdicao > 0;

$titulo       = $editando ? 'Editar usuário' : 'Novo usuário';
$css          = ['dashboard.css', 'cadastro-usuario.css'];
$js           = ['painel.js', 'descartar.js', 'cadastro-usuario.js'];
$fontesExtras = ['Inter:wght@400;700'];   // usada nos campos do formulário
$classeBody   = 'user-create-page';        // ajustes de layout do cadastro-usuario.css
$paginaAtual  = 'usuarios';                // o cadastro pertence ao módulo Usuários no menu

// Caminhos já escapados; também vão para o JS via data-*.
$img      = htmlspecialchars(BASE_URL . 'assets/img/');
$urlLista = htmlspecialchars(BASE_URL . 'admin/usuarios/index.php');

require __DIR__ . '/../../../includes/head.php';
?>
  <a class="skip-link" href="#user-create-main">Ir para o formulário</a>
<?php require __DIR__ . '/../../../includes/nav.php'; ?>
  <div class="workspace">
    <!-- Cabeçalho: botão do menu no celular, título e data de hoje (painel.js) -->
    <header class="page-header"><button class="menu-toggle" id="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-sidebar" aria-label="Abrir menu"><span></span><span></span><span></span></button><div class="page-heading"><h1>Controle de Usuários</h1><p>Gerencie as pessoas com acesso ao sistema</p></div><div class="date-pill"><img src="<?= $icones ?>calendario.svg" alt="" width="13" height="13"><time id="today"></time></div></header>
    <!-- data-edit-id: id do usuário em edição (vazio = cadastro novo), lido pelo cadastro-usuario.js -->
    <main class="user-create-main" id="user-create-main" data-lista-url="<?= $urlLista ?>" data-edit-id="<?= $editando ? $idEdicao : '' ?>" tabindex="-1">
      <div class="user-create-content">
        <!-- Título e botão Voltar (o texto muda no modo edição) -->
        <div class="user-create-intro"><div class="user-create-heading"><h2><?= $editando ? 'Editar usuário' : 'Novo usuário' ?></h2><p><?= $editando ? 'Altere os dados e o acesso do usuário ao sistema de locações.' : 'Preencha os dados e defina o acesso ao sistema de locações.' ?></p></div><a class="user-outline" href="<?= $urlLista ?>"><img class="icone" src="<?= $icones ?>voltar.svg" alt="" width="16" height="16">Voltar</a></div>
        <!-- Aviso geral (erro da API, sem permissão, cargos não carregados) -->
        <div class="user-notice" id="user-notice" role="alert" hidden><p id="user-notice-text"></p><button class="user-outline" id="reload-options" type="button" hidden>Tentar novamente</button></div>
        <form id="user-create-form" class="user-create-form" novalidate autocomplete="off">
          <!-- 1. Dados do usuário -->
          <section class="user-create-section" aria-labelledby="user-data-title">
            <h3 id="user-data-title">Dados do usuário</h3>
            <div class="user-form-grid user-identification-grid">
              <div class="user-field"><label for="nome">Nome completo *</label><input id="nome" name="nome" type="text" placeholder="Digite o nome completo" maxlength="150" autocomplete="name" required><p class="user-error" id="error-nome" hidden></p></div>
              <div class="user-field user-email"><label for="email">E-mail *</label><input id="email" name="email" type="email" placeholder="nome@casabarretto.com.br" maxlength="254" autocomplete="email" required><p class="user-error" id="error-email" hidden></p></div>
            </div>
            <div class="user-form-grid user-contact-grid">
              <div class="user-field"><label for="telefone">Telefone</label><input id="telefone" name="telefone" type="tel" placeholder="(00) 00000-0000" maxlength="25" autocomplete="tel"><p class="user-error" id="error-telefone" hidden></p></div>
              <div class="user-field user-state"><label for="status"><?= $editando ? 'Status *' : 'Status inicial *' ?></label><div class="user-select"><select id="status" name="status" required><option value="ativo">Ativo</option><option value="inativo">Inativo</option></select><img class="icone icone--seta" src="<?= $icones ?>seta-baixo.svg" alt="" width="10" height="6"></div><p class="user-error" id="error-status" hidden></p></div>
            </div>
          </section>
          <!-- 2. Dados de acesso (a senha é enviada só no POST e gravada como hash; escondida na edição) -->
          <section class="user-create-section" aria-labelledby="user-access-title"<?= $editando ? ' hidden' : '' ?>>
            <h3 id="user-access-title">Dados de acesso</h3>
            <div class="user-form-grid user-credentials-grid">
              <div class="user-field"><label for="senha">Senha inicial *</label><input id="senha" name="senha" type="password" placeholder="Digite uma senha" minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="password-help" required><p class="user-error" id="error-senha" hidden></p></div>
              <div class="user-field user-confirm"><label for="confirmar-senha">Confirmar senha *</label><input id="confirmar-senha" name="confirmar_senha" type="password" placeholder="Repita a senha" minlength="8" maxlength="72" autocomplete="new-password" required><p class="user-error" id="error-confirmar-senha" hidden></p></div>
            </div>
            <p class="user-help" id="password-help">Use ao menos 8 caracteres, com letras e números.</p>
            <label class="user-first-access" for="alterar-senha"><input id="alterar-senha" name="alterar_senha" type="checkbox" checked><span>Solicitar alteração de senha no primeiro acesso</span></label>
          </section>
          <!-- 3. Perfil de acesso: data-cargo é o código do cargo; o value (id) vem da API -->
          <section class="user-create-section" aria-labelledby="user-role-title">
            <h3 id="user-role-title">Perfil de acesso</h3>
            <fieldset class="user-role-options"><legend class="sr-only">Escolha o perfil de acesso</legend>
              <label class="user-role-card" for="perfil-operador"><span class="user-role-title"><input id="perfil-operador" type="radio" name="perfil" data-cargo="OPERADOR" value="" checked required><strong>Operador</strong></span><span class="user-role-description">Acesso às rotinas de clientes, produtos e locações.</span></label>
              <label class="user-role-card user-admin-card" for="perfil-administrador"><span class="user-role-title"><input id="perfil-administrador" type="radio" name="perfil" data-cargo="ADMIN" value="" required><strong>Administrador</strong></span><span class="user-role-description">Acesso completo, incluindo financeiro e gestão de usuários.</span></label>
            </fieldset>
            <p class="user-error" id="error-cargo_id" hidden></p>
            <p class="user-help" id="role-help">O perfil Operador não permite cadastrar usuários ou alterar permissões.</p>
          </section>
          <!-- Rodapé: Cancelar volta para a listagem; Salvar fica desabilitado até os cargos carregarem -->
          <div class="user-create-actions"><p>* Campos obrigatórios</p><div><a class="user-outline" href="<?= $urlLista ?>">Cancelar</a><button class="user-save" id="save-user" type="submit" disabled><?= $editando ? 'Salvar alterações' : 'Salvar usuário' ?></button></div></div>
        </form>
        <noscript><p class="user-noscript">Ative o JavaScript para cadastrar usuários.</p></noscript>
      </div>
    </main>
  </div>
  <!-- Confirmação ao sair com dados preenchidos e ainda não salvos -->
<?php require __DIR__ . '/../../../includes/dialogo-descartar.php'; ?>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>
