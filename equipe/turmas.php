<?php
/**
 * Gestão de turmas (administrativo/diretor): cria/remove curso, edita vagas
 * (interno, nunca aparece pro cliente), carga horária, status público e
 * atribui instrutor. Continua escrevendo em data/turmas.json — o mesmo
 * arquivo que o site público e o agente agenda-sync leem (ver
 * tools/sync_agenda.py, que agora preserva esses campos ao re-sincronizar).
 */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/administradores.php';
require __DIR__ . '/../includes/instrutores.php';
require __DIR__ . '/../includes/turmas.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['administrador']);

$mensagens = [];
$erros = [];
$acao = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['acao'] ?? '') : '';

if ($acao === 'criar_turma') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $curso = trim($_POST['curso'] ?? '');
        $datasRaw = trim($_POST['datas'] ?? '');
        $horario = trim($_POST['horario'] ?? '');
        $local = trim($_POST['local'] ?? '');
        $preco = (float) str_replace(',', '.', $_POST['preco'] ?? '0');
        $cargaHoraria = (int) ($_POST['carga_horaria'] ?? 0);
        $codigoTurma = trim($_POST['codigo_turma'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');

        $datas = array_values(array_filter(array_map('trim', explode(',', $datasRaw))));
        $datasIso = [];
        foreach ($datas as $d) {
            $ts = DateTime::createFromFormat('d/m/Y', $d);
            if ($ts) {
                $datasIso[] = $ts->format('Y-m-d');
            }
        }
        sort($datasIso);

        if ($curso === '') {
            $erros[] = 'Informe o nome do curso.';
        } elseif (!$datasIso) {
            $erros[] = 'Informe pelo menos uma data válida (formato DD/MM/AAAA, separadas por vírgula).';
        } elseif ($horario === '') {
            $erros[] = 'Informe o horário.';
        } elseif ($preco <= 0) {
            $erros[] = 'Informe um preço válido.';
        } else {
            $slug = slugify($curso);
            if (find_turma($slug)) {
                $erros[] = 'Já existe uma turma com esse nome (slug "' . $slug . '").';
            } else {
                $imagemNome = null;
                if (!empty($_FILES['imagem']['name'])) {
                    $info = @getimagesize($_FILES['imagem']['tmp_name'] ?? '');
                    if ($info && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                        $imagemNome = $slug . '.jpg';
                        @move_uploaded_file($_FILES['imagem']['tmp_name'], __DIR__ . '/../assets/images/cursos/' . $imagemNome);
                    }
                }

                $turma = [
                    'slug'            => $slug,
                    'curso'           => $curso,
                    'datas'           => $datasIso,
                    'horario'         => $horario,
                    'local'           => $local !== '' ? $local : 'Presencial · São José do Rio Preto',
                    'preco'           => $preco,
                    'status'          => 'aberta',
                    'imagem'          => $imagemNome,
                    'descricao'       => $descricao,
                    'codigo_turma'    => $codigoTurma !== '' ? $codigoTurma : strtoupper($slug) . '-' . date('ymd'),
                    'carga_horaria'   => $cargaHoraria,
                    'vagas_total'     => (int) ($_POST['vagas_total'] ?? 0),
                    'vagas_ocupadas'  => 0,
                    'instrutor_id'    => $_POST['instrutor_id'] !== '' ? $_POST['instrutor_id'] : null,
                ];
                criar_turma($turma);

                $cursoPageTemplate = "<?php\nrequire __DIR__ . '/../includes/functions.php';\n"
                    . "require __DIR__ . '/../includes/env.php';\nrequire __DIR__ . '/../includes/turmas.php';\n"
                    . "require __DIR__ . '/../includes/curso-page.php';\nrender_curso_page('{$slug}');\n";
                file_put_contents(__DIR__ . '/../cursos/' . $slug . '.php', $cursoPageTemplate);

                $mensagens[] = 'Turma "' . $curso . '" criada.';
            }
        }
    }
}

if ($acao === 'atualizar_turma') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $slug = $_POST['slug'] ?? '';
        atualizar_turma($slug, [
            'vagas_total'    => (int) ($_POST['vagas_total'] ?? 0),
            'vagas_ocupadas' => (int) ($_POST['vagas_ocupadas'] ?? 0),
            'carga_horaria'  => (int) ($_POST['carga_horaria'] ?? 0),
            'codigo_turma'   => trim($_POST['codigo_turma'] ?? ''),
            'status'         => $_POST['status'] ?? 'aberta',
            'instrutor_id'   => $_POST['instrutor_id'] !== '' ? $_POST['instrutor_id'] : null,
        ]);
        $mensagens[] = 'Turma atualizada.';
    }
}

if ($acao === 'remover_turma') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $slug = $_POST['slug'] ?? '';
        if (remover_turma($slug)) {
            @unlink(__DIR__ . '/../cursos/' . $slug . '.php');
            $mensagens[] = 'Turma removida.';
        } else {
            $erros[] = 'Turma não encontrada.';
        }
    }
}

$turmas = load_turmas();
$instrutores = listar_instrutores();
$instrutoresPorId = [];
foreach ($instrutores as $i) {
    $instrutoresPorId[$i['id']] = $i['nome_completo'];
}

equipe_header_html('Turmas', $staff['nome']);
?>

<?php foreach ($mensagens as $m): ?>
    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;"><?= e($m) ?></div>
<?php endforeach; ?>
<?php if ($erros): ?>
    <div class="form-errors-box"><ul><?php foreach ($erros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="equipe-card">
    <h3>Criar turma</h3>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="criar_turma" />
        <div class="form-field"><label>Curso</label><input type="text" name="curso" required /></div>
        <div class="form-field"><label>Datas (DD/MM/AAAA, separadas por vírgula se houver mais de uma)</label><input type="text" name="datas" placeholder="08/08/2026, 15/08/2026" required /></div>
        <div class="form-row">
            <div class="form-field"><label>Horário</label><input type="text" name="horario" placeholder="09:00" required /></div>
            <div class="form-field"><label>Preço (R$)</label><input type="text" name="preco" placeholder="290,00" required /></div>
        </div>
        <div class="form-field"><label>Local</label><input type="text" name="local" placeholder="Presencial · São José do Rio Preto" /></div>
        <div class="form-row">
            <div class="form-field"><label>Carga horária (horas)</label><input type="number" name="carga_horaria" min="0" value="0" /></div>
            <div class="form-field"><label>Vagas totais (interno, não aparece pro cliente)</label><input type="number" name="vagas_total" min="0" value="0" /></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label>Código da turma (opcional)</label><input type="text" name="codigo_turma" placeholder="CALC-2607-01" /></div>
            <div class="form-field">
                <label>Instrutor responsável</label>
                <select name="instrutor_id">
                    <option value="">-- Sem instrutor ainda --</option>
                    <?php foreach ($instrutores as $i): ?>
                        <option value="<?= e($i['id']) ?>"><?= e($i['nome_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-field"><label>Imagem</label><input type="file" name="imagem" accept="image/*" /></div>
        <div class="form-field"><label>Descrição</label><input type="text" name="descricao" /></div>
        <button type="submit" class="btn btn-primary">Criar turma</button>
    </form>
</div>

<div class="equipe-card">
    <h3>Turmas (<?= count($turmas) ?>)</h3>
    <?php foreach ($turmas as $t): ?>
        <div style="border-top:1px solid var(--border);padding:1rem 0;">
            <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:.5rem;align-items:baseline;">
                <strong><?= e($t['curso']) ?></strong>
                <span class="muted" style="font-size:.82rem;"><?= e(turma_dates_full($t['datas'])) ?> às <?= e($t['horario']) ?> · <?= e(format_price((float) $t['preco'])) ?></span>
            </div>
            <form method="post" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;margin-top:.65rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="acao" value="atualizar_turma" />
                <input type="hidden" name="slug" value="<?= e($t['slug']) ?>" />
                <div class="form-field" style="margin:0;"><label>Código</label><input type="text" name="codigo_turma" value="<?= e($t['codigo_turma'] ?? '') ?>" style="width:140px;" /></div>
                <div class="form-field" style="margin:0;"><label>Carga horária</label><input type="number" name="carga_horaria" min="0" value="<?= e((string) ($t['carga_horaria'] ?? 0)) ?>" style="width:90px;" /></div>
                <div class="form-field" style="margin:0;"><label>Vagas totais</label><input type="number" name="vagas_total" min="0" value="<?= e((string) ($t['vagas_total'] ?? 0)) ?>" style="width:90px;" /></div>
                <div class="form-field" style="margin:0;"><label>Vagas ocupadas</label><input type="number" name="vagas_ocupadas" min="0" value="<?= e((string) ($t['vagas_ocupadas'] ?? 0)) ?>" style="width:90px;" /></div>
                <div class="form-field" style="margin:0;">
                    <label>Status público</label>
                    <select name="status" style="width:130px;">
                        <?php foreach (['aberta' => 'Vagas abertas', 'ultimas' => 'Últimas vagas', 'esgotada' => 'Esgotado'] as $v => $l): ?>
                            <option value="<?= e($v) ?>" <?= ($t['status'] ?? '') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field" style="margin:0;">
                    <label>Instrutor</label>
                    <select name="instrutor_id" style="width:170px;">
                        <option value="">-- Sem instrutor --</option>
                        <?php foreach ($instrutores as $i): ?>
                            <option value="<?= e($i['id']) ?>" <?= ($t['instrutor_id'] ?? '') === $i['id'] ? 'selected' : '' ?>><?= e($i['nome_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="padding:.55rem .9rem;font-size:.82rem;">Salvar</button>
            </form>
            <form method="post" onsubmit="return confirm('Remover essa turma? Isso não pode ser desfeito.');" style="margin-top:.5rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="acao" value="remover_turma" />
                <input type="hidden" name="slug" value="<?= e($t['slug']) ?>" />
                <button type="submit" class="btn btn-outline-dark" style="padding:.4rem .8rem;font-size:.78rem;">Remover turma</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<?php equipe_footer_html(); ?>
