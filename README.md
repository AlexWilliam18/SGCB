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

> Atualizado em 25/10/2026.

### Já integrado (front-end + back-end funcionando)

| Funcionalidade | Detalhes |
|---|---|
| **Login com banco de dados** | E-mail e senha validados na tabela `usuarios` (somente usuário ativo), com proteção CSRF e nova sessão a cada login. |
| **Senhas com hash** | A senha do seed pode estar em texto puro; no primeiro login ela é convertida automaticamente para hash bcrypt (`password_hash` / `password_verify`). |
| **Sessão e páginas protegidas** | Páginas de `public/admin/` exigem login (`Autenticacao::exigirLogin()`). A cada página, o usuário da sessão é conferido no banco: se ele foi apagado, desativado ou se o banco estiver fora do ar, a sessão é encerrada e a pessoa volta para o login com o motivo. |
| **Logout** | Botão "Sair" encerra a sessão (com token CSRF na URL). |
| **Redirecionamento da raiz** | `http://localhost/SGCB/` leva para o dashboard (sessão confirmada no banco) ou para o login. |
| **Menu lateral único** | `includes/nav.php` monta a navegação de todas as páginas do admin a partir de um único array; o item da página atual fica destacado e módulos sem página abrem o aviso "em desenvolvimento". |
| **Usuário logado no menu** | Nome, cargo e inicial do avatar vêm da sessão. |
| **Cache de CSS/JS** | Os arquivos são carregados com `?v=<data de modificação>`, então o navegador sempre pega a versão nova. |

### Somente front-end (aguardando o back-end)

| Tela | Arquivo | Depende de |
|---|---|---|
| Visão Geral (dashboard) | `public/admin/dashboard.php` | `/api/dashboard` — indicadores, gráficos, rankings e últimas locações |
| Listagem de clientes | `public/admin/clientes/index.php` | `/api/clientes` — lista, filtros, paginação e detalhes |
| Cadastro de cliente | `public/admin/clientes/cadastro.php` | `/api/cadastros/opcoes` e `/api/clientes` (POST) — condições de pagamento e salvamento |
| Listagem de produtos | `public/admin/produtos/index.php` | `/api/produtos` — lista, filtros por status/categoria, paginação, estoque e detalhes |
| Nova categoria (diálogo da tela de Produtos) | `public/admin/produtos/index.php` | `/api/categorias/opcoes` e `/api/categorias` (POST) — token CSRF e salvamento |
| Cadastro de produto | `public/admin/produtos/cadastro.php` | `/api/produtos/opcoes` e `/api/produtos` (POST) — categorias, fornecedores, token CSRF e salvamento (gera as unidades físicas) |

Enquanto essas APIs não existem, as telas abrem normalmente e mostram um aviso de "não foi possível carregar".

### Ainda não iniciado

- Módulos **Contratos de Locação**, **Manutenções** e **Financeiro** (já aparecem no menu como "em desenvolvimento").
- Tela de **troca de senha obrigatória** no primeiro acesso (o banco já tem o campo `deve_alterar_senha`).
- Recuperação de senha, "lembrar de mim" no servidor e permissões por cargo (as tabelas já existem).
- Testes automatizados (pastas `tests/` criadas, ainda vazias).

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
| 9 | Cadastro de produto | — (em andamento) | Tela de Cadastro de Produto (somente front-end) em `public/admin/produtos/cadastro.php`: dados do produto, informações de compra, valores de locação por período e estoque/limites. Arquivos `cadastro-produto.css` e `cadastro-produto.js`; o botão "Novo produto" da listagem abre essa tela. |

---

## Linguagens, frameworks e ferramentas

### Linguagens

| Linguagem | Uso no projeto |
|---|---|
| **PHP 8.5** | Páginas, includes, autenticação, sessão e acesso ao banco (`src/`, `public/`, `includes/`). |
| **SQL (MySQL)** | Criação do banco, seed, views e stored procedure (`database/`). |
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
| **Google Fonts** | Fontes Figtree, Libre Caslon Text e Inter. |

### Ferramentas e ambiente

| Ferramenta | Versão / uso |
|---|---|
| **XAMPP** | Apache 2.4.65 + PHP 8.5.12 |
| **MySQL** | 8.4 (os scripts também são compatíveis com MariaDB 10.4+) |
| **Apache mod_rewrite** | O `.htaccess` da raiz redireciona tudo para `public/` |
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
│   ├── seeds/                 # 001_usuario_admin.sql (cargo ADMIN + usuário admin)
│   ├── views/                 # vw_unidades_fisicas.sql, vw_resumo_parcelas_contratos.sql
│   └── procedures/            # ConsultarUnidadesDisponiveis.sql
├── docs/                      # documentação do projeto
├── includes/
│   ├── head.php               # <head>, fontes, CSS da página e abertura do <body>
│   ├── nav.php                # menu lateral da área administrativa
│   └── footer.php             # scripts da página e fechamento do HTML
├── public/                    # ÚNICA pasta acessível pelo navegador
│   ├── index.php              # raiz: manda para o dashboard ou para o login
│   ├── login.php              # tela de login + autenticação
│   ├── logout.php             # encerra a sessão
│   ├── admin/                 # páginas que exigem login
│   │   ├── dashboard.php      # Visão Geral
│   │   ├── clientes/          # index.php (listagem) e cadastro.php
│   │   ├── produtos/          # index.php (listagem) e cadastro.php
│   │   ├── containers/  locacoes/  usuarios/   # módulos futuros
│   ├── assets/
│   │   ├── css/               # login, dashboard, clientes, cadastro-cliente, produtos, cadastro-produto
│   │   ├── js/                # login, painel, dashboard, clientes, cadastro-cliente, produtos, categorias, cadastro-produto
│   │   └── img/               # login, logo, nav, dashboard, clientes, cadastro-cliente, produtos
│   └── uploads/               # arquivos enviados (protegida por .htaccess)
├── src/
│   ├── bootstrap.php          # carrega config, classes e inicia a sessão
│   ├── Auth/Autenticacao.php  # login, logout, exigirLogin, CSRF
│   ├── Database/Conexao.php   # conexão PDO única
│   ├── Controllers/  Models/  # reservados para o back-end dos módulos
└── tests/                     # unit/, integration/, manual/ (reservados)
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
| `seeds/001_usuario_admin.sql` | Seed | Cria o cargo Administrador e o usuário inicial (`admin@casabarretto.local` / `mudar123`). Ele fica marcado com `deve_alterar_senha`, mas a tela de troca de senha ainda não existe. |
| `views/vw_unidades_fisicas.sql` | View simples | Lista cada contêiner físico com o nome do produto e da categoria. |
| `views/vw_resumo_parcelas_contratos.sql` | View complexa | Uma linha por contrato com quantidade de parcelas, valor total e primeiro/último vencimento. |
| `procedures/ConsultarUnidadesDisponiveis.sql` | Stored procedure | Recebe um produto, lista os contêineres disponíveis e devolve a quantidade em parâmetro de saída. |

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
3. `database/views/vw_unidades_fisicas.sql` e `database/views/vw_resumo_parcelas_contratos.sql`;
4. `database/procedures/ConsultarUnidadesDisponiveis.sql`.

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

### Problemas comuns

| Sintoma | Causa provável | Como resolver |
|---|---|---|
| "Não foi possível conectar ao banco de dados" no login | MySQL desligado, banco `sgcb` não criado ou dados errados no `config.php` | Ligue o MySQL no XAMPP, rode os scripts do passo 3 e confira o `config.php`. |
| O sistema entra, mas o banco `sgcb` não aparece no phpMyAdmin (ou o contrário) | Há **dois servidores MySQL** na máquina e o `config.php` aponta para um diferente do phpMyAdmin | Deixe `DB_PORT` igual à porta mostrada no topo do phpMyAdmin. |
| "E-mail ou senha inválidos" com o usuário do seed | Seed não executado, usuário desativado (`ativo = 0`) ou senha alterada | Rode o seed e confira a tabela `usuarios`. |
| "Sua sessão foi encerrada…" ao navegar | O usuário logado foi apagado ou desativado no banco | Entre com um usuário ativo. |
| Página "Not Found" | `BASE_URL` diferente do nome da pasta ou `mod_rewrite` desligado no Apache | Ajuste a `BASE_URL` e confirme que o `.htaccess` da raiz está sendo lido. |
| Tela sem estilo ou ícones | CSS/imagens não encontrados (geralmente `BASE_URL` errada) | Ajuste a `BASE_URL`; depois recarregue com `Ctrl + F5`. |
| Aviso amarelo "Não foi possível carregar…" no dashboard, em Clientes ou em Produtos | Normal nesta fase: as APIs dessas telas ainda não existem | Nada a fazer até a etapa de back-end. |

---

## Fluxo de trabalho no Git

- `main` — versão estável; recebe a `Develop` por Pull Request quando uma etapa estiver pronta.
- `Develop` — branch de integração; recebe as `feature/<nome>` por Pull Request (desde o PR #6).
- `feature/<nome>` — uma branch por funcionalidade (ex.: `feature/clientes`), sempre criada a partir da `Develop` atualizada.
- Commits com a data e uma descrição curta da entrega (ex.: `24/10/2026 - Commit Página Clientes`).