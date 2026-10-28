'use strict';
/*
 * Cadastro de manutenção: carrega contêineres, tipos, prioridades e responsáveis, valida,
 * salva e avisa sobre alterações não salvas. As opções vêm de /api/manutencoes/opcoes e o
 * salvamento faz POST em /api/manutencoes (back-end ainda não implementado).
 * Depois de salvar, o formulário é limpo e fica na tela para um novo cadastro.
 */
(() => {
  // URL da listagem de manutenções, vinda do data-lista-url do <main>.
  const listaUrl = document.getElementById('maintenance-main').dataset.listaUrl;
  const $ = id => document.getElementById(id),
    form = $('maintenance-form');
  const fieldNames = ['unidade_id', 'tipo', 'prioridade', 'data_prevista', 'responsavel_usuario_id', 'descricao'];
  const unitStates = { DISPONIVEL: 'Disponível', MANUTENCAO: 'Em manutenção', INATIVA: 'Inativa' };
  let ready = false, // opções carregadas e usuário responsável configurado
    busy = false, // salvamento em andamento
    dirty = false, // há dados digitados ainda não salvos
    leaving = false, // saída já confirmada (não pergunta de novo)
    csrf = '',
    nextUrl = listaUrl,
    optionsRequest = 0,
    optionsController;

  // Lê o corpo da resposta como JSON. Se o servidor devolver HTML (ex.: página 404 enquanto a
  // API não existe), retorna null em vez de deixar o erro "Unexpected token '<'" chegar à tela.
  function readJson(response) {
    return response.json().catch(() => null);
  }

  // Aviso no topo (texto vazio esconde); "retry" mostra o botão "Tentar novamente".
  function notice(text, retry = false) {
    $('notice-text').textContent = text;
    $('maintenance-notice').hidden = !text;
    $('retry-options').hidden = !retry;
  }
  function saveState() {
    $('save-maintenance').disabled = !ready || busy;
  }

  // Erros por campo: aria-invalid + mensagem abaixo do campo. O aria-describedby original
  // (dica do responsável) fica guardado em data-describedby para ser restaurado depois.
  function clearError(input) {
    const error = $('error-' + input.id);
    if (error) {
      error.hidden = true;
      error.textContent = '';
    }
    input.removeAttribute('aria-invalid');
    if (input.dataset.describedby) input.setAttribute('aria-describedby', input.dataset.describedby);
    else if (input.dataset.describedby === '') input.removeAttribute('aria-describedby');
  }
  function clearErrors() {
    fieldNames.forEach(name => clearError($(name)));
  }
  function showErrors(errors) {
    clearErrors();
    let first;
    Object.entries(errors).forEach(([key, text]) => {
      const input = $(key),
        error = $('error-' + key);
      if (!input || !error) return;
      input.dataset.describedby ??= input.getAttribute('aria-describedby') || '';
      error.textContent = text;
      error.hidden = false;
      input.setAttribute('aria-invalid', 'true');
      input.setAttribute('aria-describedby', (error.id + ' ' + input.dataset.describedby).trim());
      first ||= input;
    });
    first?.focus();
  }

  // Preenche um select com os itens da API, mantendo a opção já escolhida.
  // label: função que monta o texto; value: propriedade usada como valor da opção.
  function fillSelect(id, items, placeholder, label, value = 'id') {
    const select = $(id),
      previous = select.value;
    select.replaceChildren(new Option(placeholder, ''));
    items.forEach(item => select.append(new Option(label(item), String(item[value]))));
    if ([...select.options].some(option => option.value === previous)) select.value = previous;
  }

  // Busca as opções e o token CSRF. optionsRequest descarta respostas atrasadas de
  // tentativas anteriores. Só libera o "Salvar" se houver responsável e contêineres.
  async function loadOptions() {
    const current = ++optionsRequest;
    optionsController?.abort();
    const controller = (optionsController = new AbortController()),
      timeout = setTimeout(() => controller.abort(), 15000);
    ready = false;
    saveState();
    $('retry-options').disabled = true;
    try {
      const response = await fetch('/api/manutencoes/opcoes', { cache: 'no-store', signal: controller.signal });
      const data = await readJson(response);
      if (!response.ok || !data) throw Error();
      if (!['unidades', 'responsaveis', 'tipos', 'prioridades'].every(key => Array.isArray(data[key])) || !data.csrf_token)
        throw Error();
      if (current !== optionsRequest) return;
      // Contêiner: "CM-001 — Contêiner Escritório 20 pés (Disponível)".
      fillSelect(
        'unidade_id',
        data.unidades,
        'Selecione o Contêiner',
        item => `${item.codigo} — ${item.produto} (${unitStates[item.estado_operacional] || item.estado_operacional})`
      );
      fillSelect('responsavel_usuario_id', data.responsaveis, 'Selecione o responsável', item => item.nome);
      fillSelect('tipo', data.tipos, 'Selecione o Tipo', item => item.nome, 'valor');
      fillSelect('prioridade', data.prioridades, 'Selecione', item => item.nome, 'valor');
      csrf = data.csrf_token;
      ready = data.responsavel_configurado === true && data.unidades.length > 0;
      if (!data.responsavel_configurado)
        notice('Solicite ao administrador a configuração de um usuário responsável para habilitar o salvamento.', true);
      else if (!data.unidades.length)
        notice('Cadastre um produto ativo com unidades físicas para registrar uma manutenção.', true);
      else notice('');
    } catch (error) {
      if (current === optionsRequest)
        notice(
          error.name === 'AbortError'
            ? 'A consulta demorou mais que o esperado. Tente novamente.'
            : 'Não foi possível carregar as opções de manutenção. Verifique a conexão com o banco e tente novamente.',
          true
        );
    } finally {
      clearTimeout(timeout);
      if (current === optionsRequest) {
        $('retry-options').disabled = false;
        saveState();
      }
    }
  }
  $('retry-options').addEventListener('click', loadOptions);

  // Ao mexer em um campo: marca o formulário como alterado, limpa o erro dele e esconde o "sucesso".
  form.addEventListener('input', event => {
    dirty = true;
    clearError(event.target);
    $('maintenance-success').hidden = true;
  });
  form.addEventListener('change', () => (dirty = true));

  // Botão com o ícone de calendário: abre o seletor de data nativo (showPicker), quando existir.
  $('choose-date').addEventListener('click', () => {
    const input = $('data_prevista');
    try {
      if (typeof input.showPicker === 'function') input.showPicker();
      else input.focus();
    } catch (_) {
      input.focus();
    }
  });

  // Validação no navegador; o back-end valida de novo.
  function validate() {
    const errors = {};
    ['unidade_id', 'tipo', 'prioridade'].forEach(key => {
      if (!$(key).value) errors[key] = 'Selecione uma opção.';
    });
    if (!$('data_prevista').value || !$('data_prevista').validity.valid) errors.data_prevista = 'Informe uma data válida.';
    if ($('descricao').value.length > 5000) errors.descricao = 'Use até 5.000 caracteres.';
    return errors;
  }

  // Salvar: valida, desabilita o formulário durante o envio e mostra o resultado.
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy || !ready) return;
    const errors = validate();
    if (Object.keys(errors).length) {
      showErrors(errors);
      notice('Confira os campos destacados.');
      return;
    }
    clearErrors();
    notice('');
    $('maintenance-success').hidden = true;
    const data = {};
    fieldNames.forEach(key => (data[key] = $(key).value.trim()));
    // Guarda o estado "disabled" de cada controle para restaurar depois do envio.
    const controls = [...form.querySelectorAll('input,select,textarea,button')].map(el => [el, el.disabled]);
    busy = true;
    saveState();
    controls.forEach(([el]) => (el.disabled = true));
    form.setAttribute('aria-busy', 'true');
    $('save-maintenance').querySelector('span').textContent = 'Salvando…';
    const controller = new AbortController(),
      timeout = setTimeout(() => controller.abort(), 20000);
    try {
      const response = await fetch('/api/manutencoes', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(data),
        signal: controller.signal,
      });
      const result = (await readJson(response)) || {};
      if (!response.ok) {
        // Erros por campo em "fields"; 403 = token CSRF inválido/expirado; 409 = manutenção duplicada.
        if (result.fields) showErrors(result.fields);
        notice(
          result.error || 'Não foi possível salvar a manutenção.',
          response.status === 403 || (!result.fields && response.status === 409)
        );
        if (response.status === 403) {
          ready = false;
          csrf = '';
        }
        return;
      }
      if (!Number.isInteger(result.id)) throw Error();
      // Sucesso: limpa o formulário e mostra o número da manutenção criada.
      form.reset();
      dirty = false;
      clearErrors();
      $('maintenance-success').textContent = 'Manutenção #' + result.id + ' cadastrada com sucesso.';
      $('maintenance-success').hidden = false;
      $('maintenance-success').scrollIntoView({ block: 'nearest' });
    } catch (_) {
      notice(
        'Não foi possível confirmar o cadastro. Verifique a conexão antes de tentar novamente. Um novo envio com a mesma unidade, tipo e data será verificado para evitar duplicidade.'
      );
    } finally {
      clearTimeout(timeout);
      busy = false;
      form.setAttribute('aria-busy', 'false');
      controls.forEach(([el, disabled]) => (el.disabled = disabled));
      $('save-maintenance').querySelector('span').textContent = 'Salvar manutenção';
      saveState();
      const invalid = form.querySelector('[aria-invalid=true]');
      if (invalid) invalid.focus();
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

  loadOptions();
})();
