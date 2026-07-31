<?php
/**
 * Alunos (só diretor): lista + "+" revela o cadastro manual + desabilitar
 * (bloqueia login, nunca apaga — CPF/pedidos/contratos ligados a essa conta
 * continuam intactos). Edição completa dos dados o próprio aluno já faz em
 * meus-dados.php; aqui é só cadastro manual e ativar/desativar.
 */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/administradores.php';
require __DIR__ . '/../includes/storage.php';
require __DIR__ . '/../includes/alunos.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['administrador']);
if ($staff['nivel'] !== 'diretor') {
    header('Location: administrativo.php');
    exit;
}

$mensagens = [];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'criar_aluno') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $d = [
            'pronome'         => $_POST['pronome'] ?? '',
            'nome'            => trim($_POST['nome'] ?? ''),
            'email'           => trim($_POST['email'] ?? ''),
            'whatsapp'        => $_POST['whatsapp'] ?? '',
            'cpf'             => $_POST['cpf'] ?? '',
            'data_nascimento' => $_POST['data_nascimento'] ?? '',
            'area_atuacao'    => $_POST['area_atuacao'] ?? '',
            'senha'           => (string) ($_POST['senha'] ?? ''),
        ];
        if (!in_array($d['pronome'], PRONOMES, true)) {
            $erros[] = 'Selecione o pronome.';
        } elseif ($d['nome'] === '' || mb_strlen($d['nome']) < 3) {
            $erros[] = 'Informe o nome completo.';
        } elseif (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail inválido.';
        } elseif (find_aluno_by_email($d['email'])) {
            $erros[] = 'Já existe uma conta com esse e-mail.';
        } elseif (strlen(normalizar_whatsapp($d['whatsapp'])) < 10) {
            $erros[] = 'WhatsApp inválido.';
        } elseif (!validar_cpf($d['cpf'])) {
            $erros[] = 'CPF inválido.';
        } elseif (find_aluno_by_cpf($d['cpf'])) {
            $erros[] = 'Já existe uma conta com esse CPF.';
        } elseif (!validar_data_nascimento($d['data_nascimento'])) {
            $erros[] = 'Data de nascimento inválida.';
        } elseif (!array_key_exists($d['area_atuacao'], AREAS_ATUACAO)) {
            $erros[] = 'Selecione a área de atuação.';
        } elseif (strlen($d['senha']) < 8) {
            $erros[] = 'Senha precisa ter pelo menos 8 caracteres.';
        } else {
            criar_aluno($d);
            $mensagens[] = 'Aluno cadastrado.';
        }

        if (!$erros) {
            header('Location: alunos.php');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['desativar_aluno', 'ativar_aluno'], true)) {
    if (csrf_verify($_POST['csrf_token'] ?? null)) {
        atualizar_aluno($_POST['id'] ?? '', ['ativo' => $_POST['acao'] === 'ativar_aluno']);
    }
    header('Location: alunos.php');
    exit;
}

$formAberto = (bool) $erros;
$alunos = listar_alunos();

equipe_header_html('Alunos', $staff['nome']);
?>

<p style="margin-bottom:1.25rem;"><a href="diretor.php">← Painel do diretor</a></p>

<?php foreach ($mensagens as $m): ?>
    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;"><?= e($m) ?></div>
<?php endforeach; ?>
<?php if ($erros): ?>
    <div class="form-errors-box"><ul><?php foreach ($erros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="equipe-card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;">
        <h3 style="margin:0;">Alunos (<?= count($alunos) ?>)</h3>
        <button type="button" class="btn-add-toggle" data-toggle-target="formAluno" data-toggle-close-text="Cancelar">+ Novo aluno</button>
    </div>

    <?php if (!$alunos): ?>
        <p class="equipe-empty">Nenhum aluno cadastrado ainda.</p>
    <?php else: ?>
        <div class="equipe-list">
            <?php foreach ($alunos as $a): ?>
                <div class="equipe-list-item">
                    <span class="equipe-list-link" style="cursor:default;">
                        <?= e(trim(($a['pronome'] ?? '') . ' ' . $a['nome'])) ?>
                        <span class="equipe-list-meta"><?= e($a['email']) ?><?= !empty($a['whatsapp']) ? ' · ' . e($a['whatsapp']) : '' ?></span>
                    </span>
                    <div class="equipe-list-actions">
                        <?php $ativo = ($a['ativo'] ?? true) !== false; ?>
                        <span class="<?= $ativo ? 'badge-ativo' : 'badge-inativo' ?>"><?= $ativo ? 'Ativo' : 'Inativo' ?></span>
                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="<?= $ativo ? 'desativar_aluno' : 'ativar_aluno' ?>" />
                            <input type="hidden" name="id" value="<?= e($a['id']) ?>" />
                            <button type="submit" class="<?= $ativo ? 'btn-disable' : 'btn-enable' ?>"><?= $ativo ? 'Desabilitar' : 'Reativar' ?></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="toggle-form" id="formAluno" <?= $formAberto ? '' : 'hidden' ?>>
        <h4 style="margin-bottom:1rem;">Novo aluno</h4>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="acao" value="criar_aluno" />
            <div class="form-row">
                <div class="form-field">
                    <label>Pronome</label>
                    <select name="pronome" required>
                        <option value="">--</option>
                        <?php foreach (PRONOMES as $p): ?><option value="<?= e($p) ?>"><?= e($p) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field"><label>Nome completo</label><input type="text" name="nome" required /></div>
            </div>
            <div class="form-field"><label>E-mail</label><input type="email" name="email" required /></div>
            <div class="equipe-form-row">
                <div class="form-field"><label>WhatsApp</label><input type="tel" name="whatsapp" data-mask="phone" required /></div>
                <div class="form-field"><label>CPF</label><input type="text" name="cpf" data-mask="cpf" required /></div>
            </div>
            <div class="equipe-form-row">
                <div class="form-field"><label>Data de nascimento</label><input type="date" name="data_nascimento" max="<?= e(date('Y-m-d')) ?>" required /></div>
                <div class="form-field">
                    <label>Área de atuação</label>
                    <select name="area_atuacao" required>
                        <option value="">Selecione</option>
                        <?php foreach (AREAS_ATUACAO as $chave => $label): ?><option value="<?= e($chave) ?>"><?= e($label) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-field"><label>Senha</label><input type="password" name="senha" minlength="8" required /></div>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.5rem;">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <a class="btn btn-outline-dark" href="alunos.php">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php equipe_footer_html(); ?>
