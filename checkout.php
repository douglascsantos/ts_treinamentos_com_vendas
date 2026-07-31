<?php
/**
 * Ponto de entrada do checkout: recebe um POST (tipo, slug, aceite_contrato,
 * csrf_token) vindo do formulário em cursos/{slug}.php ou produtos/{slug}.php,
 * cria o pedido localmente e redireciona pro link de pagamento da InfinitePay.
 *
 * Só aceita POST de propósito — o aceite do contrato precisa ser confirmado
 * pelo servidor, não só pelo checkbox no navegador (que dá pra pular editando
 * o HTML). GET/link direto não gera pagamento nenhum.
 */
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/turmas.php';
require __DIR__ . '/includes/produtos.php';
require __DIR__ . '/includes/storage.php';
require __DIR__ . '/includes/pedidos.php';
require __DIR__ . '/includes/infinitepay.php';
require __DIR__ . '/includes/env.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/cupons.php';
require __DIR__ . '/includes/csrf.php';
csrf_ensure_session();

$config = require __DIR__ . '/includes/config.php';

/** Mostra uma mensagem de erro simples com saída pro WhatsApp e volta pro site. */
function checkout_erro(array $config, string $titulo, string $mensagem, ?string $waTexto = null): void
{
    $version = require __DIR__ . '/includes/version.php';
    $page_title = $titulo . ' | ' . $config['site_name'];
    $base_path = '';
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="section">
        <div class="container page-content checkout-status-page">
            <h1><?= e($titulo) ?></h1>
            <p class="lead-muted checkout-status-msg"><?= e($mensagem) ?></p>
            <div class="checkout-actions">
                <a class="btn btn-primary" href="index.php">Voltar ao site</a>
                <?php if ($waTexto): ?>
                    <a class="btn btn-accent" href="<?= e(wa_link($config['whatsapp'], $waTexto)) ?>" target="_blank" rel="noopener">Falar no WhatsApp</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    checkout_erro($config, 'Acesso inválido', 'Essa página só pode ser acessada a partir do botão de pagamento de um curso ou produto.');
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    checkout_erro($config, 'Sessão expirada', 'Sua sessão expirou. Volte à página do curso/produto e tente novamente.');
    exit;
}

if (($_POST['aceite_contrato'] ?? '') !== '1') {
    checkout_erro($config, 'Contrato não aceito', 'Você precisa marcar o aceite do contrato para continuar com o pagamento.');
    exit;
}

$tipo = $_POST['tipo'] ?? '';
$slug = $_POST['slug'] ?? '';

if (!in_array($tipo, ['curso', 'produto'], true) || $slug === '') {
    checkout_erro($config, 'Item inválido', 'Não conseguimos identificar o que você quer comprar.');
    exit;
}

if ($tipo === 'curso') {
    $item = find_turma($slug);
    if (!$item || !turma_ativa($item)) {
        checkout_erro($config, 'Curso não encontrado', 'Esse curso pode não estar mais disponível.');
        exit;
    }
    if ($item['status'] === 'esgotada') {
        checkout_erro(
            $config,
            'Turma esgotada',
            'Essa turma já esgotou as vagas. Fale com a gente para entrar na lista de espera ou ver a próxima data.',
            'Olá! A turma "' . $item['curso'] . '" está esgotada — quero entrar na lista de espera.'
        );
        exit;
    }
    $descricao = $item['curso'];
    $preco = (float) $item['preco'];
} else {
    $item = find_produto($slug);
    if (!$item) {
        checkout_erro($config, 'Produto não encontrado', 'Esse produto pode não estar mais disponível.');
        exit;
    }
    if (!produto_e_digital($item['tipo']) && (int) $item['estoque'] <= 0) {
        checkout_erro(
            $config,
            'Produto esgotado',
            'Esse produto está sem estoque no momento.',
            'Olá! Quero ser avisado quando o produto "' . $item['nome'] . '" voltar ao estoque.'
        );
        exit;
    }
    $descricao = $item['nome'];
    $preco = (float) $item['preco'];
}

if ($preco <= 0) {
    checkout_erro($config, 'Item gratuito', 'Esse item não passa por pagamento — fale com a gente pelo WhatsApp para mais informações.', 'Olá! Tenho uma dúvida sobre "' . $descricao . '".');
    exit;
}

// Cupom é sempre revalidado no servidor — nunca confia no desconto mostrado na página.
$cupomCodigo = trim($_POST['cupom'] ?? '');
$cupom = $cupomCodigo !== '' ? validar_cupom($cupomCodigo, $tipo, $slug) : null;
$descontoValor = $cupom ? calcular_desconto_cupom($cupom, $preco) : 0;
$precoComDesconto = $preco - $descontoValor;

// status_entrega nunca fica nulo: curso não tem entrega (valor "entregue" é só
// preenchimento, nunca aparece pro aluno — minha-conta.php só mostra isso pra
// produto); produto digital nasce "entregue" (é o PDF que já libera o acesso),
// produto físico nasce "a_caminho" até o administrativo atualizar.
$statusEntrega = $tipo === 'curso' || produto_e_digital($item['tipo'] ?? '')
    ? 'entregue'
    : 'a_caminho';

$pedido = criar_pedido([
    'tipo'               => $tipo,
    'slug'               => $slug,
    'descricao'          => $descricao,
    'preco'              => $precoComDesconto,
    'cupom_codigo'       => $cupom['codigo'] ?? null,
    'desconto_valor'     => $descontoValor,
    'status_entrega'     => $statusEntrega,
    'aceite_contrato_em' => date('Y-m-d H:i:s'),
    'aceite_contrato_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
]);

if ($cupom) {
    marcar_cupom_usado($cupom['codigo'], $pedido['order_nsu']);
}

$resultado = infinitepay_create_link(
    $config['infinitepay_handle'],
    [
        'quantity'       => 1,
        'price_centavos' => (int) round($precoComDesconto * 100),
        'description'    => $descricao,
    ],
    $pedido['order_nsu'],
    site_base_url() . '/pagamento-concluido.php',
    site_base_url() . '/webhook-infinitepay.php',
);

if ($resultado['ok']) {
    header('Location: ' . $resultado['url']);
    exit;
}

atualizar_pedido($pedido['order_nsu'], ['status' => 'erro']);
checkout_erro(
    $config,
    'Não foi possível iniciar o pagamento',
    'Tivemos um problema ao gerar o link de pagamento. Você pode tentar novamente em instantes ou falar direto com a gente.',
    'Olá! Tentei comprar "' . $descricao . '" no site mas deu erro no pagamento — pode me ajudar?'
);
