# Roadmap — TS Treinamentos em Saúde

Itens combinados com o cliente para fases futuras. Nada aqui está implementado ainda — é o
registro do que falta construir, para não se perder entre uma conversa e outra.

## 1. E-commerce (checkout de verdade)

**Status: MVP construído (ainda não publicado), usando InfinitePay.** Ver
`.claude/agents/deploy-check.md`/CHANGELOG para quando isso for pra produção.

O que já existe (`checkout.php`, `webhook-infinitepay.php`, `pagamento-concluido.php`,
`includes/infinitepay.php`, `includes/pedidos.php`):
- Botão "Pagar agora" nas páginas de curso/produto gera um link de pagamento (Pix ou cartão até
  12x) via API da InfinitePay (`POST /links`) e redireciona o cliente.
- Confirmação por webhook, sempre reconfirmada via `POST /payment_check` antes de marcar como
  pago (a InfinitePay não assina o webhook, então nunca confiamos só nele — ver comentários em
  `webhook-infinitepay.php`).
- Pedidos e contas de aluno ficam registrados em JSON simples (`includes/storage.php`,
  usado por `includes/pedidos.php` e `includes/alunos.php`), de preferência **fora** da pasta do
  repositório (`../ts_site_data/`, um nível acima de `public_html`) pra sobreviver a deploys —
  com fallback pra `data/` (gitignored) em dev local. **Confirmar com a Hostinger se o deploy
  Git faz `git clean`/reset destrutivo** antes de depender 100% disso; migrar pra MySQL assim
  que possível (ver item 3).

**Sobre o checkout embutido:** o cliente pediu inicialmente uma tela de pagamento (escolher
Pix/cartão) direto na página, sem sair do site. Testamos e a InfinitePay **bloqueia
explicitamente** embutir a página de pagamento deles em iframe (`X-Frame-Options: SAMEORIGIN` +
`Content-Security-Policy: frame-ancestors` restrito a domínios deles) — proteção padrão
anti-clickjacking em página de pagamento, não dá pra contornar. Existe um produto "Checkout
Transparente" deles que promete pagamento sem redirecionamento (tokenização de cartão via SDK
JS), mas não há documentação técnica pública — provavelmente exige contato comercial direto com
a InfinitePay para habilitar (credenciais JWT diferentes, escopo de PCI-DSS). Decisão tomada:
manter o redirecionamento para a página deles por enquanto.

**Contrato de aceite:** antes de pagar, o cliente precisa marcar um checkbox de aceite, com o
texto do(s) contrato(s) num popup/modal (sem sair da página) — ver `includes/contratos.php` e o
modal com abas em `includes/curso-page.php`/`includes/produto-page.php`. **A matrícula de 15% do
site antigo foi removida**: agora é reserva de vaga mediante **pagamento integral**, com direito
de arrependimento do CDC (art. 49 — 7 dias, reembolso integral) e, depois disso, reembolso
parcial escalonado conforme a proximidade do curso (90% com 30+ dias de antecedência, 80% com
15-29 dias, 70% com 7-14 dias, 30% com 1-6 dias, sem reembolso a menos de 24h ou após o curso —
só reagendamento/participação em outra turma, conforme disponibilidade). Para cursos, o aceite
cobre **3 documentos** mostrados juntos no modal (abas):
1. `render_contrato_prestacao_servicos` — pagamento, reserva/cancelamento, arrependimento CDC,
   reembolso escalonado, frequência/certificação.
2. `render_contrato_lgpd_imagem` — LGPD (dados tratados, finalidade, base legal) + autorização de
   uso de imagem/voz/vídeo/redes sociais.
3. `render_contrato_risco_procedimentos` — Termo de Consentimento Livre e Esclarecido para
   práticas com risco de lesão (punção venosa, subcutânea, intramuscular etc. entre
   participantes/manequins). **Importante:** não é uma renúncia de responsabilidade — pesquisa
   sobre orientação do COREN confirmou que isso não pode retirar o direito do aluno de buscar
   reparação por negligência/imprudência/imperícia, então o texto foi redigido como consentimento
   informado, preservando esse direito explicitamente.

Para produtos, só o contrato de venda (`render_contrato_produtos`). **Todo o texto é modelo
provisório, ainda sem revisão jurídica** — não usar como contrato final sem um advogado revisar.

**Contrato assinado fica disponível na Área do Aluno:** o `order_nsu` do pedido é carregado pelo
fluxo checkout → `pagamento-concluido.php` → `area-do-aluno.php` (campo oculto nos forms de login
e cadastro) e vinculado à conta assim que o aluno loga/cadastra (`vincular_pedido_aluno()` em
`includes/pedidos.php`). `minha-conta.php` lista os pedidos da conta com link "Ver contrato";
`meu-contrato.php` mostra o(s) contrato(s) na íntegra com nome/CPF do aluno, curso/produto
comprado, preço e data/hora/IP do aceite — protegido por dono (só quem comprou vê o próprio
pedido, testado com uma segunda conta tentando acessar o pedido de outra e recebendo 404).

Falta ainda:
- Cursos agendáveis (aluno escolhe data/horário no checkout).
- Cupons de desconto.
- Emissão de recibo/nota (hoje só temos o comprovante que a própria InfinitePay gera).
- Entrega automática de e-books (hoje, mesmo pago, o PDF ainda é mandado manualmente pelo
  WhatsApp — não tem download automático no site).
- Painel administrativo pra ver os pedidos (hoje só dá pra olhar o `pedidos.json`/`meu-contrato.php`
  direto, um pedido de cada vez, como aluno).
- Contrato final revisado juridicamente (ver aviso acima).

## 2. Área do Aluno

**Status: login, cadastro e histórico de pedidos/contratos construídos (ainda não publicados)** —
`area-do-aluno.php` (login + cadastro lado a lado), `minha-conta.php` (lista os pedidos da
conta), `meu-contrato.php` (contrato assinado na íntegra por pedido), `logout.php`,
`includes/alunos.php`. Cadastro é funcional de verdade: grava conta real (senha com
`password_hash`), valida CPF (dígito verificador) e data de nascimento, e-mail e CPF únicos, com
máscaras de campo (CPF, WhatsApp) via `assets/js/main.js`. Pronome (Sr./Sra.) é obrigatório e
também grava um campo `sexo` (M/F) — só pra estatística de alunos x alunas, sem aparecer em
nenhuma tela ainda. Login por e-mail/senha funcional, e login com Google também (ver bloco
abaixo).

Depois que o cliente paga um curso/produto, `pagamento-concluido.php` leva pra
`area-do-aluno.php` com o `order_nsu` na URL; o login/cadastro carrega esse `order_nsu` num campo
oculto e vincula o pedido à conta automaticamente (`vincular_pedido_aluno()`). Testado localmente
de ponta a ponta: checkout → pagamento-concluido → login/cadastro → pedido aparece em
`minha-conta.php` → contrato abre em `meu-contrato.php` com nome/CPF/curso/preço/data-hora-IP do
aceite corretos, e uma segunda conta não consegue abrir o contrato de outra (404).

**Cabeçalho com sessão:** quando logado, o menu (`includes/header.php`, todas as páginas do site)
troca "Área do Aluno" por "Bem-vindo, {primeiro nome}", linkando pra `meus-dados.php`.

**`meus-dados.php` (novo):** troca de senha (pede senha atual; contas via Google, sem senha, veem
aviso em vez do formulário) e edição de WhatsApp/data de nascimento/endereço (rua, número, CEP,
cidade, UF — todos opcionais, `null` se não preenchido). Nome, e-mail e CPF são fixos, não dá pra
editar por ali (mudariam a identidade da conta e o que já está assinado no contrato).

**`minha-conta.php` reorganizada:** cursos e produtos comprados agora em seções separadas.
Curso pago mostra "Concluído" (se a última data da turma já passou) ou "Agendado para {data} às
{horário}" (lido de `data/turmas.json` via `find_turma()`). Produto físico pago mostra status de
entrega (`pedidos.status_entrega`: enviado/a caminho/entregue, ou "aguardando envio" se ainda não
definido) — **quem atualiza isso é o administrativo, painel ainda não existe** (ver item 3).
Produto digital (e-book) continua só "Pago" por enquanto — a área de download ainda não foi
construída (ver item 3, "Download de e-book").

O que falta pra virar uma Área do Aluno completa (hoje é login + dados editáveis + lista de
pedidos com situação + contrato, sem dashboard pedagógico):
- Parte financeira (boletos parcelados — ver item 3).
- Histórico escolar e presenças.
- Imprimir certificados (ver item 3, já tem pasta/convenção de nome prontas em
  `includes/certificados.php`, falta a geração de PDF em si).
- Assistir aulas disponíveis online (conteúdo em vídeo).
- Download de e-book comprado.

**Login com Google: construído, pendente de teste final no navegador.** OAuth 2.0 clássico
(Authorization Code, sem SDK) — `includes/google_oauth.php`, `auth/google-login.php`,
`auth/google-callback.php`, credenciais em `secret.env` (`GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET`).
CPF é obrigatório pra todo aluno, sem exceção — nunca fica nulo, nem pra conta criada via Google.
Como o Google não fornece CPF, uma conta nova passa antes por `auth/google-completar-cadastro.php`
(pede só o CPF, valida dígito verificador e unicidade) antes de a conta existir de fato; só depois
loga e volta pro fluxo normal. WhatsApp/pronome/sexo/data de nascimento/área de atuação continuam
pendentes (o Google não fornece, e não bloqueiam a criação da conta) — sem senha, login sempre via
Google pra essa conta. Se o e-mail já existia numa conta com senha, só vincula o Google a ela (não
mexe nos dados, CPF já existia). Lógica de criação/vínculo/CPF obrigatório testada localmente;
redirect URIs (`www.`, sem `www.` e localhost) já cadastrados no Google Cloud Console. O fluxo
completo (tela de consentimento do Google) só dá pra testar num navegador de verdade, com o
usuário logando — ainda não confirmado. **Falta ainda:**
- Pedir WhatsApp/pronome/data de nascimento/área de atuação antes de fechar a compra de um curso
  pra contas criadas via Google (hoje, se a pessoa nunca completar isso, o contrato mostra esses
  campos em branco).

## 3. CRM / área administrativa

**As 10 tabelas do schema v5 existem no banco de produção** (`u885171151_GlKje`, MySQL na
Hostinger — ver `db/schema.sql`, versionado). Primeiro diretor cadastrado
(`diretor@tstreinamentos`/`12345678` — **o domínio do e-mail parece incompleto, confirmar/corrigir**).
`instrutores`/`administradores`/`cupons`/`gastos`/`boletos` já são usados de verdade pelo site;
`turmas`/`produtos`/`pedidos`/`alunos` continuam em JSON — migrar esse código é a próxima fase.
**Corrigido nos testes**: `cupons`/`boletos`/`certificados` tinham FK pra `pedidos`/`alunos` no
MySQL, mas esses ainda são JSON — toda tentativa de uso quebrava com erro de integridade
referencial. FKs removidas; validação de dono/vínculo fica na aplicação (ver `db/schema.sql`).

**Construído e testado localmente (sem deploy) — login e painéis de equipe:**
- `equipe/login.php`/`logout.php` — login único: tenta `administradores` primeiro, depois
  `instrutores`; redireciona pro painel certo conforme o tipo/nível.
- `equipe/diretor.php` — cadastra instrutor, administrativo/diretor e aluno; lista instrutores e
  administradores; upload da própria assinatura.
- `equipe/instrutor.php` — vê os próprios dados, upload da própria assinatura.
- `equipe/administrativo.php` — links pras telas abaixo; relatório de vendas ainda não construído.
- `equipe/turmas.php` — cria/remove curso (gera a página de venda automaticamente), edita vagas
  internas, carga horária, código da turma, status público e instrutor atribuído.
  `tools/sync_agenda.py` atualizado pra preservar esses campos ao re-sincronizar (não sobrescreve
  mais o que foi editado pelo painel).
- `equipe/cupons.php` — gera cupom (curso/produto específico, R$ ou %, prazo em dias), mostra o
  link pronto (`?cupom=CODIGO`). Aplicado de verdade em `cursos/{slug}.php`/`produtos/{slug}.php`
  (mostra preço com desconto) e revalidado no servidor em `checkout.php` (nunca confia no que a
  página mostrou) — uso único testado (segunda tentativa de uso é rejeitada).
- `equipe/boletos.php` — busca aluno por e-mail, mostra os pedidos dele, cadastra boleto (parcela
  + vencimento + upload do PDF, valida assinatura `%PDF-` antes de salvar). `financeiro.php`
  (aluno) lista os boletos com situação; `boleto-download.php` bloqueia download de boleto
  vencido mesmo com a URL direta (testado).
- `equipe/gastos.php` — lança gasto (curso/fixo mensal/variável único/patrimônio, com categorias),
  resumo por tipo.
- `ebook-download.php` — download protegido do e-book comprado; `tools/sync_produtos.py` agora
  vincula PDF solto em `produtos_ts_site/` ao produto certo (por slug), move pra fora do repo com
  nome não adivinhável. Testado: dono com pedido pago baixa: quem não comprou recebe 403; sem
  login redireciona.
- `includes/db.php` (PDO), `includes/administradores.php`, `includes/instrutores.php`,
  `includes/cupons.php`, `includes/boletos.php`, `includes/gastos.php`, `includes/assinaturas.php`,
  `includes/ebooks.php`.
- Barreiras de permissão testadas: administrativo não acessa painel de diretor, instrutor não
  acessa painel de diretor nem de administrativo, ninguém acessa nada sem login.

**Assinatura do certificado — resolvido**: sempre 1 diretor + 1 instrutor assinam. Cada um faz
upload da própria assinatura na área dele (já funciona); quando o certificado for gerado de
verdade, usa a imagem se existir, senão imprime nome + profissão + registro em texto (igual ao
modelo que já existe em `prototipagem_stitch/`).

**Matrícula do aluno na turma**: não é tabela nova — é o próprio `pedidos` com `tipo='curso'` e
`status='pago'`, casado por `slug`.

**O que falta pra fechar o CRM:**
1. **Certificado de verdade** — ainda não construído: escolher biblioteca de geração de PDF
   (hospedagem sem Composer), tela do instrutor pra liberar certificado (turmas atribuídas +
   alunos matriculados), ação do diretor "finalizar turma" (gera certificado pra todos os alunos
   pagos da turma de uma vez, com `diretor_id` do quem finalizou), e a página pública de
   verificação por `numero_hash`/QR Code. Pasta e convenção de nome já prontas
   (`includes/certificados.php`).
2. Relatório de vendas no painel administrativo.
3. Migrar `turmas`/`produtos`/`pedidos`/`alunos` de JSON pras tabelas MySQL já criadas.

## Como isso se conecta ao que já existe

Os itens 1 e 2 dependem um do outro (não dá pra ter Área do Aluno completa sem checkout de
verdade gerando pedidos reais). O item 3 (CRM) é o que dá vida ao conteúdo que aparece nos itens
1 e 2 — por isso banco de dados (MySQL) provavelmente entra nessa fase, substituindo o
`data/turmas.json` atual por tabelas de verdade (`cursos`, `turmas`, `pedidos`, `alunos`,
`certificados` — ver esboço de schema em `old_site/funcionalidades_sistema.txt`, seção final).

Quando entrarmos nessa fase, o pedido é para retomar esta conversa a partir daqui.
