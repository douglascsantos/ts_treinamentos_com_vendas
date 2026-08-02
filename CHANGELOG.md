# Changelog — TS Treinamentos em Saúde (site novo)

Versionamento: `PROTOTIPO.MACRO.MICRO` (ver comentário em `includes/version.php`).
A versão exibida no rodapé do site vem sempre desse arquivo — atualize-o a cada entrada nova aqui.
Ordem cronológica: mais antiga no topo, mais recente no final do arquivo — sempre adicione a
entrada nova no FINAL, não no início.

## v0.0.0 — "Aurora" — 2026-07-29

Primeira versão navegável do protótipo: landing page única, mobile-first, em HTML/CSS/JS/PHP puro.

- Estrutura inicial na raiz do repositório (index.php + includes/ + assets/), pronta para subir em
  hospedagem compartilhada (Hostinger) sem build step.
- 8 seções implementadas: Hero, Credibilidade rápida, Agenda de Turmas, Carrossel/Galeria
  (estático por enquanto, pensado para depois virar feed automático do Instagram), Sobre a
  Escola, FAQ (accordion), CTA final e Rodapé completo (contato, mapa, redes sociais).
- Header fixo com menu mobile (drawer) e item "Área do Aluno" como placeholder, sem link
  funcional ainda.
- Botão flutuante de WhatsApp fixo em todas as telas.
- Paleta, tipografia (Poppins/Inter) e imagens 100% reaproveitadas do site atual (`old_site/`).
- Rodapé (nome da marca, ano e versão) alimentado por `includes/version.php`.
- Sem banco de dados ainda — agenda de turmas é uma lista estática em `index.php`
  (fica para uma versão futura migrar para MySQL).
- Agente de deploy (`.claude/agents/deploy-check.md`) criado para revisar bugs, erros e
  segurança antes de qualquer publicação em produção.

## v0.0.1 — "Aurora" — 2026-07-29

- Correção de estrutura para deploy via Git na Hostinger: o site (index.php, includes/,
  assets/) estava dentro de uma pasta `public_html/` no repositório, o que causava aninhamento
  duplicado (`public_html/public_html/...`) no servidor e resultava em erro 403 (sem index na
  raiz do domínio). Todos os arquivos do site foram movidos para a raiz do repositório —
  a raiz do repo agora corresponde diretamente à raiz do domínio (`public_html` no servidor).

## v0.1.0 — "Aurora" — 2026-07-29

Mudança macro: a Agenda deixa de ser uma lista fixa no código e passa a ser gerada a partir de
conteúdo real, e cada curso ganha sua própria página de venda.

- **Agenda orientada a dados**: `index.php` agora lê `data/turmas.json` em vez de um array fixo
  (`includes/turmas.php` tem os helpers de carregamento/formatação). Cards da Agenda mostram
  preço real e linkam para a página do curso.
- **Página de venda por curso**: cada turma em `data/turmas.json` ganha uma página própria em
  `cursos/{slug}.php` (template compartilhado em `includes/curso-page.php`), separada da landing
  page, com data(s), horário, local, preço e CTA de WhatsApp.
- **Agente `agenda-sync`** (`.claude/agents/agenda-sync.md`) + `tools/sync_agenda.py`: processa o
  conteúdo colocado pelo dono do site na pasta local `agenda/` (nunca vai pro git) e publica no
  site — recorta/redimensiona a imagem, atualiza `data/turmas.json` e gera a página do curso.
  Suporta dois formatos: imagem solta com os dados no nome do arquivo (`curso NOME DD-MM-AA HHMM
  valor PRECO.jpg` — o formato realmente usado no dia a dia) ou pasta com `dados.txt` para casos
  que precisam de mais controle (status manual, descrição). Ver `agenda/AGENDA_README.md`.
- **Carrossel do Instagram dinâmico**: `includes/instagram.php` busca fotos/reels (e tenta
  stories) recentes do Instagram via Graph API, com cache de 1h em `data/instagram-cache.json` e
  fallback automático para fotos estáticas reais do site quando não há token configurado ou a
  API falha. Ver `INSTAGRAM_SETUP.md` para gerar as credenciais na Meta — ainda não configurado
  em produção (`secret.env` continua com os campos vazios).
- **`ROADMAP.md`** criado com os itens combinados para depois: e-commerce/checkout de verdade,
  Área do Aluno (compras, financeiro, contrato, histórico/presenças, certificados, aulas online)
  e CRM/painel administrativo multiusuário com permissão por função.
- Cursos reais cadastrados nesta versão (6, a partir do conteúdo em `agenda/`): Diluições e
  Preparações de Medicamentos, Administração de Medicamentos Injetáveis/Punção Venosa/Coleta de
  Sangue, Furo Humanizado, Rotinas Hospitalares, Plantão Sem Medo, Avaliação de Feridas e
  Curativos. Os 6 cursos fictícios usados como placeholder na v0.0.x foram removidos.
- `deploy-check` atualizado para cobrir o novo escopo (JSON de dados, páginas de curso, cache do
  Instagram, segurança do token).

## v1.0.0 — "Avelã" — 2026-07-29

Primeira versão oficial fora da fase de protótipo (dígito PROTOTIPO passa de `0` para `1` —
ver `includes/version.php`). O rótulo "Protótipo" foi removido do rodapé.

- **Carrossel do Instagram removido**: a integração via Graph API (`includes/instagram.php`,
  `INSTAGRAM_SETUP.md`, cache, campos `INSTAGRAM_*` em `secret.env`) foi descontinuada a pedido —
  não deu pra validar em produção antes de mudar de direção. O ícone/link do Instagram no rodapé
  (rede social simples, sem API) continua normalmente.
- **Novo carrossel "Produtos TS Treinamentos"** no lugar: cards, kits de treinamento e books.
  Arquitetura pronta (`includes/produtos.php`, `includes/produto-page.php`, seção condicional em
  `index.php`, `data/produtos.json`, páginas individuais em `produtos/{slug}.php`, imagens em
  `assets/images/produtos/`) — mas **sem conteúdo ainda**: a seção fica automaticamente oculta
  até `data/produtos.json` ter produtos cadastrados.
- Nova pasta local `produtos_ts_site/` (fora do git, no `.gitignore`) para o dono do site
  preparar fotos/dados dos produtos, no mesmo espírito de `agenda/`. O agente que processa essa
  pasta (`produtos-sync`) ainda não foi construído — registrado em `ROADMAP.md` como próximo
  passo de curto prazo.
- `format_price()` centralizado em `includes/functions.php` (antes vivia só em
  `includes/turmas.php`), agora compartilhado entre turmas e produtos.
- `deploy-check` atualizado: escopo agora cobre `produtos/` e `data/produtos.json`, removida a
  checagem específica de segurança do token do Instagram (não existe mais nesse código).

## v1.0.1 — "Avelã" — 2026-07-30

Catálogo de produtos ganha conteúdo real e agente próprio. `stage` do rodapé passa a
`production` (explícito), saindo do `''`/sem-rótulo da v1.0.0.

- **Agente `produtos-sync`** (`.claude/agents/produtos-sync.md`) criado, espelhando o
  `agenda-sync`: lê `produtos_ts_site/` (pasta local, fora do git), processa as imagens e
  atualiza `data/produtos.json` + `produtos/{slug}.php`.
- **`tools/sync_produtos.py`** criado, com **três** formatos de nome de arquivo suportados:
  `produto NOME tipo TIPO valor PRECO estoque QTD.ext` (card/kit físicos), a variante sem
  `estoque` para `book` (e-book em PDF — nunca esgota), e `ebook_online_NOME_valorPRECO.ext`
  (nome "colado" em camelCase, sem espaços — formato realmente usado no dia a dia).
- **`tools/sync_common.py`** criado: funções que antes só existiam em `sync_agenda.py`
  (`slugify`, `parse_price`, `process_image`, correção de acentos) agora são compartilhadas
  entre `sync_agenda.py` e `sync_produtos.py`. `sync_agenda.py` foi refatorado para usar esse
  módulo — comportamento e resultado confirmados idênticos após o refatoramento.
- **Confirmado com o cliente**: produtos do tipo `book` são e-books entregues em PDF após a
  compra — nunca têm estoque físico. `includes/produtos.php` (`produto_status()`,
  `produto_e_digital()`) e `includes/produto-page.php` já tratam isso: status sempre
  "Disponível", campo de estoque substituído por "Formato: E-book em PDF" na página do produto,
  e um aviso de entrega digital no lugar da nota de pagamento manual.
- **3 produtos reais publicados** (primeiros do catálogo): *Anotação de Enfermagem*, *Guia de
  Medicamentos* e *Hipodermóclise na Prática* — todos e-books.
- Item "Produtos" adicionado ao menu principal (`includes/header.php`, desktop e mobile),
  apontando para `#produtos` — estava de propósito ausente enquanto o catálogo estava vazio.
- `produtos_ts_site/PRODUTOS_README.md` criado documentando os formatos de nome aceitos.
- `ROADMAP.md` atualizado: item "Agente de produtos" removido (concluído).
- `deploy-check` atualizado para o novo estado do catálogo de produtos.

## v2.0.0 — "Star" — 2026-07-31

Maior salto do site desde o protótipo inicial: contratos reescritos, checkout com cupom de
desconto, área do aluno completa (Google, foto, dados editáveis, financeiro, e-book), banco de
dados MySQL criado em produção e o primeiro sistema de painel interno (diretor/administrativo/
instrutor) com login próprio.

**Contratos e checkout**
- Contrato de curso reescrito: sem matrícula parcial de 15% (removida), reserva de vaga só com
  pagamento integral, direito de arrependimento CDC art. 49 (7 dias, reembolso integral) e
  reembolso parcial escalonado por proximidade do curso (90/80/70/30/0%).
- Termo de LGPD/uso de imagem e termo de consentimento para práticas com risco de lesão (este
  último redigido como consentimento informado, não renúncia — orientação do COREN), unidos ao
  contrato de prestação de serviços num modal com abas.
- `status_entrega` do pedido nunca fica em branco: curso e e-book nascem `entregue`, produto
  físico nasce `a_caminho`.
- Cupom de desconto: gerado pelo administrativo pra um curso/produto específico (R$ ou %, prazo
  em dias), link único (`?cupom=CODIGO`), uso único.

**Área do aluno**
- Login com Google (OAuth 2.0) além de e-mail/senha. CPF é obrigatório pra todo aluno sem
  exceção — conta via Google passa por uma etapa de cadastro completo (não só CPF) antes de
  existir de fato, com upload de foto (câmera do celular, webcam ao vivo ou arquivo).
- Cabeçalho mostra "Bem-vindo, {primeiro nome}" quando logado, linkando pra `meus-dados.php`
  (trocar senha, editar WhatsApp/nascimento/endereço — nome/e-mail/CPF travados).
- `minha-conta.php` reorganizada: cursos (concluído ou agendado pra quando) e produtos (status de
  entrega) em seções separadas.
- `financeiro.php`: boletos parcelados, upload manual pelo administrativo — baixável dentro do
  prazo, bloqueado se vencido sem pagamento, liberado se pago.
- `ebook-download.php`: download protegido do PDF comprado, PDF armazenado fora do
  `public_html` com nome não adivinhável.

**Banco de dados**
- 10 tabelas criadas no banco de produção MySQL (Hostinger) — `alunos`, `turmas`, `produtos`,
  `pedidos`, `instrutores`, `administradores`, `certificados`, `cupons`, `gastos`, `boletos` (ver
  `db/schema.sql`). O site segue lendo/gravando a maior parte em JSON; migrar o código é a
  próxima fase.
- Corrigido durante os testes: `cupons`/`boletos`/`certificados` tinham foreign key pra
  `pedidos`/`alunos` no MySQL, mas pedidos e alunos ainda vivem em JSON — toda tentativa de
  vincular um cupom/boleto a um pedido real quebrava com erro de integridade. FKs removidas
  (validação de dono continua feita na aplicação).

**Painel interno da equipe (novo)**
- `equipe/login.php`: login único pra administrativo/diretor/instrutor, cada um com seu painel.
- Diretor: cadastra instrutor, administrativo e aluno; upload da própria assinatura.
- Administrativo: gestão de turmas (criar curso, vagas internas, atribuir instrutor, carga
  horária), cupons, boletos, gastos (por curso/fixo/variável/patrimônio, com resumo por tipo).
- Instrutor: vê os próprios dados, upload da própria assinatura.
- Certificado sempre leva assinatura de 1 diretor + 1 instrutor (imagem se cadastrada, senão
  nome/profissão/registro em texto) — geração de PDF em si ainda não construída.
- `tools/sync_agenda.py` atualizado pra preservar vagas/instrutor/código/carga horária ao
  re-sincronizar (não sobrescreve mais o que o painel administrativo editou).
- `tools/sync_produtos.py` ganhou suporte a vincular PDF de e-book solto em `produtos_ts_site/`
  ao produto correspondente, renomeando e movendo pra fora do repositório.

## v2.0.1 — "Star" — 2026-07-31

Correção reportada pelo cliente: cabeçalho aparecendo transparente/desalinhado.

- `.site-header` usava `backdrop-filter: blur(8px)` sem o prefixo `-webkit-backdrop-filter` —
  Safari (iOS/Mac) ignora a versão sem prefixo, então o desfoque não era aplicado e sobrava só o
  fundo semi-transparente (`rgba(255,255,255,.92)`), deixando o conteúdo por trás "vazar" através
  do cabeçalho. Adicionado o prefixo, mais um fallback `@supports` pra fundo sólido em navegadores
  sem suporte a nenhuma das duas versões (nunca mais fica transparente demais).

## v2.0.2 — "Star" — 2026-07-31

Duas correções reportadas pelo cliente.

- **Painel da equipe quebrado no mobile**: as 7 tabelas em `equipe/*.php` (instrutores,
  administradores, cupons, boletos, gastos) não tinham nenhum tratamento de rolagem horizontal, e
  o `body` do site é `overflow-x: hidden` — no celular, colunas que não cabiam eram simplesmente
  cortadas/escondidas em vez de aparecerem. Em desktop havia espaço de sobra, por isso passava
  despercebido lá. Adicionado `.equipe-table-wrap` (rola só a tabela, não a página) em
  `includes/equipe.php`, aplicado nas 7 tabelas.
- **Erro 500 no login da equipe em produção**: `includes/db.php` agora lança um erro descritivo
  em vez de deixar uma `PDOException` sem tratamento estourar como 500 em branco — necessário pra
  diagnosticar por que a conexão com o MySQL está falhando em produção (suspeita: `secret.env`
  ausente/incompleto no servidor, fora do repositório). `equipe/login.php` captura e mostra esse
  erro temporariamente (marcado `diagnostico_temporario` no código — remover depois de confirmada
  e corrigida a causa raiz).

## v2.0.3 — "Star" — 2026-07-31

Causa raiz do erro 500 confirmada e corrigida: não era o `secret.env` (esse estava correto) — era
o Remote MySQL da Hostinger recusando a conexão vinda do próprio servidor de produção
(`Access denied for user '...'@'2a02:4780:13::f'`, o IP de saída do servidor, nunca liberado no
painel — só o IP da máquina de desenvolvimento estava). Resolvido trocando `DB_HOST` de
`srv725.hstgr.io` pra `localhost` no `secret.env` do servidor (hosting e banco são a mesma conta
Hostinger, então a conexão interna não passa pela restrição de acesso remoto). Login da equipe
confirmado funcionando em produção.

- Removida a mensagem de diagnóstico detalhada de `equipe/login.php` (mostrava o erro técnico na
  tela — era intencional e temporário, só pra eu conseguir ver a causa sem acesso ao log do
  servidor). Volta a mostrar "Erro ao conectar. Tente novamente em instantes." — genérico, seguro.

## v2.1.0 — "Iaris" — 2026-07-31

Correção crítica de mobile (canal principal de vendas) + novo processo de qualidade.

- **Bug real, regressão do v2.0.1**: `.mobile-nav` (o menu-gaveta do celular) vivia dentro do
  `<header>`, e o `<header>` ganhou `backdrop-filter` no v2.0.1 (correção do cabeçalho
  transparente). `backdrop-filter`/`transform`/`filter` num elemento cria um novo "containing
  block" pra descendentes `position: fixed` — o menu parou de se posicionar em relação à tela e
  passou a se posicionar em relação à altura pequena do cabeçalho, encolhendo pra uma caixinha com
  o conteúdo vazando por cima do hero. Corrigido movendo `.mobile-nav` pra fora do `<header>`
  (`includes/header.php`), como irmão do `.mobile-nav-overlay` (que já estava certo). Confirmado
  via captura de tela real do cliente no celular.
- `.badge` (selo "Certificação reconhecida" no hero) também usava `backdrop-filter` sem o prefixo
  `-webkit-`, mesma classe de problema do v2.0.1 — corrigido por consistência.
- `[hidden]` agora tem `display: none !important` pra `.mobile-nav`/`.mobile-nav-overlay` —
  reforço contra qualquer futuro conflito de especificidade que module o atributo `hidden`.
- **Novo agente `.claude/agents/design-mobile-first.md`**: audita qualidade visual/UX antes de
  todo deploy, com checklist específico pra essa classe de bug (containing block, overflow-x
  silencioso do `body`, prefixos de navegador, alvos de toque, z-index). A partir de agora, todo
  deploy passa primeiro por esse agente — só chama o `deploy-check` depois de um veredito GO
  (regra permanente, salva em memória por pedido explícito do cliente).

## v2.1.1 — "Iaris" — 2026-07-31

Duas melhorias pedidas pelo cliente: acesso mais fácil pra equipe e URLs mais limpas.

- **Login unificado**: entrar em `area-do-aluno.php` com e-mail/senha de diretor, administrativo
  ou instrutor agora redireciona automaticamente pro painel certo (`equipe/diretor.php`,
  `administrativo.php` ou `instrutor.php`) — não precisa mais saber de cor a URL separada
  `equipe/login.php`. Se já estiver logado como equipe e cair em `area-do-aluno.php` de novo,
  também redireciona direto, sem pedir login de novo. Falha de conexão com o banco (ex.: fora do
  ar) cai graciosamente pro erro normal de aluno em vez de quebrar a página.
- **URLs sem `.php`** (`.htaccess`, novo): todo o site agora responde por URLs limpas
  (`/equipe/login` em vez de `/equipe/login.php`) — quem acessar a versão com `.php` é
  redirecionado (301) pra versão limpa automaticamente, sem precisar mudar nenhum link existente
  no código. Só afeta GET (navegação) — formulários, checkout e o webhook da InfinitePay
  continuam funcionando exatamente igual, nunca são redirecionados.

## v2.2.0 — "Clara" — 2026-07-31

Padrão de UX único pra todo o painel interno da equipe, pedido pelo cliente pra deixar as telas de
cadastro mais limpas no celular. Regra nova e permanente: **nunca excluímos nada, só inativamos**
— garante que dado nenhum fica corrompido ou incompleto por causa de referência quebrada.

- **Padrão "lista primeiro"**: toda tela de cadastro (instrutores, administradores, alunos,
  turmas) agora mostra a lista primeiro, com um botão "+" verde que revela o formulário de
  cadastro (`[data-toggle-target]`, novo helper genérico em `assets/js/main.js` + CSS
  compartilhado em `includes/equipe.php`). Cada item da lista é um link — clicar nele abre o
  mesmo formulário preenchido pra editar, com Salvar/Cancelar, voltando pra lista ao salvar.
- **Desabilitar em vez de excluir**: todo item da lista ganhou um botão "Desabilitar"/"Reativar"
  que só marca `ativo: false` — nunca remove o registro. `turmas` e `alunos` ganharam o campo
  `ativo` (JSON, antes só `instrutores`/`administradores` no MySQL tinham); turma inativa some do
  site público e do checkout (`turma_ativa()`, novo helper) mas continua aparecendo normalmente
  pra quem já comprou (`minha-conta.php` não filtra, de propósito); aluno inativo não consegue
  mais logar (`verificar_login()`, `auth/google-callback.php`).
- **`remover_turma()` removida**: era a única função de exclusão de verdade que restava no código
  (`includes/turmas.php`) — substituída por `atualizar_turma($slug, ['ativo' => false])`.
  `equipe/turmas.php` reescrita: criar turma fica atrás do "+", editar é por link
  (`?editar={slug}`) numa tela dedicada, e o `slug` nunca muda depois de criado (mesmo se o nome
  do curso for corrigido) pra não quebrar link de venda nem pedido antigo que aponta pra ele.
- Administrador não consegue mais desabilitar a própria conta (bloqueio explícito em
  `equipe/administradores.php`, evita se trancar fora do painel sem querer).
- `equipe/diretor.php` e `administrativo.php` viraram dashboards enxutos — grade de cartões-link
  pra cada área, sem formulário nenhum solto na tela inicial.
- `equipe/cupons.php` e `equipe/gastos.php`: só o formulário de criação ganhou o mesmo botão "+"
  por consistência visual — continuam sem editar/desabilitar de propósito (são registro
  financeiro/histórico de uso único, não cadastro editável).
- **Bug pego pela revisão de design antes do deploy**: `.form-row` (classe já existente, pensada
  só pra par rótulo-curto + campo-largo tipo "Pronome") foi reaproveitada por engano em vários
  formulários novos pra pares onde os dois campos precisam de largura normal (nome, CPF, e-mail,
  WhatsApp, preço, data) — como o `body` do site é `overflow-x: hidden`, o campo esmagado em
  110px ficaria cortado silenciosamente em vez de aparecer. Corrigido com uma classe nova,
  `.equipe-form-row` (`includes/equipe.php`), 1 coluna no celular e 2 colunas iguais a partir de
  480px. Também aumentado o alvo de toque de "Desabilitar"/"Reativar" pra 44px e adicionado
  reforço `[hidden]` em `.toggle-form` (mesmo padrão do `.mobile-nav`, ver v2.1.0).
- **Correção de acesso**: `equipe/instrutores.php` e `equipe/administradores.php` (recém-separadas
  de dentro de `equipe/diretor.php` nesta mesma versão) ficaram sem a checagem `nivel === 'diretor'`
  que a página-irmã `alunos.php` já tinha — uma conta `administrativo` conseguia editar qualquer
  administrador (inclusive a si mesma, se autopromovendo a diretor) e cadastrar instrutor, o que
  deveria ser exclusivo do diretor. Pego pela revisão do `deploy-check` antes de qualquer coisa ir
  pro ar; corrigido adicionando o mesmo bloqueio de `alunos.php` nas duas telas.

## v2.2.1 — "Clara" — 2026-07-31

- **`tools/sync_agenda.py`** não preservava o campo `ativo` (novo na v2.2.0) ao re-sincronizar uma
  turma a partir da pasta `agenda/` — o dict novo montado pelo script nunca definia esse campo, e
  como o resto do código trata a ausência de `ativo` como "ativo por padrão", uma turma desabilitada
  pelo painel administrativo voltaria a aparecer no site sozinha na próxima sincronização, sem
  ninguém reativar de propósito. Adicionado `ativo` em `ADMIN_MANAGED_KEYS`/`ADMIN_MANAGED_DEFAULTS`
  (mesmo padrão já usado pra vagas/instrutor/código/carga horária) — agora o sync sempre preserva o
  valor que o administrativo definiu. Pego pelo `deploy-check` como achado não-bloqueante na v2.2.0;
  corrigido em seguida por proteger diretamente a política "nunca excluir, só inativar" dessa mesma
  versão.

## v2.3.0 — "Nova" — 2026-07-31

Maior lote de mudanças desde a v2.2.0: painel interno reorganizado com menu de verdade, produtos e
imagens da agenda viram cadastro administrável, e o ciclo completo "instrutor lança presença/nota →
administrativo conclui a turma → diploma é gerado e verificável publicamente" existe pela primeira vez.

**Painel interno — menu e visual**
- Cabeçalho de todo `equipe/*.php` reformulado: em vez da lista de links "·"-separada (a causa do
  "feio e confuso" reportado), agora é uma marca + botão "☰ Menu" que revela um painel organizado
  com ícone por item, e o primeiro nome da pessoa logada vira link direto pro próprio perfil. Toda
  página ganhou um `<h1>` visível com o título (antes só existia na aba do navegador).
- `equipe/diretor.php`/`administrativo.php`: grade de cartões com ícone, até 3 colunas.
- CSS do painel reescrito mobile-first (era `max-width`, virou `min-width`, seguindo o padrão do
  resto do site) + toques de 44px em todos os botões/campos.

**Perfil pessoal + ferramentas do diretor** (`equipe/perfil.php`, novo)
- Qualquer pessoa da equipe edita os próprios dados de contato, troca senha (confirmando a atual) e
  inclui/remove o campo opcional de registro profissional (instrutor).
- Só o diretor: backup do banco (.zip com todas as tabelas em SQL) e backup do site (.zip do
  repositório) sob demanda, gerados na hora e nunca salvos no servidor; tela de logs de acesso
  (`equipe/logs.php`) e tela de IPs com bloqueio/liberação (`equipe/ips.php`) — bloqueio em nível de
  aplicação, registrado em toda requisição do site (`includes/acesso.php`).

**Produtos e imagens da agenda viram cadastro** (`equipe/produtos.php`, `equipe/agenda.php`, novos)
- Produtos (cards, kits, e-books): mesmo padrão lista → "+" → editar → desabilitar dos outros
  cadastros deste painel. Upload de imagem de marketing (com instrução de medida/formato/qualidade
  na própria tela) e, pra e-book, upload do PDF que o cliente recebe.
- Imagens da seção Agenda da home: até 3 ativas ao mesmo tempo, cada uma com legenda, nunca
  excluídas — só desabilitadas. Sem nenhuma ativa (e o mesmo vale se todos os cursos ou todos os
  produtos forem desabilitados), a home mostra um aviso "Em atualização" no lugar, pra visitante
  nunca ver a seção vazia ou quebrada.
- Item inativo sempre desce pro fim da lista no painel (produtos, turmas, imagens da agenda).

**Conclusão de turma e diploma** (novo — `equipe/minha-turma.php`, `equipe/turmas.php`,
`certificado.php`, `includes/certificados.php`)
- Instrutor vê as próprias turmas, marca presença + nota (0-10) + observação opcional por aluno
  (observação só administrativo/diretor e o próprio instrutor veem, nunca o aluno) e finaliza —
  liberando a turma pro administrativo poder concluir.
- Administrativo só concebe "Concluir turma" depois de desabilitada e finalizada pelo instrutor, com
  todos os campos preenchidos (inclusive instrutor e administrativo responsável, ambos assinam o
  diploma — escolhidos no cadastro da turma). Antes de confirmar, checklist dos matriculados pra
  escolher quem recebe diploma + campo de observação interna.
- Cada diploma emitido vira um certificado com código de verificação público
  (`certificado.php?codigo=...`), sem exigir login — mostra nome do aluno, curso, carga horária,
  data e as duas assinaturas (imagem se cadastrada, senão nome/cargo em texto). Página imprimível
  (`window.print()`), mesmo princípio já usado no contrato assinado — sem depender de biblioteca de
  PDF no servidor. Aluno vê os próprios certificados em "Meus certificados" (`minha-conta.php`).

**Correção crítica pega pelo teste local antes do deploy**
- `includes/functions.php` passou a registrar log de acesso em toda requisição (novo, ver acima),
  o que carrega `includes/storage.php` de propósito. Vários arquivos do site (checkout, financeiro,
  e-book, boleto, webhook, login Google, área do aluno...) também carregavam `storage.php` direto,
  com `require` simples em vez de `require_once` — o site inteiro quebrava com "Cannot redeclare
  function storage_path()" assim que os dois caminhos se cruzavam na mesma requisição. Pego pelo
  teste local (`php -S`) rodado antes do deploy, nunca chegou a subir pro ar; corrigido trocando
  todo `require` de `storage.php` no projeto por `require_once`.

## v2.3.1 — "Nova" — 2026-07-31

Dois achados não-bloqueantes do `deploy-check` na v2.3.0, corrigidos em seguida.

- `certificado-assinatura.php` não tinha o mesmo `try/catch` em volta da consulta ao banco que
  `certificado.php`/`minha-conta.php` já usam — se o banco estivesse fora do ar, em vez do 404
  normal a página quebrava com erro não tratado. Agora cai no mesmo 404 gracioso dos outros casos.
- `gerar_backup_banco_zip()` (`includes/backups.php`) só apagava o `.sql` temporário no caminho de
  sucesso — se o `ZipArchive` falhasse no meio, o arquivo temporário ficava perdido na pasta temp do
  servidor. Envolvido num `try/finally` pra sempre limpar, dê certo ou não.

## v2.3.2 — "Nova" — 2026-07-31

Dois bugs reportados pelo cliente: menu do painel não abria, e logout não levava pra home.

- **Botão "☰ Menu" do painel da equipe não abria** — `equipe_footer_html()` carregava
  `assets/js/main.js` sem parâmetro de cache (`?v=...`), diferente do rodapé do site público que já
  tinha esse cuidado desde sempre. `main.js` foi editado várias vezes nesta sessão (inclusive pra
  adicionar o próprio código do menu); sem versionamento na URL, o navegador podia estar servindo uma
  cópia antiga em cache, de antes desse código existir — o clique não fazia nada, sem erro visível,
  porque o `addEventListener` do botão nunca chegava a ser registrado. Corrigido adicionando
  `?v=<?= $version['version'] ?>` no `<script>` do painel, igual ao site público.
- **Logout (aluno e equipe) não voltava pra página inicial** — `logout.php` mandava o aluno de volta
  pra `area-do-aluno.php` (tela de login) e `equipe/logout.php` mandava a equipe pra `equipe/login.php`.
  Agora os dois redirecionam pra raiz do site (`/`), já deslogado — pedido explícito do cliente pra
  que ninguém saia direto numa tela de login depois de sair da conta.

## v2.3.3 — "Nova" — 2026-08-01

Cliente reportou erro 500 ao tentar cadastrar aluno, produto e instrutor pela tela do diretor.
Causa raiz real encontrada e corrigida: **cadastro de instrutor (e administrador) não checava CPF/
e-mail duplicado antes de gravar** — diferente do cadastro de aluno, que já tinha essa checagem.
Como `cpf` e `email` são colunas `UNIQUE` no banco, tentar cadastrar um CPF/e-mail que já existe
(bem provável ao testar o formulário mais de uma vez) disparava uma exceção do banco sem tratamento
nenhum — e isso vira, na prática, exatamente um erro 500.

- `equipe/instrutores.php`: adicionada checagem de CPF/e-mail duplicado antes de salvar (mensagem
  amigável em vez de estourar), usando nova função `find_instrutor_by_cpf()` (`includes/instrutores.php`).
- `equipe/administradores.php`: mesma checagem pra e-mail duplicado (tinha o mesmo problema).
- Toda a lógica de salvar (checagem de duplicado + `criar_*`/`atualizar_*`) nas duas telas agora está
  dentro de um `try/catch` — qualquer outra falha do banco (fora do ar, timeout) também vira mensagem
  amigável em vez de tela quebrada.
- **Revisão mais ampla, como pedido**: `equipe/instrutores.php`, `equipe/administradores.php` e
  `equipe/turmas.php` (que também carrega instrutores/administradores pros campos de responsável)
  chamavam `listar_instrutores()`/`listar_administradores()` sem nenhuma proteção — se o banco
  estivesse fora do ar por qualquer motivo, a tela inteira quebrava (nem a lista aparecia). Agora as
  três mostram um aviso "não foi possível conectar" e continuam funcionando com o que der pra
  mostrar, em vez de tela em branco com erro.
- `equipe/alunos.php` e `equipe/produtos.php`: cadastro/edição já funcionava certo em teste local
  fiel (dado novo, sem duplicata), mas ganhou o mesmo `try/catch` de proteção por precaução —
  qualquer falha inesperada de gravação agora vira mensagem amigável, nunca mais uma tela de erro 500.

## v2.3.4 — "Nova" — 2026-08-01

Cliente ainda via "tente novamente em instantes" ao tentar cadastrar instrutor mesmo depois da
v2.3.3 — a correção anterior parou o erro 500, mas a mensagem genérica escondia qual era o
problema de verdade, tanto pro cliente quanto pra mim tentar diagnosticar à distância.

- **Nova `equipe_erro_tecnico()`** (`includes/equipe.php`): as telas do painel interno (só staff
  logado, nunca o site público) agora mostram o motivo técnico real do erro em vez de um genérico
  "tente novamente" — ex.: CPF/e-mail já cadastrado, ou o erro exato que o banco devolveu. Mensagem
  de exceção do PDO não inclui credencial/host, só o código do erro e o que o driver do banco
  reportou, então é seguro mostrar pra um administrador autenticado.
- Aplicado em todos os `try/catch` adicionados na v2.3.3: `equipe/instrutores.php`,
  `equipe/administradores.php`, `equipe/turmas.php` (inclusive o aviso de "banco fora do ar" ao
  carregar a lista de instrutores/administrativos pros campos de responsável), `equipe/alunos.php`
  e `equipe/produtos.php`.
- Com isso, na próxima tentativa a mensagem de erro deve mostrar exatamente o que está impedindo o
  cadastro do instrutor — se continuar acontecendo, a mensagem em si já vai apontar a causa.

## v2.4.0 — "Nova" — 2026-08-01

Nova tela pro diretor/administrativo: vendas de produtos.

- **`equipe/vendas.php`** (novo): lista todo pedido de produto (card/kit/e-book) — aluno, produto,
  número do pedido, data, preço — com status do pagamento e status do envio editáveis direto na
  linha (dois selects + Salvar). Produto digital (e-book) não mostra opção de envio — a entrega é o
  próprio download, sempre liberado assim que o pagamento é confirmado.
- O que for salvo aqui aparece na hora pro aluno em `minha-conta.php`, porque é o mesmo registro de
  pedido lido nos dois lugares — não duplica dado em lugar nenhum.
- Status do pagamento normalmente já vem certo sozinho pelo webhook da InfinitePay; a edição manual
  aqui serve pra correção pontual (ex.: pagamento combinado fora do gateway).
- Nova constante compartilhada `PEDIDO_STATUS_LABELS` (`includes/pedidos.php`) — `minha-conta.php`
  passou a reaproveitar em vez de ter sua própria cópia dos mesmos rótulos.
- Link "Vendas" adicionado no menu do painel e nos dashboards do diretor/administrativo.

## v2.4.1 — "Nova" — 2026-08-01

Cliente reportou não conseguir cadastrar instrutor: "Erro técnico: Call to undefined function
validar_cpf()" — a mensagem coerente da v2.3.4 revelou a causa real na hora.

- **Bug real, provavelmente presente desde que `equipe/instrutores.php` foi criado nesta sessão**:
  a validação de CPF (`validar_cpf()`) vivia só em `includes/alunos.php`, que `equipe/instrutores.php`
  nunca carregava — todo cadastro de instrutor sempre ia quebrar nessa linha, primeiro como erro 500
  puro (antes da v2.3.3), depois com a mensagem "tente novamente" genérica (v2.3.3), e só ficou
  visível de verdade com o `equipe_erro_tecnico()` da v2.3.4. `equipe/alunos.php` nunca teve esse
  problema porque já carregava `includes/alunos.php` de propósito.
  - Corrigido na raiz: `validar_cpf()`/`normalizar_cpf()` mudaram de `includes/alunos.php` pra
    `includes/functions.php` — CPF não é conceito exclusivo de aluno (instrutor também tem), e
    `functions.php` é carregado em toda página do site, então a função fica disponível em qualquer
    lugar sem precisar lembrar de um require extra.
  - **Auditoria adicional**: escrevi um script que usa o tokenizer do PHP pra conferir, em todo
    `equipe/*.php` e nas principais páginas públicas, se alguma função chamada não está declarada
    em nenhum dos `require`s do próprio arquivo (a mesma classe de bug, que `php -l` nunca pegaria
    por ser erro de runtime, não de sintaxe). Rodou limpo em todo o resto do site — esse era o único
    caso.
- **Revisão do processo de sessão/login** (pedido do cliente — relatos de "erro de sessão"):
  `includes/csrf.php` nunca configurava duração de sessão nem parâmetros do cookie, então o site
  dependia do padrão do PHP na hospedagem — em compartilhado isso costuma ser bem curto
  (`session.gc_maxlifetime` ~24min é comum). Preencher um cadastro longo (endereço, foto) ou deixar
  o painel da equipe aberto trabalhando por mais tempo que isso faz a sessão sumir no meio, e o
  próximo clique cai em "sessão expirada" do nada. Corrigido: sessão agora dura 4h de propósito
  (cookie + tempo que o PHP mantém no servidor), com `HttpOnly` e `SameSite=Lax` (reforço de
  segurança, não deixa o cookie ser lido por JavaScript nem enviado em requisição de outro site).
  Verificado localmente que o cookie de sessão sai com `Max-Age=14400` (4h) corretamente.

## v2.5.0 — "Nova" — 2026-08-01

Mudança de fundo no funil de compra: conta de aluno agora é exigida ANTES do pagamento (era depois).
Isso permite mandar e-mail de confirmação de compra pro endereço certo assim que o pagamento é
aprovado, e mostrar direto a informação relevante (e-book liberado / turma matriculada) na volta.

**Cadastro antes do pagamento**
- Clicar em "Pagar agora" sem estar logado não vai mais direto pro link de pagamento — o aceite do
  contrato já é registrado nesse clique (mesma regra de sempre, nada muda aí), mas a compra fica
  guardada esperando login/cadastro (`$_SESSION['checkout_pendente']`) e a pessoa é levada pra Área
  do Aluno com um aviso: "Falta só um passo: entre ou crie sua conta pra finalizar sua compra de
  {item} — assim que confirmar, você vai direto pro pagamento."
- **Sem clique extra**: assim que o login, o cadastro ou o login com Google terminam com sucesso, a
  compra pendente é retomada sozinha e a pessoa já cai direto no link de pagamento — não precisa
  voltar na página do curso/produto pra clicar em "Pagar agora" de novo.
- Núcleo do checkout foi extraído de `checkout.php` pra `includes/checkout_helper.php`
  (`processar_checkout()`), reaproveitado tanto por quem já está logado quanto por quem acabou de
  logar/cadastrar — mesma lógica de validação de curso/produto/cupom/estoque de sempre, sem
  mudança de comportamento nesse núcleo.
- `criar_pedido()` agora recebe o `aluno_id` na hora de criar o pedido (antes sempre nascia nulo e
  só era vinculado depois via `vincular_pedido_aluno()` — essa função continua existindo, como rede
  de segurança pra links antigos/casos legados, mas deixa de ser o caminho principal).

**E-mail de confirmação de compra**
- Novo `includes/email.php` — envia por `mail()` nativo do PHP (hospedagem compartilhada, sem SMTP
  dedicado configurado). Disparado por `webhook-infinitepay.php` assim que o pagamento é confirmado
  de verdade (nunca antes, nunca sem essa confirmação — mesma regra de segurança que já existia pra
  marcar como pago). Falha no envio nunca derruba a resposta do webhook nem o fluxo de pagamento —
  só fica registrado no log.

**Página de retorno do pagamento mais informativa**
- `pagamento-concluido.php`: como o aluno já está logado nesse ponto, a tela some com o antigo CTA
  "Entre ou crie sua conta" e mostra direto o que interessa — e-book já liberado com botão de
  download, ou a turma em que foi matriculado (data, horário, local). Se o webhook ainda não chegou
  (pagamento aparece "pendente" por alguns segundos), mostra aviso pra atualizar a página em vez de
  informação errada. Checagem de dono do pedido (nunca mostra detalhe de pedido de outra pessoa só
  por adivinhar o número na URL).

## v2.5.1 — "Nova" — 2026-08-02

Cliente reportou erro ao iniciar o pagamento na compra de um produto. Testado extensivamente contra
a InfinitePay de verdade e contra o site em produção (chamada direta da API, e o funil completo —
cadastro novo e já logado — replicado com uma conta descartável real): o caminho comum de compra
(sem cupom) está funcionando. Encontrado e corrigido, por revisão de código, um caso real que gera
exatamente esse erro:

- **Cupom cobrindo 100% do preço (ou valor fixo maior que o preço) quebrava o pagamento**: a
  checagem de "item gratuito" em `processar_checkout()` (`includes/checkout_helper.php`) olhava só
  o preço original, antes do cupom ser aplicado — um cupom válido que zera o valor final não passava
  por essa checagem e seguia direto pra `infinitepay_create_link()` com `price_centavos: 0`, que a
  InfinitePay rejeita. Pro cliente aparecia exatamente como "Não foi possível iniciar o pagamento",
  mesmo com um cupom certo. Corrigido: quando o preço com desconto fica em zero (ou menos), o pedido
  agora é confirmado na hora como pago (sem passar pela InfinitePay, não tem o que cobrar), dispara o
  e-mail de confirmação e vai direto pra `pagamento-concluido.php` — mesmo tratamento que um
  pagamento de verdade confirmado pelo webhook.
- **Novo log de diagnóstico** (`checkout_log()`, grava fora do repositório junto dos outros logs):
  qualquer falha real da InfinitePay ao gerar o link de pagamento agora fica registrada com a
  resposta completa da API, pra não precisar reproduzir a compra do zero pra investigar da próxima
  vez.

## v2.5.2 — "Nova" — 2026-08-02

Cliente testou de novo depois da v2.5.1 com um valor de R$0,99 e o erro continuou. Causa raiz de
verdade: **a InfinitePay tem um valor mínimo pra gerar link de pagamento (R$1,00) que não é
documentado oficialmente** — confirmado testando a API real: um link de R$0,99 é recusado
("Total price must be greater than 1"), R$1,00 é aceito normalmente. A correção da v2.5.1 só
cobria o preço zerado por completo (cupom de 100%); um cupom que deixa um valor pequeno, mas maior
que zero (ex.: R$0,99), ainda caía direto na InfinitePay e era recusado do mesmo jeito.

- Nova constante `INFINITEPAY_VALOR_MINIMO_CENTAVOS` (`includes/infinitepay.php`), documentando o
  valor mínimo real observado.
- `processar_checkout()` (`includes/checkout_helper.php`) agora compara o preço final (depois do
  cupom) em centavos contra esse mínimo, e não só contra zero — qualquer valor abaixo de R$1,00 (o
  que na prática só acontece com cupom de desconto grande, preço de catálogo já é sempre R$1 ou
  mais) confirma o pedido na hora, sem tentar passar pela InfinitePay, exatamente como a v2.5.1 já
  fazia pro caso de preço exatamente zero — cobrança manual de menos de R$1 não compensa o esforço.
- Testado de novo direto contra a API real da InfinitePay com R$0,99 (confirmado recusado) e R$1,00
  (confirmado aceito), e localmente o fluxo de compra normal (preço cheio, sem cupom) continua
  funcionando igual.

## v2.6.0 — "Nova" — 2026-08-02

Bug de fundo descoberto testando a v2.5.2: um produto de teste ("teste", R$0,99) que o cliente
criou pelo painel (`equipe/produtos.php`) desapareceu depois dos deploys da v2.5.1/v2.5.2. Causa
raiz: **qualquer produto criado ou editado pelo painel administrativo podia ser apagado no próximo
deploy de código** — `data/produtos.json` vive dentro do repositório Git, e o painel grava direto
nesse mesmo arquivo no servidor; todo `git push` sobrescrevia o arquivo de volta pro que estava
commitado, descartando qualquer criação/edição feita ao vivo pelo painel desde o último commit.
Confirmado pelo histórico do Git: `data/produtos.json` nunca teve o produto "teste" registrado.

- **Catálogo de produtos migrou pro mesmo armazenamento de pedidos/alunos** (fora do repositório,
  `includes/storage.php`) — `load_produtos()`/`save_produtos()` (`includes/produtos.php`) agora
  leem/gravam nesse local, que nenhum deploy de código toca. `data/produtos.json` (dentro do
  repositório) continua existindo só como "semente" inicial: na primeira leitura depois do deploy,
  todo produto de lá que ainda não existe no catálogo ao vivo (por slug) é importado uma vez, sem
  nunca sobrescrever o que já existe — assim o agente `produtos-sync` continua funcionando pra
  produto novo, e edição feita pelo painel nunca mais é desfeita por um deploy.
- Produto criado pelo painel também gera o arquivo da página individual
  (`produtos/{slug}.php`) direto no servidor, fora do Git — mesmo problema, mesma causa: o arquivo
  sumia se algum deploy fosse feito antes do próximo `git add` desse arquivo específico (o que nunca
  acontecia, já que ninguém sabia que precisava fazer isso manualmente).
- **Produto "teste" recriado** com o preço corrigido pra R$1,01 (acima do mínimo da InfinitePay,
  ver v2.5.2) — dessa vez tanto o registro (`data/produtos.json`, semente) quanto a página
  (`produtos/teste.php`) foram commitados junto com essa correção, garantindo que sobrevivem ao
  deploy de hoje e a partir daí ficam protegidos pelo novo armazenamento.
- Testado localmente: migração automática (todos os 4 produtos importados uma única vez, sem
  duplicar em leituras repetidas), edição via painel sobrevivendo a uma simulação de reset do
  arquivo semente, e compra completa do produto "teste" (R$1,01) gerando link real da InfinitePay.
- **Risco relacionado, não corrigido nesta versão**: `data/turmas.json` (agenda de cursos) tem
  potencialmente o mesmo problema — também vive no repositório e também é editável ao vivo pelo
  painel (`equipe/turmas.php`). Ainda não confirmado se já causou perda de dado real; registrado
  pra investigar/corrigir numa próxima versão se o cliente notar o mesmo sintoma com turmas.

## v2.7.0 — "Nova" — 2026-08-02

Redesign pedido pelo cliente: `minha-conta.php` (Área do Aluno) não estava mobile-first e as
compras — o que a maioria dos alunos realmente vem fazer nessa tela — ficavam escondidas embaixo
de um bloco de boas-vindas com 4 botões grandes, a maioria redundante com algo que já existe em
outro lugar do site.

- **Compras primeiro**: a saudação virou uma linha só ("Olá, {nome}!"), sem o parágrafo grande
  de "em construção" nem os botões — cursos e produtos comprados aparecem imediatamente depois,
  no topo da página, em vez de precisar rolar.
- **4 botões grandes → menu hambúrguer**: "Voltar ao site" (redundante — clicar na logo já faz
  isso) e "Falar no WhatsApp" (redundante — já existe o botão flutuante de WhatsApp em toda
  página) foram removidos. "Financeiro" e "Sair da conta" viraram itens de menu de verdade em
  `includes/header.php` (desktop e mobile), junto com um novo item "Meus dados" (esse link só
  existia embutido no "Bem-vindo" antes) — aparecem em qualquer página do site quando o aluno
  está logado, não só na Minha Conta. "Bem-vindo, {nome}" agora leva pra `minha-conta.php` (era
  `meus-dados.php`).
- **Ação de entrega por pedido de produto**: pedido de produto físico pago agora mostra um botão
  de verdade — "📦 Acompanhar envio" (abre WhatsApp já com o número do pedido) enquanto o status
  configurado em `equipe/vendas.php` não for "entregue", ou "✅ Entregue" (não clicável) quando
  for. E-book pago continua com "📄 Baixar e-book". Esse status deixou de aparecer só como texto
  no selo — agora é uma ação, não só informação.
- **Mobile-first de verdade no card de pedido**: `.pedido-card` empilhava mal em telas estreitas
  (layout só com `flex-wrap`, sem regra dedicada pra mobile) e os botões de ação
  (`.pedido-card-actions .btn`) tinham `min-height: auto` com padding pequeno — abaixo do alvo de
  toque de 44px recomendado. Corrigido: card empilha em coluna até 560px (linha única, ações à
  direita, a partir daí), botões de ação voltam a ter 44px mínimo de altura.

## v2.7.1 — "Nova" — 2026-08-02

Pedido do cliente: a tela de pagamento da InfinitePay pedia nome completo, e-mail e WhatsApp de
novo, mesmo o cliente já tendo preenchido tudo isso no cadastro do site — informação redundante
que o comprador tinha que digitar duas vezes.

- **Dados do aluno agora vão pro checkout**: `processar_checkout()` (`includes/checkout_helper.php`)
  passa nome, e-mail e WhatsApp do aluno logado pro campo `customer` da API da InfinitePay (documentado
  em infinitepay.io/checkout-documentacao — `name`, `email`, `phone_number` no formato
  `+5511999887766`) — a tela de pagamento já chega com esses campos preenchidos. Nova função
  `whatsapp_e164()` (`includes/functions.php`) converte o WhatsApp salvo (só dígitos, sem código do
  país) pro formato que a InfinitePay espera.
- **Código do curso/produto na descrição do item**: a API da InfinitePay não documenta nenhum campo
  separado de código/SKU pro item (só `quantity`, `price`, `description`) — o código vai junto na
  descrição (ex.: "Furo Humanizado (cód. FH-2608-01)"), usando `codigo_turma` pra curso ou o `slug`
  pra produto (que não tem um código interno separado). A descrição usada no pedido/e-mail/painel
  continua sem o código, só o texto enviado pra InfinitePay ganhou o sufixo.
- Testado direto contra a API real da InfinitePay com nome/e-mail/telefone de exemplo (aceito,
  link gerado normalmente) e localmente com uma conta de teste real ponta a ponta (cadastro →
  checkout → link de pagamento real, dados do aluno de teste presentes no payload enviado).
