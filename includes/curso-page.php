<?php
/**
 * Renderiza a página de venda de um curso específico (cursos/{slug}.php).
 * Reaproveita header/footer do site (mesma marca, menu, WhatsApp flutuante),
 * mas é uma página separada da landing page, como pedido.
 */
function render_curso_page(string $slug): void
{
    require_once __DIR__ . '/csrf.php';
    require_once __DIR__ . '/contratos.php';
    require_once __DIR__ . '/env.php';
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/cupons.php';
    csrf_ensure_session(); // precisa rodar antes de qualquer saída (header.php já imprime HTML)

    $config  = require __DIR__ . '/config.php';
    $version = require __DIR__ . '/version.php';
    $turma   = find_turma($slug);

    if (!$turma || !turma_ativa($turma)) {
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
    $datasFormatadas = turma_dates_full($turma['datas']);

    $cupomCodigo = trim($_GET['cupom'] ?? '');
    $cupom = $cupomCodigo !== '' ? validar_cupom($cupomCodigo, 'curso', $slug) : null;
    $precoFinal = (float) $turma['preco'];
    if ($cupom) {
        $precoFinal -= calcular_desconto_cupom($cupom, (float) $turma['preco']);
    }
    $precoFormatado = format_price($precoFinal);
    $waMsg = 'Olá! A turma "' . $turma['curso'] . '" está esgotada — quero entrar na lista de espera.';
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
                    <div>
                        <dt>💰 Investimento</dt>
                        <dd class="curso-price-big">
                            <?php if ($cupom): ?>
                                <span style="text-decoration:line-through;font-size:.75em;color:var(--muted-foreground);"><?= e(format_price((float) $turma['preco'])) ?></span>
                                <?= e($precoFormatado) ?>
                                <span class="badge badge-success" style="font-size:.6em;vertical-align:middle;">cupom <?= e($cupom['codigo']) ?> aplicado</span>
                            <?php else: ?>
                                <?= e($precoFormatado) ?>
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>

                <?php if ($esgotada): ?>
                    <a class="btn btn-muted btn-lg btn-block" href="<?= e($waLink) ?>" target="_blank" rel="noopener">Entrar na lista de espera</a>
                <?php else: ?>
                    <form method="post" action="../checkout.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="tipo" value="curso" />
                        <input type="hidden" name="slug" value="<?= e($turma['slug']) ?>" />
                        <?php if ($cupomCodigo !== ''): ?><input type="hidden" name="cupom" value="<?= e($cupomCodigo) ?>" /><?php endif; ?>
                        <label class="contrato-check">
                            <input type="checkbox" name="aceite_contrato" value="1" required />
                            <span>Li e aceito o <a href="#" data-modal-open="modal-contrato">contrato de prestação de serviços, o termo de LGPD/uso de imagem e o termo de consentimento para práticas com risco de lesão</a>.</span>
                        </label>
                        <button type="submit" class="btn btn-accent btn-lg btn-block">Pagar agora (Pix ou cartão)</button>
                    </form>
                <?php endif; ?>
                <p class="curso-note">Vagas limitadas — turmas reduzidas para garantir a qualidade da prática.</p>
                <p class="curso-back"><a href="../index.php#agenda">← Ver todas as turmas da agenda</a></p>
            </div>
        </div>
    </section>

    <div class="modal-overlay" id="modal-contrato" aria-hidden="true">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modal-contrato-titulo">
            <button type="button" class="modal-close" aria-label="Fechar">×</button>
            <div class="modal-tabs">
                <button type="button" class="modal-tab-btn is-active" data-tab-target="tab-servicos">Prestação de Serviços</button>
                <button type="button" class="modal-tab-btn" data-tab-target="tab-lgpd">LGPD e Imagem</button>
                <button type="button" class="modal-tab-btn" data-tab-target="tab-risco">Consentimento de Risco</button>
            </div>
            <div class="modal-tab-panel is-active" id="tab-servicos"><?php render_contrato_prestacao_servicos($config); ?></div>
            <div class="modal-tab-panel" id="tab-lgpd"><?php render_contrato_lgpd_imagem($config); ?></div>
            <div class="modal-tab-panel" id="tab-risco"><?php render_contrato_risco_procedimentos($config); ?></div>
        </div>
    </div>

    <?php
    include __DIR__ . '/footer.php';
}
