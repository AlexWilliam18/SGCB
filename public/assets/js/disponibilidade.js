'use strict';
/*
 * Disponibilidade futura dos contêineres.
 *  - Diálogo "Consultar disponibilidade" (Contratos de Locação): produto, período e quantidade.
 *    Chama /api/produtos/{id}/disponibilidade e mostra se atende, quantas já estão livres,
 *    quantas dependem de devolução/manutenção e a próxima data que atende.
 *  - SGCBDisponibilidade.upcoming(): seção "Próximas disponibilidades" nos detalhes do produto
 *    (chamada pelo produtos.js).
 * A consulta não cria reserva. Respostas antigas são descartadas (contador "sequence").
 */
(() => {
  const $ = id => document.getElementById(id);
  const date = value => value.split('-').reverse().join('/');
  window.SGCBDisponibilidade = { upcoming };
  const dialog = $('availability-dialog');
  if (!dialog) return;
  const form = $('availability-form'), result = $('availability-result'), retry = $('availability-retry');
  const product = $('availability-product'), start = $('availability-start'), end = $('availability-end'), quantity = $('availability-quantity');
  let controller, sequence = 0, opener, optionsReady = false, retryAction;
  async function api(path, signal) {
    const response = await fetch(window.SGCB_API + path, { cache: 'no-store', signal });
    const data = await response.json();
    if (!response.ok) throw Error(data.error || 'Não foi possível consultar.');
    return data;
  }
  const paragraph = text => {
    const p = document.createElement('p'); p.textContent = text; result.append(p); return p;
  };
  function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }
  // Monta o resultado: veredito (atende / não atende), números, dependências e próxima opção.
  function renderResult(r) {
    result.replaceChildren();
    result.append(element('h3', 'availability-product-name', `${r.produto.codigo} — ${r.produto.nome}`));
    paragraph(`Período: ${date(r.inicio)} a ${date(r.fim)} (último dia incluído).`).className = 'availability-period';
    const tone = !r.atende ? 'unavailable' : r.condicionadas > 0 ? 'conditional' : 'available';
    const status = element('div', `availability-verdict ${tone}`);
    status.append(element('strong', '', r.atende ? 'Atende à solicitação na previsão' : 'Não atende no período escolhido'));
    status.append(element('span', '', r.atende
      ? r.condicionadas > 0 ? 'Parte das unidades depende de devolução ou conclusão de manutenção.' : 'A quantidade prevista está livre durante todo o período.'
      : `Faltam ${r.quantidade - r.disponivel} unidade(s) para atender à quantidade solicitada.`));
    const stats = element('dl', 'availability-stats');
    [
      ['Solicitadas', r.quantidade, '', 'unidades desejadas'],
      ['Previstas disponíveis', r.disponivel, tone, 'durante todo o período'],
      ['Já livres', r.ja_livres, 'available', 'sem depender de eventos'],
      ['Condicionadas', r.condicionadas, r.condicionadas > 0 ? 'conditional' : '', 'dependem de eventos previstos'],
    ].forEach(([label, count, kind, hint]) => {
      const card = element('div', `availability-stat ${kind}`);
      card.append(element('dt', '', label), element('dd', '', String(count)), element('span', 'availability-stat-hint', hint));
      stats.append(card);
    });
    result.append(status, stats);
    const dependencies = element('div', 'availability-dependencies');
    dependencies.append(element('span', '', 'Dependências:'));
    [['Devolução', r.dependencias.devolucao], ['Conclusão de manutenção', r.dependencias.manutencao]].forEach(([label, count]) => {
      const badge = element('span', 'availability-dependency' + (count > 0 ? ' conditional' : ''));
      badge.append(document.createTextNode(label + ' '), element('strong', '', String(count)));
      dependencies.append(badge);
    });
    result.append(dependencies);
    if (r.dependencias.devolucao > 0 && r.dependencias.manutencao > 0) {
      paragraph('Uma mesma unidade pode depender de devolução e manutenção; os totais de dependências não devem ser somados.').className = 'availability-explanation';
    }
    if (r.proxima) {
      const next = element('section', 'availability-next');
      next.append(element('h4', '', `Próxima opção para ${r.quantidade} unidade(s)`),
        element('strong', 'availability-next-dates', `${date(r.proxima.inicio)} a ${date(r.proxima.fim)}`),
        element('p', '', `${r.proxima.disponivel} previstas disponíveis · ${r.proxima.condicionadas} condicionadas a eventos.`));
      result.append(next);
    } else if (!r.atende) {
      const limit = element('div', 'availability-search-limit');
      limit.append(element('strong', '', `Nenhuma opção encontrada com início até ${date(r.limite_busca)}.`),
        element('p', '', 'Esse é o limite da busca e não significa indisponibilidade permanente.'));
      result.append(limit);
    }
    const preparation = element('p', 'availability-preparation');
    preparation.append(element('strong', '', `Preparação: ${r.preparacao_horas} h. `), document.createTextNode('Devolução e nova locação no mesmo dia não são aceitas.'));
    result.append(preparation);
    const details = element('details', 'availability-explanation');
    details.append(element('summary', '', 'Como interpretar esta previsão'),
      element('p', '', 'A consulta não cria reservas. Os números podem mudar com novas operações. Unidades condicionadas só ficam disponíveis se a devolução ou manutenção for concluída no prazo.'),
      element('p', '', `O intervalo de preparação vale após a devolução/conclusão e antes da próxima ocupação. O último dia inclui o dia inteiro. Fuso: America/Sao_Paulo.`));
    result.append(details);
  }
  function error(message, action) {
    result.textContent = message; retryAction = action; retry.hidden = false;
  }
  // Abre o diálogo; na primeira vez carrega a lista de produtos ativos. id = produto já escolhido.
  async function open(id, trigger) {
    sequence++; controller?.abort(); result.setAttribute('aria-busy', 'false');
    opener = trigger || document.activeElement;
    result.replaceChildren(); retry.hidden = true;
    if (!dialog.open) dialog.showModal();
    product.focus();
    if (!optionsReady) {
      try {
        const data = await api('produtos/disponibilidade/opcoes', AbortSignal.timeout(15000));
        product.replaceChildren(new Option('Selecione o produto', ''));
        data.items.forEach(p => product.append(new Option(`${p.codigo} — ${p.nome}`, p.id)));
        start.min = data.hoje; start.value ||= data.hoje; end.min = start.value; end.value ||= start.value;
        optionsReady = true;
      } catch (e) { error(e.message, () => open(id, opener)); return; }
    }
    product.value = id ? String(id) : '';
  }
  // Envia a consulta (limite de 15 s). Uma nova consulta cancela a anterior.
  async function consult() {
    if (!form.reportValidity()) return;
    controller?.abort(); controller = new AbortController();
    const current = ++sequence, timeout = setTimeout(() => controller?.abort(), 15000);
    retry.hidden = true; result.textContent = 'Consultando disponibilidade…'; result.setAttribute('aria-busy', 'true');
    try {
      const params = new URLSearchParams({ data_inicio: start.value, data_fim: end.value, quantidade: quantity.value });
      const r = await api(`produtos/${encodeURIComponent(product.value)}/disponibilidade?${params}`, controller.signal);
      if (current !== sequence || !dialog.open) return;
      renderResult(r);
    } catch (e) {
      if (current === sequence && dialog.open) error(e.name === 'AbortError' ? 'A consulta demorou. Tente novamente.' : e.message, consult);
    } finally {
      clearTimeout(timeout); if (current === sequence) result.setAttribute('aria-busy', 'false');
    }
  }
  form.addEventListener('submit', e => { e.preventDefault(); consult(); });
  form.addEventListener('input', () => {
    sequence++; controller?.abort(); result.replaceChildren(); result.setAttribute('aria-busy', 'false'); retry.hidden = true;
    end.min = start.value || start.min;
    end.setCustomValidity(end.value && start.value && end.value < start.value ? 'O fim não pode preceder o início.' : '');
    quantity.setCustomValidity(Number.isSafeInteger(Number(quantity.value)) && Number(quantity.value) > 0 ? '' : 'Informe uma quantidade inteira positiva.');
  });
  retry.addEventListener('click', () => retryAction?.());
  dialog.addEventListener('close', () => { sequence++; controller?.abort(); opener?.focus(); });
  $('open-availability')?.addEventListener('click', e => open(null, e.currentTarget));
  // Seção "Próximas disponibilidades" nos detalhes do produto, com link para a consulta completa.
  async function upcoming(container, id) {
      const section = document.createElement('section'); section.className = 'availability-upcoming';
      const title = document.createElement('h3'); title.textContent = 'Próximas disponibilidades';
      const content = document.createElement('div'); content.setAttribute('aria-live', 'polite');
      const button = document.createElement('a'); button.className = 'detail-action'; button.textContent = 'Consultar em Contratos de Locação';
      button.href = window.SGCB_BASE + 'admin/locacoes/index.php?produto_id=' + encodeURIComponent(id);
      section.append(title, content, button); container.append(section);
      async function load() {
        content.textContent = 'Carregando previsão…';
        try {
          const r = await api(`produtos/${id}/proximas-disponibilidades`, AbortSignal.timeout(15000));
          const list = document.createElement('ul');
          r.items.forEach(row => { const li = document.createElement('li'); li.textContent = `${date(row.data)}: ${row.disponivel} previstas (${row.condicionadas} condicionadas)`; list.append(li); });
          const note = document.createElement('p');
          note.textContent = r.limite_busca ? `Quantidade para locação de um dia em cada data, até ${date(r.limite_busca)}. Exibimos apenas mudanças de quantidade. Consulte o período completo antes de confirmar.` : 'Produto inativo: indisponível para novos contratos.';
          content.replaceChildren(list, note);
        } catch (e) {
          const b = document.createElement('button'); b.type = 'button'; b.className = 'detail-action'; b.textContent = 'Tentar novamente'; b.addEventListener('click', load);
          content.textContent = e.message + ' '; content.append(b);
        }
      }
      await load();
  }
  window.SGCBDisponibilidade.open = open;
  // Vindo do link do produto (?produto_id=N), já abre a consulta com o produto preenchido.
  const selectedProduct = new URLSearchParams(location.search).get('produto_id');
  if (selectedProduct && /^[1-9]\d*$/.test(selectedProduct)) open(selectedProduct, $('open-availability'));
})();
