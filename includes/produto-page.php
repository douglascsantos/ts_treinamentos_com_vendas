<?php
/**
 * Renderiza a página de venda de um produto específico (produtos/{slug}.php).
 * Reaproveita header/footer do site, mas é uma página separada da landing page.
 */
function render_produto_page(string $slug): void
{
    $config  = require __DIR__ . '/config.php';
    $version = require __DIR__ . '/version.php';
    $produto = find_produto($slug);

    if (!$produto) {
        http_response_code(404);
        $page_title = 'Produto não encontrado | ' . $config['site_name'];
        $base_path = '../';
        include __DIR__ . '/header.php';
        echo '<section class="section"><div class="container page-content">';
        echo '<h1>Produto não encontrado</h1><p>Esse produto pode não estar mais disponível. <a href="../index.php#produtos">Veja os produtos disponíveis</a>.</p>';
        echo '</div></section>';
        include __DIR__ . '/footer.php';
        return;
    }

    $digital = produto_e_digital($produto['tipo']);
    [$statusLabel, $statusClass] = produto_status((int) $produto['estoque'], $produto['tipo']);
    $esgotado = !$digital && (int) $produto['estoque'] <= 0;
    $precoFormatado = format_price((float) $produto['preco']);
    $waMsg = 'Olá! Quero comprar o produto "' . $produto['nome'] . '".';
    $waLink = wa_link($config['whatsapp'], $waMsg);

    $page_title = $produto['nome'] . ' | ' . $config['site_name'];
    $page_description = mb_strimwidth($produto['descricao'] ?? $produto['nome'], 0, 155, '…');
    $base_path = '../';

    include __DIR__ . '/header.php';
    ?>

    <section class="section curso-hero">
        <div class="container curso-hero-grid">
            <div class="card-frame curso-hero-img">
                <img src="../assets/images/produtos/<?= e($produto['imagem']) ?>" alt="<?= e($produto['nome']) ?>" />
                <span class="course-status <?= e($statusClass) ?> curso-hero-status"><?= e($statusLabel) ?></span>
            </div>
            <div>
                <p class="eyebrow"><?= e(produto_tipo_label($produto['tipo'])) ?></p>
                <h1><?= e($produto['nome']) ?></h1>
                <p class="lead-muted"><?= e($produto['descricao'] ?? '') ?></p>

                <dl class="curso-meta">
                    <div><dt>💰 Preço</dt><dd class="curso-price-big"><?= e($precoFormatado) ?></dd></div>
                    <?php if ($digital): ?>
                        <div><dt>📄 Formato</dt><dd>E-book em PDF</dd></div>
                    <?php else: ?>
                        <div><dt>📦 Estoque</dt><dd><?= (int) $produto['estoque'] ?> unidade(s)</dd></div>
                    <?php endif; ?>
                </dl>

                <?php if ($esgotado): ?>
                    <a class="btn btn-muted btn-lg btn-block" href="<?= e($waLink) ?>" target="_blank" rel="noopener">Avisar quando chegar</a>
                <?php else: ?>
                    <a class="btn btn-accent btn-lg btn-block" href="<?= e($waLink) ?>" target="_blank" rel="noopener">Comprar pelo WhatsApp</a>
                <?php endif; ?>
                <?php if ($digital): ?>
                    <p class="curso-note">Produto digital: você recebe o PDF por e-mail/WhatsApp assim que o pagamento for confirmado.</p>
                <?php else: ?>
                    <p class="curso-note">Pagamento combinado direto com a equipe — em breve com checkout no site.</p>
                <?php endif; ?>
                <p class="curso-back"><a href="../index.php#produtos">← Ver todos os produtos</a></p>
            </div>
        </div>
    </section>

    <?php
    include __DIR__ . '/footer.php';
}
