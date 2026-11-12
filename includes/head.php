<?php
/**
 * Início do HTML (<head> e abertura do <body>), igual para todas as páginas.
 *
 * @var string   $titulo       Título da aba; recebe o sufixo " | Casa Barretto".
 * @var string[] $css          (opcional) Arquivos de public/assets/css/ a carregar, ex.: ['login.css'].
 * @var string[] $fontesExtras (opcional) Famílias extras do Google Fonts, ex.: ['Inter:wght@400;700'].
 * @var string   $classeBody   (opcional) Classe CSS do <body>, ex.: 'form-page'.
 */

$titulo       = $titulo ?? 'SGCB';
$css          = $css ?? [];
$fontesExtras = $fontesExtras ?? [];
$classeBody   = $classeBody ?? '';
// Pasta dos ícones SVG oficiais (classe .icone no dashboard.css), disponível em todas as páginas.
$icones       = htmlspecialchars(BASE_URL . 'assets/img/icones/');

// Fontes da identidade visual + as extras pedidas pela página.
$familias = array_merge($fontesExtras, ['Figtree:wght@400;600;700;800', 'Libre+Caslon+Text:wght@400;700']);
$urlFontes = 'https://fonts.googleapis.com/css2?family=' . implode('&family=', $familias) . '&display=swap';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#1b4332">
  <!-- Tema e cor de destaque: aplica a preferência salva (menu do perfil, painel.js) ANTES de
       desenhar a página, para não piscar o tema claro. "sistema" segue o Windows/celular. -->
  <script>
    (function () {
      var raiz = document.documentElement, tema = 'sistema', destaque = 'floresta';
      try {
        tema = localStorage.getItem('sgcb-tema') || tema;
        destaque = localStorage.getItem('sgcb-destaque') || destaque;
      } catch (e) {}
      var escuroDoSistema = window.matchMedia('(prefers-color-scheme: dark)');
      raiz.dataset.themePreference = tema;
      raiz.dataset.theme = tema === 'escuro' || (tema === 'sistema' && escuroDoSistema.matches) ? 'dark' : 'light';
      raiz.dataset.accent = destaque;
      // Se o sistema trocar de tema com a página aberta, acompanha (só no modo "Do sistema").
      escuroDoSistema.addEventListener('change', function (ev) {
        if (raiz.dataset.themePreference === 'sistema') raiz.dataset.theme = ev.matches ? 'dark' : 'light';
      });
    })();
  </script>
  <title><?= htmlspecialchars($titulo) ?> | Casa Barretto</title>
  <!-- Favicon (logotipo) em todas as páginas; "?v=" evita que o navegador fique com o ícone antigo em cache -->
  <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(BASE_URL . 'assets/img/logo/favicon.svg?v=' . (@filemtime(__DIR__ . '/../public/assets/img/logo/favicon.svg') ?: 0)) ?>">
  <!-- Figtree para textos, Libre Caslon Text para o logotipo -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="<?= htmlspecialchars($urlFontes) ?>" rel="stylesheet">
<?php
// "?v=" recebe a data de modificação do arquivo: quando o CSS muda, a URL muda e o navegador baixa a versão nova.
// tema.css (variáveis de cor do tema claro/escuro) vem sempre primeiro.
foreach (array_merge(['tema.css'], $css) as $arquivoCss):
  $versaoCss = @filemtime(__DIR__ . '/../public/assets/css/' . $arquivoCss) ?: 0; ?>
  <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL . 'assets/css/' . $arquivoCss . '?v=' . $versaoCss) ?>">
<?php endforeach; ?>
</head>

<body<?= $classeBody !== '' ? ' class="' . htmlspecialchars($classeBody) . '"' : '' ?>>
