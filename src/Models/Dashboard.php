<?php
/**
 * Dados da tela Visão Geral (dashboard.js): indicadores, gráficos dos últimos 12 meses,
 * rankings e últimas locações. Só leitura; contratos em RASCUNHO não entram nas contas.
 */

final class Dashboard extends Modelo
{
    /** Quantos meses os gráficos mostram (incluindo o atual). */
    private const MESES = 12;

    /**
     * Tudo o que o dashboard.js espera de /api/dashboard.
     *
     * @return array{metrics: array, monthly_revenue: array, rental_counts: array,
     *               top_products: array, top_clients: array, recent_contracts: array}
     */
    public static function resumo(): array
    {
        return [
            'metrics'          => self::indicadores(),
            'monthly_revenue'  => self::faturamentoMensal(),
            'rental_counts'    => self::locacoesPorMes(),
            'top_products'     => self::produtosMaisLocados(),
            'top_clients'      => self::clientesQueMaisLocam(),
            'recent_contracts' => self::ultimasLocacoes(),
        ];
    }

    /** Cards: faturamento total (pagamentos não estornados), contratos ativos e contêineres disponíveis/locados. */
    public static function indicadores(): array
    {
        $pdo = self::pdo();

        $faturamento = (float) $pdo->query('SELECT COALESCE(SUM(valor), 0) FROM pagamentos WHERE estornado_em IS NULL')->fetchColumn();
        $contratos   = (int) $pdo->query("SELECT COUNT(*) FROM contratos WHERE status = 'ATIVO'")->fetchColumn();
        $unidades    = $pdo->query(
            "SELECT COALESCE(SUM(estado_operacional = 'DISPONIVEL'), 0) AS disponiveis,
                    COALESCE(SUM(estado_operacional = 'LOCADA'), 0) AS locadas
               FROM unidades_fisicas"
        )->fetch();

        return [
            'revenue_total'    => $faturamento,
            'active_contracts' => $contratos,
            'available_units'  => (int) $unidades['disponiveis'],
            'rented_units'     => (int) $unidades['locadas'],
        ];
    }

    /** Faturamento por mês: [{ month: 'AAAA-MM', value }], meses sem pagamento com 0. */
    public static function faturamentoMensal(): array
    {
        $consulta = self::pdo()->prepare(
            "SELECT DATE_FORMAT(pago_em, '%Y-%m') AS mes, SUM(valor) AS total
               FROM pagamentos WHERE estornado_em IS NULL AND pago_em >= :inicio
              GROUP BY mes"
        );
        $consulta->execute(['inicio' => self::inicioPeriodo()]);

        return self::preencherMeses($consulta->fetchAll(PDO::FETCH_KEY_PAIR), static fn ($v): float => (float) $v);
    }

    /** Contratos emitidos por mês: [{ month: 'AAAA-MM', value }]. */
    public static function locacoesPorMes(): array
    {
        $consulta = self::pdo()->prepare(
            "SELECT DATE_FORMAT(data_emissao, '%Y-%m') AS mes, COUNT(*) AS total
               FROM contratos WHERE status <> 'RASCUNHO' AND data_emissao >= :inicio
              GROUP BY mes"
        );
        $consulta->execute(['inicio' => substr(self::inicioPeriodo(), 0, 10)]);

        return self::preencherMeses($consulta->fetchAll(PDO::FETCH_KEY_PAIR), static fn ($v): int => (int) $v);
    }

    /** Ranking: produtos presentes em mais contratos ({ name, count }). */
    public static function produtosMaisLocados(int $limite = 5): array
    {
        $limite = max(1, min($limite, 50));

        return self::ranking(
            "SELECT p.nome AS name, COUNT(DISTINCT i.contrato_id) AS count
               FROM contrato_itens i
               JOIN contratos c ON c.id = i.contrato_id AND c.status <> 'RASCUNHO'
               JOIN produtos p ON p.id = i.produto_id
              GROUP BY p.id, p.nome ORDER BY count DESC, p.nome LIMIT $limite"
        );
    }

    /** Ranking: clientes com mais contratos ({ name, count }). */
    public static function clientesQueMaisLocam(int $limite = 5): array
    {
        $limite = max(1, min($limite, 50));

        return self::ranking(
            "SELECT p.nome_razao_social AS name, COUNT(*) AS count
               FROM contratos c JOIN pessoas p ON p.id = c.cliente_id
              WHERE c.status <> 'RASCUNHO'
              GROUP BY p.id, p.nome_razao_social ORDER BY count DESC, p.nome_razao_social LIMIT $limite"
        );
    }

    /**
     * Últimas locações: { id, number, client, start, end, days, value, status }.
     * Status da tela: ATIVO que ainda não começou aparece como RESERVADO.
     * O id é usado no dashboard.js para abrir a prévia em admin/locacoes/index.php?ver=ID.
     */
    public static function ultimasLocacoes(int $limite = 15): array
    {
        $limite   = max(1, min($limite, 100));
        $consulta = self::pdo()->prepare(
            "SELECT c.id, c.numero_contrato, p.nome_razao_social AS cliente, c.data_inicio, c.data_fim, c.status,
                    (SELECT COALESCE(SUM(i.subtotal), 0) FROM contrato_itens i WHERE i.contrato_id = c.id)
                      + COALESCE(c.frete_deslocamento, 0) + COALESCE(c.outros_valores, 0) AS valor
               FROM contratos c JOIN pessoas p ON p.id = c.cliente_id
              WHERE c.status <> 'RASCUNHO'
              ORDER BY c.criado_em DESC, c.id DESC
              LIMIT $limite"
        );
        $consulta->execute();
        $hoje = self::hoje();

        return array_map(static function (array $c) use ($hoje): array {
            $status = $c['status'] === 'ATIVO' && $c['data_inicio'] > $hoje ? 'RESERVADO' : $c['status'];

            return [
                'id'      => (int) $c['id'],
                'number'  => $c['numero_contrato'],
                'client'  => $c['cliente'],
                'start'   => $c['data_inicio'],
                'end'     => $c['data_fim'],
                'days'    => $c['data_fim'] !== null ? Contrato::diasLocacao($c['data_inicio'], $c['data_fim']) : null,
                'value'   => (float) $c['valor'],
                'status'  => $status,
            ];
        }, $consulta->fetchAll());
    }

    // -------------------------------------------------------------------------

    /** Primeiro dia do mês, 11 meses atrás (12 meses com o atual). */
    private static function inicioPeriodo(): string
    {
        return date('Y-m-01 00:00:00', strtotime('first day of -' . (self::MESES - 1) . ' month'));
    }

    /** Monta os 12 meses em ordem, colocando 0 onde a consulta não trouxe valor. */
    private static function preencherMeses(array $porMes, callable $converter): array
    {
        $meses = [];
        for ($i = self::MESES - 1; $i >= 0; $i--) {
            $mes     = date('Y-m', strtotime("first day of -$i month"));
            $meses[] = ['month' => $mes, 'value' => $converter($porMes[$mes] ?? 0)];
        }

        return $meses;
    }

    private static function ranking(string $sql): array
    {
        return array_map(
            static fn (array $l): array => ['name' => $l['name'], 'count' => (int) $l['count']],
            self::pdo()->query($sql)->fetchAll()
        );
    }
}
