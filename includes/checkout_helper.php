<?php
/**
 * Núcleo do checkout, compartilhado entre checkout.php (aluno já logado) e
 * area-do-aluno.php/auth/google-*.php (retomando uma compra que ficou
 * pendente de login/cadastro — ver $_SESSION['checkout_pendente']).
 *
 * O site exige conta ANTES do pagamento (não depois, como era antes) — é
 * assim que dá pra mandar e-mail de confirmação de compra pro endereço
 * certo assim que o pagamento é aprovado (ver includes/email.php e
 * webhook-infinitepay.php).
 */
require_once __DIR__ . '/turmas.php';
require_once __DIR__ . '/produtos.php';
require_once __DIR__ . '/pedidos.php';
require_once __DIR__ . '/infinitepay.php';
require_once __DIR__ . '/cupons.php';
require_once __DIR__ . '/alunos.php';
require_once __DIR__ . '/email.php';

/**
 * Registro de falhas reais ao gerar link de pagamento (resposta da InfinitePay,
 * não erro de validação nossa) — pra dar pra diagnosticar sem precisar
 * reproduzir a compra de novo. Mesmo padrão do webhook_log() em
 * webhook-infinitepay.php, mas em arquivo separado (fora do repositório).
 */
function checkout_log(string $linha): void
{
    $dir = dirname(storage_path(PEDIDOS_FILE));
    @file_put_contents(
        $dir . '/checkout.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $linha . "\n",
        FILE_APPEND | LOCK_EX
    );
}

/** Mensagem de erro simples com saída pro WhatsApp e volta pro site — usada em todo o funil de checkout. */
function checkout_erro(array $config, string $titulo, string $mensagem, ?string $waTexto = null): void
{
    $version = require __DIR__ . '/version.php';
    $page_title = $titulo . ' | ' . $config['site_name'];
    $base_path = '';
    include __DIR__ . '/header.php';
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
    include __DIR__ . '/footer.php';
}

/**
 * $intent: tipo (curso|produto), slug, cupom_codigo (opcional),
 * aceite_contrato_em, aceite_contrato_ip — já validados antes de chegar
 * aqui (aceite do contrato confirmado no clique original, mesmo que o
 * pagamento só aconteça depois de login/cadastro). Sempre termina a
 * requisição: redireciona pro link de pagamento ou mostra checkout_erro()
 * e sai — nunca retorna normalmente.
 */
function processar_checkout(array $config, string $alunoId, array $intent): void
{
    $tipo = $intent['tipo'] ?? '';
    $slug = $intent['slug'] ?? '';

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
        if (!$item || !produto_ativo($item)) {
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
    $cupomCodigo = trim($intent['cupom_codigo'] ?? '');
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
        'aluno_id'           => $alunoId,
        'aceite_contrato_em' => $intent['aceite_contrato_em'] ?? date('Y-m-d H:i:s'),
        'aceite_contrato_ip' => $intent['aceite_contrato_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? null),
    ]);

    if ($cupom) {
        marcar_cupom_usado($cupom['codigo'], $pedido['order_nsu']);
    }

    $precoComDescontoCentavos = (int) round($precoComDesconto * 100);

    // A InfinitePay recusa qualquer link com valor total abaixo de
    // INFINITEPAY_VALOR_MINIMO_CENTAVOS (R$1,00) — confirmado testando a API
    // real, não é só o caso de cupom zerando o preço 100%: um cupom que deixa
    // só alguns centavos, ou (bem mais raro) o próprio preço de catálogo
    // sendo menor que R$1, caem no mesmo problema. Antes disso não era
    // tratado à parte: caía direto em infinitepay_create_link(), que a
    // InfinitePay rejeita — aparecendo pro cliente como "Não foi possível
    // iniciar o pagamento" mesmo com tudo certo do nosso lado. Como o valor
    // que sobraria pra cobrar é tão baixo que não compensa cobrança manual,
    // confirma o pedido na hora, sem gateway, igual o webhook faria.
    if ($precoComDescontoCentavos < INFINITEPAY_VALOR_MINIMO_CENTAVOS) {
        $pedidoPago = atualizar_pedido($pedido['order_nsu'], ['status' => 'pago']) ?? $pedido;
        $aluno = find_aluno_by_id($alunoId);
        if ($aluno) {
            try {
                enviar_email_compra_confirmada($aluno, $pedidoPago);
            } catch (Throwable $e) {
                checkout_log("Falha ao enviar e-mail de confirmação (pedido abaixo do mínimo da InfinitePay) pra {$pedido['order_nsu']}: " . $e->getMessage());
            }
        }
        header('Location: ' . site_base_url() . '/pagamento-concluido.php?order_nsu=' . rawurlencode($pedido['order_nsu']));
        exit;
    }

    $resultado = infinitepay_create_link(
        $config['infinitepay_handle'],
        [
            'quantity'       => 1,
            'price_centavos' => $precoComDescontoCentavos,
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
    checkout_log("infinitepay_create_link falhou pro pedido {$pedido['order_nsu']} ({$tipo}/{$slug}, R$ " . number_format($precoComDesconto, 2, ',', '') . "): " . json_encode($resultado['raw'], JSON_UNESCAPED_UNICODE));
    checkout_erro(
        $config,
        'Não foi possível iniciar o pagamento',
        'Tivemos um problema ao gerar o link de pagamento. Você pode tentar novamente em instantes ou falar direto com a gente.',
        'Olá! Tentei comprar "' . $descricao . '" no site mas deu erro no pagamento — pode me ajudar?'
    );
    exit;
}

/**
 * Se havia uma compra pendente de login/cadastro (ver checkout.php), retoma
 * automaticamente — sem precisar clicar em "Pagar" de novo. Chamado logo
 * depois de $_SESSION['aluno_id'] ser definido (login, cadastro, Google).
 * Só retorna normalmente se NÃO havia nada pendente.
 */
function checkout_retomar_se_pendente(array $config): void
{
    if (empty($_SESSION['checkout_pendente']) || empty($_SESSION['aluno_id'])) {
        return;
    }
    $intent = $_SESSION['checkout_pendente'];
    unset($_SESSION['checkout_pendente']);
    processar_checkout($config, $_SESSION['aluno_id'], $intent);
}
