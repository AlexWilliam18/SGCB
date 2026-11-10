'use strict';
/*
 * Diálogo padrão "Descartar alterações?" (includes/dialogo-descartar.php), usado por todos os cadastros.
 * O JS da página chama SgcbDescartar.abrir(destino, antesDeSair) quando há dados não salvos:
 *   - "Continuar preenchendo" (ou clique fora / Esc) fecha o diálogo e mantém a página;
 *   - "Descartar e sair" roda antesDeSair() (ex.: dirty = false) e vai para o destino.
 */
window.SgcbDescartar = (() => {
  const dialog = document.getElementById('discard-dialog'),
    stay = document.getElementById('discard-stay');
  let destino = '',
    antes = null;

  stay.addEventListener('click', () => dialog.close());
  document.getElementById('discard-confirm').addEventListener('click', () => {
    if (antes) antes();
    location.assign(destino);
  });
  // Fecha ao clicar fora da caixa (o padding do diálogo também é o próprio <dialog>, por isso confere a posição).
  dialog.addEventListener('click', event => {
    const r = dialog.getBoundingClientRect();
    if (
      event.target === dialog &&
      (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom)
    )
      dialog.close();
  });

  return {
    abrir(url, antesDeSair) {
      destino = url;
      antes = antesDeSair || null;
      dialog.showModal();
      stay.focus();
    },
  };
})();
