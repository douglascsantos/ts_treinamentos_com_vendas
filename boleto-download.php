<?php
/**
 * Download protegido de boleto: só o dono (aluno logado) baixa, e só se pago
 * ou dentro do prazo (ver includes/boletos.php:boleto_situacao()).
 */
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/storage.php';
require __DIR__ . '/includes/alunos.php';
require __DIR__ . '/includes/env.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/boletos.php';
require __DIR__ . '/includes/csrf.php';
csrf_ensure_session();

if (empty($_SESSION['aluno_id'])) {
    header('Location: area-do-aluno.php');
    exit;
}

$boleto = find_boleto_by_id($_GET['id'] ?? '');
if (!$boleto || $boleto['aluno_id'] !== $_SESSION['aluno_id']) {
    http_response_code(404);
    exit('Boleto não encontrado.');
}

[, , $podeBaixar] = boleto_situacao($boleto);
if (!$podeBaixar) {
    http_response_code(403);
    exit('Esse boleto está vencido. Fale com a equipe pra regularizar.');
}

$caminho = boletos_dir() . '/' . $boleto['arquivo_pdf'];
if (!is_file($caminho)) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="boleto-parcela-' . (int) $boleto['numero_parcela'] . '.pdf"');
header('Content-Length: ' . filesize($caminho));
readfile($caminho);
exit;
