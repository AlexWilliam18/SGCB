<?php
/**
 * Fim do HTML: scripts da página, </body> e </html>. Último require de cada página.
 *
 * @var string[] $js (opcional) Arquivos de public/assets/js/ a carregar, ex.: ['login.js'].
 */

$js = $js ?? [];
?>
  <!-- Para os scripts: endereço da API (ex.: "/SGCB/api/"; os fetch usam SGCB_API + 'clientes'),
       endereço base do site (imagens enviadas) e token CSRF das ações dos diálogos (acoes.js) -->
  <script>
    window.SGCB_API = <?= json_encode(BASE_URL . 'api/', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
    window.SGCB_BASE = <?= json_encode(BASE_URL, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
    window.SGCB_CSRF = <?= json_encode(Autenticacao::tokenCsrf(), JSON_HEX_TAG) ?>;
  </script>
<?php

// "defer" roda o script depois que o HTML é lido; "?v=" (data de modificação) evita JS antigo em cache.
foreach ($js as $arquivoJs):
  $versaoJs = @filemtime(__DIR__ . '/../public/assets/js/' . $arquivoJs) ?: 0; ?>
  <script src="<?= htmlspecialchars(BASE_URL . 'assets/js/' . $arquivoJs . '?v=' . $versaoJs) ?>" defer></script>
<?php endforeach; ?>
</body>

</html>
