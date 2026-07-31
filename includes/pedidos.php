<?php
/**
 * Registro simples de pedidos (protótipo — sem banco de dados ainda).
 * Persistência resolvida em includes/storage.php (ver comentário lá sobre
 * o risco de perder dados em deploys, e o que fazer a respeito).
 */

const PEDIDOS_FILE = 'pedidos.json';

/**
 * Status de entrega — só faz sentido pra pedido de produto físico (kit/card,
 * não e-book) já pago. Quem atualiza é o administrativo (painel ainda não
 * construído — ver ROADMAP.md); aqui só o rótulo/classe CSS pra exibir.
 */
const STATUS_ENTREGA_LABELS = [
    'enviado'   => ['Enviado por transportadora', 'status-last'],
    'a_caminho' => ['A caminho', 'status-last'],
    'entregue'  => ['Entregue', 'status-open'],
];

function load_pedidos(): array
{
    return storage_read_json(PEDIDOS_FILE);
}

function save_pedidos(array $pedidos): void
{
    storage_write_json(PEDIDOS_FILE, $pedidos);
}

/** Gera um order_nsu curto e razoavelmente único. */
function gerar_order_nsu(): string
{
    return 'TS' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

/**
 * Cria um novo pedido com status "pendente" e retorna o registro criado.
 * $dados deve ter: tipo (curso|produto), slug, descricao, preco (float), e
 * opcionalmente aceite_contrato_em / aceite_contrato_ip (registro do aceite
 * eletrônico dos contratos, feito no checkout — ver checkout.php).
 */
function criar_pedido(array $dados): array
{
    $pedidos = load_pedidos();

    $pedido = [
        'order_nsu'           => gerar_order_nsu(),
        'aluno_id'             => null,
        'tipo'                 => $dados['tipo'],
        'slug'                 => $dados['slug'],
        'descricao'            => $dados['descricao'],
        'preco'                => (float) $dados['preco'],
        'cupom_codigo'         => $dados['cupom_codigo'] ?? null,
        'desconto_valor'       => (float) ($dados['desconto_valor'] ?? 0),
        'status'               => 'pendente',
        'transaction_nsu'      => null,
        'invoice_slug'         => null,
        'capture_method'       => null,
        'receipt_url'          => null,
        'status_entrega'       => $dados['status_entrega'],
        'aceite_contrato_em'   => $dados['aceite_contrato_em'] ?? null,
        'aceite_contrato_ip'   => $dados['aceite_contrato_ip'] ?? null,
        'criado_em'            => date('Y-m-d H:i:s'),
        'atualizado_em'        => null,
    ];

    $pedidos[] = $pedido;
    save_pedidos($pedidos);

    return $pedido;
}

function find_pedido_by_nsu(string $orderNsu): ?array
{
    foreach (load_pedidos() as $pedido) {
        if (($pedido['order_nsu'] ?? '') === $orderNsu) {
            return $pedido;
        }
    }
    return null;
}

/** Todos os pedidos vinculados a um aluno (ver vincular_pedido_aluno), mais recentes primeiro. */
function find_pedidos_by_aluno(string $alunoId): array
{
    $pedidos = array_values(array_filter(
        load_pedidos(),
        fn ($p) => ($p['aluno_id'] ?? null) === $alunoId
    ));
    usort($pedidos, fn ($a, $b) => strcmp($b['criado_em'], $a['criado_em']));
    return $pedidos;
}

/**
 * Vincula um pedido (feito antes do login/cadastro, no fluxo de checkout) à
 * conta de aluno criada/usada logo em seguida. Só vincula se o pedido ainda
 * não pertencer a outra conta, pra não sequestrar pedido de outra pessoa.
 */
function vincular_pedido_aluno(string $orderNsu, string $alunoId): ?array
{
    $pedido = find_pedido_by_nsu($orderNsu);
    if (!$pedido || !empty($pedido['aluno_id'])) {
        return null;
    }
    return atualizar_pedido($orderNsu, ['aluno_id' => $alunoId]);
}

/** Atualiza campos de um pedido existente pelo order_nsu. */
function atualizar_pedido(string $orderNsu, array $changes): ?array
{
    $pedidos = load_pedidos();
    $atualizado = null;

    foreach ($pedidos as &$pedido) {
        if (($pedido['order_nsu'] ?? '') === $orderNsu) {
            $pedido = array_merge($pedido, $changes, ['atualizado_em' => date('Y-m-d H:i:s')]);
            $atualizado = $pedido;
            break;
        }
    }
    unset($pedido);

    if ($atualizado) {
        save_pedidos($pedidos);
    }
    return $atualizado;
}
