# SGCB — Sistema de Gestão de Containers Barreto

Sistema web para a **Casa Barretto** gerenciar a locação de contêineres: clientes, produtos (modelos e unidades físicas), contratos de locação, manutenções e financeiro.

Projeto Integrador desenvolvido pela equipe **Bemfa Developers**:

<p align="center">
  <img src="docs/img/bemfa.png" alt="Logo Bemfa Developers" width="100%" height="252">
</p>

<!-- Integrantes: foto e nome levam ao LinkedIn de cada um.
     As fotos são PNGs recortados em círculo (o GitHub não aplica CSS no README). -->
<table align="center">
  <tr>
    <td align="center">
      <a href="https://www.linkedin.com/in/alex-silva-a758a6317/"><img src="docs/img/equipe/alex.png" width="90" alt="Foto de Alex Silva"><br><sub><b>Alex Silva</b></sub></a>
    </td>
    <td align="center">
      <a href="https://www.linkedin.com/in/bruno-gabriel-ribeiro-dos-santos-690031375/"><img src="docs/img/equipe/bruno.png" width="90" alt="Foto de Bruno Gabriel"><br><sub><b>Bruno Gabriel</b></sub></a>
    </td>
    <td align="center">
      <a href="https://www.linkedin.com/in/eduardoscouto/"><img src="docs/img/equipe/eduardo.png" width="90" alt="Foto de Eduardo Sales"><br><sub><b>Eduardo Sales</b></sub></a>
    </td>
    <td align="center">
      <a href="https://www.linkedin.com/in/fabr%C3%ADcio-teodoro-0242b0264/"><img src="docs/img/equipe/fabricio.png" width="90" alt="Foto de Fabricio Teodoro"><br><sub><b>Fabricio Teodoro</b></sub></a>
    </td>
    <td align="center">
      <a href="https://www.linkedin.com/in/mauroyida/"><img src="docs/img/equipe/mauro.png" width="90" alt="Foto de Mauro Yida"><br><sub><b>Mauro Yida</b></sub></a>
    </td>
  </tr>
</table>

---

## Sumário

1. [Situação atual](#situação-atual)
2. [O que cada branch implementou](#o-que-cada-branch-implementou)
3. [Linguagens, frameworks e ferramentas](#linguagens-frameworks-e-ferramentas)
4. [Estrutura de pastas](#estrutura-de-pastas)
5. [Banco de dados](#banco-de-dados)
6. [Como rodar o projeto](#como-rodar-o-projeto)
7. [Fluxo de trabalho no Git](#fluxo-de-trabalho-no-git)

---

## Situação atual

> Atualizado em 01/11/2026 (branch `feature/funcoes`: back-end completo das 27 tabelas, API e edição nas telas).

### Já integrado (front-end + back-end funcionando)

| Funcionalidade | Detalhes |
|---|---|
| **Back-end das 27 tabelas** | Regras de negócio e acesso ao banco em `src/Models/` (uma classe por assunto), chamadas pela API em `src/Controllers/` + `public/api/index.php`. Ver [Como o back-end está organizado](#como-o-back-end-está-organizado) e [Regras de negócio aplicadas](#regras-de-negócio-aplicadas). |
| **Telas lendo e gravando no banco** | Dashboard, Clientes, Produtos (e categorias), Manutenções, Financeiro e Usuários carregam e salvam pela API. |
| **Edição e ações** | Editar cliente e produto, inativar/reativar, ajustar estoque, iniciar/concluir/cancelar manutenção, registrar pagamento, estornar e cancelar parcela. |
| **Auditoria** | Toda gravação registra quem fez, o quê e quando na tabela `auditoria`. |
| **Permissões** | Toda gravação confere o token CSRF e a permissão do usuário (`Usuario::temPermissao`); o cargo ADMIN pode tudo. |
| **Upload de arquivos** | Foto e anexos do cliente vão para `public/uploads/AAAA/MM/` com nome aleatório; o tipo é conferido pelo conteúdo (não pela extensão). |
| **Dados de exemplo** | `database/seeds/003_dados_exemplo.sql` preenche todas as telas com dados fictícios coerentes. |
| **Login com banco de dados** | E-mail e senha validados na tabela `usuarios` (somente usuário ativo), com proteção CSRF e nova sessão a cada login. |
| **Senhas com hash** | A senha do seed pode estar em texto puro; no primeiro login ela é convertida automaticamente para hash bcrypt (`password_hash` / `password_verify`). |
| **Sessão e páginas protegidas** | Páginas de `public/admin/` exigem login (`Autenticacao::exigirLogin()`). A cada página, o usuário da sessão é conferido no banco: se ele foi apagado, desativado ou se o banco estiver fora do ar, a sessão é encerrada e a pessoa volta para o login com o motivo. |
| **Logout** | Botão "Sair" encerra a sessão (com token CSRF na URL). |
| **Redirecionamento da raiz** | `http://localhost/SGCB/` leva para o dashboard (sessão confirmada no banco) ou para o login. |
| **Menu lateral único** | `includes/nav.php` monta a navegação de todas as páginas do admin a partir de um único array; o item da página atual fica destacado e módulos sem página abrem o aviso "em desenvolvimento". |
| **Usuário logado no menu** | Nome, cargo e inicial do avatar vêm da sessão. |
| **Cache de CSS/JS** | Os arquivos são carregados com `?v=<data de modificação>`, então o navegador sempre pega a versão nova. |
| **Favicon** | `includes/head.php` aplica o ícone `img/logo/favicon.svg` em todas as páginas. |

### Telas ligadas ao back-end (`feature/funcoes`)

| Tela | Arquivo | API usada |
|---|---|---|
| Visão Geral (dashboard) | `public/admin/dashboard.php` | `/api/dashboard` — indicadores, gráficos, rankings e últimas locações |
| Listagem de clientes | `public/admin/clientes/index.php` | `/api/clientes` — lista, filtros, paginação e detalhes |
| Cadastro/edição de cliente | `public/admin/clientes/cadastro.php` (`?id=` edita) | `/api/cadastros/opcoes`, `/api/clientes` (POST, novo) e `/api/clientes/{id}` (GET carrega, POST salva a edição) |
| Listagem de produtos | `public/admin/produtos/index.php` | `/api/produtos` — lista, filtros por status/categoria, paginação, estoque e detalhes |
| Nova categoria (diálogo da tela de Produtos) | `public/admin/produtos/index.php` | `/api/categorias/opcoes` e `/api/categorias` (POST) — token CSRF e salvamento |
| Cadastro/edição de produto | `public/admin/produtos/cadastro.php` (`?id=` edita) | `/api/produtos/opcoes`, `/api/produtos` (POST, novo: gera as unidades físicas) e `/api/produtos/{id}` (GET carrega, POST salva a edição) |
| Manutenções | `public/admin/manutencoes/index.php` | `/api/manutencoes` e `/api/manutencoes/{id}` — indicadores, lista, filtros, localidades, paginação e detalhes |
| Cadastro de manutenção | `public/admin/manutencoes/cadastro.php` | `/api/manutencoes/opcoes` e `/api/manutencoes` (POST) — contêineres, tipos, prioridades, responsáveis, token CSRF e salvamento |
| Financeiro | `public/admin/financeiro/index.php` | `/api/financeiro` e `/api/financeiro/parcelas/{id}` — indicadores, parcelas, filtros, paginação, detalhes e histórico de pagamentos |
| Listagem de usuários | `public/admin/usuarios/index.php` | `/api/usuarios` e `/api/usuarios/{id}` — indicadores (total, ativos, inativos), pesquisa por nome/e-mail, filtros de perfil e status, paginação e detalhes |
| Cadastro/edição de usuário | `public/admin/usuarios/cadastro.php` (`?id=` edita) | `/api/usuarios/opcoes` (cargos e token CSRF), `/api/usuarios` (POST, novo: senha inicial com hash bcrypt, status inicial, troca de senha no 1º acesso e perfil) e `/api/usuarios/{id}` (GET carrega, POST salva nome, e-mail, telefone e perfil; se o status mudou, chama `/api/usuarios/{id}/status`) |

**Edição e ações** (botões no diálogo de detalhes de cada listagem, montados pelo `public/assets/js/acoes.js`):

| Tela | O que dá para fazer |
|---|---|
| Clientes | **Editar cadastro** (abre `cadastro.php?id=` preenchido; foto nova substitui, anexos novos somam), **Inativar/Reativar** |
| Produtos | **Editar produto** (`cadastro.php?id=`: dados, preços e limites), **Ajustar estoque** (+ cria contêineres, − inativa os livres), **Inativar/Reativar** |
| Manutenções | **Iniciar**, **Concluir** (custo e responsável externo) e **Cancelar** |
| Financeiro | **Registrar pagamento** (forma, valor e data; parcial ou total), **Estornar** um pagamento, **Cancelar parcela** sem pagamento |
| Usuários | **Editar usuário** (abre `cadastro.php?id=` preenchido; a senha não é alterada na edição), **Inativar/Reativar** (a API não deixa o usuário logado inativar a si mesmo; inativar encerra os "lembrar de mim" dele) |

Os scripts chamam a API por `window.SGCB_API` (definido em `includes/footer.php` como `BASE_URL . 'api/'`, ex.: `/SGCB/api/`), então funcionam com qualquer nome de pasta. O `footer.php` também entrega `window.SGCB_BASE` (endereço do site, para exibir as fotos enviadas) e `window.SGCB_CSRF` (token usado pelas ações dos diálogos).

### Como o back-end está organizado

Uma requisição percorre três camadas:

```
tela (fetch em public/assets/js)
  → public/api/index.php        entrada única: tabela de rotas, respostas em JSON e tratamento de erros
  → src/Controllers/*.php        intermediário: exige login, confere token CSRF e permissão, lê os dados enviados
  → src/Models/*.php             regras de negócio e SQL das 27 tabelas
  → banco sgcb
```

O padrão segue o CRUD de base do curso (`seguranca.php`, `salvar.php`, `atualizar.php`, `excluir.php`): `Api::usuario()` faz o papel do `seguranca.php`, `Api::verificarToken()` do `verificarToken()` e `Api::permitir()` do `apenasAdmin()`. Só GET (ler) e POST (gravar) são usados; toda gravação exige o token CSRF no cabeçalho `X-CSRF-Token`. Clientes, usuários e produtos não são apagados: o "excluir" é inativar, porque contratos e parcelas apontam para eles.

Erros saem sempre no mesmo formato: `{ "error": "mensagem", "fields": { "campo": "mensagem" } }`, com o status HTTP certo (401 sem login, 403 sem token/permissão, 404 não encontrado, 405 método errado, 409 conflito — duplicado ou indisponível —, 422 dados inválidos, 503 banco fora do ar). Os detalhes técnicos de erros inesperados vão só para o log do Apache, nunca para a tela.

| Controller | Rotas (`api/...`) |
|---|---|
| `DashboardController` | `GET dashboard` |
| `ClienteController` | `GET clientes`, `GET clientes/{id}`, `GET cadastros/opcoes`, `POST clientes`, `POST clientes/{id}`, `POST clientes/{id}/status` |
| `CategoriaController` | `GET categorias/opcoes`, `POST categorias`, `POST categorias/{id}`, `POST categorias/{id}/status` |
| `ProdutoController` | `GET produtos`, `GET produtos/opcoes`, `GET produtos/{id}`, `POST produtos`, `POST produtos/{id}`, `POST produtos/{id}/status`, `POST produtos/{id}/estoque` |
| `ManutencaoController` | `GET manutencoes`, `GET manutencoes/opcoes`, `GET manutencoes/{id}`, `POST manutencoes`, `POST manutencoes/{id}/iniciar`, `.../concluir`, `.../cancelar` |
| `FinanceiroController` | `GET financeiro`, `GET financeiro/parcelas/{id}`, `POST financeiro/parcelas/{id}/pagamentos`, `.../ajuste`, `.../cancelar`, `POST financeiro/pagamentos/{id}/estorno`, `GET financeiro/pagamentos/{id}/recibo` |
| `ContratoController` | `GET contratos`, `GET contratos/opcoes`, `GET contratos/{id}`, `POST contratos/calcular`, `POST contratos`, `POST contratos/{id}/gerar`, `.../iniciar`, `.../finalizar`, `.../cancelar` |
| `UsuarioController` | `GET usuarios`, `GET usuarios/opcoes`, `GET usuarios/{id}`, `POST usuarios`, `POST usuarios/{id}`, `POST usuarios/{id}/status`, `POST conta/senha` |
| `EmpresaController` | `GET empresa`, `POST empresa`, `POST empresa/preferencias`, `POST condicoes-pagamento` |

Os models (`src/Models/`), chamados pelos controllers:

| Classe | Tabelas | Principais funções |
|---|---|---|
| `Modelo` (base) | — | transação (com savepoint), paginação `{items, total, page, pages}`, leitura/validação dos campos, auditoria |
| `ErroValidacao` | — | erro com mensagem, erros por campo e status HTTP (422, 404, 409), no formato `{error, fields}` das telas |
| `Usuario`, `SessaoPersistente` | usuarios, cargos, modulos, permissoes, cargo_permissoes, usuario_permissoes, recuperacoes_senha, sessoes_usuario | `listarUsuarios` (filtros e totais dos cards), `cadastrarUsuario` (status e troca de senha opcionais), `alterarStatus`, `alterarSenha`, `gerarRecuperacaoSenha`, `redefinirSenhaComToken`, `temPermissao`, `listarResponsaveis`; "lembrar de mim" (`criar`, `validar`, `revogar`) |
| `Cliente`, `Arquivo` | pessoas, enderecos_pessoa, pessoa_anexos, arquivos | `listarClientes`, `buscarCliente`, `cadastrarCliente` (endereços, foto e anexos), `atualizarDados`, `inativarCliente`, `listarFornecedores`, `validarCpfCnpj`; uploads em `public/uploads/AAAA/MM/` |
| `CondicaoPagamento`, `Empresa` | condicoes_pagamento, empresas, preferencias_contrato | condições ativas, dados da empresa, preferências e numeração automática (`reservarNumero`) |
| `Categoria`, `Produto`, `UnidadeFisica` | categorias, produtos, precos_produto, unidades_fisicas, agenda_unidades | `listarProdutos`, `buscarProduto`, `cadastrarProduto` (gera as unidades), `atualizarQuantidade`, `verificarDisponibilidade` por período, `alterarStatusProduto` |
| `Manutencao` | manutencoes, agenda_unidades | `listarManutencoes` (com indicadores), `opcoes`, `cadastrarManutencao`, `iniciarManutencao`, `concluirManutencao`, `cancelarManutencao` |
| `Contrato` | contratos, contrato_itens, agenda_unidades | `calcularValorTotal`, `criarContrato` (rascunho), `gerarContrato` (reserva contêineres, numera, snapshots e parcelas), `iniciarLocacao`, `finalizarContrato`, `cancelarContrato` |
| `Parcela` | parcelas | `listarParcelas` (tela Financeiro, com indicadores), `buscarParcela`, `aplicarAjuste`, `cancelarParcela`; status e prioridade calculados |
| `Pagamento` (abstrata) e `PagamentoPix`, `PagamentoCartao`, `PagamentoDinheiro`, `PagamentoBoleto`, `PagamentoTransferencia` | pagamentos | `processarPagamento`, `verificarStatusPagamento`, `estornar`, `emitirRecibo`, `criarPorForma` |
| `Dashboard`, `Auditoria` | todas / auditoria | `Dashboard::resumo()` (Visão Geral); `Auditoria::listar` (histórico de um registro) |

Os nomes dos métodos do diagrama de classes foram mantidos (`cadastrarCliente`, `atualizarDados`, `inativarCliente`, `verificarDisponibilidade`, `atualizarQuantidade`, `alterarStatusProduto`, `calcularValorTotal`, `gerarContrato`, `cancelarContrato`, `finalizarContrato`, `processarPagamento`, `emitirRecibo`, `verificarStatusPagamento`), agora gravando no banco. A classe *Administrador* do diagrama virou `Usuario` (os cargos definem o acesso).

### Regras de negócio aplicadas

**Gerais**
- Toda gravação acontece em **transação**: se qualquer parte falhar, nada fica gravado pela metade (operações dentro de outras usam *savepoint*).
- Dinheiro é calculado em **centavos** (inteiros), para não acumular erro de arredondamento; no banco fica `DECIMAL`.
- Datas usam o fuso **America/Sao_Paulo** (definido no `src/bootstrap.php`; o MySQL recebe o mesmo fuso na conexão).
- Nada importante é apagado: clientes, produtos, usuários e categorias são **inativados**; pagamentos são **estornados** (ficam no histórico). Contratos e parcelas continuam apontando para eles.
- Os dados enviados são validados de novo no servidor (o navegador pode ser burlado): campos obrigatórios, tamanhos, datas reais, CPF/CNPJ com dígitos verificadores, e-mail, CEP e UF.

**Clientes e fornecedores** (`Cliente`)
- CPF/CNPJ e telefone são gravados **só com números**; o tipo de pessoa (física/jurídica) sai do tamanho do documento. CPF/CNPJ não pode repetir (409).
- Exigem pelo menos um endereço completo e **exatamente um principal**. Na edição, os endereços enviados substituem os atuais (os antigos são desativados, não apagados).
- Fornecedores exclusivos não aparecem na listagem de clientes; o tipo AMBOS aparece nas duas.

**Produtos e estoque** (`Produto`, `UnidadeFisica`)
- O produto é o **modelo**; cada contêiner real é uma **unidade física** com código próprio (`C20-ESC-001`, `C20-ESC-002`...), gerada automaticamente na quantidade informada.
- O estoque mostrado é a contagem das unidades por estado: disponível, locada, em manutenção, inativa e reservada.
- **Disponibilidade por período**: uma unidade está livre se não está inativa nem em manutenção e se a agenda (`agenda_unidades`) não tem reserva, uso ou manutenção que cruze as datas pedidas. Ao reservar, as unidades do produto ficam travadas até o fim da transação, para dois contratos não pegarem o mesmo contêiner.
- Retirar do estoque só inativa unidades disponíveis e sem reservas futuras.

**Contratos** (`Contrato`)
- Ciclo: **rascunho** (`criarContrato`) → **ativo** (`gerarContrato`) → **finalizado** (`finalizarContrato`) ou **cancelado** (`cancelarContrato`).
- Valor de cada item = **preço da periodicidade × quantidade × número de períodos − desconto + outros**. Períodos = dias de locação ÷ dias da periodicidade (diária 1, semanal 7, quinzenal 15, mensal 30), no mínimo 1. Dias = data final − data inicial (mínimo 1); com "dias úteis" nas preferências, só contam segunda a sexta. Prazo indeterminado cobra um período por parcela.
- Total a receber = itens + frete + outros valores (a caução fica à parte).
- Ao gerar: confere cliente ativo, **limite de crédito** (saldo em aberto + este contrato, se o cliente não puder ultrapassar), estoque no período; reserva os contêineres; numera (`CT-000001`, `LOC-000001`, numeração das preferências da empresa); guarda **cópia dos dados** do cliente, da empresa e do endereço de entrega (o contrato não muda se o cadastro mudar); e gera as parcelas.
- Contrato que começa hoje ou antes já sai **em uso** (contêineres LOCADA); futuro fica **reservado** até `iniciarLocacao`.
- Finalizar devolve os contêineres (as parcelas em aberto continuam devidas). Cancelar libera os contêineres e cancela as parcelas sem pagamento.

**Parcelas e pagamentos** (`Parcela`, `Pagamento`)
- O total é dividido igualmente; os centavos que sobram vão para a última parcela. 1º vencimento = início + dias da condição de pagamento; as demais seguem o intervalo da condição (ou a periodicidade).
- Valor devido = valor original + acréscimo − desconto. O **status** é calculado pelos pagamentos não estornados: **Paga**, **Em atraso** (vencida com saldo), **Parcial**, **Em aberto** ou **Cancelada**.
- **Prioridade** (só com saldo): urgente = vencida; alta = vence em até 7 dias; média = até 30; baixa = depois.
- Pagamento precisa ser maior que zero e **não pode passar do saldo** da parcela; a data não pode ser no futuro. Cada forma é uma classe filha de `Pagamento` (Pix, cartão de crédito/débito, dinheiro, boleto, transferência). O cartão não guarda o número: só os 4 últimos dígitos, na auditoria. Dinheiro calcula o troco.
- Estorno exige motivo; o pagamento estornado deixa de contar no saldo e não tem recibo.

**Manutenções** (`Manutencao`)
- Ciclo: **agendada** → **em andamento** (contêiner em MANUTENCAO e bloqueado na agenda) → **concluída** (custo, contêiner volta a DISPONIVEL e registra a vistoria) ou **cancelada**.
- Não deixa agendar manutenção igual já aberta no mesmo contêiner (409), nem em dia em que o contêiner está reservado ou locado.

**Usuários e acesso** (`Usuario`, `SessaoPersistente`)
- Senha nova: 8 ou mais caracteres, com letras e números; trocar a senha encerra os "lembrar de mim" abertos.
- Recuperação de senha por token de uso único com validade de 1 hora (no banco fica só o hash do token).
- Permissão: cargo ADMIN pode tudo; os outros cargos seguem `cargo_permissoes`, com exceções individuais em `usuario_permissoes`. Ninguém pode desativar o próprio usuário.

### Como usar as funções

**No PHP** (ex.: em um script ou em um controller novo). O `src/bootstrap.php` carrega as classes sozinho:

```php
require __DIR__ . '/../src/bootstrap.php';

// Ler: as listagens devolvem { items, total, page, pages }
$clientes = Cliente::listarClientes(['situacao' => 'ativos', 'page' => 1]);

// Gravar: dados inválidos lançam ErroValidacao (mensagem + erros por campo + status HTTP)
try {
    $id = Contrato::criarContrato([
        'cliente_id' => 5, 'periodicidade' => 'DIARIA',
        'data_inicio' => '2026-11-10', 'data_fim' => '2026-11-20',
        'itens' => [['produto_id' => 1, 'quantidade' => 2]],
    ], $usuarioId);
    Contrato::gerarContrato($id, $usuarioId);              // reserva, numera e gera as parcelas

    $pix = new PagamentoPix($parcelaId, '500,00');        // cada forma de pagamento é uma classe
    $pix->processarPagamento($usuarioId);
} catch (ErroValidacao $e) {
    echo $e->getMessage();      // "Verifique os campos destacados."
    print_r($e->getCampos());   // ['itens.0.quantidade' => 'Estoque insuficiente no período.']
}
```

**No JavaScript das telas**, pela API (leitura com GET; gravação com POST + token CSRF):

```js
// Ler
const resposta = await fetch(window.SGCB_API + 'clientes?situacao=ativos');
const { items, total } = await resposta.json();

// Gravar (o acoes.js já faz isso: SGCBAcoes.executar('produtos/3/status'))
await fetch(window.SGCB_API + 'financeiro/parcelas/7/pagamentos', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.SGCB_CSRF },
  body: JSON.stringify({ forma: 'PIX', valor: '500,00' }),
});
// Erro → status 4xx com { "error": "...", "fields": { "valor": "Saldo da parcela: R$ 525,00." } }
```

### Testes manuais

| Teste | O que confere | Altera o banco? |
|---|---|---|
| `tests/manual/teste_models.php` | Fluxo completo nos models: cliente → produto → contrato → pagamentos → manutenção → dashboard → usuário | Não (roda em transação e desfaz no final) |
| `tests/manual/teste_api.php` | A API pelo Apache: login, formato do JSON de cada tela, 401/403/404/405/422 | Não (só leituras e gravações recusadas) |

```bash
C:\xampp\php\php.exe tests\manual\teste_models.php
C:\xampp\php\php.exe tests\manual\teste_api.php                         # Apache na porta 80
C:\xampp\php\php.exe tests\manual\teste_api.php http://localhost:8080   # Apache em outra porta
```

### Ainda não iniciado

- Telas de **Contratos de Locação** e **Configurações da empresa** (a API e os models já existem; falta o front-end).
- **Redefinir a senha** de outro usuário pela tela de Usuários (hoje a edição altera dados, perfil e status; a senha só muda pela rota `POST api/conta/senha`, do próprio usuário).
- Tela de **troca de senha obrigatória** no primeiro acesso (rota `POST api/conta/senha` pronta).
- Telas de recuperação de senha, "lembrar de mim" e permissões por cargo (funções prontas em `Usuario` e `SessaoPersistente`; hoje só o cargo ADMIN grava, porque as permissões dos outros cargos ainda não foram cadastradas).
- Impressão do **recibo** e do **PDF do contrato** (os dados do recibo já saem em `GET api/financeiro/pagamentos/{id}/recibo`).
- Edição dos dados de uma manutenção já agendada (hoje dá para iniciar, concluir e cancelar).
- Confirmações com diálogos no visual do sistema (as ações usam `confirm`/`prompt` do navegador).
- Testes automatizados (`tests/unit/` e `tests/integration/` ainda vazias; existem os dois testes manuais).

---

## O que cada branch implementou

Ordem cronológica. Os PRs #1 a #5 foram integrados na `main`; a partir do #6 os PRs vão para a `Develop`, que ainda não foi levada de volta para a `main`.

| # | Branch | PR | O que entregou |
|---|---|---|---|
| 1 | `feature/estrutura-pastas` | #1 | Primeira versão da tela de login (HTML/CSS/JS soltos na raiz) e documentação inicial. Depois foi substituída pela reestruturação do projeto. |
| 2 | `feature/Estrutura_de_pastas` | #2 | Estrutura de pastas definitiva: `public/` (única pasta acessível pelo navegador), `src/`, `includes/`, `config/`, `database/`, `docs/`, `tests/` e a proteção da pasta de uploads. |
| 3 | `feature/login` | #3, #4 | Tela de login dentro de `public/` (`login.php`, `login.css`, `login.js`, imagens). Criou o `src/bootstrap.php`, os includes `head.php` e `footer.php`, o `config/config.example.php` (`BASE_URL`) e o `public/index.php`. |
| 4 | `Develop` | #5 | Sincronização da `Develop` com a `main`. |
| 5 | `feature/database` | #6 | Banco de dados completo: migração com as 27 tabelas, seed do usuário administrador, view simples, view complexa e stored procedure (ver [Banco de dados](#banco-de-dados)). |
| 6 | `feature/pagina_dashboard` | #7 | Autenticação real (`Conexao` PDO, classe `Autenticacao`, sessão, CSRF, logout), tela Visão Geral em `public/admin/dashboard.php`, `painel.js` (menu no celular, data, diálogos), ícones do menu e do dashboard, CSS do login e do dashboard comentados. Correções de caminho do login (commits seguintes). |
| 7 | `feature/clientes` | #8 | Telas de Clientes (listagem e cadastro) em `public/admin/clientes/`, com CSS/JS próprios e imagens; menu centralizado em `includes/nav.php`; `head.php` passou a aceitar classe no `<body>` e fontes extras. |
| 8 | `feature/produto` | #9 | Tela de Produtos (somente front-end) em `public/admin/produtos/index.php`: pesquisa, filtros de status e categoria, tabela com diária e estoque, paginação, diálogo "Dados do produto" e diálogo "Cadastre nova Categoria". Arquivos `produtos.css`, `produtos.js`, `categorias.js` e ícones em `img/produtos/`. |
| 9 | `Develop` | — | Tela de Cadastro de Produto (somente front-end) em `public/admin/produtos/cadastro.php`: dados do produto, informações de compra, valores de locação por período e estoque/limites. Arquivos `cadastro-produto.css` e `cadastro-produto.js`; o botão "Novo produto" da listagem abre essa tela. |
| 10 | `feature/manutencao` | #10 | Tela de Manutenções (somente front-end) em `public/admin/manutencoes/index.php`: indicadores (em manutenção, agendadas, urgentes e concluídas no mês), pesquisa, filtros de prioridade, localidade e data, tabela com status e prioridade, paginação e diálogo "Dados da manutenção". Tela de Cadastro de Manutenção em `public/admin/manutencoes/cadastro.php`: contêiner, tipo, prioridade, data prevista, responsável e descrição; o botão "Nova manutenção" da listagem abre essa tela. Arquivos `manutencoes.css`, `manutencoes.js`, `cadastro-manutencao.css`, `cadastro-manutencao.js` e ícones em `img/manutencoes/` e `img/cadastro-manutencao/`. |
| 11 | `feature/favicon` | #11 | Favicon (`img/logo/favicon.svg`) aplicado no `includes/head.php`, valendo para todas as páginas. |
| 12 | `feature/financeiro` | #12 | Tela de Financeiro (somente front-end) em `public/admin/financeiro/index.php`: indicadores (faturamento do mês, em aberto, em atraso e prestes a vencer), pesquisa por cliente, filtros de prioridade, status e vencimento, tabela de parcelas, paginação e diálogo "Detalhes da parcela" com histórico de pagamentos e estornos. Arquivos `financeiro.css`, `financeiro.js` e ícone em `img/financeiro/`; reaproveita o visual de `clientes.css` e `manutencoes.css`. |
| 13 | `feature/funcoes` | — (em andamento) | **Back-end completo.** Models das 27 tabelas em `src/Models/` (22 classes, incluindo a hierarquia `Pagamento` → Pix/Cartão/Dinheiro/Boleto/Transferência), API em `public/api/index.php` + `src/Controllers/` (login, CSRF e permissão em `Api.php`, seguindo o CRUD de base do curso), telas ligadas à API (`window.SGCB_API`), edição e ações nos diálogos (`acoes.js`/`acoes.css`, cadastros com `?id=`), seeds `002_dados_basicos.sql` e `003_dados_exemplo.sql`, testes manuais `teste_models.php` e `teste_api.php`. Ajustes: fuso America/Sao_Paulo no bootstrap e na conexão; envio de anexos como `anexos[]`. |
| 14 | `feature/usuarios` | — | Telas de **Usuários** ligadas ao banco: listagem com indicadores, pesquisa e filtros; cadastro e edição de usuários; ativar e inativar pelo diálogo de detalhes. Item **Usuários** no menu e ícones do menu alinhados. |

---

## Linguagens, frameworks e ferramentas

### Linguagens

| Linguagem | Uso no projeto |
|---|---|
| **PHP 8.5** | Páginas, includes, autenticação, sessão, API em JSON, regras de negócio e acesso ao banco com orientação a objetos (`src/`, `public/`, `includes/`). |
| **SQL (MySQL)** | Criação do banco, seeds (inclusive dados de exemplo), views e stored procedure (`database/`); consultas das listagens e indicadores nos models. |
| **HTML5** | Estrutura das telas (dentro dos arquivos `.php`). |
| **CSS3** | Estilos das telas, com layout em Grid/Flexbox e responsivo (`public/assets/css/`). |
| **JavaScript (ES6+)** | Interações no navegador: validações, máscaras, menu, diálogos, gráficos e chamadas `fetch` (`public/assets/js/`). |

### Frameworks e bibliotecas

**Nenhum framework é usado.** O projeto é feito em PHP puro, CSS puro e JavaScript puro (sem Laravel, Bootstrap, React, jQuery etc.), de propósito, para a equipe praticar a base de cada linguagem.

Recursos nativos e serviços usados:

| Recurso | Para quê |
|---|---|
| **PDO** (extensão nativa do PHP) | Conexão com o MySQL usando *prepared statements*. |
| **password_hash / password_verify** (PHP) | Hash bcrypt das senhas. |
| **Sessões do PHP** | Usuário logado e token CSRF. |
| **finfo** (PHP) | Descobrir o tipo real dos arquivos enviados (foto e anexos). |
| **JSON** (`json_encode` / `fetch`) | Comunicação entre as telas e a API; cópias dos dados guardadas nos contratos. |
| **Google Fonts** | Fontes Figtree, Libre Caslon Text e Inter. |

### Ferramentas e ambiente

| Ferramenta | Versão / uso |
|---|---|
| **XAMPP** | Apache 2.4.65 + PHP 8.5.12 |
| **MySQL** | 8.4 (os scripts também são compatíveis com MariaDB 10.4+) |
| **Apache mod_rewrite** | O `.htaccess` da raiz redireciona tudo para `public/`; o `public/api/.htaccess` manda as URLs `api/...` para o `index.php` da API |
| **Git + GitHub** | Versionamento, branches por funcionalidade e Pull Requests |
| **VS Code** | Editor |

---

## Estrutura de pastas

```
SGCB/
├── .htaccess                  # redireciona as requisições para public/
├── config/
│   └── config.example.php     # modelo: BASE_URL e dados do banco (copiar para config.php)
├── database/
│   ├── migrations/            # 001_create_schema_casa_barretto.sql (27 tabelas)
│   ├── seeds/                 # 001 (admin), 002 (condições e empresa) e 003 (dados de exemplo, opcional)
│   ├── views/                 # vw_unidades_fisicas.sql, vw_resumo_parcelas_contratos.sql
│   └── procedures/            # sp_consultar_unidades_disponiveis.sql e sp_com_parametros.sql (10 procedures)
├── docs/                      # documentação do projeto
├── includes/
│   ├── head.php               # <head>, fontes, CSS da página e abertura do <body>
│   ├── nav.php                # menu lateral da área administrativa
│   └── footer.php             # scripts da página e fechamento do HTML
├── public/                    # ÚNICA pasta acessível pelo navegador
│   ├── index.php              # raiz: manda para o dashboard ou para o login
│   ├── login.php              # tela de login + autenticação
│   ├── logout.php             # encerra a sessão
│   ├── api/                   # index.php: entrada única da API (rotas → src/Controllers), .htaccess
│   ├── admin/                 # páginas que exigem login
│   │   ├── dashboard.php      # Visão Geral
│   │   ├── clientes/          # index.php (listagem) e cadastro.php
│   │   ├── produtos/          # index.php (listagem) e cadastro.php
│   │   ├── manutencoes/       # index.php (listagem) e cadastro.php
│   │   ├── financeiro/        # index.php (parcelas)
│   │   ├── usuarios/          # index.php (listagem) e cadastro.php (?id= edita)
│   │   ├── containers/  locacoes/   # módulos futuros
│   ├── assets/
│   │   ├── css/               # login, dashboard, clientes, cadastro-cliente, produtos, cadastro-produto, manutencoes, cadastro-manutencao, financeiro, acoes, usuarios, cadastro-usuario
│   │   ├── js/                # login, painel, dashboard, clientes, cadastro-cliente, produtos, categorias, cadastro-produto, manutencoes, cadastro-manutencao, financeiro, acoes, usuarios, cadastro-usuario
│   │   └── img/               # login, logo, nav, dashboard, clientes, cadastro-cliente, produtos, manutencoes, cadastro-manutencao, financeiro, usuarios
│   └── uploads/               # arquivos enviados, em AAAA/MM/ (protegida por .htaccess; fora do Git)
├── src/
│   ├── bootstrap.php          # config, fuso horário, autoload de Models/Controllers e sessão
│   ├── Auth/Autenticacao.php  # login, logout, exigirLogin, CSRF
│   ├── Database/Conexao.php   # conexão PDO única (no mesmo fuso do PHP)
│   ├── Models/                # regras de negócio e acesso às 27 tabelas: Modelo (base), ErroValidacao, Usuario,
│   │                          #   SessaoPersistente, Cliente, Arquivo, CondicaoPagamento, Empresa, Categoria, Produto,
│   │                          #   UnidadeFisica, Manutencao, Contrato, Parcela, Pagamento + 5 formas, Dashboard, Auditoria
│   └── Controllers/           # intermediário da API: Api.php (login, CSRF, permissão) + um controller por módulo
└── tests/
    └── manual/                # teste_models.php (models) e teste_api.php (API pelo Apache); unit/ e integration/ reservados
```

---

## Banco de dados

Banco **`sgcb`**, criado pela migração `database/migrations/001_create_schema_casa_barretto.sql` (27 tabelas, a partir do DER do projeto):

| Grupo | Tabelas |
|---|---|
| **Acesso** | `cargos`, `modulos`, `permissoes`, `cargo_permissoes`, `usuarios`, `usuario_permissoes`, `sessoes_usuario`, `recuperacoes_senha` |
| **Cadastros** | `arquivos`, `condicoes_pagamento`, `pessoas`, `pessoa_anexos`, `enderecos_pessoa`, `empresas`, `preferencias_contrato` |
| **Estoque** | `categorias`, `produtos`, `precos_produto`, `unidades_fisicas`, `manutencoes` |
| **Locação** | `contratos`, `contrato_itens`, `agenda_unidades`, `contrato_documentos` |
| **Financeiro** | `parcelas`, `pagamentos` |
| **Auditoria** | `auditoria` |

Outros scripts:

| Arquivo | Tipo | O que faz |
|---|---|---|
| `seeds/001_usuario_admin.sql` | Seed | Cria o cargo Administrador e o usuário inicial (`admin@casabarretto.local` / `mudar123`). Ele fica marcado com `deve_alterar_senha`, mas a tela de troca de senha ainda não existe (a rota `POST api/conta/senha` já existe). |
| `seeds/002_dados_basicos.sql` | Seed | Condições de pagamento (À vista, 30 dias, 2x, 3x) e a empresa "Casa Barretto" com numeração automática `CT-000001`. Pode rodar de novo sem duplicar. |
| `seeds/003_dados_exemplo.sql` | Seed (opcional) | Dados fictícios para ver as telas funcionando: 9 clientes, 2 fornecedores, 5 produtos (19 contêineres), 12 contratos, parcelas pagas/em aberto/em atraso, pagamentos em todas as formas, 5 manutenções e 2 operadores (senha `Exemplo123`). Rode uma vez só. |
| `views/vw_unidades_fisicas.sql` | View simples | Lista cada contêiner físico com o nome do produto e da categoria. |
| `views/vw_resumo_parcelas_contratos.sql` | View complexa | Uma linha por contrato com quantidade de parcelas, valor total e primeiro/último vencimento. |
| `procedures/sp_consultar_unidades_disponiveis.sql` | Stored procedure | `ConsultarUnidadesDisponiveis(produto, @qtd)`: lista os contêineres do produto que estão disponíveis **agora** e devolve a quantidade em parâmetro de saída. |
| `procedures/sp_com_parametros.sql` | 10 stored procedures | Exercícios 7 a 16 (Aula 14): `BuscarProdutoPorId`, `BuscarProdutoPorNome`, `BuscarProdutosPorCategoria`, `InserirProduto`, `AtualizarValorProduto`, `ExcluirProduto`, `BuscarUnidadesPorProdutoEstado`, `BuscarProdutosPorFaixaValor`, `BuscarContratosPorDataInicio` e `ListarProdutosCategoriaPorNome`. |

Views e procedures foram testadas com os dados do `003_dados_exemplo.sql` e funcionam com o banco preenchido pelo sistema. Diferenças em relação às telas:

- `ConsultarUnidadesDisponiveis` e `BuscarUnidadesPorProdutoEstado` olham só o estado **atual** do contêiner. As telas e os contratos usam `Produto::verificarDisponibilidade`, que também considera reservas futuras e manutenções na agenda (ex.: um contêiner disponível hoje, mas reservado a partir da semana que vem, aparece na procedure e não fica livre para um contrato nesse período).
- `vw_unidades_fisicas` usa INNER JOIN com categorias: produto sem categoria não aparece nela.
- `vw_resumo_parcelas_contratos` ignora parcelas canceladas, então contratos cancelados e rascunhos (sem parcelas) não aparecem.
- `InserirProduto` cria só o produto, sem fornecedor, preços nem contêineres e sem registro na auditoria. Para usar no sistema, complete pela tela de edição do produto. O cadastro completo é o da tela (`Produto::cadastrarProduto`).
- `ExcluirProduto` só apaga produto sem contêineres e sem preços; com eles, o banco recusa (chave estrangeira). No sistema, o caminho é inativar.

> A senha do seed é pública (está no repositório). Use apenas em desenvolvimento.

---

## Como rodar o projeto

> **O banco de dados é obrigatório.** Sem o MySQL ligado e sem o banco `sgcb` com o usuário cadastrado, **não é possível entrar no sistema**: o login recusa o acesso e qualquer sessão aberta antes é encerrada.

### Pré-requisitos

- **XAMPP** com Apache e MySQL (PHP 8.5 ou superior; ver [Ferramentas e ambiente](#ferramentas-e-ambiente)).
- **Git**, para clonar o repositório.

### 1. Baixar o projeto

Clone dentro da pasta `htdocs` do XAMPP, mantendo o nome da pasta `SGCB`:

```bash
cd C:\xampp\htdocs
git clone https://github.com/AlexWilliam18/SGCB.git
```

### 2. Ligar o servidor

No painel do XAMPP, clique em **Start** no **Apache** e no **MySQL**. Os dois precisam ficar verdes.

### 3. Criar o banco de dados (somente na primeira vez)

Abra o phpMyAdmin (`http://localhost/phpmyadmin`) → aba **SQL** (ou use o cliente MySQL) e execute os arquivos **nesta ordem**:

1. `database/migrations/001_create_schema_casa_barretto.sql` — cria o banco `sgcb` e as 27 tabelas;
2. `database/seeds/001_usuario_admin.sql` — cria o cargo Administrador e o usuário inicial;
3. `database/seeds/002_dados_basicos.sql` — condições de pagamento padrão e a empresa com as preferências de contrato (sem ele, não há como cadastrar cliente nem gerar contrato);
4. `database/views/vw_unidades_fisicas.sql` e `database/views/vw_resumo_parcelas_contratos.sql`;
5. `database/procedures/sp_consultar_unidades_disponiveis.sql` e `database/procedures/sp_com_parametros.sql` (este não tem `USE sgcb`: selecione o banco `sgcb` no phpMyAdmin antes; para rodar de novo, apague as procedures com `DROP PROCEDURE`).
6. *(Opcional, para ver as telas com informações)* `database/seeds/003_dados_exemplo.sql` — clientes, produtos, contratos, parcelas, pagamentos e manutenções fictícios, com datas relativas ao dia em que rodar. Rode uma vez só.

> A migração cria as tabelas sem `IF NOT EXISTS`: rode-a só em um banco novo. Para recomeçar do zero, apague o banco `sgcb` antes. O seed pode ser executado de novo sem duplicar o usuário.

### 4. Configurar o projeto

Copie `config/config.example.php` para `config/config.php` (esse arquivo não vai para o Git, cada integrante tem o seu) e confira:

| Constante | Padrão | Quando mudar |
|---|---|---|
| `BASE_URL` | `/SGCB/` | Se a pasta no `htdocs` tiver outro nome (ex.: `/sgcb/`). Sempre com barra no início e no fim. |
| `DB_HOST`, `DB_PORT` | `127.0.0.1`, `3306` | Se o MySQL do XAMPP estiver em outra porta. **Use a mesma porta que aparece no phpMyAdmin** ("Servidor: 127.0.0.1:porta"); quando a 3306 já está ocupada por outro MySQL instalado, o XAMPP costuma usar 3307 ou 3308. |
| `DB_NAME` | `sgcb` | Se o banco tiver outro nome. |
| `DB_USER`, `DB_PASS` | `root`, vazio | Se o seu MySQL tiver senha no usuário `root` ou outro usuário. |

Sem o `config.php`, o sistema usa os valores do `config.example.php`.

### 5. Acessar

1. Abra `http://localhost/SGCB/` (se o Apache estiver em outra porta, inclua-a: `http://localhost:8080/SGCB/`).
2. Entre com o usuário do seed: **`admin@casabarretto.local`** / **`mudar123`**.
3. Depois do login você cai na **Visão Geral** (`admin/dashboard.php`). Use o menu lateral para navegar; "Sair" encerra a sessão.
4. Para editar ou executar ações, abra o item na listagem (botão de ver/detalhes): os botões ficam no rodapé do diálogo.

Com o seed `003_dados_exemplo.sql` também existem os operadores `carla.mendes@casabarretto.exemplo.com.br` e `rafael.souza@casabarretto.exemplo.com.br` (senha `Exemplo123`). Eles conseguem consultar, mas ainda não gravam: o cargo Operador não tem permissões cadastradas.

### 6. Conferir se está tudo funcionando (opcional)

```bash
C:\xampp\php\php.exe tests\manual\teste_models.php                      # regras de negócio (não altera o banco)
C:\xampp\php\php.exe tests\manual\teste_api.php http://localhost:8080   # API pelo Apache; use a porta do seu Apache
```

### Problemas comuns

| Sintoma | Causa provável | Como resolver |
|---|---|---|
| "Não foi possível conectar ao banco de dados" no login | MySQL desligado, banco `sgcb` não criado ou dados errados no `config.php` | Ligue o MySQL no XAMPP, rode os scripts do passo 3 e confira o `config.php`. |
| O sistema entra, mas o banco `sgcb` não aparece no phpMyAdmin (ou o contrário) | Há **dois servidores MySQL** na máquina e o `config.php` aponta para um diferente do phpMyAdmin | Deixe `DB_PORT` igual à porta mostrada no topo do phpMyAdmin. |
| "E-mail ou senha inválidos" com o usuário do seed | Seed não executado, usuário desativado (`ativo = 0`) ou senha alterada | Rode o seed e confira a tabela `usuarios`. |
| "Sua sessão foi encerrada…" ao navegar | O usuário logado foi apagado ou desativado no banco | Entre com um usuário ativo. |
| Página "Not Found" | `BASE_URL` diferente do nome da pasta ou `mod_rewrite` desligado no Apache | Ajuste a `BASE_URL` e confirme que o `.htaccess` da raiz está sendo lido. |
| Tela sem estilo ou ícones | CSS/imagens não encontrados (geralmente `BASE_URL` errada) | Ajuste a `BASE_URL`; depois recarregue com `Ctrl + F5`. |
| Aviso amarelo "Não foi possível carregar…" no dashboard, em Clientes, Produtos, Manutenções ou Financeiro | A API não respondeu: banco fora do ar, `mod_rewrite` desligado ou `BASE_URL` errada | Abra `http://localhost/SGCB/api/dashboard` logado: deve aparecer um JSON. Rode `tests\manual\teste_api.php` para ver qual rota falha. |
| Cadastro de cliente diz que não há condição de pagamento | Seed `002_dados_basicos.sql` não executado | Rode o seed (passo 3 de "Como rodar o projeto"). |
| `http://localhost/SGCB/` abre outra coisa ou dá erro, e o login responde "405" | Outro programa (ex.: nginx, IIS, Skype) usa a porta 80 e o Apache do XAMPP subiu em outra porta | Veja a porta do Apache no painel do XAMPP (coluna "Port(s)") e acesse `http://localhost:PORTA/SGCB/` (ex.: 8080). |
| Botões "Editar", "Inativar", "Registrar pagamento" não aparecem | Navegador com o JavaScript antigo em cache | Recarregue com `Ctrl + F5`. |
| "Você não tem permissão para esta ação." | O usuário logado não é do cargo ADMIN e o cargo dele não tem a permissão cadastrada | Entre com o admin ou cadastre as permissões do cargo (`cargo_permissoes`). |
| "Sessão expirada. Recarregue a página e tente novamente." ao salvar | O token CSRF mudou (novo login em outra aba, sessão expirada) | Recarregue a página. |
| Produtos sem categoria depois de rodar o `003_dados_exemplo.sql` | Versão antiga do seed no phpMyAdmin | Use a versão atual do arquivo; para corrigir sem recomeçar, ligue cada produto à categoria certa na tabela `produtos`. |
| O `003_dados_exemplo.sql` para com "Duplicate entry" | Os dados de exemplo já estão no banco | Nada a fazer (nada foi gravado). Para recomeçar, apague o banco `sgcb` e rode tudo de novo. |

---

## Fluxo de trabalho no Git

- `main` — versão estável; recebe a `Develop` por Pull Request quando uma etapa estiver pronta.
- `Develop` — branch de integração; recebe as `feature/<nome>` por Pull Request (desde o PR #6).
- `feature/<nome>` — uma branch por funcionalidade (ex.: `feature/clientes`), sempre criada a partir da `Develop` atualizada.
- Commits com a data e uma descrição curta da entrega (ex.: `24/10/2026 - Commit Página Clientes`).