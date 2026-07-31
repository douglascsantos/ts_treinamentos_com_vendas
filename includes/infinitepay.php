<?php
/**
 * Cliente da API de Checkout Integrado da InfinitePay.
 * Doc oficial: https://www.infinitepay.io/checkout-documentacao
 *
 * IMPORTANTE (segurança): a API não usa nenhuma chave/token — a identificação
 * é só pelo "handle" no payload, e o webhook de confirmação não traz nenhuma
 * assinatura verificável. Por isso NUNCA marcamos um pedido como pago só por
 * ter recebido o webhook — sempre confirmamos de volta com infinitepay_check_payment()
 * antes de considerar o pagamento válido (ver webhook-infinitepay.php).
 */

const INFINITEPAY_API_BASE = 'https://api.checkout.infinitepay.io';

/** Faz um POST JSON simples via cURL. Retorna [statusCode, arrayDecodificado|null]. */
function infinitepay_post(string $path, array $body): array
{
    $ch = curl_init(INFINITEPAY_API_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return [0, ['success' => false, 'error' => 'curl_error: ' . $err]];
    }
    $decoded = json_decode($raw, true);
    return [$status, is_array($decoded) ? $decoded : ['success' => false, 'raw' => $raw]];
}

/**
 * Cria um link de checkout para um item único.
 * $item = ['description' => string, 'price_centavos' => int, 'quantity' => int]
 * Retorna ['ok' => bool, 'url' => ?string, 'raw' => array] — nunca lança exceção,
 * quem chama decide o que fazer em caso de falha.
 */
function infinitepay_create_link(
    string $handle,
    array $item,
    string $orderNsu,
    ?string $redirectUrl = null,
    ?string $webhookUrl = null,
    ?array $customer = null
): array {
    $payload = [
        'handle' => $handle,
        'items' => [[
            'quantity'    => $item['quantity'] ?? 1,
            'price'       => (int) $item['price_centavos'],
            'description' => (string) $item['description'],
        ]],
        'order_nsu' => $orderNsu,
    ];
    if ($redirectUrl) {
        $payload['redirect_url'] = $redirectUrl;
    }
    if ($webhookUrl) {
        $payload['webhook_url'] = $webhookUrl;
    }
    if ($customer) {
        $payload['customer'] = $customer;
    }

    [$status, $data] = infinitepay_post('/links', $payload);

    // A resposta de sucesso observada na prática é {"url": "https://checkout.infinitepay.io/..."}.
    // A doc oficial não documenta o formato — checamos algumas variações de nome de
    // campo por segurança, mas "url" é o confirmado.
    $url = $data['url'] ?? $data['link'] ?? $data['checkout_url'] ?? $data['payment_url'] ?? null;

    return [
        'ok'  => $status === 200 && $url !== null,
        'url' => $url,
        'raw' => $data,
    ];
}

/** Consulta o status real de um pagamento — usado para confirmar o webhook. */
function infinitepay_check_payment(string $handle, string $orderNsu, string $transactionNsu, string $slug): array
{
    [$status, $data] = infinitepay_post('/payment_check', [
        'handle'          => $handle,
        'order_nsu'       => $orderNsu,
        'transaction_nsu' => $transactionNsu,
        'slug'            => $slug,
    ]);

    return [
        'ok'   => $status === 200 && ($data['success'] ?? false) === true,
        'paid' => ($data['paid'] ?? false) === true,
        'raw'  => $data,
    ];
}
