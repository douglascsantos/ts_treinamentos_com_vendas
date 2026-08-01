<?php
/**
 * Administrativo/Diretor: mesmo padrão de instrutores.php — lista, "+" revela
 * o cadastro, clicar num item edita, "Desabilitar"/"Reativar" no lugar de
 * excluir. Ninguém consegue desabilitar a própria conta (evita se trancar
 * fora do painel sem querer).
 */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/administradores.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['administrador']);
if ($staff['nivel'] !== 'diretor') {
    header('Location: administrativo.php');
    exit;
}

$mensagens = [];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_administrador') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $id = $_POST['id'] ?? '';
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $nivel = $_POST['nivel'] ?? 'administrativo';
        $senha = (string) ($_POST['senha'] ?? '');

        try {
            $existente = $id !== '' ? find_administrador_by_id($id) : null;
            $emailDuplicado = filter_var($email, FILTER_VALIDATE_EMAIL) ? find_administrador_by_email($email) : null;

            if ($nome === '') {
                $erros[] = 'Informe o nome.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $erros[] = 'E-mail inválido.';
            } elseif ($emailDuplicado && (!$existente || $emailDuplicado['id'] !== $existente['id'])) {
                $erros[] = 'Já existe uma conta cadastrada com esse e-mail.';
            } elseif (!in_array($nivel, ['administrativo', 'diretor'], true)) {
                $erros[] = 'Nível inválido.';
            } elseif (!$existente && strlen($senha) < 8) {
                $erros[] = 'Senha precisa ter pelo menos 8 caracteres.';
            }

            if (!$erros) {
                if ($id !== '' && $existente) {
                    atualizar_administrador($id, ['nome' => $nome, 'email' => mb_strtolower($email), 'nivel' => $nivel]);
                    $mensagens[] = 'Conta atualizada.';
                } else {
                    criar_administrador(['nome' => $nome, 'email' => $email, 'senha' => $senha, 'nivel' => $nivel]);
                    $mensagens[] = 'Conta cadastrada.';
                }
            }
        } catch (Throwable $e) {
            $erros[] = equipe_erro_tecnico($e);
        }

        if (!$erros) {
            header('Location: administradores.php');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['desativar_administrador', 'ativar_administrador'], true)) {
    if (csrf_verify($_POST['csrf_token'] ?? null)) {
        $idAlvo = $_POST['id'] ?? '';
        if ($idAlvo === $staff['id']) {
            $erros[] = 'Você não pode desabilitar a própria conta.';
        } else {
            atualizar_administrador($idAlvo, ['ativo' => ($_POST['acao'] === 'ativar_administrador') ? 1 : 0]);
        }
    }
    if (!$erros) {
        header('Location: administradores.php');
        exit;
    }
}

$editando = null;
$erroBanco = null;
try {
    if (isset($_GET['editar'])) {
        $editando = find_administrador_by_id($_GET['editar']);
    }
    $administradores = listar_administradores();
} catch (Throwable $e) {
    $erroBanco = equipe_erro_tecnico($e);
    $administradores = [];
}

$valores = $editando ?: ['id' => '', 'nome' => '', 'email' => '', 'nivel' => 'administrativo'];
if ($erros && isset($_POST['nome']) && ($_POST['acao'] ?? '') === 'salvar_administrador') {
    $valores = ['id' => $_POST['id'] ?? '', 'nome' => $_POST['nome'], 'email' => $_POST['email'] ?? '', 'nivel' => $_POST['nivel'] ?? 'administrativo'];
}
$formAberto = $editando !== null || (($_POST['acao'] ?? '') === 'salvar_administrador' && $erros);

equipe_header_html('Administradores', $staff['nome']);
?>

<p style="margin-bottom:1.25rem;"><a href="diretor.php">← Painel do diretor</a></p>

<?php if ($erroBanco): ?>
    <div class="form-errors-box">Não foi possível conectar ao banco agora. <?= e($erroBanco) ?></div>
<?php endif; ?>

<?php foreach ($mensagens as $m): ?>
    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;"><?= e($m) ?></div>
<?php endforeach; ?>
<?php if ($erros): ?>
    <div class="form-errors-box"><ul><?php foreach ($erros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="equipe-card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;">
        <h3 style="margin:0;">Administrativo/Diretor (<?= count($administradores) ?>)</h3>
        <?php if (!$editando): ?>
            <button type="button" class="btn-add-toggle" data-toggle-target="formAdmin" data-toggle-close-text="Cancelar">+ Nova conta</button>
        <?php endif; ?>
    </div>

    <div class="equipe-list">
        <?php foreach ($administradores as $a): ?>
            <div class="equipe-list-item">
                <a class="equipe-list-link" href="administradores.php?editar=<?= e($a['id']) ?>">
                    <?= e($a['nome']) ?>
                    <span class="equipe-list-meta"><?= e(ucfirst($a['nivel'])) ?> · <?= e($a['email']) ?><?= $a['id'] === $staff['id'] ? ' · (você)' : '' ?></span>
                </a>
                <div class="equipe-list-actions">
                    <span class="<?= (int) $a['ativo'] === 1 ? 'badge-ativo' : 'badge-inativo' ?>"><?= (int) $a['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span>
                    <?php if ($a['id'] !== $staff['id']): ?>
                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="<?= (int) $a['ativo'] === 1 ? 'desativar_administrador' : 'ativar_administrador' ?>" />
                            <input type="hidden" name="id" value="<?= e($a['id']) ?>" />
                            <button type="submit" class="<?= (int) $a['ativo'] === 1 ? 'btn-disable' : 'btn-enable' ?>"><?= (int) $a['ativo'] === 1 ? 'Desabilitar' : 'Reativar' ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="toggle-form" id="formAdmin" <?= $formAberto ? '' : 'hidden' ?>>
        <h4 style="margin-bottom:1rem;"><?= $editando ? 'Editar conta' : 'Nova conta' ?></h4>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="acao" value="salvar_administrador" />
            <input type="hidden" name="id" value="<?= e($valores['id']) ?>" />
            <div class="equipe-form-row">
                <div class="form-field"><label>Nome</label><input type="text" name="nome" value="<?= e($valores['nome']) ?>" required /></div>
                <div class="form-field"><label>E-mail</label><input type="email" name="email" value="<?= e($valores['email']) ?>" required /></div>
            </div>
            <div class="equipe-form-row">
                <?php if (!$editando): ?>
                    <div class="form-field"><label>Senha</label><input type="password" name="senha" minlength="8" required /></div>
                <?php endif; ?>
                <div class="form-field">
                    <label>Nível</label>
                    <select name="nivel" required>
                        <option value="administrativo" <?= $valores['nivel'] === 'administrativo' ? 'selected' : '' ?>>Administrativo</option>
                        <option value="diretor" <?= $valores['nivel'] === 'diretor' ? 'selected' : '' ?>>Diretor</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.5rem;">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <a class="btn btn-outline-dark" href="administradores.php">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php equipe_footer_html(); ?>
