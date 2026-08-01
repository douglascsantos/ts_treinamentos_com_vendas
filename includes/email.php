<?php
/**
 * E-mail transacional via mail() nativo do PHP — hospedagem compartilhada
 * (Hostinger), sem servidor SMTP dedicado configurado. Funciona bem quando
 * o remetente é do mesmo domínio do site (a própria Hostinger cuida do
 * SPF/DKIM da conta de e-mail do domínio). Se a entrega não for confiável no
 * futuro, trocar aqui por um relay SMTP dedicado — só esse arquivo muda.
 */

/** Envia um e-mail HTML simples. Nunca lança exceção — falha de envio não pode derrubar o fluxo de pagamento. */
function enviar_email(string $paraEmail, string $assunto, string $corpoHtml): bool
{
    $config = require __DIR__ . '/config.php';

    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . mb_encode_mimeheader($config['site_name']) . ' <' . $config['email'] . '>',
        'Reply-To: ' . $config['email'],
        'X-Mailer: PHP/' . phpversion(),
    ]);

    $assuntoCodificado = '=?UTF-8?B?' . base64_encode($assunto) . '?=';

    try {
        return @mail($paraEmail, $assuntoCodificado, $corpoHtml, $headers);
    } catch (Throwable $e) {
        return false;
    }
}

/** Moldura HTML simples reaproveitada por todo e-mail transacional do site. */
function email_moldura(string $tituloEmoji, string $corpoHtml): string
{
    $config = require __DIR__ . '/config.php';
    return '
    <div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;color:#2C3E50;padding:1.5rem;">
      <h2 style="color:#1B5E20;margin-bottom:1rem;">' . $tituloEmoji . '</h2>
      ' . $corpoHtml . '
      <p style="margin-top:2rem;padding-top:1rem;border-top:1px solid #E2E7EE;color:#5C6B7A;font-size:.85rem;">
        ' . e($config['site_name']) . ' · ' . e($config['phone']) . ' · ' . e($config['email']) . '
      </p>
    </div>';
}

/**
 * E-mail de confirmação de compra — disparado pelo webhook assim que o
 * pagamento é confirmado de verdade (nunca antes, nunca por engano — ver
 * webhook-infinitepay.php). $pedido já deve estar com status 'pago'.
 */
function enviar_email_compra_confirmada(array $aluno, array $pedido): bool
{
    $nome = trim(($aluno['pronome'] ?? '') . ' ' . $aluno['nome']);
    $assunto = 'Compra confirmada — ' . $pedido['descricao'];

    $corpo = '
      <p>Olá, ' . e($nome) . '!</p>
      <p>Recebemos o pagamento do seu pedido com sucesso. Aqui está o resumo:</p>
      <table style="width:100%;border-collapse:collapse;margin:1rem 0;">
        <tr><td style="padding:.4rem 0;color:#5C6B7A;">Item</td><td style="padding:.4rem 0;text-align:right;"><strong>' . e($pedido['descricao']) . '</strong></td></tr>
        <tr><td style="padding:.4rem 0;color:#5C6B7A;">Valor</td><td style="padding:.4rem 0;text-align:right;">' . e(format_price((float) $pedido['preco'])) . '</td></tr>
        <tr><td style="padding:.4rem 0;color:#5C6B7A;">Pedido</td><td style="padding:.4rem 0;text-align:right;">' . e($pedido['order_nsu']) . '</td></tr>
      </table>
      <p><a href="' . e(site_base_url()) . '/minha-conta.php" style="display:inline-block;background:#1B5E20;color:#fff;padding:.7rem 1.3rem;border-radius:.5rem;text-decoration:none;font-weight:700;">Ver na minha conta</a></p>';

    return enviar_email($aluno['email'], $assunto, email_moldura('Compra confirmada! 🎉', $corpo));
}
