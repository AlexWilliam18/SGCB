'use strict';
/*
 * Cadastro de cliente: máscaras, validação, endereços, foto, anexos e aviso de alterações não salvas.
 * As opções e o salvamento usam /api/cadastros/opcoes e /api/clientes (back-end ainda não implementado).
 */
(() => {
  // URL da listagem de clientes, vinda do data-lista-url do <main>.
  const listaUrl = document.getElementById('cadastro-main').dataset.listaUrl;
  const $ = id => document.getElementById(id),
    form = $('cadastro-form'),
    nodes = () => [...$('addresses').querySelectorAll('.address-block')];
  const ufs = 'AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO'.split(' ');
  const labels = {
    RESIDENCIAL: 'residencial',
    COMERCIAL: 'comercial',
    ENTREGA: 'de entrega',
    COBRANCA: 'de cobrança',
    OUTRO: 'adicional',
  };
  let dirty = false,
    saving = false,
    ready = false,
    responsible = false,
    csrf = '',
    photo = null,
    photoURL = null,
    attachments = [],
    sequence = 0,
    leave = listaUrl;
  $('data_nascimento').max = new Intl.DateTimeFormat('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    timeZone: 'America/Sao_Paulo',
  }).format(new Date());
  function notice(text, retry = false) {
    $('notice-text').textContent = text;
    $('form-notice').hidden = false;
    $('reload-options').hidden = !retry;
  }
  function clearErrors() {
    document.querySelectorAll('.field-error').forEach(el => (el.textContent = ''));
    form.querySelectorAll('[aria-invalid]').forEach(el => {
      el.removeAttribute('aria-invalid');
      el.removeAttribute('aria-describedby');
    });
  }
  function showErrors(errors) {
    clearErrors();
    let first;
    Object.entries(errors).forEach(([key, message]) => {
      const m = /^enderecos\.(\d+)\.(.+)$/.exec(key),
        input = m ? nodes()[Number(m[1])]?.querySelector('[data-field="' + m[2] + '"]') : $(key),
        id = input?.id || key,
        error = $('error-' + id);
      if (error) error.textContent = message;
      if (input) {
        input.setAttribute('aria-invalid', 'true');
        if (error) input.setAttribute('aria-describedby', error.id);
        const block = input.closest('.address-block');
        if (block) {
          block.querySelector('.address-body').hidden = false;
          block.querySelector('.collapse-address').setAttribute('aria-expanded', 'true');
        }
        first ||= input;
      }
    });
    first?.focus();
  }
  function maskDoc(value) {
    let d = value.replace(/\D/g, '').slice(0, 14);
    if (d.length <= 11)
      return d
        .replace(/^(\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
    return d
      .replace(/^(\d{2})(\d)/, '$1.$2')
      .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
      .replace(/\.(\d{3})(\d)/, '.$1/$2')
      .replace(/(\d{4})(\d)/, '$1-$2');
  }
  // Confere os dígitos verificadores do CPF (11 dígitos) ou do CNPJ (14 dígitos).
  function validDoc(value) {
    const d = value.replace(/\D/g, '');
    if (!/^(?:[0-9]{11}|[0-9]{14})$/.test(d) || new Set(d).size === 1) return false;
    let base = d.slice(0, d.length === 11 ? 9 : 12);
    const all =
      d.length === 11
        ? [
            [10, 9, 8, 7, 6, 5, 4, 3, 2],
            [11, 10, 9, 8, 7, 6, 5, 4, 3, 2],
          ]
        : [
            [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
          ];
    for (const w of all) {
      const mod = [...base].reduce((sum, n, i) => sum + Number(n) * w[i], 0) % 11;
      base += String(mod < 2 ? 0 : 11 - mod);
    }
    return base === d;
  }
  function refresh() {
    nodes().forEach((node, i) => {
      const principal = node.querySelector('.principal-input').checked,
        kind = node.querySelector('[data-field=tipo]').value;
      node.classList.toggle('primary', principal);
      node.querySelector('.address-title').textContent = principal ? 'Endereço principal' : 'Endereço ' + labels[kind];
      node
        .querySelector('.principal-input')
        .setAttribute('aria-label', 'Definir endereço ' + (i + 1) + ' como principal');
      node.querySelector('.remove-address').setAttribute('aria-label', 'Remover endereço ' + (i + 1));
      node.classList.toggle(
        'has-data',
        [...node.querySelectorAll('.address-body input,.address-body select')].some(el => el.value.trim())
      );
      node.querySelector('[data-field=cep]').placeholder = principal ? '00000-000' : '';
    });
    $('add-address').disabled = nodes().length >= 20;
  }
  function addAddress(kind = 'ENTREGA', principal = false, focus = false) {
    if (nodes().length >= 20) return;
    const fragment = $('address-template').content.cloneNode(true),
      node = fragment.querySelector('.address-block'),
      seq = ++sequence;
    node.querySelector('[data-field=tipo]').value = kind;
    node.querySelector('.principal-input').checked = principal;
    node.querySelectorAll('[data-field]').forEach(input => {
      input.id = 'address-' + seq + '-' + input.dataset.field;
      const field = input.closest('.form-field');
      if (field) {
        field.querySelector('label').htmlFor = input.id;
        field.querySelector('.field-error').id = 'error-' + input.id;
      }
      if (input.dataset.field === 'uf') ufs.forEach(uf => input.append(new Option(uf, uf)));
    });
    const body = node.querySelector('.address-body'),
      collapse = node.querySelector('.collapse-address');
    body.id = 'address-body-' + seq;
    collapse.setAttribute('aria-controls', body.id);
    collapse.addEventListener('click', () => {
      body.hidden = !body.hidden;
      collapse.setAttribute('aria-expanded', String(!body.hidden));
      collapse.textContent = body.hidden ? '⌄' : '⌃';
    });
    node.querySelector('.make-primary').addEventListener('click', () => {
      node.querySelector('.principal-input').checked = true;
      dirty = true;
      refresh();
    });
    node.querySelector('.remove-address').addEventListener('click', () => {
      if (nodes().length <= 1) return;
      const was = node.querySelector('.principal-input').checked;
      node.remove();
      if (was) nodes()[0].querySelector('.principal-input').checked = true;
      dirty = true;
      refresh();
    });
    node.querySelector('[data-field=cep]').addEventListener(
      'input',
      e =>
        (e.target.value = e.target.value
          .replace(/\D/g, '')
          .slice(0, 8)
          .replace(/(\d{5})(\d)/, '$1-$2'))
    );
    node.addEventListener('input', refresh);
    node.querySelector('[data-field=tipo]').addEventListener('change', refresh);
    $('addresses').append(fragment);
    refresh();
    if (focus) {
      dirty = true;
      node.scrollIntoView({
        behavior: matchMedia('(prefers-reduced-motion:reduce)').matches ? 'auto' : 'smooth',
        block: 'center',
      });
      node.querySelector('[data-field=cep]').focus({ preventScroll: true });
    }
  }
  addAddress('RESIDENCIAL', true);
  addAddress('ENTREGA');
  $('add-address').addEventListener('click', () => addAddress('ENTREGA', false, true));
  form.addEventListener('input', e => {
    dirty = true;
    const error = $('error-' + e.target.id);
    if (error) error.textContent = '';
    e.target.removeAttribute('aria-invalid');
  });
  form.addEventListener('change', () => (dirty = true));
  $('cpf_cnpj').addEventListener('input', e => (e.target.value = maskDoc(e.target.value)));
  $('telefone').addEventListener('blur', e => {
    const d = e.target.value.replace(/\D/g, '');
    if (d.length === 10) e.target.value = d.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    if (d.length === 11) e.target.value = d.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
  });
  $('nacionalidade').addEventListener('change', () => {
    $('other-nationality').hidden = $('nacionalidade').value !== 'Outra';
    if (!$('other-nationality').hidden) $('nacionalidade_outra').focus();
  });
  function credit() {
    const raw = $('limite_credito').value.trim();
    return raw.includes(',') ? raw.replace(/\./g, '').replace(',', '.') : raw;
  }
  $('limite_credito').addEventListener('blur', () => {
    const value = credit();
    if (/^\d{1,12}(\.\d{1,2})?$/.test(value))
      $('limite_credito').value = new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(Number(value));
  });
  function removePhoto() {
    if (photoURL) URL.revokeObjectURL(photoURL);
    photo = null;
    photoURL = null;
    $('photo-preview').hidden = true;
    $('photo-preview').removeAttribute('src');
    $('remove-photo').hidden = true;
    $('choose-photo').textContent = 'Adicionar foto';
    $('photo-file').value = '';
  }
  $('choose-photo').addEventListener('click', () => $('photo-file').click());
  $('remove-photo').addEventListener('click', () => {
    removePhoto();
    dirty = true;
  });
  $('photo-file').addEventListener('change', () => {
    const file = $('photo-file').files[0];
    if (!file) return;
    if (!/\.(jpe?g|png|webp)$/i.test(file.name) || file.size > 5 * 1024 * 1024 || !file.size) {
      $('error-foto').textContent = 'Escolha uma imagem JPG, PNG ou WEBP de até 5 MB.';
      return;
    }
    removePhoto();
    photo = file;
    photoURL = URL.createObjectURL(file);
    $('photo-preview').src = photoURL;
    $('photo-preview').hidden = false;
    $('remove-photo').hidden = false;
    $('choose-photo').textContent = 'Alterar foto';
    $('error-foto').textContent = '';
    dirty = true;
  });
  $('photo-preview').addEventListener('error', () => {
    if (photo) {
      removePhoto();
      $('error-foto').textContent = 'Não foi possível abrir esta imagem. Escolha outro arquivo.';
    }
  });
  function renderFiles() {
    const list = $('attachment-list');
    list.replaceChildren();
    list.hidden = !attachments.length;
    attachments.forEach((file, index) => {
      const row = document.createElement('li'),
        name = document.createElement('span'),
        remove = document.createElement('button');
      name.textContent =
        file.name + ' • ' + (file.size / 1024 / 1024).toLocaleString('pt-BR', { maximumFractionDigits: 2 }) + ' MB';
      remove.type = 'button';
      remove.textContent = 'Remover';
      remove.setAttribute('aria-label', 'Remover ' + file.name);
      remove.addEventListener('click', () => {
        attachments.splice(index, 1);
        dirty = true;
        renderFiles();
      });
      row.append(name, remove);
      list.append(row);
    });
  }
  function addFiles(files) {
    const added = [...files].filter(
      file =>
        !attachments.some(
          old => old.name === file.name && old.size === file.size && old.lastModified === file.lastModified
        )
    );
    if (attachments.length + added.length > 10) {
      $('error-anexos').textContent = 'Selecione no máximo 10 arquivos por cadastro.';
      return;
    }
    const invalid = added.find(
      file => !/\.(pdf|jpe?g|png|docx?|xlsx?)$/i.test(file.name) || file.size > 10 * 1024 * 1024 || !file.size
    );
    if (invalid) {
      $('error-anexos').textContent =
        'Confira o formato e o tamanho de ' + invalid.name + '. O limite é de 10 MB por arquivo.';
      return;
    }
    attachments.push(...added);
    $('error-anexos').textContent = '';
    dirty ||= added.length > 0;
    renderFiles();
  }
  $('dropzone').addEventListener('click', () => $('attachment-files').click());
  $('attachment-files').addEventListener('change', () => {
    addFiles($('attachment-files').files);
    $('attachment-files').value = '';
  });
  ['dragenter', 'dragover'].forEach(name =>
    $('dropzone').addEventListener(name, e => {
      e.preventDefault();
      $('dropzone').classList.add('dragover');
    })
  );
  ['dragleave', 'drop'].forEach(name =>
    $('dropzone').addEventListener(name, e => {
      e.preventDefault();
      $('dropzone').classList.remove('dragover');
    })
  );
  $('dropzone').addEventListener('drop', e => addFiles(e.dataTransfer.files));
  function saveState() {
    $('save-client').disabled = saving || !ready || !responsible;
  }
  // Busca as condições de pagamento e o token CSRF; o botão Salvar só libera se vier tudo certo.
  async function loadOptions() {
    ready = false;
    saveState();
    $('reload-options').disabled = true;
    try {
      const response = await fetch('/api/cadastros/opcoes', { cache: 'no-store' });
      if (!response.ok) throw Error();
      const data = await response.json();
      if (!Array.isArray(data.condicoes_pagamento) || !data.csrf_token) throw Error();
      const previous = $('condicao_pagamento_id').value;
      $('condicao_pagamento_id').replaceChildren(new Option('Selecione uma opção', ''));
      data.condicoes_pagamento.forEach(c => $('condicao_pagamento_id').append(new Option(c.nome, String(c.id))));
      $('condicao_pagamento_id').value = previous;
      csrf = data.csrf_token;
      responsible = data.responsavel_configurado === true;
      ready = data.condicoes_pagamento.length > 0;
      if (!responsible)
        notice('Solicite ao administrador a configuração de um usuário responsável para habilitar o salvamento.', true);
      else if (!ready) notice('Cadastre uma condição de pagamento para continuar.', true);
      else $('form-notice').hidden = true;
    } catch (_) {
      notice(
        'Não foi possível carregar as condições de pagamento. Verifique a conexão com o banco e tente novamente.',
        true
      );
    } finally {
      $('reload-options').disabled = false;
      saveState();
    }
  }
  $('reload-options').addEventListener('click', loadOptions);
  function collect() {
    const data = {};
    [
      'tipo_cadastro',
      'nome_razao_social',
      'nome_fantasia',
      'cpf_cnpj',
      'email',
      'telefone',
      'data_nascimento',
      'profissao',
    ].forEach(id => (data[id] = $(id).value.trim()));
    data.nacionalidade =
      $('nacionalidade').value === 'Outra' ? $('nacionalidade_outra').value.trim() : $('nacionalidade').value;
    data.condicao_pagamento_id = $('condicao_pagamento_id').value;
    data.limite_credito = credit();
    data.permite_ultrapassar_limite = $('permite_ultrapassar_limite').checked;
    data.enderecos = nodes().map(node => {
      const a = { principal: node.querySelector('.principal-input').checked };
      node.querySelectorAll('[data-field]').forEach(input => (a[input.dataset.field] = input.value.trim()));
      return a;
    });
    return data;
  }
  function validate(data) {
    const errors = {};
    ['tipo_cadastro', 'nome_razao_social', 'email', 'telefone', 'condicao_pagamento_id'].forEach(k => {
      if (!data[k]) errors[k] = 'Preencha este campo.';
    });
    if (!validDoc(data.cpf_cnpj)) errors.cpf_cnpj = 'CPF/CNPJ inválido. Confira os números informados.';
    if (!$('email').validity.valid) errors.email = 'Informe um e-mail válido.';
    if (!/^\d{8,15}$/.test(data.telefone.replace(/\D/g, '')))
      errors.telefone = 'Informe um telefone válido, incluindo o DDD.';
    if (data.data_nascimento && !$('data_nascimento').validity.valid)
      errors.data_nascimento = 'Informe uma data válida, até o dia de hoje.';
    if (!/^\d{1,12}(\.\d{1,2})?$/.test(data.limite_credito))
      errors.limite_credito = 'Informe um limite válido e não negativo.';
    if ($('nacionalidade').value === 'Outra' && !data.nacionalidade)
      errors.nacionalidade_outra = 'Informe a nacionalidade.';
    let count = 0,
      principals = 0;
    data.enderecos.forEach((a, i) => {
      if (!['cep', 'rua', 'numero', 'bairro', 'cidade', 'uf', 'complemento'].some(k => a[k])) return;
      count++;
      if (a.principal) principals++;
      ['rua', 'numero', 'bairro', 'cidade', 'uf'].forEach(k => {
        if (!a[k]) errors['enderecos.' + i + '.' + k] = 'Preencha este campo.';
      });
      if (!/^\d{8}$/.test(a.cep.replace(/\D/g, '')))
        errors['enderecos.' + i + '.cep'] = 'Informe um CEP com 8 dígitos.';
    });
    if (!count) errors.enderecos = 'Preencha ao menos um endereço completo.';
    else if (principals !== 1) errors.enderecos = 'Defina exatamente um endereço principal.';
    return errors;
  }
  // Salvar: valida no navegador e envia dados, foto e anexos em um único POST para /api/clientes.
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (saving || !ready || !responsible) return;
    const data = collect(),
      errors = validate(data);
    if (Object.keys(errors).length) {
      showErrors(errors);
      notice('Verifique os campos destacados antes de salvar.');
      return;
    }
    clearErrors();
    $('form-notice').hidden = true;
    const body = new FormData();
    body.append('dados', JSON.stringify(data));
    if (photo) body.append('foto', photo);
    attachments.forEach(f => body.append('anexos', f));
    saving = true;
    form.setAttribute('aria-busy', 'true');
    $('save-client').textContent = 'Salvando…';
    const controls = [...form.querySelectorAll('input,select,button')].map(el => [el, el.disabled]);
    controls.forEach(([el]) => (el.disabled = true));
    const controller = new AbortController(),
      timer = setTimeout(() => controller.abort(), 45000);
    try {
      const response = await fetch('/api/clientes', {
          method: 'POST',
          headers: { 'X-CSRF-Token': csrf },
          body,
          signal: controller.signal,
        }),
        result = await response.json();
      if (!response.ok) {
        if (result.fields) showErrors(result.fields);
        notice(result.error || 'Não foi possível salvar o cadastro.', response.status === 403);
        return;
      }
      if (!Number.isInteger(result.id)) throw Error();
      dirty = false;
      location.assign(
        listaUrl + '?cadastro_salvo=' + result.id + '&tipo_cadastro=' + encodeURIComponent(result.tipo_cadastro)
      );
    } catch (_) {
      notice(
        'Não foi possível confirmar o cadastro. Verifique a conexão e consulte a listagem antes de tentar novamente para evitar duplicidade.'
      );
    } finally {
      clearTimeout(timer);
      saving = false;
      form.setAttribute('aria-busy', 'false');
      controls.forEach(([el, disabled]) => (el.disabled = disabled));
      $('save-client').textContent = 'Salvar cadastro';
      saveState();
    }
  });
  document.querySelectorAll('a[href]').forEach(link =>
    link.addEventListener('click', event => {
      if (event.ctrlKey || event.metaKey || event.shiftKey || link.getAttribute('href').startsWith('#')) return;
      if (saving) {
        event.preventDefault();
        notice('Aguarde a conclusão do salvamento.');
        return;
      }
      if (dirty) {
        event.preventDefault();
        leave = link.href;
        $('discard-dialog').showModal();
      }
    })
  );
  $('discard-dialog').addEventListener('close', () => {
    if ($('discard-dialog').returnValue === 'descartar') {
      dirty = false;
      location.assign(leave);
    }
  });
  window.addEventListener('beforeunload', event => {
    if (dirty) {
      event.preventDefault();
      event.returnValue = '';
    }
  });
  window.addEventListener('pagehide', () => {
    if (photoURL) URL.revokeObjectURL(photoURL);
  });
  loadOptions();
})();
