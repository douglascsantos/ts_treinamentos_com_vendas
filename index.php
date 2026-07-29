<?php
require __DIR__ . '/includes/functions.php';
$config  = require __DIR__ . '/includes/config.php';
$version = require __DIR__ . '/includes/version.php';

/**
 * Agenda de turmas (protótipo).
 * Em uma fase futura isso vem do banco (MySQL). Por enquanto é uma lista
 * estática só para validar o layout de alta conversão.
 */
$turmas = [
    [
        'dia' => '27', 'mes' => 'Ago',
        'titulo' => 'Perfuração Humanizada',
        'imagem' => 'course-furo-humanizado.jpg',
        'status' => 'aberta',
    ],
    [
        'dia' => '03', 'mes' => 'Set',
        'titulo' => 'Administração de Medicações Injetáveis',
        'imagem' => 'course-medicacao-injetavel.jpg',
        'status' => 'ultimas',
    ],
    [
        'dia' => '10', 'mes' => 'Set',
        'titulo' => 'Eletrocardiograma e Monitorização',
        'imagem' => 'course-ecg.jpg',
        'status' => 'aberta',
    ],
    [
        'dia' => '17', 'mes' => 'Set',
        'titulo' => 'Rotinas Hospitalares (turma estendida + consultoria)',
        'imagem' => 'course-acls.jpg',
        'status' => 'esgotada',
    ],
    [
        'dia' => '24', 'mes' => 'Set',
        'titulo' => 'Processo Seletivo para Provas de Hospitais',
        'imagem' => 'course-processo-seletivo.jpg',
        'status' => 'aberta',
    ],
    [
        'dia' => '01', 'mes' => 'Out',
        'titulo' => 'Acesso Vascular, Sondagem e Coleta de Sangue',
        'imagem' => 'course-acesso.jpg',
        'status' => 'aberta',
    ],
];

$status_labels = [
    'aberta'   => ['Vagas abertas', 'status-open'],
    'ultimas'  => ['Últimas vagas', 'status-last'],
    'esgotada' => ['Esgotado', 'status-out'],
];

/** Fotos de vitrine da rotina da escola — hoje estáticas, pensadas para
 *  no futuro serem substituídas por um feed automático do Instagram
 *  (ver prototipo_stitch.md, seção "Carrossel do Instagram"). */
$galeria = [
    ['img' => 'hero-training.jpg', 'alt' => 'Treinamento prático com profissionais de saúde'],
    ['img' => 'fachada.png', 'alt' => 'Fachada da TS Treinamentos em Saúde'],
    ['img' => 'course-ecg.jpg', 'alt' => 'Prática de eletrocardiograma'],
    ['img' => 'course-medicacao-injetavel.jpg', 'alt' => 'Prática de administração de medicação injetável'],
    ['img' => 'course-furo-humanizado.jpg', 'alt' => 'Treinamento de perfuração humanizada'],
    ['img' => 'course-processo-seletivo.jpg', 'alt' => 'Preparatório para processo seletivo'],
];

include __DIR__ . '/includes/header.php';
?>

<section id="inicio" class="hero">
    <div class="hero-bg">
        <img src="assets/images/hero-training.jpg" alt="Profissionais de saúde em treinamento prático" />
        <div class="hero-overlay"></div>
    </div>

    <div class="container hero-content">
        <span class="badge">🛡 Certificação reconhecida</span>
        <h1>Eleve sua carreira na saúde com <span class="accent">treinamentos práticos</span> e certificados.</h1>
        <p class="lead">Capacitação intensiva para enfermeiros, técnicos e profissionais que buscam segurança clínica, novas oportunidades e diferencial no mercado.</p>

        <div class="hero-ctas">
            <a class="btn btn-accent btn-lg" href="#agenda">📅 Ver Agenda</a>
            <a class="btn btn-outline btn-lg" href="<?= e(wa_link($config['whatsapp'], 'Olá! Quero saber mais sobre os cursos da TS Treinamentos.')) ?>" target="_blank" rel="noopener">Falar no WhatsApp</a>
        </div>

        <dl class="hero-stats">
            <div><dt>+1.000</dt><dd>Alunos formados</dd></div>
            <div><dt>+07</dt><dd>Cursos ativos</dd></div>
            <div><dt>5★</dt><dd>Avaliação média</dd></div>
        </dl>
    </div>
</section>

<section class="section section-credibility">
    <div class="container credibility-inner">
        <p><strong>São José do Rio Preto/SP</strong> · Aulas 100% presenciais, em laboratório próprio, com professores atuantes no mercado hospitalar.</p>
    </div>
</section>

<section id="agenda" class="section section-schedule">
    <div class="container">
        <div class="section-head">
            <p class="eyebrow">Agenda de turmas</p>
            <h2>Próximas datas confirmadas</h2>
            <p class="muted">Garanta sua vaga com antecedência. As turmas são reduzidas para máxima qualidade prática.</p>
        </div>

        <div class="course-grid">
            <?php foreach ($turmas as $t):
                [$label, $statusClass] = $status_labels[$t['status']];
                $esgotada = $t['status'] === 'esgotada';
                $waMsg = 'Olá! Quero reservar minha vaga no curso "' . $t['titulo'] . '".';
            ?>
            <article class="course-card">
                <div class="course-img">
                    <span class="course-status <?= e($statusClass) ?>"><?= e($label) ?></span>
                    <span class="course-date"><b><?= e($t['dia']) ?></b><small><?= e($t['mes']) ?></small></span>
                    <img src="assets/images/<?= e($t['imagem']) ?>" alt="<?= e($t['titulo']) ?>" loading="lazy" />
                </div>
                <div class="course-body">
                    <h3><?= e($t['titulo']) ?></h3>
                    <p class="course-loc">📍 Presencial · São José do Rio Preto</p>
                    <?php if ($esgotada): ?>
                        <button class="btn btn-muted btn-block" disabled>Lista de espera</button>
                    <?php else: ?>
                        <a class="btn btn-primary btn-block" href="<?= e(wa_link($config['whatsapp'], $waMsg)) ?>" target="_blank" rel="noopener">Garantir vaga</a>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <div class="schedule-cta">
            <a class="btn btn-accent btn-lg" href="<?= e(wa_link($config['whatsapp'], 'Olá! Quero reservar minha vaga.')) ?>" target="_blank" rel="noopener">
                📅 Reservar minha vaga
            </a>
        </div>
    </div>
</section>

<section class="section section-gallery">
    <div class="container">
        <div class="section-head gallery-head">
            <div>
                <p class="eyebrow">Nossa rotina</p>
                <h2>Acompanhe no Instagram</h2>
            </div>
            <a class="gallery-link" href="<?= e($config['instagram']) ?>" target="_blank" rel="noopener"><?= e($config['instagram_handle']) ?> ↗</a>
        </div>
    </div>
    <div class="gallery-scroll" role="list">
        <?php foreach ($galeria as $g): ?>
            <div class="gallery-item" role="listitem">
                <img src="assets/images/<?= e($g['img']) ?>" alt="<?= e($g['alt']) ?>" loading="lazy" />
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section id="sobre" class="section section-about">
    <div class="container about-grid">
        <div class="card-frame about-img">
            <img src="assets/images/fachada.png" alt="Fachada TS Treinamentos em Saúde" loading="lazy" />
        </div>
        <div>
            <p class="eyebrow">Sobre a escola</p>
            <h2>TS Treinamentos: sua ponte para o mercado da saúde</h2>
            <p class="lead-muted">Com sede em São José do Rio Preto, somos uma escola de capacitação prática para profissionais e estudantes da área da saúde. Nossa metodologia é aplicada, com equipamentos e cenários próximos da realidade hospitalar.</p>
            <ul class="check-list">
                <li>✅ Aulas 100% práticas em laboratório próprio</li>
                <li>✅ Professores atuantes no mercado hospitalar</li>
                <li>✅ Certificação ao final de cada curso</li>
                <li>✅ Turmas reduzidas para atenção individual</li>
                <li>✅ Suporte direto pelo WhatsApp</li>
            </ul>
            <a class="btn btn-primary" href="<?= e(wa_link($config['whatsapp'], 'Olá! Quero falar com um consultor sobre os cursos.')) ?>" target="_blank" rel="noopener">Fale com um consultor →</a>
        </div>
    </div>
</section>

<section id="faq" class="section section-faq">
    <div class="container faq-container">
        <div class="section-head">
            <p class="eyebrow">Dúvidas frequentes</p>
            <h2>Tudo o que você precisa saber</h2>
        </div>

        <div class="faq-list">
            <?php
            $faqs = [
                ['Os cursos têm certificado?', 'Sim. Todos os cursos emitem certificado de conclusão, com código de verificação individual.'],
                ['As aulas são presenciais? Onde ficam?', 'Sim, as aulas acontecem na nossa unidade própria em São José do Rio Preto, na Rua Gastão Vidigal, 2150.'],
                ['Preciso de experiência prévia para me inscrever?', 'Depende do curso — a maioria é voltada para quem já atua ou estuda na área da saúde. Em caso de dúvida, fale com a gente pelo WhatsApp.'],
                ['Como funciona o pagamento?', 'Cursos mais longos podem usar matrícula de 15% para garantir a vaga, com o saldo quitado no primeiro dia de aula. Os demais são pagos integralmente na inscrição.'],
                ['Como faço para garantir minha vaga?', 'Clique em "Garantir vaga" na turma desejada ou fale direto com a gente pelo WhatsApp — as turmas são reduzidas e as vagas se esgotam rápido.'],
                ['Tem suporte se eu tiver dúvidas antes de me inscrever?', 'Tem sim! Nosso time responde pelo WhatsApp para tirar qualquer dúvida antes, durante e depois do curso.'],
            ];
            foreach ($faqs as $i => $faq): ?>
                <div class="faq-item">
                    <button class="faq-question" type="button" aria-expanded="false" aria-controls="faq-a-<?= (int) $i ?>">
                        <span><?= e($faq[0]) ?></span>
                        <span class="faq-icon" aria-hidden="true">+</span>
                    </button>
                    <div class="faq-answer" id="faq-a-<?= (int) $i ?>" hidden>
                        <p><?= e($faq[1]) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-cta-final">
    <div class="container cta-final-inner">
        <h2>Invista na sua evolução profissional agora</h2>
        <p>Turmas reduzidas, vagas limitadas e formato 100% prático — dê o próximo passo na sua carreira em saúde hoje mesmo.</p>
        <div class="cta-final-actions">
            <a class="btn btn-accent btn-lg" href="#agenda">Ver Agenda de Turmas</a>
            <a class="btn btn-outline-dark btn-lg" href="<?= e(wa_link($config['whatsapp'], 'Olá! Quero saber mais sobre os cursos da TS Treinamentos.')) ?>" target="_blank" rel="noopener">Falar no WhatsApp</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
