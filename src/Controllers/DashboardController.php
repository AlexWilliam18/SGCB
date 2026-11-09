<?php
/**
 * API da Visão Geral (admin/dashboard.php: dashboard.js).
 *
 *   GET api/dashboard   indicadores, gráficos, rankings e últimas locações
 */

final class DashboardController
{
    public static function resumo(): array
    {
        Api::usuario();

        return Dashboard::resumo();
    }
}
