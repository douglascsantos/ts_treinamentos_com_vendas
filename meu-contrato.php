<?php
/**
 * Contrato assinado na íntegra, com os dados reais do pedido/aluno — só o
 * dono do pedido pode ver (ownership checado pelo aluno_id vinculado).
 */
require __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/storage.php';
require __DIR__ . '/includes/alunos.php';
require __DIR__ . '/includes/pedidos.php';
require __DIR__ . '/includes/contratos.php';
require __DIR__ . '/includes/csrf.php';
csrf_ensure_session();

$config  = require __DIR__ . '/includes/config.php';
$version = require __DIR__ . '/includes/version.php';

if (empty($_SESSION['aluno_id'])) {
    header('Location: area-do-aluno.php');
    exit;
}

$orderNsu = $_GET['order_nsu'] ?? '';
$pedido = $orderNsu !== '' ? find_pedido_by_nsu($orderNsu) : null;

if (!$pedido || ($pedido['aluno_id'] ?? null) !== $_SESSION['aluno_id']) {
    http_response_code(404);
    $page_title = 'Contrato não encontrado | ' . $config['site_name'];
    $base_path = '';
    include __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="container page-content"><h1>Contrato não encontrado</h1><p>Esse contrato não existe ou não pertence à sua conta. <a href="minha-conta.php">Voltar para Minha Conta</a>.</p></div></section>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$aluno = null;
foreach (load_alunos() as $a) {
    if ($a['id'] === $_SESSION['aluno_id']) {
        $aluno = $a;
        break;
    }
}

$dadosAluno = [
    'nome'      => $aluno['nome'] ?? '',
    'cpf'       => isset($aluno['cpf']) ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $aluno['cpf']) : '',
    'curso'     => $pedido['descricao'],
    'preco'     => $pedido['preco'],
    'aceite_em' => $pedido['aceite_contrato_em'] ?? null,
    'aceite_ip' => $pedido['aceite_contrato_ip'] ?? null,
];

$page_title = 'Contrato — ' . $pedido['descricao'] . ' | ' . $config['site_name'];
$base_path = '';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="section-head" style="max-width:none;">
            <p class="eyebrow">Meu contrato</p>
            <h2><?= e($pedido['descricao']) ?></h2>
            <p class="muted">
                Pedido <?= e($pedido['order_nsu']) ?> ·
                Status: <strong><?= e(ucfirst($pedido['status'])) ?></strong>
                <?php if ($pedido['status'] === 'pago' && $pedido['receipt_url']): ?>
                    · <a href="<?= e($pedido['receipt_url']) ?>" target="_blank" rel="noopener">Ver recibo</a>
                <?php endif; ?>
            </p>
        </div>

        <div class="contrato-print-btn" style="margin-top:1.5rem;">
            <button type="button" class="btn btn-primary" onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>
            <a class="btn btn-outline-dark" href="minha-conta.php">← Voltar pra Minha Conta</a>
        </div>

        <div class="contrato-full" style="margin-top:2rem;">
            <?php if ($pedido['tipo'] === 'curso'): ?>
                <?php render_contrato_prestacao_servicos($config, $dadosAluno); ?>
                <?php render_contrato_lgpd_imagem($config, $dadosAluno); ?>
                <?php render_contrato_risco_procedimentos($config, $dadosAluno); ?>
            <?php else: ?>
                <?php render_contrato_produtos($config, $dadosAluno); ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
