<?php
/**
 * Painel do administrativo — login funcional, dashboard ainda em construção
 * (gestão de turmas/vagas, gastos, relatórios, cupons, boletos — ver
 * ROADMAP.md item 3 pra status detalhado de cada parte).
 */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/administradores.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['administrador']);
if ($staff['nivel'] === 'diretor') {
    header('Location: diretor.php');
    exit;
}

equipe_header_html('Painel Administrativo', $staff['nome']);
?>

<div class="equipe-card">
    <h3>Turmas</h3>
    <p class="muted">Criar curso, editar vagas/carga horária/status e atribuir instrutor.</p>
    <a class="btn btn-primary" href="turmas.php">Gerenciar turmas</a>
</div>

<div class="equipe-card">
    <h3>Cupons de desconto</h3>
    <p class="muted">Gerar link de desconto pra um curso ou produto específico.</p>
    <a class="btn btn-primary" href="cupons.php">Gerenciar cupons</a>
</div>

<div class="equipe-card">
    <h3>Boletos</h3>
    <p class="muted">Cadastrar parcela por aluno (upload do PDF).</p>
    <a class="btn btn-primary" href="boletos.php">Gerenciar boletos</a>
</div>

<div class="equipe-card">
    <h3>Gastos</h3>
    <p class="muted">Lançar gastos por curso, fixos, variáveis e de patrimônio.</p>
    <a class="btn btn-primary" href="gastos.php">Gerenciar gastos</a>
</div>

<div class="equipe-card">
    <h3>Em construção</h3>
    <p class="muted">Relatórios de vendas — ver <code>ROADMAP.md</code>.</p>
</div>

<?php equipe_footer_html(); ?>
