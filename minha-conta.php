<?php
/**
 * Stub pós-login/cadastro. A Área do Aluno de verdade (financeiro, histórico
 * escolar, certificados, aulas) ainda não existe — ver ROADMAP.md. Já mostra
 * os pedidos vinculados à conta (cursos e produtos separados, com situação),
 * com link pro contrato assinado e recibo.
 */
require __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/storage.php';
require __DIR__ . '/includes/alunos.php';
require __DIR__ . '/includes/pedidos.php';
require __DIR__ . '/includes/turmas.php';
require __DIR__ . '/includes/produtos.php';
require __DIR__ . '/includes/env.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/certificados.php';
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
    // Sessão órfã (conta não existe mais) — desloga.
    session_destroy();
    header('Location: area-do-aluno.php');
    exit;
}

$pedidos = find_pedidos_by_aluno($aluno['id']);
$cursos = array_values(array_filter($pedidos, fn ($p) => $p['tipo'] === 'curso'));
$produtos = array_values(array_filter($pedidos, fn ($p) => $p['tipo'] === 'produto'));

try {
    $certificados = listar_certificados_por_aluno($aluno['id']);
} catch (Throwable $e) {
    $certificados = [];
}

$statusLabels = PEDIDO_STATUS_LABELS;

/** Situação de um pedido de curso já pago: concluído ou agendado pra quando. */
function situacao_curso_pago(array $pedido): array
{
    $turma = find_turma($pedido['slug']);
    if (!$turma || empty($turma['datas'])) {
        return ['Pago', 'status-open'];
    }
    $ultimaData = end($turma['datas']);
    if (strtotime($ultimaData) < strtotime('today')) {
        return ['Concluído', 'status-open'];
    }
    $quando = 'Agendado para ' . turma_dates_full($turma['datas']);
    if (!empty($turma['horario'])) {
        $quando .= ' às ' . $turma['horario'];
    }
    return [$quando, 'status-last'];
}

$novo = isset($_GET['novo']);
$page_title = 'Minha Conta | ' . $config['site_name'];
$base_path = '';
include __DIR__ . '/includes/header.php';
?>

<section class="section minha-conta-hero">
    <div class="container">
        <?php if ($novo): ?>
            <span class="badge badge-success">✅ Conta criada</span>
        <?php endif; ?>
        <p class="eyebrow">Área do Aluno</p>
        <h1>Olá, <?= e(trim($aluno['pronome'] . ' ' . $aluno['nome'])) ?>!</h1>
    </div>
</section>

<section class="section" style="padding-top:0;">
    <div class="container">
        <div class="section-head" style="max-width:none;">
            <p class="eyebrow">Meus cursos</p>
            <h2>Cursos comprados</h2>
        </div>

        <?php if (!$cursos): ?>
            <p class="muted" style="margin-top:1.5rem;">Você ainda não comprou nenhum curso.</p>
        <?php else: ?>
            <div class="pedidos-list">
                <?php foreach ($cursos as $p):
                    [$statusLabel, $statusClass] = $p['status'] === 'pago'
                        ? situacao_curso_pago($p)
                        : ($statusLabels[$p['status']] ?? ['Desconhecido', 'status-last']);
                ?>
                    <div class="pedido-card">
                        <div class="pedido-card-info">
                            <h3><?= e($p['descricao']) ?></h3>
                            <p class="muted">
                                Pedido <?= e($p['order_nsu']) ?> · <?= e(format_price((float) $p['preco'])) ?> ·
                                <span class="course-status <?= e($statusClass) ?>" style="position:static;display:inline-flex;"><?= e($statusLabel) ?></span>
                            </p>
                        </div>
                        <div class="pedido-card-actions">
                            <a class="btn btn-outline-dark" href="meu-contrato.php?order_nsu=<?= e(rawurlencode($p['order_nsu'])) ?>">Ver contrato</a>
                            <?php if ($p['status'] === 'pago' && $p['receipt_url']): ?>
                                <a class="btn btn-primary" href="<?= e($p['receipt_url']) ?>" target="_blank" rel="noopener">Ver recibo</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section" style="padding-top:0;">
    <div class="container">
        <div class="section-head" style="max-width:none;">
            <p class="eyebrow">Meus produtos</p>
            <h2>Produtos comprados</h2>
        </div>

        <?php if (!$produtos): ?>
            <p class="muted" style="margin-top:1.5rem;">Você ainda não comprou nenhum produto.</p>
        <?php else: ?>
            <div class="pedidos-list">
                <?php foreach ($produtos as $p):
                    [$statusLabel, $statusClass] = $statusLabels[$p['status']] ?? ['Desconhecido', 'status-last'];
                    $produtoRef = $p['status'] === 'pago' ? find_produto($p['slug']) : null;
                    $ehDigital = $produtoRef && produto_e_digital($produtoRef['tipo'] ?? '');
                    $entregue = ($p['status_entrega'] ?? '') === 'entregue';
                ?>
                    <div class="pedido-card">
                        <div class="pedido-card-info">
                            <h3><?= e($p['descricao']) ?></h3>
                            <p class="muted">
                                Pedido <?= e($p['order_nsu']) ?> · <?= e(format_price((float) $p['preco'])) ?> ·
                                <span class="course-status <?= e($statusClass) ?>" style="position:static;display:inline-flex;"><?= e($statusLabel) ?></span>
                            </p>
                        </div>
                        <div class="pedido-card-actions">
                            <a class="btn btn-outline-dark" href="meu-contrato.php?order_nsu=<?= e(rawurlencode($p['order_nsu'])) ?>">Ver contrato</a>
                            <?php if ($p['status'] === 'pago' && $p['receipt_url']): ?>
                                <a class="btn btn-outline-dark" href="<?= e($p['receipt_url']) ?>" target="_blank" rel="noopener">Ver recibo</a>
                            <?php endif; ?>
                            <?php if ($ehDigital && !empty($produtoRef['arquivo_ebook'])): ?>
                                <a class="btn btn-accent" href="ebook-download.php?produto=<?= e(rawurlencode($p['slug'])) ?>">📄 Baixar e-book</a>
                            <?php elseif ($produtoRef && !$ehDigital): ?>
                                <?php if ($entregue): ?>
                                    <span class="btn btn-accent pedido-status-btn" aria-disabled="true">✅ Entregue</span>
                                <?php else: ?>
                                    <a class="btn btn-primary" href="<?= e(wa_link($config['whatsapp'], 'Olá! Quero acompanhar o envio do meu pedido ' . $p['order_nsu'] . '.')) ?>" target="_blank" rel="noopener">📦 Acompanhar envio</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($certificados): ?>
<section class="section" style="padding-top:0;">
    <div class="container">
        <div class="section-head" style="max-width:none;">
            <p class="eyebrow">Meus certificados</p>
            <h2>Certificados de conclusão</h2>
        </div>
        <div class="pedidos-list">
            <?php foreach ($certificados as $c): ?>
                <div class="pedido-card">
                    <div class="pedido-card-info">
                        <h3><?= e($c['curso_nome']) ?></h3>
                        <p class="muted"><?= (int) $c['carga_horaria'] ?>h · concluído em <?= e(date('d/m/Y', strtotime($c['data_emissao']))) ?></p>
                    </div>
                    <div class="pedido-card-actions">
                        <a class="btn btn-primary" href="certificado.php?codigo=<?= e(rawurlencode($c['numero_hash'])) ?>" target="_blank" rel="noopener">Ver certificado</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section" style="padding-top:0;">
    <div class="container">
        <p class="muted minha-conta-em-construcao">
            A Área do Aluno completa (histórico escolar, presenças e aulas online) ainda está em
            construção — em breve tudo isso vai aparecer aqui.
        </p>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
