<?php
/**
 * Gestão de produtos (cards, kits e e-books): lista primeiro, "+" revela o
 * cadastro, clicar num item edita (mesma tela, Salvar/Cancelar), "Desabilitar"
 * em vez de excluir — produto nunca é removido do banco, só some do site
 * público (ver produto_ativo() em includes/produtos.php). Continua escrevendo
 * em data/produtos.json — o mesmo arquivo que o site público e o agente
 * produtos-sync leem.
 */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/administradores.php';
require __DIR__ . '/../includes/produtos.php';
require __DIR__ . '/../includes/ebooks.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['administrador']);

$mensagens = [];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_produto') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $slugExistente = $_POST['slug_original'] ?? '';
        $editando = $slugExistente !== '' ? find_produto($slugExistente) : null;

        $nome = trim($_POST['nome'] ?? '');
        $tipo = $_POST['tipo'] ?? '';
        $preco = (float) str_replace(',', '.', $_POST['preco'] ?? '0');
        $estoque = (int) ($_POST['estoque'] ?? 0);
        $descricao = trim($_POST['descricao'] ?? '');
        $digital = produto_e_digital($tipo);

        if ($nome === '') {
            $erros[] = 'Informe o nome do produto.';
        } elseif (!array_key_exists($tipo, PRODUTO_TIPO_LABELS)) {
            $erros[] = 'Selecione o tipo do produto.';
        } elseif ($preco <= 0) {
            $erros[] = 'Informe um preço válido.';
        } elseif (!$digital && $estoque < 0) {
            $erros[] = 'Informe um estoque válido.';
        }

        if (!$erros && $editando) {
            $imagemNome = $editando['imagem'] ?? null;
            if (!empty($_FILES['imagem']['name'])) {
                $info = @getimagesize($_FILES['imagem']['tmp_name'] ?? '');
                if ($info && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                    $imagemNome = $editando['slug'] . '.jpg';
                    @move_uploaded_file($_FILES['imagem']['tmp_name'], __DIR__ . '/../assets/images/produtos/' . $imagemNome);
                }
            }
            $arquivoEbook = $editando['arquivo_ebook'] ?? null;
            if ($digital && !empty($_FILES['ebook']['name'])) {
                $novoArquivo = salvar_ebook_pdf($editando['slug'], $_FILES['ebook']);
                if ($novoArquivo) {
                    $arquivoEbook = $novoArquivo;
                } else {
                    $erros[] = 'Não foi possível salvar o PDF do e-book (envie um PDF válido, até 30MB).';
                }
            }
            if (!$erros) {
                atualizar_produto($editando['slug'], [
                    'nome' => $nome, 'tipo' => $tipo, 'preco' => $preco,
                    'estoque' => $digital ? 9999 : $estoque, 'imagem' => $imagemNome,
                    'descricao' => $descricao, 'arquivo_ebook' => $digital ? $arquivoEbook : null,
                ]);
                $mensagens[] = 'Produto atualizado.';
            }
        } elseif (!$erros) {
            $slug = slugify($nome);
            if (find_produto($slug)) {
                $erros[] = 'Já existe um produto com esse nome (slug "' . $slug . '").';
            } elseif (empty($_FILES['imagem']['name'])) {
                $erros[] = 'Envie a imagem de marketing do produto.';
            } else {
                $info = @getimagesize($_FILES['imagem']['tmp_name'] ?? '');
                if (!$info || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
                    $erros[] = 'Envie uma imagem válida (JPG, PNG ou WEBP).';
                } else {
                    $imagemNome = $slug . '.jpg';
                    @move_uploaded_file($_FILES['imagem']['tmp_name'], __DIR__ . '/../assets/images/produtos/' . $imagemNome);

                    $arquivoEbook = null;
                    if ($digital && !empty($_FILES['ebook']['name'])) {
                        $arquivoEbook = salvar_ebook_pdf($slug, $_FILES['ebook']);
                        if (!$arquivoEbook) {
                            $erros[] = 'Não foi possível salvar o PDF do e-book (envie um PDF válido, até 30MB).';
                        }
                    }

                    if (!$erros) {
                        criar_produto([
                            'slug' => $slug, 'nome' => $nome, 'tipo' => $tipo, 'preco' => $preco,
                            'estoque' => $digital ? 9999 : $estoque, 'imagem' => $imagemNome,
                            'descricao' => $descricao, 'arquivo_ebook' => $arquivoEbook, 'ativo' => true,
                        ]);
                        $paginaTemplate = "<?php\nrequire __DIR__ . '/../includes/functions.php';\n"
                            . "require __DIR__ . '/../includes/produtos.php';\nrequire __DIR__ . '/../includes/produto-page.php';\n"
                            . "render_produto_page('{$slug}');\n";
                        file_put_contents(__DIR__ . '/../produtos/' . $slug . '.php', $paginaTemplate);
                        $mensagens[] = 'Produto "' . $nome . '" criado.';
                    }
                }
            }
        }

        if (!$erros) {
            header('Location: produtos.php');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['desativar_produto', 'ativar_produto'], true)) {
    if (csrf_verify($_POST['csrf_token'] ?? null)) {
        atualizar_produto($_POST['slug'] ?? '', ['ativo' => $_POST['acao'] === 'ativar_produto']);
    }
    header('Location: produtos.php');
    exit;
}

$editando = null;
if (isset($_GET['editar'])) {
    $editando = find_produto($_GET['editar']);
}

if ($editando) {
    $valores = [
        'slug_original' => $editando['slug'], 'nome' => $editando['nome'], 'tipo' => $editando['tipo'],
        'preco' => number_format((float) $editando['preco'], 2, ',', ''), 'estoque' => $editando['estoque'] ?? 0,
        'descricao' => $editando['descricao'] ?? '',
    ];
} elseif ($erros && isset($_POST['nome'])) {
    $valores = [
        'slug_original' => $_POST['slug_original'] ?? '', 'nome' => $_POST['nome'], 'tipo' => $_POST['tipo'] ?? '',
        'preco' => $_POST['preco'] ?? '', 'estoque' => $_POST['estoque'] ?? 0, 'descricao' => $_POST['descricao'] ?? '',
    ];
} else {
    $valores = ['slug_original' => '', 'nome' => '', 'tipo' => '', 'preco' => '', 'estoque' => 0, 'descricao' => ''];
}
$formAberto = $editando !== null || (bool) $erros;

$produtos = ordenar_ativos_primeiro(load_produtos(), 'produto_ativo');

equipe_header_html('Produtos', $staff['nome']);
?>

<?php foreach ($mensagens as $m): ?>
    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;"><?= e($m) ?></div>
<?php endforeach; ?>
<?php if ($erros): ?>
    <div class="form-errors-box"><ul><?php foreach ($erros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="equipe-card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;">
        <h3 style="margin:0;">Produtos (<?= count($produtos) ?>)</h3>
        <?php if (!$editando): ?>
            <button type="button" class="btn-add-toggle" data-toggle-target="formProduto" data-toggle-close-text="Cancelar">+ Novo produto</button>
        <?php endif; ?>
    </div>

    <?php if (!$produtos): ?>
        <p class="equipe-empty">Nenhum produto cadastrado ainda.</p>
    <?php else: ?>
        <div class="equipe-list">
            <?php foreach ($produtos as $p): ?>
                <?php $ativo = produto_ativo($p); ?>
                <div class="equipe-list-item">
                    <a class="equipe-list-link" href="produtos.php?editar=<?= e($p['slug']) ?>">
                        <?= e($p['nome']) ?>
                        <span class="equipe-list-meta">
                            <?= e(produto_tipo_label($p['tipo'])) ?> · <?= e(format_price((float) $p['preco'])) ?>
                            <?php if (produto_e_digital($p['tipo'])): ?> · <?= !empty($p['arquivo_ebook']) ? 'PDF enviado' : 'sem PDF ainda' ?>
                            <?php else: ?> · <?= (int) $p['estoque'] ?> em estoque<?php endif; ?>
                        </span>
                    </a>
                    <div class="equipe-list-actions">
                        <span class="<?= $ativo ? 'badge-ativo' : 'badge-inativo' ?>"><?= $ativo ? 'Ativo' : 'Inativo' ?></span>
                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="<?= $ativo ? 'desativar_produto' : 'ativar_produto' ?>" />
                            <input type="hidden" name="slug" value="<?= e($p['slug']) ?>" />
                            <button type="submit" class="<?= $ativo ? 'btn-disable' : 'btn-enable' ?>"><?= $ativo ? 'Desabilitar' : 'Reativar' ?></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="toggle-form" id="formProduto" <?= $formAberto ? '' : 'hidden' ?>>
        <h4 style="margin-bottom:1rem;"><?= $editando ? 'Editar produto' : 'Novo produto' ?></h4>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="acao" value="salvar_produto" />
            <input type="hidden" name="slug_original" value="<?= e($valores['slug_original']) ?>" />
            <div class="equipe-form-row">
                <div class="form-field"><label>Nome</label><input type="text" name="nome" value="<?= e($valores['nome']) ?>" required /></div>
                <div class="form-field">
                    <label>Tipo</label>
                    <select name="tipo" id="produtoTipo" required>
                        <option value="">Selecione</option>
                        <?php foreach (PRODUTO_TIPO_LABELS as $chave => $label): ?>
                            <option value="<?= e($chave) ?>" <?= $valores['tipo'] === $chave ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="equipe-form-row">
                <div class="form-field"><label>Preço (R$)</label><input type="text" name="preco" value="<?= e((string) $valores['preco']) ?>" placeholder="89,90" required /></div>
                <div class="form-field" id="produtoEstoqueWrap"><label>Estoque</label><input type="number" name="estoque" min="0" value="<?= e((string) $valores['estoque']) ?>" /></div>
            </div>
            <div class="form-field"><label>Descrição</label><input type="text" name="descricao" value="<?= e($valores['descricao']) ?>" /></div>
            <div class="form-field">
                <label>Imagem de marketing<?= $editando ? ' (deixe em branco pra manter a atual)' : '' ?></label>
                <input type="file" name="imagem" accept="image/*" <?= $editando ? '' : 'required' ?> />
                <p class="muted" style="margin-top:.4rem;font-size:.8rem;">
                    Medida recomendada: <strong>1200×900px</strong> (proporção 4:3) · formato <strong>JPG, PNG ou WEBP</strong> ·
                    até 2MB, foto bem iluminada e nítida, sem texto ilegível — essa é a imagem que aparece no carrossel e na página de venda.
                </p>
            </div>
            <div class="form-field" id="produtoEbookWrap">
                <label>PDF do e-book<?= $editando ? ' (deixe em branco pra manter o atual)' : ' (opcional agora, pode enviar depois editando)' ?></label>
                <input type="file" name="ebook" accept="application/pdf" />
                <p class="muted" style="margin-top:.4rem;font-size:.8rem;">PDF de verdade, até 30MB — é o arquivo que o cliente baixa depois de comprar.</p>
            </div>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.5rem;">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <a class="btn btn-outline-dark" href="produtos.php">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
  (function () {
    var tipoSel = document.getElementById('produtoTipo');
    var estoqueWrap = document.getElementById('produtoEstoqueWrap');
    var ebookWrap = document.getElementById('produtoEbookWrap');
    var TIPOS_DIGITAIS = ['book'];
    function sincronizar() {
      var digital = TIPOS_DIGITAIS.indexOf(tipoSel.value) !== -1;
      estoqueWrap.style.display = digital ? 'none' : '';
      ebookWrap.style.display = digital ? '' : 'none';
    }
    if (tipoSel) {
      tipoSel.addEventListener('change', sincronizar);
      sincronizar();
    }
  })();
</script>

<?php equipe_footer_html(); ?>
