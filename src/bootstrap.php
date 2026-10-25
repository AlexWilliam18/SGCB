<?php
/**
 * Inicialização comum: configuração, classes e sessão.
 * Toda página de public/ inclui este arquivo antes de qualquer saída.
 */

// Usa o config.php local; se ainda não existir, o modelo versionado (o projeto abre logo após o clone).
$configLocal = __DIR__ . '/../config/config.php';
require_once is_file($configLocal) ? $configLocal : __DIR__ . '/../config/config.example.php';
unset($configLocal);

// Classes do sistema (sem Composer ainda, então carregadas manualmente).
require_once __DIR__ . '/Database/Conexao.php';
require_once __DIR__ . '/Auth/Autenticacao.php';

// Cookie da sessão fora do alcance do JavaScript e restrito à pasta do projeto.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_path'     => BASE_URL,
        'use_strict_mode' => true,
    ]);
}
