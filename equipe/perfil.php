<?php
/**
 * Perfil pessoal — qualquer pessoa da equipe (administrador ou instrutor)
 * edita os próprios dados de contato, troca a senha e inclui/remove campos
 * opcionais. As ferramentas de sistema (backup, logs, IPs) só aparecem pro
 * diretor, no final da tela (ver equipe/backup-banco.php, backup-site.php,
 * logs.php, ips.php).
 */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/administradores.php';
require __DIR__ . '/../includes/instrutores.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['administrador', 'instrutor']);
$ehAdministrador = $staff['tipo'] === 'administrador';
$eu = $ehAdministrador ? find_administrador_by_id($staff['id']) : find_instrutor_by_id($staff['id']);

$mensagens = [];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_dados') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } elseif ($ehAdministrador) {
        $nome = trim($_POST['nome'] ?? '');
        if ($nome === '') {
            $erros[] = 'Informe o nome.';
        } else {
            $eu = atualizar_administrador($staff['id'], ['nome' => $nome]);
            $_SESSION['staff_nome'] = $eu['nome'];
            $mensagens[] = 'Dados atualizados.';
        }
    } else {
        $whatsapp = $_POST['whatsapp'] ?? '';
        $numeroRegistro = trim($_POST['numero_registro'] ?? '');
        if (strlen(preg_replace('/\D/', '', $whatsapp)) < 10) {
            $erros[] = 'WhatsApp inválido.';
        } else {
            $eu = atualizar_instrutor($staff['id'], [
                'whatsapp'        => preg_replace('/\D/', '', $whatsapp),
                'numero_registro' => $numeroRegistro !== '' ? $numeroRegistro : null,
            ]);
            $mensagens[] = 'Dados atualizados.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'trocar_senha') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $senhaAtual = (string) ($_POST['senha_atual'] ?? '');
        $senhaNova = (string) ($_POST['senha_nova'] ?? '');
        if (!password_verify($senhaAtual, $eu['senha_hash'] ?? '')) {
            $erros[] = 'Senha atual incorreta.';
        } elseif (strlen($senhaNova) < 8) {
            $erros[] = 'A nova senha precisa ter pelo menos 8 caracteres.';
        } else {
            $novoHash = password_hash($senhaNova, PASSWORD_DEFAULT);
            $eu = $ehAdministrador
                ? atualizar_administrador($staff['id'], ['senha_hash' => $novoHash])
                : atualizar_instrutor($staff['id'], ['senha_hash' => $novoHash]);
            $mensagens[] = 'Senha alterada.';
        }
    }
}

if (($_GET['erro'] ?? '') === 'backup_banco') {
    $erros[] = 'Não foi possível gerar o backup do banco agora — tente novamente em instantes.';
} elseif (($_GET['erro'] ?? '') === 'backup_site') {
    $erros[] = 'Não foi possível gerar o backup do site agora — tente novamente em instantes.';
}

equipe_header_html('Meu perfil', $staff['nome']);
?>

<?php foreach ($mensagens as $m): ?>
    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;"><?= e($m) ?></div>
<?php endforeach; ?>
<?php if ($erros): ?>
    <div class="form-errors-box"><ul><?php foreach ($erros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="equipe-card">
    <h3>Meus dados</h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="salvar_dados" />
        <?php if ($ehAdministrador): ?>
            <div class="equipe-form-row">
                <div class="form-field"><label>Nome</label><input type="text" name="nome" value="<?= e($eu['nome']) ?>" required /></div>
                <div class="form-field"><label>E-mail (não editável)</label><input type="email" value="<?= e($eu['email']) ?>" disabled /></div>
            </div>
            <p class="muted" style="margin:.25rem 0 1rem;">Nível: <strong><?= e(ucfirst($eu['nivel'])) ?></strong> · e-mail e nível só o diretor altera (ver <a href="administradores.php">Administradores</a>).</p>
        <?php else: ?>
            <div class="equipe-form-row">
                <div class="form-field"><label>Nome (não editável)</label><input type="text" value="<?= e($eu['nome_completo']) ?>" disabled /></div>
                <div class="form-field"><label>E-mail (não editável)</label><input type="email" value="<?= e($eu['email']) ?>" disabled /></div>
            </div>
            <div class="equipe-form-row">
                <div class="form-field"><label>WhatsApp</label><input type="tel" name="whatsapp" data-mask="phone" value="<?= e($eu['whatsapp']) ?>" required /></div>
                <div class="form-field">
                    <label>Registro profissional (opcional)</label>
                    <input type="text" name="numero_registro" value="<?= e($eu['numero_registro'] ?? '') ?>" placeholder="ex: COREN 123456" />
                </div>
            </div>
            <p class="muted" style="margin:.25rem 0 1rem;">Deixe o registro profissional em branco e salve pra remover essa informação opcional.</p>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Salvar</button>
    </form>
</div>

<div class="equipe-card">
    <h3>Trocar senha</h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="trocar_senha" />
        <div class="equipe-form-row">
            <div class="form-field"><label>Senha atual</label><input type="password" name="senha_atual" required /></div>
            <div class="form-field"><label>Nova senha</label><input type="password" name="senha_nova" minlength="8" required /></div>
        </div>
        <button type="submit" class="btn btn-primary">Trocar senha</button>
    </form>
</div>

<?php if ($ehAdministrador && $staff['nivel'] === 'diretor'): ?>
<div class="equipe-card">
    <h3>Ferramentas do sistema</h3>
    <p class="muted" style="margin-bottom:1.25rem;">Disponível só pro diretor.</p>
    <div class="equipe-dash-grid">
        <form method="post" action="backup-banco.php">
            <?= csrf_field() ?>
            <button type="submit" class="equipe-dash-card" style="width:100%;text-align:left;border:1px solid var(--border);cursor:pointer;font:inherit;">
                <span class="equipe-dash-card-icon" aria-hidden="true">🗄️</span>
                <div><h4>Backup do banco</h4><p>Baixa um .zip com todas as tabelas em SQL, com a data de hoje.</p></div>
            </button>
        </form>
        <form method="post" action="backup-site.php">
            <?= csrf_field() ?>
            <button type="submit" class="equipe-dash-card" style="width:100%;text-align:left;border:1px solid var(--border);cursor:pointer;font:inherit;">
                <span class="equipe-dash-card-icon" aria-hidden="true">💾</span>
                <div><h4>Backup do site</h4><p>Baixa um .zip com todo o código/conteúdo do site, com a data de hoje.</p></div>
            </button>
        </form>
        <a class="equipe-dash-card" href="logs.php">
            <span class="equipe-dash-card-icon" aria-hidden="true">📜</span>
            <div><h4>Logs do sistema</h4><p>Últimos acessos ao site: quando, de qual IP e qual página.</p></div>
        </a>
        <a class="equipe-dash-card" href="ips.php">
            <span class="equipe-dash-card-icon" aria-hidden="true">🚫</span>
            <div><h4>IPs e bloqueio</h4><p>Ver quem está acessando, bloquear ou liberar um IP.</p></div>
        </a>
    </div>
</div>
<?php endif; ?>

<?php equipe_footer_html(); ?>
