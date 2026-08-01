<?php
/**
 * Instrutores: lista primeiro, botão "+" revela o formulário de cadastro,
 * clicar num item abre o mesmo formulário pra editar (Salvar/Cancelar),
 * "Desabilitar"/"Reativar" no lugar de excluir — nunca apagamos ninguém do
 * banco, só marcamos ativo=0 (ver includes/instrutores.php).
 */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/administradores.php';
require __DIR__ . '/../includes/instrutores.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['administrador']);
if ($staff['nivel'] !== 'diretor') {
    header('Location: administrativo.php');
    exit;
}

$mensagens = [];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_instrutor') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $id = $_POST['id'] ?? '';
        $d = [
            'nome_completo'   => trim($_POST['nome_completo'] ?? ''),
            'formacao'        => trim($_POST['formacao'] ?? ''),
            'cpf'             => $_POST['cpf'] ?? '',
            'area_atuacao'    => $_POST['area_atuacao'] ?? '',
            'numero_registro' => trim($_POST['numero_registro'] ?? ''),
            'email'           => trim($_POST['email'] ?? ''),
            'whatsapp'        => $_POST['whatsapp'] ?? '',
        ];
        $senha = (string) ($_POST['senha'] ?? '');

        try {
            $existente = $id !== '' ? find_instrutor_by_id($id) : null;
            $cpfDuplicado = validar_cpf($d['cpf']) ? find_instrutor_by_cpf($d['cpf']) : null;
            $emailDuplicado = filter_var($d['email'], FILTER_VALIDATE_EMAIL) ? find_instrutor_by_email($d['email']) : null;

            if ($d['nome_completo'] === '' || $d['formacao'] === '') {
                $erros[] = 'Preencha nome completo e formação.';
            } elseif (!validar_cpf($d['cpf'])) {
                $erros[] = 'CPF inválido.';
            } elseif ($cpfDuplicado && (!$existente || $cpfDuplicado['id'] !== $existente['id'])) {
                $erros[] = 'Já existe um instrutor cadastrado com esse CPF.';
            } elseif (!array_key_exists($d['area_atuacao'], AREAS_ATUACAO_INSTRUTOR)) {
                $erros[] = 'Selecione a área de atuação.';
            } elseif (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
                $erros[] = 'E-mail inválido.';
            } elseif ($emailDuplicado && (!$existente || $emailDuplicado['id'] !== $existente['id'])) {
                $erros[] = 'Já existe um instrutor cadastrado com esse e-mail.';
            } elseif (!$existente && strlen($senha) < 8) {
                $erros[] = 'Senha precisa ter pelo menos 8 caracteres.';
            }

            if (!$erros) {
                if ($id !== '' && $existente) {
                    atualizar_instrutor($id, [
                        'nome_completo'   => $d['nome_completo'],
                        'formacao'        => $d['formacao'],
                        'cpf'             => preg_replace('/\D/', '', $d['cpf']),
                        'area_atuacao'    => $d['area_atuacao'],
                        'numero_registro' => $d['numero_registro'] !== '' ? $d['numero_registro'] : null,
                        'email'           => mb_strtolower(trim($d['email'])),
                        'whatsapp'        => preg_replace('/\D/', '', $d['whatsapp']),
                    ]);
                    $mensagens[] = 'Instrutor atualizado.';
                } else {
                    criar_instrutor(array_merge($d, ['senha' => $senha]));
                    $mensagens[] = 'Instrutor cadastrado.';
                }
            }
        } catch (Throwable $e) {
            $erros[] = equipe_erro_tecnico($e);
        }

        if (!$erros) {
            header('Location: instrutores.php');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['desativar_instrutor', 'ativar_instrutor'], true)) {
    if (csrf_verify($_POST['csrf_token'] ?? null)) {
        atualizar_instrutor($_POST['id'] ?? '', ['ativo' => ($_POST['acao'] === 'ativar_instrutor') ? 1 : 0]);
    }
    header('Location: instrutores.php');
    exit;
}

$editando = null;
$erroBanco = null;
try {
    if (isset($_GET['editar'])) {
        $editando = find_instrutor_by_id($_GET['editar']);
    }
    $instrutores = listar_instrutores();
} catch (Throwable $e) {
    $erroBanco = equipe_erro_tecnico($e);
    $instrutores = [];
}

$valores = $editando ?: [
    'id' => '', 'nome_completo' => '', 'formacao' => '', 'cpf' => '', 'area_atuacao' => '',
    'numero_registro' => '', 'email' => '', 'whatsapp' => '',
];
if ($erros && isset($_POST['nome_completo'])) {
    // Reexibe o que a pessoa digitou se deu erro de validação.
    $valores = [
        'id' => $_POST['id'] ?? '', 'nome_completo' => $_POST['nome_completo'], 'formacao' => $_POST['formacao'] ?? '',
        'cpf' => $_POST['cpf'] ?? '', 'area_atuacao' => $_POST['area_atuacao'] ?? '',
        'numero_registro' => $_POST['numero_registro'] ?? '', 'email' => $_POST['email'] ?? '', 'whatsapp' => $_POST['whatsapp'] ?? '',
    ];
}
$formAberto = $editando !== null || (bool) $erros;

equipe_header_html('Instrutores', $staff['nome']);
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
        <h3 style="margin:0;">Instrutores (<?= count($instrutores) ?>)</h3>
        <?php if (!$editando): ?>
            <button type="button" class="btn-add-toggle" data-toggle-target="formInstrutor" data-toggle-close-text="Cancelar">+ Novo instrutor</button>
        <?php endif; ?>
    </div>

    <?php if (!$instrutores): ?>
        <p class="equipe-empty">Nenhum instrutor cadastrado ainda.</p>
    <?php else: ?>
        <div class="equipe-list">
            <?php foreach ($instrutores as $i): ?>
                <div class="equipe-list-item">
                    <a class="equipe-list-link" href="instrutores.php?editar=<?= e($i['id']) ?>">
                        <?= e($i['nome_completo']) ?>
                        <span class="equipe-list-meta"><?= e(AREAS_ATUACAO_INSTRUTOR[$i['area_atuacao']] ?? $i['area_atuacao']) ?> · <?= e($i['email']) ?></span>
                    </a>
                    <div class="equipe-list-actions">
                        <span class="<?= (int) $i['ativo'] === 1 ? 'badge-ativo' : 'badge-inativo' ?>"><?= (int) $i['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span>
                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="<?= (int) $i['ativo'] === 1 ? 'desativar_instrutor' : 'ativar_instrutor' ?>" />
                            <input type="hidden" name="id" value="<?= e($i['id']) ?>" />
                            <button type="submit" class="<?= (int) $i['ativo'] === 1 ? 'btn-disable' : 'btn-enable' ?>"><?= (int) $i['ativo'] === 1 ? 'Desabilitar' : 'Reativar' ?></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="toggle-form" id="formInstrutor" <?= $formAberto ? '' : 'hidden' ?>>
        <h4 style="margin-bottom:1rem;"><?= $editando ? 'Editar instrutor' : 'Novo instrutor' ?></h4>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="acao" value="salvar_instrutor" />
            <input type="hidden" name="id" value="<?= e($valores['id']) ?>" />
            <div class="equipe-form-row">
                <div class="form-field"><label>Nome completo</label><input type="text" name="nome_completo" value="<?= e($valores['nome_completo']) ?>" required /></div>
                <div class="form-field"><label>Formação</label><input type="text" name="formacao" value="<?= e($valores['formacao']) ?>" required /></div>
            </div>
            <div class="equipe-form-row">
                <div class="form-field"><label>CPF</label><input type="text" name="cpf" data-mask="cpf" value="<?= e($valores['cpf']) ?>" required /></div>
                <div class="form-field">
                    <label>Área de atuação</label>
                    <select name="area_atuacao" required>
                        <option value="">Selecione</option>
                        <?php foreach (AREAS_ATUACAO_INSTRUTOR as $chave => $label): ?>
                            <option value="<?= e($chave) ?>" <?= $valores['area_atuacao'] === $chave ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-field"><label>Número do conselho (COREN/CRM/CRBM...)</label><input type="text" name="numero_registro" value="<?= e($valores['numero_registro']) ?>" /></div>
            <div class="equipe-form-row">
                <div class="form-field"><label>E-mail</label><input type="email" name="email" value="<?= e($valores['email']) ?>" required /></div>
                <div class="form-field"><label>WhatsApp</label><input type="tel" name="whatsapp" data-mask="phone" value="<?= e($valores['whatsapp']) ?>" required /></div>
            </div>
            <?php if (!$editando): ?>
                <div class="form-field"><label>Senha</label><input type="password" name="senha" minlength="8" required /></div>
            <?php endif; ?>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.5rem;">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <a class="btn btn-outline-dark" href="instrutores.php">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php equipe_footer_html(); ?>
