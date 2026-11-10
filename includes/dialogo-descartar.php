<?php
/**
 * Diálogo padrão "Descartar alterações?", igual em todos os cadastros
 * (cliente, produto, contrato, manutenção e usuário).
 *
 * Incluir antes do footer.php e carregar o descartar.js antes do JS da página.
 * O JS da página só decide QUANDO perguntar (há dados não salvos) e chama
 * SgcbDescartar.abrir(destino, antesDeSair); o resto (botões, clique fora) fica no descartar.js.
 */
?>
  <dialog class="info-dialog discard-dialog" id="discard-dialog" aria-labelledby="discard-title" aria-describedby="discard-text"><h2 id="discard-title">Descartar alterações?</h2><p id="discard-text">Os dados preenchidos ainda não foram salvos e serão perdidos ao sair.</p><div class="discard-actions"><button class="discard-stay" id="discard-stay" type="button">Continuar preenchendo</button><button class="discard-confirm" id="discard-confirm" type="button">Descartar e sair</button></div></dialog>
