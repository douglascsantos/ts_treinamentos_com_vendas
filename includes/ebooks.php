<?php
/**
 * Resolve o diretório dos PDFs de e-book comprados — fora do repositório/
 * public_html, mesmo princípio de segurança de includes/certificados.php.
 * Os arquivos são vinculados a `produtos.arquivo_ebook` pelo agente
 * produtos-sync (tools/sync_produtos.py), com nome não adivinhável.
 */
function ebooks_dir(): string
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }

    $outside = dirname(__DIR__, 2) . '/ts_site_data/ebooks_protegidos';
    if (is_dir($outside) || @mkdir($outside, 0755, true)) {
        if (is_writable($outside)) {
            return $dir = $outside;
        }
    }

    $fallback = __DIR__ . '/../data/ebooks_protegidos';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0755, true);
    }
    return $dir = $fallback;
}

const EBOOK_TAMANHO_MAXIMO = 30 * 1024 * 1024; // 30MB

/** Valida que é um PDF de verdade (assinatura %PDF-) antes de salvar — mesmo princípio de salvar_boleto_pdf(). */
function salvar_ebook_pdf(string $slug, array $arquivo): ?string
{
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (($arquivo['size'] ?? 0) <= 0 || $arquivo['size'] > EBOOK_TAMANHO_MAXIMO) {
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

    $nomeArquivo = $slug . '_' . bin2hex(random_bytes(8)) . '.pdf';
    if (!is_uploaded_file($arquivo['tmp_name']) || !move_uploaded_file($arquivo['tmp_name'], ebooks_dir() . '/' . $nomeArquivo)) {
        return null;
    }
    return $nomeArquivo;
}
