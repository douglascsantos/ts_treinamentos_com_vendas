<?php
/**
 * Imagens de destaque da seção "Agenda" da home: lista primeiro, "+" revela
 * o upload, clicar numa imagem edita a legenda (Salvar/Cancelar),
 * "Desabilitar" no lugar de excluir — nunca apagamos, só inativamos (ver
 * includes/agenda-imagens.php). Até 3 imagens ativas ao mesmo tempo; sem
 * nenhuma ativa, a home mostra um aviso "Em atualização" (ver index.php).
 */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/administradores.php';
require __DIR__ . '/../includes/agenda-imagens.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['administrador']);

$mensagens = [];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_imagem') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $id = $_POST['id'] ?? '';
        $editando = $id !== '' ? find_agenda_imagem($id) : null;
        $legenda = trim($_POST['legenda'] ?? '');

        $ativasAgora = array_filter(load_agenda_imagens(), fn ($i) => agenda_imagem_ativa($i) && ($i['id'] ?? '') !== $id);
        if (!$editando && count($ativasAgora) >= AGENDA_IMAGENS_MAX_ATIVAS) {
            $erros[] = 'Já existem ' . AGENDA_IMAGENS_MAX_ATIVAS . ' imagens ativas — desabilite uma antes de adicionar outra.';
        } elseif ($legenda === '') {
            $erros[] = 'Informe a legenda da imagem.';
        } elseif (!$editando && empty($_FILES['imagem']['name'])) {
            $erros[] = 'Envie a imagem.';
        }

        if (!$erros && $editando) {
            $arquivoNome = $editando['arquivo'];
            if (!empty($_FILES['imagem']['name'])) {
                $info = @getimagesize($_FILES['imagem']['tmp_name'] ?? '');
                if ($info && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                    $arquivoNome = $editando['id'] . '.jpg';
                    @move_uploaded_file($_FILES['imagem']['tmp_name'], __DIR__ . '/../assets/images/agenda-destaques/' . $arquivoNome);
                }
            }
            atualizar_agenda_imagem($editando['id'], ['legenda' => $legenda, 'arquivo' => $arquivoNome]);
            $mensagens[] = 'Imagem atualizada.';
        } elseif (!$erros) {
            $info = @getimagesize($_FILES['imagem']['tmp_name'] ?? '');
            if (!$info || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
                $erros[] = 'Envie uma imagem válida (JPG, PNG ou WEBP).';
            } else {
                $novoId = 'AG' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
                $arquivoNome = $novoId . '.jpg';
                @mkdir(__DIR__ . '/../assets/images/agenda-destaques', 0755, true);
                @move_uploaded_file($_FILES['imagem']['tmp_name'], __DIR__ . '/../assets/images/agenda-destaques/' . $arquivoNome);
                criar_agenda_imagem([
                    'id' => $novoId, 'arquivo' => $arquivoNome, 'legenda' => $legenda,
                    'ativo' => true, 'criado_em' => date('Y-m-d H:i:s'),
                ]);
                $mensagens[] = 'Imagem adicionada.';
            }
        }

        if (!$erros) {
            header('Location: agenda.php');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['desativar_imagem', 'ativar_imagem'], true)) {
    if (csrf_verify($_POST['csrf_token'] ?? null)) {
        if ($_POST['acao'] === 'ativar_imagem') {
            $ativasAgora = array_filter(load_agenda_imagens(), 'agenda_imagem_ativa');
            if (count($ativasAgora) >= AGENDA_IMAGENS_MAX_ATIVAS) {
                $erros[] = 'Já existem ' . AGENDA_IMAGENS_MAX_ATIVAS . ' imagens ativas — desabilite uma antes de reativar essa.';
            } else {
                atualizar_agenda_imagem($_POST['id'] ?? '', ['ativo' => true]);
            }
        } else {
            atualizar_agenda_imagem($_POST['id'] ?? '', ['ativo' => false]);
        }
    }
    if (!$erros) {
        header('Location: agenda.php');
        exit;
    }
}

$editando = null;
if (isset($_GET['editar'])) {
    $editando = find_agenda_imagem($_GET['editar']);
}
$valores = $editando ? ['id' => $editando['id'], 'legenda' => $editando['legenda']] : ['id' => '', 'legenda' => ''];
if ($erros && isset($_POST['legenda'])) {
    $valores = ['id' => $_POST['id'] ?? '', 'legenda' => $_POST['legenda']];
}
$formAberto = $editando !== null || (bool) $erros;

$imagens = ordenar_ativos_primeiro(load_agenda_imagens(), 'agenda_imagem_ativa');
$totalAtivas = count(array_filter($imagens, 'agenda_imagem_ativa'));

equipe_header_html('Imagens da agenda', $staff['nome']);
?>

<?php foreach ($mensagens as $m): ?>
    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;"><?= e($m) ?></div>
<?php endforeach; ?>
<?php if ($erros): ?>
    <div class="form-errors-box"><ul><?php foreach ($erros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="equipe-card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;">
        <h3 style="margin:0;">Imagens (<?= $totalAtivas ?>/<?= AGENDA_IMAGENS_MAX_ATIVAS ?> ativas)</h3>
        <?php if (!$editando && $totalAtivas < AGENDA_IMAGENS_MAX_ATIVAS): ?>
            <button type="button" class="btn-add-toggle" data-toggle-target="formImagem" data-toggle-close-text="Cancelar">+ Nova imagem</button>
        <?php endif; ?>
    </div>
    <p class="muted" style="margin-top:.5rem;">Até <?= AGENDA_IMAGENS_MAX_ATIVAS ?> imagens ativas ao mesmo tempo, mostradas uma abaixo da outra na home. Sem nenhuma ativa, a home mostra um aviso de "Em atualização" pro visitante não ficar perdido.</p>

    <?php if (!$imagens): ?>
        <p class="equipe-empty">Nenhuma imagem cadastrada ainda.</p>
    <?php else: ?>
        <div class="equipe-list">
            <?php foreach ($imagens as $img): ?>
                <?php $ativa = agenda_imagem_ativa($img); ?>
                <div class="equipe-list-item">
                    <a class="equipe-list-link" href="agenda.php?editar=<?= e($img['id']) ?>">
                        <?= e($img['legenda']) ?>
                        <span class="equipe-list-meta"><?= e($img['arquivo']) ?></span>
                    </a>
                    <div class="equipe-list-actions">
                        <span class="<?= $ativa ? 'badge-ativo' : 'badge-inativo' ?>"><?= $ativa ? 'Ativa' : 'Inativa' ?></span>
                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="<?= $ativa ? 'desativar_imagem' : 'ativar_imagem' ?>" />
                            <input type="hidden" name="id" value="<?= e($img['id']) ?>" />
                            <button type="submit" class="<?= $ativa ? 'btn-disable' : 'btn-enable' ?>"><?= $ativa ? 'Desabilitar' : 'Reativar' ?></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="toggle-form" id="formImagem" <?= $formAberto ? '' : 'hidden' ?>>
        <h4 style="margin-bottom:1rem;"><?= $editando ? 'Editar imagem' : 'Nova imagem' ?></h4>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="acao" value="salvar_imagem" />
            <input type="hidden" name="id" value="<?= e($valores['id']) ?>" />
            <div class="form-field">
                <label>Imagem<?= $editando ? ' (deixe em branco pra manter a atual)' : '' ?></label>
                <input type="file" name="imagem" accept="image/*" <?= $editando ? '' : 'required' ?> />
            </div>
            <div class="form-field"><label>Legenda (aparece embaixo da imagem na home)</label><input type="text" name="legenda" value="<?= e($valores['legenda']) ?>" placeholder="Visão geral da agenda de Agosto e Setembro" required /></div>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.5rem;">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <a class="btn btn-outline-dark" href="agenda.php">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php equipe_footer_html(); ?>
