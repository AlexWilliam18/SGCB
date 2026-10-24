<?php
/**
 * bootstrap.php — inicialização comum a todas as páginas do SGCB.
 *
 * Finalidade: carregar a configuração (BASE_URL e, futuramente, banco/sessão) num único ponto,
 *             para que nenhuma página precise saber onde ficam os arquivos de config.
 * Depende de: config/config.php (local) ou, na falta dele, config/config.example.php.
 * Usado por:  todas as páginas de public/ (ex.: public/index.php, public/login.php),
 *             sempre como primeira instrução: require __DIR__ . '/../src/bootstrap.php';
 */

/*
 * Configuração: prioriza o config.php local de cada integrante; se ele ainda não foi criado,
 * usa o modelo versionado, para o projeto abrir logo após o clone.
 * __DIR__ garante o caminho certo independentemente da página que incluiu este arquivo.
 */
$configLocal = __DIR__ . '/../config/config.php';
require_once is_file($configLocal) ? $configLocal : __DIR__ . '/../config/config.example.php';
unset($configLocal);
