<?php
/**
 * Download protegido de e-book: só o dono de um pedido pago daquele produto
 * pode baixar. slug vem na URL, resolvemos o pedido/arquivo no servidor —
 * nunca confiamos em nome de arquivo vindo do cliente.
 */
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/storage.php';
require __DIR__ . '/includes/alunos.php';
require __DIR__ . '/includes/pedidos.php';
require __DIR__ . '/includes/produtos.php';
require __DIR__ . '/includes/ebooks.php';
require __DIR__ . '/includes/csrf.php';
csrf_ensure_session();

if (empty($_SESSION['aluno_id'])) {
    header('Location: area-do-aluno.php');
    exit;
}

$slug = $_GET['produto'] ?? '';
$produto = find_produto($slug);
if (!$produto || !produto_e_digital($produto['tipo']) || empty($produto['arquivo_ebook'])) {
    http_response_code(404);
    exit('E-book não encontrado.');
}

$possui = false;
foreach (find_pedidos_by_aluno($_SESSION['aluno_id']) as $p) {
    if ($p['tipo'] === 'produto' && $p['slug'] === $slug && $p['status'] === 'pago') {
        $possui = true;
        break;
    }
}
if (!$possui) {
    http_response_code(403);
    exit('Você não tem esse e-book na sua conta.');
}

$caminho = ebooks_dir() . '/' . $produto['arquivo_ebook'];
if (!is_file($caminho)) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $slug . '.pdf"');
header('Content-Length: ' . filesize($caminho));
readfile($caminho);
exit;
