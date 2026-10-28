'use strict';
/*
 * Gerenciamento de manutenções: indicadores, pesquisa, filtros, paginação e diálogo de detalhes.
 * Os dados vêm de /api/manutencoes e /api/manutencoes/{id} (back-end ainda não implementado).
 * Os filtros e a página ficam na URL (?q=&prioridade=&localidade=&data=&page=), então
 * recarregar ou compartilhar o link mantém a mesma consulta.
 */
(() => {
  const $ = id => document.getElementById(id);
  // Caminho das imagens, vindo do data-img-url do <main>.
  const imgUrl = $('maintenance-main').dataset.imgUrl;
  const params = new URLSearchParams(location.search);
  const state = {
    q: params.get('q') || '',
    prioridade: params.get('prioridade') || '',
    localidade: params.get('localidade') || '',
    data: params.get('data') || '',
    page: Math.max(1, Number(params.get('page')) || 1),
    pages: 1,
  };
  const statusLabels = { AGENDADA: 'Agendada', EM_MANUTENCAO: 'Em manutenção', CONCLUIDA: 'Concluída', CANCELADA: 'Cancelada' };
  const priorityLabels = { BAIXA: 'Baixa', MEDIA: 'Média', ALTA: 'Alta', URGENTE: 'Urgente' };
  const metricKeys = ['em_manutencao', 'agendadas', 'urgentes', 'concluidas'];
  // Chave do filtro no state/URL → id do campo no formulário.
  const fields = {
    q: 'maintenance-search',
    prioridade: 'maintenance-priority',
    localidade: 'maintenance-location',
    data: 'maintenance-date',
  };
  // Preenche os campos com os filtros da URL (a localidade só depois que as opções chegam da API).
  Object.entries(fields).forEach(([key, id]) => {
    if (key !== 'localidade') $(id).value = state[key];
  });

  // Lê o corpo da resposta como JSON. Se o servidor devolver HTML (ex.: página 404 enquanto a
  // API não existe), retorna null em vez de deixar o erro "Unexpected token '<'" chegar à tela.
  function readJson(response) {
    return response.json().catch(() => null);
  }

  // "2026-10-27" ou "2026-10-27 14:00:00" → "27/10/2026".
  function dateLabel(value) {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
    return match ? `${match[3]}/${match[2]}/${match[1]}` : '—';
  }

  // Mostra/esconde o texto "Selecione uma data" e a linha "Filtros aplicados".
  function updateFilters() {
    document.querySelector('.date-input').classList.toggle('has-value', Boolean($('maintenance-date').value));
    $('filter-feedback').hidden = !Object.keys(fields).some(key => state[key]);
  }

  // Linha única da tabela com ícone e mensagem (carregando, vazio ou erro).
  function emptyRow(message) {
    const row = document.createElement('tr'),
      cell = document.createElement('td'),
      box = document.createElement('div'),
      img = document.createElement('img'),
      text = document.createElement('p');
    cell.colSpan = 7;
    cell.className = 'clients-empty';
    img.src = imgUrl + 'manutencoes/49f98.svg';
    img.alt = '';
    img.width = img.height = 32;
    text.textContent = message;
    box.append(img, text);
    cell.append(box);
    row.append(cell);
    $('maintenance-rows').replaceChildren(row);
  }

  // Selo colorido de status/prioridade; a classe CSS vem do valor em minúsculas (ver manutencoes.css).
  function badge(value, labels, prefix = '') {
    const element = document.createElement('span');
    element.className = 'maintenance-badge ' + prefix + String(value).toLowerCase();
    element.textContent = labels[value] || value || '—';
    return element;
  }

  // Monta as linhas com textContent (nunca innerHTML), evitando HTML vindo do banco.
  function renderRows(items) {
    if (!items.length) {
      emptyRow('Nenhuma manutenção encontrada.');
      return;
    }
    $('maintenance-rows').replaceChildren();
    items.forEach(item => {
      const row = document.createElement('tr');
      [
        item.produto,
        item.codigo,
        item.localidade || '—',
        badge(item.status, statusLabels),
        badge(item.prioridade, priorityLabels, 'priority-'),
        dateLabel(item.ultima_vistoria_em),
      ].forEach(value => {
        const cell = document.createElement('td');
        if (value instanceof Node) cell.append(value);
        else cell.textContent = value;
        row.append(cell);
      });
      const action = document.createElement('td'),
        button = document.createElement('button');
      button.className = 'view-client';
      button.type = 'button';
      button.textContent = 'Ver';
      button.setAttribute('aria-label', 'Ver manutenção de ' + item.codigo);
      button.addEventListener('click', () => showDetail(item.id));
      action.append(button);
      row.append(action);
      $('maintenance-rows').append(row);
    });
  }

  // Paginação: até 5 botões numerados ao redor da página atual.
  function renderPages() {
    $('page-numbers').replaceChildren();
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
      $('page-numbers').append(button);
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
    const id = ++requestId;
    controller?.abort();
    controller = new AbortController();
    const current = controller,
      timeout = setTimeout(() => current.abort(), 15000);
    $('maintenance-main').setAttribute('aria-busy', 'true');
    $('retry-maintenance').disabled = true;
    document.querySelectorAll('.pagination button').forEach(button => (button.disabled = true));
    // Só vão para a URL os filtros preenchidos (e a página, se não for a 1).
    const query = new URLSearchParams();
    Object.entries(state).forEach(([key, value]) => {
      if (key !== 'pages' && value !== '' && !(key === 'page' && value === 1)) query.set(key, value);
    });
    updateFilters();
    try {
      const response = await fetch('/api/manutencoes?' + query, {
        cache: 'no-store',
        signal: current.signal,
        headers: { Accept: 'application/json' },
      });
      const data = await readJson(response);
      if (!response.ok || !data)
        throw Error(data?.error || 'Não foi possível carregar as manutenções. Verifique a conexão com o banco e tente novamente.');
      // Confere o formato da resposta antes de usar (listas, números inteiros e indicadores ≥ 0).
      if (
        !Array.isArray(data.items) ||
        !Array.isArray(data.localidades) ||
        !Number.isInteger(data.total) ||
        !Number.isInteger(data.page) ||
        !Number.isInteger(data.pages) ||
        !metricKeys.every(key => Number.isInteger(data.metrics?.[key]) && data.metrics[key] >= 0)
      )
        throw Error('O servidor retornou dados inválidos. Tente novamente.');
      if (id !== requestId) return;
      Object.assign(state, { page: data.page, pages: data.pages });
      renderRows(data.items);
      renderPages();
      metricKeys.forEach(key => ($('metric-' + key).textContent = new Intl.NumberFormat('pt-BR').format(data.metrics[key])));
      // Localidades do filtro; mantém a escolhida mesmo se ela não vier mais na lista.
      const locations = $('maintenance-location');
      locations.replaceChildren(new Option('Todos', ''));
      data.localidades.forEach(value => locations.append(new Option(value, value)));
      if (state.localidade && !data.localidades.includes(state.localidade))
        locations.append(new Option(state.localidade, state.localidade));
      locations.value = state.localidade;
      $('page-summary').textContent = `${data.total} manutenções • página ${state.page} de ${state.pages}`;
      $('maintenance-notice').hidden = true;
      // Atualiza a URL sem recarregar a página (a API pode ter corrigido o número da página).
      if (state.page === 1) query.delete('page');
      else query.set('page', state.page);
      const search = query.toString();
      history.replaceState(null, '', location.pathname + (search ? '?' + search : ''));
    } catch (error) {
      if (id !== requestId) return;
      emptyRow('Não foi possível carregar as manutenções.');
      metricKeys.forEach(key => ($('metric-' + key).textContent = '—'));
      $('maintenance-message').textContent =
        error.name === 'AbortError'
          ? 'A consulta demorou mais que o esperado. Tente novamente.'
          : error.message || 'Verifique a conexão e tente novamente.';
      $('maintenance-notice').hidden = false;
      $('page-summary').textContent = 'Dados indisponíveis';
      $('page-numbers').replaceChildren();
    } finally {
      clearTimeout(timeout);
      if (id === requestId) {
        $('maintenance-main').setAttribute('aria-busy', 'false');
        $('retry-maintenance').disabled = false;
      }
    }
  }

  // Lê os campos, volta para a página 1 e consulta de novo.
  function applyFilters() {
    clearTimeout(debounce);
    Object.entries(fields).forEach(([key, id]) => (state[key] = $(id).value.trim()));
    state.page = 1;
    load();
  }
  $('maintenance-filters').addEventListener('submit', event => {
    event.preventDefault();
    applyFilters();
  });
  // Pesquisa espera 350 ms sem digitação antes de consultar (evita uma requisição por tecla).
  $('maintenance-search').addEventListener('input', () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 350);
  });
  ['maintenance-priority', 'maintenance-location', 'maintenance-date'].forEach(id =>
    $(id).addEventListener('change', applyFilters)
  );
  $('clear-maintenance-filters').addEventListener('click', () => {
    $('maintenance-filters').reset();
    applyFilters();
    $('maintenance-search').focus();
  });
  $('retry-maintenance').addEventListener('click', () => {
    clearTimeout(debounce);
    load();
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

  // Diálogo "Dados da manutenção": busca os detalhes de uma manutenção pelo id.
  let detailId = 0,
    detailController;
  async function showDetail(identifier) {
    const id = ++detailId;
    detailController?.abort();
    detailController = new AbortController();
    const current = detailController,
      timeout = setTimeout(() => current.abort(), 15000),
      box = $('maintenance-detail');
    box.textContent = 'Carregando manutenção…';
    if (!$('maintenance-dialog').open) $('maintenance-dialog').showModal();
    try {
      const response = await fetch('/api/manutencoes/' + encodeURIComponent(identifier), {
        cache: 'no-store',
        signal: current.signal,
      });
      const item = await readJson(response);
      if (!response.ok || !item) throw Error(item?.error || 'Não foi possível abrir a manutenção.');
      if (id !== detailId || !$('maintenance-dialog').open) return;
      const list = document.createElement('dl');
      list.className = 'detail-grid';
      const money =
        item.custo === null || item.custo === undefined
          ? '—'
          : new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(item.custo));
      [
        ['Contêiner', item.produto],
        ['Código da unidade', item.codigo],
        ['Localidade', item.localidade],
        ['Tipo', item.tipo],
        ['Status', statusLabels[item.status]],
        ['Prioridade', priorityLabels[item.prioridade]],
        ['Data prevista', dateLabel(item.data_prevista)],
        ['Última vistoria', dateLabel(item.ultima_vistoria_em)],
        ['Iniciada em', dateLabel(item.iniciada_em)],
        ['Concluída em', dateLabel(item.concluida_em)],
        ['Responsável', item.responsavel_usuario || item.responsavel_externo],
        ['Custo', money],
      ].forEach(([label, value]) => {
        const row = document.createElement('div'),
          dt = document.createElement('dt'),
          dd = document.createElement('dd');
        dt.textContent = label;
        dd.textContent = value || '—';
        row.append(dt, dd);
        list.append(row);
      });
      const description = document.createElement('p');
      description.className = 'maintenance-description';
      description.textContent = item.descricao || 'Sem descrição.';
      box.replaceChildren(list, description);
    } catch (error) {
      if (id !== detailId || !$('maintenance-dialog').open) return;
      box.textContent =
        error.name === 'AbortError'
          ? 'A consulta demorou mais que o esperado.'
          : error.message || 'Não foi possível abrir a manutenção.';
      const retry = document.createElement('button');
      retry.type = 'button';
      retry.className = 'view-client';
      retry.textContent = 'Tentar novamente';
      retry.addEventListener('click', () => showDetail(identifier));
      box.append(document.createElement('br'), retry);
    } finally {
      clearTimeout(timeout);
    }
  }
  // Fechar o diálogo cancela a consulta em andamento.
  $('maintenance-dialog').addEventListener('close', () => {
    detailId++;
    detailController?.abort();
  });

  updateFilters();
  load();
})();
