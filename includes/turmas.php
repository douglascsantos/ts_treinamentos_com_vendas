<?php
/** Funções de apoio para ler e formatar a agenda de turmas (data/turmas.json). */

const TURMA_STATUS_LABELS = [
    'aberta'   => ['Vagas abertas', 'status-open'],
    'ultimas'  => ['Últimas vagas', 'status-last'],
    'esgotada' => ['Esgotado', 'status-out'],
];

const MESES_ABREV_PT = [
    1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
    7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez',
];

/** Carrega e ordena as turmas por data mais próxima primeiro. */
function load_turmas(): array
{
    $path = __DIR__ . '/../data/turmas.json';
    if (!is_file($path)) {
        return [];
    }

    $json = file_get_contents($path);
    $turmas = json_decode($json, true);
    if (!is_array($turmas)) {
        return [];
    }

    usort($turmas, function ($a, $b) {
        return ($a['datas'][0] ?? '') <=> ($b['datas'][0] ?? '');
    });

    return $turmas;
}

/** Busca uma turma específica pelo slug (para a página individual do curso). */
function find_turma(string $slug): ?array
{
    foreach (load_turmas() as $turma) {
        if (($turma['slug'] ?? '') === $slug) {
            return $turma;
        }
    }
    return null;
}

/**
 * Turma desabilitada pelo administrativo (nunca excluída — só marcada
 * `ativo: false`) não aparece nem pode ser comprada no site público. Pedidos
 * já feitos continuam mostrando os dados normalmente (ver minha-conta.php,
 * que não usa esse filtro de propósito).
 */
function turma_ativa(array $turma): bool
{
    return ($turma['ativo'] ?? true) !== false;
}

/** Turma concluída (diplomas já gerados) — não pode ser concluída de novo. */
function turma_concluida(array $turma): bool
{
    return ($turma['concluida'] ?? false) === true;
}

/**
 * Campos que uma turma precisa ter preenchidos antes de poder ser concluída
 * (gerar diploma sem instrutor/administrativo responsável ou sem carga
 * horária não faz sentido — ambos assinam o diploma, ver includes/certificados.php).
 * Retorna a lista de campos faltando (vazia = pronta pra concluir).
 */
function turma_campos_faltando_para_concluir(array $turma): array
{
    $faltando = [];
    if (empty($turma['curso'])) {
        $faltando[] = 'Nome do curso';
    }
    if (empty($turma['datas'])) {
        $faltando[] = 'Datas';
    }
    if (empty($turma['horario'])) {
        $faltando[] = 'Horário';
    }
    if (empty($turma['carga_horaria'])) {
        $faltando[] = 'Carga horária';
    }
    if (empty($turma['codigo_turma'])) {
        $faltando[] = 'Código da turma';
    }
    if (empty($turma['instrutor_id'])) {
        $faltando[] = 'Instrutor responsável';
    }
    if (empty($turma['administrativo_id'])) {
        $faltando[] = 'Administrativo responsável';
    }
    return $faltando;
}

/** [label, classe css] para o status da turma. */
function turma_status(string $status): array
{
    return TURMA_STATUS_LABELS[$status] ?? TURMA_STATUS_LABELS['aberta'];
}

/** Dia + mês abreviado (pt-BR) da primeira data, para o selo do card. */
function turma_card_date(array $datas): array
{
    $primeira = $datas[0] ?? null;
    if (!$primeira) {
        return ['dia' => '--', 'mes' => '--'];
    }
    $ts = strtotime($primeira);
    return [
        'dia' => date('d', $ts),
        'mes' => MESES_ABREV_PT[(int) date('n', $ts)],
    ];
}

/** Todas as datas formatadas por extenso (dd/mm/aaaa), separadas por vírgula/"e". */
function turma_dates_full(array $datas): string
{
    $formatadas = array_map(fn ($d) => date('d/m/Y', strtotime($d)), $datas);
    if (count($formatadas) === 1) {
        return $formatadas[0];
    }
    $ultima = array_pop($formatadas);
    return implode(', ', $formatadas) . ' e ' . $ultima;
}

/**
 * Escrita usada só pelo painel administrativo (equipe/turmas.php). O site
 * público e o agente agenda-sync continuam só lendo via load_turmas()/find_turma().
 */
function save_turmas(array $turmas): void
{
    $path = __DIR__ . '/../data/turmas.json';
    file_put_contents($path, json_encode(array_values($turmas), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
}

function atualizar_turma(string $slug, array $changes): ?array
{
    $turmas = load_turmas();
    $atualizada = null;
    foreach ($turmas as &$t) {
        if ($t['slug'] === $slug) {
            $t = array_merge($t, $changes);
            $atualizada = $t;
            break;
        }
    }
    unset($t);
    if ($atualizada) {
        save_turmas($turmas);
    }
    return $atualizada;
}

function criar_turma(array $dados): array
{
    $turmas = load_turmas();
    $turmas[] = $dados;
    save_turmas($turmas);
    return $dados;
}

