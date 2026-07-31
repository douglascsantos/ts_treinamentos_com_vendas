<?php
/**
 * Tela de dados do aluno: trocar senha e completar/editar endereço, data de
 * nascimento e WhatsApp. Nome, e-mail e CPF são fixos — não dá pra editar
 * por aqui (mudariam a identidade da conta e o que já está assinado nos
 * contratos).
 */
require __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/storage.php';
require __DIR__ . '/includes/alunos.php';
require __DIR__ . '/includes/csrf.php';
csrf_ensure_session();

$config  = require __DIR__ . '/includes/config.php';
$version = require __DIR__ . '/includes/version.php';

if (empty($_SESSION['aluno_id'])) {
    header('Location: area-do-aluno.php');
    exit;
}

$aluno = find_aluno_by_id($_SESSION['aluno_id']);
if (!$aluno) {
    session_destroy();
    header('Location: area-do-aluno.php');
    exit;
}

$dadosErros = [];
$dadosOk = false;
$senhaErros = [];
$senhaOk = false;

$dadosValores = [
    'whatsapp'        => $aluno['whatsapp'] ?? '',
    'data_nascimento' => $aluno['data_nascimento'] ?? '',
    'endereco'        => $aluno['endereco'] ?? '',
    'numero'          => $aluno['numero'] ?? '',
    'cep'             => $aluno['cep'] ?? '',
    'cidade'          => $aluno['cidade'] ?? '',
    'estado'          => $aluno['estado'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'atualizar_dados') {
    $dadosValores = [
        'whatsapp'        => $_POST['whatsapp'] ?? '',
        'data_nascimento' => $_POST['data_nascimento'] ?? '',
        'endereco'        => trim($_POST['endereco'] ?? ''),
        'numero'          => trim($_POST['numero'] ?? ''),
        'cep'             => trim($_POST['cep'] ?? ''),
        'cidade'          => trim($_POST['cidade'] ?? ''),
        'estado'          => mb_strtoupper(trim($_POST['estado'] ?? '')),
    ];

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $dadosErros[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        // Endereço é totalmente opcional — só valida formato se algo foi preenchido.
        if (strlen(normalizar_whatsapp($dadosValores['whatsapp'])) < 10) {
            $dadosErros[] = 'Informe um WhatsApp válido, com DDD.';
        }
        if ($dadosValores['data_nascimento'] !== '' && !validar_data_nascimento($dadosValores['data_nascimento'])) {
            $dadosErros[] = 'Informe uma data de nascimento válida.';
        }
        if (mb_strlen($dadosValores['endereco']) > 255) {
            $dadosErros[] = 'Endereço muito longo.';
        }
        if ($dadosValores['cep'] !== '' && !validar_cep($dadosValores['cep'])) {
            $dadosErros[] = 'CEP inválido.';
        }
        if ($dadosValores['estado'] !== '' && !in_array($dadosValores['estado'], ESTADOS_BR, true)) {
            $dadosErros[] = 'Estado (UF) inválido.';
        }
    }

    if (!$dadosErros) {
        $aluno = atualizar_aluno($aluno['id'], [
            'whatsapp'        => normalizar_whatsapp($dadosValores['whatsapp']),
            'data_nascimento' => $dadosValores['data_nascimento'] !== '' ? $dadosValores['data_nascimento'] : null,
            'endereco'        => $dadosValores['endereco'] !== '' ? $dadosValores['endereco'] : null,
            'numero'          => $dadosValores['numero'] !== '' ? $dadosValores['numero'] : null,
            'cep'             => $dadosValores['cep'] !== '' ? normalizar_cep($dadosValores['cep']) : null,
            'cidade'          => $dadosValores['cidade'] !== '' ? $dadosValores['cidade'] : null,
            'estado'          => $dadosValores['estado'] !== '' ? $dadosValores['estado'] : null,
        ]) ?? $aluno;
        $dadosOk = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'trocar_senha') {
    $senhaAtual = (string) ($_POST['senha_atual'] ?? '');
    $senhaNova = (string) ($_POST['senha_nova'] ?? '');
    $senhaConfirmar = (string) ($_POST['senha_confirmar'] ?? '');

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $senhaErros[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } elseif (empty($aluno['senha_hash'])) {
        $senhaErros[] = 'Essa conta usa login do Google, não tem senha pra trocar.';
    } elseif (!password_verify($senhaAtual, $aluno['senha_hash'])) {
        $senhaErros[] = 'Senha atual incorreta.';
    } elseif (strlen($senhaNova) < 8) {
        $senhaErros[] = 'A nova senha precisa ter pelo menos 8 caracteres.';
    } elseif ($senhaNova !== $senhaConfirmar) {
        $senhaErros[] = 'As senhas não coincidem.';
    }

    if (!$senhaErros) {
        $aluno = atualizar_aluno($aluno['id'], [
            'senha_hash' => password_hash($senhaNova, PASSWORD_DEFAULT),
        ]) ?? $aluno;
        $senhaOk = true;
    }
}

$page_title = 'Meus Dados | ' . $config['site_name'];
$base_path = '';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="section-head" style="max-width:none;">
            <p class="eyebrow">Minha conta</p>
            <h2>Meus dados</h2>
        </div>

        <div class="auth-grid" style="margin-top:2.5rem;">
            <div class="auth-col">
                <h2>Dados pessoais</h2>
                <p class="muted" style="margin-bottom:1.25rem;">Nome, e-mail e CPF não podem ser alterados por aqui.</p>

                <div class="form-field">
                    <label>Nome</label>
                    <input type="text" value="<?= e($aluno['nome']) ?>" disabled />
                </div>
                <div class="form-field">
                    <label>E-mail</label>
                    <input type="text" value="<?= e($aluno['email']) ?>" disabled />
                </div>
                <div class="form-field">
                    <label>CPF</label>
                    <input type="text" value="<?= e(preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $aluno['cpf'])) ?>" disabled />
                </div>

                <?php if ($dadosOk): ?>
                    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;"><ul><li>Dados atualizados com sucesso.</li></ul></div>
                <?php endif; ?>
                <?php if ($dadosErros): ?>
                    <div class="form-errors-box"><ul><?php foreach ($dadosErros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>

                <form method="post" style="margin-top:1.25rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="acao" value="atualizar_dados" />
                    <div class="form-field">
                        <label for="whatsapp">WhatsApp</label>
                        <input type="tel" id="whatsapp" name="whatsapp" data-mask="phone" placeholder="(17) 99999-9999" maxlength="15" required value="<?= e($dadosValores['whatsapp']) ?>" />
                    </div>
                    <div class="form-field">
                        <label for="data_nascimento">Data de nascimento</label>
                        <input type="date" id="data_nascimento" name="data_nascimento" min="1900-01-01" max="<?= e(date('Y-m-d')) ?>" value="<?= e($dadosValores['data_nascimento']) ?>" />
                    </div>
                    <div class="form-row">
                        <div class="form-field">
                            <label for="numero">Número</label>
                            <input type="text" id="numero" name="numero" maxlength="20" value="<?= e($dadosValores['numero']) ?>" />
                        </div>
                        <div class="form-field">
                            <label for="endereco">Endereço</label>
                            <input type="text" id="endereco" name="endereco" maxlength="255" placeholder="Rua / Av." value="<?= e($dadosValores['endereco']) ?>" />
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-field">
                            <label for="cep">CEP</label>
                            <input type="text" id="cep" name="cep" data-mask="cep" placeholder="00000-000" maxlength="9" value="<?= e($dadosValores['cep']) ?>" />
                        </div>
                        <div class="form-field">
                            <label for="cidade">Cidade</label>
                            <input type="text" id="cidade" name="cidade" maxlength="100" value="<?= e($dadosValores['cidade']) ?>" />
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="estado">Estado (UF)</label>
                        <select id="estado" name="estado">
                            <option value="">--</option>
                            <?php foreach (ESTADOS_BR as $uf): ?>
                                <option value="<?= e($uf) ?>" <?= $dadosValores['estado'] === $uf ? 'selected' : '' ?>><?= e($uf) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Salvar dados</button>
                </form>
            </div>

            <div class="auth-col">
                <h2>Trocar senha</h2>
                <?php if ($senhaOk): ?>
                    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;"><ul><li>Senha alterada com sucesso.</li></ul></div>
                <?php endif; ?>
                <?php if ($senhaErros): ?>
                    <div class="form-errors-box"><ul><?php foreach ($senhaErros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>

                <?php if (empty($aluno['senha_hash'])): ?>
                    <p class="muted">Sua conta usa login do Google — não tem senha cadastrada pra trocar.</p>
                <?php else: ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="acao" value="trocar_senha" />
                        <div class="form-field">
                            <label for="senha_atual">Senha atual</label>
                            <input type="password" id="senha_atual" name="senha_atual" required />
                        </div>
                        <div class="form-field">
                            <label for="senha_nova">Nova senha</label>
                            <input type="password" id="senha_nova" name="senha_nova" minlength="8" required />
                        </div>
                        <div class="form-field">
                            <label for="senha_confirmar">Confirmar nova senha</label>
                            <input type="password" id="senha_confirmar" name="senha_confirmar" minlength="8" required />
                        </div>
                        <button type="submit" class="btn btn-accent btn-block">Trocar senha</button>
                    </form>
                <?php endif; ?>

                <div class="auth-divider">&nbsp;</div>
                <a class="btn btn-outline-dark btn-block" href="minha-conta.php">← Voltar pra Minha Conta</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
