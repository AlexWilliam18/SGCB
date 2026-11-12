'use strict';
/*
 * Cadastro de contrato de locação (public/admin/locacoes/cadastro.php).
 *
 * Fluxo da tela:
 *   1. carrega clientes, produtos e condições de pagamento  → GET  /api/contratos/opcoes
 *   2. ao escolher o cliente, carrega dados e endereços      → GET  /api/clientes/{id}
 *   3. cada item lista os contêineres livres no período      → GET  /api/contratos/unidades
 *   4. o resumo mostra o total calculado pelo servidor        → POST /api/contratos/calcular
 *   5. "Conferir e salvar" abre a prévia; "Confirmar" grava   → POST /api/contratos (gerar: true = Ativo)
 * O servidor valida tudo de novo; as mensagens dele voltam para os campos da tela.
 */
(() => {
  const $ = selector => document.querySelector(selector);
  const form = $('#contract-form');
  const field = name => form.elements.namedItem(name);
  const listUrl = $('#contract-form-main').dataset.listaUrl;
  const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  const BILLING = { DIARIA: 'Diária', SEMANAL: 'Semanal', QUINZENAL: 'Quinzenal', MENSAL: 'Mensal' };
  const STATES = 'AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO'.split(' ');

  // Dados vindos da API e estado da tela.
  let options = { clientes: [], produtos: [], condicoes_pagamento: [] };
  let csrf = window.SGCB_CSRF || '';
  let customer = null;          // cliente completo (com o endereço cadastral)
  let calc = null;              // último cálculo do servidor (null = ainda sem dados suficientes)
  let calcMessage = '';         // motivo de não haver cálculo (ex.: produto sem preço)
  let firstDueEdited = false;   // o usuário mudou o 1º vencimento à mão: não recalcular sozinho
  let itemSequence = 0;
  let dirty = false;            // há dados digitados ainda não salvos (pergunta antes de sair)
  let leaving = false;          // saída já confirmada no diálogo padrão (descartar.js)
  const items = [];             // { id, element, units: [{id, codigo, livre}], loading }

  // ---------------------------------------------------------------------------
  // Formatação e utilidades
  // ---------------------------------------------------------------------------

  const digits = value => String(value || '').replace(/\D/g, '');
  const dateLabel = value => (value ? value.split('-').reverse().join('/') : 'Não informado');
  const reais = value => money.format(Number(value) || 0);
  const option = (value, text) => {
    const element = document.createElement('option');
    element.value = value;
    element.textContent = text;
    return element;
  };
  // Valor de um <input type="number"> como texto "1234.56" (o formato que a API aceita).
  const decimal = input => (Number.isFinite(input.valueAsNumber) ? input.valueAsNumber.toFixed(2) : '0.00');

  // CPF (11) / CNPJ (14) com pontuação; CEP 12345678 → 12345-678.
  function documentLabel(value) {
    const d = digits(value);
    if (d.length === 11) return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    if (d.length === 14) return d.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    return value || 'Não informado';
  }
  const cepLabel = value => (digits(value).length === 8 ? digits(value).replace(/(\d{5})(\d{3})/, '$1-$2') : value || '');
  function phoneLabel(value) {
    const d = digits(value);
    if (d.length === 11) return d.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    if (d.length === 10) return d.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    return value || 'Não informado';
  }

  // Endereço em três linhas (rua/número, bairro/cidade, CEP).
  function addressLabel(a) {
    if (!a || !a.rua) return 'Não informado';
    return `${a.rua}, ${a.numero || 'S/N'}${a.complemento ? ` · ${a.complemento}` : ''}\n${a.bairro || ''} · ${a.cidade || ''} / ${a.uf || ''}\nCEP ${cepLabel(a.cep)}`;
  }

  // Data de hoje (fuso de São Paulo) e soma de dias em datas AAAA-MM-DD.
  const today = new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Sao_Paulo' }).format(new Date());
  function addDays(date, days) {
    const d = new Date(`${date}T12:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);
    return d.toISOString().slice(0, 10);
  }

  // Chamada à API: GET sem corpo ou POST em JSON com o token CSRF. Erros trazem { message, fields }.
  async function api(route, body) {
    let response, data;
    try {
      response = await fetch(window.SGCB_API + route, body === undefined
        ? { cache: 'no-store', headers: { Accept: 'application/json' } }
        : { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(body) });
      data = await response.json().catch(() => null);
    } catch (_) {
      throw Object.assign(Error('Não foi possível falar com o servidor. Verifique a conexão.'), { fields: {} });
    }
    if (!response.ok) throw Object.assign(Error(data?.error || 'Não foi possível concluir a ação.'), { fields: data?.fields || {} });
    return data;
  }

  // ---------------------------------------------------------------------------
  // 1. Opções (clientes, produtos e condições de pagamento)
  // ---------------------------------------------------------------------------

  async function loadOptions() {
    $('#options-notice').hidden = true;
    try {
      options = await api('contratos/opcoes');
      csrf = options.csrf_token || csrf;
    } catch (error) {
      $('#options-message').textContent = 'Não foi possível carregar clientes e produtos. ' + error.message;
      $('#options-notice').hidden = false;
      return;
    }
    // Clientes ativos: "Nome · CPF/CNPJ".
    field('client').replaceChildren(option('', options.clientes.length ? 'Selecione um cliente' : 'Nenhum cliente ativo cadastrado'));
    options.clientes.forEach(c => field('client').append(option(c.id, `${c.nome} · ${documentLabel(c.cpf_cnpj)}`)));
    // Condições de pagamento ativas.
    field('paymentCondition').replaceChildren(option('', 'Selecione'));
    options.condicoes_pagamento.forEach(c => field('paymentCondition').append(option(c.id, c.nome)));
    if (options.condicoes_pagamento.length) field('paymentCondition').value = options.condicoes_pagamento[0].id;
    applyCondition();
    // Primeiro item já aberto.
    if (!items.length) addItem();
    update();
  }

  // ---------------------------------------------------------------------------
  // 2. Cliente e endereço de entrega
  // ---------------------------------------------------------------------------

  const DELIVERY = { cep: 'deliveryPostalCode', rua: 'deliveryStreet', numero: 'deliveryNumber', bairro: 'deliveryDistrict', cidade: 'deliveryCity', uf: 'deliveryState', complemento: 'deliveryComplement', referencia: 'deliveryReference' };

  // Campos que vêm do endereço do cliente (a referência é sempre livre).
  const ADDRESS_KEYS = ['cep', 'rua', 'numero', 'bairro', 'cidade', 'uf', 'complemento'];

  // Copia um endereço para os campos de entrega (sem mexer na referência).
  function fillDelivery(address) {
    for (const key of ADDRESS_KEYS) field(DELIVERY[key]).value = key === 'cep' ? cepLabel(address?.cep) : address?.[key] || '';
  }
  // Endereço de entrega como a API espera.
  const deliveryData = () => Object.fromEntries(Object.entries(DELIVERY).map(([key, name]) => [key, field(name).value.trim()]));
  // Origem do local de entrega: CLIENTE (cópia do endereço cadastral) ou OUTRO (digitado aqui).
  const deliverySource = () => form.querySelector('[name=deliverySource]:checked')?.value || 'CLIENTE';

  // Aplica a origem escolhida: CLIENTE preenche e trava os campos; OUTRO limpa e libera para digitar.
  function applyDeliverySource(focus = false) {
    const fromClient = deliverySource() === 'CLIENTE';
    if (fromClient) fillDelivery(customer?.endereco);
    else if (ADDRESS_KEYS.some(key => field(DELIVERY[key]).disabled)) fillDelivery(null); // vinha do cliente: começa vazio
    // Campos desativados não entram na conferência da tela (getIssues); o servidor copia o endereço do cliente.
    for (const key of ADDRESS_KEYS) field(DELIVERY[key]).disabled = fromClient;
    if (!fromClient && focus) field(DELIVERY.cep).focus();
    update();
  }

  let customerRequest = 0;
  async function selectCustomer() {
    const id = field('client').value;
    const current = ++customerRequest;
    customer = null;
    $('#client-data').hidden = true;
    $('#client-empty').hidden = false;
    $('#client-empty').textContent = id ? 'Carregando dados do cliente…' : 'Selecione um cliente para conferir seus dados.';
    $('#signatory-fields').hidden = true;
    $('#client-address-line').textContent = id ? 'Carregando…' : 'Selecione um cliente.';
    form.querySelector('[name=deliverySource][value=CLIENTE]').disabled = false;
    applyDeliverySource();
    if (!id) return;
    try {
      const data = await api('clientes/' + encodeURIComponent(id));
      if (current !== customerRequest) return;
      customer = data;
    } catch (error) {
      if (current === customerRequest) $('#client-empty').textContent = 'Não foi possível carregar o cliente. ' + error.message;
      return;
    }

    // Cartão com os dados cadastrais.
    const pj = customer.tipo_pessoa === 'JURIDICA' || digits(customer.cpf_cnpj).length === 14;
    const address = customer.endereco; // endereço cadastral (um por cliente) ou null
    for (const [selector, value] of Object.entries({
      '#client-name': customer.nome,
      '#client-type': pj ? 'Pessoa jurídica' : 'Pessoa física',
      '#client-trade': customer.nome_fantasia || 'Não se aplica',
      '#client-document-label': pj ? 'CNPJ' : 'CPF',
      '#client-document': documentLabel(customer.cpf_cnpj),
      '#client-city': address ? `${address.cidade} / ${address.uf}` : 'Não informado',
      '#client-phone': phoneLabel(customer.telefone),
      '#client-email': customer.email || 'Não informado',
      '#client-nationality': customer.nacionalidade || 'Não se aplica',
      '#client-profession': customer.profissao || 'Não se aplica',
      '#registered-address': addressLabel(address),
    })) $(selector).textContent = value;
    $('#client-data').hidden = false;
    $('#client-empty').hidden = true;

    // Pessoa jurídica: responsável pela assinatura, já preenchido com o representante do cadastro.
    $('#signatory-fields').hidden = !pj;
    field('signerName').value = pj ? customer.representante_legal || '' : '';
    field('signerDocument').value = pj && customer.cpf_representante ? documentLabel(customer.cpf_representante) : '';

    // Condição de pagamento padrão do cliente, se houver.
    if (customer.condicao_pagamento_id && options.condicoes_pagamento.some(c => c.id === customer.condicao_pagamento_id)) {
      field('paymentCondition').value = customer.condicao_pagamento_id;
      applyCondition();
    }

    // Opção "Endereço do cliente": mostra o endereço; sem endereço cadastrado, só resta "Outro endereço".
    const clientOption = form.querySelector('[name=deliverySource][value=CLIENTE]');
    $('#client-address-line').textContent = address
      ? `${address.rua}, ${address.numero || 'S/N'} • ${address.bairro} • ${address.cidade} - ${address.uf} • ${cepLabel(address.cep)}`
      : 'Cliente sem endereço cadastrado.';
    clientOption.disabled = !address;
    form.querySelector(`[name=deliverySource][value=${address ? 'CLIENTE' : 'OUTRO'}]`).checked = true;
    applyDeliverySource();
  }

  // ---------------------------------------------------------------------------
  // 3. Itens: produto, contêineres livres no período e valores
  // ---------------------------------------------------------------------------

  const itemField = (item, name) => item.element.querySelector(`[data-field="${name}"]`);
  const currentProduct = item => options.produtos.find(p => String(p.id) === itemField(item, 'product').value);
  const selectedUnits = item => [...item.element.querySelectorAll('[data-field=units] input:checked')].map(input => Number(input.value));
  // Preço do produto na periodicidade escolhida (null se o produto não tem esse preço).
  const priceFor = product => product?.precos.find(p => p.periodicidade === field('billing').value)?.valor ?? null;

  // Sugere o preço do produto para a periodicidade atual.
  function applyPrice(item) {
    const price = priceFor(currentProduct(item));
    itemField(item, 'price').value = price !== null ? Number(price).toFixed(2) : '';
  }

  // Busca os contêineres do produto e marca quais estão livres nas datas do contrato.
  async function loadUnits(item) {
    const product = currentProduct(item);
    const start = field('start').value;
    const end = field('term').value === 'DETERMINADO' ? field('end').value : '';
    const keep = selectedUnits(item);
    const container = itemField(item, 'units');
    item.units = [];
    container.replaceChildren();
    if (!product || !start) {
      update();
      return;
    }
    const request = (item.request = (item.request || 0) + 1);
    item.loading = true;
    update();
    try {
      const params = new URLSearchParams({ produto_id: product.id, data_inicio: start, data_fim: end });
      const data = await api('contratos/unidades?' + params);
      if (request !== item.request) return;
      item.units = data.items;
    } catch (_) {
      if (request !== item.request) return;
      item.units = [];
    } finally {
      if (request === item.request) item.loading = false;
    }
    // Uma caixa de seleção por contêiner; os ocupados ficam desativados.
    item.units.forEach(unit => {
      const label = document.createElement('label'),
        input = document.createElement('input'),
        text = document.createElement('span');
      label.className = 'unit-option';
      input.type = 'checkbox';
      input.value = unit.id;
      input.id = `unit-${item.id}-${unit.id}`;
      input.checked = unit.livre && keep.includes(unit.id);
      text.textContent = unit.codigo;
      label.append(input, text);
      container.append(label);
    });
    update();
  }

  // Recarrega os contêineres de todos os itens (datas ou prazo mudaram).
  let unitsTimer;
  function reloadAllUnits() {
    clearTimeout(unitsTimer);
    unitsTimer = setTimeout(() => items.forEach(loadUnits), 300);
  }

  // Novo bloco de item a partir do <template>.
  function addItem() {
    const fragment = $('#item-template').content.cloneNode(true);
    const element = fragment.querySelector('.rental-item');
    const item = { id: ++itemSequence, element, units: [], loading: false };
    // Cada campo repetido ganha um id próprio ligado ao rótulo.
    element.querySelectorAll('label.contract-field').forEach(label => {
      const input = label.querySelector('input,select');
      input.id = `item-${item.id}-${input.dataset.field}`;
      label.htmlFor = input.id;
    });
    const select = itemField(item, 'product');
    select.append(option('', options.produtos.length ? 'Selecione um produto' : 'Nenhum produto ativo'));
    options.produtos.forEach(p => select.append(option(p.id, p.nome)));
    $('#rental-items').append(fragment);
    items.push(item);

    select.addEventListener('change', () => {
      applyPrice(item);
      loadUnits(item);
    });
    element.querySelector('.remove-item').addEventListener('click', () => {
      const index = items.indexOf(item);
      items.splice(index, 1);
      element.remove();
      update();
      const next = items[Math.min(index, items.length - 1)];
      (next ? itemField(next, 'product') : $('#add-item')).focus();
    });
    update();
    return item;
  }

  // ---------------------------------------------------------------------------
  // 5. Condição de pagamento: sugere parcelas e 1º vencimento
  // ---------------------------------------------------------------------------

  function applyCondition() {
    const condition = options.condicoes_pagamento.find(c => String(c.id) === field('paymentCondition').value);
    if (!condition) return;
    field('installments').value = condition.numero_parcelas_padrao || 1;
    if (!firstDueEdited && field('start').value) field('firstDue').value = addDays(field('start').value, condition.dias_primeiro_vencimento || 0);
  }

  // ---------------------------------------------------------------------------
  // Dados enviados para a API e validação na tela
  // ---------------------------------------------------------------------------

  function payload() {
    const pj = !$('#signatory-fields').hidden;
    return {
      cliente_id: field('client').value,
      entrega_origem: deliverySource(),
      entrega: deliveryData(),
      responsavel: pj ? { nome: field('signerName').value.trim(), cpf: digits(field('signerDocument').value) } : null,
      condicao_pagamento_id: field('paymentCondition').value,
      data_emissao: field('issueDate').value,
      prazo: field('term').value,
      data_inicio: field('start').value,
      data_fim: field('term').value === 'DETERMINADO' ? field('end').value : '',
      periodicidade: field('billing').value,
      primeiro_vencimento: field('firstDue').value,
      numero_parcelas: field('installments').value,
      dia_vencimento: field('dueDay').value,
      frete_deslocamento: decimal(field('freight')),
      outros_valores: decimal(field('other')),
      caucao: decimal(field('deposit')),
      observacoes: field('notes').value.trim(),
      itens: items.map(item => ({
        produto_id: itemField(item, 'product').value,
        quantidade: selectedUnits(item).length || '',
        unidades: selectedUnits(item),
        valor_unitario: decimal(itemField(item, 'price')),
        outros: decimal(itemField(item, 'other')),
        desconto: decimal(itemField(item, 'discount')),
      })),
    };
  }

  // Pendências da tela (o servidor confere de novo ao salvar).
  function getIssues() {
    const issues = [];
    if (!field('client').value || !customer) issues.push({ text: 'Selecione um cliente.', element: field('client') });
    const required = { deliveryPostalCode: 'CEP da entrega', deliveryStreet: 'Rua da entrega', deliveryNumber: 'Número da entrega', deliveryDistrict: 'Bairro da entrega', deliveryCity: 'Cidade da entrega', deliveryState: 'Estado da entrega', start: 'Data inicial', end: 'Data final', paymentCondition: 'Condição de pagamento', firstDue: 'Primeiro vencimento', installments: 'Quantidade de parcelas', dueDay: 'Dia de cobrança', freight: 'Frete', other: 'Outras despesas gerais', deposit: 'Caução' };
    for (const [name, label] of Object.entries(required)) {
      const input = field(name);
      if (input.disabled) continue;
      if ((input.required && !input.value.trim()) || !input.validity.valid) issues.push({ text: `Confira: ${label}.`, element: input });
    }
    if (field('deliveryPostalCode').value && digits(field('deliveryPostalCode').value).length !== 8) issues.push({ text: 'Informe um CEP de entrega com 8 dígitos.', element: field('deliveryPostalCode') });
    if (field('term').value === 'DETERMINADO' && field('start').value && field('end').value && field('end').value < field('start').value) issues.push({ text: 'A data final deve ser igual ou posterior à inicial.', element: field('end') });
    if (field('firstDue').value && field('start').value && field('firstDue').value < field('start').value) issues.push({ text: 'O primeiro vencimento não pode ser antes do início.', element: field('firstDue') });
    if (!$('#signatory-fields').hidden) {
      const name = field('signerName').value.trim(), cpf = digits(field('signerDocument').value);
      if ((name || cpf) && (!name || cpf.length !== 11)) issues.push({ text: 'Complete o nome e os 11 dígitos do CPF do responsável, ou deixe ambos vazios.', element: name ? field('signerDocument') : field('signerName') });
    }
    if (!items.length) issues.push({ text: 'Adicione pelo menos um produto.', element: $('#add-item') });
    items.forEach((item, index) => {
      if (!currentProduct(item)) issues.push({ text: `Selecione o produto do item ${index + 1}.`, element: itemField(item, 'product') });
      else if (!selectedUnits(item).length) issues.push({ text: `Selecione os contêineres do item ${index + 1}.`, element: item.element.querySelector('[data-field=units] input:not(:disabled)') || itemField(item, 'product') });
      for (const name of ['price', 'other', 'discount']) {
        const input = itemField(item, name);
        if ((input.required && !input.value) || !input.validity.valid) issues.push({ text: `Confira os valores do item ${index + 1}.`, element: input });
      }
    });
    return issues;
  }

  // ---------------------------------------------------------------------------
  // 4. Cálculo no servidor (mesma regra que será gravada), com espera de 400 ms
  // ---------------------------------------------------------------------------

  let calcTimer, calcRequest = 0;
  function requestCalc() {
    clearTimeout(calcTimer);
    calcTimer = setTimeout(async () => {
      const data = payload();
      // Só calcula com o mínimo: cliente, data inicial e itens com produto e contêineres.
      const ready = data.cliente_id && data.data_inicio && data.itens.length && data.itens.every(i => i.produto_id && i.unidades.length);
      const current = ++calcRequest;
      if (!ready) {
        calc = null;
        calcMessage = '';
        render();
        return;
      }
      try {
        const result = await api('contratos/calcular', data);
        if (current !== calcRequest) return;
        calc = result;
        calcMessage = '';
      } catch (error) {
        if (current !== calcRequest) return;
        calc = null;
        calcMessage = [error.message, ...Object.values(error.fields || {})].join(' ');
      }
      render();
    }, 400);
  }

  // ---------------------------------------------------------------------------
  // Resumo e pendências
  // ---------------------------------------------------------------------------

  // Lista "rótulo: valor" do resumo.
  function summaryRows(selector, rows) {
    const list = $(selector);
    list.replaceChildren();
    rows.forEach(([label, value, emphasis]) => {
      const group = document.createElement('div'),
        term = document.createElement('dt'),
        description = document.createElement('dd');
      if (emphasis) group.className = 'emphasis';
      term.textContent = label;
      description.textContent = value || 'Não informado';
      group.append(term, description);
      list.append(group);
    });
  }

  // Atualiza tudo que depende dos campos e pede um novo cálculo.
  function update() {
    render();
    requestCalc();
  }

  function render() {
    const billing = BILLING[field('billing').value];
    const fixed = field('term').value === 'DETERMINADO';

    // Itens: posição, quantidade, contêineres ocupados/em outro item e subtotal do servidor.
    items.forEach((item, index) => {
      const product = currentProduct(item);
      const chosen = selectedUnits(item);
      item.element.querySelector('.item-position').textContent = index + 1;
      item.element.querySelector('.remove-item').setAttribute('aria-label', `Remover item ${index + 1}`);
      itemField(item, 'quantity').textContent = chosen.length;
      itemField(item, 'billing').textContent = billing;
      itemField(item, 'assetValue').textContent = product ? reais(product.valor_total_bem) : '—';
      itemField(item, 'subtotal').textContent = calc?.itens?.[index] ? reais(calc.itens[index].subtotal) : '—';
      item.element.querySelectorAll('[data-field=units] input').forEach(input => {
        const unit = item.units.find(u => u.id === Number(input.value));
        const elsewhere = items.some(other => other !== item && selectedUnits(other).includes(Number(input.value)));
        input.disabled = !unit?.livre || (elsewhere && !input.checked);
        const suffix = !unit?.livre ? ' — ocupado no período' : elsewhere ? ' — em outro item' : '';
        const code = unit?.codigo || input.value;
        input.setAttribute('aria-label', code + suffix);
        input.parentElement.title = code + suffix;
      });
      const free = item.units.filter(u => u.livre).length;
      const availability = itemField(item, 'availability');
      availability.textContent = !product ? 'Selecione o produto para ver os contêineres.'
        : !field('start').value ? 'Informe a data inicial para ver os contêineres livres.'
        : item.loading ? 'Carregando contêineres…'
        : !item.units.length ? 'Este produto não tem contêineres ativos.'
        : chosen.length ? `${chosen.length} unidade(s) selecionada(s) de ${free} disponíveis no período.`
        : `Selecione ao menos um contêiner (${free} disponíveis no período).`;
      availability.classList.toggle('has-selection', chosen.length > 0);
    });

    // Identificação.
    const condition = options.condicoes_pagamento.find(c => String(c.id) === field('paymentCondition').value);
    const status = field('status').value === 'ATIVO' ? 'Ativo (gerado ao salvar)' : 'Rascunho';
    summaryRows('#summary-identification', [
      ['Nº da locação', 'Automático'], ['Nº do contrato', 'Automático'], ['Emissão', dateLabel(field('issueDate').value)],
      ['Prazo', fixed ? 'Determinado' : 'Indeterminado'],
      ['Período', fixed ? `${dateLabel(field('start').value)} a ${dateLabel(field('end').value)}` : `A partir de ${dateLabel(field('start').value)}`],
      ['Cobrança', billing], ['Status', status],
    ]);

    // Cliente e entrega.
    const delivery = deliveryData();
    const clientRows = [
      ['Cliente', customer?.nome, true], ['CPF / CNPJ', customer ? documentLabel(customer.cpf_cnpj) : ''],
      ['Telefone', customer ? phoneLabel(customer.telefone) : ''], ['E-mail', customer?.email],
      ['Entrega', delivery.rua ? addressLabel(delivery) : ''], ['Referência', delivery.referencia || 'Não informada'],
    ];
    if (!$('#signatory-fields').hidden && field('signerName').value.trim()) clientRows.push(['Responsável', `${field('signerName').value.trim()} · CPF ${documentLabel(field('signerDocument').value)}`]);
    summaryRows('#summary-client', clientRows);

    // Contêineres.
    $('#summary-products').replaceChildren();
    items.forEach((item, index) => {
      const product = currentProduct(item);
      if (!product) return;
      const codes = item.units.filter(u => selectedUnits(item).includes(u.id)).map(u => u.codigo);
      const li = document.createElement('li');
      const lines = [
        ['strong', `${codes.length} × ${product.nome}`],
        ['span', codes.join(' • ') || 'Contêineres pendentes'],
        ['span', `Bem: ${reais(product.valor_total_bem)} por unidade`],
        ['span', `Locação: ${reais(itemField(item, 'price').value)} por unidade · ${billing.toLowerCase()}`],
        ['span', `Subtotal: ${calc?.itens?.[index] ? reais(calc.itens[index].subtotal) : '—'}`],
      ];
      lines.forEach(([tag, text]) => {
        const el = document.createElement(tag);
        el.textContent = text;
        li.append(el);
      });
      $('#summary-products').append(li);
    });
    if (!$('#summary-products').children.length) {
      const empty = document.createElement('li');
      empty.textContent = 'Nenhum produto adicionado.';
      $('#summary-products').append(empty);
    }

    // Valores e pagamento (total do servidor dividido nas parcelas; centavos que sobram vão para a última).
    const installments = Math.max(1, Number(field('installments').value) || 1);
    let installmentText = 'Aguardando o cálculo';
    if (calc) {
      const totalC = Math.round(calc.valor_total * 100);
      const base = Math.floor(totalC / installments), rest = totalC - base * installments;
      installmentText = rest
        ? `${installments} parcela(s): ${installments > 1 ? `${installments - 1} de ${reais(base / 100)} e ` : ''}última de ${reais((base + rest) / 100)}`
        : `${installments} × ${reais(base / 100)}`;
    }
    summaryRows('#summary-finance', [
      ['Itens', calc ? reais(calc.valor_itens) : '—', true],
      ['Períodos', calc ? String(calc.numero_periodos).replace('.', ',') : '—'],
      ['Frete', reais(field('freight').value)], ['Outros', reais(field('other').value)],
      ['Caução à parte', reais(field('deposit').value)], ['Pagamento', condition?.nome],
      ['Parcelas', installmentText], ['1º vencimento', dateLabel(field('firstDue').value)],
      ['Dia de cobrança', field('dueDay').value ? `Dia ${field('dueDay').value}` : 'Pela condição'],
    ]);
    $('#summary-total').textContent = calc ? reais(calc.valor_total) : 'R$ 0,00';

    // Observações.
    $('#notes-count').textContent = field('notes').value.length;
    $('#summary-notes').textContent = field('notes').value.trim();
    $('#summary-notes-section').hidden = !field('notes').value.trim();

    // Pendências (as da tela + o motivo de o servidor não ter calculado).
    const issues = getIssues();
    if (!issues.length && calcMessage) issues.push({ text: calcMessage, element: field('client') });
    $('#completeness').classList.toggle('is-pending', issues.length > 0);
    $('#completeness-title').textContent = issues.length ? `${issues.length} pendência(s) para conferir` : '✓ Dados obrigatórios completos';
    $('#completeness-description').textContent = issues.length ? 'Preencha ou corrija os campos indicados.' : 'Cliente, entrega, período, contêineres e condições comerciais conferidos.';
    $('#pending-fields').replaceChildren();
    $('#pending-fields').hidden = !issues.length;
    issues.forEach(issue => {
      const li = document.createElement('li');
      li.textContent = issue.text;
      $('#pending-fields').append(li);
    });
    $('#summary-announcement').textContent = `Resumo atualizado. ${issues.length ? issues.length + ' pendências.' : 'Dados completos.'}`;
  }

  // ---------------------------------------------------------------------------
  // Prévia, gravação e erros vindos do servidor
  // ---------------------------------------------------------------------------

  // Prévia no mesmo formato do documento (CASA BARRETTO + linhas do contrato).
  function fillPreview() {
    const sheet = $('#preview-sheet');
    sheet.replaceChildren();
    const delivery = deliveryData();
    const allCodes = items.flatMap(item => item.units.filter(u => selectedUnits(item).includes(u.id)).map(u => u.codigo));
    const lines = [
      ['p', 'contract-brand', 'CASA BARRETTO'],
      ['h3', '', 'CONTRATO DE LOCAÇÃO DE EQUIPAMENTOS'],
      ['p', 'contract-line', 'Contrato: número gerado ao salvar'],
      ['p', 'contract-line', `Cliente: ${customer?.nome || ''}`],
      ['p', 'contract-line', `CPF / CNPJ: ${documentLabel(customer?.cpf_cnpj)}`],
      ['p', 'contract-line', `Equipamento: ${items.map(i => currentProduct(i)?.nome).filter(Boolean).join(', ')}`],
      ['p', 'contract-line', `Unidades: ${allCodes.join(', ')} • Quantidade: ${allCodes.length}`],
      ['p', 'contract-line', `Período: ${field('term').value === 'DETERMINADO' ? `${dateLabel(field('start').value)} a ${dateLabel(field('end').value)}` : `a partir de ${dateLabel(field('start').value)} (indeterminado)`}`],
      ['p', 'contract-line', `Cobrança: ${BILLING[field('billing').value]} • Itens: ${calc ? reais(calc.valor_itens) : '—'}`],
      ['p', 'contract-line', `Frete: ${reais(field('freight').value)} • Total: ${calc ? reais(calc.valor_total) : '—'}`],
      ['p', 'contract-line', `Caução: ${reais(field('deposit').value)}`],
      ['p', 'contract-line', `Entrega: ${delivery.rua}, ${delivery.numero} • ${delivery.bairro} • ${delivery.cidade} - ${delivery.uf}`],
      ['p', 'contract-note', field('status').value === 'ATIVO' ? 'Ao confirmar, o contrato é gerado: os contêineres são reservados, o número é criado e as parcelas vão para o Financeiro.' : 'Ao confirmar, o contrato fica salvo como rascunho (sem reservar contêineres).'],
    ];
    lines.forEach(([tag, className, text]) => {
      const el = document.createElement(tag);
      if (className) el.className = className;
      el.textContent = text;
      sheet.append(el);
    });
  }

  // Campo da tela correspondente a cada chave de erro do servidor.
  const SERVER_FIELDS = {
    cliente_id: 'client', entrega_origem: 'client', condicao_pagamento_id: 'paymentCondition', data_inicio: 'start', data_fim: 'end',
    periodicidade: 'billing', primeiro_vencimento: 'firstDue', numero_parcelas: 'installments', dia_vencimento: 'dueDay',
    frete_deslocamento: 'freight', outros_valores: 'other', caucao: 'deposit', observacoes: 'notes',
    'entrega.cep': 'deliveryPostalCode', 'entrega.rua': 'deliveryStreet', 'entrega.numero': 'deliveryNumber', 'entrega.bairro': 'deliveryDistrict',
    'entrega.cidade': 'deliveryCity', 'entrega.uf': 'deliveryState', 'entrega.complemento': 'deliveryComplement', 'entrega.referencia': 'deliveryReference',
    'responsavel.nome': 'signerName', 'responsavel.cpf': 'signerDocument',
  };
  const ITEM_FIELDS = { produto_id: 'product', valor_unitario: 'price', desconto: 'discount', outros: 'other' };

  // Marca os campos recusados pelo servidor e mostra as mensagens abaixo do resumo.
  function showServerErrors(error) {
    let first = null;
    for (const key of Object.keys(error.fields || {})) {
      const match = /^itens\.(\d+)\.(\w+)$/.exec(key);
      let element = null;
      if (match && items[match[1]]) {
        const item = items[match[1]];
        element = ITEM_FIELDS[match[2]] ? itemField(item, ITEM_FIELDS[match[2]]) : item.element.querySelector('[data-field=units] input') || itemField(item, 'product');
      } else if (SERVER_FIELDS[key]) element = field(SERVER_FIELDS[key]);
      if (element) {
        element.setAttribute('aria-invalid', 'true');
        first ??= element;
      }
    }
    $('#form-error').textContent = [error.message, ...Object.values(error.fields || {})].join(' ');
    $('#form-error').hidden = false;
    if (first) {
      first.focus();
      first.scrollIntoView({ block: 'center' });
    }
  }

  // "Conferir e salvar": confere as pendências e abre a prévia.
  form.addEventListener('submit', event => {
    event.preventDefault();
    render();
    const issues = getIssues();
    if (issues.length || !calc) {
      $('#form-error').textContent = issues.length ? 'Confira os campos indicados antes de continuar.' : calcMessage || 'Aguarde o cálculo do valor e tente novamente.';
      $('#form-error').hidden = false;
      if (issues.length) {
        issues[0].element.setAttribute('aria-invalid', 'true');
        issues[0].element.focus();
        issues[0].element.scrollIntoView({ block: 'center' });
      }
      return;
    }
    $('#form-error').hidden = true;
    $('#save-error').hidden = true;
    fillPreview();
    $('#preview-dialog').showModal();
  });

  // "Confirmar e salvar": grava (e gera, se o status inicial for Ativo).
  $('#confirm-save').addEventListener('click', async () => {
    const button = $('#confirm-save');
    button.disabled = true;
    button.textContent = 'Salvando…';
    $('#save-error').hidden = true;
    const gerar = field('status').value === 'ATIVO';
    try {
      const contrato = await api('contratos', { ...payload(), gerar });
      dirty = false; // salvo: sair da página não precisa mais perguntar
      $('#preview-dialog').close();
      $('#success-title').textContent = gerar ? 'Contrato gerado com sucesso' : 'Rascunho salvo com sucesso';
      $('#success-text').textContent = gerar
        ? `Contrato ${contrato.numero_contrato} pronto para conferência.`
        : 'O contrato foi salvo como rascunho e pode ser conferido na listagem.';
      // "Visualizar documento" abre a listagem já com a prévia deste contrato.
      $('#view-document').href = `${listUrl}?ver=${encodeURIComponent(contrato.id)}`;
      $('#success-dialog').showModal();
    } catch (error) {
      $('#preview-dialog').close();
      showServerErrors(error);
    } finally {
      button.disabled = false;
      button.textContent = 'Confirmar e salvar';
    }
  });

  // ---------------------------------------------------------------------------
  // Eventos dos campos
  // ---------------------------------------------------------------------------

  form.addEventListener('input', event => {
    dirty = true;
    event.target.removeAttribute('aria-invalid');
    $('#form-error').hidden = true;
    update();
  });
  form.addEventListener('change', () => {
    dirty = true;
    update();
  });
  field('client').addEventListener('change', selectCustomer);
  field('paymentCondition').addEventListener('change', applyCondition);
  field('firstDue').addEventListener('input', () => { firstDueEdited = true; });
  // Datas mudam os contêineres livres e o 1º vencimento sugerido.
  field('start').addEventListener('change', () => { applyCondition(); reloadAllUnits(); });
  field('end').addEventListener('change', reloadAllUnits);
  // Prazo indeterminado: sem data final.
  field('term').addEventListener('change', () => {
    const fixed = field('term').value === 'DETERMINADO';
    field('end').disabled = !fixed;
    field('end').required = fixed;
    if (!fixed) field('end').value = '';
    else if (!field('end').value && field('start').value) field('end').value = addDays(field('start').value, 30);
    $('#end-label').textContent = fixed ? 'Data final *' : 'Data final · não se aplica';
    reloadAllUnits();
    update();
  });
  // Periodicidade troca o preço sugerido de cada item.
  field('billing').addEventListener('change', () => { items.forEach(applyPrice); update(); });
  // Trocar entre "Endereço do cliente" e "Outro endereço".
  form.querySelectorAll('[name=deliverySource]').forEach(radio => radio.addEventListener('change', () => applyDeliverySource(true)));
  $('#add-item').addEventListener('click', () => itemField(addItem(), 'product').focus());
  $('#retry-options').addEventListener('click', loadOptions);

  // Diálogos.
  $('#close-preview').addEventListener('click', () => $('#preview-dialog').close());
  $('#new-contract').addEventListener('click', () => location.reload());
  // Clique fora da caixa fecha a prévia.
  $('#preview-dialog').addEventListener('click', event => {
    if (event.target === $('#preview-dialog')) $('#preview-dialog').close();
  });

  // Links da página (menu, Voltar, Cancelar, Sair) com dados não salvos: diálogo padrão "Descartar alterações?".
  document.querySelectorAll('a[href]').forEach(link => link.addEventListener('click', event => {
    if (!dirty || leaving || event.ctrlKey || event.metaKey || event.shiftKey || link.getAttribute('href').startsWith('#')) return;
    event.preventDefault();
    SgcbDescartar.abrir(link.href, () => (leaving = true));
  }));
  // Fechar a aba ou recarregar com dados não salvos: confirmação nativa do navegador.
  window.addEventListener('beforeunload', event => {
    if (dirty && !leaving) {
      event.preventDefault();
      event.returnValue = '';
    }
  });

  // ---------------------------------------------------------------------------
  // Valores iniciais: estados, hoje como emissão/início e fim em 30 dias
  // ---------------------------------------------------------------------------

  field('deliveryState').append(option('', 'UF'), ...STATES.map(uf => option(uf, uf)));
  field('issueDate').value = today;
  field('start').value = today;
  field('end').value = addDays(today, 30);
  field('firstDue').value = today;
  // Começa em "Endereço do cliente": campos travados até escolher o cliente ou "Outro endereço".
  for (const key of ADDRESS_KEYS) field(DELIVERY[key]).disabled = true;
  loadOptions();
})();
