<?php
/** Proteção simples de CSRF baseada em sessão, para os formulários do site
 *  (aceite de contrato no checkout, login, cadastro). */

function csrf_ensure_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/** Gera (ou reaproveita) o token da sessão atual. */
function csrf_token(): string
{
    csrf_ensure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Confere um token recebido de um formulário contra o da sessão. */
function csrf_verify(?string $token): bool
{
    csrf_ensure_session();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/** <input hidden> pronto pra colar dentro de um <form>. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '" />';
}
