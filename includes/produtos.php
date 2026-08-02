<?php
/** Funções de apoio para ler e formatar o catálogo de produtos. */
require_once __DIR__ . '/storage.php';

const PRODUTO_TIPO_LABELS = [
    'card' => 'Card',
    'kit'  => 'Kit de treinamento',
    'book' => 'Book (e-book em PDF)',
];

/** Tipos de produto que são entregues digitalmente (PDF) e por isso nunca esgotam. */
const PRODUTO_TIPOS_DIGITAIS = ['book'];

const PRODUTOS_FILE = 'produtos.json';

/**
 * Carrega os produtos. Fonte principal é o mesmo diretório fora do repositório
 * usado por pedidos/alunos (ver includes/storage.php) — dessa forma um produto
 * criado ou editado pelo painel administrativo (equipe/produtos.php) sobrevive
 * a qualquer deploy de código, em vez de ser apagado no próximo `git push`
 * (era o que acontecia antes: `data/produtos.json` vive dentro do repositório,
 * e todo deploy sobrescrevia o arquivo de volta pro que estava commitado).
 *
 * `data/produtos.json` (dentro do repositório) continua existindo como a
 * "semente" alimentada pelo agente produtos-sync/tools/sync_produtos.py — todo
 * produto de lá que ainda não existe no catálogo "ao vivo" (por slug) é
 * importado automaticamente aqui, uma vez, sem nunca sobrescrever um produto
 * que já existe (a edição feita pelo painel sempre prevalece).
 */
function load_produtos(): array
{
    $produtos = storage_read_json(PRODUTOS_FILE);

    $seedPath = __DIR__ . '/../data/produtos.json';
    if (is_file($seedPath)) {
        $seed = json_decode((string) file_get_contents($seedPath), true);
        if (is_array($seed)) {
            $slugsExistentes = array_column($produtos, 'slug');
            $importou = false;
            foreach ($seed as $produtoSeed) {
                if (!in_array($produtoSeed['slug'] ?? null, $slugsExistentes, true)) {
                    $produtos[] = $produtoSeed;
                    $importou = true;
                }
            }
            if ($importou) {
                storage_write_json(PRODUTOS_FILE, $produtos);
            }
        }
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
 * Escrita usada só pelo painel administrativo (equipe/produtos.php) — grava no
 * catálogo "ao vivo" (fora do repositório), nunca em `data/produtos.json`.
 */
function save_produtos(array $produtos): void
{
    storage_write_json(PRODUTOS_FILE, array_values($produtos));
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
