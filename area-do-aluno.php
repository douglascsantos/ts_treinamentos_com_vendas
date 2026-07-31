<?php
/**
 * Login e cadastro de aluno. Ainda não existe um "dashboard" de verdade —
 * depois de logar/cadastrar, o aluno vai pra minha-conta.php (stub),
 * enquanto a Área do Aluno completa não é construída (ver ROADMAP.md).
 */
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/storage.php';
require __DIR__ . '/includes/alunos.php';
require __DIR__ . '/includes/pedidos.php';
require __DIR__ . '/includes/env.php';
require __DIR__ . '/includes/csrf.php';
require __DIR__ . '/includes/google_oauth.php';
csrf_ensure_session();

$config  = require __DIR__ . '/includes/config.php';
$version = require __DIR__ . '/includes/version.php';

// order_nsu chega por GET (link de pagamento-concluido.php) ou continua vindo
// junto no POST do form (campo oculto), pra sobreviver ao ciclo de validação.
$orderNsu = $_POST['order_nsu'] ?? $_GET['order_nsu'] ?? '';

if (!empty($_SESSION['aluno_id'])) {
    if ($orderNsu !== '') {
        vincular_pedido_aluno($orderNsu, $_SESSION['aluno_id']);
    }
    header('Location: minha-conta.php');
    exit;
}

$loginErros = [];
$cadastroErros = [];
$cadastroValores = ['pronome' => '', 'nome' => '', 'email' => '', 'whatsapp' => '', 'cpf' => '', 'data_nascimento' => '', 'area_atuacao' => ''];

$acao = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['acao'] ?? '') : '';

if ($acao === 'login') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $loginErros[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        $email = trim($_POST['login_email'] ?? '');
        $senha = $_POST['login_senha'] ?? '';
        $aluno = ($email !== '' && $senha !== '') ? verificar_login($email, $senha) : null;

        if ($aluno) {
            session_regenerate_id(true);
            $_SESSION['aluno_id'] = $aluno['id'];
            if ($orderNsu !== '') {
                vincular_pedido_aluno($orderNsu, $aluno['id']);
            }
            header('Location: minha-conta.php');
            exit;
        }

        $contaGoogle = $email !== '' ? find_aluno_by_email($email) : null;
        if ($contaGoogle && empty($contaGoogle['senha_hash'])) {
            $loginErros[] = 'Essa conta foi criada com login do Google — use o botão "Entrar com Google" abaixo.';
        } else {
            $loginErros[] = 'E-mail ou senha inválidos.';
        }
    }
}

if ($acao === 'cadastro') {
    $cadastroValores = [
        'pronome'         => $_POST['cad_pronome'] ?? '',
        'nome'            => trim($_POST['cad_nome'] ?? ''),
        'email'           => trim($_POST['cad_email'] ?? ''),
        'whatsapp'        => $_POST['cad_whatsapp'] ?? '',
        'cpf'             => $_POST['cad_cpf'] ?? '',
        'data_nascimento' => $_POST['cad_nascimento'] ?? '',
        'area_atuacao'    => $_POST['cad_area'] ?? '',
    ];
    $senha = (string) ($_POST['cad_senha'] ?? '');
    $senhaConfirmar = (string) ($_POST['cad_senha_confirmar'] ?? '');

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $cadastroErros[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        if (!in_array($cadastroValores['pronome'], PRONOMES, true)) {
            $cadastroErros[] = 'Selecione um pronome.';
        }
        if ($cadastroValores['nome'] === '' || mb_strlen($cadastroValores['nome']) < 3) {
            $cadastroErros[] = 'Informe seu nome completo.';
        }
        if (!filter_var($cadastroValores['email'], FILTER_VALIDATE_EMAIL)) {
            $cadastroErros[] = 'E-mail inválido.';
        } elseif (find_aluno_by_email($cadastroValores['email'])) {
            $cadastroErros[] = 'Já existe uma conta com esse e-mail.';
        }
        if (strlen(normalizar_whatsapp($cadastroValores['whatsapp'])) < 10) {
            $cadastroErros[] = 'Informe um WhatsApp válido, com DDD.';
        }
        if (!validar_cpf($cadastroValores['cpf'])) {
            $cadastroErros[] = 'CPF inválido.';
        } elseif (find_aluno_by_cpf($cadastroValores['cpf'])) {
            $cadastroErros[] = 'Já existe uma conta com esse CPF.';
        }
        if (!validar_data_nascimento($cadastroValores['data_nascimento'])) {
            $cadastroErros[] = 'Informe uma data de nascimento válida.';
        }
        if (!array_key_exists($cadastroValores['area_atuacao'], AREAS_ATUACAO)) {
            $cadastroErros[] = 'Selecione sua área de atuação.';
        }
        if (strlen($senha) < 8) {
            $cadastroErros[] = 'A senha precisa ter pelo menos 8 caracteres.';
        } elseif ($senha !== $senhaConfirmar) {
            $cadastroErros[] = 'As senhas não coincidem.';
        }
    }

    if (!$cadastroErros) {
        $aluno = criar_aluno(array_merge($cadastroValores, ['senha' => $senha]));
        session_regenerate_id(true);
        $_SESSION['aluno_id'] = $aluno['id'];
        if ($orderNsu !== '') {
            vincular_pedido_aluno($orderNsu, $aluno['id']);
        }
        header('Location: minha-conta.php?novo=1');
        exit;
    }
}

$page_title = 'Área do Aluno | ' . $config['site_name'];
$base_path = '';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="section-head" style="text-align:center;max-width:none;">
            <p class="eyebrow">Área do Aluno</p>
            <h2>Entre na sua conta ou cadastre-se</h2>
        </div>

        <div class="auth-grid" style="margin-top:2.5rem;">
            <div class="auth-col">
                <h2>Já tenho conta</h2>
                <?php if (isset($_GET['google_erro'])): ?>
                    <div class="form-errors-box"><ul><li>Não deu pra entrar com o Google agora. Tente de novo ou use e-mail/senha.</li></ul></div>
                <?php endif; ?>
                <?php if ($loginErros): ?>
                    <div class="form-errors-box"><ul><?php foreach ($loginErros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="acao" value="login" />
                    <input type="hidden" name="order_nsu" value="<?= e($orderNsu) ?>" />
                    <div class="form-field">
                        <label for="login_email">E-mail</label>
                        <input type="email" id="login_email" name="login_email" maxlength="180" required />
                    </div>
                    <div class="form-field">
                        <label for="login_senha">Senha</label>
                        <input type="password" id="login_senha" name="login_senha" required />
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Entrar</button>
                </form>
                <div class="auth-divider">ou</div>
                <?php if (google_oauth_configured()): ?>
                    <a class="btn btn-google" href="auth/google-login.php<?= $orderNsu !== '' ? '?order_nsu=' . rawurlencode($orderNsu) : '' ?>">Entrar com Google</a>
                <?php else: ?>
                    <button type="button" class="btn btn-google" disabled title="Em breve">Entrar com Google (em breve)</button>
                <?php endif; ?>
            </div>

            <div class="auth-col">
                <h2>Criar conta</h2>
                <?php if ($cadastroErros): ?>
                    <div class="form-errors-box"><ul><?php foreach ($cadastroErros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="acao" value="cadastro" />
                    <input type="hidden" name="order_nsu" value="<?= e($orderNsu) ?>" />
                    <div class="form-row">
                        <div class="form-field">
                            <label for="cad_pronome">Pronome</label>
                            <select id="cad_pronome" name="cad_pronome" required>
                                <option value="">--</option>
                                <?php foreach (PRONOMES as $p): ?>
                                    <option value="<?= e($p) ?>" <?= $cadastroValores['pronome'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="cad_nome">Nome completo</label>
                            <input type="text" id="cad_nome" name="cad_nome" maxlength="150" required value="<?= e($cadastroValores['nome']) ?>" />
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="cad_email">E-mail</label>
                        <input type="email" id="cad_email" name="cad_email" maxlength="180" required value="<?= e($cadastroValores['email']) ?>" />
                    </div>
                    <div class="form-field">
                        <label for="cad_whatsapp">WhatsApp</label>
                        <input type="tel" id="cad_whatsapp" name="cad_whatsapp" data-mask="phone" placeholder="(17) 99999-9999" maxlength="15" required value="<?= e($cadastroValores['whatsapp']) ?>" />
                    </div>
                    <div class="form-field">
                        <label for="cad_cpf">CPF</label>
                        <input type="text" id="cad_cpf" name="cad_cpf" data-mask="cpf" placeholder="000.000.000-00" maxlength="14" required value="<?= e($cadastroValores['cpf']) ?>" />
                    </div>
                    <div class="form-field">
                        <label for="cad_nascimento">Data de nascimento</label>
                        <input type="date" id="cad_nascimento" name="cad_nascimento" required min="1900-01-01" max="<?= e(date('Y-m-d')) ?>" value="<?= e($cadastroValores['data_nascimento']) ?>" />
                    </div>
                    <div class="form-field">
                        <label for="cad_area">Área de atuação</label>
                        <select id="cad_area" name="cad_area" required>
                            <option value="">Selecione</option>
                            <?php foreach (AREAS_ATUACAO as $chave => $label): ?>
                                <option value="<?= e($chave) ?>" <?= $cadastroValores['area_atuacao'] === $chave ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="cad_senha">Senha</label>
                        <input type="password" id="cad_senha" name="cad_senha" minlength="8" required />
                    </div>
                    <div class="form-field">
                        <label for="cad_senha_confirmar">Confirmar senha</label>
                        <input type="password" id="cad_senha_confirmar" name="cad_senha_confirmar" minlength="8" required />
                    </div>
                    <button type="submit" class="btn btn-accent btn-block">Criar conta</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
