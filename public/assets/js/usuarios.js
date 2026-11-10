'use strict';
/*
 * Listagem de usuários: indicadores, filtros, paginação e diálogo de detalhes.
 * Os dados vêm de /api/usuarios (public/api/index.php → UsuarioController → Usuario).
 * Só quem tem permissão no módulo "usuarios" (cargo ADMIN) consegue ver a lista.
 */
(() => {
  const $ = id => document.getElementById(id);
  // Caminho das imagens, vindo do data-img-url do <main>.
  const imgUrl = $('users-main').dataset.imgUrl;
  const url = new URLSearchParams(location.search);

  // Aviso verde quando a página foi aberta pelo cadastro (?usuario_salvo=id; editado=1 vem da edição).
  if (/^\d+$/.test(url.get('usuario_salvo') || '')) {
    $('saved-notice').textContent =
      url.get('editado') === '1' ? 'Usuário atualizado com sucesso.' : 'Usuário cadastrado com sucesso.';
    $('saved-notice').hidden = false;
  }

  // Filtros atuais; começam com o que estiver na URL (ex.: ao recarregar a página).
  const state = {
    q: url.get('q') || '',
    cargo: url.get('cargo') || '',
    situacao: url.get('situacao') || '',
    page: Math.max(1, Number(url.get('page')) || 1),
    pages: 1,
  };

  // ---------------------------------------------------------------------------
  // Formatação
  // ---------------------------------------------------------------------------

  // Telefone só com dígitos → (12) 98888-7777.
  function phone(value) {
    const d = String(value || '').replace(/\D/g, '');
    return d.length === 11
      ? d.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3')
      : d.length === 10
        ? d.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3')
        : value || '—';
  }

  // Data e hora do banco (AAAA-MM-DD HH:MM:SS) → DD/MM/AAAA HH:MM.
  function dateTime(value) {
    const m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec(value || '');
    if (!m) return '—';
    return `${m[3]}/${m[2]}/${m[1]}` + (m[4] ? ` ${m[4]}:${m[5]}` : '');
  }

  // ---------------------------------------------------------------------------
  // Filtro de perfil: acrescenta os cargos do banco que não estão fixos no HTML
  // ---------------------------------------------------------------------------

  async function loadRoles() {
    try {
      const response = await fetch(window.SGCB_API + 'usuarios/opcoes', { cache: 'no-store' });
      if (!response.ok) return;
      const data = await response.json();
      const existing = [...$('user-role').options].map(option => option.value);
      (data.cargos || []).forEach(cargo => {
        if (!existing.includes(cargo.codigo)) $('user-role').append(new Option(cargo.nome, cargo.codigo));
      });
      $('user-role').value = state.cargo;
    } catch (_) {
      // Sem a lista de cargos, o filtro continua com Administrador e Operador.
    }
  }

  // ---------------------------------------------------------------------------
  // Tabela
  // ---------------------------------------------------------------------------

  // Linha única com ícone e mensagem (carregando, vazio ou erro).
  function emptyRow(text) {
    const tr = document.createElement('tr'),
      td = document.createElement('td'),
      div = document.createElement('div'),
      img = document.createElement('img'),
      p = document.createElement('p');
    td.colSpan = 5;
    td.className = 'clients-empty';
    img.src = imgUrl + 'usuarios/sem-usuarios.svg';
    img.width = 32;
    img.height = 32;
    img.alt = '';
    p.textContent = text;
    div.append(img, p);
    td.append(div);
    tr.append(td);
    $('user-rows').replaceChildren(tr);
  }

  // Uma linha por usuário: nome, e-mail, perfil, status e botão "Ver usuário".
  function renderRows(items) {
    if (!items.length) {
      emptyRow('Nenhum usuário encontrado.');
      return;
    }
    $('user-rows').replaceChildren();
    items.forEach(user => {
      const tr = document.createElement('tr');
      [user.nome, user.email, user.cargo_nome || user.cargo_codigo].forEach((value, index) => {
        const td = document.createElement('td');
        td.textContent = value || '—';
        if (index === 2) td.className = 'user-profile-badge';
        tr.append(td);
      });
      // Etiqueta de status (verde ou vermelha).
      const statusCell = document.createElement('td'),
        badge = document.createElement('span');
      badge.className = 'users-status' + (user.ativo ? '' : ' inactive');
      badge.textContent = user.ativo ? 'Ativo' : 'Inativo';
      statusCell.append(badge);
      // Botão que abre o diálogo de detalhes.
      const actions = document.createElement('td'),
        button = document.createElement('button');
      button.type = 'button';
      button.className = 'view-client';
      button.textContent = 'Ver usuário';
      button.setAttribute('aria-label', 'Ver usuário ' + user.nome);
      button.addEventListener('click', () => showUser(user.id));
      actions.append(button);
      tr.append(statusCell, actions);
      $('user-rows').append(tr);
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
          loadUsers();
        });
      container.append(button);
    }
    $('previous-page').disabled = state.page <= 1;
    $('next-page').disabled = state.page >= state.pages;
  }

  // Cards de indicadores (resumo devolvido pela API).
  function renderStats(resumo) {
    $('metric-total').textContent = resumo.total;
    $('metric-ativos').textContent = resumo.ativos;
    $('metric-inativos').textContent = resumo.inativos;
  }

  // ---------------------------------------------------------------------------
  // Busca na API; uma nova busca cancela a anterior e respostas atrasadas são ignoradas (requestId).
  // ---------------------------------------------------------------------------

  let activeController = null,
    requestId = 0,
    debounce;
  async function loadUsers() {
    const id = ++requestId;
    if (activeController) activeController.abort();
    activeController = new AbortController();
    const controller = activeController,
      timeout = setTimeout(() => controller.abort(), 15000);
    $('users-main').setAttribute('aria-busy', 'true');
    $('retry-users').disabled = true;
    $('previous-page').disabled = true;
    $('next-page').disabled = true;
    // Mensagem padrão do aviso; troca pela da API quando o usuário não tem permissão (403).
    let message = 'Não foi possível carregar os usuários. Verifique a conexão com o banco e tente novamente.';
    try {
      // Monta ?q=&cargo=&situacao=&page= só com os filtros preenchidos.
      const params = new URLSearchParams();
      for (const [key, value] of Object.entries(state)) {
        if (key !== 'pages' && value !== '' && (key !== 'page' || value !== 1)) params.set(key, value);
      }
      const response = await fetch(window.SGCB_API + 'usuarios?' + params, {
        signal: controller.signal,
        cache: 'no-store',
        headers: { Accept: 'application/json' },
      });
      const data = await response.json().catch(() => null);
      // 403 = sem permissão (só administradores gerenciam usuários): mostra a mensagem da API.
      if (response.status === 403 && data?.error) message = data.error;
      if (!response.ok) throw Error('users-unavailable');
      if (!data || !Array.isArray(data.items) || !Number.isInteger(data.total) || !data.resumo) throw Error('invalid-users');
      if (id !== requestId) return;
      state.page = data.page;
      state.pages = data.pages;
      renderRows(data.items);
      renderPages();
      renderStats(data.resumo);
      $('page-summary').textContent = `${data.total} usuário(s) • página ${state.page} de ${state.pages}`;
      $('users-notice').hidden = true;
      // Guarda os filtros na URL (recarregar a página mantém a pesquisa).
      params.set('page', state.page);
      if (state.page === 1) params.delete('page');
      const search = params.toString();
      history.replaceState(null, '', location.pathname + (search ? '?' + search : ''));
    } catch (_) {
      if (id !== requestId) return;
      emptyRow('Não foi possível carregar os usuários.');
      $('users-message').textContent = message;
      $('users-notice').hidden = false;
      $('page-summary').textContent = 'Dados indisponíveis';
      $('page-numbers').replaceChildren();
    } finally {
      clearTimeout(timeout);
      if (id === requestId) {
        $('users-main').setAttribute('aria-busy', 'false');
        $('retry-users').disabled = false;
      }
    }
  }

  // ---------------------------------------------------------------------------
  // Eventos dos filtros e da paginação
  // ---------------------------------------------------------------------------

  // Lê os campos do formulário de filtros e volta para a página 1.
  function readFilters() {
    state.q = $('user-search').value.trim();
    state.cargo = $('user-role').value;
    state.situacao = $('user-status').value;
    state.page = 1;
  }
  $('user-search').value = state.q;
  $('user-role').value = state.cargo;
  $('user-status').value = state.situacao;

  // Enter na pesquisa.
  $('user-filters').addEventListener('submit', event => {
    event.preventDefault();
    clearTimeout(debounce);
    readFilters();
    loadUsers();
  });
  // Digitação: espera 350 ms sem digitar para não chamar a API a cada letra.
  $('user-search').addEventListener('input', () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
      readFilters();
      loadUsers();
    }, 350);
  });
  // Selects de perfil e status pesquisam na hora.
  ['user-role', 'user-status'].forEach(id =>
    $(id).addEventListener('change', () => {
      clearTimeout(debounce);
      readFilters();
      loadUsers();
    })
  );
  // Botão Limpar.
  $('clear-user-filters').addEventListener('click', () => {
    clearTimeout(debounce);
    $('user-filters').reset();
    Object.assign(state, { q: '', cargo: '', situacao: '', page: 1 });
    loadUsers();
    $('user-search').focus();
  });
  // Página anterior / próxima.
  $('previous-page').addEventListener('click', () => {
    if (state.page > 1) {
      state.page--;
      loadUsers();
    }
  });
  $('next-page').addEventListener('click', () => {
    if (state.page < state.pages) {
      state.page++;
      loadUsers();
    }
  });
  $('retry-users').addEventListener('click', loadUsers);

  // ---------------------------------------------------------------------------
  // Diálogo "Dados do usuário" (GET /api/usuarios/{id}) com o botão Ativar/Inativar
  // ---------------------------------------------------------------------------

  let detailRequest = 0;
  async function showUser(id) {
    const current = ++detailRequest;
    const container = $('user-detail');
    container.textContent = 'Carregando usuário…';
    if (!$('user-dialog').open) $('user-dialog').showModal();
    try {
      const response = await fetch(window.SGCB_API + 'usuarios/' + encodeURIComponent(id), { cache: 'no-store' });
      if (!response.ok) throw Error();
      const user = await response.json();
      if (current !== detailRequest || !$('user-dialog').open) return;
      container.replaceChildren();
      // Lista de rótulo/valor (mesmo visual do diálogo de clientes).
      const dl = document.createElement('dl');
      dl.className = 'detail-grid';
      [
        ['Usuário', user.nome],
        ['E-mail', user.email],
        ['Telefone', phone(user.telefone)],
        ['Perfil de acesso', user.cargo_nome || user.cargo_codigo],
        ['Status', user.ativo ? 'Ativo' : 'Inativo'],
        ['Troca de senha pendente', user.deve_alterar_senha ? 'Sim' : 'Não'],
        ['Último acesso', dateTime(user.ultimo_acesso)],
        ['Cadastro', dateTime(user.criado_em)],
      ].forEach(([label, value]) => {
        const field = document.createElement('div'),
          dt = document.createElement('dt'),
          dd = document.createElement('dd');
        dt.textContent = label;
        dd.textContent = value || '—';
        field.append(dt, dd);
        dl.append(field);
      });
      container.append(dl);
      // Ações (acoes.js): Editar abre o cadastro preenchido (cadastro.php?id=);
      // Ativar/Inativar grava na hora em /api/usuarios/{id}/status e atualiza a lista.
      // A API não deixa o usuário logado inativar a si mesmo nem inativar um administrador (cargo ADMIN):
      // por segurança, para administrador ativo o botão "Inativar" nem aparece.
      const adminAtivo = user.cargo_codigo === 'ADMIN' && user.ativo;
      const acoes = [{ texto: 'Editar usuário', classe: 'primaria', href: 'cadastro.php?id=' + encodeURIComponent(id) }];
      if (!adminAtivo)
        acoes.unshift({
          texto: user.ativo ? 'Inativar' : 'Reativar',
          classe: user.ativo ? 'perigo' : '',
          confirmar: user.ativo ? 'Inativar ' + user.nome + '? Ele não poderá mais entrar no sistema.' : null,
          acao: async () => {
            await SGCBAcoes.executar('usuarios/' + encodeURIComponent(id) + '/status', { ativo: !user.ativo });
            loadUsers();
            showUser(id);
          },
        });
      else {
        const aviso = document.createElement('p');
        aviso.className = 'user-admin-note';
        aviso.textContent = 'Administrador: por segurança, não pode ser inativado.';
        container.append(aviso);
      }
      SGCBAcoes.barra(container, acoes);
    } catch (_) {
      if (current === detailRequest) {
        container.textContent = 'Não foi possível abrir o usuário. Feche esta janela e tente novamente.';
      }
    }
  }
  // Fechar o diálogo invalida uma resposta que ainda esteja a caminho.
  $('user-dialog').addEventListener('close', () => {
    detailRequest++;
  });

  loadRoles();
  loadUsers();
})();
