<?php
/**
 * Serve a imagem de assinatura (instrutor ou administrativo) de um
 * certificado JÁ EMITIDO — nunca serve assinatura de quem não assinou nenhum
 * certificado real, escopo limitado ao que já é público pela própria
 * verificação do diploma (ver certificado.php). Arquivo vive fora do
 * public_html (includes/assinaturas.php); esse endpoint é o único jeito de
 * exibi-lo numa página pública.
 */
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/env.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/certificados.php';
require __DIR__ . '/includes/instrutores.php';
require __DIR__ . '/includes/administradores.php';
require __DIR__ . '/includes/assinaturas.php';

$codigo = $_GET['codigo'] ?? '';
$papel = $_GET['papel'] ?? '';

try {
    $certificado = $codigo !== '' ? find_certificado_by_codigo($codigo) : null;
} catch (Throwable $e) {
    http_response_code(404);
    exit;
}
if (!$certificado || !in_array($papel, ['instrutor', 'administrativo'], true)) {
    http_response_code(404);
    exit;
}

$pessoa = $papel === 'instrutor'
    ? find_instrutor_by_id($certificado['instrutor_id'])
    : find_administrador_by_id($certificado['diretor_id']);

$arquivo = $pessoa['assinatura_imagem'] ?? null;
if (!$arquivo) {
    http_response_code(404);
    exit;
}

$caminho = assinaturas_dir() . '/' . $arquivo;
if (!is_file($caminho)) {
    http_response_code(404);
    exit;
}

$extensao = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
$mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
header('Content-Type: ' . ($mimes[$extensao] ?? 'application/octet-stream'));
header('Cache-Control: public, max-age=86400');
readfile($caminho);
