<?php
/**
 * Boletos parcelados (tabela `boletos`, MySQL) — administrativo cadastra 1
 * PDF por mês de parcelamento (upload manual). Aluno baixa na guia
 * financeira (financeiro.php) enquanto dentro do prazo; vencido sem
 * pagamento BLOQUEIA o download; pago sempre libera. "Vencido" nunca é
 * gravado — é calculado na hora (ver boleto_situacao()).
 */

const BOLETO_TAMANHO_MAXIMO = 10 * 1024 * 1024; // 10MB

function boletos_dir(): string
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }

    $outside = dirname(__DIR__, 2) . '/ts_site_data/boletos';
    if (is_dir($outside) || @mkdir($outside, 0755, true)) {
        if (is_writable($outside)) {
            return $dir = $outside;
        }
    }

    $fallback = __DIR__ . '/../data/boletos';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0755, true);
    }
    return $dir = $fallback;
}

/** Valida que é um PDF de verdade (assinatura %PDF-) antes de salvar. */
function salvar_boleto_pdf(string $nomeBase, array $arquivo): ?string
{
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (($arquivo['size'] ?? 0) <= 0 || $arquivo['size'] > BOLETO_TAMANHO_MAXIMO) {
        return null;
    }
    $handle = @fopen($arquivo['tmp_name'], 'rb');
    if (!$handle) {
        return null;
    }
    $assinatura = fread($handle, 5);
    fclose($handle);
    if ($assinatura !== '%PDF-') {
        return null;
    }

    $nomeArquivo = $nomeBase . '.pdf';
    $destino = boletos_dir() . '/' . $nomeArquivo;
    if (!is_uploaded_file($arquivo['tmp_name']) || !move_uploaded_file($arquivo['tmp_name'], $destino)) {
        return null;
    }
    return $nomeArquivo;
}

/** $dados: aluno_id, pedido_id, numero_parcela, vencimento (Y-m-d), arquivo_pdf, criado_por. */
function criar_boleto(array $dados): array
{
    $id = gerar_id('BL');
    $stmt = db()->prepare(
        'INSERT INTO boletos (id, aluno_id, pedido_id, numero_parcela, arquivo_pdf, vencimento, criado_por, criado_em)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $id, $dados['aluno_id'], $dados['pedido_id'], (int) $dados['numero_parcela'],
        $dados['arquivo_pdf'], $dados['vencimento'], $dados['criado_por'], date('Y-m-d H:i:s'),
    ]);
    return find_boleto_by_id($id);
}

function find_boleto_by_id(string $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM boletos WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function listar_boletos_por_aluno(string $alunoId): array
{
    $stmt = db()->prepare('SELECT * FROM boletos WHERE aluno_id = ? ORDER BY vencimento ASC');
    $stmt->execute([$alunoId]);
    return $stmt->fetchAll();
}

function listar_boletos(): array
{
    return db()->query('SELECT * FROM boletos ORDER BY criado_em DESC')->fetchAll();
}

function marcar_boleto_pago(string $id): ?array
{
    $stmt = db()->prepare("UPDATE boletos SET status = 'pago', pago_em = ? WHERE id = ?");
    $stmt->execute([date('Y-m-d H:i:s'), $id]);
    return find_boleto_by_id($id);
}

/** ['label', 'classe css', 'pode_baixar'] — vencido nunca é gravado, sempre calculado aqui. */
function boleto_situacao(array $boleto): array
{
    if ($boleto['status'] === 'pago') {
        return ['Pago', 'status-open', true];
    }
    if (strtotime($boleto['vencimento']) < strtotime('today')) {
        return ['Vencido', 'status-out', false];
    }
    return ['Em aberto', 'status-last', true];
}
