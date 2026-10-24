<?php
/**
 * login.php — tela de login da área administrativa do SGCB (http://localhost/SGCB/login.php).
 *
 * Finalidade: exibir o formulário de acesso (front-end). A autenticação real ainda não existe.
 * Depende de: src/bootstrap.php (BASE_URL), includes/head.php, includes/footer.php,
 *             public/assets/css/login.css, public/assets/js/login.js,
 *             imagens de public/assets/img/login/ e public/assets/img/logo/.
 * Usado por:  public/index.php (redireciona a raiz do sistema para cá).
 * Observação: não usa includes/nav-admin.php, porque a tela de login não tem menu.
 */

// Bootstrap: carrega a configuração (BASE_URL) antes de qualquer saída.
require __DIR__ . '/../src/bootstrap.php';

// Envio do formulário (method="post" para esta mesma página).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // TODO: autenticação (tarefa feature/login-back)
}

// Variáveis lidas por includes/head.php e includes/footer.php.
$titulo = 'Login';
$css    = ['login.css'];
$js     = ['login.js'];

// Caminho base das imagens, já escapado para uso direto nos atributos src.
$img = htmlspecialchars(BASE_URL . 'assets/img/');

require __DIR__ . '/../includes/head.php';
?>
  <main class="login-card">
    <!-- Painel de boas-vindas: foto de fundo, fachada da loja e cartão com a marca -->
    <section class="welcome" aria-labelledby="welcome-title">
      <img class="welcome-background" src="<?= $img ?>login/background.png" alt="">
      <div class="photo-shade"></div>
      <img class="welcome-photo" src="<?= $img ?>logo/casa-barretto.png"
        alt="Fachada da Casa Barretto com caminhões estacionados">
      <div class="welcome-panel">
        <p class="wordmark">CASA<br>BARRETTO</p>
        <h2 id="welcome-title">Bem-vindo!</h2>
        <p class="welcome-description">Acesse o sistema de gerenciamento de locações de contêineres com uma experiência
          simples, organizada e elegante.</p>
        <a class="instagram" href="https://www.instagram.com/casabarretto/" target="_blank"
          rel="noopener noreferrer"><img src="<?= $img ?>login/instagram.svg" alt="" width="16" height="16">@casabarretto</a>
      </div>
    </section>
    <!-- Painel da conta: formulário de login -->
    <section class="account" aria-labelledby="login-title">
      <p class="eyebrow">Área administrativa</p>
      <h1 id="login-title">Entrar na conta</h1>
      <p class="subtitle">Informe seus dados para continuar.</p>
      <!-- Hoje o login.js intercepta o envio e valida as credenciais de desenvolvimento;
           o POST para esta página passa a valer quando a autenticação for implementada. -->
      <form id="login-form" method="post" action="<?= htmlspecialchars(BASE_URL . 'login.php') ?>">
        <label class="field-label" for="email">E-mail</label>
        <input id="email" name="email" type="email" autocomplete="username" placeholder="ronaldo@casabarretto.local"
          required>
        <label class="field-label password-label" for="password">Senha</label>
        <div class="password-field">
          <input id="password" name="password" type="password" autocomplete="current-password" required>
          <button class="password-toggle" id="password-toggle" type="button" aria-label="Mostrar senha"
            aria-pressed="false"><img src="<?= $img ?>login/eye.svg" alt="" width="19" height="19"></button>
        </div>
        <div class="form-options">
          <label class="remember"><input id="remember" name="remember" type="checkbox" checked><span class="checkbox-art"><img
                src="<?= $img ?>login/check.svg" alt="" width="11" height="11"></span>Lembrar de mim</label>
          <button class="text-button" id="forgot-password" type="button">Esqueceu a senha?</button>
        </div>
        <p id="form-message" class="form-message" role="alert" hidden></p>
        <button class="submit-button" type="submit">Entrar</button>
      </form>
      <p class="development-note">Ambiente de desenvolvimento: admin@casabarretto.local / admin123</p>
      <button class="library-link" id="open-library" type="button">Ver biblioteca de componentes</button>
    </section>
  </main>

  <!-- Diálogo "Esqueceu a senha?" (aberto pelo login.js) -->
  <dialog id="help-dialog" aria-labelledby="help-title">
    <h2 id="help-title">Recuperar acesso</h2>
    <p>Entre em contato com o administrador da Casa Barretto para solicitar a recuperação da sua senha.</p>
    <form method="dialog"><button class="submit-button">Entendi</button></form>
  </dialog>

  <!-- Diálogo "Biblioteca de componentes" (aberto pelo login.js); cores das amostras no login.css -->
  <dialog id="library-dialog" aria-labelledby="library-title">
    <h2 id="library-title">Biblioteca de componentes</h2>
    <p class="library-description">Elementos da interface Casa Barretto</p>
    <div class="swatches"><span class="swatch-verde">Verde principal</span><span
        class="swatch-areia">Areia</span><span class="swatch-texto">Texto</span></div>
    <label class="field-label" for="sample-input">Campo de texto</label>
    <input id="sample-input" placeholder="Digite um texto">
    <form method="dialog"><button class="submit-button">Fechar biblioteca</button></form>
  </dialog>
<?php require __DIR__ . '/../includes/footer.php'; ?>
