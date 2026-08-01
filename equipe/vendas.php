<?php
/**
 * Vendas de produtos: lista todo pedido de produto (card/kit/e-book), com
 * status do pagamento e status do envio editáveis pelo administrativo/
 * diretor. O que for salvo aqui aparece na hora pro aluno em minha-conta.php
 * (mesmo registro em data do pedido, só uma tela de leitura/escrita
 * diferente — nunca duplica o dado). Pagamento normalmente vem sozinho do
 * webhook da InfinitePay (ver webhook-infinitepay.php); a edição manual aqui
 * é pra correção pontual (ex.: pagamento combinado fora do gateway).
 * Produto digital (e-book) não tem envio — a entrega é o próprio download.
 */
require __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/storage.php';
require __DIR__ . '/../includes/alunos.php';
require __DIR__ . '/../includes/pedidos.php';
require __DIR__ . '/../includes/produtos.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['administrador']);

$mensagens = [];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'atualizar_status_pedido') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $orderNsu = $_POST['order_nsu'] ?? '';
        $novoStatus = $_POST['status'] ?? '';
        $pedido = find_pedido_by_nsu($orderNsu);

        if (!$pedido || $pedido['tipo'] !== 'produto') {
            $erros[] = 'Pedido não encontrado.';
        } elseif (!array_key_exists($novoStatus, PEDIDO_STATUS_LABELS)) {
            $erros[] = 'Status de pagamento inválido.';
        } else {
            $produtoRef = find_produto($pedido['slug']);
            $digital = $produtoRef && produto_e_digital($produtoRef['tipo']);
            $mudancas = ['status' => $novoStatus];

            if (!$digital) {
                $novoStatusEntrega = $_POST['status_entrega'] ?? '';
                if (!array_key_exists($novoStatusEntrega, STATUS_ENTREGA_LABELS)) {
                    $erros[] = 'Status de envio inválido.';
                } else {
                    $mudancas['status_entrega'] = $novoStatusEntrega;
                }
            }

            if (!$erros) {
                try {
                    atualizar_pedido($orderNsu, $mudancas);
                    $mensagens[] = 'Status do pedido ' . $orderNsu . ' atualizado.';
                } catch (Throwable $e) {
                    $erros[] = equipe_erro_tecnico($e);
                }
            }
        }

        if (!$erros) {
            header('Location: vendas.php');
            exit;
        }
    }
}

$pedidos = listar_pedidos_de_produtos();

equipe_header_html('Vendas de Produtos', $staff['nome']);
?>

<?php if ($staff['nivel'] === 'diretor'): ?><p style="margin-bottom:1.25rem;"><a href="diretor.php">← Painel do diretor</a></p><?php endif; ?>

<?php foreach ($mensagens as $m): ?>
    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;"><?= e($m) ?></div>
<?php endforeach; ?>
<?php if ($erros): ?>
    <div class="form-errors-box"><ul><?php foreach ($erros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="equipe-card">
    <h3 style="margin-bottom:.25rem;">Pedidos de produtos (<?= count($pedidos) ?>)</h3>
    <p class="muted" style="margin-bottom:1rem;">Status do pagamento normalmente vem sozinho do gateway — só corrija na mão em caso combinado fora do site. Status de envio é você quem controla.</p>

    <?php if (!$pedidos): ?>
        <p class="equipe-empty">Nenhum pedido de produto ainda.</p>
    <?php else: ?>
    <div class="equipe-table-wrap">
    <table class="equipe-table">
        <thead>
            <tr><th>Aluno</th><th>Produto</th><th>Pedido</th><th>Data</th><th>Preço</th><th>Pagamento / Envio</th></tr>
        </thead>
        <tbody>
        <?php foreach ($pedidos as $p):
            $aluno = !empty($p['aluno_id']) ? find_aluno_by_id($p['aluno_id']) : null;
            $produtoRef = find_produto($p['slug']);
            $digital = $produtoRef && produto_e_digital($produtoRef['tipo']);
        ?>
            <tr>
                <td><?= $aluno ? e(trim(($aluno['pronome'] ?? '') . ' ' . $aluno['nome'])) : '—' ?><?php if ($aluno): ?><br /><span class="muted" style="font-size:.78rem;"><?= e($aluno['email']) ?></span><?php endif; ?></td>
                <td><?= e($p['descricao']) ?></td>
                <td><code><?= e($p['order_nsu']) ?></code></td>
                <td><?= e(date('d/m/Y', strtotime($p['criado_em']))) ?></td>
                <td><?= e(format_price((float) $p['preco'])) ?></td>
                <td>
                    <form method="post" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="acao" value="atualizar_status_pedido" />
                        <input type="hidden" name="order_nsu" value="<?= e($p['order_nsu']) ?>" />
                        <select name="status" style="min-height:36px;padding:.3rem .5rem;border-radius:.4rem;border:1px solid var(--border);font-size:.82rem;">
                            <?php foreach (PEDIDO_STATUS_LABELS as $chave => $info): ?>
                                <option value="<?= e($chave) ?>" <?= $p['status'] === $chave ? 'selected' : '' ?>><?= e($info[0]) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($digital): ?>
                            <span class="muted" style="font-size:.8rem;">Entrega digital (PDF)</span>
                        <?php else: ?>
                            <select name="status_entrega" style="min-height:36px;padding:.3rem .5rem;border-radius:.4rem;border:1px solid var(--border);font-size:.82rem;">
                                <?php foreach (STATUS_ENTREGA_LABELS as $chave => $info): ?>
                                    <option value="<?= e($chave) ?>" <?= ($p['status_entrega'] ?? 'a_caminho') === $chave ? 'selected' : '' ?>><?= e($info[0]) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <button type="submit" class="btn-enable">Salvar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php equipe_footer_html(); ?>
