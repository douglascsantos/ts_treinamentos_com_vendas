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

<div class="equipe-dash-grid">
    <a class="equipe-dash-card" href="turmas.php">
        <h4>Turmas</h4>
        <p>Criar curso, editar vagas/carga horária/status e atribuir instrutor.</p>
    </a>
    <a class="equipe-dash-card" href="cupons.php">
        <h4>Cupons de desconto</h4>
        <p>Gerar link de desconto pra um curso ou produto específico.</p>
    </a>
    <a class="equipe-dash-card" href="boletos.php">
        <h4>Boletos</h4>
        <p>Cadastrar parcela por aluno (upload do PDF).</p>
    </a>
    <a class="equipe-dash-card" href="gastos.php">
        <h4>Gastos</h4>
        <p>Lançar gastos por curso, fixos, variáveis e de patrimônio.</p>
    </a>
</div>

<div class="equipe-card" style="margin-top:1.5rem;">
    <h3>Em construção</h3>
    <p class="muted">Relatórios de vendas — ver <code>ROADMAP.md</code>.</p>
</div>

<?php equipe_footer_html(); ?>
