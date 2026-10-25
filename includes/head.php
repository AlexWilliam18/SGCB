<?php
/**
 * head.php — abertura padrão do HTML (<!DOCTYPE>, <head> e <body>) das páginas do SGCB.
 *
 * Finalidade: centralizar metatags, fontes e folhas de estilo, para que toda página tenha o mesmo cabeçalho.
 * Depende de: src/bootstrap.php já carregado (constante BASE_URL).
 * Usado por:  páginas de public/ (ex.: public/login.php), via require.
 *
 * Variáveis esperadas (definidas pela página antes do require):
 *   @var string   $titulo Título da aba; recebe o sufixo " | Casa Barretto".
 *   @var string[] $css    (opcional) Arquivos de public/assets/css/ a carregar, ex.: ['login.css'].
 */

// Valores padrão, caso a página não defina as variáveis.
$titulo = $titulo ?? 'SGCB';
$css    = $css ?? [];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#1b4332">
  <title><?= htmlspecialchars($titulo) ?> | Casa Barretto</title>
  <!-- Fontes da identidade visual (Figtree para textos, Libre Caslon Text para o logotipo) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;600;700;800&family=Libre+Caslon+Text:wght@400;700&display=swap"
    rel="stylesheet">
<?php
// Folhas de estilo da página. BASE_URL deixa o caminho absoluto a partir da raiz do site,
// então funciona tanto em public/ quanto em public/admin/<modulo>/.
// "?v=" recebe a data de modificação do arquivo: quando o CSS muda, a URL muda e o
// navegador baixa a versão nova em vez de usar a do cache.
foreach ($css as $arquivoCss):
  $versaoCss = @filemtime(__DIR__ . '/../public/assets/css/' . $arquivoCss) ?: 0; ?>
  <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL . 'assets/css/' . $arquivoCss . '?v=' . $versaoCss) ?>">
<?php endforeach; ?>
</head>

<body>
