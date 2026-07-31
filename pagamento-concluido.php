<?php
/**
 * Página de retorno após o pagamento (redirect_url do checkout InfinitePay).
 * Só exibe uma confirmação amigável — quem realmente marca o pedido como
 * pago é o webhook-infinitepay.php (não dá pra confiar em parâmetros de URL,
 * que o próprio cliente poderia manipular).
 */
require __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/storage.php';
require __DIR__ . '/includes/pedidos.php';

$config  = require __DIR__ . '/includes/config.php';
$version = require __DIR__ . '/includes/version.php';

$orderNsu   = $_GET['order_nsu'] ?? '';
$receiptUrl = $_GET['receipt_url'] ?? '';
$captureMethod = $_GET['capture_method'] ?? '';

$pedido = $orderNsu !== '' ? find_pedido_by_nsu($orderNsu) : null;
$descricao = $pedido['descricao'] ?? null;

$page_title = 'Pagamento recebido | ' . $config['site_name'];
$base_path = '';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container page-content checkout-status-page">
        <span class="badge badge-success">✅ Pagamento recebido</span>
        <h1 class="checkout-status-title">Obrigado pela sua compra!</h1>
        <?php if ($descricao): ?>
            <p class="lead-muted checkout-status-msg">Recebemos seu pagamento de <strong><?= e($descricao) ?></strong>. Agora falta só um passo: entre ou crie sua conta na Área do Aluno para acompanhar tudo.</p>
        <?php else: ?>
            <p class="lead-muted checkout-status-msg">Recebemos a confirmação do seu pagamento. Agora falta só um passo: entre ou crie sua conta na Área do Aluno para acompanhar tudo.</p>
        <?php endif; ?>

        <div class="checkout-actions">
            <a class="btn btn-accent btn-lg" href="<?= e('area-do-aluno.php' . ($orderNsu ? '?order_nsu=' . rawurlencode($orderNsu) : '')) ?>">Entrar / Criar minha conta</a>
        </div>
        <div class="checkout-actions">
            <?php if ($receiptUrl): ?>
                <a class="btn btn-outline-dark" href="<?= e($receiptUrl) ?>" target="_blank" rel="noopener">Ver comprovante</a>
            <?php endif; ?>
            <a class="btn btn-primary" href="index.php">Voltar ao site</a>
        </div>

        <?php if ($orderNsu): ?>
            <p class="curso-note checkout-order-nsu">Número do pedido: <?= e($orderNsu) ?></p>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
