<?php
/** Últimos acessos ao site (ver includes/acesso.php) — só diretor, ver equipe/perfil.php. */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/administradores.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['administrador']);
if ($staff['nivel'] !== 'diretor') {
    header('Location: administrativo.php');
    exit;
}

$logs = acesso_log_recentes(300);

equipe_header_html('Logs do sistema', $staff['nome']);
?>

<p style="margin-bottom:1.25rem;"><a href="perfil.php">← Meu perfil</a></p>

<div class="equipe-card">
    <h3>Últimos acessos (<?= count($logs) ?> de até 300)</h3>
    <p class="muted" style="margin-bottom:1rem;">Cada linha é uma requisição ao site (página pública, painel interno, checkout...). Guardamos só os últimos ~5.000 acessos.</p>
    <div class="equipe-table-wrap">
    <table class="equipe-table">
        <thead><tr><th>Quando</th><th>IP</th><th>Método</th><th>Caminho</th><th>Navegador</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
            <tr>
                <td><?= e($l['em'] ?? '—') ?></td>
                <td><code><?= e($l['ip'] ?? '—') ?></code></td>
                <td><?= e($l['metodo'] ?? '—') ?></td>
                <td><?= e($l['path'] ?? '—') ?></td>
                <td><?= e(mb_strimwidth($l['ua'] ?? '—', 0, 60, '…')) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="5" class="muted">Nenhum acesso registrado ainda.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php equipe_footer_html(); ?>
