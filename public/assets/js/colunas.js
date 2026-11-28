'use strict';

/*
 * Colunas ajustáveis em todas as tabelas do sistema (carregado em todas as páginas pelo footer.php).
 *
 *  - Entre os títulos das colunas aparece uma linha divisória (.col-resizer). Arrastando essa linha
 *    para o lado a coluna fica mais larga ou mais estreita; a tabela rola na horizontal (.table-scroll)
 *    quando passa da largura da tela, então nenhuma informação fica cortada.
 *  - Duplo clique na linha: ajusta a coluna ao maior conteúdo dela (ex.: data inteira, sem "…").
 *  - Teclado: Tab até a linha e setas ← / → (Shift = passo maior). Home volta às larguras originais.
 *  - A página sempre abre com as larguras originais (tabela ocupando todo o espaço). O ajuste vale
 *    só enquanto a página estiver aberta: ao atualizar (F5), volta ao padrão.
 */
(() => {
  const MINIMO = 56;      // largura mínima de uma coluna, em px
  const PASSO = 16;       // passo das setas do teclado (Shift: 4x)

  function iniciar(tabela) {
    const titulos = Array.from(tabela.tHead ? tabela.tHead.rows[0].cells : []);
    if (titulos.length < 2) {
      return;
    }

    // Onde a largura de cada coluna é aplicada: no <col> do <colgroup> ou, sem ele, no próprio <th>.
    const cols = tabela.querySelectorAll('colgroup col');
    const alvos = cols.length === titulos.length ? Array.from(cols) : titulos;
    // Estilos originais, para o "voltar ao padrão" (tecla Home).
    const originais = alvos.map(a => a.style.width);
    const tabelaOriginal = { width: tabela.style.width, minWidth: tabela.style.minWidth, layout: tabela.style.tableLayout };

    let larguras = null; // larguras em px depois do primeiro ajuste

    // Congela as larguras atuais em px: daí em diante cada coluna muda sozinha, sem empurrar as outras.
    function congelar() {
      if (larguras) {
        return;
      }
      larguras = titulos.map(th => Math.round(th.getBoundingClientRect().width));
      aplicar();
    }

    function aplicar() {
      alvos.forEach((alvo, i) => { alvo.style.width = larguras[i] + 'px'; });
      tabela.style.tableLayout = 'fixed';
      // Nunca menor que o espaço disponível: se as colunas somarem menos, a sobra é dividida entre elas
      // e a tabela continua preenchendo o cartão inteiro, como no padrão.
      tabela.style.minWidth = '100%';
      tabela.style.width = larguras.reduce((soma, l) => soma + l, 0) + 'px';
    }

    function redimensionar(i, largura) {
      congelar();
      larguras[i] = Math.max(MINIMO, Math.round(largura));
      aplicar();
    }

    function restaurar() {
      larguras = null;
      alvos.forEach((alvo, i) => { alvo.style.width = originais[i]; });
      tabela.style.width = tabelaOriginal.width;
      tabela.style.minWidth = tabelaOriginal.minWidth;
      tabela.style.tableLayout = tabelaOriginal.layout;
    }

    // Maior conteúdo da coluna, medido sem quebra de linha (cópia invisível dentro da própria célula,
    // para herdar fonte e tamanho).
    function larguraIdeal(i) {
      let maior = 0;
      Array.from(tabela.rows).forEach(linha => {
        const celula = linha.cells[i];
        if (!celula || celula.colSpan > 1) {
          return;
        }
        const medida = document.createElement('div');
        medida.style.cssText = 'position:absolute;visibility:hidden;white-space:nowrap;width:auto;display:inline-block';
        Array.from(celula.childNodes).forEach(no => {
          if (!(no instanceof Element && no.classList.contains('col-resizer'))) {
            medida.append(no.cloneNode(true));
          }
        });
        celula.append(medida);
        const estilo = getComputedStyle(celula);
        const largura = medida.scrollWidth + parseFloat(estilo.paddingLeft) + parseFloat(estilo.paddingRight) + 2;
        medida.remove();
        maior = Math.max(maior, largura);
      });
      return maior;
    }

    // Uma linha divisória no lado direito de cada título (a última coluna também, para alargar a tabela).
    titulos.forEach((th, i) => {
      th.classList.add('col-resizable');
      const linha = document.createElement('span');
      linha.className = 'col-resizer';
      linha.tabIndex = 0;
      linha.setAttribute('role', 'separator');
      linha.setAttribute('aria-orientation', 'vertical');
      linha.setAttribute('aria-label', 'Ajustar largura da coluna ' + (th.textContent.trim() || (i + 1))
        + '. Arraste, use as setas ou dê duplo clique para caber o conteúdo.');
      linha.title = 'Arraste para ajustar • duplo clique para caber o conteúdo';
      th.append(linha);

      // A linha de destaque desce pela tabela inteira enquanto a pessoa aponta ou arrasta.
      const mostrarGuia = () => { linha.style.setProperty('--guia', tabela.offsetHeight + 'px'); };
      linha.addEventListener('pointerenter', mostrarGuia);
      linha.addEventListener('focus', mostrarGuia);

      linha.addEventListener('pointerdown', evento => {
        if (evento.button !== 0) {
          return;
        }
        evento.preventDefault();
        congelar();
        mostrarGuia();
        const inicioX = evento.clientX;
        const inicioLargura = larguras[i];
        linha.setPointerCapture(evento.pointerId);
        linha.classList.add('is-dragging');
        document.documentElement.classList.add('is-resizing-col');

        const mover = e => { redimensionar(i, inicioLargura + e.clientX - inicioX); mostrarGuia(); };
        const soltar = () => {
          linha.removeEventListener('pointermove', mover);
          linha.removeEventListener('pointerup', soltar);
          linha.removeEventListener('pointercancel', soltar);
          linha.classList.remove('is-dragging');
          document.documentElement.classList.remove('is-resizing-col');
        };
        linha.addEventListener('pointermove', mover);
        linha.addEventListener('pointerup', soltar);
        linha.addEventListener('pointercancel', soltar);
      });

      // Duplo clique: a coluna fica do tamanho do maior conteúdo (ex.: data completa).
      linha.addEventListener('dblclick', evento => {
        evento.preventDefault();
        redimensionar(i, larguraIdeal(i));
      });

      linha.addEventListener('keydown', evento => {
        const passo = evento.shiftKey ? PASSO * 4 : PASSO;
        if (evento.key === 'ArrowRight' || evento.key === 'ArrowLeft') {
          evento.preventDefault();
          congelar();
          redimensionar(i, larguras[i] + (evento.key === 'ArrowRight' ? passo : -passo));
        } else if (evento.key === 'Enter') {
          evento.preventDefault();
          redimensionar(i, larguraIdeal(i));
        } else if (evento.key === 'Home') {
          evento.preventDefault();
          restaurar();
        }
      });

      // Clique na linha não deve acionar ordenação/ação do título.
      linha.addEventListener('click', evento => evento.stopPropagation());
    });

  }

  document.querySelectorAll('.table-scroll table').forEach(iniciar);
})();
