<?php
/** Funções de apoio para ler e formatar o catálogo de produtos (data/produtos.json). */

const PRODUTO_TIPO_LABELS = [
    'card' => 'Card',
    'kit'  => 'Kit de treinamento',
    'book' => 'Book',
];

/** Carrega os produtos, mais recentes primeiro. */
function load_produtos(): array
{
    $path = __DIR__ . '/../data/produtos.json';
    if (!is_file($path)) {
        return [];
    }

    $json = file_get_contents($path);
    $produtos = json_decode($json, true);
    if (!is_array($produtos)) {
        return [];
    }

    return $produtos;
}

/** Busca um produto específico pelo slug (para a página individual de venda). */
function find_produto(string $slug): ?array
{
    foreach (load_produtos() as $produto) {
        if (($produto['slug'] ?? '') === $slug) {
            return $produto;
        }
    }
    return null;
}

/** [label, classe css] para o status de estoque do produto. */
function produto_status(int $estoque): array
{
    if ($estoque <= 0) {
        return ['Esgotado', 'status-out'];
    }
    if ($estoque <= 3) {
        return ['Últimas unidades', 'status-last'];
    }
    return ['Disponível', 'status-open'];
}

/** Rótulo legível do tipo de produto (card/kit/book). */
function produto_tipo_label(string $tipo): string
{
    return PRODUTO_TIPO_LABELS[$tipo] ?? ucfirst($tipo);
}
