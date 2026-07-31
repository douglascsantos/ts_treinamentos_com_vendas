<?php
/**
 * Lançamento de gastos + resumo por tipo (controle interno, nunca aparece
 * pro cliente). Ver includes/gastos.php pras categorias.
 */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/administradores.php';
require __DIR__ . '/../includes/turmas.php';
require __DIR__ . '/../includes/gastos.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['administrador']);

$mensagens = [];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'criar_gasto') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $tipo = $_POST['tipo'] ?? '';
        $categoria = $_POST['categoria'] ?? '';
        $valor = (float) str_replace(',', '.', $_POST['valor'] ?? '0');
        $dataGasto = $_POST['data_gasto'] ?? '';

        if (!array_key_exists($tipo, GASTO_TIPOS)) {
            $erros[] = 'Selecione o tipo de gasto.';
        } elseif (!array_key_exists($categoria, GASTO_CATEGORIAS[$tipo] ?? [])) {
            $erros[] = 'Selecione a categoria.';
        } elseif ($valor <= 0) {
            $erros[] = 'Informe um valor válido.';
        } elseif (!DateTime::createFromFormat('Y-m-d', $dataGasto)) {
            $erros[] = 'Informe uma data válida.';
        } else {
            criar_gasto([
                'tipo'        => $tipo,
                'categoria'   => $categoria,
                'turma_slug'  => $tipo === 'curso' ? ($_POST['turma_slug'] ?? '') : null,
                'descricao'   => trim($_POST['descricao'] ?? ''),
                'valor'       => $valor,
                'data_gasto'  => $dataGasto,
                'criado_por'  => $staff['id'],
            ]);
            $mensagens[] = 'Gasto lançado.';
        }
    }
}

$gastos = listar_gastos();
$totais = total_gastos_por_tipo();
$totalGeral = array_sum($totais);
$turmas = load_turmas();

equipe_header_html('Gastos', $staff['nome']);
?>

<?php foreach ($mensagens as $m): ?>
    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;"><?= e($m) ?></div>
<?php endforeach; ?>
<?php if ($erros): ?>
    <div class="form-errors-box"><ul><?php foreach ($erros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="equipe-card">
    <h3>Resumo</h3>
    <table class="equipe-table">
        <thead><tr><th>Tipo</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach (GASTO_TIPOS as $chave => $label): ?>
            <tr><td><?= e($label) ?></td><td><?= e(format_price($totais[$chave] ?? 0)) ?></td></tr>
        <?php endforeach; ?>
        <tr><td><strong>Total geral</strong></td><td><strong><?= e(format_price($totalGeral)) ?></strong></td></tr>
        </tbody>
    </table>
</div>

<div class="equipe-card">
    <h3>Lançar gasto</h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="criar_gasto" />
        <div class="form-row">
            <div class="form-field">
                <label>Tipo</label>
                <select name="tipo" id="gastoTipo" required>
                    <?php foreach (GASTO_TIPOS as $chave => $label): ?>
                        <option value="<?= e($chave) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label>Categoria</label>
                <select name="categoria" id="gastoCategoria" required></select>
            </div>
        </div>
        <div class="form-field" id="gastoTurmaWrap">
            <label>Curso relacionado</label>
            <select name="turma_slug">
                <option value="">--</option>
                <?php foreach ($turmas as $t): ?>
                    <option value="<?= e($t['slug']) ?>"><?= e($t['curso']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <div class="form-field"><label>Valor (R$)</label><input type="text" name="valor" placeholder="150,00" required /></div>
            <div class="form-field"><label>Data</label><input type="date" name="data_gasto" value="<?= e(date('Y-m-d')) ?>" required /></div>
        </div>
        <div class="form-field"><label>Descrição</label><input type="text" name="descricao" /></div>
        <button type="submit" class="btn btn-primary">Lançar gasto</button>
    </form>
</div>

<div class="equipe-card">
    <h3>Últimos gastos (<?= count($gastos) ?>)</h3>
    <table class="equipe-table">
        <thead><tr><th>Data</th><th>Tipo</th><th>Categoria</th><th>Descrição</th><th>Valor</th></tr></thead>
        <tbody>
        <?php foreach ($gastos as $g): ?>
            <tr>
                <td><?= e(date('d/m/Y', strtotime($g['data_gasto']))) ?></td>
                <td><?= e(GASTO_TIPOS[$g['tipo']] ?? $g['tipo']) ?></td>
                <td><?= e(GASTO_CATEGORIAS[$g['tipo']][$g['categoria']] ?? $g['categoria']) ?></td>
                <td><?= e($g['descricao'] ?? '—') ?><?= $g['turma_slug'] ? ' · ' . e($g['turma_slug']) : '' ?></td>
                <td><?= e(format_price((float) $g['valor'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$gastos): ?><tr><td colspan="5" class="muted">Nenhum gasto lançado ainda.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<script>
  var categorias = <?= json_encode(GASTO_CATEGORIAS, JSON_UNESCAPED_UNICODE) ?>;
  var tipoSel = document.getElementById('gastoTipo');
  var catSel = document.getElementById('gastoCategoria');
  var turmaWrap = document.getElementById('gastoTurmaWrap');
  function atualizarCategorias() {
    catSel.innerHTML = '';
    var opcoes = categorias[tipoSel.value] || {};
    Object.keys(opcoes).forEach(function (chave) {
      var opt = document.createElement('option');
      opt.value = chave; opt.textContent = opcoes[chave];
      catSel.appendChild(opt);
    });
    turmaWrap.style.display = tipoSel.value === 'curso' ? '' : 'none';
  }
  tipoSel.addEventListener('change', atualizarCategorias);
  atualizarCategorias();
</script>

<?php equipe_footer_html(); ?>
