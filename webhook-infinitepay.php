<?php
/**
 * Recebe a notificação de pagamento da InfinitePay.
 *
 * SEGURANÇA: a InfinitePay não assina/autentica essa chamada de nenhuma forma
 * documentada — qualquer um que descubra essa URL poderia, em teoria, mandar
 * um POST forjado dizendo "isso foi pago". Por isso NUNCA confiamos direto no
 * corpo recebido: sempre confirmamos de volta com a API (payment_check) antes
 * de marcar um pedido como pago de verdade.
 *
 * Deve responder rápido (idealmente <1s). Em erro, responder 400 faz a
 * InfinitePay tentar reenviar depois.
 */
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/storage.php';
require __DIR__ . '/includes/pedidos.php';
require __DIR__ . '/includes/infinitepay.php';

$config = require __DIR__ . '/includes/config.php';

function webhook_responder(int $status, array $body = []): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body);
    exit;
}

function webhook_log(string $linha): void
{
    $dir = dirname(storage_path(PEDIDOS_FILE));
    @file_put_contents(
        $dir . '/webhook.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $linha . "\n",
        FILE_APPEND | LOCK_EX
    );
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);

if (!is_array($payload) || empty($payload['order_nsu'])) {
    webhook_log('Payload inválido/sem order_nsu: ' . substr((string) $raw, 0, 500));
    webhook_responder(400, ['success' => false, 'error' => 'invalid payload']);
}

$orderNsu = (string) $payload['order_nsu'];
$transactionNsu = (string) ($payload['transaction_nsu'] ?? '');
$invoiceSlug = (string) ($payload['invoice_slug'] ?? '');
$captureMethod = (string) ($payload['capture_method'] ?? '');
$receiptUrl = (string) ($payload['receipt_url'] ?? '');

$pedido = find_pedido_by_nsu($orderNsu);
if (!$pedido) {
    // Não existe pedido nosso com esse order_nsu — reenviar não vai resolver.
    webhook_log("Webhook para order_nsu desconhecido: {$orderNsu}");
    webhook_responder(200, ['success' => true, 'note' => 'order_nsu not found, ignored']);
}

if ($transactionNsu === '' || $invoiceSlug === '') {
    webhook_log("Webhook sem transaction_nsu/invoice_slug para {$orderNsu}");
    webhook_responder(400, ['success' => false, 'error' => 'missing transaction_nsu/invoice_slug']);
}

// Confirma de verdade com a InfinitePay antes de considerar pago.
$confirmacao = infinitepay_check_payment($config['infinitepay_handle'], $orderNsu, $transactionNsu, $invoiceSlug);

if (!$confirmacao['ok']) {
    // Falha ao consultar (rede, etc.) — pede reenvio.
    webhook_log("payment_check falhou para {$orderNsu}: " . json_encode($confirmacao['raw']));
    webhook_responder(400, ['success' => false, 'error' => 'payment_check failed']);
}

if (!$confirmacao['paid']) {
    // InfinitePay confirma que NÃO está pago — não insiste, não marca como pago.
    webhook_log("payment_check diz não-pago para {$orderNsu}, webhook dizia pago — ignorando.");
    webhook_responder(200, ['success' => true, 'note' => 'payment_check says not paid']);
}

atualizar_pedido($orderNsu, [
    'status'          => 'pago',
    'transaction_nsu' => $transactionNsu,
    'invoice_slug'    => $invoiceSlug,
    'capture_method'  => $captureMethod,
    'receipt_url'     => $receiptUrl,
]);

webhook_log("Pedido {$orderNsu} confirmado como PAGO ({$captureMethod}).");
webhook_responder(200, ['success' => true]);
