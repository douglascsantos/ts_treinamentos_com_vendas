<?php
/**
 * Textos de contrato exibidos no popup de aceite antes do pagamento, e na
 * íntegra (com dados do aluno preenchidos) em meu-contrato.php.
 *
 * MODELO PROVISÓRIO — adaptado do contrato do site antigo (old_site/contrato-modelo.pdf)
 * e de referências públicas de termos de LGPD/uso de imagem, mas ainda SEM revisão
 * jurídica final. Não usar como contrato definitivo sem um advogado revisar,
 * especialmente as cláusulas de cancelamento/reembolso e o termo de risco de
 * lesão (ver ROADMAP.md).
 *
 * Compra de CURSO exige aceite de 3 documentos (todos via um único checkbox,
 * visualizáveis em abas no popup — ver assets/js/main.js):
 *   1. render_contrato_prestacao_servicos() — objeto, pagamento, cancelamento, foro
 *   2. render_contrato_lgpd_imagem()          — dados pessoais + uso de imagem/vídeo/redes
 *   3. render_contrato_risco_procedimentos()  — consentimento p/ práticas com risco de lesão
 * Compra de PRODUTO físico/digital usa só render_contrato_produtos().
 *
 * $dadosAluno (opcional, usado em meu-contrato.php pra imprimir a via assinada):
 *   ['nome' => ..., 'cpf' => ..., 'curso' => ..., 'preco' => ..., 'aceite_em' => ..., 'aceite_ip' => ...]
 */

function render_contrato_prestacao_servicos(array $config, ?array $dadosAluno = null): void
{
    ?>
    <p class="contrato-aviso">⚠️ Modelo provisório, ainda sem revisão jurídica final.</p>
    <h3>1. Contrato de Prestação de Serviços Educacionais</h3>

    <?php if ($dadosAluno): ?>
        <p><strong>CONTRATANTE:</strong> <?= e($dadosAluno['nome']) ?> — CPF <?= e($dadosAluno['cpf'] ?: 'não informado') ?></p>
        <p><strong>Curso:</strong> <?= e($dadosAluno['curso']) ?> — <strong>Valor:</strong> <?= e(format_price((float) $dadosAluno['preco'])) ?></p>
    <?php else: ?>
        <p><strong>CONTRATANTE:</strong> o(a) aluno(a) identificado(a) no cadastro/pedido de compra.</p>
    <?php endif; ?>
    <p><strong>CONTRATADA:</strong> <?= e($config['site_name']) ?> — <?= e($config['address']) ?></p>

    <ol>
        <li><strong>Do objeto:</strong> prestação, pela CONTRATADA ao CONTRATANTE, do curso adquirido pelo site, conforme descrição, carga horária, modalidade e data informadas no momento da compra.</li>
        <li><strong>Do pagamento e reserva de vaga:</strong> a vaga é garantida mediante o pagamento <strong>integral</strong> do valor do curso, via Pix ou cartão, processado pela InfinitePay. A confirmação da matrícula está condicionada à aprovação do pagamento.</li>
        <li><strong>Direito de arrependimento:</strong> conforme o art. 49 do Código de Defesa do Consumidor, o CONTRATANTE tem o prazo de 7 (sete) dias corridos a contar da data da compra para exercer o direito de arrependimento e obter reembolso integral, desde que o curso ainda não tenha sido realizado e a solicitação seja feita por escrito ao e-mail <?= e($config['email']) ?>.</li>
        <li><strong>Política de cancelamento (após o prazo de arrependimento):</strong> em caso de desistência fora do prazo do item anterior, o reembolso segue a proximidade da data do curso, calculado sobre o valor pago:
            <ul>
                <li>30 dias ou mais de antecedência: reembolso de 90%;</li>
                <li>de 15 a 29 dias de antecedência: reembolso de 80%;</li>
                <li>de 7 a 14 dias de antecedência: reembolso de 70%;</li>
                <li>de 1 a 6 dias de antecedência: reembolso de 30%;</li>
                <li>menos de 24 horas de antecedência, não comparecimento, ou após o curso já realizado: sem reembolso — o CONTRATANTE poderá solicitar reagendamento ou participação em outra turma, conforme disponibilidade da CONTRATADA.</li>
            </ul>
        </li>
        <li><strong>Frequência e certificação:</strong> para emissão do certificado, o CONTRATANTE deverá cumprir frequência mínima de 75% da carga horária e realizar as avaliações práticas/teóricas, quando aplicáveis. O certificado digital será disponibilizado na Área do Aluno após liberação pela CONTRATADA.</li>
        <li><strong>Material didático:</strong> todo material fornecido é de uso exclusivo do CONTRATANTE, sendo vedada sua reprodução total ou parcial sem autorização escrita.</li>
        <li><strong>Documentos complementares:</strong> fazem parte integrante e indissociável deste contrato o <em>Termo de Proteção de Dados (LGPD) e Autorização de Uso de Imagem</em> e, quando o curso envolver prática de procedimentos, o <em>Termo de Consentimento para Práticas com Risco de Lesão</em> — ambos apresentados junto a este documento.</li>
        <li><strong>Aceite eletrônico:</strong> ao marcar "Li e aceito os contratos" no checkout, o CONTRATANTE declara que leu, compreendeu e aceita integralmente este contrato e os documentos complementares citados acima. O aceite, acompanhado do registro de data, hora e IP, tem valor de assinatura para todos os fins legais (MP 2.200-2/2001).<?php if ($dadosAluno && !empty($dadosAluno['aceite_em'])): ?> Neste pedido, o aceite foi registrado em <strong><?= e($dadosAluno['aceite_em']) ?></strong><?= !empty($dadosAluno['aceite_ip']) ? ', a partir do IP ' . e($dadosAluno['aceite_ip']) : '' ?>.<?php endif; ?></li>
        <li><strong>Foro:</strong> fica eleito o foro da Comarca de São José do Rio Preto/SP para dirimir quaisquer questões oriundas deste contrato.</li>
    </ol>
    <?php
}

function render_contrato_lgpd_imagem(array $config, ?array $dadosAluno = null): void
{
    ?>
    <p class="contrato-aviso">⚠️ Modelo provisório, ainda sem revisão jurídica final.</p>
    <h3>2. Termo de Proteção de Dados (LGPD) e Autorização de Uso de Imagem, Voz, Vídeo e Redes Sociais</h3>

    <?php if ($dadosAluno): ?>
        <p><strong>TITULAR:</strong> <?= e($dadosAluno['nome']) ?> — CPF <?= e($dadosAluno['cpf'] ?: 'não informado') ?></p>
    <?php else: ?>
        <p><strong>TITULAR:</strong> o(a) aluno(a) identificado(a) no cadastro/pedido de compra.</p>
    <?php endif; ?>
    <p><strong>CONTROLADORA:</strong> <?= e($config['site_name']) ?> — <?= e($config['address']) ?></p>

    <ol>
        <li><strong>Dados tratados:</strong> nome, CPF, e-mail, telefone/WhatsApp e área de atuação informados no cadastro, além de imagens e vídeos captados durante as atividades presenciais.</li>
        <li><strong>Finalidade:</strong> execução do contrato de prestação de serviços, emissão de certificado, comunicação sobre o curso e, quando autorizado neste termo, divulgação institucional.</li>
        <li><strong>Base legal:</strong> execução de contrato (art. 7º, V, LGPD) para os dados cadastrais, e consentimento específico (art. 7º, I, LGPD) para o uso de imagem/voz/vídeo tratado neste termo.</li>
        <li><strong>Autorização de uso de imagem, voz e vídeo:</strong> o TITULAR autoriza, a título gratuito e por prazo indeterminado enquanto vigorar a finalidade aqui descrita, o uso de fotos e vídeos das atividades em que participar para: (a) material didático interno da CONTROLADORA; e (b) divulgação institucional no site oficial e nas redes sociais da <?= e($config['site_name']) ?> (incluindo o Instagram <?= e($config['instagram_handle']) ?>). Não haverá uso com finalidade comercial de terceiros, nem uso que exponha o TITULAR de forma vexatória ou pejorativa.</li>
        <li><strong>Revogação da autorização de imagem:</strong> o TITULAR pode revogar esta autorização a qualquer momento, por escrito, ao e-mail <?= e($config['email']) ?>. A revogação não afeta materiais já publicados antes da solicitação, que serão removidos em prazo razoável quando tecnicamente possível.</li>
        <li><strong>Compartilhamento com terceiros:</strong> os dados pessoais só são compartilhados com prestadores estritamente necessários à operação (ex.: processador de pagamento). Os dados não são vendidos nem compartilhados para fins de marketing de terceiros.</li>
        <li><strong>Retenção e segurança:</strong> os dados são mantidos pelo prazo necessário às finalidades descritas e ao cumprimento de obrigações legais, com medidas técnicas e administrativas razoáveis de proteção.</li>
        <li><strong>Direitos do titular (art. 18 da LGPD):</strong> o TITULAR pode solicitar, a qualquer momento e mediante contato com <?= e($config['email']) ?>, a confirmação do tratamento, acesso, correção, anonimização, portabilidade, eliminação dos dados ou informação sobre compartilhamento, bem como revogar o consentimento aqui dado.</li>
    </ol>
    <?php
}

function render_contrato_risco_procedimentos(array $config, ?array $dadosAluno = null): void
{
    ?>
    <p class="contrato-aviso">⚠️ Modelo provisório, ainda sem revisão jurídica final.</p>
    <h3>3. Termo de Consentimento Livre e Esclarecido para Práticas com Risco de Lesão</h3>

    <?php if ($dadosAluno): ?>
        <p><strong>PARTICIPANTE:</strong> <?= e($dadosAluno['nome']) ?> — CPF <?= e($dadosAluno['cpf'] ?: 'não informado') ?></p>
    <?php else: ?>
        <p><strong>PARTICIPANTE:</strong> o(a) aluno(a) identificado(a) no cadastro/pedido de compra.</p>
    <?php endif; ?>

    <ol>
        <li><strong>Natureza da prática:</strong> alguns cursos da <?= e($config['site_name']) ?> incluem prática de procedimentos técnicos de enfermagem/saúde — como punção venosa, punção subcutânea, punção intramuscular e outros procedimentos invasivos ou de contato — realizados entre os próprios participantes e/ou em manequins/simuladores, sempre sob supervisão de instrutor(a) qualificado(a).</li>
        <li><strong>Riscos inerentes:</strong> por sua natureza, esses procedimentos podem causar desconforto, dor leve, hematoma ou equimose no local, e, mais raramente, outras intercorrências. O PARTICIPANTE declara estar ciente de que esses riscos são inerentes à prática de treinamento em procedimentos de saúde.</li>
        <li><strong>Medidas de segurança:</strong> a CONTRATADA se compromete a realizar as práticas com material adequado e, quando aplicável, estéril ou descartável, em ambiente apropriado e sob supervisão de instrutor(a) qualificado(a), seguindo as boas práticas do ensino técnico em saúde.</li>
        <li><strong>Participação voluntária:</strong> a participação em cada prática é voluntária. O PARTICIPANTE pode, a qualquer momento e sem necessidade de justificativa, recusar-se a realizar ou a receber um procedimento específico, sem qualquer prejuízo em sua permanência no curso.</li>
        <li><strong>Assistência em caso de intercorrência:</strong> caso ocorra qualquer intercorrência durante a prática, a CONTRATADA prestará assistência imediata, incluindo primeiros socorros e, se necessário, encaminhamento a atendimento médico.</li>
        <li><strong>Direitos preservados:</strong> este termo tem finalidade exclusivamente informativa e de registro do consentimento esclarecido do PARTICIPANTE quanto aos riscos normais e inerentes à prática — <strong>não implica renúncia a nenhum direito legal</strong>. O PARTICIPANTE mantém integralmente o direito de buscar reparação e indenização, nos termos do Código de Defesa do Consumidor e da legislação civil aplicável, em caso de dano comprovadamente decorrente de negligência, imprudência ou imperícia da CONTRATADA ou de seus instrutores.</li>
        <li><strong>Dados de saúde:</strong> informações de saúde eventualmente reveladas pelo PARTICIPANTE (ex.: alergias, uso de anticoagulantes, condições relevantes para a segurança da prática) são tratadas como dado pessoal sensível, nos termos da LGPD, usadas exclusivamente para adequar a prática com segurança.</li>
    </ol>
    <?php
}

function render_contrato_produtos(array $config, ?array $dadosAluno = null): void
{
    ?>
    <p class="contrato-aviso">⚠️ Modelo provisório, ainda sem revisão jurídica final.</p>
    <h3>Contrato de Venda de Produtos</h3>
    <?php if ($dadosAluno): ?>
        <p><strong>COMPRADOR:</strong> <?= e($dadosAluno['nome']) ?> — CPF <?= e($dadosAluno['cpf'] ?: 'não informado') ?></p>
        <p><strong>Produto:</strong> <?= e($dadosAluno['curso']) ?> — <strong>Valor:</strong> <?= e(format_price((float) $dadosAluno['preco'])) ?></p>
    <?php endif; ?>
    <p><strong>VENDEDORA:</strong> <?= e($config['site_name']) ?> — <?= e($config['address']) ?></p>
    <ol>
        <li><strong>Objeto:</strong> venda do produto (card, kit de treinamento ou e-book) escolhido pelo COMPRADOR, conforme descrição apresentada na página do produto.</li>
        <li><strong>Pagamento:</strong> realizado via meio integrado no checkout (Pix ou cartão). A confirmação do pagamento garante a compra.</li>
        <li><strong>Entrega — produtos físicos (cards/kits):</strong> combinada diretamente com a equipe após a compra (retirada no local ou combinação de envio).</li>
        <li><strong>Entrega — produtos digitais (e-books):</strong> o arquivo em PDF é enviado por e-mail ou WhatsApp após a confirmação do pagamento.</li>
        <li><strong>Direito de arrependimento:</strong> conforme o art. 49 do CDC, o COMPRADOR tem 7 dias corridos a contar da compra para desistir, com reembolso integral, desde que o produto físico não tenha sido retirado/utilizado ou o e-book não tenha sido enviado.</li>
        <li><strong>Trocas e devoluções:</strong> produtos físicos com defeito podem ser trocados em até 7 dias após o recebimento; produtos digitais, por já entregues, não são reembolsáveis salvo erro da vendedora.</li>
        <li><strong>LGPD:</strong> os dados pessoais coletados são utilizados apenas para processar a compra e a entrega, conforme a Lei 13.709/2018.</li>
        <li><strong>Aceite eletrônico:</strong> ao marcar "Li e aceito o contrato" no checkout, o COMPRADOR declara que leu, compreendeu e aceita integralmente este contrato. O aceite, acompanhado do registro de data, hora e IP, tem valor de assinatura para todos os fins legais (MP 2.200-2/2001).<?php if ($dadosAluno && !empty($dadosAluno['aceite_em'])): ?> Neste pedido, o aceite foi registrado em <strong><?= e($dadosAluno['aceite_em']) ?></strong><?= !empty($dadosAluno['aceite_ip']) ? ', a partir do IP ' . e($dadosAluno['aceite_ip']) : '' ?>.<?php endif; ?></li>
        <li><strong>Foro:</strong> fica eleito o foro da Comarca de São José do Rio Preto/SP para dirimir quaisquer questões oriundas deste contrato.</li>
    </ol>
    <?php
}
