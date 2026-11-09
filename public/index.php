<?php
/**
 * Raiz do site: manda para o dashboard (se logado) ou para o login.
 */

require __DIR__ . '/../src/bootstrap.php';

// Só vai para o dashboard se o usuário da sessão for confirmado no banco.
$destino = Autenticacao::usuarioValido() !== null ? 'admin/dashboard.php' : 'login.php';
header('Location: ' . BASE_URL . $destino);
exit;
