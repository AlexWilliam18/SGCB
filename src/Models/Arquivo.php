<?php
/**
 * Arquivos enviados pelos usuários (tabela arquivos): fotos, anexos, comprovantes e PDFs.
 * O arquivo fica em public/uploads/AAAA/MM/ com nome aleatório; no banco ficam o nome original,
 * o caminho, o tipo, o tamanho e o SHA-256 (para conferir a integridade).
 */

final class Arquivo extends Modelo
{
    /** Imagens aceitas (foto do cliente, logo da empresa): tipo MIME real → extensão gravada. */
    public const IMAGENS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** Documentos aceitos (anexos do cadastro, comprovantes). */
    public const DOCUMENTOS = self::IMAGENS + [
        'application/pdf'    => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'       => 'xlsx',
    ];

    /** Limites usados pelas telas (cadastro-cliente.js): foto 5 MB, anexos 10 MB. */
    public const LIMITE_IMAGEM    = 5 * 1024 * 1024;
    public const LIMITE_DOCUMENTO = 10 * 1024 * 1024;

    /** Pasta pública dos uploads (protegida contra execução de scripts pelo .htaccess dela). */
    private const PASTA = __DIR__ . '/../../public/uploads/';

    /** Arquivos gravados nesta requisição, para apagar do disco se o cadastro falhar. */
    private static array $gravados = [];

    /**
     * Valida e grava um arquivo vindo de $_FILES. Devolve o id na tabela arquivos.
     *
     * @param array  $arquivo Um item de $_FILES (name, type, tmp_name, error, size).
     * @param array  $tipos   Arquivo::IMAGENS ou Arquivo::DOCUMENTOS.
     * @param string $campo   Nome do campo, para a mensagem de erro voltar no lugar certo.
     */
    public static function salvarUpload(array $arquivo, int $usuarioId, array $tipos, int $limiteBytes, string $campo): int
    {
        $erro = $arquivo['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($erro !== UPLOAD_ERR_OK) {
            $mensagem = in_array($erro, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'Arquivo maior que o permitido pelo servidor.'
                : 'Não foi possível receber o arquivo. Tente enviar novamente.';
            throw new ErroValidacao($mensagem, [$campo => $mensagem]);
        }

        $temporario = (string) $arquivo['tmp_name'];
        $tamanho    = (int) filesize($temporario);
        $original   = mb_substr(basename((string) $arquivo['name']), 0, 255);

        if ($tamanho <= 0 || $tamanho > $limiteBytes) {
            $mb = $limiteBytes / 1024 / 1024;
            throw new ErroValidacao("O arquivo $original precisa ter até $mb MB.", [$campo => "Envie um arquivo de até $mb MB."]);
        }

        // O tipo é descoberto pelo conteúdo, não pela extensão nem pelo que o navegador informou.
        $mime     = self::tipoReal($temporario, $original);
        $extensao = $tipos[$mime] ?? null;
        if ($extensao === null) {
            throw new ErroValidacao("Formato não aceito: $original.", [$campo => 'Formato de arquivo não aceito.']);
        }

        // Nome aleatório: evita sobrescrever arquivos e esconde o nome original na URL.
        $relativo = 'uploads/' . date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $extensao;
        $destino  = self::PASTA . substr($relativo, strlen('uploads/'));
        if (!is_dir(dirname($destino)) && !mkdir(dirname($destino), 0775, true) && !is_dir(dirname($destino))) {
            throw new RuntimeException('Não foi possível criar a pasta de uploads.');
        }

        // move_uploaded_file só aceita arquivos que vieram de um upload HTTP de verdade.
        if (!move_uploaded_file($temporario, $destino)) {
            throw new RuntimeException('Não foi possível gravar o arquivo enviado.');
        }
        self::$gravados[] = $destino;

        self::pdo()->prepare(
            'INSERT INTO arquivos (enviado_por, nome_original, caminho_armazenamento, mime_type, tamanho_bytes, sha256, criado_em)
             VALUES (:usuario, :nome, :caminho, :mime, :tamanho, :sha, :criado)'
        )->execute([
            'usuario' => $usuarioId, 'nome' => $original, 'caminho' => $relativo, 'mime' => $mime,
            'tamanho' => $tamanho, 'sha' => hash_file('sha256', $destino), 'criado' => self::agora(),
        ]);

        return (int) self::pdo()->lastInsertId();
    }

    /**
     * Apaga do disco os arquivos gravados nesta requisição.
     * Chame quando a transação do cadastro for desfeita (o banco volta, o disco não).
     */
    public static function descartarGravados(): void
    {
        foreach (self::$gravados as $caminho) {
            if (is_file($caminho)) {
                unlink($caminho);
            }
        }
        self::$gravados = [];
    }

    /**
     * $_FILES de um campo com vários arquivos → lista de itens no formato de um arquivo só.
     * Funciona com name="anexos[]" (vários) e com name="anexos" (um).
     */
    public static function normalizarLista(?array $arquivos): array
    {
        if (!$arquivos || !isset($arquivos['name'])) {
            return [];
        }
        if (!is_array($arquivos['name'])) {
            return ($arquivos['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$arquivos];
        }

        $lista = [];
        foreach (array_keys($arquivos['name']) as $i) {
            if ($arquivos['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $lista[] = [
                'name' => $arquivos['name'][$i], 'type' => $arquivos['type'][$i], 'tmp_name' => $arquivos['tmp_name'][$i],
                'error' => $arquivos['error'][$i], 'size' => $arquivos['size'][$i],
            ];
        }

        return $lista;
    }

    /** Dados de um arquivo (para download ou exibição). */
    public static function buscar(int $id): ?array
    {
        $consulta = self::pdo()->prepare(
            'SELECT id, enviado_por, nome_original, caminho_armazenamento, mime_type, tamanho_bytes, sha256, criado_em
               FROM arquivos WHERE id = :id'
        );
        $consulta->execute(['id' => $id]);
        $arquivo = $consulta->fetch();

        return $arquivo ?: null;
    }

    /**
     * Tipo MIME pelo conteúdo. Arquivos do Office às vezes são vistos como zip (docx/xlsx)
     * ou como "CDFV2" (doc/xls antigos); nesses casos a extensão decide entre as opções do Office.
     */
    private static function tipoReal(string $caminho, string $nomeOriginal): string
    {
        $mime     = (string) (new finfo(FILEINFO_MIME_TYPE))->file($caminho);
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

        if (in_array($mime, ['application/zip', 'application/octet-stream'], true)) {
            $office = ['docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                       'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
            return $office[$extensao] ?? $mime;
        }
        if (in_array($mime, ['application/CDFV2', 'application/x-ole-storage'], true)) {
            $office = ['doc' => 'application/msword', 'xls' => 'application/vnd.ms-excel'];
            return $office[$extensao] ?? $mime;
        }

        return $mime;
    }
}
