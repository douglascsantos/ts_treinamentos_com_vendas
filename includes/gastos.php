<?php
/**
 * Gastos (tabela `gastos`, MySQL) — controle interno pra saber quanto a
 * empresa gasta. Nunca aparece pro cliente.
 */

const GASTO_TIPOS = [
    'curso'           => 'Por curso',
    'fixa_mensal'     => 'Despesa fixa mensal',
    'variavel_unica'  => 'Despesa variável única',
    'patrimonio'      => 'Imóvel / equipamento',
];

const GASTO_CATEGORIAS = [
    'curso' => [
        'materiais' => 'Materiais', 'apostilas' => 'Apostilas', 'coffee_break' => 'Coffee break',
        'impressoes' => 'Impressões', 'professor' => 'Pagamento de professor',
        'terceirizado' => 'Pagamento de terceirizado', 'outros_curso' => 'Outros',
    ],
    'fixa_mensal' => [
        'aluguel' => 'Aluguel', 'condominio' => 'Condomínio', 'luz' => 'Luz', 'internet' => 'Internet',
        'alarme' => 'Alarme', 'equipamento_fixo' => 'Equipamentos', 'financiamento' => 'Financiamento',
    ],
    'variavel_unica' => ['variavel_outros' => 'Outros'],
    'patrimonio' => [
        'imovel' => 'Compra de imóvel', 'obra_benfeitoria' => 'Obra / benfeitoria',
        'equipamento_compra' => 'Compra de equipamento',
    ],
];

/** $dados: tipo, categoria, turma_slug (nullable), descricao, valor, data_gasto, criado_por. */
function criar_gasto(array $dados): array
{
    $id = gerar_id('GA');
    $stmt = db()->prepare(
        'INSERT INTO gastos (id, tipo, categoria, turma_slug, descricao, valor, data_gasto, criado_por, criado_em)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $id, $dados['tipo'], $dados['categoria'], $dados['turma_slug'] ?: null,
        $dados['descricao'] ?: null, (float) $dados['valor'], $dados['data_gasto'],
        $dados['criado_por'], date('Y-m-d H:i:s'),
    ]);
    $stmt = db()->prepare('SELECT * FROM gastos WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function listar_gastos(): array
{
    return db()->query('SELECT * FROM gastos ORDER BY data_gasto DESC')->fetchAll();
}

/** Soma total por tipo — pro resumo no topo da tela. */
function total_gastos_por_tipo(): array
{
    $rows = db()->query('SELECT tipo, SUM(valor) AS total FROM gastos GROUP BY tipo')->fetchAll();
    $totais = [];
    foreach ($rows as $r) {
        $totais[$r['tipo']] = (float) $r['total'];
    }
    return $totais;
}
