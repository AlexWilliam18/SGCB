# SGCB — Sistema de Gestão de Containers Barreto

Sistema web para a **Casa Barretto** gerenciar a locação de contêineres: clientes, produtos (modelos e unidades físicas), contratos de locação, manutenções e financeiro.

Projeto Integrador desenvolvido pela equipe:

- Alex Silva
- Bruno Gabriel Ribeiro
- Eduardo Sales
- Fabricio Teodoro
- Mauro Yida

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
| **Sessão e páginas protegidas** | Páginas de `public/admin/` exigem login (`Autenticacao::exigirLogin()`); sem sessão, voltam para o login. |
| **Logout** | Botão "Sair" encerra a sessão (com token CSRF na URL). |
| **Redirecionamento da raiz** | `http://localhost/SGCB/` leva para o dashboard (logado) ou para o login. |
| **Menu lateral único** | `includes/nav.php` monta a navegação de todas as páginas do admin a partir de um único array; o item da página atual fica destacado e módulos sem página abrem o aviso "em desenvolvimento". |
| **Usuário logado no menu** | Nome, cargo e inicial do avatar vêm da sessão. |
| **Cache de CSS/JS** | Os arquivos são carregados com `?v=<data de modificação>`, então o navegador sempre pega a versão nova. |

### Somente front-end (aguardando o back-end)

| Tela | Arquivo | Depende de |
|---|---|---|
| Visão Geral (dashboard) | `public/admin/dashboard.php` | `/api/dashboard` — indicadores, gráficos, rankings e últimas locações |
| Listagem de clientes | `public/admin/clientes/index.php` | `/api/clientes` — lista, filtros, paginação e detalhes |
| Cadastro de cliente | `public/admin/clientes/cadastro.php` | `/api/cadastros/opcoes` e `/api/clientes` (POST) — condições de pagamento e salvamento |

Enquanto essas APIs não existem, as telas abrem normalmente e mostram um aviso de "não foi possível carregar".

### Ainda não iniciado

- Módulos **Produtos**, **Contratos de Locação**, **Manutenções** e **Financeiro** (já aparecem no menu como "em desenvolvimento").
- Tela de **troca de senha obrigatória** no primeiro acesso (o banco já tem o campo `deve_alterar_senha`).
- Recuperação de senha, "lembrar de mim" no servidor e permissões por cargo (as tabelas já existem).
- Testes automatizados (pastas `tests/` criadas, ainda vazias).

---

## O que cada branch implementou

Ordem cronológica. Todas foram integradas na `main` por Pull Request; a `Develop` está no mesmo ponto da `main`.

| # | Branch | PR | O que entregou |
|---|---|---|---|
| 1 | `feature/estrutura-pastas` | #1 | Primeira versão da tela de login (HTML/CSS/JS soltos na raiz) e documentação inicial. Depois foi substituída pela reestruturação do projeto. |
| 2 | `feature/Estrutura_de_pastas` | #2 | Estrutura de pastas definitiva: `public/` (única pasta acessível pelo navegador), `src/`, `includes/`, `config/`, `database/`, `docs/`, `tests/` e a proteção da pasta de uploads. |
| 3 | `feature/login` | #3, #4 | Tela de login dentro de `public/` (`login.php`, `login.css`, `login.js`, imagens). Criou o `src/bootstrap.php`, os includes `head.php` e `footer.php`, o `config/config.example.php` (`BASE_URL`) e o `public/index.php`. |
| 4 | `Develop` | #5 | Sincronização da `Develop` com a `main`. |
| 5 | `feature/database` | #6 | Banco de dados completo: migração com as 27 tabelas, seed do usuário administrador, view simples, view complexa e stored procedure (ver [Banco de dados](#banco-de-dados)). |
| 6 | `feature/pagina_dashboard` | #7 | Autenticação real (`Conexao` PDO, classe `Autenticacao`, sessão, CSRF, logout), tela Visão Geral em `public/admin/dashboard.php`, `painel.js` (menu no celular, data, diálogos), ícones do menu e do dashboard, CSS do login e do dashboard comentados. Correções de caminho do login (commits seguintes). |
| 7 | `feature/clientes` | #8 | Telas de Clientes (listagem e cadastro) em `public/admin/clientes/`, com CSS/JS próprios e imagens; menu centralizado em `includes/nav.php`; `head.php` passou a aceitar classe no `<body>` e fontes extras. |

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
│   │   ├── containers/  locacoes/  produtos/  usuarios/   # módulos futuros
│   ├── assets/
│   │   ├── css/               # login, dashboard, clientes, cadastro-cliente
│   │   ├── js/                # login, painel, dashboard, clientes, cadastro-cliente
│   │   └── img/               # login, logo, nav, dashboard, clientes, cadastro-cliente
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
| `seeds/001_usuario_admin.sql` | Seed | Cria o cargo Administrador e o usuário inicial (`admin@casabarretto.local` / `mudar123`, com troca de senha obrigatória). |
| `views/vw_unidades_fisicas.sql` | View simples | Lista cada contêiner físico com o nome do produto e da categoria. |
| `views/vw_resumo_parcelas_contratos.sql` | View complexa | Uma linha por contrato com quantidade de parcelas, valor total e primeiro/último vencimento. |
| `procedures/ConsultarUnidadesDisponiveis.sql` | Stored procedure | Recebe um produto, lista os contêineres disponíveis e devolve a quantidade em parâmetro de saída. |

> A senha do seed é pública (está no repositório). Use apenas em desenvolvimento.

---

## Como rodar o projeto

1. Instale o **XAMPP** e coloque o projeto em `C:\xampp\htdocs\SGCB`.
2. Inicie o **Apache** e o **MySQL** no painel do XAMPP.
3. Execute, nesta ordem, no phpMyAdmin ou no cliente MySQL:
   1. `database/migrations/001_create_schema_casa_barretto.sql`
   2. `database/seeds/001_usuario_admin.sql`
   3. os arquivos de `database/views/` e `database/procedures/`
4. Copie `config/config.example.php` para `config/config.php` e ajuste, se necessário:
   - `BASE_URL` — nome da pasta no htdocs (padrão `/SGCB/`);
   - `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` — padrão do XAMPP: `root` sem senha.
5. Acesse `http://localhost/SGCB/` (ou `http://localhost:8080/SGCB/` se o Apache estiver na porta 8080).
6. Entre com `admin@casabarretto.local` / `mudar123`.

---

## Fluxo de trabalho no Git

- `main` — versão estável, recebe as branches por Pull Request.
- `Develop` — branch de integração.
- `feature/<nome>` — uma branch por funcionalidade (ex.: `feature/clientes`).
- Commits com a data e uma descrição curta da entrega (ex.: `24/10/2026 - Commit Página Clientes`).
