<?php
/**
 * Botão "Sair" do menu: encerra a sessão e volta para o login.
 * Exige o token CSRF na URL, para que um link externo não deslogue o usuário.
 */

require __DIR__ . '/../src/bootstrap.php';

if (Autenticacao::csrfValido($_GET['csrf'] ?? null)) {
    Autenticacao::sair();
}

header('Location: ' . BASE_URL . 'login.php');
exit;
