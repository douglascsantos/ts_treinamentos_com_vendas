<?php
/**
 * Guia financeira do aluno: boletos parcelados cadastrados pelo administrativo.
 * Baixável enquanto dentro do prazo; vencido sem pagamento bloqueia o
 * download (ícone vermelho/X); pago fica verde/check (ver includes/boletos.php).
 */
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/storage.php';
require __DIR__ . '/includes/alunos.php';
require __DIR__ . '/includes/env.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/boletos.php';
require __DIR__ . '/includes/csrf.php';
csrf_ensure_session();

$config  = require __DIR__ . '/includes/config.php';
$version = require __DIR__ . '/includes/version.php';

if (empty($_SESSION['aluno_id'])) {
    header('Location: area-do-aluno.php');
    exit;
}

$aluno = find_aluno_by_id($_SESSION['aluno_id']);
if (!$aluno) {
    session_destroy();
    header('Location: area-do-aluno.php');
    exit;
}

$boletos = listar_boletos_por_aluno($aluno['id']);

$page_title = 'Financeiro | ' . $config['site_name'];
$base_path = '';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="section-head" style="max-width:none;">
            <p class="eyebrow">Minha conta</p>
            <h2>Financeiro</h2>
        </div>

        <?php if (!$boletos): ?>
            <p class="muted" style="margin-top:1.5rem;">Você ainda não tem nenhum boleto cadastrado.</p>
        <?php else: ?>
            <div class="pedidos-list">
                <?php foreach ($boletos as $b):
                    [$situacaoLabel, $situacaoClasse, $podeBaixar] = boleto_situacao($b);
                    $icone = $b['status'] === 'pago' ? '✅' : ($podeBaixar ? '' : '❌');
                ?>
                    <div class="pedido-card">
                        <div>
                            <h3>Parcela <?= (int) $b['numero_parcela'] ?> <?= $icone ?></h3>
                            <p class="muted">
                                Vencimento <?= e(date('d/m/Y', strtotime($b['vencimento']))) ?> ·
                                <span class="course-status <?= e($situacaoClasse) ?>" style="position:static;display:inline-flex;"><?= e($situacaoLabel) ?></span>
                            </p>
                        </div>
                        <div class="pedido-card-actions">
                            <?php if ($podeBaixar): ?>
                                <a class="btn btn-primary" href="boleto-download.php?id=<?= e(rawurlencode($b['id'])) ?>">Baixar boleto</a>
                            <?php else: ?>
                                <span class="muted" style="font-size:.82rem;">Vencido — fale com a gente pra regularizar</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
