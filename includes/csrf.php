<?php
/** Proteção simples de CSRF baseada em sessão, para os formulários do site
 *  (aceite de contrato no checkout, login, cadastro). */

/**
 * Duração da sessão (cookie + tempo que o PHP mantém o arquivo de sessão no
 * servidor antes de poder apagar por inatividade). Sem isso, hospedagem
 * compartilhada costuma usar o padrão do PHP (`session.gc_maxlifetime`,
 * geralmente ~24min) — curto demais pro cadastro de aluno (vários campos +
 * foto) ou pra equipe deixar o painel aberto trabalhando. Isso é a causa mais
 * comum de "erro de sessão"/"sessão expirada" aparecendo do nada no meio de
 * um formulário longo ou depois de um tempo parado numa tela.
 */
const SESSAO_VIDA_SEGUNDOS = 2 * 60 * 60; // 2h — pedido explícito do cliente; depois disso a pessoa precisa logar de novo.

function csrf_ensure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Só dá pra configurar isso ANTES do session_start().
    ini_set('session.gc_maxlifetime', (string) SESSAO_VIDA_SEGUNDOS);
    session_set_cookie_params([
        'lifetime' => SESSAO_VIDA_SEGUNDOS,
        'path'     => '/',
        'domain'   => '',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
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
