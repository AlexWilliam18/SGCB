<?php
/**
 * logout.php — encerra a sessão do usuário e volta para o login.
 *
 * Finalidade: botão "Sair" do menu lateral (public/admin/dashboard.php).
 * Depende de: src/bootstrap.php (sessão, Autenticacao).
 * Observação: exige o token CSRF na URL, para que um link externo não deslogue o usuário.
 */

require __DIR__ . '/../src/bootstrap.php';

if (Autenticacao::csrfValido($_GET['csrf'] ?? null)) {
    Autenticacao::sair();
}

header('Location: ' . BASE_URL . 'login.php');
exit;
