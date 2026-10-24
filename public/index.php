<?php
/**
 * index.php — ponto de entrada do SGCB (http://localhost/SGCB/).
 *
 * Finalidade: redirecionar quem acessa a raiz do sistema para a tela de login.
 * Depende de: src/bootstrap.php (constante BASE_URL).
 * Usado por:  o Apache, como página padrão da pasta public/ (o .htaccess da raiz aponta para ela).
 */

require __DIR__ . '/../src/bootstrap.php';

// Sem autenticação ainda, todo acesso à raiz vai para o login.
header('Location: ' . BASE_URL . 'login.php');
exit;
