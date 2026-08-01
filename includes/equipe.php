<?php
/**
 * Layout mínimo compartilhado pelas páginas internas (equipe/) — painel de
 * diretor/administrativo/instrutor. Deliberadamente separado do
 * header.php/footer.php do site público (sem nav de marketing, sem
 * WhatsApp flutuante): é ferramenta interna, não página de venda.
 */

/**
 * Mensagem de erro coerente pro painel interno — só staff autenticado vê
 * (nunca o site público), então mostrar o motivo técnico real (em vez de um
 * genérico "tente novamente") ajuda a pessoa a entender o problema (ex.: CPF
 * já cadastrado com formatação diferente, banco fora do ar) sem precisar
 * pedir pra mim investigar. Mensagem de exceção do PDO não inclui
 * credencial/host — só SQLSTATE + o que o driver do banco reportou.
 */
function equipe_erro_tecnico(Throwable $e): string
{
    return 'Erro técnico: ' . $e->getMessage();
}

/** Chamado sempre de dentro de equipe/*.php — links relativos assumem esse diretório. */
function equipe_exigir_login(array $tiposPermitidos): array
{
    require_once __DIR__ . '/csrf.php';
    csrf_ensure_session();

    $tipo = $_SESSION['staff_tipo'] ?? null;
    if (!$tipo || !in_array($tipo, $tiposPermitidos, true)) {
        header('Location: login.php');
        exit;
    }

    return [
        'tipo'   => $tipo,
        'id'     => $_SESSION['staff_id'],
        'nivel'  => $_SESSION['staff_nivel'] ?? null,
        'nome'   => $_SESSION['staff_nome'] ?? '',
    ];
}

function equipe_header_html(string $titulo, string $nomeExibido): void
{
    $tipo = $_SESSION['staff_tipo'] ?? '';
    $nivel = $_SESSION['staff_nivel'] ?? null;
    $inicio = $tipo === 'instrutor' ? 'instrutor.php' : ($nivel === 'diretor' ? 'diretor.php' : 'administrativo.php');
    $primeiroNome = trim(explode(' ', trim($nomeExibido))[0] ?? $nomeExibido);

    $itensMenu = [];
    if ($tipo === 'administrador') {
        $itensMenu[] = ['href' => $inicio, 'label' => 'Início', 'icone' => '🏠'];
        if ($nivel === 'diretor') {
            $itensMenu[] = ['href' => 'instrutores.php', 'label' => 'Instrutores', 'icone' => '🩺'];
            $itensMenu[] = ['href' => 'administradores.php', 'label' => 'Administradores', 'icone' => '🔑'];
            $itensMenu[] = ['href' => 'alunos.php', 'label' => 'Alunos', 'icone' => '🎓'];
        }
        $itensMenu[] = ['href' => 'turmas.php', 'label' => 'Turmas', 'icone' => '📅'];
        $itensMenu[] = ['href' => 'produtos.php', 'label' => 'Produtos', 'icone' => '🛍️'];
        $itensMenu[] = ['href' => 'vendas.php', 'label' => 'Vendas', 'icone' => '📦'];
        $itensMenu[] = ['href' => 'agenda.php', 'label' => 'Imagens da agenda', 'icone' => '🖼️'];
        $itensMenu[] = ['href' => 'cupons.php', 'label' => 'Cupons', 'icone' => '🏷️'];
        $itensMenu[] = ['href' => 'boletos.php', 'label' => 'Boletos', 'icone' => '🧾'];
        $itensMenu[] = ['href' => 'gastos.php', 'label' => 'Gastos', 'icone' => '💸'];
    } elseif ($tipo === 'instrutor') {
        $itensMenu[] = ['href' => 'instrutor.php', 'label' => 'Início', 'icone' => '🏠'];
    }
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= e($titulo) ?> | Painel interno | TS Treinamentos</title>
<meta name="robots" content="noindex, nofollow" />
<link rel="stylesheet" href="../assets/css/style.css" />
<style>
  body { background: #f4f5f3; }

  /* Cabeçalho do painel: marca + menu colapsável (sempre em painel, nunca em
     lista pontuada) + atalho pro perfil da própria pessoa logada. */
  .equipe-topbar { background: var(--primary); position: relative; z-index: 20; }
  .equipe-topbar-row {
    display: flex; align-items: center; justify-content: space-between; gap: .75rem;
    flex-wrap: wrap; row-gap: .5rem;
    padding-top: .8rem; padding-bottom: .8rem;
  }
  .equipe-brand { display: flex; flex-direction: column; line-height: 1.2; color: #fff; text-decoration: none; }
  .equipe-brand-title { font-family: 'Poppins','Inter',sans-serif; font-weight: 800; font-size: .98rem; }
  .equipe-brand-sub { display: none; font-size: .68rem; color: rgba(255,255,255,.75); text-transform: uppercase; letter-spacing: .05em; }

  .equipe-topbar-actions { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; justify-content: space-between; width: 100%; }
  .equipe-menu-btn, .equipe-user-link, .equipe-logout-link {
    display: inline-flex; align-items: center; gap: .4rem; border-radius: .6rem;
    padding: .5rem .85rem; font-size: .82rem; font-weight: 700; min-height: 44px;
    color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,.28); background: rgba(255,255,255,.07);
    cursor: pointer; transition: var(--transition); line-height: 1;
  }
  .equipe-menu-btn:hover, .equipe-user-link:hover, .equipe-logout-link:hover { background: rgba(255,255,255,.18); }
  .equipe-menu-btn[aria-expanded="true"] { background: var(--accent); border-color: var(--accent); }
  .equipe-user-link { background: rgba(255,255,255,.14); }

  .equipe-nav-panel { background: #123018; border-top: 1px solid rgba(255,255,255,.12); }
  .equipe-nav-panel[hidden] { display: none !important; }
  .equipe-nav-panel-inner { display: flex; flex-direction: column; flex-wrap: wrap; gap: .6rem; padding-top: 1rem; padding-bottom: 1rem; }
  .equipe-nav-panel a {
    display: inline-flex; align-items: center; gap: .5rem; background: rgba(255,255,255,.06); width: 100%;
    color: rgba(255,255,255,.94); border-radius: .6rem; padding: .65rem 1.1rem; font-size: .86rem; font-weight: 600;
    text-decoration: none; min-height: 44px; transition: var(--transition); border: 1px solid transparent;
  }
  .equipe-nav-panel a:hover { background: var(--accent); border-color: var(--accent); color: #fff; }

  .equipe-page-title { font-size: 1.25rem; color: var(--primary); margin-bottom: 1.4rem; }

  .equipe-main { max-width: 1040px; margin: 0 auto; padding: 1.25rem .9rem 3rem; }
  .equipe-card { background: #fff; border: 1px solid var(--border); border-radius: .85rem; padding: 1.1rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card); }
  /* Tabela pode ter mais colunas do que cabe numa tela de celular — em vez de
     cortar (o body do site é overflow-x:hidden), essa faixa rola de lado só
     nela, sem quebrar o layout da página em volta. */
  .equipe-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .equipe-table { width: 100%; min-width: 480px; border-collapse: collapse; font-size: .88rem; }
  .equipe-table th, .equipe-table td { text-align: left; padding: .55rem .6rem; border-bottom: 1px solid var(--border); white-space: nowrap; }
  .equipe-table th { color: var(--muted-foreground); font-weight: 600; }

  /* Par de campos de peso igual (nome+e-mail, CPF+área, preço+data...) — ao
     contrário de .form-row (110px fixo, feito só pra rótulo curto tipo
     "Pronome"), aqui as duas colunas dividem o espaço igualmente e empilham
     antes de espremer em telas estreitas. */
  .equipe-form-row { display: grid; gap: 1rem; grid-template-columns: 1fr; }
  @media (min-width: 480px) { .equipe-form-row { grid-template-columns: 1fr 1fr; } }

  /* Padrão "lista primeiro": lista empilhada (não tabela larga) — melhor pro
     mobile. Cada item é um link (edita) + um botão de desabilitar/reativar
     (nunca exclui, só marca inativo). */
  .equipe-list { display: flex; flex-direction: column; margin-top: .5rem; }
  .equipe-list-item {
    display: flex; flex-direction: column; align-items: flex-start; justify-content: space-between; gap: .75rem;
    padding: .9rem .1rem; border-bottom: 1px solid var(--border); flex-wrap: wrap;
  }
  .equipe-list-item:last-child { border-bottom: 0; }
  .equipe-list-link { flex: 1; min-width: 160px; font-weight: 700; color: var(--foreground); }
  .equipe-list-link:hover { color: var(--primary); }
  .equipe-list-meta { display: block; margin-top: .2rem; font-size: .8rem; font-weight: 400; color: var(--muted-foreground); }
  .equipe-list-actions { display: flex; gap: .5rem; align-items: center; flex-shrink: 0; width: 100%; }
  .equipe-empty { color: var(--muted-foreground); font-size: .9rem; margin-top: .5rem; }

  .badge-ativo, .badge-inativo {
    display: inline-block; padding: .2rem .65rem; border-radius: 999px; font-size: .7rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .03em;
  }
  .badge-ativo { background: rgba(76,175,80,.15); color: var(--accent); }
  .badge-inativo { background: rgba(239,68,68,.12); color: var(--destructive); }

  /* Botão "+" verde que revela o formulário (data-toggle-target, ver main.js) */
  .btn-add-toggle {
    display: inline-flex; align-items: center; gap: .4rem; background: var(--accent); color: #fff;
    border: 0; border-radius: .6rem; padding: .75rem 1.25rem; font-weight: 700; font-size: .92rem;
    cursor: pointer; min-height: 44px;
  }
  .btn-add-toggle:hover { background: #3f9b43; }
  .btn-add-toggle[aria-expanded="true"] { background: var(--muted-foreground); }
  .toggle-form { margin-top: 1.35rem; padding-top: 1.35rem; border-top: 1px dashed var(--border); }
  .toggle-form[hidden] { display: none !important; }

  .btn-disable, .btn-enable {
    border-radius: .5rem; padding: .45rem .85rem; font-size: .78rem; font-weight: 700; cursor: pointer;
    background: transparent; min-height: 44px;
  }
  .btn-disable { border: 1px solid var(--destructive); color: var(--destructive); }
  .btn-disable:hover { background: rgba(239,68,68,.08); }
  .btn-enable { border: 1px solid var(--accent); color: var(--accent); }
  .btn-enable:hover { background: rgba(76,175,80,.08); }

  /* Grade de cartões-link do dashboard (diretor.php/administrativo.php) */
  .equipe-dash-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; margin-top: .5rem; }
  @media (min-width: 560px) { .equipe-dash-grid { grid-template-columns: 1fr 1fr; } }
  @media (min-width: 860px) { .equipe-dash-grid { grid-template-columns: 1fr 1fr 1fr; } }
  .equipe-dash-card {
    display: flex; align-items: flex-start; gap: .9rem; background: #fff; border: 1px solid var(--border);
    border-radius: .85rem; padding: 1.25rem 1.4rem; text-decoration: none; transition: var(--transition);
  }
  .equipe-dash-card:hover { border-color: var(--accent); box-shadow: var(--shadow-card); transform: translateY(-2px); }
  .equipe-dash-card-icon {
    flex-shrink: 0; width: 42px; height: 42px; border-radius: .6rem; background: rgba(76,175,80,.12);
    display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
  }
  .equipe-dash-card h4 { color: var(--primary); font-size: 1rem; margin-bottom: .3rem; }
  .equipe-dash-card p { font-size: .82rem; color: var(--muted-foreground); line-height: 1.4; }

  /* A partir daqui sobra espaço horizontal — desfaz as pilhas do mobile. */
  @media (min-width: 641px) {
    .equipe-brand-sub { display: block; }
    .equipe-topbar-actions { width: auto; justify-content: flex-end; }
    .equipe-nav-panel-inner { flex-direction: row; }
    .equipe-nav-panel a { width: auto; }
    .equipe-page-title { font-size: 1.5rem; }
    .equipe-main { padding: 2rem 1.25rem 4rem; }
    .equipe-card { padding: 1.5rem; }
    .equipe-list-item { flex-direction: row; align-items: center; }
    .equipe-list-actions { width: auto; }
  }
</style>
</head>
<body>
<header class="equipe-topbar">
  <div class="container equipe-topbar-row">
    <a class="equipe-brand" href="<?= e($inicio) ?>">
      <span class="equipe-brand-title">TS Treinamentos</span>
      <span class="equipe-brand-sub">Painel interno</span>
    </a>
    <div class="equipe-topbar-actions">
      <?php if ($itensMenu): ?>
        <button type="button" class="equipe-menu-btn" data-toggle-target="equipeMenu" data-toggle-close-text="✕ Fechar" aria-expanded="false">☰ Menu</button>
      <?php endif; ?>
      <a class="equipe-user-link" href="perfil.php">👤 <?= e($primeiroNome) ?></a>
      <a class="equipe-logout-link" href="logout.php">Sair</a>
    </div>
  </div>
  <?php if ($itensMenu): ?>
  <nav class="equipe-nav-panel" id="equipeMenu" hidden>
    <div class="container equipe-nav-panel-inner">
      <?php foreach ($itensMenu as $item): ?>
        <a href="<?= e($item['href']) ?>"><span aria-hidden="true"><?= $item['icone'] ?></span> <?= e($item['label']) ?></a>
      <?php endforeach; ?>
    </div>
  </nav>
  <?php endif; ?>
</header>
<div class="equipe-main">
    <h1 class="equipe-page-title"><?= e($titulo) ?></h1>
    <?php
}


function equipe_footer_html(): void
{
    $version = require __DIR__ . '/version.php';
    ?>
</div>
<script src="../assets/js/main.js?v=<?= e($version['version']) ?>" defer></script>
</body>
</html>
    <?php
}
