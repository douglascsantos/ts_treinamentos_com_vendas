<?php
/**
 * Stub pós-login/cadastro. A Área do Aluno de verdade (financeiro, histórico
 * escolar, certificados, aulas) ainda não existe — ver ROADMAP.md. Já mostra
 * os pedidos vinculados à conta (cursos e produtos separados, com situação),
 * com link pro contrato assinado e recibo.
 */
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/storage.php';
require __DIR__ . '/includes/alunos.php';
require __DIR__ . '/includes/pedidos.php';
require __DIR__ . '/includes/turmas.php';
require __DIR__ . '/includes/produtos.php';
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

$statusLabels = [
    'pendente' => ['Pagamento pendente', 'status-last'],
    'pago'     => ['Pago', 'status-open'],
    'erro'     => ['Erro no pagamento', 'status-out'],
];

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

/** Situação de um pedido de produto já pago: e-book mostra "Pago", físico mostra status de entrega. */
function situacao_produto_pago(array $pedido): array
{
    $produto = find_produto($pedido['slug']);
    if ($produto && produto_e_digital($produto['tipo'] ?? '')) {
        return ['Pago', 'status-open'];
    }
    return STATUS_ENTREGA_LABELS[$pedido['status_entrega']] ?? ['A caminho', 'status-last'];
}

$novo = isset($_GET['novo']);
$page_title = 'Minha Conta | ' . $config['site_name'];
$base_path = '';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container page-content checkout-status-page">
        <?php if ($novo): ?>
            <span class="badge badge-success">✅ Conta criada</span>
        <?php endif; ?>
        <h1 class="checkout-status-title">Bem-vindo(a), <?= e(trim($aluno['pronome'] . ' ' . $aluno['nome'])) ?>!</h1>
        <p class="lead-muted checkout-status-msg">
            A Área do Aluno completa (histórico escolar, presenças, certificados e aulas online)
            ainda está em construção — em breve tudo isso vai aparecer aqui.
        </p>
        <div class="checkout-actions">
            <a class="btn btn-primary" href="index.php">Voltar ao site</a>
            <a class="btn btn-accent" href="financeiro.php">Financeiro</a>
            <a class="btn btn-accent" href="<?= e(wa_link($config['whatsapp'], 'Olá! Acabei de criar minha conta no site.')) ?>" target="_blank" rel="noopener">Falar no WhatsApp</a>
            <a class="btn btn-outline-dark" href="logout.php">Sair da conta</a>
        </div>
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
                        <div>
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
                    [$statusLabel, $statusClass] = $p['status'] === 'pago'
                        ? situacao_produto_pago($p)
                        : ($statusLabels[$p['status']] ?? ['Desconhecido', 'status-last']);
                ?>
                    <div class="pedido-card">
                        <div>
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
                            <?php if ($p['status'] === 'pago'):
                                $produtoRef = find_produto($p['slug']);
                            ?>
                                <?php if ($produtoRef && produto_e_digital($produtoRef['tipo']) && !empty($produtoRef['arquivo_ebook'])): ?>
                                    <a class="btn btn-accent" href="ebook-download.php?produto=<?= e(rawurlencode($p['slug'])) ?>">Baixar e-book</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
