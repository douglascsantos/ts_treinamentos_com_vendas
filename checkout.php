<?php
/**
 * Ponto de entrada do checkout: recebe um POST (tipo, slug, aceite_contrato,
 * csrf_token) vindo do formulário em cursos/{slug}.php ou produtos/{slug}.php.
 *
 * Só aceita POST de propósito — o aceite do contrato precisa ser confirmado
 * pelo servidor, não só pelo checkbox no navegador (que dá pra pular editando
 * o HTML). GET/link direto não gera pagamento nenhum.
 *
 * Exige aluno logado ANTES de pagar (pedido do dono do site, pra poder mandar
 * e-mail de confirmação de compra pro endereço certo assim que o pagamento é
 * aprovado). Se não estiver logado, guarda a intenção de compra JÁ VALIDADA
 * (aceite do contrato registrado agora, não depois) na sessão e manda pra
 * área do aluno — assim que a pessoa entrar ou criar a conta, a compra
 * continua sozinha direto pro pagamento, sem precisar clicar de novo (ver
 * includes/checkout_helper.php::checkout_retomar_se_pendente()).
 */
require __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/storage.php';
require __DIR__ . '/includes/checkout_helper.php';
require __DIR__ . '/includes/env.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/csrf.php';
csrf_ensure_session();

$config = require __DIR__ . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    checkout_erro($config, 'Acesso inválido', 'Essa página só pode ser acessada a partir do botão de pagamento de um curso ou produto.');
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    checkout_erro($config, 'Sessão expirada', 'Sua sessão expirou. Volte à página do curso/produto e tente novamente.');
    exit;
}

if (($_POST['aceite_contrato'] ?? '') !== '1') {
    checkout_erro($config, 'Contrato não aceito', 'Você precisa marcar o aceite do contrato para continuar com o pagamento.');
    exit;
}

$intent = [
    'tipo'               => $_POST['tipo'] ?? '',
    'slug'               => $_POST['slug'] ?? '',
    'cupom_codigo'       => $_POST['cupom'] ?? '',
    'aceite_contrato_em' => date('Y-m-d H:i:s'),
    'aceite_contrato_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
];

if (empty($_SESSION['aluno_id'])) {
    $_SESSION['checkout_pendente'] = $intent;
    header('Location: area-do-aluno.php?retomar_compra=1');
    exit;
}

processar_checkout($config, $_SESSION['aluno_id'], $intent);
