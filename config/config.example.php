<?php
/**
 * Modelo de configuração. Copie para config/config.php e ajuste à sua máquina
 * (o config.php fica fora do Git, cada integrante tem o seu).
 */

/*
 * URL base do projeto, sempre com barra no início e no fim.
 * Corresponde ao nome da pasta do projeto dentro do htdocs:
 *   - htdocs/SGCB: '/SGCB/'
 *   - htdocs/sgcb: '/sgcb/'
 *   - projeto direto na raiz do htdocs: '/'
 * Todos os caminhos de CSS, JS e imagens são montados a partir dela, para que funcionem
 * em qualquer nível de pasta (public/ e public/admin/<modulo>/).
 * Não inclua "public/": o .htaccess da raiz já redireciona as requisições para essa pasta.
 */
define('BASE_URL', '/SGCB/');

/*
 * Conexão com o MySQL/MariaDB do XAMPP (usada por src/Database/Conexao.php).
 * Padrão do XAMPP: usuário root sem senha. O banco "sgcb" é criado pela
 * migração database/migrations/001_create_schema_casa_barretto.sql.
 */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'sgcb');
define('DB_USER', 'root');
define('DB_PASS', '');
