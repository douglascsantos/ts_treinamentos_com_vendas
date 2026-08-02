<?php
/** @var array $config */
/** @var array $version */
/** @var string|null $page_title Título específico da página (opcional). */
/** @var string|null $page_description Meta description específica (opcional). */
/** @var string $base_path Prefixo relativo para assets/links quando a página está numa subpasta (ex.: 'cursos/' -> '../'). Padrão: raiz. */
$base_path = $base_path ?? '';
$title = $page_title ?? ($config['site_name'] . ' | Capacitação prática para profissionais de saúde');
$description = $page_description ?? 'Cursos práticos e certificados para enfermeiros e técnicos de saúde em São José do Rio Preto/SP. Turmas reduzidas, professores atuantes no mercado.';

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/alunos.php';
csrf_ensure_session();
$alunoLogadoHeader = !empty($_SESSION['aluno_id']) ? find_aluno_by_id($_SESSION['aluno_id']) : null;
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>" />
<link rel="icon" href="<?= e($base_path) ?>assets/images/cropped-logo-atualizado-32x32.png" sizes="32x32" />
<link rel="icon" href="<?= e($base_path) ?>assets/images/cropped-logo-atualizado-192x192.png" sizes="192x192" />
<link rel="apple-touch-icon" href="<?= e($base_path) ?>assets/images/cropped-logo-atualizado-180x180.png" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="<?= e($base_path) ?>assets/css/style.css?v=<?= e($version['version']) ?>" />
</head>
<body>

<a href="#main" class="skip-link">Pular para o conteúdo</a>

<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e($base_path) ?>index.php#inicio" aria-label="<?= e($config['site_name']) ?>">
            <img src="<?= e($base_path) ?>assets/images/logo-ts.png" alt="Logotipo TS Treinamentos em Saúde" width="48" height="48" />
            <span class="brand-text">
                <strong>TS Treinamentos</strong>
                <small>em Saúde</small>
            </span>
        </a>

        <nav class="primary-nav" aria-label="Principal">
            <ul class="menu">
                <li><a href="<?= e($base_path) ?>index.php#inicio">Início</a></li>
                <li><a href="<?= e($base_path) ?>index.php#agenda">Cursos</a></li>
                <li><a href="<?= e($base_path) ?>index.php#agenda">Agenda</a></li>
                <li><a href="<?= e($base_path) ?>index.php#produtos">Produtos</a></li>
                <li><a href="<?= e($base_path) ?>index.php#contato">Contato</a></li>
                <?php if ($alunoLogadoHeader): ?>
                    <li><a href="<?= e($base_path) ?>minha-conta.php" class="nav-aluno-logado">Bem-vindo, <?= e(primeiro_nome($alunoLogadoHeader['nome'])) ?></a></li>
                    <li><a href="<?= e($base_path) ?>meus-dados.php">Meus dados</a></li>
                    <li><a href="<?= e($base_path) ?>financeiro.php">Financeiro</a></li>
                    <li><a href="<?= e($base_path) ?>logout.php">Sair da conta</a></li>
                <?php else: ?>
                    <li><a href="<?= e($base_path) ?>area-do-aluno.php">Área do Aluno</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <button class="menu-toggle" aria-label="Abrir menu" aria-expanded="false" aria-controls="mobile-nav" type="button">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>

<!-- Fora do <header>: backdrop-filter no .site-header cria um novo containing
     block pra descendentes position:fixed (comportamento do CSS), o que
     encolhia esse menu pra caber na altura do cabeçalho em vez da tela
     inteira. Como elemento irmão, volta a se posicionar em relação à viewport. -->
<div class="mobile-nav" id="mobile-nav" hidden>
    <ul>
        <li><a href="<?= e($base_path) ?>index.php#inicio">Início</a></li>
        <li><a href="<?= e($base_path) ?>index.php#agenda">Cursos</a></li>
        <li><a href="<?= e($base_path) ?>index.php#agenda">Agenda</a></li>
        <li><a href="<?= e($base_path) ?>index.php#produtos">Produtos</a></li>
        <li><a href="<?= e($base_path) ?>index.php#contato">Contato</a></li>
        <?php if ($alunoLogadoHeader): ?>
            <li><a href="<?= e($base_path) ?>minha-conta.php" class="nav-aluno-logado">Bem-vindo, <?= e(primeiro_nome($alunoLogadoHeader['nome'])) ?></a></li>
            <li><a href="<?= e($base_path) ?>meus-dados.php">Meus dados</a></li>
            <li><a href="<?= e($base_path) ?>financeiro.php">Financeiro</a></li>
            <li><a href="<?= e($base_path) ?>logout.php">Sair da conta</a></li>
        <?php else: ?>
            <li><a href="<?= e($base_path) ?>area-do-aluno.php">Área do Aluno</a></li>
        <?php endif; ?>
    </ul>
</div>
<div class="mobile-nav-overlay" id="mobile-nav-overlay" hidden></div>

<main id="main">
