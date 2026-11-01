<?php
/**
 * Usuários do sistema (no diagrama de classes: Administrador).
 * Tabelas: usuarios, cargos, modulos, permissoes, cargo_permissoes,
 * usuario_permissoes e recuperacoes_senha.
 *
 * Login e logout continuam em src/Auth/Autenticacao.php (sessão do PHP);
 * aqui ficam o cadastro, a troca e a recuperação de senha e as permissões.
 */

final class Usuario extends Modelo
{
    /** Cargo que tem acesso total, sem precisar de permissões cadastradas. */
    private const CARGO_ADMIN = 'ADMIN';

    /** Validade do link de recuperação de senha. */
    private const HORAS_RECUPERACAO = 1;

    /** Dados do usuário (sem o hash da senha) ou null. */
    public static function buscarPorId(int $id): ?array
    {
        $consulta = self::pdo()->prepare(
            'SELECT u.id, u.cargo_id, u.nome, u.email, u.telefone, u.ativo, u.deve_alterar_senha,
                    u.ultimo_acesso, u.senha_alterada_em, u.criado_em, u.atualizado_em,
                    c.codigo AS cargo_codigo, c.nome AS cargo_nome
               FROM usuarios u
               LEFT JOIN cargos c ON c.id = u.cargo_id
              WHERE u.id = :id'
        );
        $consulta->execute(['id' => $id]);
        $usuario = $consulta->fetch();

        return $usuario ? self::formatar($usuario) : null;
    }

    /** Listagem paginada para a futura tela de Usuários. Filtros: q (nome/e-mail), situacao (ativos|inativos). */
    public static function listarUsuarios(array $filtros = []): array
    {
        $where  = ['1 = 1'];
        $params = [];

        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            $where[]      = '(u.nome LIKE :q1 OR u.email LIKE :q2)';
            $params['q1'] = $params['q2'] = "%$q%";
        }
        if (($filtros['situacao'] ?? '') === 'ativos') {
            $where[] = 'u.ativo = 1';
        } elseif (($filtros['situacao'] ?? '') === 'inativos') {
            $where[] = 'u.ativo = 0';
        }

        $resultado = self::paginar(
            'u.id, u.cargo_id, u.nome, u.email, u.telefone, u.ativo, u.deve_alterar_senha, u.ultimo_acesso,
             u.senha_alterada_em, u.criado_em, u.atualizado_em, c.codigo AS cargo_codigo, c.nome AS cargo_nome',
            'FROM usuarios u LEFT JOIN cargos c ON c.id = u.cargo_id WHERE ' . implode(' AND ', $where),
            $params,
            'u.nome',
            (int) ($filtros['page'] ?? 1)
        );
        $resultado['items'] = array_map([self::class, 'formatar'], $resultado['items']);

        return $resultado;
    }

    /** Usuários ativos para os campos "Responsável" (ex.: cadastro de manutenção). */
    public static function listarResponsaveis(): array
    {
        $linhas = self::pdo()->query('SELECT id, nome FROM usuarios WHERE ativo = 1 ORDER BY nome')->fetchAll();

        return array_map(static fn (array $l): array => ['id' => (int) $l['id'], 'nome' => $l['nome']], $linhas);
    }

    /** Cargos ativos, para o cadastro de usuário. */
    public static function listarCargos(): array
    {
        $linhas = self::pdo()->query('SELECT id, codigo, nome, nivel FROM cargos WHERE ativo = 1 ORDER BY nivel DESC, nome')->fetchAll();

        return array_map(static fn (array $l): array => [
            'id' => (int) $l['id'], 'codigo' => $l['codigo'], 'nome' => $l['nome'], 'nivel' => (int) $l['nivel'],
        ], $linhas);
    }

    /**
     * Cadastra um usuário com senha provisória: no primeiro acesso ele precisa trocá-la.
     * Campos: nome, email, telefone, cargo_id, senha.
     *
     * @return int id do usuário criado.
     */
    public static function cadastrarUsuario(array $dados, int $criadoPor): int
    {
        $erros  = [];
        $campos = self::validarDados($dados, $erros);
        $senha  = (string) ($dados['senha'] ?? '');
        if ($erro = self::erroSenha($senha)) {
            $erros['senha'] = $erro;
        }
        self::lancarSeErros($erros);
        self::exigirEmailLivre($campos['email']);

        return self::transacao(static function (PDO $pdo) use ($campos, $senha, $criadoPor): int {
            $agora = self::agora();
            $pdo->prepare(
                'INSERT INTO usuarios (cargo_id, nome, email, telefone, senha_hash, ativo, deve_alterar_senha, criado_em, atualizado_em)
                 VALUES (:cargo, :nome, :email, :telefone, :hash, 1, 1, :criado, :atualizado)'
            )->execute([
                'cargo' => $campos['cargo_id'], 'nome' => $campos['nome'], 'email' => $campos['email'],
                'telefone' => $campos['telefone'], 'hash' => password_hash($senha, PASSWORD_BCRYPT),
                'criado' => $agora, 'atualizado' => $agora,
            ]);
            $id = (int) $pdo->lastInsertId();
            self::auditar($criadoPor, 'usuarios', $id, 'CRIAR', ['email' => $campos['email']]);

            return $id;
        });
    }

    /** Atualiza nome, e-mail, telefone e cargo. */
    public static function atualizarUsuario(int $id, array $dados, int $alteradoPor): void
    {
        self::exigirUsuario($id);
        $erros  = [];
        $campos = self::validarDados($dados, $erros);
        self::lancarSeErros($erros);
        self::exigirEmailLivre($campos['email'], $id);

        self::transacao(static function (PDO $pdo) use ($id, $campos, $alteradoPor): void {
            $pdo->prepare(
                'UPDATE usuarios SET cargo_id = :cargo_id, nome = :nome, email = :email, telefone = :telefone, atualizado_em = :agora
                  WHERE id = :id'
            )->execute($campos + ['agora' => self::agora(), 'id' => $id]);
            self::auditar($alteradoPor, 'usuarios', $id, 'ALTERAR', $campos);
        });
    }

    /** Ativa ou desativa o acesso. Ninguém pode desativar a si mesmo. */
    public static function alterarStatus(int $id, bool $ativo, int $alteradoPor): void
    {
        self::exigirUsuario($id);
        if (!$ativo && $id === $alteradoPor) {
            throw new ErroValidacao('Você não pode desativar o seu próprio usuário.');
        }

        self::transacao(static function (PDO $pdo) use ($id, $ativo, $alteradoPor): void {
            $pdo->prepare('UPDATE usuarios SET ativo = :ativo, atualizado_em = :agora WHERE id = :id')
                ->execute(['ativo' => $ativo ? 1 : 0, 'agora' => self::agora(), 'id' => $id]);
            if (!$ativo) {
                SessaoPersistente::revogarTodas($id); // tira o "lembrar de mim" de quem foi desativado
            }
            self::auditar($alteradoPor, 'usuarios', $id, $ativo ? 'ATIVAR' : 'INATIVAR');
        });
    }

    /**
     * Troca a senha conferindo a atual (tela de troca obrigatória no primeiro acesso).
     * Limpa o deve_alterar_senha e encerra os "lembrar de mim" abertos.
     */
    public static function alterarSenha(int $id, string $senhaAtual, string $novaSenha): void
    {
        $consulta = self::pdo()->prepare('SELECT senha_hash FROM usuarios WHERE id = :id AND ativo = 1');
        $consulta->execute(['id' => $id]);
        $hash = $consulta->fetchColumn();

        if ($hash === false || !self::senhaConfere($senhaAtual, (string) $hash)) {
            throw new ErroValidacao('Verifique os campos destacados.', ['senha_atual' => 'Senha atual incorreta.']);
        }
        if ($erro = self::erroSenha($novaSenha)) {
            throw new ErroValidacao('Verifique os campos destacados.', ['nova_senha' => $erro]);
        }
        if (hash_equals($senhaAtual, $novaSenha)) {
            throw new ErroValidacao('Verifique os campos destacados.', ['nova_senha' => 'A nova senha precisa ser diferente da atual.']);
        }

        self::gravarSenha($id, $novaSenha, $id);
    }

    /**
     * Inicia a recuperação de senha. Devolve o token (para montar o link enviado por e-mail)
     * ou null se o e-mail não pertence a um usuário ativo. A tela deve mostrar a mesma
     * mensagem nos dois casos, para não revelar quais e-mails existem.
     */
    public static function gerarRecuperacaoSenha(string $email): ?string
    {
        $consulta = self::pdo()->prepare('SELECT id FROM usuarios WHERE email = :email AND ativo = 1');
        $consulta->execute(['email' => mb_strtolower(trim($email))]);
        $usuarioId = $consulta->fetchColumn();
        if ($usuarioId === false) {
            return null;
        }

        // O token vai só no link; no banco fica o hash (quem lê o banco não consegue usar o link).
        $token = bin2hex(random_bytes(32));

        self::transacao(static function (PDO $pdo) use ($usuarioId, $token): void {
            $agora = self::agora();
            // Um pedido novo invalida os anteriores ainda não usados.
            $pdo->prepare('UPDATE recuperacoes_senha SET expira_em = :agora WHERE usuario_id = :id AND utilizado_em IS NULL AND expira_em > :agora2')
                ->execute(['agora' => $agora, 'id' => $usuarioId, 'agora2' => $agora]);
            $pdo->prepare(
                'INSERT INTO recuperacoes_senha (usuario_id, token_hash, criado_em, expira_em) VALUES (:id, :hash, :criado, :expira)'
            )->execute([
                'id' => $usuarioId, 'hash' => hash('sha256', $token), 'criado' => $agora,
                'expira' => date('Y-m-d H:i:s', strtotime('+' . self::HORAS_RECUPERACAO . ' hour')),
            ]);
            self::auditar((int) $usuarioId, 'usuarios', (int) $usuarioId, 'SOLICITAR_RECUPERACAO_SENHA');
        });

        return $token;
    }

    /** Define a nova senha a partir do token do link de recuperação (uso único). */
    public static function redefinirSenhaComToken(string $token, string $novaSenha): void
    {
        if ($erro = self::erroSenha($novaSenha)) {
            throw new ErroValidacao('Verifique os campos destacados.', ['nova_senha' => $erro]);
        }

        self::transacao(static function (PDO $pdo) use ($token, $novaSenha): void {
            // FOR UPDATE: dois envios ao mesmo tempo não conseguem usar o mesmo token.
            $consulta = $pdo->prepare(
                'SELECT r.id, r.usuario_id FROM recuperacoes_senha r
                   JOIN usuarios u ON u.id = r.usuario_id AND u.ativo = 1
                  WHERE r.token_hash = :hash AND r.utilizado_em IS NULL AND r.expira_em > :agora
                  FOR UPDATE'
            );
            $consulta->execute(['hash' => hash('sha256', $token), 'agora' => self::agora()]);
            $pedido = $consulta->fetch();
            if (!$pedido) {
                throw new ErroValidacao('Link de recuperação inválido ou expirado. Solicite um novo.', [], 410);
            }

            $pdo->prepare('UPDATE recuperacoes_senha SET utilizado_em = :agora WHERE id = :id')
                ->execute(['agora' => self::agora(), 'id' => $pedido['id']]);
            self::gravarSenha((int) $pedido['usuario_id'], $novaSenha, (int) $pedido['usuario_id']);
        });
    }

    /**
     * Confere se o usuário pode executar uma ação de um módulo (ex.: 'produtos', 'cadastrar').
     * Ordem: cargo ADMIN libera tudo → exceção individual (usuario_permissoes) → permissões do cargo.
     * Módulos exclusivos de administrador só liberam para o cargo ADMIN.
     */
    public static function temPermissao(int $usuarioId, string $modulo, string $acao): bool
    {
        $pdo = self::pdo();

        $consulta = $pdo->prepare(
            'SELECT u.cargo_id, c.codigo FROM usuarios u JOIN cargos c ON c.id = u.cargo_id
              WHERE u.id = :id AND u.ativo = 1 AND c.ativo = 1'
        );
        $consulta->execute(['id' => $usuarioId]);
        $usuario = $consulta->fetch();
        if (!$usuario) {
            return false;
        }
        if ($usuario['codigo'] === self::CARGO_ADMIN) {
            return true;
        }

        $consulta = $pdo->prepare(
            'SELECT p.id, m.exclusivo_adm FROM permissoes p JOIN modulos m ON m.id = p.modulo_id
              WHERE m.codigo = :modulo AND p.acao = :acao'
        );
        $consulta->execute(['modulo' => $modulo, 'acao' => $acao]);
        $permissao = $consulta->fetch();
        if (!$permissao || $permissao['exclusivo_adm']) {
            return false;
        }

        // Exceção individual vale mais que o cargo (pode conceder ou negar).
        $consulta = $pdo->prepare('SELECT permitido FROM usuario_permissoes WHERE usuario_id = :u AND permissao_id = :p');
        $consulta->execute(['u' => $usuarioId, 'p' => $permissao['id']]);
        $excecao = $consulta->fetchColumn();
        if ($excecao !== false) {
            return (bool) $excecao;
        }

        $consulta = $pdo->prepare('SELECT 1 FROM cargo_permissoes WHERE cargo_id = :c AND permissao_id = :p');
        $consulta->execute(['c' => $usuario['cargo_id'], 'p' => $permissao['id']]);

        return $consulta->fetchColumn() !== false;
    }

    /**
     * Concede (true) ou nega (false) uma permissão só para este usuário.
     * null remove a exceção, e o usuário volta a seguir o cargo.
     */
    public static function definirPermissao(int $usuarioId, int $permissaoId, ?bool $permitido, int $alteradoPor): void
    {
        self::exigirUsuario($usuarioId);

        self::transacao(static function (PDO $pdo) use ($usuarioId, $permissaoId, $permitido, $alteradoPor): void {
            if ($permitido === null) {
                $pdo->prepare('DELETE FROM usuario_permissoes WHERE usuario_id = :u AND permissao_id = :p')
                    ->execute(['u' => $usuarioId, 'p' => $permissaoId]);
            } else {
                $pdo->prepare(
                    'INSERT INTO usuario_permissoes (usuario_id, permissao_id, alterado_por, permitido, alterado_em)
                     VALUES (:u, :p, :por, :permitido, :agora)
                     ON DUPLICATE KEY UPDATE alterado_por = VALUES(alterado_por), permitido = VALUES(permitido), alterado_em = VALUES(alterado_em)'
                )->execute([
                    'u' => $usuarioId, 'p' => $permissaoId, 'por' => $alteradoPor,
                    'permitido' => $permitido ? 1 : 0, 'agora' => self::agora(),
                ]);
            }
            self::auditar($alteradoPor, 'usuarios', $usuarioId, 'ALTERAR_PERMISSAO', ['permissao_id' => $permissaoId, 'permitido' => $permitido]);
        });
    }

    // -------------------------------------------------------------------------

    /** Regra da senha: 8+ caracteres, com pelo menos uma letra e um número. null = senha válida. */
    public static function erroSenha(string $senha): ?string
    {
        if (mb_strlen($senha) < 8 || !preg_match('/[A-Za-z]/', $senha) || !preg_match('/\d/', $senha)) {
            return 'Use pelo menos 8 caracteres, com letras e números.';
        }
        if (mb_strlen($senha) > 72) {
            return 'Use no máximo 72 caracteres.'; // limite do bcrypt
        }

        return null;
    }

    /** Grava o hash da nova senha, tira a obrigação de troca e revoga os "lembrar de mim". */
    private static function gravarSenha(int $id, string $novaSenha, int $alteradoPor): void
    {
        self::transacao(static function (PDO $pdo) use ($id, $novaSenha, $alteradoPor): void {
            $agora = self::agora();
            $pdo->prepare(
                'UPDATE usuarios SET senha_hash = :hash, deve_alterar_senha = 0, senha_alterada_em = :agora, atualizado_em = :agora2
                  WHERE id = :id'
            )->execute(['hash' => password_hash($novaSenha, PASSWORD_BCRYPT), 'agora' => $agora, 'agora2' => $agora, 'id' => $id]);
            SessaoPersistente::revogarTodas($id);
            self::auditar($alteradoPor, 'usuarios', $id, 'ALTERAR_SENHA');
        });
    }

    /** Mesma regra do login: aceita hash bcrypt ou a senha em texto puro do seed. */
    private static function senhaConfere(string $senha, string $armazenada): bool
    {
        if (password_get_info($armazenada)['algo'] === null) {
            return $armazenada !== '' && hash_equals($armazenada, $senha);
        }

        return password_verify($senha, $armazenada);
    }

    /** Valida os campos comuns ao cadastro e à edição. */
    private static function validarDados(array $dados, array &$erros): array
    {
        $campos = [
            'nome'     => self::campoTexto($dados, 'nome', 150, $erros, true),
            'email'    => mb_strtolower((string) self::campoTexto($dados, 'email', 254, $erros, true)),
            'telefone' => self::somenteDigitos(self::campoTexto($dados, 'telefone', 25, $erros)) ?: null,
            'cargo_id' => self::campoInteiro($dados, 'cargo_id', $erros, true, 1),
        ];

        if ($campos['email'] !== '' && !filter_var($campos['email'], FILTER_VALIDATE_EMAIL)) {
            $erros['email'] = 'Informe um e-mail válido.';
        }
        if ($campos['cargo_id'] !== null) {
            $consulta = self::pdo()->prepare('SELECT 1 FROM cargos WHERE id = :id AND ativo = 1');
            $consulta->execute(['id' => $campos['cargo_id']]);
            if ($consulta->fetchColumn() === false) {
                $erros['cargo_id'] = 'Selecione um cargo ativo.';
            }
        }

        return $campos;
    }

    private static function exigirEmailLivre(string $email, int $ignorarId = 0): void
    {
        $consulta = self::pdo()->prepare('SELECT 1 FROM usuarios WHERE email = :email AND id <> :id');
        $consulta->execute(['email' => $email, 'id' => $ignorarId]);
        if ($consulta->fetchColumn() !== false) {
            throw new ErroValidacao('Já existe um usuário com este e-mail.', ['email' => 'E-mail já cadastrado.'], 409);
        }
    }

    private static function exigirUsuario(int $id): void
    {
        if (self::buscarPorId($id) === null) {
            throw new ErroValidacao('Usuário não encontrado.', [], 404);
        }
    }

    private static function formatar(array $u): array
    {
        $u['id']                 = (int) $u['id'];
        $u['cargo_id']           = $u['cargo_id'] !== null ? (int) $u['cargo_id'] : null;
        $u['ativo']              = (bool) $u['ativo'];
        $u['deve_alterar_senha'] = (bool) $u['deve_alterar_senha'];

        return $u;
    }
}
