<?php
/**
 * Página de retorno após o pagamento (redirect_url do checkout InfinitePay).
 * Quem realmente marca o pedido como pago é o webhook-infinitepay.php (não dá
 * pra confiar em parâmetro de URL, que o próprio cliente poderia manipular)
 * — então o pagamento pode ainda aparecer "pendente" aqui por alguns
 * segundos, até o webhook chegar. Como o site agora exige conta ANTES do
 * pagamento, o aluno já está logado nesse ponto — mostramos direto o que ele
 * comprou: e-book já liberado pra baixar, ou a turma em que foi matriculado.
 */
require __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/storage.php';
require __DIR__ . '/includes/alunos.php';
require __DIR__ . '/includes/turmas.php';
require __DIR__ . '/includes/produtos.php';
require __DIR__ . '/includes/pedidos.php';
require __DIR__ . '/includes/csrf.php';
csrf_ensure_session();

$config  = require __DIR__ . '/includes/config.php';
$version = require __DIR__ . '/includes/version.php';

$orderNsu   = $_GET['order_nsu'] ?? '';
$receiptUrl = $_GET['receipt_url'] ?? '';

$pedido = $orderNsu !== '' ? find_pedido_by_nsu($orderNsu) : null;

// Segurança: só mostra detalhe de pedido de quem está logado E é dono dele —
// nunca confia só no order_nsu vindo da URL (dá pra adivinhar/compartilhar).
$aluno = !empty($_SESSION['aluno_id']) ? find_aluno_by_id($_SESSION['aluno_id']) : null;
$meuPedido = $pedido && $aluno && ($pedido['aluno_id'] ?? null) === $aluno['id'] ? $pedido : null;

$pago = $meuPedido && $meuPedido['status'] === 'pago';
$turma = $meuPedido && $meuPedido['tipo'] === 'curso' ? find_turma($meuPedido['slug']) : null;
$produto = $meuPedido && $meuPedido['tipo'] === 'produto' ? find_produto($meuPedido['slug']) : null;
$produtoDigital = $produto && produto_e_digital($produto['tipo']);

$page_title = 'Pagamento recebido | ' . $config['site_name'];
$base_path = '';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container page-content checkout-status-page">
        <span class="badge badge-success">✅ Pagamento recebido</span>
        <h1 class="checkout-status-title"><?= $aluno ? 'Obrigado pela compra, ' . e(trim(($aluno['pronome'] ?? '') . ' ' . $aluno['nome'])) . '!' : 'Obrigado pela sua compra!' ?></h1>

        <?php if (!$meuPedido): ?>
            <p class="lead-muted checkout-status-msg">Recebemos a confirmação do seu pagamento. <a href="area-do-aluno.php">Entre na sua conta</a> pra acompanhar os detalhes.</p>
        <?php elseif (!$pago): ?>
            <p class="lead-muted checkout-status-msg">
                Estamos confirmando seu pagamento de <strong><?= e($meuPedido['descricao']) ?></strong> — isso costuma levar só alguns segundos.
            </p>
            <div class="checkout-actions">
                <a class="btn btn-outline-dark" href="<?= e('pagamento-concluido.php?order_nsu=' . rawurlencode($orderNsu) . ($receiptUrl ? '&receipt_url=' . rawurlencode($receiptUrl) : '')) ?>">🔄 Atualizar esta página</a>
            </div>
        <?php elseif ($turma): ?>
            <p class="lead-muted checkout-status-msg">Sua vaga em <strong><?= e($turma['curso']) ?></strong> está confirmada! Você foi matriculado(a) na turma:</p>
            <dl class="curso-meta" style="margin:1.5rem auto;max-width:320px;text-align:left;grid-template-columns:1fr;">
                <div><dt>📅 Data(s)</dt><dd><?= e(turma_dates_full($turma['datas'])) ?></dd></div>
                <div><dt>🕐 Horário</dt><dd><?= e($turma['horario']) ?></dd></div>
                <div><dt>📍 Local</dt><dd><?= e($turma['local']) ?></dd></div>
            </dl>
        <?php elseif ($produtoDigital): ?>
            <p class="lead-muted checkout-status-msg">Seu e-book <strong><?= e($meuPedido['descricao']) ?></strong> já está liberado pra download!</p>
            <div class="checkout-actions">
                <a class="btn btn-accent btn-lg" href="ebook-download.php?produto=<?= e(rawurlencode($meuPedido['slug'])) ?>">📄 Baixar meu e-book</a>
            </div>
        <?php else: ?>
            <p class="lead-muted checkout-status-msg">Seu pedido de <strong><?= e($meuPedido['descricao']) ?></strong> está confirmado — assim que for enviado, você pode acompanhar o status na sua conta.</p>
        <?php endif; ?>

        <div class="checkout-actions">
            <a class="btn btn-primary" href="minha-conta.php">Ir para Minha Conta</a>
            <?php if ($receiptUrl): ?>
                <a class="btn btn-outline-dark" href="<?= e($receiptUrl) ?>" target="_blank" rel="noopener">Ver comprovante</a>
            <?php endif; ?>
        </div>

        <?php if ($orderNsu): ?>
            <p class="curso-note checkout-order-nsu">Número do pedido: <?= e($orderNsu) ?></p>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
