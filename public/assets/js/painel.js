'use strict';

/*
 * painel.js — comportamento comum do layout da área administrativa (páginas de public/admin/, com o menu de includes/nav.php):
 * data do cabeçalho, menu lateral no celular, aviso de módulos em desenvolvimento e perfil do usuário.
 */
(() => {
  const $ = id => document.getElementById(id);

  // Data de hoje no cabeçalho, ex.: "sábado, 24 de outubro de 2026".
  const today = $('today');
  if (today) {
    const now = new Date();
    today.dateTime = now.toISOString().slice(0, 10);
    today.textContent = new Intl.DateTimeFormat('pt-BR', {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'}).format(now);
  }

  // Menu lateral (telas até 760px): a classe nav-open no <body> mostra a sidebar (ver dashboard.css).
  const toggle = $('menu-toggle'), backdrop = $('nav-backdrop');
  function setMenu(open) {
    document.body.classList.toggle('nav-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    backdrop.hidden = !open;
    if (open) $('close-menu').focus(); else toggle.focus();
  }
  toggle.addEventListener('click', () => setMenu(true));
  $('close-menu').addEventListener('click', () => setMenu(false));
  backdrop.addEventListener('click', () => setMenu(false));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && document.body.classList.contains('nav-open')) setMenu(false);
  });

  // Diálogo de informação reaproveitado pelos avisos abaixo.
  const dialog = $('info-dialog');
  function showInfo(title, text) {
    $('dialog-title').textContent = title;
    $('dialog-description').textContent = text;
    dialog.showModal();
  }
  // Fecha ao clicar fora da caixa do diálogo.
  dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });

  // Botões com data-module: módulos do CRUD ainda não implementados.
  document.querySelectorAll('[data-module]').forEach(button => button.addEventListener('click', () => {
    showInfo(button.dataset.module, 'Este módulo está em desenvolvimento e estará disponível em breve.');
  }));

  // Perfil: dados do usuário logado vêm dos atributos data-* preenchidos pelo PHP.
  const profile = $('open-profile');
  profile.addEventListener('click', () => {
    showInfo(profile.dataset.name, `${profile.dataset.email} · ${profile.dataset.role}`);
  });
})();
