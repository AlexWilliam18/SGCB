'use strict';

/*
 * Tema (claro / escuro / do sistema) e cor de destaque, em todas as páginas (login e admin).
 *
 *  - As opções vêm do includes/opcoes-tema.php (botões com data-theme-option e data-accent-option).
 *  - A escolha fica salva no navegador (localStorage) e o script do head.php a aplica ao carregar,
 *    antes de desenhar a página. Aqui ela é aplicada na hora em que a pessoa clica.
 *  - No login há um botão próprio (.theme-button) que abre o menu de tema; no admin quem abre
 *    é o menu do perfil (painel.js).
 */
(() => {
  const root = document.documentElement;
  const darkSystem = window.matchMedia('(prefers-color-scheme: dark)');

  function save(key, value) {
    try {
      localStorage.setItem(key, value);
    } catch (_) {
      /* navegador sem armazenamento (aba anônima bloqueada): vale só para esta página */
    }
  }

  // Marca com o visto (ok.svg) a opção escolhida em cada grupo.
  function markChoices() {
    document.querySelectorAll('[data-theme-option]').forEach(item =>
      item.setAttribute('aria-checked', String(item.dataset.themeOption === root.dataset.themePreference))
    );
    document.querySelectorAll('[data-accent-option]').forEach(item =>
      item.setAttribute('aria-checked', String(item.dataset.accentOption === root.dataset.accent))
    );
  }

  // Tema: escuro | claro | sistema ("sistema" segue o Windows/celular).
  document.querySelectorAll('[data-theme-option]').forEach(item =>
    item.addEventListener('click', () => {
      const choice = item.dataset.themeOption;
      root.dataset.themePreference = choice;
      root.dataset.theme = choice === 'escuro' || (choice === 'sistema' && darkSystem.matches) ? 'dark' : 'light';
      save('sgcb-tema', choice);
      markChoices();
    })
  );

  // Cor de destaque: floresta | folha | musgo.
  document.querySelectorAll('[data-accent-option]').forEach(item =>
    item.addEventListener('click', () => {
      root.dataset.accent = item.dataset.accentOption;
      save('sgcb-destaque', item.dataset.accentOption);
      markChoices();
    })
  );

  markChoices();

  // ---------------------------------------------------------------------------
  // Login: botão de tema no canto do card, que abre o menu logo abaixo dele.
  // ---------------------------------------------------------------------------
  const button = document.querySelector('.theme-button');
  if (!button) return;
  const menu = document.getElementById(button.getAttribute('aria-controls'));

  function setMenu(open) {
    menu.hidden = !open;
    button.setAttribute('aria-expanded', String(open));
    if (open) menu.querySelector('.profile-menu-item').focus();
  }
  button.addEventListener('click', () => setMenu(menu.hidden));
  // Fecha ao clicar fora ou com Esc.
  document.addEventListener('click', event => {
    if (!menu.hidden && !menu.contains(event.target) && !button.contains(event.target)) setMenu(false);
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !menu.hidden) {
      setMenu(false);
      button.focus();
    }
  });
})();
