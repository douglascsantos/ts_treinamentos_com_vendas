<?php
/**
 * Verificação pública de certificado/diploma — qualquer pessoa com o código
 * (ou o link do QR/URL) pode conferir que é autêntico, sem precisar de
 * login. Mesmo princípio de meu-contrato.php pra impressão: página HTML com
 * "Imprimir / Salvar PDF" (@media print esconde o site ao redor) em vez de
 * gerar um PDF no servidor.
 */
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/env.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/certificados.php';
require __DIR__ . '/includes/instrutores.php';
require __DIR__ . '/includes/administradores.php';

$config  = require __DIR__ . '/includes/config.php';
$version = require __DIR__ . '/includes/version.php';

$codigo = trim($_GET['codigo'] ?? '');
$certificado = null;
$erroBanco = false;
if ($codigo !== '') {
    try {
        $certificado = find_certificado_by_codigo($codigo);
    } catch (Throwable $e) {
        $erroBanco = true;
    }
}

$instrutor = $certificado ? find_instrutor_by_id($certificado['instrutor_id']) : null;
$administrativo = $certificado ? find_administrador_by_id($certificado['diretor_id']) : null;

$page_title = $certificado ? 'Certificado de ' . $certificado['aluno_nome'] . ' | ' . $config['site_name'] : 'Verificar certificado | ' . $config['site_name'];
$base_path = '';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container page-content">
        <?php if (!$codigo): ?>
            <div class="section-head" style="max-width:none;">
                <p class="eyebrow">Verificação de certificado</p>
                <h1>Confira a autenticidade de um diploma</h1>
                <p class="muted">Digite o código impresso no certificado (ou escaneie o QR Code).</p>
            </div>
            <form method="get" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;max-width:420px;margin-top:1.5rem;">
                <div class="form-field" style="margin:0;flex:1;"><label>Código do certificado</label><input type="text" name="codigo" required /></div>
                <button type="submit" class="btn btn-primary">Verificar</button>
            </form>
        <?php elseif ($erroBanco): ?>
            <h1>Verificação indisponível</h1>
            <p class="muted">Não foi possível conferir agora — tente novamente em instantes.</p>
        <?php elseif (!$certificado): ?>
            <h1>Certificado não encontrado</h1>
            <p class="muted">Não existe nenhum certificado com o código "<?= e($codigo) ?>". Confira se digitou certo.</p>
        <?php else: ?>
            <div class="contrato-print-btn" style="margin-bottom:1.5rem;">
                <button type="button" class="btn btn-primary" onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>
            </div>

            <div class="certificado-box">
                <p class="certificado-marca">TS Treinamentos em Saúde</p>
                <p class="certificado-titulo">Certificado de Conclusão</p>
                <p class="certificado-corpo">
                    Certificamos que <strong><?= e($certificado['aluno_nome']) ?></strong> concluiu o curso
                    <strong><?= e($certificado['curso_nome']) ?></strong>, com carga horária de
                    <strong><?= (int) $certificado['carga_horaria'] ?> horas</strong>,
                    em <?= e(date('d/m/Y', strtotime($certificado['data_emissao']))) ?>.
                </p>

                <div class="certificado-assinaturas">
                    <div class="certificado-assinatura">
                        <?php if (!empty($instrutor['assinatura_imagem'])): ?>
                            <img src="certificado-assinatura.php?codigo=<?= e($certificado['numero_hash']) ?>&papel=instrutor" alt="Assinatura do instrutor" />
                        <?php endif; ?>
                        <p class="certificado-assinatura-nome"><?= e($instrutor['nome_completo'] ?? '—') ?></p>
                        <p class="certificado-assinatura-cargo">Instrutor(a)<?= !empty($instrutor['numero_registro']) ? ' · ' . e($instrutor['numero_registro']) : '' ?></p>
                    </div>
                    <div class="certificado-assinatura">
                        <?php if (!empty($administrativo['assinatura_imagem'])): ?>
                            <img src="certificado-assinatura.php?codigo=<?= e($certificado['numero_hash']) ?>&papel=administrativo" alt="Assinatura do administrativo" />
                        <?php endif; ?>
                        <p class="certificado-assinatura-nome"><?= e($administrativo['nome'] ?? '—') ?></p>
                        <p class="certificado-assinatura-cargo"><?= e(ucfirst($administrativo['nivel'] ?? 'Administrativo')) ?></p>
                    </div>
                </div>

                <p class="certificado-codigo">Código de verificação: <strong><?= e($certificado['numero_hash']) ?></strong> · confira em <?= e(site_base_url()) ?>/certificado.php</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
