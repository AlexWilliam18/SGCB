'use strict';
/*
 * Cadastro de produto: carrega categorias/fornecedores, valida, formata valores em R$ e
 * avisa sobre alterações não salvas. As opções vêm de /api/produtos/opcoes e o salvamento
 * faz POST em /api/produtos (back-end ainda não implementado).
 */
(() => {
  // URL da listagem de produtos, vinda do data-lista-url do <main>.
  const listaUrl = document.getElementById('product-main').dataset.listaUrl;
  const $ = id => document.getElementById(id),
    form = $('product-form'),
    fields = () => form.querySelectorAll('input,select,textarea');
  let csrf = '',
    ready = false, // opções carregadas e usuário responsável configurado
    busy = false, // salvamento em andamento
    dirty = false, // há dados digitados ainda não salvos
    leaving = false, // saída já confirmada (não pergunta de novo)
    nextUrl = listaUrl;

  // Aviso no topo do formulário; "retry" mostra o botão "Tentar novamente".
  function notice(text, retry = false) {
    $('form-message').textContent = text;
    $('form-notice').hidden = false;
    $('retry-options').hidden = !retry;
  }
  function updateSave() {
    $('save-product').disabled = !ready || busy;
  }

  // Preenche um select com os itens da API, mantendo a opção já escolhida.
  // Na categoria, pré-seleciona "Contêiner" se o usuário ainda não mexeu no formulário.
  function fill(id, items, placeholder) {
    const select = $(id),
      previous = select.value;
    select.replaceChildren(new Option(placeholder, ''));
    items.forEach(item => select.append(new Option(item.nome, String(item.id))));
    if (previous && [...select.options].some(option => option.value === previous)) select.value = previous;
    else if (id === 'categoria_id' && !dirty) {
      const container = items.find(item => item.nome.toLocaleLowerCase('pt-BR') === 'contêiner');
      if (container) select.value = String(container.id);
    }
  }

  // Busca categorias, fornecedores e o token CSRF. Só libera o "Salvar" se houver
  // usuário responsável, pelo menos uma categoria e um fornecedor ativos.
  async function loadOptions() {
    const controller = new AbortController(),
      timeout = setTimeout(() => controller.abort(), 15000);
    ready = false;
    updateSave();
    $('retry-options').disabled = true;
    try {
      const response = await fetch('/api/produtos/opcoes', { cache: 'no-store', signal: controller.signal });
      const data = await response.json();
      if (!response.ok) throw Error(data.error || 'Não foi possível carregar as opções.');
      if (!Array.isArray(data.categorias) || !Array.isArray(data.fornecedores) || !data.csrf_token)
        throw Error('O servidor retornou opções inválidas.');
      csrf = data.csrf_token;
      fill('categoria_id', data.categorias, 'Selecione a categoria');
      fill('fornecedor_id', data.fornecedores, 'Selecione o fornecedor');
      ready = Boolean(data.responsavel_configurado && data.categorias.length && data.fornecedores.length);
      if (!data.responsavel_configurado)
        notice('Configure um usuário responsável ativo no servidor para salvar produtos.', true);
      else if (!data.categorias.length) notice('Cadastre uma categoria ativa na tela de Produtos para continuar.', true);
      else if (!data.fornecedores.length) notice('Cadastre um fornecedor ativo em Clientes para continuar.', true);
      else $('form-notice').hidden = true;
    } catch (error) {
      notice(
        error.name === 'AbortError'
          ? 'A consulta demorou mais que o esperado. Tente novamente.'
          : error.message || 'Não foi possível carregar as opções.',
        true
      );
    } finally {
      clearTimeout(timeout);
      updateSave();
      $('retry-options').disabled = false;
    }
  }

  // Erros por campo: aria-invalid + mensagem abaixo do campo. O aria-describedby original
  // (dica ou contador) fica guardado em data-describedby para ser restaurado depois.
  function clearErrors() {
    form.querySelectorAll('[aria-invalid]').forEach(el => {
      el.removeAttribute('aria-invalid');
      if (el.dataset.describedby) el.setAttribute('aria-describedby', el.dataset.describedby);
      else el.removeAttribute('aria-describedby');
    });
    form.querySelectorAll('.field-error').forEach(el => {
      el.hidden = true;
      el.textContent = '';
    });
  }
  function fieldError(name, text) {
    const input = $(name),
      output = $('error-' + name);
    if (!input || !output) return;
    input.dataset.describedby ??= input.getAttribute('aria-describedby') || '';
    input.setAttribute('aria-invalid', 'true');
    input.setAttribute('aria-describedby', (output.id + ' ' + input.dataset.describedby).trim());
    output.textContent = text;
    output.hidden = false;
  }

  // Converte "R$ 1.234,56", "1234,5" ou "1234.56" para "1234.56" (formato enviado à API).
  // Retorna null se o valor não for um número monetário válido.
  function decimal(value) {
    let text = value.trim().replace(/^R\$\s*/, '').replace(/\s/g, '');
    if (text.includes(',')) {
      if (!/^(?:\d+|\d{1,3}(?:\.\d{3})+),\d{1,2}$/.test(text)) return null;
      text = text.replace(/\./g, '').replace(',', '.');
    }
    if (!/^\d{1,12}(?:\.\d{1,2})?$/.test(text)) return null;
    return Number(text).toFixed(2);
  }

  // Ao sair de um campo de valor, mostra no formato de moeda (ex.: R$ 1.000,00).
  form.querySelectorAll('[data-money]').forEach(input =>
    input.addEventListener('blur', () => {
      const value = decimal(input.value);
      if (value !== null)
        input.value = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value));
    })
  );

  // Qualquer alteração marca o formulário como "sujo"; o contador da descrição é atualizado.
  form.addEventListener('input', () => {
    dirty = true;
    $('description-count').textContent = $('descricao').value.length + '/500';
  });
  form.addEventListener('change', () => {
    dirty = true;
  });

  // Salvar: valida no navegador, converte os valores e envia para a API.
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy || !ready) return;
    clearErrors();
    const data = Object.fromEntries(new FormData(form));
    fields().forEach(input => {
      if (!input.checkValidity() || (input.required && !input.value.trim()))
        fieldError(input.name, input.validationMessage || 'Preencha este campo.');
    });
    form.querySelectorAll('[data-money]').forEach(input => {
      const value = decimal(input.value);
      if (value === null) fieldError(input.name, 'Informe um valor monetário válido.');
      else data[input.name] = value;
    });
    // Regras entre campos (0 = sem limite): inicial ≤ máximo e tudo cabe no máximo de estoque.
    const low = Number(data.limite_inicial),
      high = Number(data.limite_maximo),
      cap = Number(data.maximo_estoque);
    if (high && low > high) fieldError('limite_maximo', 'O limite máximo deve ser maior ou igual ao limite inicial.');
    if (cap && (low > cap || high > cap || Number(data.quantidade) > cap))
      fieldError('maximo_estoque', 'O máximo de estoque deve comportar os limites e a quantidade inicial.');
    const firstInvalid = form.querySelector('[aria-invalid=true]');
    if (firstInvalid) {
      firstInvalid.focus();
      return;
    }

    busy = true;
    updateSave();
    $('save-product').textContent = 'Salvando…';
    form.setAttribute('aria-busy', 'true');
    fields().forEach(el => (el.disabled = true));
    const controller = new AbortController(),
      timeout = setTimeout(() => controller.abort(), 30000);
    try {
      const response = await fetch('/api/produtos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(data),
        signal: controller.signal,
      });
      const result = await response.json();
      if (!response.ok) {
        // A API pode devolver erros por campo em "fields"; 403 = token CSRF inválido/expirado.
        Object.entries(result.fields || {}).forEach(([key, value]) => fieldError(key, value));
        if (response.status === 403) ready = false;
        throw Error(result.error || 'Não foi possível salvar o produto.');
      }
      // Sucesso: grava o aviso que a listagem (produtos.js) mostra e volta para ela.
      dirty = false;
      leaving = true;
      try {
        sessionStorage.setItem('produto-criado', JSON.stringify({ nome: result.nome, unidades: result.unidades_criadas }));
      } catch (_) {
        /* O cadastro já foi confirmado pelo servidor; só o aviso na listagem não aparece. */
      }
      location.assign(listaUrl);
    } catch (error) {
      notice(
        error.name === 'AbortError'
          ? 'Não foi possível confirmar o salvamento. Consulte a lista de produtos antes de reenviar.'
          : error.message || 'Não foi possível confirmar o salvamento. Consulte a lista antes de reenviar.',
        !ready
      );
    } finally {
      clearTimeout(timeout);
      busy = false;
      form.removeAttribute('aria-busy');
      fields().forEach(el => (el.disabled = false));
      $('save-product').textContent = 'Salvar produto';
      updateSave();
      const invalid = form.querySelector('[aria-invalid=true]');
      if (invalid) invalid.focus();
      else if (!leaving) $('form-notice').scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
  });

  // Links da página (menu, Voltar, Cancelar, Sair): com dados não salvos, pergunta antes de sair.
  document.querySelectorAll('a[href]').forEach(link =>
    link.addEventListener('click', event => {
      if (busy) {
        event.preventDefault();
        return;
      }
      if (!dirty || leaving || event.ctrlKey || event.metaKey || event.shiftKey || link.getAttribute('href').startsWith('#'))
        return;
      event.preventDefault();
      nextUrl = link.href;
      $('leave-dialog').showModal();
      $('stay-form').focus();
    })
  );
  $('stay-form').addEventListener('click', () => $('leave-dialog').close());
  $('discard-form').addEventListener('click', () => {
    leaving = true;
    location.assign(nextUrl);
  });
  // Fechar a aba ou recarregar com dados não salvos: confirmação nativa do navegador.
  window.addEventListener('beforeunload', event => {
    if ((dirty || busy) && !leaving) {
      event.preventDefault();
      event.returnValue = '';
    }
  });

  $('retry-options').addEventListener('click', loadOptions);
  loadOptions();
})();
