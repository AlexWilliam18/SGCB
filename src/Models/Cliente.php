<?php
/**
 * Clientes e fornecedores (tabela pessoas), com o endereço cadastral (enderecos_pessoa, um por pessoa),
 * foto e anexos (arquivos + pessoa_anexos).
 * O local de entrega dos contêineres não é do cliente: fica no contrato (Contrato, contrato_enderecos_entrega).
 *
 * No diagrama de classes é a classe Cliente. No banco, cliente e fornecedor ficam
 * na mesma tabela e se diferenciam por tipo_cadastro (CLIENTE, FORNECEDOR ou AMBOS).
 */

final class Cliente extends Modelo
{
    public const TIPOS_CADASTRO = ['CLIENTE', 'FORNECEDOR', 'AMBOS'];

    /** Campos do endereço cadastral e o tamanho máximo de cada um (iguais às colunas de enderecos_pessoa). */
    private const CAMPOS_ENDERECO = ['rua' => 200, 'numero' => 20, 'bairro' => 100, 'cidade' => 100];

    /**
     * Listagem da tela Clientes (clientes.js). Só pessoas CLIENTE ou AMBOS.
     * Filtros: q (nome, fantasia, CPF/CNPJ, e-mail ou telefone), situacao (ativos|inativos|todos),
     * uf, cidade (do endereço principal) e page.
     *
     * @return array{items: array, cities: array, total: int, page: int, pages: int}
     */
    public static function listarClientes(array $filtros = []): array
    {
        $where  = ["p.tipo_cadastro IN ('CLIENTE', 'AMBOS')"];
        $params = [];

        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            $digitos = self::somenteDigitos($q);
            $partes  = ['p.nome_razao_social LIKE :q1', 'p.nome_fantasia LIKE :q2', 'p.email LIKE :q3'];
            $params += ['q1' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%"];
            // Documento e telefone são gravados só com números: a busca também usa só os números.
            if ($digitos !== '') {
                $partes[] = 'p.cpf_cnpj LIKE :q4';
                $partes[] = 'p.telefone LIKE :q5';
                $params  += ['q4' => "%$digitos%", 'q5' => "%$digitos%"];
            }
            $where[] = '(' . implode(' OR ', $partes) . ')';
        }

        $situacao = $filtros['situacao'] ?? '';
        if ($situacao === 'ativos') {
            $where[] = 'p.ativo = 1';
        } elseif ($situacao === 'inativos') {
            $where[] = 'p.ativo = 0';
        }

        $uf = strtoupper(trim((string) ($filtros['uf'] ?? '')));
        if ($uf !== '') {
            $where[]      = 'e.uf = :uf';
            $params['uf'] = $uf;
        }
        $cidade = trim((string) ($filtros['cidade'] ?? ''));
        if ($cidade !== '') {
            $where[]          = 'e.cidade = :cidade';
            $params['cidade'] = $cidade;
        }

        // Cada pessoa tem um único endereço (pessoa_id é UNIQUE em enderecos_pessoa).
        $resultado = self::paginar(
            'p.id, p.nome_razao_social AS nome, p.cpf_cnpj, p.telefone, e.cidade, e.uf, p.criado_em, p.ativo',
            'FROM pessoas p LEFT JOIN enderecos_pessoa e ON e.pessoa_id = p.id WHERE ' . implode(' AND ', $where),
            $params,
            'p.nome_razao_social, p.id',
            (int) ($filtros['page'] ?? 1)
        );

        $resultado['items'] = array_map(static function (array $c): array {
            $c['id']    = (int) $c['id'];
            $c['ativo'] = (bool) $c['ativo'];
            return $c;
        }, $resultado['items']);
        // Opções dos filtros: só UFs e cidades que existem nos endereços dos clientes cadastrados.
        $resultado['states'] = self::listarUfs();
        $resultado['cities'] = self::listarCidades($uf !== '' ? $uf : null);

        return $resultado;
    }

    /** UFs dos endereços dos clientes (filtro de estado da listagem), sem repetir, em ordem. */
    public static function listarUfs(): array
    {
        $consulta = self::pdo()->prepare(
            "SELECT DISTINCT e.uf FROM enderecos_pessoa e JOIN pessoas p ON p.id = e.pessoa_id
              WHERE p.tipo_cadastro IN ('CLIENTE', 'AMBOS') AND e.uf IS NOT NULL AND e.uf <> ''
              ORDER BY e.uf"
        );
        $consulta->execute();

        return $consulta->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Cidades dos endereços dos clientes (filtro da listagem), opcionalmente de uma UF. */
    public static function listarCidades(?string $uf = null): array
    {
        $sql    = "SELECT DISTINCT e.cidade FROM enderecos_pessoa e JOIN pessoas p ON p.id = e.pessoa_id
                    WHERE p.tipo_cadastro IN ('CLIENTE', 'AMBOS') AND e.cidade IS NOT NULL AND e.cidade <> ''";
        $params = [];
        if ($uf !== null) {
            $sql         .= ' AND e.uf = :uf';
            $params['uf'] = $uf;
        }
        $consulta = self::pdo()->prepare($sql . ' ORDER BY e.cidade');
        $consulta->execute($params);

        return $consulta->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Detalhes de uma pessoa (diálogo "Dados do cliente" e tela de edição).
     * Inclui o endereço cadastral (endereco: objeto ou null), foto e anexos.
     */
    public static function buscarCliente(int $id): ?array
    {
        $consulta = self::pdo()->prepare(
            'SELECT p.id, p.tipo_cadastro, p.tipo_pessoa, p.nome_razao_social AS nome, p.nome_fantasia, p.cpf_cnpj,
                    p.email, p.telefone, p.data_nascimento, p.nacionalidade, p.profissao, p.rg, p.inscricao_estadual,
                    p.representante_legal, p.cpf_representante, p.condicao_pagamento_id, cp.nome AS condicao_pagamento,
                    p.limite_credito, p.permite_ultrapassar_limite, p.ativo, p.criado_em, p.atualizado_em,
                    a.caminho_armazenamento AS foto
               FROM pessoas p
               LEFT JOIN condicoes_pagamento cp ON cp.id = p.condicao_pagamento_id
               LEFT JOIN arquivos a ON a.id = p.foto_arquivo_id
              WHERE p.id = :id'
        );
        $consulta->execute(['id' => $id]);
        $cliente = $consulta->fetch();
        if (!$cliente) {
            return null;
        }

        $cliente['id']                         = (int) $cliente['id'];
        $cliente['condicao_pagamento_id']      = $cliente['condicao_pagamento_id'] !== null ? (int) $cliente['condicao_pagamento_id'] : null;
        $cliente['limite_credito']             = (float) $cliente['limite_credito'];
        $cliente['permite_ultrapassar_limite'] = (bool) $cliente['permite_ultrapassar_limite'];
        $cliente['ativo']                      = (bool) $cliente['ativo'];
        $cliente['endereco']                   = self::buscarEndereco($id);

        $anexos = self::pdo()->prepare(
            'SELECT a.id, a.nome_original, a.caminho_armazenamento, a.mime_type, a.tamanho_bytes, pa.descricao
               FROM pessoa_anexos pa JOIN arquivos a ON a.id = pa.arquivo_id
              WHERE pa.pessoa_id = :id ORDER BY a.criado_em'
        );
        $anexos->execute(['id' => $id]);
        $cliente['anexos'] = $anexos->fetchAll();

        return $cliente;
    }

    /** Endereço cadastral da pessoa ({ cep, rua, numero, bairro, cidade, uf, complemento }) ou null. */
    public static function buscarEndereco(int $pessoaId): ?array
    {
        $consulta = self::pdo()->prepare(
            'SELECT cep, rua, numero, bairro, cidade, uf, complemento FROM enderecos_pessoa WHERE pessoa_id = :id'
        );
        $consulta->execute(['id' => $pessoaId]);

        return $consulta->fetch() ?: null;
    }

    /**
     * Cadastro de cliente/fornecedor (POST /api/clientes do cadastro-cliente.js).
     *
     * @param array $dados  JSON do campo "dados": tipo_cadastro, nome_razao_social, nome_fantasia, cpf_cnpj,
     *                      email, telefone, data_nascimento, profissao, nacionalidade, condicao_pagamento_id,
     *                      limite_credito, permite_ultrapassar_limite e endereco { cep, rua, numero, bairro,
     *                      cidade, uf, complemento }.
     * @param ?array $foto   Item de $_FILES['foto'] (opcional).
     * @param array  $anexos Lista de Arquivo::normalizarLista($_FILES['anexos']).
     * @return array{id: int, tipo_cadastro: string}
     */
    public static function cadastrarCliente(array $dados, int $usuarioId, ?array $foto = null, array $anexos = []): array
    {
        [$campos, $endereco] = self::validar($dados);
        self::exigirDocumentoLivre($campos['cpf_cnpj']);

        try {
            return self::transacao(static function (PDO $pdo) use ($campos, $endereco, $usuarioId, $foto, $anexos): array {
                $agora = self::agora();

                if ($foto !== null && ($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $campos['foto_arquivo_id'] = Arquivo::salvarUpload($foto, $usuarioId, Arquivo::IMAGENS, Arquivo::LIMITE_IMAGEM, 'foto');
                }

                $colunas = array_keys($campos);
                $pdo->prepare(
                    'INSERT INTO pessoas (' . implode(', ', $colunas) . ', criado_por, ativo, criado_em, atualizado_em)
                     VALUES (:' . implode(', :', $colunas) . ', :criado_por, 1, :criado, :atualizado)'
                )->execute($campos + ['criado_por' => $usuarioId, 'criado' => $agora, 'atualizado' => $agora]);
                $id = (int) $pdo->lastInsertId();

                self::gravarEndereco($id, $endereco);

                foreach ($anexos as $anexo) {
                    $arquivoId = Arquivo::salvarUpload($anexo, $usuarioId, Arquivo::DOCUMENTOS, Arquivo::LIMITE_DOCUMENTO, 'anexos');
                    $pdo->prepare('INSERT INTO pessoa_anexos (pessoa_id, arquivo_id, descricao) VALUES (:p, :a, :d)')
                        ->execute(['p' => $id, 'a' => $arquivoId, 'd' => mb_substr(basename((string) $anexo['name']), 0, 255)]);
                }

                self::auditar($usuarioId, 'pessoas', $id, 'CRIAR', [
                    'tipo_cadastro' => $campos['tipo_cadastro'], 'cpf_cnpj' => $campos['cpf_cnpj'],
                ]);

                return ['id' => $id, 'tipo_cadastro' => $campos['tipo_cadastro']];
            });
        } catch (Throwable $e) {
            // O banco foi desfeito pela transação; os arquivos já gravados no disco são apagados aqui.
            Arquivo::descartarGravados();
            throw $e;
        }
    }

    /**
     * Atualiza o cadastro (mesmos campos do cadastro). O endereço enviado substitui o atual;
     * contratos não são afetados, porque cada um guarda o próprio local de entrega.
     * Foto nova (opcional) substitui a atual; anexos novos (opcionais) são somados aos que já existem.
     */
    public static function atualizarDados(int $id, array $dados, int $usuarioId, ?array $foto = null, array $anexos = []): void
    {
        if (self::buscarCliente($id) === null) {
            throw new ErroValidacao('Cadastro não encontrado.', [], 404);
        }
        [$campos, $endereco] = self::validar($dados);
        self::exigirDocumentoLivre($campos['cpf_cnpj'], $id);

        try {
            self::transacao(static function (PDO $pdo) use ($id, $campos, $endereco, $usuarioId, $foto, $anexos): void {
                if ($foto !== null && ($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $campos['foto_arquivo_id'] = Arquivo::salvarUpload($foto, $usuarioId, Arquivo::IMAGENS, Arquivo::LIMITE_IMAGEM, 'foto');
                }

                $sets = implode(', ', array_map(static fn (string $c): string => "$c = :$c", array_keys($campos)));
                $pdo->prepare("UPDATE pessoas SET $sets, atualizado_em = :agora WHERE id = :id")
                    ->execute($campos + ['agora' => self::agora(), 'id' => $id]);

                self::gravarEndereco($id, $endereco);

                foreach ($anexos as $anexo) {
                    $arquivoId = Arquivo::salvarUpload($anexo, $usuarioId, Arquivo::DOCUMENTOS, Arquivo::LIMITE_DOCUMENTO, 'anexos');
                    $pdo->prepare('INSERT INTO pessoa_anexos (pessoa_id, arquivo_id, descricao) VALUES (:p, :a, :d)')
                        ->execute(['p' => $id, 'a' => $arquivoId, 'd' => mb_substr(basename((string) $anexo['name']), 0, 255)]);
                }

                self::auditar($usuarioId, 'pessoas', $id, 'ALTERAR', $campos);
            });
        } catch (Throwable $e) {
            Arquivo::descartarGravados(); // o banco voltou; os arquivos gravados no disco também saem
            throw $e;
        }
    }

    /** Inativa o cadastro: ele some dos selects e não pode receber contratos novos. */
    public static function inativarCliente(int $id, int $usuarioId): void
    {
        self::alterarStatus($id, false, $usuarioId);
    }

    public static function ativarCliente(int $id, int $usuarioId): void
    {
        self::alterarStatus($id, true, $usuarioId);
    }

    /** Fornecedores ativos para o cadastro de produto ({ id, nome }). */
    public static function listarFornecedores(): array
    {
        $linhas = self::pdo()->query(
            "SELECT id, COALESCE(NULLIF(nome_fantasia, ''), nome_razao_social) AS nome
               FROM pessoas WHERE ativo = 1 AND tipo_cadastro IN ('FORNECEDOR', 'AMBOS')
              ORDER BY nome"
        )->fetchAll();

        return array_map(static fn (array $l): array => ['id' => (int) $l['id'], 'nome' => $l['nome']], $linhas);
    }

    /** Clientes ativos para o select do contrato ({ id, nome, cpf_cnpj }). */
    public static function listarClientesAtivos(): array
    {
        $linhas = self::pdo()->query(
            "SELECT id, nome_razao_social AS nome, cpf_cnpj FROM pessoas
              WHERE ativo = 1 AND tipo_cadastro IN ('CLIENTE', 'AMBOS') ORDER BY nome_razao_social"
        )->fetchAll();

        return array_map(static function (array $l): array {
            $l['id'] = (int) $l['id'];
            return $l;
        }, $linhas);
    }

    /**
     * Saldo que o cliente ainda deve (parcelas não canceladas de contratos ativos ou finalizados,
     * menos os pagamentos não estornados), em centavos.
     */
    public static function saldoDevedorCentavos(int $clienteId): int
    {
        $consulta = self::pdo()->prepare(
            "SELECT COALESCE(SUM(pa.valor_original + pa.acrescimo - pa.desconto - COALESCE(pg.recebido, 0)), 0)
               FROM parcelas pa
               JOIN contratos c ON c.id = pa.contrato_id
               LEFT JOIN (SELECT parcela_id, SUM(valor) AS recebido FROM pagamentos WHERE estornado_em IS NULL GROUP BY parcela_id) pg
                      ON pg.parcela_id = pa.id
              WHERE c.cliente_id = :id AND pa.cancelada = 0 AND c.status IN ('ATIVO', 'FINALIZADO')"
        );
        $consulta->execute(['id' => $clienteId]);

        return max(0, self::centavos($consulta->fetchColumn()));
    }

    /**
     * CPF (11 dígitos) ou CNPJ (14 dígitos) com dígitos verificadores válidos.
     * Mesma regra do cadastro-cliente.js, repetida aqui porque o navegador pode ser burlado.
     */
    public static function validarCpfCnpj(string $documento): bool
    {
        $d = self::somenteDigitos($documento);
        if (!preg_match('/^(\d{11}|\d{14})$/', $d) || count(array_unique(str_split($d))) === 1) {
            return false;
        }

        if (strlen($d) === 11) {
            for ($t = 9; $t < 11; $t++) {
                $soma = 0;
                for ($i = 0; $i < $t; $i++) {
                    $soma += (int) $d[$i] * (($t + 1) - $i);
                }
                if ((int) $d[$t] !== ((10 * $soma) % 11) % 10) {
                    return false;
                }
            }
            return true;
        }

        // CNPJ: pesos 5..2 e 9..2 no primeiro dígito; 6..2 e 9..2 no segundo.
        foreach ([12, 13] as $t) {
            $soma = 0;
            $peso = $t - 7;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $d[$i] * $peso;
                $peso  = $peso === 2 ? 9 : $peso - 1;
            }
            $digito = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
            if ((int) $d[$t] !== $digito) {
                return false;
            }
        }

        return true;
    }

    // -------------------------------------------------------------------------

    private static function alterarStatus(int $id, bool $ativo, int $usuarioId): void
    {
        if (self::buscarCliente($id) === null) {
            throw new ErroValidacao('Cadastro não encontrado.', [], 404);
        }

        self::transacao(static function (PDO $pdo) use ($id, $ativo, $usuarioId): void {
            $pdo->prepare('UPDATE pessoas SET ativo = :ativo, atualizado_em = :agora WHERE id = :id')
                ->execute(['ativo' => $ativo ? 1 : 0, 'agora' => self::agora(), 'id' => $id]);
            self::auditar($usuarioId, 'pessoas', $id, $ativo ? 'ATIVAR' : 'INATIVAR');
        });
    }

    /**
     * Valida os dados do formulário. Os nomes dos erros seguem os ids da tela
     * (ex.: 'endereco.cep'), para o cadastro-cliente.js destacar o campo certo.
     *
     * @return array{0: array, 1: array} [colunas da tabela pessoas, endereço normalizado]
     */
    private static function validar(array $dados): array
    {
        $erros = [];

        $documento = self::somenteDigitos($dados['cpf_cnpj'] ?? '');
        if (!self::validarCpfCnpj($documento)) {
            $erros['cpf_cnpj'] = 'CPF/CNPJ inválido. Confira os números informados.';
        }

        $campos = [
            'tipo_cadastro'       => self::campoOpcao($dados, 'tipo_cadastro', self::TIPOS_CADASTRO, $erros, true),
            'tipo_pessoa'         => strlen($documento) === 14 ? 'JURIDICA' : 'FISICA',
            'nome_razao_social'   => self::campoTexto($dados, 'nome_razao_social', 200, $erros, true),
            'nome_fantasia'       => self::campoTexto($dados, 'nome_fantasia', 200, $erros),
            'cpf_cnpj'            => $documento,
            'email'               => mb_strtolower((string) self::campoTexto($dados, 'email', 254, $erros, true)),
            'telefone'            => self::somenteDigitos($dados['telefone'] ?? ''),
            'data_nascimento'     => self::campoData($dados, 'data_nascimento', $erros),
            'nacionalidade'       => self::campoTexto($dados, 'nacionalidade', 80, $erros),
            'profissao'           => self::campoTexto($dados, 'profissao', 100, $erros),
            'rg'                  => self::campoTexto($dados, 'rg', 30, $erros),
            'inscricao_estadual'  => self::campoTexto($dados, 'inscricao_estadual', 30, $erros),
            'representante_legal' => self::campoTexto($dados, 'representante_legal', 150, $erros),
            'cpf_representante'   => self::somenteDigitos($dados['cpf_representante'] ?? '') ?: null,
            'condicao_pagamento_id' => self::campoInteiro($dados, 'condicao_pagamento_id', $erros, true, 1),
            'limite_credito'      => self::campoDecimal($dados, 'limite_credito', $erros) ?? '0.00',
            'permite_ultrapassar_limite' => self::campoBool($dados, 'permite_ultrapassar_limite'),
        ];

        if ($campos['email'] !== '' && !filter_var($campos['email'], FILTER_VALIDATE_EMAIL)) {
            $erros['email'] = 'Informe um e-mail válido.';
        }
        if (!preg_match('/^\d{8,15}$/', $campos['telefone'])) {
            $erros['telefone'] = 'Informe um telefone válido, incluindo o DDD.';
        }
        if ($campos['data_nascimento'] !== null && $campos['data_nascimento'] > self::hoje()) {
            $erros['data_nascimento'] = 'Informe uma data válida, até o dia de hoje.';
        }
        if ($campos['cpf_representante'] !== null && (strlen($campos['cpf_representante']) !== 11 || !self::validarCpfCnpj($campos['cpf_representante']))) {
            $erros['cpf_representante'] = 'CPF do representante inválido.';
        }
        if ($campos['condicao_pagamento_id'] !== null) {
            $condicao = CondicaoPagamento::buscar($campos['condicao_pagamento_id']);
            if ($condicao === null || !$condicao['ativo']) {
                $erros['condicao_pagamento_id'] = 'Selecione uma condição de pagamento ativa.';
            }
        }

        $endereco = self::validarEndereco($dados['endereco'] ?? null, $erros);
        self::lancarSeErros($erros);

        return [$campos, $endereco];
    }

    /** Endereço cadastral (obrigatório). Erros voltam como "endereco.campo". */
    private static function validarEndereco(mixed $e, array &$erros): array
    {
        $e = is_array($e) ? array_map(static fn ($v): mixed => is_string($v) ? trim($v) : $v, $e) : [];

        $endereco = [
            'cep'         => self::somenteDigitos((string) ($e['cep'] ?? '')),
            'rua'         => (string) ($e['rua'] ?? ''),
            'numero'      => (string) ($e['numero'] ?? ''),
            'bairro'      => (string) ($e['bairro'] ?? ''),
            'cidade'      => (string) ($e['cidade'] ?? ''),
            'uf'          => strtoupper((string) ($e['uf'] ?? '')),
            'complemento' => ($e['complemento'] ?? '') !== '' ? (string) $e['complemento'] : null,
        ];

        foreach (self::CAMPOS_ENDERECO as $campo => $maximo) {
            if ($endereco[$campo] === '') {
                $erros['endereco.' . $campo] = 'Preencha este campo.';
            } elseif (mb_strlen($endereco[$campo]) > $maximo) {
                $erros['endereco.' . $campo] = "Use no máximo $maximo caracteres.";
            }
        }
        if ($endereco['complemento'] !== null && mb_strlen($endereco['complemento']) > 150) {
            $erros['endereco.complemento'] = 'Use no máximo 150 caracteres.';
        }
        if (strlen($endereco['cep']) !== 8) {
            $erros['endereco.cep'] = 'Informe um CEP com 8 dígitos.';
        }
        if (!preg_match('/^[A-Z]{2}$/', $endereco['uf'])) {
            $erros['endereco.uf'] = 'Informe a UF com 2 letras.';
        }

        return $endereco;
    }

    /**
     * Grava o endereço já validado. pessoa_id é UNIQUE: no cadastro insere, na edição
     * o ON DUPLICATE KEY atualiza a linha que já existe.
     */
    private static function gravarEndereco(int $pessoaId, array $e): void
    {
        self::pdo()->prepare(
            'INSERT INTO enderecos_pessoa (pessoa_id, cep, rua, numero, bairro, cidade, uf, complemento)
             VALUES (:pessoa, :cep, :rua, :numero, :bairro, :cidade, :uf, :complemento)
             ON DUPLICATE KEY UPDATE cep = VALUES(cep), rua = VALUES(rua), numero = VALUES(numero), bairro = VALUES(bairro),
                                     cidade = VALUES(cidade), uf = VALUES(uf), complemento = VALUES(complemento)'
        )->execute(['pessoa' => $pessoaId] + $e);
    }

    private static function exigirDocumentoLivre(string $documento, int $ignorarId = 0): void
    {
        $consulta = self::pdo()->prepare('SELECT 1 FROM pessoas WHERE cpf_cnpj = :doc AND id <> :id');
        $consulta->execute(['doc' => $documento, 'id' => $ignorarId]);
        if ($consulta->fetchColumn() !== false) {
            throw new ErroValidacao('Já existe um cadastro com este CPF/CNPJ.', ['cpf_cnpj' => 'CPF/CNPJ já cadastrado.'], 409);
        }
    }
}
