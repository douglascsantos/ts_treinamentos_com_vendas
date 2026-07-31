<?php
/** Passo 1 do login com Google: gera o "state" anti-CSRF, guarda o order_nsu
 *  pendente (se veio de um checkout) na sessão, e redireciona pro Google. */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/google_oauth.php';
csrf_ensure_session();

if (!google_oauth_configured()) {
    header('Location: /area-do-aluno.php?google_erro=1');
    exit;
}

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;
$_SESSION['google_oauth_order_nsu'] = $_GET['order_nsu'] ?? '';

header('Location: ' . google_oauth_authorize_url($state));
exit;
