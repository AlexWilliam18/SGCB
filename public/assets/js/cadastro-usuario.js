'use strict';
/*
 * Cadastro de usuário: carrega os cargos, valida os campos e grava pela API.
 *   GET  /api/usuarios/opcoes → cargos ativos (ligam os cards Operador/Administrador ao id do cargo) e token CSRF
 *   POST /api/usuarios        → cria o usuário (UsuarioController::salvar → Usuario::cadastrarUsuario)
 * Modo edição (cadastro.php?id=5, data-edit-id no <main>):
 *   GET  /api/usuarios/5        → preenche o formulário
 *   POST /api/usuarios/5        → salva nome, e-mail, telefone e cargo (Usuario::atualizarUsuario)
 *   POST /api/usuarios/5/status → só quando o status foi trocado (Usuario::alterarStatus)
 * Depois de salvar, volta para a listagem com ?usuario_salvo=id (mostra o aviso verde).
 */
(() => {
  const $ = id => document.getElementById(id),
    form = $('user-create-form'),
    listaUrl = $('user-create-main').dataset.listaUrl,
    // Id do usuário em edição; vazio = cadastro novo.
    editId = $('user-create-main').dataset.editId || '';
  // Status com que o usuário foi carregado (na edição, para saber se precisa chamar a rota de status).
  let originalStatus = '';
  // dirty = há dados não salvos; leaving = saída confirmada; saving = envio em andamento.
  let dirty = false,
    leaving = false,
    saving = false,
    csrf = window.SGCB_CSRF || '';

  // ---------------------------------------------------------------------------
  // Avisos e erros dos campos
  // ---------------------------------------------------------------------------

  // Aviso geral no topo do formulário; showRetry mostra o botão "Tentar novamente".
  function notice(text, showRetry = false) {
    $('user-notice-text').textContent = text;
    $('reload-options').hidden = !showRetry;
    $('user-notice').hidden = false;
    $('user-notice').scrollIntoView({ block: 'nearest' });
  }

  // Esconde todas as mensagens de erro e tira a borda vermelha dos campos.
  function clearErrors() {
    form.querySelectorAll('.user-error').forEach(el => {
      el.hidden = true;
      el.textContent = '';
    });
    form.querySelectorAll('[aria-invalid]').forEach(el => {
      el.removeAttribute('aria-invalid');
      el.removeAttribute('aria-describedby');
    });
    $('senha').setAttribute('aria-describedby', 'password-help');
  }

  // Mostra os erros { campo: mensagem } (da validação local ou da API) e foca o primeiro campo.
  // cargo_id (erro da API) aparece abaixo dos cards de perfil.
  function showErrors(errors) {
    clearErrors();
    let first;
    Object.entries(errors).forEach(([key, text]) => {
      const error = $('error-' + key),
        input = key === 'cargo_id' ? form.querySelector('input[name="perfil"]:checked') : $(key);
      if (!error) return;
      error.textContent = text;
      error.hidden = false;
      if (input) {
        input.setAttribute('aria-invalid', 'true');
        input.setAttribute('aria-describedby', error.id + (key === 'senha' ? ' password-help' : ''));
        first ||= input;
      }
    });
    first?.focus();
  }

  // ---------------------------------------------------------------------------
  // Cargos: o value de cada card vira o id do cargo no banco (procurado pelo data-cargo)
  // ---------------------------------------------------------------------------

  async function loadOptions() {
    $('save-user').disabled = true;
    $('user-notice').hidden = true;
    try {
      const response = await fetch(window.SGCB_API + 'usuarios/opcoes', { cache: 'no-store' });
      const data = await response.json().catch(() => null);
      if (response.status === 403) {
        notice(data?.error || 'Você não tem permissão para cadastrar usuários.');
        return;
      }
      if (!response.ok || !data || !Array.isArray(data.cargos)) throw Error();
      csrf = data.csrf_token || csrf;
      form.querySelectorAll('input[name="perfil"]').forEach(radio => {
        const cargo = data.cargos.find(item => item.codigo === radio.dataset.cargo);
        // Cargo que não existe no banco (ex.: OPERADOR sem o seed 003) deixa o card desabilitado.
        radio.value = cargo ? cargo.id : '';
        radio.disabled = !cargo;
      });
      // Se o card marcado ficou desabilitado, marca o primeiro disponível.
      if (form.querySelector('input[name="perfil"]:checked')?.disabled) {
        const available = form.querySelector('input[name="perfil"]:not(:disabled)');
        if (available) available.checked = true;
      }
      if (!form.querySelector('input[name="perfil"]:not(:disabled)')) {
        notice('Nenhum cargo ativo cadastrado. Cadastre os cargos no banco antes de criar usuários.');
        return;
      }
      // Na edição, só libera o Salvar depois de carregar os dados do usuário.
      if (editId && !(await loadUser())) return;
      $('save-user').disabled = false;
    } catch (_) {
      notice('Não foi possível carregar os perfis de acesso. Verifique a conexão e tente novamente.', true);
    }
  }
  $('reload-options').addEventListener('click', loadOptions);

  // ---------------------------------------------------------------------------
  // Modo edição: preenche o formulário com o usuário salvo (GET /api/usuarios/{id})
  // ---------------------------------------------------------------------------

  async function loadUser() {
    try {
      const response = await fetch(window.SGCB_API + 'usuarios/' + encodeURIComponent(editId), { cache: 'no-store' });
      const user = await response.json().catch(() => null);
      if (response.status === 404) {
        notice('Usuário não encontrado. Volte para a listagem e escolha outro usuário.');
        return false;
      }
      if (!response.ok || !user) throw Error();
      $('nome').value = user.nome || '';
      $('email').value = user.email || '';
      $('telefone').value = user.telefone || '';
      $('telefone').dispatchEvent(new Event('blur')); // aplica a máscara do telefone
      originalStatus = user.ativo ? 'ativo' : 'inativo';
      $('status').value = originalStatus;
      // Marca o card cujo value (id do cargo) é o cargo atual do usuário.
      const radio = form.querySelector(`input[name="perfil"][value="${Number(user.cargo_id)}"]`);
      if (radio) radio.checked = true;
      else notice('O perfil atual deste usuário (' + (user.cargo_nome || 'sem cargo') + ') não está entre as opções. Escolha um perfil antes de salvar.');
      dirty = false; // preencher o formulário não conta como alteração
      return true;
    } catch (_) {
      notice('Não foi possível carregar o usuário. Verifique a conexão e tente novamente.', true);
      return false;
    }
  }

  // ---------------------------------------------------------------------------
  // Digitação: marca que há dados não salvos e apaga o erro do campo alterado
  // ---------------------------------------------------------------------------

  form.addEventListener('input', event => {
    dirty = true;
    const error = $('error-' + event.target.id);
    if (error) error.hidden = true;
    event.target.removeAttribute('aria-invalid');
  });
  form.addEventListener('change', () => {
    dirty = true;
  });

  // Máscara do telefone ao sair do campo: (12) 98888-7777 ou (12) 3888-7777.
  $('telefone').addEventListener('blur', event => {
    const digits = event.target.value.replace(/\D/g, '');
    if (digits.length === 11) event.target.value = digits.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    else if (digits.length === 10) event.target.value = digits.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
  });

  // ---------------------------------------------------------------------------
  // Validação no navegador (as mesmas regras são conferidas de novo no model Usuario)
  // ---------------------------------------------------------------------------

  function validate() {
    const errors = {};
    if (!$('nome').value.trim()) errors.nome = 'Informe o nome completo.';
    if (!$('email').value.trim() || !$('email').validity.valid) errors.email = 'Informe um e-mail válido.';
    const phone = $('telefone').value.replace(/\D/g, '');
    if ($('telefone').value.trim() && !/^\d{10,11}$/.test(phone))
      errors.telefone = 'Informe um telefone válido, incluindo o DDD.';
    if (!['ativo', 'inativo'].includes($('status').value)) errors.status = 'Selecione o status.';
    // Senha: 8 a 72 caracteres (limite do bcrypt), com letras e números. Na edição a senha não muda.
    const password = $('senha').value;
    if (!editId) {
      if (password.length < 8 || password.length > 72 || !/[A-Za-z]/.test(password) || !/[0-9]/.test(password))
        errors.senha = 'Use de 8 a 72 caracteres, com letras e números.';
      if (!$('confirmar-senha').value || password !== $('confirmar-senha').value)
        errors['confirmar-senha'] = 'As senhas devem ser iguais.';
    }
    if (!form.querySelector('input[name="perfil"]:checked')?.value) errors.cargo_id = 'Selecione o perfil de acesso.';
    return errors;
  }

  // ---------------------------------------------------------------------------
  // Envio: POST /api/usuarios (novo) ou /api/usuarios/{id} (edição) em JSON, com o token CSRF
  // ---------------------------------------------------------------------------

  // POST em JSON; devolve { response, result } para tratar os erros por campo.
  async function post(rota, dados, signal) {
    const response = await fetch(window.SGCB_API + rota, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(dados),
      signal,
    });
    return { response, result: await response.json().catch(() => ({})) };
  }

  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (saving) return;
    const errors = validate();
    if (Object.keys(errors).length) {
      showErrors(errors);
      return;
    }
    clearErrors();
    $('user-notice').hidden = true;

    // Mesmos nomes que o model Usuario espera (a confirmação de senha não vai para o servidor).
    const data = {
      nome: $('nome').value.trim(),
      email: $('email').value.trim(),
      telefone: $('telefone').value.trim(),
      cargo_id: Number(form.querySelector('input[name="perfil"]:checked').value),
      senha: $('senha').value,
      ativo: $('status').value === 'ativo',
      deve_alterar_senha: $('alterar-senha').checked,
    };

    // Trava o formulário enquanto salva.
    saving = true;
    form.setAttribute('aria-busy', 'true');
    $('save-user').textContent = 'Salvando…';
    const controls = [...form.querySelectorAll('input,select,button')].map(el => [el, el.disabled]);
    controls.forEach(([el]) => (el.disabled = true));
    const controller = new AbortController(),
      timer = setTimeout(() => controller.abort(), 30000);
    try {
      // Edição: a rota de atualizar ignora senha/status, então eles não são enviados.
      if (editId) {
        delete data.senha;
        delete data.ativo;
        delete data.deve_alterar_senha;
      }
      const { response, result } = await post(editId ? 'usuarios/' + encodeURIComponent(editId) : 'usuarios', data, controller.signal);
      if (!response.ok) {
        // Erros por campo (ex.: e-mail já cadastrado) + mensagem geral.
        if (result.fields) showErrors(result.fields);
        notice(result.error || 'Não foi possível salvar o usuário.');
        return;
      }
      if (!Number.isInteger(result.id)) throw Error();
      // Edição com status trocado: grava o status na rota própria (ativar/inativar).
      if (editId && $('status').value !== originalStatus) {
        const status = await post('usuarios/' + encodeURIComponent(editId) + '/status', { ativo: $('status').value === 'ativo' }, controller.signal);
        if (!status.response.ok) {
          // Os dados já foram salvos; só o status falhou (ex.: tentar inativar a si mesmo).
          notice('Dados salvos, mas o status não foi alterado: ' + (status.result.error || 'tente novamente.'));
          return;
        }
        originalStatus = $('status').value;
      }
      // Salvo: volta para a listagem sem perguntar sobre descartar.
      dirty = false;
      leaving = true;
      location.assign(listaUrl + '?usuario_salvo=' + result.id + (editId ? '&editado=1' : ''));
    } catch (_) {
      notice(
        'Não foi possível confirmar o cadastro. Verifique a conexão e consulte a listagem antes de tentar novamente para evitar duplicidade.'
      );
    } finally {
      clearTimeout(timer);
      saving = false;
      form.setAttribute('aria-busy', 'false');
      controls.forEach(([el, disabled]) => (el.disabled = disabled));
      $('save-user').textContent = editId ? 'Salvar alterações' : 'Salvar usuário';
    }
  });

  // ---------------------------------------------------------------------------
  // Sair da página com dados preenchidos: pergunta antes de descartar
  // ---------------------------------------------------------------------------

  document.querySelectorAll('a[href]').forEach(link =>
    link.addEventListener('click', event => {
      // Ignora: sem alterações, nova aba (Ctrl/Shift), âncoras da página e o botão Sair.
      if (
        !dirty ||
        leaving ||
        event.ctrlKey ||
        event.metaKey ||
        event.shiftKey ||
        link.getAttribute('href').startsWith('#') ||
        link.classList.contains('logout')
      )
        return;
      event.preventDefault();
      // Diálogo padrão (descartar.js): "Descartar e sair" limpa o formulário e marca a saída como confirmada.
      SgcbDescartar.abrir(link.href, () => {
        form.reset();
        dirty = false;
        leaving = true;
      });
    })
  );
  // Fechar a aba ou recarregar: aviso padrão do navegador.
  window.addEventListener('beforeunload', event => {
    if (dirty && !leaving) {
      event.preventDefault();
      event.returnValue = '';
    }
  });

  loadOptions();
})();
