<?php
/**
 * Gestão de turmas: lista primeiro, "+" revela o formulário de criação,
 * clicar numa turma abre o mesmo formulário pra editar (Salvar/Cancelar),
 * "Desabilitar"/"Reativar" no lugar de excluir — turma desabilitada some do
 * site público e não pode mais ser comprada, mas continua no banco (pedidos
 * antigos que referenciam ela continuam mostrando os dados certos). Continua
 * escrevendo em data/turmas.json — o mesmo arquivo que o site público e o
 * agente agenda-sync leem (ver tools/sync_agenda.py, que preserva esses
 * campos ao re-sincronizar).
 */
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/administradores.php';
require __DIR__ . '/../includes/instrutores.php';
require __DIR__ . '/../includes/turmas.php';
require_once __DIR__ . '/../includes/storage.php';
require __DIR__ . '/../includes/alunos.php';
require __DIR__ . '/../includes/pedidos.php';
require __DIR__ . '/../includes/certificados.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/equipe.php';

$staff = equipe_exigir_login(['administrador']);

$mensagens = [];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_turma') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $slugExistente = $_POST['slug_original'] ?? '';
        $editando = $slugExistente !== '' ? find_turma($slugExistente) : null;

        $curso = trim($_POST['curso'] ?? '');
        $datasRaw = trim($_POST['datas'] ?? '');
        $horario = trim($_POST['horario'] ?? '');
        $local = trim($_POST['local'] ?? '');
        $preco = (float) str_replace(',', '.', $_POST['preco'] ?? '0');
        $cargaHoraria = (int) ($_POST['carga_horaria'] ?? 0);
        $codigoTurma = trim($_POST['codigo_turma'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $vagasTotal = (int) ($_POST['vagas_total'] ?? 0);
        $vagasOcupadas = (int) ($_POST['vagas_ocupadas'] ?? 0);
        $status = $_POST['status'] ?? 'aberta';
        $instrutorId = $_POST['instrutor_id'] !== '' ? $_POST['instrutor_id'] : null;
        $administrativoId = ($_POST['administrativo_id'] ?? '') !== '' ? $_POST['administrativo_id'] : null;

        $datasIso = [];
        foreach (array_filter(array_map('trim', explode(',', $datasRaw))) as $d) {
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
        }

        if (!$erros && $editando) {
            $imagemNome = $editando['imagem'] ?? null;
            if (!empty($_FILES['imagem']['name'])) {
                $info = @getimagesize($_FILES['imagem']['tmp_name'] ?? '');
                if ($info && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                    $imagemNome = $editando['slug'] . '.jpg';
                    @move_uploaded_file($_FILES['imagem']['tmp_name'], __DIR__ . '/../assets/images/cursos/' . $imagemNome);
                }
            }
            atualizar_turma($editando['slug'], [
                'curso' => $curso, 'datas' => $datasIso, 'horario' => $horario,
                'local' => $local !== '' ? $local : 'Presencial · São José do Rio Preto',
                'preco' => $preco, 'carga_horaria' => $cargaHoraria,
                'codigo_turma' => $codigoTurma !== '' ? $codigoTurma : ($editando['codigo_turma'] ?? ''),
                'vagas_total' => $vagasTotal, 'vagas_ocupadas' => $vagasOcupadas,
                'status' => $status, 'instrutor_id' => $instrutorId, 'administrativo_id' => $administrativoId,
                'imagem' => $imagemNome, 'descricao' => $descricao,
            ]);
            $mensagens[] = 'Turma atualizada.';
        } elseif (!$erros) {
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
                criar_turma([
                    'slug' => $slug, 'curso' => $curso, 'datas' => $datasIso, 'horario' => $horario,
                    'local' => $local !== '' ? $local : 'Presencial · São José do Rio Preto',
                    'preco' => $preco, 'status' => 'aberta', 'imagem' => $imagemNome, 'descricao' => $descricao,
                    'codigo_turma' => $codigoTurma !== '' ? $codigoTurma : strtoupper($slug) . '-' . date('ymd'),
                    'carga_horaria' => $cargaHoraria, 'vagas_total' => $vagasTotal, 'vagas_ocupadas' => 0,
                    'instrutor_id' => $instrutorId, 'administrativo_id' => $administrativoId, 'ativo' => true,
                    'concluida' => false, 'instrutor_finalizou' => false, 'avaliacoes' => [],
                ]);
                $cursoPageTemplate = "<?php\nrequire __DIR__ . '/../includes/functions.php';\n"
                    . "require __DIR__ . '/../includes/env.php';\nrequire __DIR__ . '/../includes/turmas.php';\n"
                    . "require __DIR__ . '/../includes/curso-page.php';\nrender_curso_page('{$slug}');\n";
                file_put_contents(__DIR__ . '/../cursos/' . $slug . '.php', $cursoPageTemplate);
                $mensagens[] = 'Turma "' . $curso . '" criada.';
            }
        }

        if (!$erros) {
            header('Location: turmas.php');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['desativar_turma', 'ativar_turma'], true)) {
    if (csrf_verify($_POST['csrf_token'] ?? null)) {
        atualizar_turma($_POST['slug'] ?? '', ['ativo' => $_POST['acao'] === 'ativar_turma']);
    }
    header('Location: turmas.php');
    exit;
}

/** Tela de conclusão: valida pré-requisitos, monta o checklist de quem recebe diploma. */
$concluindo = null;
$matriculadosConclusao = [];
if (isset($_GET['concluir'])) {
    $concluindo = find_turma($_GET['concluir']);
    if (!$concluindo) {
        header('Location: turmas.php');
        exit;
    }
    if (turma_ativa($concluindo)) {
        $erros[] = 'Desabilite a turma antes de concluir.';
        $concluindo = null;
    } elseif (turma_concluida($concluindo)) {
        header('Location: turmas.php');
        exit;
    } elseif (!($concluindo['instrutor_finalizou'] ?? false)) {
        $erros[] = 'O instrutor ainda não finalizou a presença/nota dessa turma — peça pra ele concluir primeiro (ver equipe/minha-turma.php).';
        $concluindo = null;
    } else {
        $faltando = turma_campos_faltando_para_concluir($concluindo);
        if ($faltando) {
            header('Location: turmas.php?editar=' . rawurlencode($concluindo['slug']) . '&faltando=' . rawurlencode(implode(',', $faltando)));
            exit;
        }
        $avaliacoesPorAluno = [];
        foreach ($concluindo['avaliacoes'] ?? [] as $av) {
            $avaliacoesPorAluno[$av['aluno_id']] = $av;
        }
        foreach (pedidos_pagos_por_turma($concluindo['slug']) as $pedido) {
            $aluno = find_aluno_by_id($pedido['aluno_id']);
            if (!$aluno) {
                continue;
            }
            $matriculadosConclusao[] = [
                'aluno' => $aluno,
                'pedido' => $pedido,
                'avaliacao' => $avaliacoesPorAluno[$aluno['id']] ?? null,
            ];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'confirmar_conclusao') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Sessão expirada, tente de novo.';
    } else {
        $turma = find_turma($_POST['slug'] ?? '');
        $faltando = $turma ? turma_campos_faltando_para_concluir($turma) : ['Turma não encontrada'];
        if (!$turma || $faltando || turma_concluida($turma) || turma_ativa($turma)) {
            $erros[] = 'Não foi possível concluir essa turma — confira os dados e tente de novo.';
        } else {
            $selecionados = array_map('strval', $_POST['diploma_alunos'] ?? []);
            $observacao = trim($_POST['observacao_conclusao'] ?? '');
            $hoje = date('Y-m-d');
            $diplomasGerados = [];

            foreach (pedidos_pagos_por_turma($turma['slug']) as $pedido) {
                if (!in_array($pedido['aluno_id'], $selecionados, true)) {
                    continue;
                }
                $aluno = find_aluno_by_id($pedido['aluno_id']);
                if (!$aluno) {
                    continue;
                }
                criar_certificado([
                    'pedido_id'          => $pedido['order_nsu'],
                    'aluno_id'           => $aluno['id'],
                    'turma_slug'         => $turma['slug'],
                    'aluno_nome'         => trim(($aluno['pronome'] ?? '') . ' ' . $aluno['nome']),
                    'curso_nome'         => $turma['curso'],
                    'carga_horaria'      => $turma['carga_horaria'],
                    'data_emissao'       => $hoje,
                    'instrutor_id'       => $turma['instrutor_id'],
                    'administrativo_id'  => $turma['administrativo_id'],
                ]);
                $diplomasGerados[] = $aluno['id'];
            }

            atualizar_turma($turma['slug'], [
                'concluida' => true, 'concluida_em' => date('Y-m-d H:i:s'),
                'observacao_conclusao' => $observacao, 'diploma_alunos' => $diplomasGerados,
            ]);
            $mensagens[] = 'Turma concluída — ' . count($diplomasGerados) . ' diploma(s) gerado(s).';
        }
        if (!$erros) {
            header('Location: turmas.php');
            exit;
        }
    }
}

$editando = null;
if (isset($_GET['editar'])) {
    $editando = find_turma($_GET['editar']);
}
$camposFaltandoAviso = isset($_GET['faltando']) ? explode(',', $_GET['faltando']) : [];

if ($editando) {
    $valores = [
        'slug_original' => $editando['slug'], 'curso' => $editando['curso'],
        'datas' => implode(', ', array_map(fn ($d) => date('d/m/Y', strtotime($d)), $editando['datas'])),
        'horario' => $editando['horario'], 'local' => $editando['local'], 'preco' => number_format((float) $editando['preco'], 2, ',', ''),
        'carga_horaria' => $editando['carga_horaria'] ?? 0, 'vagas_total' => $editando['vagas_total'] ?? 0,
        'vagas_ocupadas' => $editando['vagas_ocupadas'] ?? 0, 'codigo_turma' => $editando['codigo_turma'] ?? '',
        'status' => $editando['status'] ?? 'aberta', 'instrutor_id' => $editando['instrutor_id'] ?? '',
        'administrativo_id' => $editando['administrativo_id'] ?? '', 'descricao' => $editando['descricao'] ?? '',
    ];
} elseif ($erros && isset($_POST['curso'])) {
    $valores = [
        'slug_original' => $_POST['slug_original'] ?? '', 'curso' => $_POST['curso'], 'datas' => $_POST['datas'] ?? '',
        'horario' => $_POST['horario'] ?? '', 'local' => $_POST['local'] ?? '', 'preco' => $_POST['preco'] ?? '',
        'carga_horaria' => $_POST['carga_horaria'] ?? 0, 'vagas_total' => $_POST['vagas_total'] ?? 0,
        'vagas_ocupadas' => $_POST['vagas_ocupadas'] ?? 0, 'codigo_turma' => $_POST['codigo_turma'] ?? '',
        'status' => $_POST['status'] ?? 'aberta', 'instrutor_id' => $_POST['instrutor_id'] ?? '',
        'administrativo_id' => $_POST['administrativo_id'] ?? '', 'descricao' => $_POST['descricao'] ?? '',
    ];
} else {
    $valores = [
        'slug_original' => '', 'curso' => '', 'datas' => '', 'horario' => '', 'local' => '', 'preco' => '',
        'carga_horaria' => 0, 'vagas_total' => 0, 'vagas_ocupadas' => 0, 'codigo_turma' => '',
        'status' => 'aberta', 'instrutor_id' => '', 'administrativo_id' => '', 'descricao' => '',
    ];
}
$formAberto = $editando !== null || (bool) $erros;

$turmas = ordenar_ativos_primeiro(load_turmas(), 'turma_ativa');
$erroBanco = null;
try {
    $instrutores = listar_instrutores();
    $administradores = listar_administradores();
} catch (Throwable $e) {
    $erroBanco = equipe_erro_tecnico($e);
    $instrutores = [];
    $administradores = [];
}
$instrutoresPorId = [];
foreach ($instrutores as $i) {
    $instrutoresPorId[$i['id']] = $i['nome_completo'];
}
$administradoresPorId = [];
foreach ($administradores as $a) {
    $administradoresPorId[$a['id']] = $a['nome'];
}

equipe_header_html('Turmas', $staff['nome']);
?>

<?php if ($staff['nivel'] === 'diretor'): ?><p style="margin-bottom:1.25rem;"><a href="diretor.php">← Painel do diretor</a></p><?php endif; ?>

<?php if ($erroBanco): ?>
    <div class="form-errors-box">Não foi possível carregar instrutores/administradores agora — a lista de turmas continua abaixo, mas os campos de responsável podem estar vazios. <?= e($erroBanco) ?></div>
<?php endif; ?>
<?php foreach ($mensagens as $m): ?>
    <div class="form-errors-box" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#15803d;"><?= e($m) ?></div>
<?php endforeach; ?>
<?php if ($erros): ?>
    <div class="form-errors-box"><ul><?php foreach ($erros as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if ($camposFaltandoAviso): ?>
    <div class="form-errors-box">Preencha antes de concluir a turma: <strong><?= e(implode(', ', $camposFaltandoAviso)) ?></strong>.</div>
<?php endif; ?>

<?php if ($concluindo): ?>
<div class="equipe-card">
    <h3>Concluir turma: <?= e($concluindo['curso']) ?></h3>
    <p class="muted" style="margin-bottom:1.25rem;">Selecione quem recebe diploma e confirme. Essa ação gera os certificados e não pode ser desfeita.</p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="confirmar_conclusao" />
        <input type="hidden" name="slug" value="<?= e($concluindo['slug']) ?>" />
        <?php if (!$matriculadosConclusao): ?>
            <p class="equipe-empty">Nenhum aluno matriculado (pedido pago) nessa turma.</p>
        <?php else: ?>
            <div class="equipe-list">
                <?php foreach ($matriculadosConclusao as $m): ?>
                    <?php $av = $m['avaliacao']; ?>
                    <label class="equipe-list-item" style="cursor:pointer;">
                        <span class="equipe-list-link" style="flex:1;">
                            <input type="checkbox" name="diploma_alunos[]" value="<?= e($m['aluno']['id']) ?>" <?= (!$av || ($av['presenca'] ?? false)) ? 'checked' : '' ?> style="margin-right:.6rem;width:18px;height:18px;vertical-align:middle;" />
                            <?= e(trim(($m['aluno']['pronome'] ?? '') . ' ' . $m['aluno']['nome'])) ?>
                            <span class="equipe-list-meta">
                                <?php if ($av): ?>
                                    Presença: <?= ($av['presenca'] ?? false) ? 'sim' : 'não' ?><?= isset($av['nota']) && $av['nota'] !== null ? ' · Nota: ' . e((string) $av['nota']) : '' ?>
                                <?php else: ?>
                                    Sem registro do instrutor ainda
                                <?php endif; ?>
                            </span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="form-field" style="margin-top:1.25rem;">
            <label>Observação interna (só administrativo/diretor veem — nunca aparece pro aluno ou instrutor)</label>
            <input type="text" name="observacao_conclusao" placeholder="ex: turma concluída sem intercorrências" />
        </div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem;">
            <button type="submit" class="btn btn-primary">Confirmar conclusão e gerar diplomas</button>
            <a class="btn btn-outline-dark" href="turmas.php">Cancelar</a>
        </div>
    </form>
</div>
<?php else: ?>

<div class="equipe-card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;">
        <h3 style="margin:0;">Turmas (<?= count($turmas) ?>)</h3>
        <?php if (!$editando): ?>
            <button type="button" class="btn-add-toggle" data-toggle-target="formTurma" data-toggle-close-text="Cancelar">+ Nova turma</button>
        <?php endif; ?>
    </div>

    <?php if (!$turmas): ?>
        <p class="equipe-empty">Nenhuma turma cadastrada ainda.</p>
    <?php else: ?>
        <div class="equipe-list">
            <?php foreach ($turmas as $t): ?>
                <?php $ativa = turma_ativa($t); ?>
                <div class="equipe-list-item">
                    <a class="equipe-list-link" href="turmas.php?editar=<?= e($t['slug']) ?>">
                        <?= e($t['curso']) ?>
                        <span class="equipe-list-meta">
                            <?= e(turma_dates_full($t['datas'])) ?> às <?= e($t['horario']) ?> · <?= e(format_price((float) $t['preco'])) ?>
                            <?php if (!empty($t['instrutor_id']) && isset($instrutoresPorId[$t['instrutor_id']])): ?> · <?= e($instrutoresPorId[$t['instrutor_id']]) ?><?php endif; ?>
                        </span>
                    </a>
                    <div class="equipe-list-actions">
                        <?php if (turma_concluida($t)): ?>
                            <span class="badge-ativo">Concluída · diplomas emitidos</span>
                        <?php else: ?>
                            <span class="<?= $ativa ? 'badge-ativo' : 'badge-inativo' ?>"><?= $ativa ? 'Ativa' : 'Inativa' ?></span>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="acao" value="<?= $ativa ? 'desativar_turma' : 'ativar_turma' ?>" />
                                <input type="hidden" name="slug" value="<?= e($t['slug']) ?>" />
                                <button type="submit" class="<?= $ativa ? 'btn-disable' : 'btn-enable' ?>"><?= $ativa ? 'Desabilitar' : 'Reativar' ?></button>
                            </form>
                            <?php if (!$ativa): ?>
                                <?php if ($t['instrutor_finalizou'] ?? false): ?>
                                    <a class="btn-enable" href="turmas.php?concluir=<?= e($t['slug']) ?>" style="text-decoration:none;display:inline-flex;align-items:center;">Concluir turma</a>
                                <?php else: ?>
                                    <span class="equipe-list-meta" style="width:100%;">Aguardando o instrutor finalizar</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="toggle-form" id="formTurma" <?= $formAberto ? '' : 'hidden' ?>>
        <h4 style="margin-bottom:1rem;"><?= $editando ? 'Editar turma' : 'Nova turma' ?></h4>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="acao" value="salvar_turma" />
            <input type="hidden" name="slug_original" value="<?= e($valores['slug_original']) ?>" />
            <div class="form-field"><label>Curso</label><input type="text" name="curso" value="<?= e($valores['curso']) ?>" required /></div>
            <div class="form-field"><label>Datas (DD/MM/AAAA, separadas por vírgula se houver mais de uma)</label><input type="text" name="datas" value="<?= e($valores['datas']) ?>" placeholder="08/08/2026, 15/08/2026" required /></div>
            <div class="equipe-form-row">
                <div class="form-field"><label>Horário</label><input type="text" name="horario" value="<?= e($valores['horario']) ?>" placeholder="09:00" required /></div>
                <div class="form-field"><label>Preço (R$)</label><input type="text" name="preco" value="<?= e((string) $valores['preco']) ?>" placeholder="290,00" required /></div>
            </div>
            <div class="form-field"><label>Local</label><input type="text" name="local" value="<?= e($valores['local']) ?>" placeholder="Presencial · São José do Rio Preto" /></div>
            <div class="equipe-form-row">
                <div class="form-field"><label>Carga horária (horas)</label><input type="number" name="carga_horaria" min="0" value="<?= e((string) $valores['carga_horaria']) ?>" /></div>
                <div class="form-field"><label>Vagas totais (interno)</label><input type="number" name="vagas_total" min="0" value="<?= e((string) $valores['vagas_total']) ?>" /></div>
            </div>
            <?php if ($editando): ?>
                <div class="equipe-form-row">
                    <div class="form-field"><label>Vagas ocupadas</label><input type="number" name="vagas_ocupadas" min="0" value="<?= e((string) $valores['vagas_ocupadas']) ?>" /></div>
                    <div class="form-field">
                        <label>Status público</label>
                        <select name="status">
                            <?php foreach (['aberta' => 'Vagas abertas', 'ultimas' => 'Últimas vagas', 'esgotada' => 'Esgotado'] as $v => $l): ?>
                                <option value="<?= e($v) ?>" <?= $valores['status'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php else: ?>
                <input type="hidden" name="vagas_ocupadas" value="0" />
            <?php endif; ?>
            <div class="equipe-form-row">
                <div class="form-field"><label>Código da turma (opcional)</label><input type="text" name="codigo_turma" value="<?= e($valores['codigo_turma']) ?>" placeholder="CALC-2607-01" /></div>
                <div class="form-field">
                    <label>Instrutor responsável</label>
                    <select name="instrutor_id">
                        <option value="">-- Sem instrutor ainda --</option>
                        <?php foreach ($instrutores as $i): ?>
                            <option value="<?= e($i['id']) ?>" <?= $valores['instrutor_id'] === $i['id'] ? 'selected' : '' ?>><?= e($i['nome_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-field">
                <label>Administrativo responsável (assina o diploma junto com o instrutor)</label>
                <select name="administrativo_id">
                    <option value="">-- Sem administrativo ainda --</option>
                    <?php foreach ($administradores as $a): ?>
                        <option value="<?= e($a['id']) ?>" <?= $valores['administrativo_id'] === $a['id'] ? 'selected' : '' ?>><?= e($a['nome']) ?> (<?= e(ucfirst($a['nivel'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field"><label>Imagem<?= $editando ? ' (deixe em branco pra manter a atual)' : '' ?></label><input type="file" name="imagem" accept="image/*" <?= $editando ? '' : 'required' ?> /></div>
            <div class="form-field"><label>Descrição</label><input type="text" name="descricao" value="<?= e($valores['descricao']) ?>" /></div>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.5rem;">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <a class="btn btn-outline-dark" href="turmas.php">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php equipe_footer_html(); ?>
