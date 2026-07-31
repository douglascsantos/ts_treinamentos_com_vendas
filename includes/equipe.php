<?php
/**
 * Layout mínimo compartilhado pelas páginas internas (equipe/) — painel de
 * diretor/administrativo/instrutor. Deliberadamente separado do
 * header.php/footer.php do site público (sem nav de marketing, sem
 * WhatsApp flutuante): é ferramenta interna, não página de venda.
 */

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
  .equipe-topbar {
    background: var(--primary); color: #fff; padding: .85rem 1.25rem;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem;
  }
  .equipe-topbar a { color: #fff; }
  .equipe-topbar strong { font-size: .95rem; }
  .equipe-main { max-width: 960px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
  .equipe-card { background: #fff; border: 1px solid var(--border); border-radius: .85rem; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card); }
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
    display: flex; align-items: center; justify-content: space-between; gap: .75rem;
    padding: .9rem .1rem; border-bottom: 1px solid var(--border); flex-wrap: wrap;
  }
  .equipe-list-item:last-child { border-bottom: 0; }
  .equipe-list-link { flex: 1; min-width: 160px; font-weight: 700; color: var(--foreground); }
  .equipe-list-link:hover { color: var(--primary); }
  .equipe-list-meta { display: block; margin-top: .2rem; font-size: .8rem; font-weight: 400; color: var(--muted-foreground); }
  .equipe-list-actions { display: flex; gap: .5rem; align-items: center; flex-shrink: 0; }
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

  @media (max-width: 640px) {
    .equipe-card { padding: 1.1rem; }
    .equipe-main { padding: 1.25rem .9rem 3rem; }
    .equipe-list-item { flex-direction: column; align-items: flex-start; }
    .equipe-list-actions { width: 100%; }
  }

  /* Grade de cartões-link do dashboard (diretor.php/administrativo.php) */
  .equipe-dash-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; margin-top: .5rem; }
  @media (min-width: 560px) { .equipe-dash-grid { grid-template-columns: 1fr 1fr; } }
  .equipe-dash-card {
    display: block; background: #fff; border: 1px solid var(--border); border-radius: .85rem;
    padding: 1.25rem 1.4rem; text-decoration: none; transition: var(--transition);
  }
  .equipe-dash-card:hover { border-color: var(--accent); transform: translateY(-2px); }
  .equipe-dash-card h4 { color: var(--primary); font-size: 1rem; margin-bottom: .3rem; }
  .equipe-dash-card p { font-size: .82rem; color: var(--muted-foreground); }
</style>
</head>
<body>
<div class="equipe-topbar">
  <strong>TS Treinamentos — Painel interno</strong>
  <span>
    <?= e($nomeExibido) ?> ·
    <?php if (($_SESSION['staff_tipo'] ?? '') === 'administrador'): ?>
        <a href="<?= $_SESSION['staff_nivel'] === 'diretor' ? 'diretor.php' : 'administrativo.php' ?>">Início</a> ·
        <?php if ($_SESSION['staff_nivel'] === 'diretor'): ?>
            <a href="instrutores.php">Instrutores</a> ·
            <a href="administradores.php">Administradores</a> ·
            <a href="alunos.php">Alunos</a> ·
        <?php endif; ?>
        <a href="turmas.php">Turmas</a> ·
        <a href="cupons.php">Cupons</a> ·
        <a href="boletos.php">Boletos</a> ·
        <a href="gastos.php">Gastos</a> ·
    <?php endif; ?>
    <a href="logout.php">Sair</a>
  </span>
</div>
<div class="equipe-main">
    <?php
}


function equipe_footer_html(): void
{
    ?>
</div>
<script src="../assets/js/main.js" defer></script>
</body>
</html>
    <?php
}
