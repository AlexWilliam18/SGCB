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
        $usuario = Api::usuario();

        // O valor das locações é dado financeiro: só vai para quem pode consultar o Financeiro
        // (achado A02). Os demais veem a tabela com "—" na coluna Valor.
        $veValores = Usuario::temPermissao($usuario['id'], 'financeiro', 'visualizar');

        return Dashboard::resumo($veValores);
    }
}
