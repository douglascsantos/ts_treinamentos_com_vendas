<?php
/**
 * Passo 2 do login com Google: confere o "state", troca o "code" pelo
 * e-mail/nome, encontra a conta existente (e loga) ou, se é conta nova, manda
 * completar o cadastro (pronome/nome/whatsapp/cpf/nascimento/área — obrigatórios
 * — e endereço/foto — opcionais) antes de criar de fato — ver
 * auth/google-completar-cadastro.php e criar_aluno_google() em includes/alunos.php.
 */
require __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/storage.php';
require __DIR__ . '/../includes/alunos.php';
require __DIR__ . '/../includes/checkout_helper.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/google_oauth.php';
csrf_ensure_session();

$config = require __DIR__ . '/../includes/config.php';

$expectedState = $_SESSION['google_oauth_state'] ?? null;
$orderNsu = $_SESSION['google_oauth_order_nsu'] ?? '';
unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_order_nsu']);

$state = $_GET['state'] ?? '';
$code = $_GET['code'] ?? '';

if (!google_oauth_configured() || !$expectedState || !hash_equals($expectedState, $state) || $code === '') {
    header('Location: /area-do-aluno.php?google_erro=1');
    exit;
}

$resultado = google_oauth_exchange_code($code);
if (!$resultado['ok']) {
    header('Location: /area-do-aluno.php?google_erro=1');
    exit;
}

$aluno = find_aluno_by_email($resultado['email']);

if (!$aluno) {
    // Conta nova: CPF é obrigatório pra todo aluno (nunca fica nulo) e o
    // Google não fornece isso — guarda os dados do Google na sessão e manda
    // completar o CPF antes de criar a conta de verdade.
    $_SESSION['google_pending'] = [
        'email' => $resultado['email'],
        'nome'  => $resultado['nome'],
        'sub'   => $resultado['sub'],
    ];
    $_SESSION['google_pending_order_nsu'] = $orderNsu;
    header('Location: /auth/google-completar-cadastro.php');
    exit;
}

if (($aluno['ativo'] ?? true) === false) {
    header('Location: /area-do-aluno.php?google_erro=1');
    exit;
}

if (empty($aluno['google_sub'])) {
    // Conta já existia (cadastro normal com esse e-mail) — vincula o Google pra
    // próxima vez, sem mexer em mais nada da conta.
    $aluno = atualizar_aluno($aluno['id'], ['google_sub' => $resultado['sub']]) ?? $aluno;
}

session_regenerate_id(true);
$_SESSION['aluno_id'] = $aluno['id'];

if ($orderNsu !== '') {
    vincular_pedido_aluno($orderNsu, $aluno['id']);
}

checkout_retomar_se_pendente($config);
header('Location: /minha-conta.php');
exit;
