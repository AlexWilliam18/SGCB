<?php
/**
 * Tela de login. Com e-mail e senha válidos, segue para admin/dashboard.php.
 * Credenciais iniciais: as do seed database/seeds/001_usuario_admin.sql.
 */

require __DIR__ . '/../src/bootstrap.php';

// Quem já está logado (e confirmado no banco) não precisa ver o login de novo.
if (Autenticacao::usuarioValido() !== null) {
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
    exit;
}

$email = ''; // e-mail digitado, para não obrigar a redigitar após um erro

// Mensagem de erro exibida no formulário. Começa com o motivo de uma sessão recusada
// (enviado por Autenticacao::exigirLogin() em ?motivo=), se houver.
$motivos = [
    'banco'  => 'Não foi possível conectar ao banco de dados. Entre novamente quando o sistema estiver disponível.',
    'sessao' => 'Sua sessão foi encerrada porque o usuário não está mais ativo. Entre novamente.',
];
$erro = $motivos[$_GET['motivo'] ?? ''] ?? null;

// O formulário faz POST para esta mesma página.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['password'] ?? '');

    if (!Autenticacao::csrfValido($_POST['csrf'] ?? null)) {
        $erro = 'Sessão expirada. Tente entrar novamente.';
    } elseif ($email === '' || $senha === '') {
        $erro = 'Informe o e-mail e a senha.';
    } else {
        try {
            if (Autenticacao::entrar($email, $senha)) {
                header('Location: ' . BASE_URL . 'admin/dashboard.php');
                exit;
            }
            // Mensagem genérica: não revela se o e-mail existe.
            $erro = 'E-mail ou senha inválidos.';
        } catch (PDOException $e) {
            // Banco fora do ar ou config/config.php com credenciais erradas.
            error_log('SGCB login: ' . $e->getMessage());
            $erro = 'Não foi possível conectar ao banco de dados. Tente novamente em instantes.';
        }
    }
}

$titulo = 'Login';
$css    = ['login.css'];
$js     = ['login.js'];

// Caminho base das imagens, já escapado para uso direto nos atributos src.
$img = htmlspecialchars(BASE_URL . 'assets/img/');

require __DIR__ . '/../includes/head.php';
?>
  <!-- Cartão único centralizado: marca no topo e formulário logo abaixo.
       A classe "account" posiciona o botão de tema no canto (estilo em tema.css). -->
  <main class="login-card account" aria-labelledby="login-title">
    <!-- Tema e cor de destaque também no login (tema.js); a escolha vale para as outras telas -->
    <button class="theme-button" type="button" aria-label="Tema e cor de destaque" aria-haspopup="menu"
      aria-expanded="false" aria-controls="theme-menu"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></button>
    <div class="profile-menu theme-menu" id="theme-menu" role="menu" aria-label="Tema e cor de destaque" hidden>
<?php require __DIR__ . '/../includes/opcoes-tema.php'; ?>
    </div>

    <!-- Marca: logotipo (o mesmo do favicon), nome e selo da área -->
    <header class="brand">
      <img class="brand-logo" src="<?= $img ?>logo/favicon.svg" alt="" width="84" height="76">
      <p class="brand-name">CASA BARRETTO</p>
      <p class="brand-area">Área administrativa</p>
    </header>

    <h1 id="login-title">Acesse sua conta</h1>
    <p class="subtitle">Informe seus dados para continuar.</p>

    <form id="login-form" method="post" action="<?= htmlspecialchars(BASE_URL . 'login.php') ?>">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(Autenticacao::tokenCsrf()) ?>">
      <label class="field-label" for="email">E-mail</label>
      <!-- Placeholder genérico: não expõe o e-mail do administrador padrão -->
      <input id="email" name="email" type="email" autocomplete="username" placeholder="voce@empresa.com.br"
        value="<?= htmlspecialchars($email) ?>" required>
      <label class="field-label password-label" for="password">Senha</label>
      <div class="password-field">
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button class="password-toggle" id="password-toggle" type="button" aria-label="Mostrar senha"
          aria-pressed="false"><img src="<?= $img ?>login/eye.svg" alt="" width="19" height="19"></button>
      </div>
      <div class="form-options">
        <!-- Só lembra o e-mail neste navegador (login.js); a senha nunca é guardada -->
        <label class="remember"><input id="remember" name="remember" type="checkbox" checked><span class="checkbox-art"><img
              src="<?= $img ?>login/check.svg" alt="" width="11" height="11"></span>Lembrar e-mail</label>
        <button class="text-button" id="forgot-password" type="button">Esqueceu a senha?</button>
      </div>
      <!-- Erro do servidor (e-mail/senha inválidos, banco fora do ar...) -->
      <p id="form-message" class="form-message" role="alert"<?= $erro === null ? ' hidden' : '' ?>><?= htmlspecialchars((string) $erro) ?></p>
      <button class="submit-button" type="submit">Entrar</button>
    </form>
  </main>

  <!-- Diálogo "Esqueceu a senha?" (aberto pelo login.js) -->
  <dialog id="help-dialog" aria-labelledby="help-title">
    <h2 id="help-title">Recuperar acesso</h2>
    <p>Entre em contato com o administrador deste sistema para realizar a troca ou a recuperação da sua senha.</p>
    <form method="dialog"><button class="submit-button">Entendi</button></form>
  </dialog>

<?php require __DIR__ . '/../includes/footer.php'; ?>
