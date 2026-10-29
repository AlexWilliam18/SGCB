'use strict';
/*
 * Gerenciamento financeiro: indicadores, pesquisa, filtros, paginação e diálogo de detalhes da parcela.
 * Os dados vêm de /api/financeiro e /api/financeiro/parcelas/{id} (back-end ainda não implementado).
 * Os filtros e a página ficam na URL (?q=&prioridade=&status=&data=&page=), então
 * recarregar ou compartilhar o link mantém a mesma consulta.
 */
(() => {
  const $ = id => document.getElementById(id);
  // Caminho das imagens, vindo do data-img-url do <main>.
  const imgUrl = $('finance-main').dataset.imgUrl;
  const url = new URLSearchParams(location.search);
  const state = {
    q: url.get('q') || '',
    prioridade: url.get('prioridade') || '',
    status: url.get('status') || '',
    data: url.get('data') || '',
    page: Math.max(1, Number(url.get('page')) || 1),
    pages: 1,
  };
  const statuses = { ABERTA: 'Em aberto', PARCIAL: 'Parcial', ATRASADA: 'Em atraso', PAGA: 'Paga', CANCELADA: 'Cancelada' };
  const methods = {
    PIX: 'Pix',
    CREDITO: 'Cartão de crédito',
    DEBITO: 'Cartão de débito',
    BOLETO: 'Boleto',
    DINHEIRO: 'Dinheiro',
    TRANSFERENCIA: 'Transferência',
  };
  // Explicação mostrada na linha "Filtros aplicados" quando há prioridade escolhida.
  const priorities = {
    URGENTE: 'Urgente: parcelas vencidas com saldo.',
    ALTA: 'Alta: vencimento de hoje até 7 dias.',
    MEDIA: 'Média: vencimento após 7 e até 30 dias.',
    BAIXA: 'Baixa: vencimento após 30 dias.',
  };

  // Formatação: R$, datas ("2026-10-29" → "29/10/2026", com hora quando houver) e CPF/CNPJ.
  const money = value =>
    value === null || value === undefined
      ? '—'
      : new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value));
  const shortDate = value => {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value || '');
    return m ? `${m[3]}/${m[2]}/${m[1]}` : '—';
  };
  const dateTime = value => shortDate(value) + (value?.length > 10 ? ' ' + value.slice(11, 16) : '');
  const documentNumber = value => {
    const text = String(value || '');
    if (text.length === 11) return text.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    if (text.length === 14) return text.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    return text || '—';
  };

  // Lê o corpo da resposta como JSON. Se o servidor devolver HTML (ex.: página 404 enquanto a
  // API não existe), retorna null em vez de deixar o erro "Unexpected token '<'" chegar à tela.
  function readJson(response) {
    return response.json().catch(() => null);
  }

  // Preenche os campos com os filtros da URL.
  $('finance-search').value = state.q;
  $('finance-priority').value = state.prioridade;
  $('finance-status').value = state.status;
  $('finance-date').value = state.data;

  // Mostra/esconde a linha "Filtros aplicados" (com o resumo) e o texto "Selecione uma data".
  function updateFilters() {
    const active = !!(state.q || state.prioridade || state.status || state.data);
    $('filter-feedback').hidden = !active;
    $('finance-date').parentElement.classList.toggle('has-value', !!$('finance-date').value);
    $('filter-summary').textContent =
      'Filtros aplicados às parcelas; indicadores gerais. ' +
      (priorities[state.prioridade] || '') +
      (state.data ? ' Vencimento em ' + shortDate(state.data) + '.' : '');
  }

  // Linha única da tabela com ícone e mensagem (carregando, vazio ou erro).
  function emptyRow(text) {
    const tr = document.createElement('tr'),
      td = document.createElement('td'),
      box = document.createElement('div'),
      img = document.createElement('img'),
      p = document.createElement('p');
    td.colSpan = 7;
    td.className = 'clients-empty';
    img.src = imgUrl + 'manutencoes/49f98.svg';
    img.width = 32;
    img.height = 32;
    img.alt = '';
    p.textContent = text;
    box.append(img, p);
    td.append(box);
    tr.append(td);
    $('finance-rows').replaceChildren(tr);
  }

  // Indicadores dos cards; sem dados (erro), volta tudo para "—".
  function metrics(data) {
    for (const key of ['faturamento_mes', 'em_aberto', 'em_atraso', 'prestes_vencer'])
      $('metric-' + key).textContent = data ? (key === 'faturamento_mes' ? money(data[key]) : String(data[key])) : '—';
    $('metric-pagamentos_recebidos').textContent = (data ? data.pagamentos_recebidos : '—') + ' pagamentos recebidos';
    $('metric-clientes_atraso').textContent = (data ? data.clientes_atraso : '—') + ' clientes em atraso';
  }

  // Monta as linhas com textContent (nunca innerHTML), evitando HTML vindo do banco.
  function renderRows(items) {
    if (!items.length) {
      emptyRow('Nenhuma parcela encontrada.');
      return;
    }
    $('finance-rows').replaceChildren();
    items.forEach(item => {
      const tr = document.createElement('tr');
      // Cliente, contrato (+ "Parcela N"), vencimento e valor.
      [item.cliente, item.contrato, shortDate(item.vencimento), money(item.valor_devido)].forEach((value, index) => {
        const td = document.createElement('td');
        td.textContent = value ?? '—';
        if (index === 0) td.title = item.cliente;
        if (index === 1) {
          const span = document.createElement('span');
          span.className = 'installment-number';
          span.textContent = 'Parcela ' + item.numero;
          td.append(span);
        }
        if (index === 3) td.className = 'money-cell';
        tr.append(td);
      });
      // Selo de status; a classe CSS vem do status em minúsculas (ver financeiro.css).
      const status = document.createElement('td'),
        badge = document.createElement('span');
      badge.className = 'finance-badge ' + String(item.status).toLowerCase();
      badge.textContent = statuses[item.status] || item.status;
      status.append(badge);
      const method = document.createElement('td');
      method.textContent = methods[item.ultima_forma] || item.ultima_forma || '—';
      const action = document.createElement('td'),
        button = document.createElement('button');
      button.type = 'button';
      button.className = 'view-client';
      button.textContent = 'Ver parcela';
      button.setAttribute('aria-label', 'Ver parcela ' + item.numero + ' do contrato ' + item.contrato);
      button.addEventListener('click', () => showDetail(item.id));
      action.append(button);
      tr.append(status, method, action);
      $('finance-rows').append(tr);
    });
  }

  // Paginação: até 5 botões numerados ao redor da página atual.
  function renderPages() {
    const box = $('page-numbers');
    box.replaceChildren();
    const start = Math.max(1, Math.min(state.page - 2, state.pages - 4));
    for (let p = start; p <= Math.min(state.pages, start + 4); p++) {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = p;
      button.setAttribute('aria-label', 'Página ' + p);
      if (p === state.page) {
        button.className = 'selected';
        button.setAttribute('aria-current', 'page');
        button.disabled = true;
      } else
        button.addEventListener('click', () => {
          state.page = p;
          load();
        });
      box.append(button);
    }
    $('previous-page').disabled = state.page <= 1;
    $('next-page').disabled = state.page >= state.pages;
  }

  // Consulta a lista. requestId descarta respostas atrasadas quando o usuário muda o filtro
  // antes da anterior terminar; a requisição anterior também é cancelada (abort).
  let requestId = 0,
    controller,
    debounce;
  async function load() {
    const current = ++requestId;
    controller?.abort();
    const active = (controller = new AbortController()),
      timeout = setTimeout(() => active.abort(), 15000);
    $('finance-main').setAttribute('aria-busy', 'true');
    $('retry-finance').disabled = true;
    $('previous-page').disabled = true;
    $('next-page').disabled = true;
    $('page-numbers').querySelectorAll('button').forEach(el => (el.disabled = true));
    try {
      // Só vão para a URL os filtros preenchidos (e a página, se não for a 1).
      const params = new URLSearchParams();
      for (const [key, value] of Object.entries(state))
        if (key !== 'pages' && value !== '' && (key !== 'page' || value !== 1)) params.set(key, value);
      const response = await fetch('/api/financeiro?' + params, { cache: 'no-store', signal: active.signal });
      const data = await readJson(response);
      if (!response.ok || !data)
        throw Error(data?.error || 'Não foi possível carregar o financeiro. Verifique a conexão com o banco e tente novamente.');
      // Confere o formato da resposta antes de usar (listas, números inteiros e faturamento numérico).
      if (
        !Array.isArray(data.items) ||
        !Number.isInteger(data.total) ||
        !Number.isInteger(data.page) ||
        !Number.isInteger(data.pages) ||
        !data.metrics ||
        !['em_aberto', 'em_atraso', 'clientes_atraso', 'prestes_vencer', 'pagamentos_recebidos'].every(k =>
          Number.isInteger(data.metrics[k])
        ) ||
        data.metrics.faturamento_mes === null ||
        !Number.isFinite(Number(data.metrics.faturamento_mes))
      )
        throw Error('O servidor retornou dados inválidos. Tente novamente.');
      if (current !== requestId) return;
      state.page = data.page;
      state.pages = data.pages;
      renderRows(data.items);
      metrics(data.metrics);
      renderPages();
      $('finance-notice').hidden = true;
      $('page-summary').textContent = `${data.total} parcela(s) • página ${state.page} de ${state.pages}`;
      // Atualiza a URL sem recarregar a página (a API pode ter corrigido o número da página).
      if (state.page === 1) params.delete('page');
      else params.set('page', state.page);
      const search = params.toString();
      history.replaceState(null, '', location.pathname + (search ? '?' + search : ''));
    } catch (error) {
      if (current !== requestId) return;
      metrics(null);
      emptyRow('Não foi possível carregar as parcelas.');
      $('finance-message').textContent =
        error.name === 'AbortError'
          ? 'A consulta demorou mais que o esperado. Tente novamente.'
          : error.message || 'Verifique a conexão e tente novamente.';
      $('finance-notice').hidden = false;
      $('page-summary').textContent = 'Dados indisponíveis';
      $('page-numbers').replaceChildren();
    } finally {
      clearTimeout(timeout);
      if (current === requestId) {
        $('finance-main').setAttribute('aria-busy', 'false');
        $('retry-finance').disabled = false;
      }
    }
  }

  // Lê os campos, volta para a página 1 e atualiza a linha "Filtros aplicados".
  function readFilters() {
    Object.assign(state, {
      q: $('finance-search').value.trim(),
      prioridade: $('finance-priority').value,
      status: $('finance-status').value,
      data: $('finance-date').value,
      page: 1,
    });
    updateFilters();
  }
  $('finance-filters').addEventListener('submit', event => {
    event.preventDefault();
    clearTimeout(debounce);
    readFilters();
    load();
  });
  // Pesquisa espera 350 ms sem digitação antes de consultar (evita uma requisição por tecla).
  $('finance-search').addEventListener('input', () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
      readFilters();
      load();
    }, 350);
  });
  ['finance-priority', 'finance-status', 'finance-date'].forEach(id =>
    $(id).addEventListener('change', () => {
      clearTimeout(debounce);
      readFilters();
      load();
    })
  );
  $('clear-finance-filters').addEventListener('click', () => {
    clearTimeout(debounce);
    $('finance-filters').reset();
    Object.assign(state, { q: '', prioridade: '', status: '', data: '', page: 1 });
    updateFilters();
    load();
    $('finance-search').focus();
  });
  $('previous-page').addEventListener('click', () => {
    if (state.page > 1) {
      state.page--;
      load();
    }
  });
  $('next-page').addEventListener('click', () => {
    if (state.page < state.pages) {
      state.page++;
      load();
    }
  });
  $('retry-finance').addEventListener('click', load);

  // Diálogo "Detalhes da parcela": dados, observações e histórico de pagamentos (com estornos).
  let detailId = 0,
    detailController;
  async function showDetail(identifier) {
    const current = ++detailId;
    detailController?.abort();
    const active = (detailController = new AbortController()),
      timeout = setTimeout(() => active.abort(), 15000),
      box = $('finance-detail');
    box.textContent = 'Carregando parcela…';
    if (!$('finance-dialog').open) $('finance-dialog').showModal();
    try {
      const response = await fetch('/api/financeiro/parcelas/' + encodeURIComponent(identifier), {
        cache: 'no-store',
        signal: active.signal,
      });
      const item = await readJson(response);
      if (!response.ok || !item) throw Error(item?.error || 'Não foi possível abrir a parcela.');
      if (current !== detailId || !$('finance-dialog').open) return;
      box.replaceChildren();
      const dl = document.createElement('dl');
      dl.className = 'detail-grid';
      [
        ['Cliente', item.cliente],
        ['CPF / CNPJ', documentNumber(item.cpf_cnpj)],
        ['E-mail', item.email],
        ['Contrato', item.contrato],
        ['Parcela', item.numero],
        ['Competência', shortDate(item.competencia)],
        ['Vencimento', shortDate(item.vencimento)],
        ['Status', statuses[item.status] || item.status],
        ['Valor original', money(item.valor_original)],
        ['Acréscimo', money(item.acrescimo)],
        ['Desconto', money(item.desconto)],
        ['Valor devido', money(item.valor_devido)],
        ['Recebido (sem estornos)', money(item.valor_recebido)],
        ['Saldo', money(item.saldo)],
        ['Última forma de pagamento', methods[item.ultima_forma] || item.ultima_forma],
        ['Último pagamento válido', item.ultimo_pagamento_em ? dateTime(item.ultimo_pagamento_em) : '—'],
      ].forEach(([label, value]) => {
        const div = document.createElement('div'),
          dt = document.createElement('dt'),
          dd = document.createElement('dd');
        dt.textContent = label;
        dd.textContent = String(value ?? '—');
        div.append(dt, dd);
        dl.append(div);
      });
      box.append(dl);
      if (item.observacoes) {
        const note = document.createElement('p');
        note.className = 'finance-note';
        note.textContent = item.observacoes;
        box.append(note);
      }
      // Histórico: "29/10/2026 14:30 • R$ 500,00 • Pix", com a linha de estorno quando houver.
      const section = document.createElement('section');
      section.className = 'payment-history';
      const h = document.createElement('h3');
      h.textContent = 'Histórico de pagamentos';
      section.append(h);
      if (!item.pagamentos?.length) {
        const p = document.createElement('p');
        p.className = 'finance-note';
        p.textContent = 'Nenhum pagamento registrado.';
        section.append(p);
      } else
        item.pagamentos.forEach(payment => {
          const entry = document.createElement('div'),
            p = document.createElement('p');
          entry.className = 'payment-entry';
          p.textContent = `${dateTime(payment.pago_em)} • ${money(payment.valor)} • ${methods[payment.forma] || payment.forma}`;
          entry.append(p);
          if (payment.estornado_em) {
            const reversed = document.createElement('p');
            reversed.className = 'payment-reversed';
            reversed.textContent =
              'Estornado em ' + dateTime(payment.estornado_em) + (payment.motivo_estorno ? ' — ' + payment.motivo_estorno : '');
            entry.append(reversed);
          }
          section.append(entry);
        });
      box.append(section);
    } catch (error) {
      if (current === detailId && $('finance-dialog').open) {
        box.textContent =
          error.name === 'AbortError'
            ? 'A consulta demorou mais que o esperado.'
            : error.message || 'Não foi possível abrir a parcela.';
        const retry = document.createElement('button');
        retry.type = 'button';
        retry.className = 'view-client';
        retry.textContent = 'Tentar novamente';
        retry.addEventListener('click', () => showDetail(identifier));
        box.append(document.createElement('br'), retry);
      }
    } finally {
      clearTimeout(timeout);
    }
  }
  // Fechar o diálogo cancela a consulta em andamento.
  $('finance-dialog').addEventListener('close', () => {
    detailId++;
    detailController?.abort();
  });

  updateFilters();
  load();
})();
