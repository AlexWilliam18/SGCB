<?php
/**
 * Parâmetros da disponibilidade futura (src/Models/Disponibilidade.php).
 * Carregado pelo bootstrap.php depois do config.php: para mudar, defina as
 * constantes no config/config.php (este arquivo só define o que ainda não existe).
 */

// Horas de preparação do contêiner depois de cada devolução/manutenção antes de nova locação.
defined('DISPONIBILIDADE_PREPARACAO_HORAS') || define('DISPONIBILIDADE_PREPARACAO_HORAS', 24);

// Até quantos dias à frente a consulta procura a próxima data disponível.
defined('DISPONIBILIDADE_HORIZONTE_DIAS') || define('DISPONIBILIDADE_HORIZONTE_DIAS', 365);
