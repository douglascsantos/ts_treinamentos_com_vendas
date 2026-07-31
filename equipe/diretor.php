<?php
/**
 * Painel do diretor: cadastra instrutor, administrativo e aluno; vê a lista
 * de instrutores/administradores; faz upload da própria assinatura (usada
 * no certificado — ver includes/assinaturas.php e ROADMAP.md).
 */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/administradores.php';
require __DIR__ . '/../includes/instrutores.php';
require __DIR__ . '/../includes/storage.php';
require __DIR__ . '/../includes/alunos.php';
require __DIR__ . '/../includes/assinaturas.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['administrador']);
if ($staff['nivel'] !== 'diretor') {
    header('Location: administrativo.php');
    exit;
}

$eu = find_administrador_by_id($staff['id']);
$mensagens = [];
$erros = [];
$acao = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['acao'] ?? '') : '';

if ($acao === 'criar_instrutor') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $d = [
            'nome_completo'   => trim($_POST['nome_completo'] ?? ''),
            'formacao'        => trim($_POST['formacao'] ?? ''),
            'cpf'             => $_POST['cpf'] ?? '',
            'area_atuacao'    => $_POST['area_atuacao'] ?? '',
            'numero_registro' => trim($_POST['numero_registro'] ?? ''),
            'email'           => trim($_POST['email'] ?? ''),
            'whatsapp'        => $_POST['whatsapp'] ?? '',
            'senha'           => (string) ($_POST['senha'] ?? ''),
        ];
        if ($d['nome_completo'] === '' || $d['formacao'] === '') {
            $erros[] = 'Preencha nome completo e formação do instrutor.';
        } elseif (!validar_cpf($d['cpf'])) {
            $erros[] = 'CPF do instrutor inválido.';
        } elseif (!array_key_exists($d['area_atuacao'], AREAS_ATUACAO_INSTRUTOR)) {
            $erros[] = 'Selecione a área de atuação do instrutor.';
        } elseif (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail do instrutor inválido.';
        } elseif (find_instrutor_by_email($d['email'])) {
            $erros[] = 'Já existe um instrutor com esse e-mail.';
        } elseif (strlen($d['senha']) < 8) {
            $erros[] = 'Senha do instrutor precisa ter pelo menos 8 caracteres.';
        } else {
            criar_instrutor($d);
            $mensagens[] = 'Instrutor cadastrado com sucesso.';
        }
    }
}

if ($acao === 'criar_administrador') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $nome = trim($_POST['ad_nome'] ?? '');
        $email = trim($_POST['ad_email'] ?? '');
        $senha = (string) ($_POST['ad_senha'] ?? '');
        $nivel = $_POST['ad_nivel'] ?? 'administrativo';
        if ($nome === '') {
            $erros[] = 'Informe o nome do administrativo.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail do administrativo inválido.';
        } elseif (find_administrador_by_email($email)) {
            $erros[] = 'Já existe uma conta com esse e-mail.';
        } elseif (strlen($senha) < 8) {
            $erros[] = 'Senha precisa ter pelo menos 8 caracteres.';
        } elseif (!in_array($nivel, ['administrativo', 'diretor'], true)) {
            $erros[] = 'Nível inválido.';
        } else {
            criar_administrador(['nome' => $nome, 'email' => $email, 'senha' => $senha, 'nivel' => $nivel]);
            $mensagens[] = 'Conta de ' . $nivel . ' cadastrada com sucesso.';
        }
    }
}

if ($acao === 'criar_aluno') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $d = [
            'pronome'         => $_POST['al_pronome'] ?? '',
            'nome'            => trim($_POST['al_nome'] ?? ''),
            'email'           => trim($_POST['al_email'] ?? ''),
            'whatsapp'        => $_POST['al_whatsapp'] ?? '',
            'cpf'             => $_POST['al_cpf'] ?? '',
            'data_nascimento' => $_POST['al_nascimento'] ?? '',
            'area_atuacao'    => $_POST['al_area'] ?? '',
            'senha'           => (string) ($_POST['al_senha'] ?? ''),
        ];
        if (!in_array($d['pronome'], PRONOMES, true)) {
            $erros[] = 'Selecione o pronome do aluno.';
        } elseif ($d['nome'] === '' || mb_strlen($d['nome']) < 3) {
            $erros[] = 'Informe o nome completo do aluno.';
        } elseif (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail do aluno inválido.';
        } elseif (find_aluno_by_email($d['email'])) {
            $erros[] = 'Já existe uma conta de aluno com esse e-mail.';
        } elseif (strlen(normalizar_whatsapp($d['whatsapp'])) < 10) {
            $erros[] = 'WhatsApp do aluno inválido.';
        } elseif (!validar_cpf($d['cpf'])) {
            $erros[] = 'CPF do aluno inválido.';
        } elseif (find_aluno_by_cpf($d['cpf'])) {
            $erros[] = 'Já existe uma conta de aluno com esse CPF.';
        } elseif (!validar_data_nascimento($d['data_nascimento'])) {
            $erros[] = 'Data de nascimento do aluno inválida.';
        } elseif (!array_key_exists($d['area_atuacao'], AREAS_ATUACAO)) {
            $erros[] = 'Selecione a área de atuação do aluno.';
        } elseif (strlen($d['senha']) < 8) {
            $erros[] = 'Senha do aluno precisa ter pelo menos 8 caracteres.';
        } else {
            criar_aluno($d);
            $mensagens[] = 'Aluno cadastrado com sucesso.';
        }
    }
}

if ($acao === 'assinatura') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $nomeArquivo = salvar_assinatura($eu['id'], $_FILES['assinatura'] ?? []);
        if ($nomeArquivo) {
            $eu = atualizar_administrador($eu['id'], ['assinatura_imagem' => $nomeArquivo]);
            $mensagens[] = 'Assinatura atualizada.';
        } else {
            $erros[] = 'Não foi possível salvar a imagem da assinatura.';
        }
    }
}

$instrutores = listar_instrutores();
$administradores = listar_administradores();

equipe_header_html('Painel do Diretor', $staff['nome'] . ' (diretor)');
?>

<?php foreach ($mensagens as $m): ?>
    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;"><?= e($m) ?></div>
<?php endforeach; ?>
<?php if ($erros): ?>
    <div class="form-errors-box"><ul><?php foreach ($erros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="equipe-card">
    <h3>Minha assinatura (usada nos certificados)</h3>
    <?php if (!empty($eu['assinatura_imagem'])): ?>
        <p class="muted">Assinatura cadastrada: <?= e($eu['assinatura_imagem']) ?></p>
    <?php else: ?>
        <p class="muted">Nenhuma assinatura cadastrada ainda — sem ela, o certificado imprime seu nome em texto.</p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="assinatura" />
        <div class="form-field">
            <label for="assinatura">Imagem da assinatura (upload ou foto do celular)</label>
            <input type="file" id="assinatura" name="assinatura" accept="image/*" required />
        </div>
        <button type="submit" class="btn btn-primary">Salvar assinatura</button>
    </form>
</div>

<div class="equipe-card">
    <h3>Cadastrar instrutor</h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="criar_instrutor" />
        <div class="form-row">
            <div class="form-field"><label>Nome completo</label><input type="text" name="nome_completo" required /></div>
            <div class="form-field"><label>Formação</label><input type="text" name="formacao" required /></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label>CPF</label><input type="text" name="cpf" data-mask="cpf" required /></div>
            <div class="form-field">
                <label>Área de atuação</label>
                <select name="area_atuacao" required>
                    <option value="">Selecione</option>
                    <?php foreach (AREAS_ATUACAO_INSTRUTOR as $chave => $label): ?>
                        <option value="<?= e($chave) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-field"><label>Número do conselho (COREN/CRM/CRBM...)</label><input type="text" name="numero_registro" /></div>
        <div class="form-row">
            <div class="form-field"><label>E-mail</label><input type="email" name="email" required /></div>
            <div class="form-field"><label>WhatsApp</label><input type="tel" name="whatsapp" data-mask="phone" required /></div>
        </div>
        <div class="form-field"><label>Senha</label><input type="password" name="senha" minlength="8" required /></div>
        <button type="submit" class="btn btn-primary">Cadastrar instrutor</button>
    </form>
</div>

<div class="equipe-card">
    <h3>Cadastrar administrativo/diretor</h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="criar_administrador" />
        <div class="form-row">
            <div class="form-field"><label>Nome</label><input type="text" name="ad_nome" required /></div>
            <div class="form-field"><label>E-mail</label><input type="email" name="ad_email" required /></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label>Senha</label><input type="password" name="ad_senha" minlength="8" required /></div>
            <div class="form-field">
                <label>Nível</label>
                <select name="ad_nivel" required>
                    <option value="administrativo">Administrativo</option>
                    <option value="diretor">Diretor</option>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Cadastrar</button>
    </form>
</div>

<div class="equipe-card">
    <h3>Cadastrar aluno</h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="criar_aluno" />
        <div class="form-row">
            <div class="form-field">
                <label>Pronome</label>
                <select name="al_pronome" required>
                    <option value="">--</option>
                    <?php foreach (PRONOMES as $p): ?><option value="<?= e($p) ?>"><?= e($p) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-field"><label>Nome completo</label><input type="text" name="al_nome" required /></div>
        </div>
        <div class="form-field"><label>E-mail</label><input type="email" name="al_email" required /></div>
        <div class="form-row">
            <div class="form-field"><label>WhatsApp</label><input type="tel" name="al_whatsapp" data-mask="phone" required /></div>
            <div class="form-field"><label>CPF</label><input type="text" name="al_cpf" data-mask="cpf" required /></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label>Data de nascimento</label><input type="date" name="al_nascimento" max="<?= e(date('Y-m-d')) ?>" required /></div>
            <div class="form-field">
                <label>Área de atuação</label>
                <select name="al_area" required>
                    <option value="">Selecione</option>
                    <?php foreach (AREAS_ATUACAO as $chave => $label): ?><option value="<?= e($chave) ?>"><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-field"><label>Senha</label><input type="password" name="al_senha" minlength="8" required /></div>
        <button type="submit" class="btn btn-primary">Cadastrar aluno</button>
    </form>
</div>

<div class="equipe-card">
    <h3>Instrutores (<?= count($instrutores) ?>)</h3>
    <table class="equipe-table">
        <thead><tr><th>Nome</th><th>Área</th><th>Registro</th><th>E-mail</th><th>Assinatura</th></tr></thead>
        <tbody>
        <?php foreach ($instrutores as $i): ?>
            <tr>
                <td><?= e($i['nome_completo']) ?></td>
                <td><?= e(AREAS_ATUACAO_INSTRUTOR[$i['area_atuacao']] ?? $i['area_atuacao']) ?></td>
                <td><?= e($i['numero_registro'] ?? '—') ?></td>
                <td><?= e($i['email']) ?></td>
                <td><?= $i['assinatura_imagem'] ? '✅' : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$instrutores): ?><tr><td colspan="5" class="muted">Nenhum instrutor cadastrado ainda.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="equipe-card">
    <h3>Administrativo/Diretor (<?= count($administradores) ?>)</h3>
    <table class="equipe-table">
        <thead><tr><th>Nome</th><th>Nível</th><th>E-mail</th><th>Assinatura</th></tr></thead>
        <tbody>
        <?php foreach ($administradores as $a): ?>
            <tr>
                <td><?= e($a['nome']) ?></td>
                <td><?= e(ucfirst($a['nivel'])) ?></td>
                <td><?= e($a['email']) ?></td>
                <td><?= $a['assinatura_imagem'] ? '✅' : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php equipe_footer_html(); ?>
