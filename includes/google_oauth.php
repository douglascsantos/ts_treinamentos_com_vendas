<?php
/**
 * Login com Google (OAuth 2.0, Authorization Code) sem SDK — hospedagem
 * compartilhada comum não tem Composer. Fluxo: auth/google-login.php redireciona
 * pro Google, auth/google-callback.php recebe o "code", troca por access_token
 * e busca nome/e-mail no userinfo endpoint. Credenciais em secret.env
 * (GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET — ver includes/env.php).
 */

const GOOGLE_OAUTH_AUTH_ENDPOINT     = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_OAUTH_TOKEN_ENDPOINT    = 'https://oauth2.googleapis.com/token';
const GOOGLE_OAUTH_USERINFO_ENDPOINT = 'https://www.googleapis.com/oauth2/v3/userinfo';

function google_oauth_configured(): bool
{
    return env('GOOGLE_CLIENT_ID') !== null && env('GOOGLE_CLIENT_SECRET') !== null;
}

/**
 * Redirect URI usado no fluxo — precisa bater, caractere por caractere, com
 * um dos "URIs de redirecionamento autorizados" cadastrados no Google Cloud
 * Console. `localhost` usa http (Google aceita) pra dar pra testar antes do
 * deploy; qualquer outro host usa https sempre.
 */
function google_oauth_redirect_uri(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'tstreinamento.com';
    $scheme = str_starts_with($host, 'localhost') ? 'http' : 'https';
    return $scheme . '://' . $host . '/auth/google-callback.php';
}

function google_oauth_authorize_url(string $state): string
{
    $params = [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'redirect_uri'  => google_oauth_redirect_uri(),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => 'select_account',
    ];
    return GOOGLE_OAUTH_AUTH_ENDPOINT . '?' . http_build_query($params);
}

/**
 * Troca o "code" recebido no callback pelo e-mail/nome do usuário. Nunca
 * lança exceção — quem chama decide o que fazer com ['ok' => false].
 */
function google_oauth_exchange_code(string $code): array
{
    $ch = curl_init(GOOGLE_OAUTH_TOKEN_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => $code,
            'client_id'     => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect_uri'  => google_oauth_redirect_uri(),
            'grant_type'    => 'authorization_code',
        ]),
    ]);
    $rawToken = curl_exec($ch);
    $statusToken = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $token = json_decode((string) $rawToken, true);
    if ($statusToken !== 200 || !is_array($token) || empty($token['access_token'])) {
        return ['ok' => false];
    }

    $ch = curl_init(GOOGLE_OAUTH_USERINFO_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token['access_token']],
    ]);
    $rawUser = curl_exec($ch);
    $statusUser = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $user = json_decode((string) $rawUser, true);
    if ($statusUser !== 200 || !is_array($user) || empty($user['email']) || empty($user['email_verified'])) {
        return ['ok' => false];
    }

    return [
        'ok'    => true,
        'email' => mb_strtolower(trim($user['email'])),
        'nome'  => trim((string) ($user['name'] ?? $user['email'])),
        'sub'   => (string) ($user['sub'] ?? ''),
    ];
}
