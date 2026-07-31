<?php
/**
 * Tela do instrutor pra uma turma específica: presença + nota (0-10) +
 * observação (opcional, só administrativo/diretor e o próprio instrutor
 * veem — nunca o aluno) por aluno matriculado. "Finalizar turma" libera pro
 * administrativo poder concluir e gerar os diplomas (ver equipe/turmas.php,
 * ação "concluir_turma"/"confirmar_conclusao"). Continua editável depois de
 * finalizada, só trava depois que a turma já foi concluída (diplomas emitidos).
 */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/instrutores.php';
require __DIR__ . '/../includes/turmas.php';
require_once __DIR__ . '/../includes/storage.php';
require __DIR__ . '/../includes/alunos.php';
require __DIR__ . '/../includes/pedidos.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['instrutor']);

$slug = $_GET['slug'] ?? '';
$turma = find_turma($slug);
if (!$turma || ($turma['instrutor_id'] ?? null) !== $staff['id']) {
    header('Location: instrutor.php');
    exit;
}

$mensagens = [];
$erros = [];
$travada = turma_concluida($turma);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['salvar_avaliacoes', 'finalizar_turma'], true) && !$travada) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $presencas = $_POST['presenca'] ?? [];
        $notas = $_POST['nota'] ?? [];
        $observacoes = $_POST['observacao'] ?? [];

        $avaliacoes = [];
        foreach (pedidos_pagos_por_turma($slug) as $pedido) {
            $alunoId = $pedido['aluno_id'];
            $notaRaw = trim((string) ($notas[$alunoId] ?? ''));
            $avaliacoes[] = [
                'aluno_id'    => $alunoId,
                'presenca'    => isset($presencas[$alunoId]),
                'nota'        => $notaRaw !== '' ? min(10, max(0, (float) str_replace(',', '.', $notaRaw))) : null,
                'observacao'  => trim((string) ($observacoes[$alunoId] ?? '')),
            ];
        }

        $mudancas = ['avaliacoes' => $avaliacoes];
        if (($_POST['acao'] ?? '') === 'finalizar_turma') {
            $mudancas['instrutor_finalizou'] = true;
            $mudancas['instrutor_finalizou_em'] = date('Y-m-d H:i:s');
            $mensagens[] = 'Turma finalizada — já pode ser concluída pelo administrativo.';
        } else {
            $mensagens[] = 'Presença/nota/observação salvas.';
        }
        $turma = atualizar_turma($slug, $mudancas);

        header('Location: minha-turma.php?slug=' . rawurlencode($slug) . '&ok=1' . (($_POST['acao'] ?? '') === 'finalizar_turma' ? '&fin=1' : ''));
        exit;
    }
}

if (isset($_GET['ok'])) {
    $mensagens[] = isset($_GET['fin']) ? 'Turma finalizada — já pode ser concluída pelo administrativo.' : 'Presença/nota/observação salvas.';
}

$avaliacoesPorAluno = [];
foreach ($turma['avaliacoes'] ?? [] as $av) {
    $avaliacoesPorAluno[$av['aluno_id']] = $av;
}

$matriculados = [];
foreach (pedidos_pagos_por_turma($slug) as $pedido) {
    $aluno = find_aluno_by_id($pedido['aluno_id']);
    if ($aluno) {
        $matriculados[] = ['aluno' => $aluno, 'avaliacao' => $avaliacoesPorAluno[$aluno['id']] ?? null];
    }
}

equipe_header_html('Minha turma', $staff['nome']);
?>

<p style="margin-bottom:1.25rem;"><a href="instrutor.php">← Minhas turmas</a></p>

<?php foreach ($mensagens as $m): ?>
    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;"><?= e($m) ?></div>
<?php endforeach; ?>
<?php if ($erros): ?>
    <div class="form-errors-box"><ul><?php foreach ($erros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="equipe-card">
    <h3><?= e($turma['curso']) ?></h3>
    <p class="muted" style="margin-bottom:1rem;">
        <?= e(turma_dates_full($turma['datas'])) ?> às <?= e($turma['horario']) ?> ·
        <?= (int) ($turma['carga_horaria'] ?? 0) ?>h · <?= e($turma['local']) ?>
    </p>
    <?php if ($travada): ?>
        <span class="badge-ativo">Turma concluída — diplomas já emitidos, presença/nota travadas.</span>
    <?php elseif ($turma['instrutor_finalizou'] ?? false): ?>
        <span class="badge-ativo">Você já finalizou — ainda pode ajustar até o administrativo concluir.</span>
    <?php endif; ?>

    <?php if (!$matriculados): ?>
        <p class="equipe-empty" style="margin-top:1rem;">Nenhum aluno matriculado (pedido pago) nessa turma ainda.</p>
    <?php else: ?>
        <form method="post" style="margin-top:1.25rem;">
            <?= csrf_field() ?>
            <div class="equipe-list">
                <?php foreach ($matriculados as $m): $aluno = $m['aluno']; $av = $m['avaliacao']; ?>
                    <div class="equipe-list-item" style="align-items:stretch;">
                        <div style="flex:1;min-width:220px;">
                            <strong><?= e(trim(($aluno['pronome'] ?? '') . ' ' . $aluno['nome'])) ?></strong>
                            <div class="equipe-form-row" style="margin-top:.6rem;">
                                <label style="display:flex;align-items:center;gap:.5rem;font-weight:600;font-size:.88rem;padding:.6rem 0;">
                                    <input type="checkbox" name="presenca[<?= e($aluno['id']) ?>]" value="1" <?= ($av['presenca'] ?? false) ? 'checked' : '' ?> <?= $travada ? 'disabled' : '' ?> style="width:18px;height:18px;" />
                                    Presente
                                </label>
                                <div class="form-field" style="margin:0;">
                                    <label>Nota (0-10)</label>
                                    <input type="number" name="nota[<?= e($aluno['id']) ?>]" min="0" max="10" step="0.1" value="<?= e($av && $av['nota'] !== null ? (string) $av['nota'] : '') ?>" <?= $travada ? 'disabled' : '' ?> />
                                </div>
                            </div>
                            <div class="form-field" style="margin-top:.6rem;">
                                <label>Observação (opcional — só administrativo/diretor e você veem)</label>
                                <input type="text" name="observacao[<?= e($aluno['id']) ?>]" value="<?= e($av['observacao'] ?? '') ?>" <?= $travada ? 'disabled' : '' ?> />
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!$travada): ?>
                <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1.25rem;">
                    <button type="submit" name="acao" value="salvar_avaliacoes" class="btn btn-outline-dark">Salvar</button>
                    <button type="submit" name="acao" value="finalizar_turma" class="btn btn-primary">Finalizar turma</button>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<?php equipe_footer_html(); ?>
