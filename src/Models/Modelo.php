<?php
/**
 * Base de todos os models (src/Models): acesso ao banco, transações, paginação,
 * auditoria e leitura/validação dos valores que chegam dos formulários.
 *
 * Regras usadas em todos os models:
 * - Valores em dinheiro são calculados em centavos (int), para não acumular erro de float.
 * - BOOLEAN é gravado como 1/0 (o MySQL em modo estrito recusa '' vindo de false).
 * - Datas e horas são geradas pelo PHP (fuso America/Sao_Paulo, definido no bootstrap).
 * - Com prepared statements reais (sem emulação), cada placeholder só pode aparecer
 *   uma vez por SQL; por isso há nomes como :q1, :q2, :q3.
 */

abstract class Modelo
{
    /** Linhas por página nas listagens. */
    protected const POR_PAGINA = 10;

    /** Profundidade de transações aninhadas (um model chamando outro dentro de uma transação). */
    private static int $nivelTransacao = 0;

    protected static function pdo(): PDO
    {
        return Conexao::obter();
    }

    /**
     * Executa $acao em uma transação: confirma se tudo der certo, desfaz se houver exceção.
     * Se já existir uma transação aberta, usa um SAVEPOINT: um erro desfaz só esta parte
     * e quem abriu a transação principal decide se confirma o resto.
     */
    protected static function transacao(callable $acao): mixed
    {
        $pdo = self::pdo();

        if ($pdo->inTransaction()) {
            $ponto = 'sp_sgcb_' . (++self::$nivelTransacao);
            $pdo->exec("SAVEPOINT $ponto");
            try {
                $resultado = $acao($pdo);
                $pdo->exec("RELEASE SAVEPOINT $ponto");
                return $resultado;
            } catch (Throwable $e) {
                $pdo->exec("ROLLBACK TO SAVEPOINT $ponto");
                throw $e;
            } finally {
                self::$nivelTransacao--;
            }
        }

        $pdo->beginTransaction();
        try {
            $resultado = $acao($pdo);
            $pdo->commit();
            return $resultado;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Listagem paginada no formato das telas: { items, total, page, pages }.
     *
     * @param string $colunas Lista do SELECT.
     * @param string $de      "FROM ... WHERE ..." (sem ORDER BY), usado na contagem e na lista.
     * @param string $ordem   Conteúdo do ORDER BY (sempre fixo no código, nunca vindo do usuário).
     */
    protected static function paginar(string $colunas, string $de, array $params, string $ordem, int $pagina, int $porPagina = self::POR_PAGINA): array
    {
        $pdo = self::pdo();

        $conta = $pdo->prepare("SELECT COUNT(*) $de");
        $conta->execute($params);
        $total = (int) $conta->fetchColumn();

        // Página fora do intervalo vai para a mais próxima (ex.: filtro reduziu o total).
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina  = min(max(1, $pagina), $paginas);
        $inicio  = ($pagina - 1) * $porPagina;

        // LIMIT/OFFSET são inteiros calculados aqui, então podem entrar direto no SQL.
        $lista = $pdo->prepare("SELECT $colunas $de ORDER BY $ordem LIMIT $porPagina OFFSET $inicio");
        $lista->execute($params);

        return ['items' => $lista->fetchAll(), 'total' => $total, 'page' => $pagina, 'pages' => $paginas];
    }

    /**
     * Ordem escolhida no filtro "Ordem" das listagens (?ordem=az ou ?ordem=za).
     * Só aceita esses dois valores; qualquer outro usa a ordem padrão da tela.
     * A coluna vem sempre do código, nunca do usuário, então pode entrar direto no SQL.
     *
     * @param string $coluna Coluna de nome usada na ordem alfabética (ex.: "p.nome").
     * @param string $padrao ORDER BY usado quando o filtro está vazio.
     * @param string $id     Coluna de desempate, para a paginação não repetir linhas.
     */
    protected static function ordemAlfabetica(array $filtros, string $coluna, string $padrao, string $id): string
    {
        $ordem = (string) ($filtros['ordem'] ?? '');
        if ($ordem === 'az') {
            return "$coluna ASC, $id ASC";
        }
        if ($ordem === 'za') {
            return "$coluna DESC, $id DESC";
        }
        return $padrao;
    }

    /** Registra uma ação na tabela auditoria (quem, o quê, em qual registro). */
    protected static function auditar(?int $usuarioId, string $entidade, int $registroId, string $acao, array $detalhes = []): void
    {
        Auditoria::registrar($usuarioId, $entidade, $registroId, $acao, $detalhes);
    }

    // -------------------------------------------------------------------------
    // Datas e dinheiro
    // -------------------------------------------------------------------------

    protected static function agora(): string
    {
        return date('Y-m-d H:i:s');
    }

    protected static function hoje(): string
    {
        return date('Y-m-d');
    }

    /** '1234.56' → 123456 centavos. */
    protected static function centavos(string|int|float|null $valor): int
    {
        return (int) round(((float) ($valor ?? 0)) * 100);
    }

    /** 123456 centavos → '1234.56' (formato aceito pelo DECIMAL do banco). */
    protected static function reais(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }

    /** Converte para JSON preservando acentos (campos JSON e snapshots). */
    protected static function json(mixed $valor): string
    {
        return json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function somenteDigitos(?string $valor): string
    {
        return preg_replace('/\D+/', '', (string) $valor);
    }

    /**
     * E-mail no formato nome@dominio.tld. O FILTER_VALIDATE_EMAIL aceita domínio sem extensão
     * (ex.: "nome@empresa"); aqui o domínio precisa ter ponto e extensão de 2+ letras.
     * Mesma regra do Validacao.email (public/assets/js/validacao.js).
     */
    public static function validarEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && preg_match('/@(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,}$/', $email) === 1;
    }

    /**
     * Telefone brasileiro só com dígitos: DDD (11 a 99, sem zero) + fixo de 8 dígitos começando
     * com 2 a 5, ou celular de 9 dígitos começando com 9. Mesma regra do Validacao.telefone.
     */
    public static function validarTelefone(string $digitos): bool
    {
        return preg_match('/^[1-9][1-9](?:[2-5]\d{7}|9\d{8})$/', $digitos) === 1;
    }

    // -------------------------------------------------------------------------
    // Leitura dos campos do formulário.
    // Cada função devolve o valor já normalizado (ou null quando vazio)
    // e, se o valor for inválido, grava a mensagem em $erros[$campo].
    // -------------------------------------------------------------------------

    /** Texto: remove espaços das pontas e confere o tamanho máximo. */
    protected static function campoTexto(array $dados, string $campo, int $maximo, array &$erros, bool $obrigatorio = false): ?string
    {
        $valor = trim((string) ($dados[$campo] ?? ''));

        if ($valor === '') {
            if ($obrigatorio) {
                $erros[$campo] = 'Preencha este campo.';
            }
            return null;
        }
        if (mb_strlen($valor) > $maximo) {
            $erros[$campo] = "Use no máximo $maximo caracteres.";
        }

        return $valor;
    }

    /** Número inteiro dentro do intervalo [$minimo, $maximo]. */
    protected static function campoInteiro(array $dados, string $campo, array &$erros, bool $obrigatorio = false, int $minimo = 0, ?int $maximo = null): ?int
    {
        $valor = trim((string) ($dados[$campo] ?? ''));

        if ($valor === '') {
            if ($obrigatorio) {
                $erros[$campo] = 'Preencha este campo.';
            }
            return null;
        }
        if (!preg_match('/^-?\d+$/', $valor)) {
            $erros[$campo] = 'Informe um número inteiro.';
            return null;
        }

        $numero = (int) $valor;
        if ($numero < $minimo || ($maximo !== null && $numero > $maximo)) {
            $erros[$campo] = $maximo === null ? "Informe um valor a partir de $minimo." : "Informe um valor entre $minimo e $maximo.";
            return null;
        }

        return $numero;
    }

    /**
     * Valor em dinheiro, não negativo. Aceita "1234.56", "1234,56" e "1.234,56".
     * Devolve com duas casas ('1234.56').
     */
    protected static function campoDecimal(array $dados, string $campo, array &$erros, bool $obrigatorio = false): ?string
    {
        $valor = str_replace(['R$', ' ', "\u{00A0}"], '', trim((string) ($dados[$campo] ?? '')));

        if ($valor === '') {
            if ($obrigatorio) {
                $erros[$campo] = 'Preencha este campo.';
            }
            return null;
        }

        // Com vírgula, o formato é o brasileiro: pontos são milhar e a vírgula é decimal.
        if (str_contains($valor, ',')) {
            $valor = str_replace(['.', ','], ['', '.'], $valor);
        }
        if (!preg_match('/^\d{1,12}(\.\d{1,2})?$/', $valor)) {
            $erros[$campo] = 'Informe um valor válido e não negativo.';
            return null;
        }

        return number_format((float) $valor, 2, '.', '');
    }

    /** Data no formato AAAA-MM-DD (o mesmo do <input type="date">). */
    protected static function campoData(array $dados, string $campo, array &$erros, bool $obrigatorio = false): ?string
    {
        $valor = trim((string) ($dados[$campo] ?? ''));

        if ($valor === '') {
            if ($obrigatorio) {
                $erros[$campo] = 'Preencha este campo.';
            }
            return null;
        }

        // createFromFormat aceita 2026-02-31 (vira março); comparar a volta garante que a data existe.
        $data = DateTime::createFromFormat('!Y-m-d', $valor);
        if (!$data || $data->format('Y-m-d') !== $valor) {
            $erros[$campo] = 'Informe uma data válida.';
            return null;
        }

        return $valor;
    }

    /** Valor que precisa ser uma das opções permitidas (ENUMs do banco). */
    protected static function campoOpcao(array $dados, string $campo, array $opcoes, array &$erros, bool $obrigatorio = false): ?string
    {
        $valor = strtoupper(trim((string) ($dados[$campo] ?? '')));

        if ($valor === '') {
            if ($obrigatorio) {
                $erros[$campo] = 'Selecione uma opção.';
            }
            return null;
        }
        if (!in_array($valor, $opcoes, true)) {
            $erros[$campo] = 'Opção inválida.';
            return null;
        }

        return $valor;
    }

    /** Caixa de seleção / booleano do JSON → 1 ou 0. */
    protected static function campoBool(array $dados, string $campo): int
    {
        $valor = $dados[$campo] ?? false;

        if (is_string($valor)) {
            return in_array(strtolower($valor), ['1', 'true', 'on', 'sim', 'yes'], true) ? 1 : 0;
        }

        return $valor ? 1 : 0;
    }

    /** Se houver erros de campo, interrompe a operação com ErroValidacao (status 422). */
    protected static function lancarSeErros(array $erros, string $mensagem = 'Verifique os campos destacados.'): void
    {
        if ($erros) {
            throw new ErroValidacao($mensagem, $erros);
        }
    }
}
