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

/** URL absoluta (sempre https — obrigatório pra webhook_url da InfinitePay). */
function site_base_url(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'tstreinamento.com';
    return 'https://' . $host;
}

/** Preço formatado em R$ pt-BR. Compartilhado entre turmas e produtos. */
function format_price(float $preco): string
{
    if ($preco <= 0) {
        return 'Gratuito';
    }
    return 'R$ ' . number_format($preco, 2, ',', '.');
}

/** Primeiro nome, pro "Bem-vindo, Fulano" no cabeçalho. */
function primeiro_nome(string $nomeCompleto): string
{
    $partes = explode(' ', trim($nomeCompleto));
    return $partes[0] ?? '';
}

/** Mesma lógica de tools/sync_common.py:slugify() — minúsculo, sem acento, só a-z0-9 e hífen. */
function slugify(string $texto): string
{
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    $mapa = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ];
    $texto = strtr($texto, $mapa);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';
    return trim($texto, '-');
}
