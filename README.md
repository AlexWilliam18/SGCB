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
2. [Fluxo de uma locação](#fluxo-de-uma-locação)
3. [Histórico de entregas](#histórico-de-entregas)
4. [Linguagens, frameworks e ferramentas](#linguagens-frameworks-e-ferramentas)
5. [Estrutura geral do projeto](#estrutura-geral-do-projeto)
6. [Como rodar o projeto](#como-rodar-o-projeto)
7. [Fluxo de trabalho no Git](#fluxo-de-trabalho-no-git)

---

## Situação atual

### O que o sistema já faz

| Módulo | Funcionalidades |
|---|---|
| **Acesso** | Login com e-mail e senha, senhas protegidas por hash, páginas restritas a usuários logados, encerramento de sessão e controle de permissões por perfil. |
| **Visão Geral** | Indicadores, gráficos, rankings e últimas locações. |
| **Clientes** | Listagem com pesquisa e filtros, cadastro e edição (endereços, foto e anexos), inativar e reativar. |
| **Produtos** | Listagem com pesquisa e filtros, cadastro e edição de produtos e categorias, preços por período, controle de estoque por contêiner. |
| **Contratos de Locação** | Listagem com pesquisa por cliente, CPF/CNPJ ou número, filtros de status e período, paginação e prévia do contrato com opção de imprimir/salvar em PDF. Cadastro de contrato com escolha do cliente, endereço de entrega, período, contêineres livres no período, condições comerciais, resumo com o valor calculado e geração do contrato (ou rascunho). |
| **Manutenções** | Listagem com indicadores e filtros, agendamento, início, conclusão e cancelamento. |
| **Financeiro** | Parcelas com indicadores e filtros, registro de pagamentos (parcial ou total, em várias formas), estornos e cancelamento de parcelas. |
| **Usuários** | Listagem com indicadores e filtros, cadastro e edição, ativar e inativar. |
| **Auditoria** | Toda alteração fica registrada (quem fez, o quê e quando). |

### Próximas etapas

- Configurações da empresa.
- Troca e recuperação de senha pela tela.
- Impressão do recibo de pagamento.
- Edição de manutenção já agendada.
- Testes automatizados.

---

## Fluxo de uma locação

Resumo do caminho que seguimos do cadastro até o encerramento de um contrato:

1. **Cadastrar o cliente** — dados pessoais ou da empresa, CPF/CNPJ, contato e endereços (um principal e, se precisar, outros para entrega).
2. **Cadastrar o produto** — modelo do contêiner, categoria, preços por período (diária, semanal, quinzenal, mensal) e quantidade em estoque. Cada contêiner real ganha um código próprio.
3. **Montar o contrato** — escolher o cliente, o produto, a quantidade, o período, a periodicidade de cobrança, a condição de pagamento e o endereço de entrega. O sistema calcula o valor e o contrato fica como **rascunho**.
4. **Gerar o contrato** — o sistema confere o estoque no período, reserva os contêineres, numera o contrato e cria as parcelas. O contrato passa a **ativo**.
5. **Entrega e acompanhamento** — os contêineres saem para o cliente e as parcelas aparecem no **Financeiro**, onde os pagamentos são registrados.
6. **Encerramento** — ao fim da locação o contrato é **finalizado** e os contêineres voltam ao estoque. Se for **cancelado**, os contêineres são liberados e as parcelas sem pagamento são canceladas.

> Pela tela de cadastro, os passos 3 e 4 acontecem juntos quando o status inicial é **Ativo**; com **Rascunho**, o contrato fica salvo para ser gerado depois.

---

## Histórico de entregas

| # | Entrega |
|---|---|
| 1 | Primeira versão da tela de login e documentação inicial. |
| 2 | Estrutura de pastas definitiva do projeto. |
| 3 | Tela de login integrada à estrutura do projeto. |
| 4 | Sincronização das branches principais. |
| 5 | Banco de dados completo, com views e stored procedures. |
| 6 | Autenticação real e tela de Visão Geral. |
| 7 | Telas de Clientes (listagem e cadastro) e menu lateral único. |
| 8 | Tela de Produtos e cadastro de categorias. |
| 9 | Tela de Cadastro de Produto. |
| 10 | Telas de Manutenções (listagem e cadastro). |
| 11 | Favicon em todas as páginas. |
| 12 | Tela de Financeiro. |
| 13 | Back-end completo: todas as telas lendo e gravando no banco, com edição e ações. |
| 14 | Telas de Usuários (listagem, cadastro, edição, ativar e inativar). |
| 15 | Tela inicial de Contratos de Locação ligada ao banco, com prévia e impressão do contrato. |
| 16 | Cadastro de contrato de locação ligado ao banco: escolha dos contêineres livres no período, valor calculado pelo sistema, prévia antes de salvar e geração do contrato com as parcelas. |

---

## Linguagens, frameworks e ferramentas

### Linguagens

| Linguagem | Uso no projeto |
|---|---|
| **PHP 8.5** | Páginas, autenticação, regras de negócio e acesso ao banco, com orientação a objetos. |
| **SQL (MySQL)** | Banco de dados, views e stored procedures. |
| **HTML5** | Estrutura das telas. |
| **CSS3** | Estilos e layout responsivo (Grid/Flexbox). |
| **JavaScript (ES6+)** | Interações no navegador: validações, máscaras, menu, diálogos e gráficos. |

### Frameworks e bibliotecas

**Nenhum framework é usado.** O projeto é feito em PHP puro, CSS puro e JavaScript puro (sem Laravel, Bootstrap, React, jQuery etc.), de propósito, para a equipe praticar a base de cada linguagem. As fontes vêm do **Google Fonts**.

### Ferramentas e ambiente

| Ferramenta | Uso |
|---|---|
| **XAMPP** | Apache + PHP + MySQL no ambiente de desenvolvimento |
| **Git + GitHub** | Versionamento, branches por funcionalidade e Pull Requests |
| **VS Code** | Editor |
| **Figma** | Protótipo das telas |

---

## Estrutura geral do projeto

Visão resumida das pastas e para que cada uma serve:

| Pasta | Para que serve |
|---|---|
| `public` | Única pasta acessível pelo navegador: as páginas do sistema (login e área administrativa), os estilos, scripts e imagens das telas e a pasta de arquivos enviados. |
| `includes` | Pedaços de página reaproveitados em todas as telas, como o cabeçalho, o menu lateral e o rodapé. |
| `src` | O "coração" do sistema: regras de negócio, acesso ao banco, autenticação e a comunicação entre as telas e o banco. |
| `config` | Configuração local (endereço do projeto e dados de conexão com o banco). Só o modelo vai para o Git. |
| `database` | Scripts SQL do banco: criação das tabelas, dados iniciais, views e procedures. |
| `docs` | Documentação e imagens usadas no README. |
| `tests` | Testes do sistema. |

---

## Como rodar o projeto

> **O banco de dados é obrigatório.** Sem o MySQL ligado e sem o banco criado, não é possível entrar no sistema: o login recusa o acesso e qualquer sessão aberta antes é encerrada.

### Pré-requisitos

- **XAMPP** com Apache e MySQL (PHP 8.5 ou superior).
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

Abra o phpMyAdmin (`http://localhost/phpmyadmin`), vá na aba **SQL** (ou use o cliente MySQL) e execute os scripts da pasta `database` **nesta ordem**:

1. **migrations** — cria o banco e as tabelas;
2. **seeds 001** — cria o perfil Administrador e o usuário inicial;
3. **seeds 002** — condições de pagamento e dados da empresa (sem ele não é possível cadastrar cliente nem gerar contrato);
4. **views**;
5. **procedures** (selecione o banco no phpMyAdmin antes de executar);
6. *(Opcional)* **seeds 003** — dados fictícios para ver as telas preenchidas. Rode uma vez só.

> A migração só deve ser executada em um banco novo. Para recomeçar do zero, apague o banco antes e rode tudo de novo.

**Pelo terminal (alternativa ao phpMyAdmin).** Na pasta do projeto, rode um comando por script, seguindo a mesma ordem acima. Troque os valores entre `< >` pelos da sua máquina:

```bash
# Estrutura (cria o banco e as tabelas)
C:\xampp\mysql\bin\mysql.exe -u <usuario> -p -P <porta> < database\migrations\<script>.sql

# Seeds, views e procedures: informe o nome do banco antes do "<"
C:\xampp\mysql\bin\mysql.exe -u <usuario> -p -P <porta> <banco> < database\seeds\<script>.sql
C:\xampp\mysql\bin\mysql.exe -u <usuario> -p -P <porta> <banco> < database\views\<script>.sql
C:\xampp\mysql\bin\mysql.exe -u <usuario> -p -P <porta> <banco> < database\procedures\<script>.sql
```

- `<usuario>`: usuário do MySQL (no XAMPP, normalmente `root`).
- `-p`: pede a senha. Se o usuário não tiver senha, é só apertar Enter.
- `<porta>`: a mesma porta mostrada no topo do phpMyAdmin (ex.: 3306).
- `<banco>`: o nome do banco criado pela migração.
- `<script>`: o arquivo da pasta, na ordem da numeração.

### 4. Configurar o projeto

Copie `config/config.example.php` para `config/config.php` (esse arquivo não vai para o Git; cada integrante tem o seu) e confira:

| Configuração | Padrão | Quando mudar |
|---|---|---|
| `BASE_URL` | `/SGCB/` | Se a pasta no `htdocs` tiver outro nome. Sempre com barra no início e no fim. |
| `DB_HOST`, `DB_PORT` | `127.0.0.1`, `3306` | Se o MySQL do XAMPP estiver em outra porta. **Use a mesma porta que aparece no topo do phpMyAdmin**; quando a 3306 já está ocupada, o XAMPP costuma usar 3307 ou 3308. |
| `DB_NAME` | `sgcb` | Se o banco tiver outro nome. |
| `DB_USER`, `DB_PASS` | `root`, vazio | Se o seu MySQL tiver senha ou outro usuário. |

### 5. Acessar

1. Abra `http://localhost/SGCB/` (se o Apache estiver em outra porta, inclua-a: `http://localhost:8080/SGCB/`).
2. Entre com o usuário inicial. **As credenciais são repassadas internamente à equipe.**
3. Depois do login você cai na **Visão Geral**. Use o menu lateral para navegar; "Sair" encerra a sessão.
4. Para editar ou executar ações, abra o item na listagem: os botões ficam no rodapé do diálogo de detalhes.

### Problemas comuns

| Sintoma | Causa provável | Como resolver |
|---|---|---|
| "Não foi possível conectar ao banco de dados" no login | MySQL desligado, banco não criado ou dados errados na configuração | Ligue o MySQL no XAMPP, rode os scripts do passo 3 e confira o `config.php`. |
| O sistema entra, mas o banco não aparece no phpMyAdmin (ou o contrário) | Há **dois servidores MySQL** na máquina e a configuração aponta para um diferente do phpMyAdmin | Deixe `DB_PORT` igual à porta mostrada no topo do phpMyAdmin. |
| "E-mail ou senha inválidos" com o usuário inicial | Seed 001 não executado, usuário desativado ou senha alterada | Rode o seed 001 e confira o usuário no banco. |
| "Sua sessão foi encerrada…" ao navegar | O usuário logado foi apagado ou desativado | Entre com um usuário ativo. |
| Página "Not Found" | `BASE_URL` diferente do nome da pasta ou `mod_rewrite` desligado no Apache | Ajuste a `BASE_URL` e confirme que o `mod_rewrite` está ativo. |
| Tela sem estilo ou sem ícones | Arquivos não encontrados (geralmente `BASE_URL` errada) | Ajuste a `BASE_URL` e recarregue com `Ctrl + F5`. |
| Aviso amarelo "Não foi possível carregar…" nas telas | O servidor não respondeu: banco fora do ar, `mod_rewrite` desligado ou `BASE_URL` errada | Confira o MySQL, o `mod_rewrite` e a `BASE_URL`. |
| Cadastro de cliente diz que não há condição de pagamento | Seed 002 não executado | Rode o seed 002 (passo 3). |
| `http://localhost/SGCB/` abre outra coisa ou o login responde "405" | Outro programa (ex.: IIS, nginx, Skype) usa a porta 80 e o Apache subiu em outra porta | Veja a porta do Apache no painel do XAMPP (coluna "Port(s)") e acesse `http://localhost:PORTA/SGCB/`. |
| Botões novos não aparecem depois de atualizar o projeto | Navegador com arquivos antigos em cache | Recarregue com `Ctrl + F5`. |
| "Você não tem permissão para esta ação." | O perfil do usuário logado não tem essa permissão | Entre com um administrador ou peça a liberação da permissão. |
| "Sessão expirada. Recarregue a página e tente novamente." ao salvar | Novo login em outra aba ou sessão expirada | Recarregue a página. |
| O seed 003 para com "Duplicate entry" | Os dados de exemplo já estão no banco | Nada foi gravado. Para recomeçar, apague o banco e rode tudo de novo. |

---

## Fluxo de trabalho no Git

- `main` — versão estável; recebe a `Develop` por Pull Request quando uma etapa estiver pronta.
- `Develop` — branch de integração; recebe as `feature/<nome>` por Pull Request.
- `feature/<nome>` — uma branch por funcionalidade, sempre criada a partir da `Develop` atualizada.
- Commits com a data e uma descrição curta da entrega (ex.: `24/10/2026 - Commit Página Clientes`).
