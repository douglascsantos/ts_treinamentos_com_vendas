<?php
/** @var array $config */
/** @var array $version */
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= e($config['site_name']) ?> | Capacitação prática para profissionais de saúde</title>
<meta name="description" content="Cursos práticos e certificados para enfermeiros e técnicos de saúde em São José do Rio Preto/SP. Turmas reduzidas, professores atuantes no mercado." />
<link rel="icon" href="assets/images/cropped-logo-atualizado-32x32.png" sizes="32x32" />
<link rel="icon" href="assets/images/cropped-logo-atualizado-192x192.png" sizes="192x192" />
<link rel="apple-touch-icon" href="assets/images/cropped-logo-atualizado-180x180.png" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="assets/css/style.css?v=<?= e($version['version']) ?>" />
</head>
<body>

<a href="#main" class="skip-link">Pular para o conteúdo</a>

<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="#inicio" aria-label="<?= e($config['site_name']) ?>">
            <img src="assets/images/logo-ts.png" alt="Logotipo TS Treinamentos em Saúde" width="48" height="48" />
            <span class="brand-text">
                <strong>TS Treinamentos</strong>
                <small>em Saúde</small>
            </span>
        </a>

        <nav class="primary-nav" aria-label="Principal">
            <ul class="menu">
                <li><a href="#inicio">Início</a></li>
                <li><a href="#agenda">Cursos</a></li>
                <li><a href="#agenda">Agenda</a></li>
                <li><a href="#contato">Contato</a></li>
                <li><a href="#" class="menu-disabled" aria-disabled="true" title="Em breve">Área do Aluno</a></li>
            </ul>
        </nav>

        <button class="menu-toggle" aria-label="Abrir menu" aria-expanded="false" aria-controls="mobile-nav" type="button">
            <span></span><span></span><span></span>
        </button>
    </div>

    <div class="mobile-nav" id="mobile-nav" hidden>
        <ul>
            <li><a href="#inicio">Início</a></li>
            <li><a href="#agenda">Cursos</a></li>
            <li><a href="#agenda">Agenda</a></li>
            <li><a href="#contato">Contato</a></li>
            <li><a href="#" class="menu-disabled" aria-disabled="true">Área do Aluno <span class="soon">em breve</span></a></li>
        </ul>
    </div>
</header>
<div class="mobile-nav-overlay" id="mobile-nav-overlay" hidden></div>

<main id="main">
