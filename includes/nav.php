<?php
/**
 * Menu lateral das páginas do admin: logotipo, módulos, usuário logado e botão Sair.
 * Para criar ou renomear um item, altere só o array $itensMenu. A página precisa ter
 * chamado Autenticacao::exigirLogin() antes.
 *
 * @var string $paginaAtual Chave do item ativo no menu, ex.: 'visao-geral', 'clientes'.
 */

$paginaAtual = $paginaAtual ?? '';
$usuario     = Autenticacao::usuario();

/*
 * Itens do menu, na ordem em que aparecem.
 *   rotulo:  texto do link
 *   destino: caminho a partir de public/ (o mesmo da URL, depois da BASE_URL)
 *   icone:   arquivo em public/assets/img/nav/, com largura e altura
 * Enquanto o arquivo de destino não existir, o item é um botão que abre o aviso
 * "em desenvolvimento" (painel.js); quando a página for criada, vira link sozinho.
 */
$itensMenu = [
    'visao-geral' => ['rotulo' => 'Visão Geral',          'destino' => 'admin/dashboard.php',       'icone' => ['visao-geral.svg', 18, 18]],
    'clientes'    => ['rotulo' => 'Clientes',             'destino' => 'admin/clientes/index.php',    'icone' => ['clientes.svg', 18, 18]],
    'produtos'    => ['rotulo' => 'Produtos',             'destino' => 'admin/produtos/index.php',    'icone' => ['produtos.svg', 18, 18]],
    'locacoes'    => ['rotulo' => 'Contratos de Locação', 'destino' => 'admin/locacoes/index.php',    'icone' => ['contratos.svg', 18, 18]],
    'manutencoes' => ['rotulo' => 'Manutenções',          'destino' => 'admin/manutencoes/index.php', 'icone' => ['manutencoes.png', 22, 21]],
    'financeiro'  => ['rotulo' => 'Financeiro',           'destino' => 'admin/financeiro/index.php',  'icone' => ['financeiro.svg', 30, 19]],
    'usuarios'    => ['rotulo' => 'Usuários',             'destino' => 'admin/usuarios/index.php',    'icone' => ['usuarios.svg', 18, 18]],
];

// Caminhos já escapados para os atributos.
$iconesNav = htmlspecialchars(BASE_URL . 'assets/img/nav/');
$urlInicio = htmlspecialchars(BASE_URL . 'admin/dashboard.php');
$urlSair   = htmlspecialchars(BASE_URL . 'logout.php?csrf=' . urlencode(Autenticacao::tokenCsrf()));

// Inicial do nome para o avatar.
$inicial = mb_strtoupper(mb_substr($usuario['nome'] ?: $usuario['email'], 0, 1));
?>
  <button class="nav-backdrop" id="nav-backdrop" type="button" aria-label="Fechar menu" tabindex="-1" hidden></button>
  <aside class="sidebar" id="primary-sidebar" aria-label="Menu principal">
    <!-- Logotipo (volta para a Visão Geral) e botão × do menu no celular -->
    <div class="brand-wrap"><a class="brand" href="<?= $urlInicio ?>" aria-label="Casa Barretto — Visão Geral"><span class="brand-name">CASA<br>BARRETTO</span><span class="brand-description">Gestão de Locações</span></a><button class="close-menu" id="close-menu" type="button" aria-label="Fechar menu">×</button></div>
    <nav class="navigation" aria-label="Navegação do sistema">
<?php foreach ($itensMenu as $chave => $item):
    [$arquivoIcone, $larguraIcone, $alturaIcone] = $item['icone'];
    // A caixa .nav-icon tem largura fixa e centraliza o ícone: ícones de tamanhos diferentes
    // (ex.: financeiro 30x19) ficam no mesmo eixo e os textos começam todos no mesmo ponto.
    $icone = '<span class="nav-icon"><img src="' . $iconesNav . $arquivoIcone . '" alt="" width="' . $larguraIcone . '" height="' . $alturaIcone . '"></span>';
    $ativo = $chave === $paginaAtual;

    if (is_file(__DIR__ . '/../public/' . $item['destino'])): ?>
      <a class="nav-link<?= $ativo ? ' active' : '' ?>" href="<?= htmlspecialchars(BASE_URL . $item['destino']) ?>"<?= $ativo ? ' aria-current="page"' : '' ?>><?= $icone ?><?= htmlspecialchars($item['rotulo']) ?></a>
<?php else: ?>
      <button class="nav-link" type="button" data-module="<?= htmlspecialchars($item['rotulo']) ?>"><?= $icone ?><?= htmlspecialchars($item['rotulo']) ?></button>
<?php endif;
endforeach; ?>
    </nav>
    <!-- Usuário logado (dados da sessão gravados por Autenticacao::entrar) e botão Sair -->
    <!-- Clicar no usuário abre o menu do perfil (painel.js): Perfil, Tema, Cor de destaque e Sair.
         A escolha fica salva no navegador e é aplicada pelo script do head.php. -->
    <div class="profile-menu" id="profile-menu" role="menu" aria-label="Menu do perfil" hidden>
      <button class="profile-menu-item" type="button" role="menuitem" id="profile-info"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>Perfil</button>
<?php require __DIR__ . '/opcoes-tema.php'; ?>
      <a class="profile-menu-item danger" role="menuitem" href="<?= $urlSair ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>Sair</a>
    </div>
    <div class="user-box"><button class="user-profile" id="open-profile" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="profile-menu"
        data-name="<?= htmlspecialchars($usuario['nome']) ?>" data-email="<?= htmlspecialchars($usuario['email']) ?>"
        data-role="<?= htmlspecialchars($usuario['cargo_nome']) ?>"><span class="avatar"><?= htmlspecialchars($inicial) ?></span><span class="user-info"><strong><?= htmlspecialchars($usuario['nome']) ?></strong><span><?= htmlspecialchars($usuario['cargo_nome']) ?></span></span></button><a class="logout" href="<?= $urlSair ?>"><img src="<?= $iconesNav ?>sair.svg" alt="" width="16" height="16">Sair</a></div>
  </aside>
  <!-- Diálogo de aviso (módulo em desenvolvimento, perfil do usuário), aberto pelo painel.js -->
  <dialog class="info-dialog" id="info-dialog" aria-labelledby="dialog-title"><h2 id="dialog-title"></h2><p id="dialog-description"></p><form method="dialog"><button class="primary-button">Entendi</button></form></dialog>
