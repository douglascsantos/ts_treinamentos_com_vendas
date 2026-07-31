<?php
/**
 * Painel do instrutor: dados do perfil + upload da assinatura (usada nos
 * certificados que ele liberar — ver ROADMAP.md). Atribuição de turma e
 * liberação de certificado ainda não construídos.
 */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/instrutores.php';
require __DIR__ . '/../includes/assinaturas.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['instrutor']);
$eu = find_instrutor_by_id($staff['id']);

$mensagens = [];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'assinatura') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $nomeArquivo = salvar_assinatura($eu['id'], $_FILES['assinatura'] ?? []);
        if ($nomeArquivo) {
            $eu = atualizar_instrutor($eu['id'], ['assinatura_imagem' => $nomeArquivo]);
            $mensagens[] = 'Assinatura atualizada.';
        } else {
            $erros[] = 'Não foi possível salvar a imagem da assinatura.';
        }
    }
}

equipe_header_html('Painel do Instrutor', $staff['nome']);
?>

<?php foreach ($mensagens as $m): ?>
    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;"><?= e($m) ?></div>
<?php endforeach; ?>
<?php if ($erros): ?>
    <div class="form-errors-box"><ul><?php foreach ($erros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="equipe-card">
    <h3>Meus dados</h3>
    <div class="equipe-table-wrap">
    <table class="equipe-table">
        <tr><th>Nome</th><td><?= e($eu['nome_completo']) ?></td></tr>
        <tr><th>Formação</th><td><?= e($eu['formacao']) ?></td></tr>
        <tr><th>Área</th><td><?= e(AREAS_ATUACAO_INSTRUTOR[$eu['area_atuacao']] ?? $eu['area_atuacao']) ?></td></tr>
        <tr><th>Registro profissional</th><td><?= e($eu['numero_registro'] ?? '—') ?></td></tr>
        <tr><th>E-mail</th><td><?= e($eu['email']) ?></td></tr>
        <tr><th>WhatsApp</th><td><?= e($eu['whatsapp']) ?></td></tr>
    </table>
    </div>
</div>

<div class="equipe-card">
    <h3>Minha assinatura (usada nos certificados que eu liberar)</h3>
    <?php if (!empty($eu['assinatura_imagem'])): ?>
        <p class="muted">Assinatura cadastrada: <?= e($eu['assinatura_imagem']) ?></p>
    <?php else: ?>
        <p class="muted">Nenhuma assinatura cadastrada ainda — sem ela, o certificado imprime seu nome, profissão e registro em texto.</p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="assinatura" />
        <div class="form-field">
            <label for="assinatura">Imagem da assinatura (upload ou foto do celular)</label>
            <input type="file" id="assinatura" name="assinatura" accept="image/*" required />
        </div>
        <button type="submit" class="btn btn-primary">Salvar assinatura</button>
    </form>
</div>

<div class="equipe-card">
    <h3>Minhas turmas</h3>
    <p class="muted">Em construção — em breve aqui aparecem as turmas atribuídas a você, com a lista de alunos matriculados e o botão pra liberar certificado.</p>
</div>

<?php equipe_footer_html(); ?>
