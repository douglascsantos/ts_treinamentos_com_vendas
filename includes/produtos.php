<?php
/** Funções de apoio para ler e formatar o catálogo de produtos (data/produtos.json). */

const PRODUTO_TIPO_LABELS = [
    'card' => 'Card',
    'kit'  => 'Kit de treinamento',
    'book' => 'Book (e-book em PDF)',
];

/** Tipos de produto que são entregues digitalmente (PDF) e por isso nunca esgotam. */
const PRODUTO_TIPOS_DIGITAIS = ['book'];

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

/** [label, classe css] para o status de estoque do produto.
 *  Produtos digitais (ver PRODUTO_TIPOS_DIGITAIS) ignoram o estoque —
 *  um e-book/PDF nunca "esgota". */
function produto_status(int $estoque, string $tipo = ''): array
{
    if (produto_e_digital($tipo)) {
        return ['Disponível', 'status-open'];
    }
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

/** Produto de entrega digital (PDF) — nunca esgota, não usa estoque físico. */
function produto_e_digital(string $tipo): bool
{
    return in_array($tipo, PRODUTO_TIPOS_DIGITAIS, true);
}

/**
 * Produto desabilitado pelo administrativo (nunca excluído — só marcado
 * `ativo: false`) não aparece nem pode ser comprado no site público. Pedidos
 * já feitos continuam mostrando os dados normalmente (mesmo princípio de
 * turma_ativa() em includes/turmas.php).
 */
function produto_ativo(array $produto): bool
{
    return ($produto['ativo'] ?? true) !== false;
}

/**
 * Escrita usada só pelo painel administrativo (equipe/produtos.php). O site
 * público e o agente produtos-sync continuam só lendo via load_produtos()/find_produto().
 */
function save_produtos(array $produtos): void
{
    $path = __DIR__ . '/../data/produtos.json';
    file_put_contents($path, json_encode(array_values($produtos), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
}

function criar_produto(array $dados): array
{
    $produtos = load_produtos();
    $produtos[] = $dados;
    save_produtos($produtos);
    return $dados;
}

function atualizar_produto(string $slug, array $changes): ?array
{
    $produtos = load_produtos();
    $atualizado = null;
    foreach ($produtos as &$p) {
        if ($p['slug'] === $slug) {
            $p = array_merge($p, $changes);
            $atualizado = $p;
            break;
        }
    }
    unset($p);
    if ($atualizado) {
        save_produtos($produtos);
    }
    return $atualizado;
}
