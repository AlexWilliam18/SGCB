<?php
/**
 * config.example.php — modelo de configuração do SGCB.
 *
 * Finalidade: definir as constantes globais do sistema (por enquanto, só a BASE_URL).
 * Como usar:  copie este arquivo para config/config.php e ajuste os valores à sua máquina.
 *             O config.php está no .gitignore, então cada integrante tem o seu.
 * Depende de: nada.
 * Usado por:  src/bootstrap.php (carrega config.php; se ele não existir, carrega este modelo).
 */

/*
 * URL base do projeto, sempre com barra no início e no fim.
 * Corresponde ao nome da pasta do projeto dentro do htdocs:
 *   - htdocs/SGCB  → '/SGCB/'
 *   - htdocs/sgcb  → '/sgcb/'
 *   - projeto direto na raiz do htdocs → '/'
 * Todos os caminhos de CSS, JS e imagens são montados a partir dela, para que funcionem
 * em qualquer nível de pasta (public/ e public/admin/<modulo>/).
 * Não inclua "public/": o .htaccess da raiz já redireciona as requisições para essa pasta.
 */
define('BASE_URL', '/SGCB/');
