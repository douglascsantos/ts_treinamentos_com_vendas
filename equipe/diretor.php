<?php
/**
 * Painel do diretor: dashboard com links pra cada área (instrutores,
 * administradores, alunos, turmas, cupons, boletos, gastos) + upload da
 * própria assinatura (usada no certificado — ver includes/assinaturas.php).
 * Cadastro/edição de cada tipo de conta mora na página dedicada de cada um.
 */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/administradores.php';
require __DIR__ . '/../includes/assinaturas.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['administrador']);
if ($staff['nivel'] !== 'diretor') {
    header('Location: administrativo.php');
    exit;
}

$eu = find_administrador_by_id($staff['id']);
$mensagens = [];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'assinatura') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $nomeArquivo = salvar_assinatura($eu['id'], $_FILES['assinatura'] ?? []);
        if ($nomeArquivo) {
            $eu = atualizar_administrador($eu['id'], ['assinatura_imagem' => $nomeArquivo]);
            $mensagens[] = 'Assinatura atualizada.';
        } else {
            $erros[] = 'Não foi possível salvar a imagem da assinatura.';
        }
    }
}

equipe_header_html('Painel do Diretor', $staff['nome'] . ' (diretor)');
?>

<?php foreach ($mensagens as $m): ?>
    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;"><?= e($m) ?></div>
<?php endforeach; ?>
<?php if ($erros): ?>
    <div class="form-errors-box"><ul><?php foreach ($erros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="equipe-dash-grid">
    <a class="equipe-dash-card" href="instrutores.php">
        <h4>Instrutores</h4>
        <p>Cadastrar, editar, desabilitar/reativar.</p>
    </a>
    <a class="equipe-dash-card" href="administradores.php">
        <h4>Administrativo/Diretor</h4>
        <p>Cadastrar, editar, desabilitar/reativar.</p>
    </a>
    <a class="equipe-dash-card" href="alunos.php">
        <h4>Alunos</h4>
        <p>Cadastro manual, desabilitar/reativar.</p>
    </a>
    <a class="equipe-dash-card" href="turmas.php">
        <h4>Turmas</h4>
        <p>Criar curso, vagas, instrutor, desabilitar/reativar.</p>
    </a>
    <a class="equipe-dash-card" href="cupons.php">
        <h4>Cupons de desconto</h4>
        <p>Gerar link de desconto pra um curso ou produto.</p>
    </a>
    <a class="equipe-dash-card" href="boletos.php">
        <h4>Boletos</h4>
        <p>Cadastrar parcela por aluno.</p>
    </a>
    <a class="equipe-dash-card" href="gastos.php">
        <h4>Gastos</h4>
        <p>Lançar e acompanhar gastos por categoria.</p>
    </a>
</div>

<div class="equipe-card" style="margin-top:1.5rem;">
    <h3>Minha assinatura (usada nos certificados)</h3>
    <?php if (!empty($eu['assinatura_imagem'])): ?>
        <p class="muted">Assinatura cadastrada: <?= e($eu['assinatura_imagem']) ?></p>
    <?php else: ?>
        <p class="muted">Nenhuma assinatura cadastrada ainda — sem ela, o certificado imprime seu nome em texto.</p>
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

<?php equipe_footer_html(); ?>
