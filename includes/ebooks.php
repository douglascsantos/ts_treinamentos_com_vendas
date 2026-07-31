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
