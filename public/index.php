<?php
/**
 * index.php — ponto de entrada do SGCB (http://localhost/SGCB/).
 *
 * Finalidade: mandar quem acessa a raiz para o dashboard (se logado) ou para o login.
 * Depende de: src/bootstrap.php (BASE_URL, sessão, Autenticacao).
 * Usado por:  o Apache, como página padrão da pasta public/ (o .htaccess da raiz aponta para ela).
 */

require __DIR__ . '/../src/bootstrap.php';

// Só vai para o dashboard se o usuário da sessão for confirmado no banco.
$destino = Autenticacao::usuarioValido() !== null ? 'admin/dashboard.php' : 'login.php';
header('Location: ' . BASE_URL . $destino);
exit;
