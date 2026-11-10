<?php
/**
 * Pagamento de uma parcela (tabela pagamentos). Classe abstrata, como no diagrama:
 * cada forma (Pix, cartão, dinheiro, boleto, transferência) é uma classe filha que diz
 * qual é a forma gravada e valida os seus dados próprios.
 *
 * Uso:
 *   $pix = new PagamentoPix($parcelaId, '500.00', chavePix: 'cliente@email.com');
 *   $id  = $pix->processarPagamento($usuarioId);
 *   $pix->verificarStatusPagamento(); // 'PAGO'
 *
 * Um estorno não apaga o pagamento: preenche estornado_em (Pagamento::estornar).
 */

abstract class Pagamento extends Modelo
{
    /** Formas do banco → rótulo para recibos e telas. */
    public const FORMAS = [
        'DINHEIRO'       => 'Dinheiro',
        'PIX'            => 'Pix',
        'CARTAO_CREDITO' => 'Cartão de crédito',
        'CARTAO_DEBITO'  => 'Cartão de débito',
        'BOLETO'         => 'Boleto',
        'TRANSFERENCIA'  => 'Transferência',
    ];

    protected ?int $idPagamento = null;
    protected int $parcelaId;
    protected string $valorPago;
    protected ?int $comprovanteArquivoId;
    protected ?string $pagoEm;
    // protected: as classes filhas também podem consultar o status.
    protected string $statusPagamento = 'PENDENTE';

    /**
     * @param string  $valor  Valor recebido ('1234.56', '1234,56' ou '1.234,56').
     * @param ?string $pagoEm Data/hora do recebimento ('Y-m-d H:i:s' ou 'Y-m-d\TH:i' do input datetime-local); vazio = agora.
     */
    public function __construct(int $parcelaId, string $valor, ?int $comprovanteArquivoId = null, ?string $pagoEm = null)
    {
        $this->parcelaId            = $parcelaId;
        $this->valorPago            = $valor;
        $this->comprovanteArquivoId = $comprovanteArquivoId;
        $this->pagoEm               = $pagoEm;
    }

    /** Forma gravada no banco (um dos valores de Pagamento::FORMAS). */
    abstract public function getForma(): string;

    /** Validação própria da forma de pagamento: devolve os erros por campo (vazio = válido). */
    abstract protected function validarDados(): array;

    /** Dados próprios da forma (ex.: final do cartão), guardados na auditoria — a tabela não tem colunas para eles. */
    protected function detalhes(): array
    {
        return [];
    }

    /**
     * Registra o pagamento (método processarPagamento do diagrama).
     * Regras: valor positivo; parcela existente, não cancelada e de contrato ativo ou finalizado;
     * valor até o saldo da parcela (não aceita pagar a mais).
     *
     * @return int id do pagamento.
     * @throws ErroValidacao com o status RECUSADO no objeto.
     */
    public function processarPagamento(int $usuarioId): int
    {
        try {
            $erros = $this->validarDados();
            $valor = self::campoDecimal(['valor' => $this->valorPago], 'valor', $erros, true);
            if ($valor !== null && self::centavos($valor) <= 0) {
                $erros['valor'] = 'Informe um valor maior que zero.';
            }
            $pagoEm = $this->dataPagamento($erros);
            self::lancarSeErros($erros);

            $this->idPagamento = self::transacao(function (PDO $pdo) use ($valor, $pagoEm, $usuarioId): int {
                $parcela = Parcela::situacao($this->parcelaId, true);
                if ($parcela === null) {
                    throw new ErroValidacao('Parcela não encontrada.', [], 404);
                }
                if ($parcela['cancelada']) {
                    throw new ErroValidacao('Esta parcela está cancelada.', [], 409);
                }
                if (!in_array($parcela['contrato_status'], ['ATIVO', 'FINALIZADO'], true)) {
                    throw new ErroValidacao('O contrato desta parcela não está ativo.', [], 409);
                }
                if ($parcela['saldo_c'] === 0) {
                    throw new ErroValidacao('Esta parcela já está paga.', [], 409);
                }
                if (self::centavos($valor) > $parcela['saldo_c']) {
                    $saldo = number_format($parcela['saldo_c'] / 100, 2, ',', '.');
                    throw new ErroValidacao("O valor ultrapassa o saldo da parcela (R$ $saldo).", ['valor' => "Saldo da parcela: R$ $saldo."]);
                }

                $pdo->prepare(
                    'INSERT INTO pagamentos (parcela_id, comprovante_arquivo_id, registrado_por, valor, pago_em, forma, criado_em)
                     VALUES (:parcela, :comprovante, :por, :valor, :pago, :forma, :criado)'
                )->execute([
                    'parcela' => $this->parcelaId, 'comprovante' => $this->comprovanteArquivoId, 'por' => $usuarioId,
                    'valor' => $valor, 'pago' => $pagoEm, 'forma' => $this->getForma(), 'criado' => self::agora(),
                ]);
                $id = (int) $pdo->lastInsertId();

                self::auditar($usuarioId, 'pagamentos', $id, 'REGISTRAR', ['parcela_id' => $this->parcelaId, 'valor' => $valor, 'forma' => $this->getForma()] + $this->detalhes());

                return $id;
            });
        } catch (ErroValidacao $e) {
            $this->statusPagamento = 'RECUSADO';
            throw $e;
        }

        $this->statusPagamento = 'PAGO';

        return $this->idPagamento;
    }

    /** PENDENTE (ainda não processado), PAGO ou RECUSADO. */
    public function verificarStatusPagamento(): string
    {
        return $this->statusPagamento;
    }

    public function getId(): ?int
    {
        return $this->idPagamento;
    }

    /** Rótulo da forma (ex.: "Cartão de crédito"). */
    public function getFormaPagamento(): string
    {
        return self::FORMAS[$this->getForma()];
    }

    // -------------------------------------------------------------------------
    // Funções sobre pagamentos já gravados
    // -------------------------------------------------------------------------

    /**
     * Cria o objeto da forma certa a partir dos dados de um formulário/API (fábrica).
     * Campos: forma, parcela_id, valor, pago_em, comprovante_arquivo_id e os campos próprios
     * de cada forma (chave_pix, ultimos_digitos, bandeira, parcelas, codigo_autorizacao, valor_entregue,
     * nosso_numero, banco).
     */
    public static function criarPorForma(array $dados): self
    {
        $parcelaId   = (int) ($dados['parcela_id'] ?? 0);
        $valor       = (string) ($dados['valor'] ?? '');
        $comprovante = isset($dados['comprovante_arquivo_id']) && $dados['comprovante_arquivo_id'] !== '' ? (int) $dados['comprovante_arquivo_id'] : null;
        $pagoEm      = isset($dados['pago_em']) && $dados['pago_em'] !== '' ? (string) $dados['pago_em'] : null;
        $texto       = static fn (string $campo): string => trim((string) ($dados[$campo] ?? ''));

        return match (strtoupper($texto('forma'))) {
            'PIX'                      => new PagamentoPix($parcelaId, $valor, $comprovante, $pagoEm, $texto('chave_pix')),
            'CARTAO_CREDITO', 'CREDITO' => new PagamentoCartao($parcelaId, $valor, $comprovante, $pagoEm, 'CREDITO', (int) ($dados['parcelas'] ?? 1), $texto('ultimos_digitos'), $texto('bandeira'), $texto('codigo_autorizacao')),
            'CARTAO_DEBITO', 'DEBITO'   => new PagamentoCartao($parcelaId, $valor, $comprovante, $pagoEm, 'DEBITO', 1, $texto('ultimos_digitos'), $texto('bandeira'), $texto('codigo_autorizacao')),
            'DINHEIRO'                 => new PagamentoDinheiro($parcelaId, $valor, $comprovante, $pagoEm, $texto('valor_entregue')),
            'BOLETO'                   => new PagamentoBoleto($parcelaId, $valor, $comprovante, $pagoEm, $texto('nosso_numero')),
            'TRANSFERENCIA'            => new PagamentoTransferencia($parcelaId, $valor, $comprovante, $pagoEm, $texto('banco')),
            default => throw new ErroValidacao('Selecione a forma de pagamento.', ['forma' => 'Forma de pagamento inválida.']),
        };
    }

    /** Pagamentos de uma parcela (inclusive estornados), do mais recente para o mais antigo. */
    public static function listarPorParcela(int $parcelaId): array
    {
        $consulta = self::pdo()->prepare(
            'SELECT pg.id, pg.valor, pg.pago_em, pg.forma, pg.estornado_em, pg.motivo_estorno, pg.comprovante_arquivo_id,
                    r.nome AS registrado_por, e.nome AS estornado_por
               FROM pagamentos pg
               LEFT JOIN usuarios r ON r.id = pg.registrado_por
               LEFT JOIN usuarios e ON e.id = pg.estornado_por
              WHERE pg.parcela_id = :id
              ORDER BY pg.pago_em DESC, pg.id DESC'
        );
        $consulta->execute(['id' => $parcelaId]);

        return array_map(static function (array $p): array {
            $p['id']                     = (int) $p['id'];
            $p['valor']                  = (float) $p['valor'];
            $p['comprovante_arquivo_id'] = $p['comprovante_arquivo_id'] !== null ? (int) $p['comprovante_arquivo_id'] : null;
            return $p;
        }, $consulta->fetchAll());
    }

    /** Estorna um pagamento: ele continua no histórico, mas deixa de contar no saldo. */
    public static function estornar(int $pagamentoId, string $motivo, int $usuarioId): void
    {
        $erros  = [];
        $motivo = self::campoTexto(['motivo_estorno' => $motivo], 'motivo_estorno', 500, $erros, true);
        self::lancarSeErros($erros);

        self::transacao(static function (PDO $pdo) use ($pagamentoId, $motivo, $usuarioId): void {
            $consulta = $pdo->prepare('SELECT id, estornado_em, valor, parcela_id FROM pagamentos WHERE id = :id FOR UPDATE');
            $consulta->execute(['id' => $pagamentoId]);
            $pagamento = $consulta->fetch();
            if (!$pagamento) {
                throw new ErroValidacao('Pagamento não encontrado.', [], 404);
            }
            if ($pagamento['estornado_em'] !== null) {
                throw new ErroValidacao('Este pagamento já foi estornado.', [], 409);
            }

            $pdo->prepare('UPDATE pagamentos SET estornado_em = :agora, estornado_por = :por, motivo_estorno = :motivo WHERE id = :id')
                ->execute(['agora' => self::agora(), 'por' => $usuarioId, 'motivo' => $motivo, 'id' => $pagamentoId]);
            self::auditar($usuarioId, 'pagamentos', $pagamentoId, 'ESTORNAR', [
                'parcela_id' => (int) $pagamento['parcela_id'], 'valor' => $pagamento['valor'], 'motivo' => $motivo,
            ]);
        });
    }

    /**
     * Corrige um pagamento lançado errado (valor, forma ou data).
     * Nada é apagado nem alterado no lançamento original: numa única transação, o pagamento antigo
     * é estornado ("Correção: motivo") e o novo é registrado com as mesmas regras do processarPagamento
     * (saldo, contrato ativo, data não futura). Se o novo for recusado, o estorno também é desfeito.
     *
     * @param array $dados forma, valor, pago_em (e os campos próprios da forma, se houver).
     * @return int id do novo pagamento.
     */
    public static function corrigir(int $pagamentoId, array $dados, string $motivo, int $usuarioId): int
    {
        $erros  = [];
        $motivo = self::campoTexto(['motivo' => $motivo], 'motivo', 480, $erros, true);
        if (isset($erros['motivo'])) {
            $erros['motivo'] = 'Informe o motivo da correção.';
        }
        self::lancarSeErros($erros);

        return self::transacao(static function (PDO $pdo) use ($pagamentoId, $dados, $motivo, $usuarioId): int {
            $consulta = $pdo->prepare('SELECT parcela_id, estornado_em FROM pagamentos WHERE id = :id FOR UPDATE');
            $consulta->execute(['id' => $pagamentoId]);
            $original = $consulta->fetch();
            if (!$original) {
                throw new ErroValidacao('Pagamento não encontrado.', [], 404);
            }
            if ($original['estornado_em'] !== null) {
                throw new ErroValidacao('Este pagamento já foi estornado e não pode ser corrigido.', [], 409);
            }

            // 1) Estorna o original (devolve o valor ao saldo da parcela).
            self::estornar($pagamentoId, 'Correção: ' . $motivo, $usuarioId);

            // 2) Registra o lançamento correto na mesma parcela (forma decide a classe: Pix, Cartão...).
            $novo = self::criarPorForma(['parcela_id' => (int) $original['parcela_id']] + $dados);
            $id   = $novo->processarPagamento($usuarioId);

            self::auditar($usuarioId, 'pagamentos', $id, 'CORRIGIR', ['substitui_pagamento_id' => $pagamentoId, 'motivo' => $motivo]);

            return $id;
        });
    }

    /**
     * Dados do recibo (método emitirRecibo do diagrama). null se não existe ou foi estornado,
     * porque pagamento estornado não tem recibo válido.
     */
    public static function emitirRecibo(int $pagamentoId): ?array
    {
        $consulta = self::pdo()->prepare(
            'SELECT pg.id, pg.valor, pg.pago_em, pg.forma, pg.estornado_em,
                    pa.numero AS parcela, pa.vencimento, c.numero_contrato AS contrato,
                    pe.nome_razao_social AS cliente, pe.cpf_cnpj, r.nome AS registrado_por
               FROM pagamentos pg
               JOIN parcelas pa ON pa.id = pg.parcela_id
               JOIN contratos c ON c.id = pa.contrato_id
               JOIN pessoas pe ON pe.id = c.cliente_id
               LEFT JOIN usuarios r ON r.id = pg.registrado_por
              WHERE pg.id = :id'
        );
        $consulta->execute(['id' => $pagamentoId]);
        $recibo = $consulta->fetch();
        if (!$recibo || $recibo['estornado_em'] !== null) {
            return null;
        }

        $empresa = Empresa::obterEmpresa();

        return [
            'numero'          => (int) $recibo['id'],
            'valor'           => (float) $recibo['valor'],
            'forma'           => self::FORMAS[$recibo['forma']] ?? $recibo['forma'],
            'pago_em'         => $recibo['pago_em'],
            'cliente'         => $recibo['cliente'],
            'cpf_cnpj'        => $recibo['cpf_cnpj'],
            'contrato'        => $recibo['contrato'],
            'parcela'         => (int) $recibo['parcela'],
            'vencimento'      => $recibo['vencimento'],
            'registrado_por'  => $recibo['registrado_por'],
            'empresa'         => $empresa !== null ? ($empresa['nome_fantasia'] ?: $empresa['razao_social']) : null,
            'cnpj_empresa'    => $empresa['cnpj'] ?? null,
        ];
    }

    // -------------------------------------------------------------------------

    /** Data/hora do pagamento: vazia = agora; não pode ser no futuro. */
    private function dataPagamento(array &$erros): ?string
    {
        if ($this->pagoEm === null || trim($this->pagoEm) === '') {
            return self::agora();
        }

        $texto = str_replace('T', ' ', trim($this->pagoEm));
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $formato) {
            $data = DateTime::createFromFormat('!' . $formato, $texto);
            if ($data && $data->format($formato) === $texto) {
                if ($data->format('Y-m-d H:i:s') > self::agora()) {
                    $erros['pago_em'] = 'A data do pagamento não pode ser no futuro.';
                    return null;
                }
                return $data->format('Y-m-d H:i:s');
            }
        }

        $erros['pago_em'] = 'Informe uma data válida.';

        return null;
    }
}
