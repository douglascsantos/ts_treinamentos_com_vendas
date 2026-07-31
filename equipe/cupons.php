<?php
/**
 * Gerar cupom de desconto: escolhe um curso/produto específico, valor (R$ ou
 * %) e prazo em dias. Sistema gera um código único — o link pra mandar pro
 * cliente é a própria página do curso/produto com "?cupom=CODIGO" (ver
 * includes/curso-page.php / includes/produto-page.php e checkout.php).
 */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/administradores.php';
require __DIR__ . '/../includes/turmas.php';
require __DIR__ . '/../includes/produtos.php';
require __DIR__ . '/../includes/cupons.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['administrador']);

$mensagens = [];
$erros = [];
$linkGerado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'criar_cupom') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $tipoItem = $_POST['tipo_item'] ?? '';
        $itemSlug = $_POST['item_slug'] ?? '';
        $tipoDesconto = $_POST['tipo_desconto'] ?? '';
        $valorDesconto = (float) str_replace(',', '.', $_POST['valor_desconto'] ?? '0');
        $validadeDias = (int) ($_POST['validade_dias'] ?? 0);

        if (!in_array($tipoItem, ['curso', 'produto'], true)) {
            $erros[] = 'Selecione curso ou produto.';
        } elseif ($itemSlug === '' || ($tipoItem === 'curso' ? !find_turma($itemSlug) : !find_produto($itemSlug))) {
            $erros[] = 'Selecione um item válido.';
        } elseif (!in_array($tipoDesconto, ['percentual', 'fixo'], true)) {
            $erros[] = 'Selecione o tipo de desconto.';
        } elseif ($valorDesconto <= 0 || ($tipoDesconto === 'percentual' && $valorDesconto > 100)) {
            $erros[] = 'Valor de desconto inválido.';
        } elseif ($validadeDias <= 0) {
            $erros[] = 'Informe um prazo de validade em dias.';
        } else {
            $cupom = criar_cupom([
                'tipo_item'      => $tipoItem,
                'item_slug'      => $itemSlug,
                'tipo_desconto'  => $tipoDesconto,
                'valor_desconto' => $valorDesconto,
                'validade_dias'  => $validadeDias,
                'criado_por'     => $staff['id'],
            ]);
            $paginaItem = $tipoItem === 'curso' ? "cursos/{$itemSlug}.php" : "produtos/{$itemSlug}.php";
            $linkGerado = site_base_url() . '/' . $paginaItem . '?cupom=' . $cupom['codigo'];
            $mensagens[] = 'Cupom "' . $cupom['codigo'] . '" criado.';
        }
    }
}

$cupons = listar_cupons();
$turmas = load_turmas();
$produtos = load_produtos();

equipe_header_html('Cupons de Desconto', $staff['nome']);
?>

<?php foreach ($mensagens as $m): ?>
    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;"><?= e($m) ?></div>
<?php endforeach; ?>
<?php if ($linkGerado): ?>
    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;">
        Link pra mandar pro cliente: <code><?= e($linkGerado) ?></code>
    </div>
<?php endif; ?>
<?php if ($erros): ?>
    <div class="form-errors-box"><ul><?php foreach ($erros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="equipe-card">
    <h3>Gerar cupom</h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="criar_cupom" />
        <div class="form-row">
            <div class="form-field">
                <label>Tipo</label>
                <select name="tipo_item" id="cupomTipo" required>
                    <option value="curso">Curso</option>
                    <option value="produto">Produto</option>
                </select>
            </div>
            <div class="form-field">
                <label>Item</label>
                <select name="item_slug" required>
                    <optgroup label="Cursos">
                        <?php foreach ($turmas as $t): ?>
                            <option value="<?= e($t['slug']) ?>" data-tipo="curso"><?= e($t['curso']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Produtos">
                        <?php foreach ($produtos as $p): ?>
                            <option value="<?= e($p['slug']) ?>" data-tipo="produto"><?= e($p['nome']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label>Tipo de desconto</label>
                <select name="tipo_desconto" required>
                    <option value="percentual">Percentual (%)</option>
                    <option value="fixo">Valor fixo (R$)</option>
                </select>
            </div>
            <div class="form-field"><label>Valor</label><input type="text" name="valor_desconto" placeholder="10" required /></div>
        </div>
        <div class="form-field"><label>Validade (dias)</label><input type="number" name="validade_dias" min="1" value="7" required /></div>
        <button type="submit" class="btn btn-primary">Gerar cupom</button>
    </form>
</div>

<div class="equipe-card">
    <h3>Cupons gerados (<?= count($cupons) ?>)</h3>
    <table class="equipe-table">
        <thead><tr><th>Código</th><th>Item</th><th>Desconto</th><th>Validade</th><th>Situação</th></tr></thead>
        <tbody>
        <?php foreach ($cupons as $c):
            $item = $c['tipo_item'] === 'curso' ? find_turma($c['item_slug']) : find_produto($c['item_slug']);
            $nomeItem = $item['curso'] ?? $item['nome'] ?? $c['item_slug'];
            $desconto = $c['tipo_desconto'] === 'percentual' ? $c['valor_desconto'] . '%' : format_price((float) $c['valor_desconto']);
            if ($c['usado_em']) {
                $situacao = 'Usado em ' . date('d/m/Y', strtotime($c['usado_em']));
            } elseif (strtotime($c['expira_em']) < time()) {
                $situacao = 'Expirado';
            } else {
                $situacao = 'Ativo até ' . date('d/m/Y H:i', strtotime($c['expira_em']));
            }
        ?>
            <tr>
                <td><code><?= e($c['codigo']) ?></code></td>
                <td><?= e($nomeItem) ?></td>
                <td><?= e($desconto) ?></td>
                <td><?= e(date('d/m/Y', strtotime($c['criado_em']))) ?></td>
                <td><?= e($situacao) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$cupons): ?><tr><td colspan="5" class="muted">Nenhum cupom gerado ainda.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<script>
  var tipoSel = document.getElementById('cupomTipo');
  var itemSel = document.querySelector('select[name="item_slug"]');
  function filtrarItens() {
    Array.prototype.forEach.call(itemSel.options, function (opt) {
      if (!opt.dataset.tipo) return;
      opt.hidden = opt.dataset.tipo !== tipoSel.value;
    });
  }
  if (tipoSel && itemSel) {
    tipoSel.addEventListener('change', filtrarItens);
    filtrarItens();
  }
</script>

<?php equipe_footer_html(); ?>
