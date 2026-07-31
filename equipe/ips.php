<?php
/** Bloqueio de IP em nível de aplicação (ver includes/acesso.php) — só diretor, ver equipe/perfil.php. */
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

$mensagens = [];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['bloquear_ip', 'liberar_ip'], true)) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $ip = trim($_POST['ip'] ?? '');
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $erros[] = 'IP inválido.';
        } elseif ($_POST['acao'] === 'bloquear_ip' && $ip === acesso_ip_cliente()) {
            $erros[] = 'Esse é o seu próprio IP agora — bloqueá-lo tiraria seu próprio acesso ao site. Peça pra outra pessoa da equipe bloquear, se for o caso.';
        } elseif ($_POST['acao'] === 'bloquear_ip') {
            bloquear_ip($ip);
            $mensagens[] = 'IP ' . $ip . ' bloqueado.';
        } else {
            liberar_ip($ip);
            $mensagens[] = 'IP ' . $ip . ' liberado.';
        }
    }
    if (!$erros) {
        header('Location: ips.php');
        exit;
    }
}

$bloqueados = ips_bloqueados();
$recentes = acesso_ips_recentes();

equipe_header_html('IPs e bloqueio de acesso', $staff['nome']);
?>

<p style="margin-bottom:1.25rem;"><a href="perfil.php">← Meu perfil</a></p>

<?php foreach ($mensagens as $m): ?>
    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;"><?= e($m) ?></div>
<?php endforeach; ?>
<?php if ($erros): ?>
    <div class="form-errors-box"><ul><?php foreach ($erros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="equipe-card">
    <h3>IPs bloqueados (<?= count($bloqueados) ?>)</h3>
    <p class="muted" style="margin-bottom:1rem;">Bloqueio a nível de aplicação: quem acessa de um IP bloqueado recebe uma resposta "Acesso bloqueado" em qualquer página do site, antes até do login carregar.</p>
    <?php if (!$bloqueados): ?>
        <p class="equipe-empty">Nenhum IP bloqueado no momento.</p>
    <?php else: ?>
        <div class="equipe-list">
            <?php foreach ($bloqueados as $ip): ?>
                <div class="equipe-list-item">
                    <span class="equipe-list-link" style="cursor:default;"><code><?= e($ip) ?></code></span>
                    <form method="post" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="acao" value="liberar_ip" />
                        <input type="hidden" name="ip" value="<?= e($ip) ?>" />
                        <button type="submit" class="btn-enable">Liberar</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="toggle-form" style="border-top:1px dashed var(--border);">
        <h4 style="margin-bottom:1rem;">Bloquear um IP manualmente</h4>
        <form method="post" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
            <?= csrf_field() ?>
            <input type="hidden" name="acao" value="bloquear_ip" />
            <div class="form-field" style="margin:0;flex:1;min-width:200px;"><label>Endereço IP</label><input type="text" name="ip" placeholder="ex: 203.0.113.42" required /></div>
            <button type="submit" class="btn btn-primary">Bloquear</button>
        </form>
    </div>
</div>

<div class="equipe-card">
    <h3>IPs mais ativos recentemente</h3>
    <p class="muted" style="margin-bottom:1rem;">Contagem de acessos nas últimas ~3.000 requisições registradas.</p>
    <?php if (!$recentes): ?>
        <p class="equipe-empty">Nenhum acesso registrado ainda.</p>
    <?php else: ?>
        <div class="equipe-list">
            <?php foreach ($recentes as $ip => $qtd): ?>
                <div class="equipe-list-item">
                    <span class="equipe-list-link" style="cursor:default;"><code><?= e($ip) ?></code><span class="equipe-list-meta"><?= (int) $qtd ?> acesso(s)</span></span>
                    <?php if (in_array($ip, $bloqueados, true)): ?>
                        <span class="badge-inativo">Bloqueado</span>
                    <?php else: ?>
                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="bloquear_ip" />
                            <input type="hidden" name="ip" value="<?= e($ip) ?>" />
                            <button type="submit" class="btn-disable">Bloquear</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php equipe_footer_html(); ?>
