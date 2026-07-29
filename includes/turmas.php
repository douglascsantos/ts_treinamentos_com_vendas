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

/** Preço formatado em R$ pt-BR. */
function format_price(float $preco): string
{
    if ($preco <= 0) {
        return 'Gratuito';
    }
    return 'R$ ' . number_format($preco, 2, ',', '.');
}
