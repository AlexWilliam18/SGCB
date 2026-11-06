'use strict';
/*
 * Listagem de Contratos de Locação: pesquisa, filtros (status e período), paginação
 * e diálogo "Prévia do contrato" com impressão / salvar em PDF.
 * Os dados vêm de /api/contratos (public/api/index.php → ContratoController → Contrato).
 * Por enquanto a tela só consulta; o cadastro de contrato virá numa próxima etapa.
 */
(() => {
  const $ = id => document.getElementById(id);
  const url = new URLSearchParams(location.search);

  // Rótulos e classes de cor de cada status gravado no banco.
  const STATUS = {
    RASCUNHO: { texto: 'Rascunho', classe: 'draft' },
    ATIVO: { texto: 'Ativo', classe: 'active' },
    FINALIZADO: { texto: 'Finalizado', classe: 'finished' },
    CANCELADO: { texto: 'Cancelado', classe: 'canceled' },
  };
  const PERIODICIDADE = { DIARIA: 'Diária', SEMANAL: 'Semanal', QUINZENAL: 'Quinzenal', MENSAL: 'Mensal' };

  // Filtros atuais; começam com o que estiver na URL (ex.: ao recarregar a página).
  const state = {
    q: url.get('q') || '',
    status: url.get('status') || '',
    data: /^\d{4}-\d{2}-\d{2}$/.test(url.get('data') || '') ? url.get('data') : '',
    page: Math.max(1, Number(url.get('page')) || 1),
    pages: 1,
  };

  // ---------------------------------------------------------------------------
  // Formatação
  // ---------------------------------------------------------------------------

  const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

  // Data do banco (AAAA-MM-DD) → DD/MM/AAAA.
  function date(value) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value || '');
    return m ? `${m[3]}/${m[2]}/${m[1]}` : '—';
  }

  // CPF (11 dígitos) ou CNPJ (14 dígitos) com pontuação.
  function documento(value) {
    const d = String(value || '').replace(/\D/g, '');
    if (d.length === 11) return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    if (d.length === 14) return d.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    return value || '—';
  }

  // CEP 12245100 → 12.245-100 (mesmo padrão do Figma).
  function cep(value) {
    const d = String(value || '').replace(/\D/g, '');
    return d.length === 8 ? d.replace(/(\d{2})(\d{3})(\d{3})/, '$1.$2-$3') : value || '';
  }

  // Número exibido: rascunhos ainda não têm número, então mostra o id.
  function numero(contrato) {
    return contrato.numero_contrato || 'Rascunho #' + contrato.id;
  }

  // Etiqueta colorida de status.
  function badge(status) {
    const info = STATUS[status] || { texto: status || '—', classe: '' };
    const span = document.createElement('span');
    span.className = 'contract-status ' + info.classe;
    span.textContent = info.texto;
    return span;
  }

  // ---------------------------------------------------------------------------
  // Tabela
  // ---------------------------------------------------------------------------

  // Linha única com ícone e mensagem (carregando, vazio ou erro).
  function emptyRow(text) {
    const tr = document.createElement('tr'),
      td = document.createElement('td'),
      div = document.createElement('div'),
      icon = document.createElement('span'),
      p = document.createElement('p');
    td.colSpan = 8;
    td.className = 'clients-empty';
    icon.className = 'icon icon-document';
    icon.setAttribute('aria-hidden', 'true');
    p.textContent = text;
    div.append(icon, p);
    td.append(div);
    tr.append(td);
    $('contract-rows').replaceChildren(tr);
  }

  // Célula de texto simples.
  function cell(text, className) {
    const td = document.createElement('td');
    td.textContent = text;
    if (className) td.className = className;
    return td;
  }

  // Uma linha por contrato: número, cliente (com CPF/CNPJ), datas, itens, valor, status e "Ver".
  function renderRows(items) {
    if (!items.length) {
      emptyRow('Nenhum contrato encontrado.');
      return;
    }
    $('contract-rows').replaceChildren();
    items.forEach(contrato => {
      const tr = document.createElement('tr');

      // Número do contrato (clicável, abre a prévia) e número da locação abaixo.
      const numberCell = document.createElement('td'),
        numberButton = document.createElement('button');
      numberButton.type = 'button';
      numberButton.className = 'contract-number';
      numberButton.textContent = numero(contrato);
      numberButton.setAttribute('aria-label', 'Ver contrato ' + numero(contrato));
      numberButton.addEventListener('click', () => showContract(contrato.id));
      numberCell.append(numberButton);
      if (contrato.numero_locacao) {
        const small = document.createElement('small');
        small.textContent = 'Locação ' + contrato.numero_locacao;
        numberCell.append(small);
      }

      // Cliente com o documento em cinza abaixo.
      const clientCell = cell(contrato.cliente || '—'),
        doc = document.createElement('small');
      doc.textContent = documento(contrato.cpf_cnpj);
      clientCell.append(doc);

      // Status e botão "Ver".
      const statusCell = document.createElement('td');
      statusCell.append(badge(contrato.status));
      const actions = document.createElement('td'),
        button = document.createElement('button');
      button.type = 'button';
      button.className = 'view-client';
      button.textContent = 'Ver contrato';
      button.setAttribute('aria-label', 'Ver contrato ' + numero(contrato));
      button.addEventListener('click', () => showContract(contrato.id));
      actions.append(button);

      tr.append(
        numberCell,
        clientCell,
        cell(date(contrato.data_inicio)),
        cell(contrato.data_fim ? date(contrato.data_fim) : 'Indeterminado'),
        cell(String(contrato.itens ?? 0)),
        cell(money.format(contrato.valor_total || 0), 'contract-value'),
        statusCell,
        actions
      );
      $('contract-rows').append(tr);
    });
  }

  // Números das páginas (até 5 botões em volta da página atual).
  function renderPages() {
    const container = $('page-numbers');
    container.replaceChildren();
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
          loadContracts();
        });
      container.append(button);
    }
    $('previous-page').disabled = state.page <= 1;
    $('next-page').disabled = state.page >= state.pages;
  }

  // ---------------------------------------------------------------------------
  // Busca na API; uma nova busca cancela a anterior e respostas atrasadas são ignoradas (requestId).
  // ---------------------------------------------------------------------------

  let activeController = null,
    requestId = 0,
    debounce;
  async function loadContracts() {
    const id = ++requestId;
    if (activeController) activeController.abort();
    activeController = new AbortController();
    const controller = activeController,
      timeout = setTimeout(() => controller.abort(), 15000);
    $('contracts-main').setAttribute('aria-busy', 'true');
    $('retry-contracts').disabled = true;
    $('previous-page').disabled = true;
    $('next-page').disabled = true;
    let message = 'Não foi possível carregar os contratos. Verifique a conexão com o banco e tente novamente.';
    try {
      // Monta ?q=&status=&data=&page= só com os filtros preenchidos.
      const params = new URLSearchParams();
      for (const [key, value] of Object.entries(state)) {
        if (key !== 'pages' && value !== '' && (key !== 'page' || value !== 1)) params.set(key, value);
      }
      const response = await fetch(window.SGCB_API + 'contratos?' + params, {
        signal: controller.signal,
        cache: 'no-store',
        headers: { Accept: 'application/json' },
      });
      const data = await response.json().catch(() => null);
      if (response.status === 403 && data?.error) message = data.error;
      if (!response.ok) throw Error('contracts-unavailable');
      if (!data || !Array.isArray(data.items) || !Number.isInteger(data.total)) throw Error('invalid-contracts');
      if (id !== requestId) return;
      state.page = data.page;
      state.pages = data.pages;
      renderRows(data.items);
      renderPages();
      $('page-summary').textContent = `${data.total} contrato(s) • página ${state.page} de ${state.pages}`;
      $('contracts-notice').hidden = true;
      // Guarda os filtros na URL (recarregar a página mantém a pesquisa).
      params.set('page', state.page);
      if (state.page === 1) params.delete('page');
      const search = params.toString();
      history.replaceState(null, '', location.pathname + (search ? '?' + search : ''));
    } catch (_) {
      if (id !== requestId) return;
      emptyRow('Não foi possível carregar os contratos.');
      $('contracts-message').textContent = message;
      $('contracts-notice').hidden = false;
      $('page-summary').textContent = 'Dados indisponíveis';
      $('page-numbers').replaceChildren();
    } finally {
      clearTimeout(timeout);
      if (id === requestId) {
        $('contracts-main').setAttribute('aria-busy', 'false');
        $('retry-contracts').disabled = false;
      }
    }
  }

  // ---------------------------------------------------------------------------
  // Eventos dos filtros e da paginação
  // ---------------------------------------------------------------------------

  // Mostra "Selecione uma data" quando o campo de período está vazio.
  function syncDatePlaceholder() {
    const filled = Boolean($('contract-date').value);
    $('date-field').classList.toggle('has-date', filled);
    $('date-placeholder').hidden = filled;
  }

  // Lê os campos do formulário de filtros e volta para a página 1.
  function readFilters() {
    state.q = $('contract-search').value.trim();
    state.status = $('contract-status').value;
    state.data = $('contract-date').value;
    state.page = 1;
  }
  $('contract-search').value = state.q;
  $('contract-status').value = state.status;
  $('contract-date').value = state.data;
  syncDatePlaceholder();

  // Enter na pesquisa.
  $('contract-filters').addEventListener('submit', event => {
    event.preventDefault();
    clearTimeout(debounce);
    readFilters();
    loadContracts();
  });
  // Digitação: espera 350 ms sem digitar para não chamar a API a cada letra.
  $('contract-search').addEventListener('input', () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
      readFilters();
      loadContracts();
    }, 350);
  });
  // Status e período pesquisam na hora.
  ['contract-status', 'contract-date'].forEach(id =>
    $(id).addEventListener('change', () => {
      clearTimeout(debounce);
      syncDatePlaceholder();
      readFilters();
      loadContracts();
    })
  );
  // Botão Limpar.
  $('clear-contract-filters').addEventListener('click', () => {
    clearTimeout(debounce);
    $('contract-filters').reset();
    syncDatePlaceholder();
    Object.assign(state, { q: '', status: '', data: '', page: 1 });
    loadContracts();
    $('contract-search').focus();
  });
  // Página anterior / próxima.
  $('previous-page').addEventListener('click', () => {
    if (state.page > 1) {
      state.page--;
      loadContracts();
    }
  });
  $('next-page').addEventListener('click', () => {
    if (state.page < state.pages) {
      state.page++;
      loadContracts();
    }
  });
  $('retry-contracts').addEventListener('click', loadContracts);

  // ---------------------------------------------------------------------------
  // Diálogo "Prévia do contrato" (GET /api/contratos/{id}), no visual do Figma
  // ---------------------------------------------------------------------------

  // Monta as linhas "Rótulo: valor" da prévia.
  function previewLines(c) {
    const itens = c.itens || [];
    const unidades = (c.unidades || []).map(u => u.codigo).filter(Boolean);
    const quantidade = itens.reduce((total, i) => total + (i.quantidade || 0), 0);
    const entrega = c.dados_entrega_snapshot;
    const endereco = entrega
      ? `${entrega.rua || ''}, ${entrega.numero || 'S/N'} • ${entrega.bairro || ''} • ${entrega.cidade || ''} - ${entrega.uf || ''} • ${cep(entrega.cep)}`
      : 'endereço não informado';
    const periodo = c.data_fim ? `${date(c.data_inicio)} a ${date(c.data_fim)}` : `a partir de ${date(c.data_inicio)} (prazo indeterminado)`;
    const cobranca = [PERIODICIDADE[c.periodicidade] || c.periodicidade || '—', c.condicao_pagamento].filter(Boolean).join(' • ');
    const mercadoria = itens.reduce((total, i) => total + (i.subtotal || 0), 0);

    return [
      ['Contrato', `${numero(c)}${c.numero_locacao ? ' • Locação: ' + c.numero_locacao : ''}`],
      ['Cliente', c.cliente],
      ['CPF / CNPJ', documento(c.cpf_cnpj)],
      ['Equipamento', itens.map(i => i.produto || i.descricao).join(', ') || '—'],
      ['Unidades', `${unidades.join(', ') || 'a definir'} • Quantidade: ${quantidade}`],
      ['Período', periodo],
      ['Cobrança', `${cobranca} • Mercadoria: ${money.format(mercadoria)}`],
      ['Frete', `${money.format(c.frete_deslocamento || 0)} • Total: ${money.format(c.valor_total || 0)}`],
      ['Caução', money.format(c.caucao || 0)],
      ['Entrega', endereco],
    ];
  }

  let detailRequest = 0;
  async function showContract(id) {
    const current = ++detailRequest;
    const container = $('contract-detail');
    container.textContent = 'Carregando contrato…';
    if (!$('contract-dialog').open) $('contract-dialog').showModal();
    try {
      const response = await fetch(window.SGCB_API + 'contratos/' + encodeURIComponent(id), { cache: 'no-store' });
      if (!response.ok) throw Error();
      const c = await response.json();
      if (current !== detailRequest || !$('contract-dialog').open) return;
      container.replaceChildren();

      // Documento: marca, título, status e as linhas de dados (área que vai para a impressão).
      const sheet = document.createElement('article');
      sheet.className = 'contract-sheet';
      const brand = document.createElement('p'),
        title = document.createElement('h3'),
        status = document.createElement('p');
      brand.className = 'contract-brand';
      brand.textContent = 'CASA BARRETTO';
      title.textContent = 'CONTRATO DE LOCAÇÃO DE EQUIPAMENTOS';
      status.className = 'contract-sheet-status';
      status.append('Status: ', badge(c.status));
      sheet.append(brand, title, status);
      previewLines(c).forEach(([label, value]) => {
        const p = document.createElement('p');
        p.className = 'contract-line';
        p.textContent = `${label}: ${value}`;
        sheet.append(p);
      });

      // Parcelas (quando o contrato já foi gerado).
      if ((c.parcelas || []).length) {
        const p = document.createElement('p');
        p.className = 'contract-line';
        p.textContent = 'Parcelas: ' + c.parcelas.map(pa => `${pa.numero}ª ${date(pa.vencimento)} ${money.format(pa.valor_devido)}`).join(' • ');
        sheet.append(p);
      }
      const note = document.createElement('p');
      note.className = 'contract-note';
      note.textContent = 'Prévia gerada a partir dos dados cadastrados no sistema.';
      sheet.append(note);
      container.append(sheet);

      // Ações: imprimir / salvar como PDF (pelo próprio navegador) e fechar.
      const actions = document.createElement('div');
      actions.className = 'detail-actions';
      const print = document.createElement('button'),
        close = document.createElement('button');
      print.type = 'button';
      print.className = 'detail-action primaria';
      print.textContent = 'Imprimir / salvar PDF';
      print.addEventListener('click', () => {
        document.body.classList.add('printing-contract');
        window.print();
      });
      close.type = 'button';
      close.className = 'detail-action';
      close.textContent = 'Fechar prévia';
      close.addEventListener('click', () => $('contract-dialog').close());
      actions.append(print, close);
      container.append(actions);
    } catch (_) {
      if (current === detailRequest) {
        container.textContent = 'Não foi possível abrir o contrato. Feche esta janela e tente novamente.';
      }
    }
  }
  // Depois da impressão, volta a mostrar a página inteira.
  window.addEventListener('afterprint', () => document.body.classList.remove('printing-contract'));
  // Fechar o diálogo invalida uma resposta que ainda esteja a caminho.
  $('contract-dialog').addEventListener('close', () => {
    detailRequest++;
  });
  // Clique fora da caixa fecha a prévia.
  $('contract-dialog').addEventListener('click', event => {
    if (event.target === $('contract-dialog')) $('contract-dialog').close();
  });

  loadContracts();
})();
