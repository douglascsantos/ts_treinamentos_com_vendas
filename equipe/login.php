<?php
/**
 * Login único da equipe: tenta administradores primeiro, depois instrutores
 * (e-mails não colidem na prática — são cadastrados um de cada vez pelo
 * diretor). Redireciona pro painel certo conforme o tipo/nível encontrado.
 */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/administradores.php';
require __DIR__ . '/../includes/instrutores.php';
require __DIR__ . '/../includes/csrf.php';
csrf_ensure_session();

if (!empty($_SESSION['staff_tipo'])) {
    header('Location: ' . ($_SESSION['staff_nivel'] === 'diretor' ? 'diretor.php' : ($_SESSION['staff_tipo'] === 'instrutor' ? 'instrutor.php' : 'administrativo.php')));
    exit;
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } elseif ($email === '' || $senha === '') {
        $erros[] = 'Informe e-mail e senha.';
    } else {
        try {
            $admin = verificar_login_administrador($email, $senha);
            if ($admin) {
                session_regenerate_id(true);
                $_SESSION['staff_tipo'] = 'administrador';
                $_SESSION['staff_id'] = $admin['id'];
                $_SESSION['staff_nivel'] = $admin['nivel'];
                $_SESSION['staff_nome'] = $admin['nome'];
                header('Location: ' . ($admin['nivel'] === 'diretor' ? 'diretor.php' : 'administrativo.php'));
                exit;
            }

            $instrutor = verificar_login_instrutor($email, $senha);
            if ($instrutor) {
                session_regenerate_id(true);
                $_SESSION['staff_tipo'] = 'instrutor';
                $_SESSION['staff_id'] = $instrutor['id'];
                $_SESSION['staff_nivel'] = null;
                $_SESSION['staff_nome'] = $instrutor['nome_completo'];
                header('Location: instrutor.php');
                exit;
            }

            $erros[] = 'E-mail ou senha inválidos.';
        } catch (Throwable $e) {
            $erros[] = 'Erro ao conectar. Tente novamente em instantes.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Login da equipe | TS Treinamentos</title>
<meta name="robots" content="noindex, nofollow" />
<link rel="stylesheet" href="../assets/css/style.css" />
<style>body{background:#f4f5f3;display:flex;min-height:100vh;align-items:center;justify-content:center;}</style>
</head>
<body>
<div class="equipe-card" style="max-width:380px;width:100%;">
    <h2 style="margin-bottom:1.25rem;">Login da equipe</h2>
    <?php if ($erros): ?>
        <div class="form-errors-box"><ul><?php foreach ($erros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-field">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" required autofocus />
        </div>
        <div class="form-field">
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" required />
        </div>
        <button type="submit" class="btn btn-primary btn-block">Entrar</button>
    </form>
</div>
</body>
</html>
