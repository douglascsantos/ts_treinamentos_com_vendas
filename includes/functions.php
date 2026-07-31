<?php
/** Escapa texto para saída segura em HTML. */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/** Monta um link wa.me com mensagem pré-preenchida (URL-encoded). */
function wa_link(string $whatsapp, string $message = ''): string
{
    $url = 'https://wa.me/' . preg_replace('/\D/', '', $whatsapp);
    if ($message !== '') {
        $url .= '?text=' . rawurlencode($message);
    }
    return $url;
}

/** Preço formatado em R$ pt-BR. Compartilhado entre turmas e produtos. */
function format_price(float $preco): string
{
    if ($preco <= 0) {
        return 'Gratuito';
    }
    return 'R$ ' . number_format($preco, 2, ',', '.');
}
