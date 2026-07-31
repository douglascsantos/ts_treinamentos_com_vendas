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
  @media (max-width: 640px) {
    .equipe-card { padding: 1.1rem; }
    .equipe-main { padding: 1.25rem .9rem 3rem; }
  }
</style>
</head>
<body>
<div class="equipe-topbar">
  <strong>TS Treinamentos — Painel interno</strong>
  <span>
    <?= e($nomeExibido) ?> ·
    <?php if (($_SESSION['staff_tipo'] ?? '') === 'administrador'): ?>
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
