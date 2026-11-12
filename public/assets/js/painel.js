'use strict';

/*
 * Comportamento comum das páginas do admin: data do cabeçalho, menu lateral no celular,
 * aviso de módulos em desenvolvimento e perfil do usuário.
 */
(() => {
  const $ = id => document.getElementById(id);

  // Data de hoje no cabeçalho, ex.: "sábado, 24 de outubro de 2026".
  const today = $('today');
  if (today) {
    const now = new Date();
    today.dateTime = now.toISOString().slice(0, 10);
    today.textContent = new Intl.DateTimeFormat('pt-BR', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    }).format(now);
  }

  // Menu lateral (telas até 760px): a classe nav-open no <body> mostra a sidebar (ver dashboard.css).
  const toggle = $('menu-toggle'),
    backdrop = $('nav-backdrop');
  function setMenu(open) {
    document.body.classList.toggle('nav-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    backdrop.hidden = !open;
    if (open) $('close-menu').focus();
    else toggle.focus();
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
  dialog.addEventListener('click', event => {
    if (event.target === dialog) dialog.close();
  });

  // Botões com data-module: módulos do CRUD ainda não implementados.
  document.querySelectorAll('[data-module]').forEach(button =>
    button.addEventListener('click', () => {
      showInfo(button.dataset.module, 'Este módulo está em desenvolvimento e estará disponível em breve.');
    })
  );

  // ---------------------------------------------------------------------------
  // Menu do perfil (nav.php): Perfil, Tema, Cor de destaque e Sair
  // ---------------------------------------------------------------------------
  const profile = $('open-profile'),
    menu = $('profile-menu');

  // O menu fica "position: fixed" (a sidebar tem rolagem e cortaria um menu absoluto):
  // abre logo acima do botão do usuário, alinhado à esquerda dele.
  function placeMenu() {
    const box = profile.getBoundingClientRect();
    menu.style.left = box.left + 'px';
    menu.style.bottom = window.innerHeight - box.top + 8 + 'px';
  }
  function setProfileMenu(open) {
    menu.hidden = !open;
    profile.setAttribute('aria-expanded', String(open));
    if (open) {
      placeMenu();
      menu.querySelector('.profile-menu-item').focus();
    }
  }
  profile.addEventListener('click', () => setProfileMenu(menu.hidden));
  // Fecha ao clicar fora, com Esc, ao rolar a sidebar ou redimensionar a janela.
  document.addEventListener('click', event => {
    if (!menu.hidden && !menu.contains(event.target) && !profile.contains(event.target)) setProfileMenu(false);
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !menu.hidden) {
      setProfileMenu(false);
      profile.focus();
    }
  });
  window.addEventListener('resize', () => setProfileMenu(false));
  $('primary-sidebar').addEventListener('scroll', () => setProfileMenu(false));

  // Perfil: dados do usuário logado vêm dos atributos data-* preenchidos pelo PHP.
  $('profile-info').addEventListener('click', () => {
    setProfileMenu(false);
    showInfo(profile.dataset.name, `${profile.dataset.email} · ${profile.dataset.role}`);
  });

  // Tema e cor de destaque (itens do menu): comportamento no tema.js, carregado em todas as páginas.
})();
