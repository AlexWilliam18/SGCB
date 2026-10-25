'use strict';

const email = document.getElementById('email');
const password = document.getElementById('password');
const remember = document.getElementById('remember');
const message = document.getElementById('form-message');
const toggle = document.getElementById('password-toggle');

// Preenche o e-mail lembrado, a não ser que o PHP já tenha devolvido o e-mail digitado (após um erro).
try {
  if (!email.value) email.value = localStorage.getItem('casaBarretto.rememberedEmail') || '';
} catch (_) {
  // Sem localStorage o formulário continua funcionando.
}

// Erro vindo do servidor: marca o campo de senha como inválido para leitores de tela.
if (!message.hidden) {
  password.setAttribute('aria-invalid', 'true');
  password.setAttribute('aria-describedby', 'form-message');
}

toggle.addEventListener('click', () => {
  const visible = password.type === 'password';
  password.type = visible ? 'text' : 'password';
  toggle.setAttribute('aria-label', visible ? 'Ocultar senha' : 'Mostrar senha');
  toggle.setAttribute('aria-pressed', String(visible));
});

// A autenticação é feita no PHP (public/login.php); aqui só guardamos o "Lembrar de mim"
// e deixamos o formulário seguir com o POST normalmente.
document.getElementById('login-form').addEventListener('submit', () => {
  try {
    if (remember.checked) localStorage.setItem('casaBarretto.rememberedEmail', email.value.trim());
    else localStorage.removeItem('casaBarretto.rememberedEmail');
  } catch (_) {
    // Lembrar o e-mail é opcional.
  }
});

[email, password].forEach(input =>
  input.addEventListener('input', () => {
    message.hidden = true;
    password.removeAttribute('aria-invalid');
    password.removeAttribute('aria-describedby');
  })
);

[
  ['forgot-password', 'help-dialog'],
  ['open-library', 'library-dialog'],
].forEach(([buttonId, dialogId]) => {
  const dialog = document.getElementById(dialogId);
  document.getElementById(buttonId).addEventListener('click', () => dialog.showModal());
  dialog.addEventListener('click', event => {
    if (event.target === dialog) {
      const bounds = dialog.getBoundingClientRect();
      if (
        event.clientX < bounds.left ||
        event.clientX > bounds.right ||
        event.clientY < bounds.top ||
        event.clientY > bounds.bottom
      )
        dialog.close();
    }
  });
});
