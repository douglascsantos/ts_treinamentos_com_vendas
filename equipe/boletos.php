<?php
/**
 * Cadastro de boleto: busca o aluno pelo e-mail, escolhe o pedido dele e
 * preenche parcela/vencimento antes de fazer o upload do PDF (ver
 * includes/boletos.php). 1 arquivo por mês de parcelamento.
 */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/administradores.php';
require_once __DIR__ . '/../includes/storage.php';
require __DIR__ . '/../includes/alunos.php';
require __DIR__ . '/../includes/pedidos.php';
require __DIR__ . '/../includes/boletos.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['administrador']);

$mensagens = [];
$erros = [];
$alunoBuscado = null;
$pedidosDoAluno = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'buscar_aluno') {
    $email = trim($_POST['email'] ?? '');
    $alunoBuscado = find_aluno_by_email($email);
    if (!$alunoBuscado) {
        $erros[] = 'Nenhum aluno encontrado com esse e-mail.';
    } else {
        $pedidosDoAluno = find_pedidos_by_aluno($alunoBuscado['id']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'criar_boleto') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $alunoId = $_POST['aluno_id'] ?? '';
        $pedidoId = $_POST['pedido_id'] ?? '';
        $numeroParcela = (int) ($_POST['numero_parcela'] ?? 0);
        $vencimento = $_POST['vencimento'] ?? '';
        $pedido = find_pedido_by_nsu($pedidoId);

        if (!$pedido || ($pedido['aluno_id'] ?? null) !== $alunoId) {
            $erros[] = 'Pedido inválido pra esse aluno.';
        } elseif ($numeroParcela <= 0) {
            $erros[] = 'Informe o número da parcela.';
        } elseif (!DateTime::createFromFormat('Y-m-d', $vencimento)) {
            $erros[] = 'Informe uma data de vencimento válida.';
        } else {
            $nomeArquivo = salvar_boleto_pdf($alunoId . '_' . $pedidoId . '_' . $numeroParcela, $_FILES['arquivo'] ?? []);
            if (!$nomeArquivo) {
                $erros[] = 'Envie um arquivo PDF válido (até 10MB).';
            } else {
                criar_boleto([
                    'aluno_id'       => $alunoId,
                    'pedido_id'      => $pedidoId,
                    'numero_parcela' => $numeroParcela,
                    'vencimento'     => $vencimento,
                    'arquivo_pdf'    => $nomeArquivo,
                    'criado_por'     => $staff['id'],
                ]);
                $mensagens[] = 'Boleto da parcela ' . $numeroParcela . ' cadastrado.';
                $alunoBuscado = find_aluno_by_id($alunoId);
                $pedidosDoAluno = find_pedidos_by_aluno($alunoId);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'marcar_pago') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        marcar_boleto_pago($_POST['boleto_id'] ?? '');
        $mensagens[] = 'Boleto marcado como pago.';
    }
}

$boletos = listar_boletos();

equipe_header_html('Boletos', $staff['nome']);
?>

<?php foreach ($mensagens as $m): ?>
    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;"><?= e($m) ?></div>
<?php endforeach; ?>
<?php if ($erros): ?>
    <div class="form-errors-box"><ul><?php foreach ($erros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="equipe-card">
    <h3>Buscar aluno</h3>
    <form method="post" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="buscar_aluno" />
        <div class="form-field" style="margin:0;flex:1;min-width:220px;"><label>E-mail do aluno</label><input type="email" name="email" value="<?= e($alunoBuscado['email'] ?? '') ?>" required /></div>
        <button type="submit" class="btn btn-primary">Buscar</button>
    </form>

    <?php if ($alunoBuscado): ?>
        <div style="margin-top:1.25rem;">
            <p><strong><?= e($alunoBuscado['nome']) ?></strong> — <?= e($alunoBuscado['email']) ?></p>
            <?php if (!$pedidosDoAluno): ?>
                <p class="muted">Esse aluno não tem nenhum pedido ainda.</p>
            <?php endif; ?>
            <?php foreach ($pedidosDoAluno as $p): ?>
                <div style="border-top:1px solid var(--border);padding:.85rem 0;">
                    <p><strong><?= e($p['descricao']) ?></strong> · <?= e($p['order_nsu']) ?> · <?= e(format_price((float) $p['preco'])) ?></p>
                    <form method="post" enctype="multipart/form-data" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="acao" value="criar_boleto" />
                        <input type="hidden" name="aluno_id" value="<?= e($alunoBuscado['id']) ?>" />
                        <input type="hidden" name="pedido_id" value="<?= e($p['order_nsu']) ?>" />
                        <div class="form-field" style="margin:0;"><label>Parcela nº</label><input type="number" name="numero_parcela" min="1" style="width:80px;" required /></div>
                        <div class="form-field" style="margin:0;"><label>Vencimento</label><input type="date" name="vencimento" required /></div>
                        <div class="form-field" style="margin:0;"><label>PDF do boleto</label><input type="file" name="arquivo" accept="application/pdf" required /></div>
                        <button type="submit" class="btn btn-primary" style="padding:.55rem .9rem;font-size:.82rem;">Cadastrar boleto</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="equipe-card">
    <h3>Todos os boletos (<?= count($boletos) ?>)</h3>
    <div class="equipe-table-wrap">
    <table class="equipe-table">
        <thead><tr><th>Aluno</th><th>Pedido</th><th>Parcela</th><th>Vencimento</th><th>Situação</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($boletos as $b):
            $aluno = find_aluno_by_id($b['aluno_id']);
            [$situacaoLabel, $situacaoClasse] = boleto_situacao($b);
        ?>
            <tr>
                <td><?= e($aluno['nome'] ?? $b['aluno_id']) ?></td>
                <td><?= e($b['pedido_id']) ?></td>
                <td><?= (int) $b['numero_parcela'] ?></td>
                <td><?= e(date('d/m/Y', strtotime($b['vencimento']))) ?></td>
                <td><span class="course-status <?= e($situacaoClasse) ?>" style="position:static;display:inline-flex;"><?= e($situacaoLabel) ?></span></td>
                <td>
                    <?php if ($b['status'] !== 'pago'): ?>
                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="marcar_pago" />
                            <input type="hidden" name="boleto_id" value="<?= e($b['id']) ?>" />
                            <button type="submit" class="btn btn-outline-dark" style="padding:.35rem .7rem;font-size:.76rem;">Marcar pago</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$boletos): ?><tr><td colspan="6" class="muted">Nenhum boleto cadastrado ainda.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php equipe_footer_html(); ?>
