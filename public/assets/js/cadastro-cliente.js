'use strict';
/*
 * Cadastro de cliente: máscaras, validação, endereço (um por cliente), foto, anexos e aviso de alterações não salvas.
 * As opções e o salvamento usam /api/cadastros/opcoes e /api/clientes (atendidos por public/api/index.php → src/Controllers).
 */
(() => {
  // URL da listagem de clientes, vinda do data-lista-url do <main>.
  const listaUrl = document.getElementById('cadastro-main').dataset.listaUrl;
  const $ = id => document.getElementById(id),
    form = $('cadastro-form');
  const ufs = 'AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO'.split(' ');
  // Campos do endereço cadastral: na tela o id é "endereco_<campo>"; na API, endereco.<campo>.
  const ADDRESS_FIELDS = ['cep', 'rua', 'numero', 'bairro', 'cidade', 'uf', 'complemento'];
  // Edição: cadastro.php?id=5 abre o cliente 5 preenchido e salva em POST /api/clientes/5.
  const editId = /^\d+$/.test(new URLSearchParams(location.search).get('id') || '')
      ? new URLSearchParams(location.search).get('id')
      : null,
    saveLabel = editId ? 'Salvar alterações' : 'Salvar cadastro';
  let loaded = !editId, // na edição, só libera o Salvar depois de carregar o cadastro
    dirty = false,
    saving = false,
    ready = false,
    responsible = false,
    csrf = '',
    photo = null,
    photoURL = null,
    attachments = [];
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
      // A API devolve "endereco.cep"; o campo na tela é "endereco_cep".
      const id = key.replace('.', '_'),
        input = $(id),
        error = $('error-' + id);
      if (error) error.textContent = message;
      if (input) {
        input.setAttribute('aria-invalid', 'true');
        if (error) input.setAttribute('aria-describedby', error.id);
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
  // Estados do select e máscara do CEP (00000-000) do endereço cadastral.
  ufs.forEach(uf => $('endereco_uf').append(new Option(uf, uf)));
  $('endereco_cep').addEventListener(
    'input',
    e =>
      (e.target.value = e.target.value
        .replace(/\D/g, '')
        .slice(0, 8)
        .replace(/(\d{5})(\d)/, '$1-$2'))
  );
  // Textos da tela no modo edição.
  if (editId) {
    document.querySelector('.form-intro h2').textContent = 'Editar cliente';
    document.querySelector('.form-intro p').textContent =
      'Altere os dados e salve. Anexos novos são somados aos que já foram enviados.';
    $('save-client').textContent = saveLabel;
    document.title = document.title.replace('Novo cliente', 'Editar cliente');
  }
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
    $('save-client').disabled = saving || !ready || !responsible || !loaded;
  }
  // Edição: busca o cadastro na API (GET /api/clientes/{id}) e preenche o formulário.
  async function loadRecord() {
    const response = await fetch(window.SGCB_API + 'clientes/' + encodeURIComponent(editId), { cache: 'no-store' });
    const client = await response.json().catch(() => null);
    if (!response.ok || !client) throw Error(client?.error || 'Não foi possível carregar o cadastro.');

    $('tipo_cadastro').value = client.tipo_cadastro || '';
    $('nome_razao_social').value = client.nome || '';
    $('nome_fantasia').value = client.nome_fantasia || '';
    $('cpf_cnpj').value = maskDoc(client.cpf_cnpj || '');
    $('email').value = client.email || '';
    $('telefone').value = client.telefone || '';
    $('telefone').dispatchEvent(new Event('blur')); // aplica a máscara (12) 99999-9999
    $('data_nascimento').value = client.data_nascimento || '';
    $('profissao').value = client.profissao || '';
    // Nacionalidade fora da lista vai para "Outra" + campo de texto.
    const nationality = client.nacionalidade || '',
      known = [...$('nacionalidade').options].some(option => option.value === nationality);
    $('nacionalidade').value = known ? nationality : nationality ? 'Outra' : '';
    $('other-nationality').hidden = known || !nationality;
    $('nacionalidade_outra').value = known ? '' : nationality;
    $('condicao_pagamento_id').value = client.condicao_pagamento_id ? String(client.condicao_pagamento_id) : '';
    $('limite_credito').value = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(
      Number(client.limite_credito) || 0
    );
    $('permite_ultrapassar_limite').checked = Boolean(client.permite_ultrapassar_limite);

    // Endereço cadastral (um só por cliente).
    const address = client.endereco || {};
    ADDRESS_FIELDS.forEach(field => ($('endereco_' + field).value = address[field] || ''));
    $('endereco_cep').value = (address.cep || '').replace(/(\d{5})(\d)/, '$1-$2');

    // Foto atual (só exibição; uma foto nova escolhida substitui esta ao salvar).
    if (client.foto) {
      $('photo-preview').src = window.SGCB_BASE + client.foto;
      $('photo-preview').hidden = false;
      $('choose-photo').textContent = 'Alterar foto';
    }
    // Anexos já enviados ficam listados acima dos novos.
    if (client.anexos?.length) {
      const existing = document.createElement('p');
      existing.className = 'existing-attachments';
      existing.textContent = 'Já enviados: ' + client.anexos.map(file => file.nome_original).join(', ');
      $('attachment-list').before(existing);
    }
    loaded = true;
    dirty = false;
  }
  // Busca as condições de pagamento e o token CSRF; o botão Salvar só libera se vier tudo certo.
  async function loadOptions() {
    ready = false;
    saveState();
    $('reload-options').disabled = true;
    try {
      const response = await fetch(window.SGCB_API + 'cadastros/opcoes', { cache: 'no-store' });
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
      // Edição: com as opções prontas, carrega o cadastro (uma vez só).
      if (editId && !loaded) await loadRecord();
    } catch (_) {
      notice(
        editId && !loaded
          ? 'Não foi possível carregar o cadastro para edição. Verifique a conexão e tente novamente.'
          : 'Não foi possível carregar as condições de pagamento. Verifique a conexão com o banco e tente novamente.',
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
    data.endereco = Object.fromEntries(ADDRESS_FIELDS.map(field => [field, $('endereco_' + field).value.trim()]));
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
    // Endereço: obrigatório, menos o complemento (mesma regra do Cliente::validarEndereco no PHP).
    ['rua', 'numero', 'bairro', 'cidade', 'uf'].forEach(k => {
      if (!data.endereco[k]) errors['endereco.' + k] = 'Preencha este campo.';
    });
    if (!/^\d{8}$/.test(data.endereco.cep.replace(/\D/g, ''))) errors['endereco.cep'] = 'Informe um CEP com 8 dígitos.';
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
    attachments.forEach(f => body.append('anexos[]', f)); // [] para o PHP receber todos os arquivos
    saving = true;
    form.setAttribute('aria-busy', 'true');
    $('save-client').textContent = 'Salvando…';
    const controls = [...form.querySelectorAll('input,select,button')].map(el => [el, el.disabled]);
    controls.forEach(([el]) => (el.disabled = true));
    const controller = new AbortController(),
      timer = setTimeout(() => controller.abort(), 45000);
    try {
      // Cadastro novo: POST clientes; edição: POST clientes/{id} (mesmo formato de envio).
      const response = await fetch(window.SGCB_API + (editId ? 'clientes/' + encodeURIComponent(editId) : 'clientes'), {
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
        listaUrl +
          '?cadastro_salvo=' +
          result.id +
          '&tipo_cadastro=' +
          encodeURIComponent(result.tipo_cadastro) +
          (editId ? '&editado=1' : '')
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
      $('save-client').textContent = saveLabel;
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
        // Diálogo padrão (descartar.js): "Descartar e sair" zera o dirty e segue para o link.
        SgcbDescartar.abrir(link.href, () => (dirty = false));
      }
    })
  );
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
