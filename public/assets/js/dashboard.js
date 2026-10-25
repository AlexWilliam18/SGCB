'use strict';
/*
 * Indicadores da Visão Geral: cartões, gráficos dos últimos 12 meses, rankings e últimas locações.
 * Os dados vêm de /api/dashboard (back-end ainda não implementado).
 */
(() => {
  const $ = id => document.getElementById(id);
  const money = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  });
  const numbers = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });
  const rentalLabel = value => numbers.format(value) + (value === 1 ? ' locação' : ' locações');
  function renderChart(id, rows, currency) {
    const chart = $(id),
      axis = chart.querySelector('.chart-axis'),
      grid = chart.querySelector('.gridlines'),
      bars = chart.querySelector('.chart-bars');
    const max = Math.max(0, ...rows.map(row => Number(row.value) || 0));
    const steps = !currency && max <= 1 ? 1 : 5;
    const magnitude = max ? 10 ** Math.floor(Math.log10(max)) : 1;
    const ceiling = max ? (currency ? Math.ceil(max / magnitude) * magnitude : Math.ceil(max / steps) * steps) : 1;
    axis.replaceChildren();
    grid.replaceChildren();
    bars.replaceChildren();
    for (let tick = steps; tick >= 0; tick--) {
      const span = document.createElement('span');
      span.textContent = max
        ? numbers.format((ceiling * tick) / steps)
        : currency && tick
          ? (tick / steps).toFixed(1).replace('.', ',')
          : String(tick / steps);
      axis.append(span);
      grid.append(document.createElement('i'));
    }
    if (max)
      rows.forEach(row => {
        const value = Math.max(0, Number(row.value) || 0),
          date = new Date(row.month + '-01T12:00:00');
        const month = new Intl.DateTimeFormat('pt-BR', { month: 'short' }).format(date).replace('.', '');
        const fullMonth = new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' }).format(date);
        const slot = document.createElement('div');
        slot.className = 'bar-slot';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'chart-bar';
        button.style.setProperty('--bar-height', (value / ceiling) * 100 + '%');
        const label = fullMonth + ': ' + (currency ? money.format(value) : rentalLabel(value));
        button.setAttribute('aria-label', label);
        if (!value) button.tabIndex = -1;
        const tooltip = document.createElement('span');
        tooltip.className = 'bar-label';
        tooltip.textContent = label;
        tooltip.setAttribute('aria-hidden', 'true');
        const caption = document.createElement('span');
        caption.className = 'month-label';
        caption.textContent = month;
        caption.setAttribute('aria-hidden', 'true');
        button.append(tooltip);
        slot.append(button, caption);
        bars.append(slot);
      });
    chart.querySelector('.chart-description').textContent = max
      ? rows.map(row => row.month + ': ' + (currency ? money.format(row.value) : rentalLabel(row.value))).join('; ')
      : 'Sem movimento nos últimos 12 meses.';
  }
  function renderRanking(id, rows) {
    const container = $(id);
    container.replaceChildren();
    if (!rows.length) {
      const p = document.createElement('p');
      p.className = 'ranking-empty';
      p.textContent = 'Sem dados para exibir.';
      container.append(p);
      return;
    }
    const list = document.createElement('ol');
    list.className = 'ranking-list';
    const max = Math.max(1, ...rows.map(row => row.count));
    rows.slice(0, 5).forEach((row, index) => {
      const item = document.createElement('li');
      item.className = 'ranking-item';
      const position = document.createElement('span');
      position.className = 'ranking-position';
      position.textContent = String(index + 1).padStart(2, '0');
      const name = document.createElement('span');
      name.className = 'ranking-name';
      name.textContent = row.name;
      name.title = row.name;
      const count = document.createElement('span');
      count.className = 'ranking-count';
      count.textContent = numbers.format(row.count) + (row.count === 1 ? ' locação' : ' locações');
      const track = document.createElement('span');
      track.className = 'ranking-track';
      track.setAttribute('aria-hidden', 'true');
      const fill = document.createElement('span');
      fill.style.setProperty('--rank-width', (row.count / max) * 100 + '%');
      track.append(fill);
      item.append(position, name, count, track);
      list.append(item);
    });
    container.append(list);
  }
  function localDate(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value || '');
    return match ? `${match[3]}/${match[2]}/${match[1]}` : '—';
  }
  function emptyTable(text) {
    const row = document.createElement('tr');
    row.className = 'empty-row';
    const cell = document.createElement('td');
    cell.colSpan = 7;
    cell.textContent = text;
    row.append(cell);
    $('recent-contracts').replaceChildren(row);
  }
  function renderContracts(rows) {
    if (!rows.length) {
      emptyTable('Nenhuma locação cadastrada.');
      return;
    }
    $('recent-contracts').replaceChildren();
    const labels = {
      ATIVO: 'Ativo',
      RESERVADO: 'Reservado',
      FINALIZADO: 'Finalizado',
      CANCELADO: 'Cancelado',
      PENDENTE: 'Pendente',
    };
    rows.slice(0, 15).forEach(contract => {
      const tr = document.createElement('tr');
      [
        contract.number,
        contract.client,
        localDate(contract.start),
        localDate(contract.end),
        contract.days ?? '—',
        money.format(contract.value),
        labels[contract.status] || contract.status,
      ].forEach((value, index) => {
        const td = document.createElement('td');
        if (index === 0 && /^\/contratos\/\d+\/pdf$/.test(contract.pdf_url || '')) {
          const a = document.createElement('a');
          a.href = contract.pdf_url;
          a.className = 'contract-link';
          a.textContent = value;
          a.target = '_blank';
          a.rel = 'noopener';
          a.setAttribute('aria-label', 'Ver PDF do contrato ' + value);
          td.append(a);
        } else if (index === 6) {
          const badge = document.createElement('span');
          badge.className = 'status status-' + String(contract.status).toLowerCase();
          badge.textContent = value;
          td.append(badge);
        } else {
          td.textContent = value;
          td.title = String(value);
        }
        tr.append(td);
      });
      $('recent-contracts').append(tr);
    });
  }
  // Uma requisição por vez; desiste após 15 s.
  let loading = false;
  async function loadDashboard() {
    if (loading) return;
    loading = true;
    $('dashboard').setAttribute('aria-busy', 'true');
    $('retry-data').disabled = true;
    const controller = new AbortController(),
      timeout = setTimeout(() => controller.abort(), 15000);
    try {
      const response = await fetch('/api/dashboard', {
        headers: { Accept: 'application/json' },
        signal: controller.signal,
        cache: 'no-store',
      });
      if (!response.ok) throw new Error('dashboard-unavailable');
      const data = await response.json(),
        fields = ['revenue_total', 'active_contracts', 'available_units', 'rented_units'];
      if (
        !data.metrics ||
        fields.some(key => !Number.isFinite(data.metrics[key])) ||
        ['monthly_revenue', 'rental_counts', 'top_products', 'top_clients', 'recent_contracts'].some(
          key => !Array.isArray(data[key])
        )
      )
        throw new Error('invalid-dashboard');
      $('revenue-total').textContent = money.format(data.metrics.revenue_total);
      $('active-contracts').textContent = numbers.format(data.metrics.active_contracts);
      $('available-units').textContent = numbers.format(data.metrics.available_units);
      $('rented-units').textContent = numbers.format(data.metrics.rented_units);
      renderChart('revenue-chart', data.monthly_revenue, true);
      renderChart('rentals-chart', data.rental_counts, false);
      renderRanking('top-products', data.top_products);
      renderRanking('top-clients', data.top_clients);
      renderContracts(data.recent_contracts);
      $('data-notice').hidden = true;
    } catch (_) {
      ['revenue-total', 'active-contracts', 'available-units', 'rented-units'].forEach(id => {
        $(id).textContent = '—';
      });
      $('notice-message').textContent =
        'Não foi possível carregar os indicadores. Verifique se o sistema e o banco de dados estão disponíveis.';
      $('data-notice').hidden = false;
      ['top-products', 'top-clients'].forEach(id => {
        $(id).replaceChildren();
        const p = document.createElement('p');
        p.className = 'ranking-empty';
        p.textContent = 'Dados indisponíveis.';
        $(id).append(p);
      });
      ['revenue-chart', 'rentals-chart'].forEach(id => {
        $(id).querySelector('.chart-bars').replaceChildren();
        $(id).querySelector('.chart-description').textContent = 'Dados indisponíveis.';
      });
      emptyTable('Não foi possível carregar as locações.');
    } finally {
      clearTimeout(timeout);
      loading = false;
      $('dashboard').setAttribute('aria-busy', 'false');
      $('retry-data').disabled = false;
    }
  }
  $('retry-data').addEventListener('click', loadDashboard);
  loadDashboard();
})();
