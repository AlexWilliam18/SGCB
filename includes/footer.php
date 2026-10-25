<?php
/**
 * Fim do HTML: scripts da página, </body> e </html>. Último require de cada página.
 *
 * @var string[] $js (opcional) Arquivos de public/assets/js/ a carregar, ex.: ['login.js'].
 */

$js = $js ?? [];

// "defer" roda o script depois que o HTML é lido; "?v=" (data de modificação) evita JS antigo em cache.
foreach ($js as $arquivoJs):
  $versaoJs = @filemtime(__DIR__ . '/../public/assets/js/' . $arquivoJs) ?: 0; ?>
  <script src="<?= htmlspecialchars(BASE_URL . 'assets/js/' . $arquivoJs . '?v=' . $versaoJs) ?>" defer></script>
<?php endforeach; ?>
</body>

</html>
