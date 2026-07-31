<?php
/** Encerra a sessão do aluno. */
require __DIR__ . '/includes/csrf.php';
csrf_ensure_session();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: area-do-aluno.php');
exit;
