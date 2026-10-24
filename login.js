'use strict';

const email = document.getElementById('email');
const password = document.getElementById('password');
const remember = document.getElementById('remember');
const message = document.getElementById('form-message');
const toggle = document.getElementById('password-toggle');


try {
  email.value = localStorage.getItem('casaBarretto.rememberedEmail') || '';
} catch (_) { /* The form remains usable when browser storage is unavailable. */ }

toggle.addEventListener('click', () => {
  const visible = password.type === 'password';
  password.type = visible ? 'text' : 'password';
  toggle.setAttribute('aria-label', visible ? 'Ocultar senha' : 'Mostrar senha');
  toggle.setAttribute('aria-pressed', String(visible));
});

document.getElementById('login-form').addEventListener('submit', event => {
  event.preventDefault();
 
  if (email.value.trim().toLowerCase() !== 'admin@casabarretto.local' || password.value !== 'admin123') {
    message.textContent = 'E-mail ou senha inválidos. Utilize as credenciais de desenvolvimento indicadas abaixo.';
    message.hidden = false;
    password.setAttribute('aria-invalid', 'true');
    password.setAttribute('aria-describedby', 'form-message');
    return;
  }
  try {
    if (remember.checked) localStorage.setItem('casaBarretto.rememberedEmail', email.value.trim());
    else localStorage.removeItem('casaBarretto.rememberedEmail');
  } catch (_) { /* Storage is optional. */ }
  window.location.assign('DashBoard.html');
});

[email, password].forEach(input => input.addEventListener('input', () => {
  message.hidden = true;
  password.removeAttribute('aria-invalid');
  password.removeAttribute('aria-describedby');
}));

[['forgot-password', 'help-dialog'], ['open-library', 'library-dialog']].forEach(([buttonId, dialogId]) => {
  const dialog = document.getElementById(dialogId);
  document.getElementById(buttonId).addEventListener('click', () => dialog.showModal());
  dialog.addEventListener('click', event => { if (event.target === dialog) {
    const bounds = dialog.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
  } });
});
