'use strict';
/*
 * Diálogo de categorias da listagem de produtos: cadastrar, editar, desativar, ativar e excluir.
 * Ao abrir, busca a lista e o token CSRF em /api/categorias/opcoes. Gravações (com X-CSRF-Token):
 *   POST /api/categorias              cadastrar ({ nome })
 *   POST /api/categorias/{id}         editar o nome ({ nome })
 *   POST /api/categorias/{id}/status  desativar/ativar ({ ativo: false|true }), um UPDATE ativo; qualquer usuário
 *                                     com permissão de editar produtos.
 *   POST /api/categorias/{id}/excluir excluir de vez: só o administrador (pode_excluir), categoria já desativada
 *                                     e sem produtos; o servidor confere tudo de novo.
 * Depois de cada gravação dispara o evento "categoria:alterada" ({ mensagem }), ouvido pelo produtos.js.
 * Categoria inativa continua na lista do diálogo, com estilo de desabilitada, e ganha Ativar (e Excluir para o admin).
 */
(() => {
  const $ = id => document.getElementById(id),
    dialog = $('category-dialog'),
    form = $('category-form'),
    name = $('category-name'),
    list = $('category-items');
  let csrf = '',
    ready = false,
    saving = false,
    generation = 0,
    optionsController,
    podeExcluir = false, // vem da API: só o cargo ADMIN vê o botão Excluir
    editing = null; // categoria em edição ({ id, nome }) ou null quando o formulário cadastra uma nova

  // Mensagens do diálogo: aviso geral (com "Tentar novamente") e erro do campo nome.
  function message(text, retry = false) {
    $('category-message-text').textContent = text;
    $('category-message').hidden = !text;
    $('retry-category').hidden = !retry;
  }
  function fieldError(text) {
    $('category-error').textContent = text;
    $('category-error').hidden = !text;
    if (text) {
      name.setAttribute('aria-invalid', 'true');
      name.focus();
    } else name.removeAttribute('aria-invalid');
  }
  // Trava os controles enquanto prepara ou grava (inclui os botões Editar/Desativar da lista).
  function saveState() {
    $('save-category').disabled = !ready || saving;
    name.disabled = saving;
    $('cancel-category').disabled = saving;
    $('retry-category').disabled = saving;
    list.querySelectorAll('button').forEach(button => (button.disabled = !ready || saving || button.dataset.blocked === 'true'));
  }

  // Modo do formulário: cadastrar nova categoria ou editar a escolhida na lista.
  function setMode(category) {
    editing = category;
    $('category-title').textContent = category ? 'Editar Categoria' : 'Cadastre nova Categoria';
    $('cancel-category').textContent = category ? 'Cancelar edição' : 'Cancelar';
    name.value = category ? category.nome : '';
    fieldError('');
  }

  // Botão de ação da lista (link pequeno). className: '' (verde) ou 'category-disable' (vermelho).
  function actionButton(text, label, className, onClick) {
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = text;
    button.className = className;
    button.setAttribute('aria-label', label);
    button.addEventListener('click', onClick);
    return button;
  }

  // Lista "Categorias cadastradas": ativas com Editar e Desativar; inativas com "(inativa)", Ativar e,
  // para o administrador, Excluir (desabilitado quando a categoria ainda tem produtos).
  function renderList(categories) {
    list.replaceChildren();
    if (!categories.length) {
      const empty = document.createElement('li');
      empty.className = 'category-empty';
      empty.textContent = 'Nenhuma categoria cadastrada.';
      list.append(empty);
      return;
    }
    categories.forEach(category => {
      const item = document.createElement('li'),
        label = document.createElement('span');
      item.className = 'category-item' + (category.ativo ? '' : ' is-inactive');
      label.className = 'category-item-name';
      label.textContent = category.nome + (category.ativo ? '' : ' (inativa)');
      item.append(label);
      if (category.ativo) {
        item.append(
          actionButton('Editar', 'Editar a categoria ' + category.nome, '', () => {
            setMode(category);
            message('');
            name.focus();
          }),
          actionButton('Desativar', 'Desativar a categoria ' + category.nome, 'category-disable', () => setStatus(category, false))
        );
      } else {
        item.append(actionButton('Ativar', 'Ativar a categoria ' + category.nome, '', () => setStatus(category, true)));
        if (podeExcluir) {
          const remove = actionButton('Excluir', 'Excluir a categoria ' + category.nome, 'category-disable', () => removeCategory(category));
          // Com produtos vinculados não dá para excluir (o servidor também bloqueia): explica no próprio botão.
          if (category.produtos > 0) {
            remove.dataset.blocked = 'true';
            remove.title = 'Tem ' + category.produtos + ' produto(s) vinculado(s): pode ficar desativada, mas não pode ser excluída.';
          }
          item.append(remove);
        }
      }
      list.append(item);
    });
  }

  // Busca a lista e o token CSRF; confirma que existe usuário responsável para gravar.
  async function loadOptions() {
    const current = ++generation;
    optionsController?.abort();
    const controller = (optionsController = new AbortController()),
      timeout = setTimeout(() => controller.abort(), 15000);
    ready = false;
    saveState();
    $('retry-category').disabled = true;
    try {
      const response = await fetch(window.SGCB_API + 'categorias/opcoes', { cache: 'no-store', signal: controller.signal });
      if (!response.ok) throw Error();
      const data = await response.json();
      if (current !== generation || !dialog.open) return;
      if (!data.csrf_token || !Array.isArray(data.categorias)) throw Error();
      csrf = data.csrf_token;
      ready = data.responsavel_configurado === true;
      podeExcluir = data.pode_excluir === true;
      renderList(data.categorias);
      message(
        ready ? '' : 'Solicite ao administrador a configuração de um usuário responsável para habilitar o salvamento.',
        !ready
      );
    } catch (_) {
      if (current === generation && dialog.open)
        message('Não foi possível preparar o cadastro. Verifique a conexão e tente novamente.', true);
    } finally {
      clearTimeout(timeout);
      if (current === generation) {
        $('retry-category').disabled = false;
        saveState();
      }
    }
  }

  // POST com o token CSRF. Devolve { ok, status, result }; 403 (token vencido) pede para preparar de novo.
  async function post(path, body) {
    const controller = new AbortController(),
      timeout = setTimeout(() => controller.abort(), 20000);
    try {
      const response = await fetch(window.SGCB_API + path, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
          body: JSON.stringify(body),
          signal: controller.signal,
        }),
        result = await response.json();
      if (response.status === 403) {
        ready = false;
        csrf = '';
      }
      return { ok: response.ok, status: response.status, result };
    } finally {
      clearTimeout(timeout);
    }
  }

  // Avisa a tela de Produtos (filtro e mensagem de sucesso).
  function notify(text) {
    document.dispatchEvent(new CustomEvent('categoria:alterada', { detail: { mensagem: text } }));
  }

  // Grava uma ação da lista (desativar, ativar ou excluir) depois da confirmação e recarrega a lista.
  async function runAction(category, question, path, body, doneText, failText) {
    if (saving || !ready || !confirm(question)) return;
    message('');
    saving = true;
    saveState();
    try {
      const { ok, status, result } = await post('categorias/' + encodeURIComponent(category.id) + path, body);
      if (!ok) {
        message(result.error || failText, status === 403 && !result.error);
        return;
      }
      if (editing?.id === category.id) setMode(null);
      notify(doneText);
      loadOptions();
    } catch (_) {
      message('Não foi possível confirmar a ação. Abra a lista novamente para conferir.');
    } finally {
      saving = false;
      saveState();
    }
  }

  // Desativar (ativo = 0) ou ativar de novo (ativo = 1); nada é apagado.
  function setStatus(category, active) {
    runAction(
      category,
      active
        ? 'Ativar a categoria “' + category.nome + '”?\n\nEla volta a poder ser escolhida no cadastro de produtos.'
        : 'Desativar a categoria “' + category.nome + '”?\n\n' +
            'Ela continua aparecendo nos produtos já cadastrados, mas não poderá ser escolhida em novos produtos.',
      '/status',
      { ativo: active },
      'Categoria “' + category.nome + '” ' + (active ? 'ativada.' : 'desativada.'),
      'Não foi possível ' + (active ? 'ativar' : 'desativar') + ' a categoria.'
    );
  }

  // Excluir de vez (só administrador; categoria inativa e sem produtos).
  function removeCategory(category) {
    runAction(
      category,
      'Excluir definitivamente a categoria “' + category.nome + '”?\n\nEsta ação não pode ser desfeita.',
      '/excluir',
      {},
      'Categoria “' + category.nome + '” excluída.',
      'Não foi possível excluir a categoria.'
    );
  }

  // Abrir, cancelar e fechar o diálogo (não fecha enquanto está salvando).
  $('open-category').addEventListener('click', () => {
    form.reset();
    setMode(null);
    message('');
    list.replaceChildren();
    csrf = '';
    ready = false;
    dialog.showModal();
    document.body.classList.add('category-open');
    name.focus();
    loadOptions();
  });
  // Cancelar: na edição volta ao cadastro de nova categoria; senão fecha o diálogo.
  $('cancel-category').addEventListener('click', () => {
    if (saving) return;
    if (editing) {
      setMode(null);
      name.focus();
    } else dialog.close();
  });
  dialog.addEventListener('cancel', event => {
    if (saving) event.preventDefault();
  });
  // Mantém o foco do teclado (Tab) dentro do diálogo.
  dialog.addEventListener('keydown', event => {
    if (event.key !== 'Tab') return;
    const controls = [...dialog.querySelectorAll('input,button')].filter(
      el => !el.disabled && el.getClientRects().length
    );
    if (!controls.length) {
      event.preventDefault();
      return;
    }
    const first = controls[0],
      last = controls[controls.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });
  // Fecha ao clicar fora da caixa (somente se o clique começou e terminou fora).
  let pointerOutside = false;
  dialog.addEventListener('pointerdown', event => {
    const r = dialog.getBoundingClientRect();
    pointerOutside =
      event.target === dialog &&
      (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom);
  });
  dialog.addEventListener('click', event => {
    const r = dialog.getBoundingClientRect();
    if (
      pointerOutside &&
      !saving &&
      event.target === dialog &&
      (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom)
    )
      dialog.close();
    pointerOutside = false;
  });
  dialog.addEventListener('close', () => {
    generation++;
    optionsController?.abort();
    document.body.classList.remove('category-open');
    $('open-category').focus();
  });
  name.addEventListener('input', () => fieldError(''));
  $('retry-category').addEventListener('click', loadOptions);

  // Salvar: valida o nome (obrigatório, até 100 caracteres) e cadastra ou edita pela API.
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (saving || !ready) return;
    const value = name.value.trim();
    if (!value) {
      fieldError('Informe o nome da categoria.');
      return;
    }
    if (value.length > 100) {
      fieldError('Use no máximo 100 caracteres.');
      return;
    }
    fieldError('');
    message('');
    saving = true;
    saveState();
    form.setAttribute('aria-busy', 'true');
    $('save-category').textContent = 'Salvando…';
    const editedId = editing?.id;
    try {
      const { ok, status, result } = await post(
        editedId ? 'categorias/' + encodeURIComponent(editedId) : 'categorias',
        { nome: value }
      );
      if (!ok) {
        if (result.fields?.nome) fieldError(result.fields.nome);
        else message(result.error || 'Não foi possível salvar a categoria.', status === 403);
        return;
      }
      if (!Number.isInteger(result.id) || typeof result.nome !== 'string') throw Error();
      if (editedId) {
        // Edição: continua no diálogo, volta ao modo de cadastro e atualiza a lista.
        setMode(null);
        notify('Categoria “' + result.nome + '” atualizada com sucesso.');
        loadOptions();
      } else {
        dialog.close();
        notify('Categoria “' + result.nome + '” cadastrada com sucesso.');
      }
    } catch (_) {
      message(
        'Não foi possível confirmar a gravação. Consulte as categorias antes de tentar novamente para evitar duplicidade.'
      );
    } finally {
      saving = false;
      form.setAttribute('aria-busy', 'false');
      $('save-category').textContent = 'Salvar';
      saveState();
      if (!$('category-error').hidden) name.focus();
    }
  });
})();
