<?php
/**
 * Armazenamento da imagem de assinatura de instrutor/administrador (diretor)
 * — usada no certificado (ver ROADMAP.md). Mesmo princípio de segurança de
 * includes/fotos.php: fora do repositório, fora do public_html.
 */

const ASSINATURA_MIME_EXTENSAO = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

const ASSINATURA_TAMANHO_MAXIMO = 5 * 1024 * 1024; // 5MB

function assinaturas_dir(): string
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }

    $outside = dirname(__DIR__, 2) . '/ts_site_data/assinaturas';
    if (is_dir($outside) || @mkdir($outside, 0755, true)) {
        if (is_writable($outside)) {
            return $dir = $outside;
        }
    }

    $fallback = __DIR__ . '/../data/assinaturas';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0755, true);
    }
    return $dir = $fallback;
}

/** Mesma validação de includes/fotos.php — imagem opcional, nunca lança exceção. */
function salvar_assinatura(string $donoId, array $arquivo): ?string
{
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (($arquivo['size'] ?? 0) <= 0 || $arquivo['size'] > ASSINATURA_TAMANHO_MAXIMO) {
        return null;
    }

    $info = @getimagesize($arquivo['tmp_name']);
    if (!$info || !isset(ASSINATURA_MIME_EXTENSAO[$info['mime']])) {
        return null;
    }

    $nomeArquivo = $donoId . '.' . ASSINATURA_MIME_EXTENSAO[$info['mime']];
    $destino = assinaturas_dir() . '/' . $nomeArquivo;

    if (!is_uploaded_file($arquivo['tmp_name']) || !move_uploaded_file($arquivo['tmp_name'], $destino)) {
        return null;
    }

    return $nomeArquivo;
}
