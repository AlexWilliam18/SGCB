<?php
/**
 * Opções de "Tema" e "Cor de destaque", iguais no menu do perfil (nav.php) e no login.
 * O comportamento (marcar a escolha, gravar no navegador e aplicar) fica no tema.js;
 * as cores ficam no tema.css; o head.php aplica a preferência ao carregar a página.
 */

$iconeOk = htmlspecialchars(BASE_URL . 'assets/img/icones/ok.svg');
// Visto (ok.svg) que aparece só na opção escolhida (aria-checked="true").
$visto = '<img class="icone profile-menu-check" src="' . $iconeOk . '" alt="" width="16" height="14">';
?>
      <p class="profile-menu-title">Tema</p>
      <button class="profile-menu-item" type="button" role="menuitemradio" data-theme-option="escuro"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/></svg>Escuro<?= $visto ?></button>
      <button class="profile-menu-item" type="button" role="menuitemradio" data-theme-option="claro"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>Claro<?= $visto ?></button>
      <button class="profile-menu-item" type="button" role="menuitemradio" data-theme-option="sistema"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>Do sistema<?= $visto ?></button>
      <p class="profile-menu-title">Cor de destaque</p>
      <button class="profile-menu-item" type="button" role="menuitemradio" data-accent-option="floresta"><span class="profile-menu-swatch" data-swatch="floresta"></span>Verde floresta<?= $visto ?></button>
      <button class="profile-menu-item" type="button" role="menuitemradio" data-accent-option="folha"><span class="profile-menu-swatch" data-swatch="folha"></span>Verde folha<?= $visto ?></button>
      <button class="profile-menu-item" type="button" role="menuitemradio" data-accent-option="musgo"><span class="profile-menu-swatch" data-swatch="musgo"></span>Verde musgo<?= $visto ?></button>
