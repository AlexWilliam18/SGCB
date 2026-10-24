<?php
/**
 * footer.php — fechamento padrão do HTML (scripts, </body> e </html>) das páginas do SGCB.
 *
 * Finalidade: carregar os scripts da página e fechar o documento aberto por includes/head.php.
 * Depende de: src/bootstrap.php já carregado (constante BASE_URL) e includes/head.php já incluído.
 * Usado por:  páginas de public/ (ex.: public/login.php), via require, como última instrução.
 *
 * Variáveis esperadas (definidas pela página antes do require):
 *   @var string[] $js (opcional) Arquivos de public/assets/js/ a carregar, ex.: ['login.js'].
 */

$js = $js ?? [];

// Scripts da página com caminho montado a partir da BASE_URL (funciona em qualquer nível de pasta).
// "defer" mantém o mesmo comportamento do arquivo original: o script roda após o HTML ser lido.
foreach ($js as $arquivoJs): ?>
  <script src="<?= htmlspecialchars(BASE_URL . 'assets/js/' . $arquivoJs) ?>" defer></script>
<?php endforeach; ?>
</body>

</html>
