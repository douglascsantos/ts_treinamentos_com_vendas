<?php
/**
 * Passo extra do cadastro via Google: o Google só garante e-mail (fixo aqui)
 * e um nome sugerido — todo o resto do cadastro normal é pedido aqui antes de
 * a conta existir de fato. Obrigatórios (mesma regra do cadastro comum):
 * pronome, nome, whatsapp, cpf, data de nascimento, área de atuação.
 * Opcionais: endereço (rua/número/CEP/cidade/UF) e foto.
 */
require __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/storage.php';
require __DIR__ . '/../includes/alunos.php';
require __DIR__ . '/../includes/checkout_helper.php';
require __DIR__ . '/../includes/fotos.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/csrf.php';
csrf_ensure_session();

$config = require __DIR__ . '/../includes/config.php';

$pendente = $_SESSION['google_pending'] ?? null;
if (!$pendente) {
    header('Location: /area-do-aluno.php');
    exit;
}

$orderNsu = $_SESSION['google_pending_order_nsu'] ?? '';
$erros = [];

$valores = [
    'pronome'         => '',
    'nome'            => $pendente['nome'],
    'whatsapp'        => '',
    'cpf'             => '',
    'data_nascimento' => '',
    'area_atuacao'    => '',
    'endereco'        => '',
    'numero'          => '',
    'cep'             => '',
    'cidade'          => '',
    'estado'          => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores = [
        'pronome'         => $_POST['pronome'] ?? '',
        'nome'            => trim($_POST['nome'] ?? ''),
        'whatsapp'        => $_POST['whatsapp'] ?? '',
        'cpf'             => $_POST['cpf'] ?? '',
        'data_nascimento' => $_POST['data_nascimento'] ?? '',
        'area_atuacao'    => $_POST['area_atuacao'] ?? '',
        'endereco'        => trim($_POST['endereco'] ?? ''),
        'numero'          => trim($_POST['numero'] ?? ''),
        'cep'             => trim($_POST['cep'] ?? ''),
        'cidade'          => trim($_POST['cidade'] ?? ''),
        'estado'          => mb_strtoupper(trim($_POST['estado'] ?? '')),
    ];

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        if (!in_array($valores['pronome'], PRONOMES, true)) {
            $erros[] = 'Selecione um pronome.';
        }
        if ($valores['nome'] === '' || mb_strlen($valores['nome']) < 3) {
            $erros[] = 'Informe seu nome completo.';
        }
        if (strlen(normalizar_whatsapp($valores['whatsapp'])) < 10) {
            $erros[] = 'Informe um WhatsApp válido, com DDD.';
        }
        if (!validar_cpf($valores['cpf'])) {
            $erros[] = 'CPF inválido.';
        } elseif (find_aluno_by_cpf($valores['cpf'])) {
            $erros[] = 'Já existe uma conta com esse CPF.';
        }
        if (!validar_data_nascimento($valores['data_nascimento'])) {
            $erros[] = 'Informe uma data de nascimento válida.';
        }
        if (!array_key_exists($valores['area_atuacao'], AREAS_ATUACAO)) {
            $erros[] = 'Selecione sua área de atuação.';
        }
        if ($valores['cep'] !== '' && !validar_cep($valores['cep'])) {
            $erros[] = 'CEP inválido.';
        }
        if ($valores['estado'] !== '' && !in_array($valores['estado'], ESTADOS_BR, true)) {
            $erros[] = 'Estado (UF) inválido.';
        }
    }

    if (!$erros) {
        $aluno = criar_aluno_google($pendente['email'], $pendente['sub'], [
            'pronome'         => $valores['pronome'],
            'nome'            => $valores['nome'],
            'whatsapp'        => $valores['whatsapp'],
            'cpf'             => $valores['cpf'],
            'data_nascimento' => $valores['data_nascimento'],
            'area_atuacao'    => $valores['area_atuacao'],
            'endereco'        => $valores['endereco'] !== '' ? $valores['endereco'] : null,
            'numero'          => $valores['numero'] !== '' ? $valores['numero'] : null,
            'cep'             => $valores['cep'] !== '' ? normalizar_cep($valores['cep']) : null,
            'cidade'          => $valores['cidade'] !== '' ? $valores['cidade'] : null,
            'estado'          => $valores['estado'] !== '' ? $valores['estado'] : null,
        ]);

        $nomeFoto = salvar_foto_aluno($aluno['id'], $_FILES['foto'] ?? []);
        if ($nomeFoto) {
            $aluno = atualizar_aluno($aluno['id'], ['foto' => $nomeFoto]) ?? $aluno;
        }

        unset($_SESSION['google_pending'], $_SESSION['google_pending_order_nsu']);
        session_regenerate_id(true);
        $_SESSION['aluno_id'] = $aluno['id'];
        if ($orderNsu !== '') {
            vincular_pedido_aluno($orderNsu, $aluno['id']);
        }
        checkout_retomar_se_pendente($config);
        header('Location: /minha-conta.php?novo=1');
        exit;
    }
}

$version = require __DIR__ . '/../includes/version.php';
$page_title = 'Complete seu cadastro | ' . $config['site_name'];
$base_path = '../';
include __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <div class="container page-content" style="max-width:600px;">
        <div class="section-head" style="max-width:none;">
            <p class="eyebrow">Quase lá</p>
            <h2>Complete seu cadastro</h2>
            <p class="lead-muted">Olá, <?= e($pendente['nome']) ?>! Confirme seus dados pra finalizar sua conta.</p>
        </div>
        <?php if ($erros): ?>
            <div class="form-errors-box"><ul><?php foreach ($erros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" style="margin-top:1.5rem;">
            <?= csrf_field() ?>
            <div class="form-field">
                <label>E-mail</label>
                <input type="text" value="<?= e($pendente['email']) ?>" disabled />
            </div>
            <div class="form-row">
                <div class="form-field">
                    <label for="pronome">Pronome</label>
                    <select id="pronome" name="pronome" required>
                        <option value="">--</option>
                        <?php foreach (PRONOMES as $p): ?>
                            <option value="<?= e($p) ?>" <?= $valores['pronome'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="nome">Nome completo</label>
                    <input type="text" id="nome" name="nome" maxlength="150" required value="<?= e($valores['nome']) ?>" />
                </div>
            </div>
            <div class="form-field">
                <label for="whatsapp">WhatsApp</label>
                <input type="tel" id="whatsapp" name="whatsapp" data-mask="phone" placeholder="(17) 99999-9999" maxlength="15" required value="<?= e($valores['whatsapp']) ?>" />
            </div>
            <div class="form-field">
                <label for="cpf">CPF</label>
                <input type="text" id="cpf" name="cpf" data-mask="cpf" placeholder="000.000.000-00" maxlength="14" required value="<?= e($valores['cpf']) ?>" />
            </div>
            <div class="form-field">
                <label for="data_nascimento">Data de nascimento</label>
                <input type="date" id="data_nascimento" name="data_nascimento" min="1900-01-01" max="<?= e(date('Y-m-d')) ?>" required value="<?= e($valores['data_nascimento']) ?>" />
            </div>
            <div class="form-field">
                <label for="area_atuacao">Área de atuação</label>
                <select id="area_atuacao" name="area_atuacao" required>
                    <option value="">Selecione</option>
                    <?php foreach (AREAS_ATUACAO as $chave => $label): ?>
                        <option value="<?= e($chave) ?>" <?= $valores['area_atuacao'] === $chave ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <p class="muted" style="margin:1.5rem 0 .5rem;">Endereço e foto são opcionais — pode preencher agora ou completar depois em "Meus dados".</p>

            <div class="form-row">
                <div class="form-field">
                    <label for="numero">Número</label>
                    <input type="text" id="numero" name="numero" maxlength="20" value="<?= e($valores['numero']) ?>" />
                </div>
                <div class="form-field">
                    <label for="endereco">Endereço</label>
                    <input type="text" id="endereco" name="endereco" maxlength="255" placeholder="Rua / Av." value="<?= e($valores['endereco']) ?>" />
                </div>
            </div>
            <div class="form-row">
                <div class="form-field">
                    <label for="cep">CEP</label>
                    <input type="text" id="cep" name="cep" data-mask="cep" placeholder="00000-000" maxlength="9" value="<?= e($valores['cep']) ?>" />
                </div>
                <div class="form-field">
                    <label for="cidade">Cidade</label>
                    <input type="text" id="cidade" name="cidade" maxlength="100" value="<?= e($valores['cidade']) ?>" />
                </div>
            </div>
            <div class="form-field">
                <label for="estado">Estado (UF)</label>
                <select id="estado" name="estado">
                    <option value="">--</option>
                    <?php foreach (ESTADOS_BR as $uf): ?>
                        <option value="<?= e($uf) ?>" <?= $valores['estado'] === $uf ? 'selected' : '' ?>><?= e($uf) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label>Foto</label>
                <div class="foto-upload">
                    <div class="foto-preview">
                        <img id="fotoPreviewImg" alt="Prévia da foto" hidden />
                    </div>
                    <div class="foto-actions">
                        <button type="button" class="btn btn-outline-dark" id="btnAbrirCamera">📷 Tirar foto</button>
                        <label class="btn btn-outline-dark" for="fotoArquivo">📁 Enviar arquivo</label>
                    </div>
                    <input type="file" id="fotoArquivo" name="foto" accept="image/*" hidden />
                    <div class="camera-box" id="cameraBox" hidden>
                        <video id="cameraVideo" autoplay playsinline></video>
                        <div class="camera-box-actions">
                            <button type="button" class="btn btn-primary" id="btnCapturarFoto">Capturar</button>
                            <button type="button" class="btn btn-outline-dark" id="btnCancelarCamera">Cancelar</button>
                        </div>
                    </div>
                    <canvas id="cameraCanvas" hidden></canvas>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top:1rem;">Finalizar cadastro</button>
        </form>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
