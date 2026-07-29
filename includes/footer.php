<?php
/** @var array $config */
/** @var array $version */
/** @var string $base_path Definido em header.php — mantém consistência de prefixo. */
$base_path = $base_path ?? '';
?>
</main>

<footer id="contato" class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand-col">
            <div class="footer-brand">
                <img src="<?= e($base_path) ?>assets/images/logo-ts.png" alt="TS Treinamentos" width="48" height="48" />
                <div>
                    <p class="brand-title">TS Treinamentos</p>
                    <p class="brand-sub">em Saúde</p>
                </div>
            </div>
            <p class="footer-desc">Capacitação prática para profissionais de saúde que querem se destacar com segurança clínica e excelência técnica.</p>
            <div class="social">
                <a href="<?= e($config['instagram']) ?>" target="_blank" rel="noopener" aria-label="Instagram">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                </a>
                <a href="<?= e(wa_link($config['whatsapp'], 'Olá! Quero saber mais sobre os cursos da TS Treinamentos.')) ?>" target="_blank" rel="noopener" aria-label="WhatsApp">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                </a>
            </div>
        </div>

        <div class="footer-col">
            <h4>Navegação</h4>
            <ul>
                <li><a href="<?= e($base_path) ?>index.php#inicio">Início</a></li>
                <li><a href="<?= e($base_path) ?>index.php#agenda">Cursos e Agenda</a></li>
                <li><a href="<?= e($base_path) ?>index.php#sobre">Sobre a escola</a></li>
                <li><a href="<?= e($base_path) ?>index.php#faq">Dúvidas frequentes</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Contato</h4>
            <ul class="footer-contact">
                <li><span class="ico" aria-hidden="true">📍</span> <?= e($config['address']) ?></li>
                <li><span class="ico" aria-hidden="true">📞</span> <a href="tel:+<?= e(preg_replace('/\D/', '', $config['whatsapp'])) ?>"><?= e($config['phone']) ?></a></li>
                <li><span class="ico" aria-hidden="true">✉</span> <a href="mailto:<?= e($config['email']) ?>"><?= e($config['email']) ?></a></li>
                <li><span class="ico" aria-hidden="true">📷</span> <a href="<?= e($config['instagram']) ?>" target="_blank" rel="noopener"><?= e($config['instagram_handle']) ?></a></li>
            </ul>
        </div>

        <div class="footer-col footer-map-col">
            <h4>Localização</h4>
            <div class="card-frame">
                <iframe title="Localização TS Treinamentos em Saúde"
                        src="<?= e($config['maps_embed']) ?>"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </div>

    <div class="container footer-bottom">
        <p>&copy; <?= e($version['year']) ?> <?= e($config['site_name']) ?>. Todos os direitos reservados.</p>
        <p class="footer-version">
            v<?= e($version['version']) ?> &middot; &ldquo;<?= e($version['codename']) ?>&rdquo;
            <?php if (($version['stage'] ?? '') !== '') : ?>
                <span class="footer-stage">(<?= e($version['stage']) ?>)</span>
            <?php endif; ?>
        </p>
    </div>
</footer>

<a href="<?= e(wa_link($config['whatsapp'], 'Olá! Quero saber mais sobre os cursos da TS Treinamentos.')) ?>"
   target="_blank" rel="noopener"
   class="wa-float" aria-label="Falar no WhatsApp">
    <svg width="30" height="30" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.588-5.946 0-6.556 5.332-11.891 11.893-11.891 3.181 0 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.481 8.403 0 6.556-5.332 11.892-11.893 11.892-1.99 0-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.886-9.886 0-5.446-4.428-9.886-9.886-9.886-5.448 0-9.886 4.44-9.886 9.886 0 2.226.746 4.312 2.119 5.902l-.43 1.566 1.564-.41-.145-.164z"/></svg>
</a>

<script src="<?= e($base_path) ?>assets/js/main.js?v=<?= e($version['version']) ?>" defer></script>
</body>
</html>
