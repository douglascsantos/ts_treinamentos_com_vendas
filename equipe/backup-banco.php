<?php
/** Download do backup do banco (.zip com um .sql dentro) — só diretor, ver equipe/perfil.php. */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/administradores.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/backups.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['administrador']);
if ($staff['nivel'] !== 'diretor') {
    header('Location: administrativo.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
    header('Location: perfil.php');
    exit;
}

try {
    $caminho = gerar_backup_banco_zip();
    enviar_zip_e_apagar($caminho, 'backup-banco-' . date('Y-m-d') . '.zip');
} catch (Throwable $e) {
    header('Location: perfil.php?erro=backup_banco');
    exit;
}
