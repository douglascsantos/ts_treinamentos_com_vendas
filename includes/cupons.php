<?php
/**
 * Cupom de desconto (tabela `cupons`, MySQL) — gerado pelo administrativo pra
 * um curso ou produto específico, valor em R$ ou %, com prazo em dias.
 * Uso único: expira assim que alguém usa (mesmo dentro do prazo) ou quando o
 * prazo acaba, o que vier primeiro.
 */

function gerar_codigo_cupom(): string
{
    return strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

function find_cupom_by_codigo(string $codigo): ?array
{
    $stmt = db()->prepare('SELECT * FROM cupons WHERE codigo = ? LIMIT 1');
    $stmt->execute([mb_strtoupper(trim($codigo))]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function listar_cupons(): array
{
    return db()->query('SELECT * FROM cupons ORDER BY criado_em DESC')->fetchAll();
}

/** $dados: tipo_item, item_slug, tipo_desconto ('percentual'|'fixo'), valor_desconto, validade_dias, criado_por. */
function criar_cupom(array $dados): array
{
    $id = gerar_id('CU');
    $codigo = gerar_codigo_cupom();
    $expiraEm = (new DateTime())->modify('+' . (int) $dados['validade_dias'] . ' days')->format('Y-m-d H:i:s');

    $stmt = db()->prepare(
        'INSERT INTO cupons (id, codigo, tipo_item, item_slug, tipo_desconto, valor_desconto, expira_em, criado_por, criado_em)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $id, $codigo, $dados['tipo_item'], $dados['item_slug'], $dados['tipo_desconto'],
        (float) $dados['valor_desconto'], $expiraEm, $dados['criado_por'], date('Y-m-d H:i:s'),
    ]);

    $stmt = db()->prepare('SELECT * FROM cupons WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Confere se um cupom pode ser usado agora pra esse item específico. Retorna
 * o registro do cupom se válido, ou null (código errado, expirado, já usado,
 * ou pra outro curso/produto).
 */
function validar_cupom(string $codigo, string $tipoItem, string $itemSlug): ?array
{
    $cupom = find_cupom_by_codigo($codigo);
    if (!$cupom) {
        return null;
    }
    if ($cupom['tipo_item'] !== $tipoItem || $cupom['item_slug'] !== $itemSlug) {
        return null;
    }
    if ($cupom['usado_em'] !== null) {
        return null;
    }
    if (strtotime($cupom['expira_em']) < time()) {
        return null;
    }
    return $cupom;
}

/** Calcula o valor de desconto (em R$) pro preço informado. */
function calcular_desconto_cupom(array $cupom, float $preco): float
{
    if ($cupom['tipo_desconto'] === 'percentual') {
        $desconto = $preco * ((float) $cupom['valor_desconto'] / 100);
    } else {
        $desconto = (float) $cupom['valor_desconto'];
    }
    return min($desconto, $preco);
}

/** Marca o cupom como usado (uso único) — chamado só depois do pedido criado com sucesso. */
function marcar_cupom_usado(string $codigo, string $orderNsu): void
{
    $stmt = db()->prepare('UPDATE cupons SET usado_em = ?, pedido_id = ? WHERE codigo = ? AND usado_em IS NULL');
    $stmt->execute([date('Y-m-d H:i:s'), $orderNsu, mb_strtoupper(trim($codigo))]);
}
