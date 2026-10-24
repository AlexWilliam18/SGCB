# Login

## Objetivo
Tela de acesso à área administrativa do SGCB.

## Status
Somente front-end. A validação de credenciais é **simulada** em `login.js`
(`admin@casabarretto.local` / `admin123`) e deve ser substituída pelo back-end PHP
(`password_verify`, sessão e token CSRF) na branch `feature/login-back`.

## Campos
| Campo | Tipo | Regras (front) |
|---|---|---|
| E-mail | `email` | obrigatório |
| Senha | `password` | obrigatório; botão para mostrar/ocultar |
| Lembrar de mim | checkbox | salva o e-mail no `localStorage` |

## Comportamento
- Credenciais inválidas: mensagem de erro acima do botão "Entrar".
- Credenciais válidas: redireciona para `DashBoard.html` (página ainda não existe).
- "Esqueceu a senha?": abre diálogo orientando a contatar o administrador.

## Arquivos
- `login.html`, `login.css`, `login.js`
- `img/login/` (background, foto da fachada, ícones)

## Como testar
1. Abrir `http://localhost/SGCB/login.html` (XAMPP).
2. Enviar com dados errados → mensagem de erro.
3. Enviar com as credenciais de desenvolvimento → redireciona para `DashBoard.html`.
4. Testar o botão de mostrar senha e o "Lembrar de mim" (recarregar a página).
