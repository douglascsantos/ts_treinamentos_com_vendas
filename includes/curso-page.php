<?php
/**
 * Renderiza a página de venda de um curso específico (cursos/{slug}.php).
 * Reaproveita header/footer do site (mesma marca, menu, WhatsApp flutuante),
 * mas é uma página separada da landing page, como pedido.
 */
function render_curso_page(string $slug): void
{
    $config  = require __DIR__ . '/config.php';
    $version = require __DIR__ . '/version.php';
    $turma   = find_turma($slug);

    if (!$turma) {
        http_response_code(404);
        $page_title = 'Curso não encontrado | ' . $config['site_name'];
        $base_path = '../';
        include __DIR__ . '/header.php';
        echo '<section class="section"><div class="container page-content">';
        echo '<h1>Curso não encontrado</h1><p>Esse curso pode não estar mais disponível. <a href="../index.php#agenda">Veja a agenda atual de turmas</a>.</p>';
        echo '</div></section>';
        include __DIR__ . '/footer.php';
        return;
    }

    [$statusLabel, $statusClass] = turma_status($turma['status']);
    $esgotada = $turma['status'] === 'esgotada';
    $precoFormatado = format_price((float) $turma['preco']);
    $datasFormatadas = turma_dates_full($turma['datas']);
    $waMsg = 'Olá! Quero garantir minha vaga no curso "' . $turma['curso'] . '" (' . $datasFormatadas . ').';
    $waLink = wa_link($config['whatsapp'], $waMsg);

    $page_title = $turma['curso'] . ' | ' . $config['site_name'];
    $page_description = mb_strimwidth($turma['descricao'] ?? $turma['curso'], 0, 155, '…');
    $base_path = '../';

    include __DIR__ . '/header.php';
    ?>

    <section class="section curso-hero">
        <div class="container curso-hero-grid">
            <div class="card-frame curso-hero-img">
                <img src="../assets/images/cursos/<?= e($turma['imagem']) ?>" alt="<?= e($turma['curso']) ?>" />
                <span class="course-status <?= e($statusClass) ?> curso-hero-status"><?= e($statusLabel) ?></span>
            </div>
            <div>
                <p class="eyebrow">Curso</p>
                <h1><?= e($turma['curso']) ?></h1>
                <p class="lead-muted"><?= e($turma['descricao'] ?? '') ?></p>

                <dl class="curso-meta">
                    <div><dt>📅 Data<?= count($turma['datas']) > 1 ? 's' : '' ?></dt><dd><?= e($datasFormatadas) ?></dd></div>
                    <div><dt>⏱ Horário</dt><dd><?= e($turma['horario']) ?></dd></div>
                    <div><dt>📍 Local</dt><dd><?= e($turma['local']) ?></dd></div>
                    <div><dt>💰 Investimento</dt><dd class="curso-price-big"><?= e($precoFormatado) ?></dd></div>
                </dl>

                <?php if ($esgotada): ?>
                    <a class="btn btn-muted btn-lg btn-block" href="<?= e($waLink) ?>" target="_blank" rel="noopener">Entrar na lista de espera</a>
                <?php else: ?>
                    <a class="btn btn-accent btn-lg btn-block" href="<?= e($waLink) ?>" target="_blank" rel="noopener">Garantir vaga pelo WhatsApp</a>
                <?php endif; ?>
                <p class="curso-note">Vagas limitadas — turmas reduzidas para garantir a qualidade da prática.</p>
                <p class="curso-back"><a href="../index.php#agenda">← Ver todas as turmas da agenda</a></p>
            </div>
        </div>
    </section>

    <?php
    include __DIR__ . '/footer.php';
}
