<?php
/**
 * Renderiza a página de venda de um produto específico (produtos/{slug}.php).
 * Reaproveita header/footer do site, mas é uma página separada da landing page.
 */
function render_produto_page(string $slug): void
{
    require_once __DIR__ . '/csrf.php';
    require_once __DIR__ . '/contratos.php';
    require_once __DIR__ . '/env.php';
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/cupons.php';
    csrf_ensure_session(); // precisa rodar antes de qualquer saída (header.php já imprime HTML)

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

    $cupomCodigo = trim($_GET['cupom'] ?? '');
    $cupom = $cupomCodigo !== '' ? validar_cupom($cupomCodigo, 'produto', $slug) : null;
    $precoFinal = (float) $produto['preco'];
    if ($cupom) {
        $precoFinal -= calcular_desconto_cupom($cupom, (float) $produto['preco']);
    }
    $precoFormatado = format_price($precoFinal);
    $waMsg = 'Olá! Quero ser avisado quando o produto "' . $produto['nome'] . '" voltar ao estoque.';
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
                    <div>
                        <dt>💰 Preço</dt>
                        <dd class="curso-price-big">
                            <?php if ($cupom): ?>
                                <span style="text-decoration:line-through;font-size:.75em;color:var(--muted-foreground);"><?= e(format_price((float) $produto['preco'])) ?></span>
                                <?= e($precoFormatado) ?>
                                <span class="badge badge-success" style="font-size:.6em;vertical-align:middle;">cupom <?= e($cupom['codigo']) ?> aplicado</span>
                            <?php else: ?>
                                <?= e($precoFormatado) ?>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <?php if ($digital): ?>
                        <div><dt>📄 Formato</dt><dd>E-book em PDF</dd></div>
                    <?php else: ?>
                        <div><dt>📦 Estoque</dt><dd><?= (int) $produto['estoque'] ?> unidade(s)</dd></div>
                    <?php endif; ?>
                </dl>

                <?php if ($esgotado): ?>
                    <a class="btn btn-muted btn-lg btn-block" href="<?= e($waLink) ?>" target="_blank" rel="noopener">Avisar quando chegar</a>
                <?php else: ?>
                    <form method="post" action="../checkout.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="tipo" value="produto" />
                        <input type="hidden" name="slug" value="<?= e($produto['slug']) ?>" />
                        <?php if ($cupomCodigo !== ''): ?><input type="hidden" name="cupom" value="<?= e($cupomCodigo) ?>" /><?php endif; ?>
                        <label class="contrato-check">
                            <input type="checkbox" name="aceite_contrato" value="1" required />
                            <span>Li e aceito o <a href="#" data-modal-open="modal-contrato">contrato de venda de produtos</a>.</span>
                        </label>
                        <button type="submit" class="btn btn-accent btn-lg btn-block">Pagar agora (Pix ou cartão)</button>
                    </form>
                <?php endif; ?>
                <?php if ($digital): ?>
                    <p class="curso-note">Produto digital: você recebe o PDF por e-mail/WhatsApp assim que o pagamento for confirmado.</p>
                <?php endif; ?>
                <p class="curso-back"><a href="../index.php#produtos">← Ver todos os produtos</a></p>
            </div>
        </div>
    </section>

    <div class="modal-overlay" id="modal-contrato" aria-hidden="true">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modal-contrato-titulo">
            <button type="button" class="modal-close" aria-label="Fechar">×</button>
            <?php render_contrato_produtos($config); ?>
        </div>
    </div>

    <?php
    include __DIR__ . '/footer.php';
}
