<?php
/**
 * nav.php — menu lateral (navegação) de todas as páginas da área administrativa do SGCB.
 *
 * Finalidade: ponto único da navegação: logotipo, links dos módulos, usuário logado,
 *             botão Sair e o diálogo de aviso usado pelo menu. Para criar/renomear um item
 *             do menu, altere só o array $itensMenu abaixo.
 * Depende de: src/bootstrap.php (BASE_URL, Autenticacao) e Autenticacao::exigirLogin() já chamado
 *             pela página; ícones de public/assets/img/nav/; public/assets/css/dashboard.css
 *             (seção "Menu lateral") e public/assets/js/painel.js (abrir/fechar menu e diálogos).
 * Usado por:  páginas de public/admin/ (ex.: public/admin/dashboard.php), logo após includes/head.php.
 *
 * Variáveis esperadas (definidas pela página antes do require):
 *   @var string $paginaAtual Chave do item ativo no menu, ex.: 'visao-geral', 'clientes'.
 */

$paginaAtual = $paginaAtual ?? '';
$usuario     = Autenticacao::usuario();

/*
 * Itens do menu, na ordem em que aparecem.
 *   rotulo  → texto do link
 *   destino → caminho a partir de public/ (é o mesmo usado na URL, após a BASE_URL)
 *   icone   → arquivo em public/assets/img/nav/, com largura e altura originais
 * Se o arquivo de destino ainda não existir, o item vira um botão que abre o aviso
 * "em desenvolvimento" (painel.js); quando a página for criada, ele vira link sozinho.
 */
$itensMenu = [
    'visao-geral' => ['rotulo' => 'Visão Geral',          'destino' => 'admin/dashboard.php',       'icone' => ['visao-geral.svg', 18, 18]],
    'clientes'    => ['rotulo' => 'Clientes',             'destino' => 'admin/clientes/index.php',    'icone' => ['clientes.svg', 18, 18]],
    'produtos'    => ['rotulo' => 'Produtos',             'destino' => 'admin/produtos/index.php',    'icone' => ['produtos.svg', 18, 18]],
    'locacoes'    => ['rotulo' => 'Contratos de Locação', 'destino' => 'admin/locacoes/index.php',    'icone' => ['contratos.svg', 18, 18]],
    'manutencoes' => ['rotulo' => 'Manutenções',          'destino' => 'admin/manutencoes/index.php', 'icone' => ['manutencoes.png', 22, 21]],
    'financeiro'  => ['rotulo' => 'Financeiro',           'destino' => 'admin/financeiro/index.php',  'icone' => ['financeiro.svg', 30, 19]],
];

// Caminhos já escapados para uso direto nos atributos.
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
    $icone = '<img src="' . $iconesNav . $arquivoIcone . '" alt="" width="' . $larguraIcone . '" height="' . $alturaIcone . '">';
    $ativo = $chave === $paginaAtual;

    // Página já existe em public/? Então é link; senão, botão de "em desenvolvimento".
    if (is_file(__DIR__ . '/../public/' . $item['destino'])): ?>
      <a class="nav-link<?= $ativo ? ' active' : '' ?>" href="<?= htmlspecialchars(BASE_URL . $item['destino']) ?>"<?= $ativo ? ' aria-current="page"' : '' ?>><?= $icone ?><?= htmlspecialchars($item['rotulo']) ?></a>
<?php else: ?>
      <button class="nav-link" type="button" data-module="<?= htmlspecialchars($item['rotulo']) ?>"><?= $icone ?><?= htmlspecialchars($item['rotulo']) ?></button>
<?php endif;
endforeach; ?>
    </nav>
    <!-- Usuário logado (dados da sessão gravados por Autenticacao::entrar) e botão Sair -->
    <div class="user-box"><button class="user-profile" id="open-profile" type="button" aria-haspopup="dialog"
        data-name="<?= htmlspecialchars($usuario['nome']) ?>" data-email="<?= htmlspecialchars($usuario['email']) ?>"
        data-role="<?= htmlspecialchars($usuario['cargo_nome']) ?>"><span class="avatar"><?= htmlspecialchars($inicial) ?></span><span class="user-info"><strong><?= htmlspecialchars($usuario['nome']) ?></strong><span><?= htmlspecialchars($usuario['cargo_codigo']) ?></span></span></button><a class="logout" href="<?= $urlSair ?>"><img src="<?= $iconesNav ?>sair.svg" alt="" width="16" height="16">Sair</a></div>
  </aside>
  <!-- Diálogo de aviso (módulo em desenvolvimento, perfil do usuário), aberto pelo painel.js -->
  <dialog class="info-dialog" id="info-dialog" aria-labelledby="dialog-title"><h2 id="dialog-title"></h2><p id="dialog-description"></p><form method="dialog"><button class="primary-button">Entendi</button></form></dialog>
