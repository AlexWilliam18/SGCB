<?php
/**
 * Dados da própria Casa Barretto (tabela empresas) e configurações dos contratos
 * (tabela preferencias_contrato, 1:1 com a empresa). Usados no cabeçalho e na numeração dos contratos.
 */

final class Empresa extends Modelo
{
    /** Valores usados quando a empresa ainda não tem preferências gravadas. */
    private const PREFERENCIAS_PADRAO = [
        'numeracao_automatica'    => true,
        'prefixo_contrato'        => 'CT-',
        'proximo_numero_contrato' => 1,
        'prefixo_locacao'         => 'LOC-',
        'proximo_numero_locacao'  => 1,
        'condicoes_gerais'        => null,
        'regra_vencimento'        => 'DATA_INICIO',
        'dias_alerta_vencimento'  => 7,
        'contagem_diarias'        => 'CORRIDOS',
        'moeda'                   => 'BRL',
        'casas_decimais'          => 2,
        'formato_pdf'             => 'A4',
        'exibir_produtos'         => true,
        'exibir_diarias'          => true,
        'exibir_subtotais'        => true,
        'exibir_total'            => true,
    ];

    /** Campos de texto da empresa → tamanho máximo. */
    private const CAMPOS = [
        'razao_social' => 200, 'nome_fantasia' => 200, 'email' => 254, 'telefone' => 25,
        'rua' => 200, 'numero' => 20, 'bairro' => 100, 'cidade' => 100, 'complemento' => 150,
    ];

    /** A empresa do sistema (o sistema atende uma só: a primeira cadastrada) ou null. */
    public static function obterEmpresa(): ?array
    {
        $empresa = self::pdo()->query(
            'SELECT id, logo_arquivo_id, razao_social, nome_fantasia, cnpj, email, telefone, cep, rua, numero,
                    bairro, cidade, uf, complemento, atualizado_em
               FROM empresas ORDER BY id LIMIT 1'
        )->fetch();

        if (!$empresa) {
            return null;
        }
        $empresa['id'] = (int) $empresa['id'];

        return $empresa;
    }

    /**
     * Cadastra ou atualiza os dados da empresa.
     *
     * @return int id da empresa.
     */
    public static function salvarEmpresa(array $dados, int $usuarioId): int
    {
        $erros  = [];
        $campos = [];
        foreach (self::CAMPOS as $campo => $maximo) {
            $campos[$campo] = self::campoTexto($dados, $campo, $maximo, $erros, $campo === 'razao_social');
        }
        $campos['cnpj']     = self::somenteDigitos($dados['cnpj'] ?? '') ?: null;
        $campos['cep']      = self::somenteDigitos($dados['cep'] ?? '') ?: null;
        $campos['telefone'] = self::somenteDigitos($campos['telefone']) ?: null;
        $campos['uf']       = strtoupper(trim((string) ($dados['uf'] ?? ''))) ?: null;

        if ($campos['cnpj'] !== null && !Cliente::validarCpfCnpj($campos['cnpj'])) {
            $erros['cnpj'] = 'CNPJ inválido.';
        }
        if ($campos['cep'] !== null && strlen($campos['cep']) !== 8) {
            $erros['cep'] = 'Informe um CEP com 8 dígitos.';
        }
        if ($campos['uf'] !== null && !preg_match('/^[A-Z]{2}$/', $campos['uf'])) {
            $erros['uf'] = 'Informe a UF com 2 letras.';
        }
        if ($campos['email'] !== null && !filter_var($campos['email'], FILTER_VALIDATE_EMAIL)) {
            $erros['email'] = 'Informe um e-mail válido.';
        }
        self::lancarSeErros($erros);

        return self::transacao(static function (PDO $pdo) use ($campos, $usuarioId): int {
            $atual   = self::obterEmpresa();
            $valores = $campos + ['por' => $usuarioId, 'agora' => self::agora()];
            $lista   = implode(', ', array_keys($campos));

            if ($atual === null) {
                $pdo->prepare(
                    "INSERT INTO empresas ($lista, atualizado_por, atualizado_em)
                     VALUES (:" . implode(', :', array_keys($campos)) . ', :por, :agora)'
                )->execute($valores);
                $id = (int) $pdo->lastInsertId();
            } else {
                $id   = $atual['id'];
                $sets = implode(', ', array_map(static fn (string $c): string => "$c = :$c", array_keys($campos)));
                $pdo->prepare("UPDATE empresas SET $sets, atualizado_por = :por, atualizado_em = :agora WHERE id = :id")
                    ->execute($valores + ['id' => $id]);
            }
            self::auditar($usuarioId, 'empresas', $id, $atual === null ? 'CRIAR' : 'ALTERAR', $campos);

            return $id;
        });
    }

    /** Preferências dos contratos da empresa (com os valores padrão quando não há registro). */
    public static function obterPreferencias(int $empresaId): array
    {
        $consulta = self::pdo()->prepare('SELECT * FROM preferencias_contrato WHERE empresa_id = :id');
        $consulta->execute(['id' => $empresaId]);
        $linha = $consulta->fetch() ?: [];

        // Campos vazios no banco caem no padrão; os tipos são normalizados (int/bool).
        $preferencias = ['empresa_id' => $empresaId];
        foreach (self::PREFERENCIAS_PADRAO as $campo => $padrao) {
            $valor = $linha[$campo] ?? null;
            if ($valor === null) {
                $preferencias[$campo] = $padrao;
            } elseif (is_bool($padrao)) {
                $preferencias[$campo] = (bool) $valor;
            } elseif (is_int($padrao)) {
                $preferencias[$campo] = (int) $valor;
            } else {
                $preferencias[$campo] = $valor;
            }
        }

        return $preferencias;
    }

    /** Grava as preferências (só os campos enviados; os outros mantêm o valor atual). */
    public static function salvarPreferencias(int $empresaId, array $dados, int $usuarioId): void
    {
        $atual = self::obterPreferencias($empresaId);
        $erros = [];

        $novas = $atual;
        foreach (self::PREFERENCIAS_PADRAO as $campo => $padrao) {
            if (!array_key_exists($campo, $dados)) {
                continue;
            }
            if (is_bool($padrao)) {
                $novas[$campo] = (bool) self::campoBool($dados, $campo);
            } elseif (is_int($padrao)) {
                $novas[$campo] = self::campoInteiro($dados, $campo, $erros, true, $campo === 'casas_decimais' ? 0 : 1, $campo === 'casas_decimais' ? 4 : null);
            } else {
                $novas[$campo] = self::campoTexto($dados, $campo, $campo === 'condicoes_gerais' ? 65535 : 20, $erros);
            }
        }
        foreach (['regra_vencimento' => ['DIA_FIXO', 'DATA_INICIO'], 'contagem_diarias' => ['CORRIDOS', 'UTEIS'], 'formato_pdf' => ['A4', 'CARTA']] as $campo => $opcoes) {
            if (array_key_exists($campo, $dados)) {
                $novas[$campo] = self::campoOpcao($dados, $campo, $opcoes, $erros, true);
            }
        }
        self::lancarSeErros($erros);

        self::transacao(static function () use ($empresaId, $novas, $usuarioId): void {
            self::gravarPreferencias($empresaId, $novas);
            self::auditar($usuarioId, 'preferencias_contrato', $empresaId, 'ALTERAR', $novas);
        });
    }

    /**
     * Reserva o próximo número de contrato ou de locação (ex.: 'CT-000001').
     * Precisa ser chamado dentro de uma transação: a linha das preferências fica
     * travada (FOR UPDATE) até o fim, então dois contratos nunca recebem o mesmo número.
     *
     * @param string $tipo 'contrato' ou 'locacao'.
     */
    public static function reservarNumero(int $empresaId, string $tipo): string
    {
        $pdo = self::pdo();
        if (!$pdo->inTransaction()) {
            throw new LogicException('Empresa::reservarNumero precisa de uma transação aberta.');
        }

        $prefixoCampo = $tipo === 'locacao' ? 'prefixo_locacao' : 'prefixo_contrato';
        $numeroCampo  = $tipo === 'locacao' ? 'proximo_numero_locacao' : 'proximo_numero_contrato';
        $colunaUnica  = $tipo === 'locacao' ? 'numero_locacao' : 'numero_contrato';

        $consulta = $pdo->prepare('SELECT 1 FROM preferencias_contrato WHERE empresa_id = :id FOR UPDATE');
        $consulta->execute(['id' => $empresaId]);
        if ($consulta->fetchColumn() === false) {
            self::gravarPreferencias($empresaId, self::obterPreferencias($empresaId));
            $consulta->execute(['id' => $empresaId]); // trava a linha recém-criada
        }

        $preferencias = self::obterPreferencias($empresaId);
        $prefixo      = (string) $preferencias[$prefixoCampo];
        $proximo      = max(1, (int) $preferencias[$numeroCampo]);

        // Pula números já usados (ex.: um contrato antigo com número digitado à mão).
        $existe = $pdo->prepare("SELECT 1 FROM contratos WHERE $colunaUnica = :numero");
        do {
            $numero = $prefixo . str_pad((string) $proximo, 6, '0', STR_PAD_LEFT);
            $existe->execute(['numero' => $numero]);
            $proximo++;
        } while ($existe->fetchColumn() !== false);

        $pdo->prepare("UPDATE preferencias_contrato SET $numeroCampo = :proximo WHERE empresa_id = :id")
            ->execute(['proximo' => $proximo, 'id' => $empresaId]);

        return $numero;
    }

    /** INSERT ou UPDATE da linha de preferências (booleans como 1/0). */
    private static function gravarPreferencias(int $empresaId, array $preferencias): void
    {
        $valores = [];
        foreach (array_keys(self::PREFERENCIAS_PADRAO) as $campo) {
            $valor           = $preferencias[$campo];
            $valores[$campo] = is_bool($valor) ? (int) $valor : $valor;
        }

        $colunas = array_keys($valores);
        $updates = implode(', ', array_map(static fn (string $c): string => "$c = VALUES($c)", $colunas));
        self::pdo()->prepare(
            'INSERT INTO preferencias_contrato (empresa_id, ' . implode(', ', $colunas) . ')
             VALUES (:empresa_id, :' . implode(', :', $colunas) . ")
             ON DUPLICATE KEY UPDATE $updates"
        )->execute($valores + ['empresa_id' => $empresaId]);
    }
}
