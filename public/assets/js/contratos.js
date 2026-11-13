'use strict';
/*
 * Listagem de Contratos de Locação: pesquisa, filtros (status e período), paginação
 * e diálogo "Prévia do contrato" (o botão de PDF ainda não tem ação: fora do MVP).
 * Na prévia: "Editar contrato" (números, observações, entrega, responsável e vencimentos, com a versão
 * anterior guardada) e histórico de versões (segunda via).
 * Os dados vêm de /api/contratos (public/api/index.php → ContratoController → Contrato).
 * O cadastro fica em cadastro.php (cadastro-contrato.js); ?ver=ID abre a prévia ao chegar.
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
    // Ordem alfabética: só 'az' ou 'za' (vazio = ordem padrão da tela).
    ordem: ['az', 'za'].includes(url.get('ordem')) ? url.get('ordem') : '',
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
      icon = document.createElement('img'),
      p = document.createElement('p');
    td.colSpan = 8;
    td.className = 'clients-empty';
    // Ícone oficial de documento (public/assets/img/icones/), decorativo: alt vazio.
    icon.className = 'icone icone--xg';
    icon.src = window.SGCB_BASE + 'assets/img/icones/documento.svg';
    icon.alt = '';
    icon.width = 32;
    icon.height = 32;
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
    state.ordem = $('contract-order').value;
    state.page = 1;
  }
  $('contract-search').value = state.q;
  $('contract-status').value = state.status;
  $('contract-date').value = state.data;
  $('contract-order').value = state.ordem;
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
  ['contract-status', 'contract-date', 'contract-order'].forEach(id =>
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
    Object.assign(state, { q: '', status: '', data: '', ordem: '', page: 1 });
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
    // Local de entrega do próprio contrato (contrato_enderecos_entrega); a referência vai no fim, se houver.
    const entrega = c.entrega;
    const endereco = entrega
      ? `${entrega.rua || ''}, ${entrega.numero || 'S/N'} • ${entrega.bairro || ''} • ${entrega.cidade || ''} - ${entrega.uf || ''} • ${cep(entrega.cep)}` +
        (entrega.referencia ? ` • Ref.: ${entrega.referencia}` : '')
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
      // Responsável pela assinatura e observações só aparecem quando preenchidos.
      ...(c.dados_cliente_snapshot?.representante_legal
        ? [['Responsável', `${c.dados_cliente_snapshot.representante_legal} • CPF ${documento(c.dados_cliente_snapshot.cpf_representante)}`]]
        : []),
      ...(c.observacoes ? [['Observações', c.observacoes]] : []),
    ];
  }

  let detailRequest = 0;
  // Contrato aberto na prévia (versão atual), usado pelo formulário de edição e pelo "Voltar".
  let currentContract = null;
  // Contratos nesses status podem ter a edição limitada (finalizado/cancelado ficam como estão).
  const EDITAVEIS = ['RASCUNHO', 'ATIVO'];

  // Data e hora do banco (AAAA-MM-DD HH:MM:SS) → DD/MM/AAAA às HH:MM.
  function dateTime(value) {
    const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(value || '');
    return m ? `${m[3]}/${m[2]}/${m[1]} às ${m[4]}:${m[5]}` : date(value);
  }

  // Elemento com classe e texto (atalho para montar a prévia e o formulário).
  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  // Botão da barra de ações da prévia.
  function actionButton(text, className, onClick) {
    const button = el('button', 'detail-action' + (className ? ' ' + className : ''), text);
    button.type = 'button';
    button.addEventListener('click', onClick);
    return button;
  }

  // Folha do contrato (marca, título, status e linhas). versionInfo != null = cópia de uma versão guardada.
  function contractSheet(c, versionInfo) {
    const sheet = el('article', 'contract-sheet');
    const status = el('p', 'contract-sheet-status');
    status.append('Status: ', badge(c.status));
    sheet.append(el('p', 'contract-brand', 'CASA BARRETTO'), el('h3', '', 'CONTRATO DE LOCAÇÃO DE EQUIPAMENTOS'), status);
    previewLines(c).forEach(([label, value]) => sheet.append(el('p', 'contract-line', `${label}: ${value}`)));

    // Parcelas (quando o contrato já foi gerado).
    if ((c.parcelas || []).length) {
      sheet.append(
        el('p', 'contract-line', 'Parcelas: ' + c.parcelas.map(pa => `${pa.numero}ª ${date(pa.vencimento)} ${money.format(pa.valor_devido)}`).join(' • '))
      );
    }
    sheet.append(
      el(
        'p',
        'contract-note',
        versionInfo
          ? `Segunda via: cópia da versão ${versionInfo.versao}, guardada antes da alteração de ${dateTime(versionInfo.alterado_em)}.`
          : 'Prévia gerada a partir dos dados cadastrados no sistema.'
      )
    );
    return sheet;
  }

  // Histórico: uma linha por versão guardada (com botão "Ver") e a versão atual no fim.
  function versionHistory(c) {
    const box = el('section', 'contract-versions');
    box.setAttribute('aria-label', 'Histórico de versões');
    box.append(el('h4', '', 'Histórico de versões'));
    const list = el('ol');
    c.versoes.forEach(v => {
      const item = el('li');
      const text = el('div');
      text.append(
        el('strong', '', `Versão ${v.versao}${v.versao === 1 ? ' (original)' : ''}`),
        el('span', '', `Substituída em ${dateTime(v.alterado_em)}${v.alterado_por ? ' por ' + v.alterado_por : ''}`),
        el('span', '', `Alterado: ${v.alteracoes || '—'} • Motivo: ${v.motivo}`)
      );
      item.append(text, actionButton('Ver', 'pequena', () => showVersion(c.id, v.versao)));
      list.append(item);
    });
    const atual = el('li', 'current');
    atual.append(el('strong', '', `Versão ${c.versao_atual} (atual)`));
    list.append(atual);
    box.append(list);
    return box;
  }

  // Desenha a prévia da versão atual. notice = aviso verde no topo (ex.: depois de salvar).
  function renderContract(c, notice) {
    currentContract = c;
    const container = $('contract-detail');
    container.replaceChildren();
    if (notice) container.append(el('p', 'action-success', notice));
    container.append(contractSheet(c, null));
    if ((c.versoes || []).length) container.append(versionHistory(c));

    // Ações: "Editar contrato" (só rascunho/ativo); "Imprimir / salvar PDF" é só o botão (PDF fora do MVP); "Fechar prévia".
    const actions = el('div', 'detail-actions');
    if (EDITAVEIS.includes(c.status)) actions.append(actionButton('Editar contrato', 'primaria', () => renderEditForm(c)));
    actions.append(
      actionButton('Imprimir / salvar PDF', EDITAVEIS.includes(c.status) ? '' : 'primaria', () => {}),
      actionButton('Fechar prévia', '', () => $('contract-dialog').close())
    );
    container.append(actions);
  }

  async function showContract(id) {
    const current = ++detailRequest;
    const container = $('contract-detail');
    $('contract-dialog-title').textContent = 'Prévia do contrato';
    container.textContent = 'Carregando contrato…';
    if (!$('contract-dialog').open) $('contract-dialog').showModal();
    try {
      const response = await fetch(window.SGCB_API + 'contratos/' + encodeURIComponent(id), { cache: 'no-store' });
      if (!response.ok) throw Error();
      const c = await response.json();
      if (current !== detailRequest || !$('contract-dialog').open) return;
      renderContract(c);
    } catch (_) {
      if (current === detailRequest) {
        container.textContent = 'Não foi possível abrir o contrato. Feche esta janela e tente novamente.';
      }
    }
  }

  // Segunda via: mostra a cópia guardada de uma versão (GET /api/contratos/{id}/versoes?versao=N), só leitura.
  async function showVersion(id, versao) {
    const current = ++detailRequest;
    const container = $('contract-detail');
    $('contract-dialog-title').textContent = `Segunda via — versão ${versao}`;
    container.textContent = 'Carregando versão…';
    try {
      const response = await fetch(window.SGCB_API + 'contratos/' + encodeURIComponent(id) + '/versoes?versao=' + versao, { cache: 'no-store' });
      if (!response.ok) throw Error();
      const v = await response.json();
      if (current !== detailRequest) return;
      container.replaceChildren();
      const aviso = el('p', 'contract-version-banner');
      aviso.textContent =
        `Versão ${v.versao}${v.versao === 1 ? ' (original)' : ''} — somente leitura. ` +
        `Substituída em ${dateTime(v.alterado_em)}${v.alterado_por ? ' por ' + v.alterado_por : ''}. Motivo: ${v.motivo}`;
      container.append(aviso, contractSheet(v.dados, v));
      const actions = el('div', 'detail-actions');
      actions.append(
        actionButton('Imprimir / salvar PDF', '', () => {}),
        actionButton('Voltar à versão atual', 'primaria', () => {
          $('contract-dialog-title').textContent = 'Prévia do contrato';
          renderContract(currentContract);
        })
      );
      container.append(actions);
    } catch (_) {
      if (current === detailRequest) container.textContent = 'Não foi possível abrir esta versão. Feche a janela e tente novamente.';
    }
  }

  // ---------------------------------------------------------------------------
  // Edição limitada (POST /api/contratos/{id}/editar): número do contrato e da locação, observações, local de entrega,
  // responsável pela assinatura e vencimento das parcelas sem pagamento.
  // Valores, itens, datas da locação e contêineres não mudam aqui.
  // ---------------------------------------------------------------------------

  const UFS = 'AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO'.split(' ');

  // Campo do formulário: <label><span>Rótulo</span><input|select|textarea></label>.
  function formField(label, name, { tag = 'input', type = 'text', value = '', maxLength, required, wide } = {}) {
    const wrapper = el('label', wide ? 'wide' : '');
    const control = document.createElement(tag);
    control.name = name;
    if (tag === 'input') control.type = type;
    if (tag === 'select') {
      control.append(new Option('Selecione', ''));
      UFS.forEach(uf => control.append(new Option(uf, uf)));
    }
    if (tag === 'textarea') control.rows = 3;
    if (maxLength) control.maxLength = maxLength;
    control.value = value ?? '';
    wrapper.append(el('span', '', label + (required ? ' *' : '')), control);
    return wrapper;
  }

  function renderEditForm(c) {
    ++detailRequest; // descarta respostas antigas que ainda estejam chegando
    $('contract-dialog-title').textContent = 'Editar contrato ' + numero(c);
    const container = $('contract-detail');
    container.replaceChildren();

    const form = el('form', 'contract-edit');
    form.noValidate = true;
    form.append(
      el(
        'p',
        'contract-edit-help',
        'Valores, cliente, produtos, contêineres e datas da locação não mudam. Ao salvar, a versão atual fica guardada como segunda via no histórico.'
      )
    );

    // 1. Motivo (obrigatório) e observações.
    const geral = el('fieldset', 'detail-form');
    geral.append(el('legend', '', 'Alteração'));
    const gridGeral = el('div', 'detail-form-grid');
    gridGeral.append(
      formField('Motivo da alteração', 'motivo', { maxLength: 500, required: true, wide: true }),
      // Identificação: número do contrato (obrigatório no contrato ativo) e da locação.
      formField('Número do contrato', 'numero_contrato', { value: c.numero_contrato, maxLength: 50, required: c.status === 'ATIVO' }),
      formField('Número da locação', 'numero_locacao', { value: c.numero_locacao, maxLength: 50 }),
      formField('Observações', 'observacoes', { tag: 'textarea', value: c.observacoes, maxLength: 800, wide: true })
    );
    geral.append(gridGeral);

    // 2. Local de entrega: endereço do cliente (cópia do cadastro de hoje) ou outro digitado.
    const entrega = c.entrega || {};
    const local = el('fieldset', 'detail-form');
    local.append(el('legend', '', 'Local de entrega'));
    const origens = el('div', 'contract-edit-options');
    [
      ['CLIENTE', 'Endereço do cliente'],
      ['OUTRO', 'Outro endereço'],
    ].forEach(([value, text]) => {
      const label = el('label');
      const radio = document.createElement('input');
      radio.type = 'radio';
      radio.name = 'entrega_origem';
      radio.value = value;
      radio.checked = (entrega.endereco_do_cliente === false ? 'OUTRO' : 'CLIENTE') === value;
      label.append(radio, ' ' + text);
      origens.append(label);
    });
    const gridEntrega = el('div', 'detail-form-grid');
    gridEntrega.append(
      formField('CEP', 'cep', { value: entrega.cep ? window.BuscaCep.mascaraCep(entrega.cep) : '', maxLength: 9, required: true }),
      formField('Rua', 'rua', { value: entrega.rua, maxLength: 200, required: true }),
      formField('Número', 'numero', { value: entrega.numero, maxLength: 20, required: true }),
      formField('Bairro', 'bairro', { value: entrega.bairro, maxLength: 100, required: true }),
      formField('Cidade', 'cidade', { value: entrega.cidade, maxLength: 100, required: true }),
      formField('Estado', 'uf', { tag: 'select', value: entrega.uf, required: true }),
      formField('Complemento', 'complemento', { value: entrega.complemento, maxLength: 150 }),
      formField('Referência', 'referencia', { value: entrega.referencia, maxLength: 150 })
    );
    const cepStatus = el('small', 'cep-status');
    cepStatus.setAttribute('aria-live', 'polite');
    local.append(origens, gridEntrega, cepStatus);

    // 3. Responsável pela assinatura (nome + CPF juntos, ou os dois vazios).
    const snapshot = c.dados_cliente_snapshot || {};
    const assinatura = el('fieldset', 'detail-form');
    assinatura.append(el('legend', '', 'Responsável pela assinatura'));
    const gridAssinatura = el('div', 'detail-form-grid');
    gridAssinatura.append(
      formField('Nome', 'responsavel_nome', { value: snapshot.representante_legal, maxLength: 100 }),
      formField('CPF', 'responsavel_cpf', { value: snapshot.cpf_representante ? window.Validacao.mascaraCpf(snapshot.cpf_representante) : '', maxLength: 14 })
    );
    assinatura.append(gridAssinatura);

    // 4. Vencimentos: só parcelas sem pagamento e não canceladas podem mudar.
    const parcelas = c.parcelas || [];
    const venc = el('fieldset', 'detail-form');
    if (parcelas.length) {
      venc.append(el('legend', '', 'Vencimento das parcelas'));
      const gridVenc = el('div', 'detail-form-grid');
      parcelas.forEach(pa => {
        const editavel = pa.status !== 'CANCELADA' && !(pa.valor_recebido > 0);
        const campo = formField(`${pa.numero}ª • ${money.format(pa.valor_devido)}${editavel ? '' : ' (paga/cancelada)'}`, 'venc_' + pa.id, {
          type: 'date',
          value: pa.vencimento,
        });
        const input = campo.querySelector('input');
        input.disabled = !editavel;
        input.dataset.parcela = pa.id;
        input.dataset.original = pa.vencimento;
        gridVenc.append(campo);
      });
      venc.append(gridVenc);
    }

    const feedback = el('p', 'detail-feedback');
    feedback.setAttribute('role', 'alert');
    feedback.hidden = true;
    const actions = el('div', 'detail-actions');
    const salvar = el('button', 'detail-action primaria', 'Salvar alterações');
    salvar.type = 'submit';
    actions.append(
      actionButton('Cancelar', '', () => {
        $('contract-dialog-title').textContent = 'Prévia do contrato';
        renderContract(c);
      }),
      salvar
    );

    form.append(geral, local, assinatura);
    if (parcelas.length) form.append(venc);
    form.append(actions, feedback);
    container.append(form);

    const campo = name => form.elements[name];
    // Endereço do cliente: os campos do endereço ficam travados (o servidor copia o cadastro do cliente);
    // a referência continua editável nas duas opções.
    const enderecoCampos = ['cep', 'rua', 'numero', 'bairro', 'cidade', 'uf', 'complemento'];
    function syncOrigem() {
      const doCliente = form.querySelector('input[name="entrega_origem"]:checked')?.value === 'CLIENTE';
      enderecoCampos.forEach(name => (campo(name).disabled = doCliente));
    }
    origens.addEventListener('change', syncOrigem);
    syncOrigem();
    // CEP: máscara e busca de rua/bairro/cidade/UF ao completar 8 dígitos (cep.js).
    window.BuscaCep.ligar(Object.fromEntries(['cep', 'rua', 'numero', 'bairro', 'cidade', 'uf'].map(k => [k, campo(k)])), cepStatus);
    // CPF do responsável com máscara.
    campo('responsavel_cpf').addEventListener('input', event => (event.target.value = window.Validacao.mascaraCpf(event.target.value)));
    campo('motivo').focus();

    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (!campo('motivo').value.trim()) {
        feedback.textContent = 'Informe o motivo da alteração.';
        feedback.hidden = false;
        campo('motivo').focus();
        return;
      }
      // Só vão os vencimentos que mudaram.
      const vencimentos = [...form.querySelectorAll('input[data-parcela]')]
        .filter(input => !input.disabled && input.value !== input.dataset.original)
        .map(input => ({ parcela_id: Number(input.dataset.parcela), vencimento: input.value }));
      const dados = {
        motivo: campo('motivo').value.trim(),
        numero_contrato: campo('numero_contrato').value.trim(),
        numero_locacao: campo('numero_locacao').value.trim(),
        observacoes: campo('observacoes').value,
        entrega_origem: form.querySelector('input[name="entrega_origem"]:checked').value,
        entrega: Object.fromEntries(['cep', 'rua', 'numero', 'bairro', 'cidade', 'uf', 'complemento', 'referencia'].map(k => [k, campo(k).value])),
        responsavel: { nome: campo('responsavel_nome').value.trim(), cpf: campo('responsavel_cpf').value },
        vencimentos,
      };
      salvar.disabled = true;
      feedback.hidden = true;
      try {
        // Chamada à API: grava as correções e guarda a versão anterior (acoes.js envia o token CSRF).
        const atualizado = await SGCBAcoes.executar('contratos/' + encodeURIComponent(c.id) + '/editar', dados);
        $('contract-dialog-title').textContent = 'Prévia do contrato';
        renderContract(
          atualizado,
          `Contrato atualizado. A versão anterior foi guardada como versão ${atualizado.versao_atual - 1} no histórico.`
        );
        loadContracts();
      } catch (erro) {
        feedback.textContent = erro.message;
        feedback.hidden = false;
      } finally {
        salvar.disabled = false;
      }
    });
  }
  // Fechar o diálogo invalida uma resposta que ainda esteja a caminho.
  $('contract-dialog').addEventListener('close', () => {
    detailRequest++;
  });
  // Clique fora da caixa fecha a prévia.
  $('contract-dialog').addEventListener('click', event => {
    if (event.target === $('contract-dialog')) $('contract-dialog').close();
  });

  loadContracts();

  // ?ver=ID (vindo do cadastro, botão "Visualizar documento"): abre a prévia desse contrato.
  const verId = url.get('ver');
  if (/^\d+$/.test(verId || '')) {
    showContract(Number(verId));
    url.delete('ver');
  }
})();
