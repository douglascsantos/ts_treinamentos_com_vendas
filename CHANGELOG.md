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
