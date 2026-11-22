<?php

/**
 * Disponibilidade futura dos contêineres (previsão por período).
 *
 * Regra única, usada pela consulta "Consultar disponibilidade" (Contratos de Locação),
 * pela linha do tempo "Próximas disponibilidades" (Produtos), pelo cadastro de contrato
 * e pela emissão (UnidadeFisica::livresNoPeriodo delega para cá).
 * A conta é feita por unidade física: uma unidade só conta se ficar livre no período
 * inteiro. Intervalos da agenda são [início, fim), ou seja, o fim não está incluído.
 * Detalhes e exemplos em docs/disponibilidade-futura.md.
 */
final class Disponibilidade extends Modelo
{
    /**
     * Confere as datas da consulta (formato AAAA-MM-DD, data real e fim >= início).
     * Com $futuro = true, o início também não pode ser antes de hoje.
     *
     * @throws ErroValidacao (422) quando alguma data for inválida
     */
    public static function validarPeriodo(string $inicio, ?string $fim, bool $futuro = false): void
    {
        foreach ([$inicio, $fim] as $data) {
            if ($data === null) continue;
            if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $data)) {
                throw new ErroValidacao('Informe datas no formato AAAA-MM-DD.', [], 422);
            }
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
            if (!$d || $d->format('Y-m-d') !== $data || $data < '1000-01-01' || $data > '9998-12-31') {
                throw new ErroValidacao('Informe datas válidas até 31/12/9998.', [], 422);
            }
        }
        if (($fim !== null && $fim < $inicio) || ($futuro && $inicio < self::hoje())) {
            throw new ErroValidacao('O início deve ser a partir de hoje e o fim não pode preceder o início.', [], 422);
        }
    }

    /**
     * Carrega as unidades do produto, cada uma com a sua agenda (reservas, locações e
     * manutenções). Lê só dados operacionais, sem dados de cliente.
     * Dentro de uma transação (emissão de contrato) usa FOR UPDATE, para ler a situação
     * atual depois das travas e não reservar a mesma unidade duas vezes.
     */
    public static function carregar(int $produtoId): array
    {
        $pdo = self::pdo();
        $trava = $pdo->inTransaction() ? ' FOR UPDATE' : '';
        $c = $pdo->prepare('SELECT id, estado_operacional FROM unidades_fisicas WHERE produto_id = ? ORDER BY id' . $trava);
        $c->execute([$produtoId]);
        $unidades = [];
        foreach ($c->fetchAll() as $u) {
            $u['agenda'] = [];
            $unidades[(int) $u['id']] = $u;
        }
        $c = $pdo->prepare('SELECT a.unidade_id, a.inicio, a.fim, a.status, a.devolvida_em,
                                   m.status AS manutencao_status
                            FROM agenda_unidades a LEFT JOIN manutencoes m ON m.id = a.manutencao_id
                           WHERE a.produto_id = ? ORDER BY a.id' . $trava);
        $c->execute([$produtoId]);
        foreach ($c->fetchAll() as $a) {
            if (isset($unidades[(int) $a['unidade_id']])) $unidades[(int) $a['unidade_id']]['agenda'][] = $a;
        }
        return array_values($unidades);
    }

    /**
     * Calcula quais unidades atendem o período [inicio, fim), já somando o tempo de
     * preparação (DISPONIBILIDADE_PREPARACAO_HORAS) depois de cada ocupação.
     * Não consulta o banco (recebe o resultado de carregar()), por isso pode ser testada sozinha.
     * Não soma saldos de dias distintos: a mesma unidade precisa cobrir o período todo.
     *
     * Retorna:
     *   ids           → unidades livres (usado na emissão; a consulta pública remove)
     *   disponivel    → quantidade prevista livre no período
     *   ja_livres     → livres hoje, sem depender de nada
     *   condicionadas → livres só se a devolução/conclusão de manutenção acontecer no prazo
     *   dependencias  → quantas dependem de devolução e quantas de manutenção
     */
    public static function avaliar(array $unidades, string $inicio, string $fim, ?string $agora = null): array
    {
        $agora ??= self::agora();
        $preparo = max(0, (int) DISPONIBILIDADE_PREPARACAO_HORAS) * 3600;
        $de = strtotime($inicio);
        $ate = min(strtotime(UnidadeFisica::FIM_INDETERMINADO), strtotime($fim) + $preparo);
        $livres = [];
        $condicionadas = ['devolucao' => 0, 'manutencao' => 0];
        $jaLivres = 0;
        foreach ($unidades as $u) {
            if ($u['estado_operacional'] === 'INATIVA') continue;
            $bloqueada = false;
            $origemAtual = false;
            $depende = [];
            foreach ($u['agenda'] as $a) {
                if ($a['status'] === 'CANCELADA' || $a['manutencao_status'] === 'CANCELADA') continue;
                $manutencao = $a['status'] === 'MANUTENCAO';
                $encerrada = $a['status'] === 'DEVOLVIDA' || ($manutencao && $a['manutencao_status'] === 'CONCLUIDA');
                $fimReal = $a['devolvida_em'] ?: $a['fim'];
                $fimBloqueio = $encerrada ? $fimReal : $a['fim'];
                // Uma previsão vencida sem devolução/conclusão não libera a unidade.
                if (!$encerrada && (!$fimBloqueio || $fimBloqueio <= $agora)) $fimBloqueio = UnidadeFisica::FIM_INDETERMINADO;
                $fimTs = $fimBloqueio ? min(strtotime(UnidadeFisica::FIM_INDETERMINADO), strtotime($fimBloqueio) + $preparo) : PHP_INT_MAX;
                if (($u['estado_operacional'] === 'LOCADA' && $a['status'] === 'EM_USO') ||
                    ($u['estado_operacional'] === 'MANUTENCAO' && $manutencao && !$encerrada)) $origemAtual = true;
                if (strtotime($a['inicio']) < $ate && $fimTs > $de) {
                    $bloqueada = true;
                    break;
                }
                if (!$encerrada && $fimTs <= $de) $depende[$manutencao ? 'manutencao' : 'devolucao'] = true;
            }
            if ($bloqueada || (in_array($u['estado_operacional'], ['LOCADA', 'MANUTENCAO'], true) && !$origemAtual)) continue;
            $livres[] = (int) $u['id'];
            if (!$depende && $u['estado_operacional'] === 'DISPONIVEL') $jaLivres++;
            foreach ($depende as $tipo => $_) $condicionadas[$tipo]++;
        }
        return ['ids' => $livres, 'disponivel' => count($livres), 'ja_livres' => $jaLivres,
                'condicionadas' => count($livres) - $jaLivres, 'dependencias' => $condicionadas];
    }

    /**
     * Consulta da tela "Consultar disponibilidade": o produto atende a quantidade no período?
     * Se não atender, procura a próxima data de início (mantendo a mesma duração) em que
     * atende, olhando até DISPONIBILIDADE_HORIZONTE_DIAS dias para frente.
     * Não cria reserva nem grava nada.
     */
    public static function consultar(int $produtoId, string $inicio, string $fim, int $quantidade): array
    {
        self::validarPeriodo($inicio, $fim, true);
        if ($quantidade < 1) throw new ErroValidacao('Informe uma quantidade inteira positiva.', [], 422);
        $p = self::pdo()->prepare('SELECT id, codigo, nome FROM produtos WHERE id = ? AND ativo = 1');
        $p->execute([$produtoId]);
        $produto = $p->fetch();
        if (!$produto) throw new ErroValidacao('Selecione um produto ativo.', [], 422);
        $unidades = self::carregar($produtoId);
        $agora = self::agora();
        $calcular = static function (string $de, string $ate) use ($unidades, $agora): array {
            [$a, $b] = Produto::periodoAgenda($de, $ate);
            $r = self::avaliar($unidades, $a, $b, $agora);
            unset($r['ids']); // A consulta pública não expõe unidades nem contratos.
            return $r;
        };
        $resultado = $calcular($inicio, $fim);
        $duracao = (new DateTimeImmutable($inicio))->diff(new DateTimeImmutable($fim))->days;
        $limite = min(new DateTimeImmutable('9998-12-31'), (new DateTimeImmutable($inicio))->modify('+' . max(1, (int) DISPONIBILIDADE_HORIZONTE_DIAS) . ' days'));
        $proxima = null;
        // Só as fronteiras dos bloqueios podem aumentar a quantidade disponível.
        foreach (self::datasRelevantes($unidades, $inicio, $limite->format('Y-m-d')) as $data) {
            if ($resultado['disponivel'] >= $quantidade) break;
            $ate = (new DateTimeImmutable($data))->modify("+$duracao days")->format('Y-m-d');
            if ($ate > '9998-12-31') continue;
            $r = $calcular($data, $ate);
            if ($r['disponivel'] >= $quantidade) {
                $proxima = ['inicio' => $data, 'fim' => $ate] + $r;
                break;
            }
        }
        return ['produto' => $produto, 'inicio' => $inicio, 'fim' => $fim, 'quantidade' => $quantidade,
                'atende' => $resultado['disponivel'] >= $quantidade, 'previsao' => true,
                'proxima' => $proxima, 'limite_busca' => $limite->format('Y-m-d'),
                'preparacao_horas' => max(0, (int) DISPONIBILIDADE_PREPARACAO_HORAS)] + $resultado;
    }

    /**
     * Datas em que a quantidade disponível pode mudar: início de cada ocupação (menos a
     * preparação) e liberação de cada ocupação (mais a preparação), dentro de [inicio, fim].
     * Assim a busca da próxima opção testa só essas datas, e não todos os dias do ano.
     */
    public static function datasRelevantes(array $unidades, string $inicio, string $fim): array
    {
        $datas = [$inicio => true];
        foreach ($unidades as $u) foreach ($u['agenda'] as $a) {
            if ($a['inicio']) {
                $data = (new DateTimeImmutable($a['inicio']))->modify('-' . max(0, (int) DISPONIBILIDADE_PREPARACAO_HORAS) . ' hours')->format('Y-m-d');
                if ($data >= $inicio && $data <= $fim) $datas[$data] = true;
            }
            $liberacao = $a['devolvida_em'] ?: $a['fim'];
            if (!$liberacao || $liberacao >= '9998-01-01') continue;
            foreach ([$liberacao] as $fronteira) {
                $d = (new DateTimeImmutable($fronteira))->modify('+' . max(0, (int) DISPONIBILIDADE_PREPARACAO_HORAS) . ' hours');
                // Contratos começam à meia-noite: arredonda liberação com hora para o dia seguinte.
                if ($d->format('H:i:s') !== '00:00:00') $d = $d->modify('+1 day')->setTime(0, 0);
                $data = $d->format('Y-m-d');
                if ($data >= $inicio && $data <= $fim) $datas[$data] = true;
            }
        }
        $lista = array_keys($datas);
        sort($lista);
        return $lista;
    }

    /**
     * Linha do tempo "Próximas disponibilidades" (detalhes do produto): para cada data em que
     * algo muda, quantas unidades estariam livres para uma locação de um dia.
     * Datas seguidas com a mesma quantidade são omitidas.
     */
    public static function proximas(int $produtoId): array
    {
        $unidades = self::carregar($produtoId);
        $hoje = self::hoje();
        $limite = date('Y-m-d', strtotime('+' . max(1, (int) DISPONIBILIDADE_HORIZONTE_DIAS) . ' days'));
        $linhas = [];
        $anterior = null;
        foreach (self::datasRelevantes($unidades, $hoje, $limite) as $data) {
            [$de, $ate] = Produto::periodoAgenda($data, $data);
            $r = self::avaliar($unidades, $de, $ate);
            if ($anterior === $r['disponivel']) continue;
            $anterior = $r['disponivel'];
            $linhas[] = ['data' => $data, 'disponivel' => $r['disponivel'], 'condicionadas' => $r['condicionadas']];
        }
        return ['items' => $linhas, 'limite_busca' => $limite];
    }
}
