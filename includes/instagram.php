<?php
/**
 * Carrossel do Instagram — busca as publicações/reels mais recentes via
 * Instagram Graph API (conta Business/Creator), com cache em arquivo e
 * fallback estático para fotos reais do site quando não há token
 * configurado ou a API falha. Ver INSTAGRAM_SETUP.md para como gerar as
 * credenciais (INSTAGRAM_ACCESS_TOKEN / INSTAGRAM_USER_ID em secret.env).
 */

const INSTAGRAM_CACHE_FILE = __DIR__ . '/../data/instagram-cache.json';
const INSTAGRAM_CACHE_TTL  = 3600; // 1 hora
const INSTAGRAM_GRAPH_VERSION = 'v21.0';
const INSTAGRAM_MAX_ITEMS = 8;

/** Fallback estático — fotos reais já existentes no site. Usado sempre que
 *  o Instagram não estiver configurado ou a API não responder. */
function instagram_static_fallback(): array
{
    return [
        ['img' => 'assets/images/hero-training.jpg', 'alt' => 'Treinamento prático com profissionais de saúde', 'link' => null, 'type' => 'image'],
        ['img' => 'assets/images/fachada.png', 'alt' => 'Fachada da TS Treinamentos em Saúde', 'link' => null, 'type' => 'image'],
        ['img' => 'assets/images/cursos/course-ecg.jpg', 'alt' => 'Prática de eletrocardiograma', 'link' => null, 'type' => 'image'],
        ['img' => 'assets/images/cursos/course-medicacao-injetavel.jpg', 'alt' => 'Prática de administração de medicação injetável', 'link' => null, 'type' => 'image'],
        ['img' => 'assets/images/cursos/course-furo-humanizado.jpg', 'alt' => 'Treinamento de perfuração humanizada', 'link' => null, 'type' => 'image'],
        ['img' => 'assets/images/cursos/course-processo-seletivo.jpg', 'alt' => 'Preparatório para processo seletivo', 'link' => null, 'type' => 'image'],
    ];
}

/** Faz uma chamada GET simples e retorna o JSON decodificado, ou null em qualquer falha. */
function instagram_http_get(string $url): ?array
{
    $context = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || isset($data['error'])) {
        return null;
    }
    return $data;
}

/** Busca mídia recente (fotos, vídeos e reels) do feed via Graph API. */
function instagram_fetch_media(string $userId, string $token): array
{
    $fields = 'id,caption,media_type,media_product_type,media_url,thumbnail_url,permalink,timestamp';
    $url = "https://graph.facebook.com/" . INSTAGRAM_GRAPH_VERSION . "/{$userId}/media"
        . "?fields={$fields}&limit=" . INSTAGRAM_MAX_ITEMS . "&access_token=" . rawurlencode($token);

    $data = instagram_http_get($url);
    if (!$data || empty($data['data'])) {
        return [];
    }

    $items = [];
    foreach ($data['data'] as $post) {
        $isVideo = ($post['media_type'] ?? '') === 'VIDEO';
        $isReel  = ($post['media_product_type'] ?? '') === 'REELS';
        $img = $isVideo ? ($post['thumbnail_url'] ?? $post['media_url'] ?? null) : ($post['media_url'] ?? null);
        if (!$img) {
            continue;
        }
        $items[] = [
            'img'  => $img,
            'alt'  => mb_strimwidth((string) ($post['caption'] ?? 'Publicação do Instagram da TS Treinamentos'), 0, 120, '…'),
            'link' => $post['permalink'] ?? null,
            'type' => $isReel ? 'reel' : ($isVideo ? 'video' : 'image'),
        ];
    }
    return $items;
}

/**
 * Busca stories ativos (últimas 24h). Requer permissão elevada da Meta
 * (instagram_manage_stories) além do acesso básico de leitura de mídia —
 * pode não funcionar sem uma revisão de app aprovada. Falha em silêncio.
 */
function instagram_fetch_stories(string $userId, string $token): array
{
    $fields = 'media_type,media_url,thumbnail_url,permalink,timestamp';
    $url = "https://graph.facebook.com/" . INSTAGRAM_GRAPH_VERSION . "/{$userId}/stories"
        . "?fields={$fields}&access_token=" . rawurlencode($token);

    $data = instagram_http_get($url);
    if (!$data || empty($data['data'])) {
        return [];
    }

    $items = [];
    foreach ($data['data'] as $story) {
        $isVideo = ($story['media_type'] ?? '') === 'VIDEO';
        $img = $isVideo ? ($story['thumbnail_url'] ?? $story['media_url'] ?? null) : ($story['media_url'] ?? null);
        if (!$img) {
            continue;
        }
        $items[] = [
            'img'  => $img,
            'alt'  => 'Story recente da TS Treinamentos',
            'link' => $story['permalink'] ?? null,
            'type' => 'story',
        ];
    }
    return $items;
}

/** Lê o cache local, se existir e ainda estiver dentro do TTL. */
function instagram_read_cache(bool $allowStale = false): ?array
{
    if (!is_file(INSTAGRAM_CACHE_FILE)) {
        return null;
    }
    $raw = file_get_contents(INSTAGRAM_CACHE_FILE);
    $cache = json_decode($raw, true);
    if (!is_array($cache) || empty($cache['items']) || !isset($cache['fetched_at'])) {
        return null;
    }
    $fresh = (time() - (int) $cache['fetched_at']) < INSTAGRAM_CACHE_TTL;
    if ($fresh || $allowStale) {
        return $cache['items'];
    }
    return null;
}

function instagram_write_cache(array $items): void
{
    $dir = dirname(INSTAGRAM_CACHE_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents(INSTAGRAM_CACHE_FILE, json_encode([
        'fetched_at' => time(),
        'items'      => $items,
    ]));
}

/**
 * Ponto de entrada usado pelo index.php. Sempre retorna uma lista pronta
 * para renderizar — nunca deixa a seção vazia.
 */
function get_instagram_gallery(array $config): array
{
    $token  = env('INSTAGRAM_ACCESS_TOKEN');
    $userId = env('INSTAGRAM_USER_ID');

    if (!$token || !$userId) {
        return instagram_static_fallback();
    }

    $cached = instagram_read_cache();
    if ($cached !== null) {
        return $cached;
    }

    $stories = instagram_fetch_stories($userId, $token);
    $media   = instagram_fetch_media($userId, $token);
    $items   = array_slice(array_merge($stories, $media), 0, INSTAGRAM_MAX_ITEMS);

    if (empty($items)) {
        // API falhou agora — tenta um cache velho antes de cair no estático.
        $stale = instagram_read_cache(true);
        return $stale ?? instagram_static_fallback();
    }

    instagram_write_cache($items);
    return $items;
}
