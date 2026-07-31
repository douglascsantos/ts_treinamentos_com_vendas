# Changelog — TS Treinamentos em Saúde (site novo)

Versionamento: `PROTOTIPO.MACRO.MICRO` (ver comentário em `includes/version.php`).
A versão exibida no rodapé do site vem sempre desse arquivo — atualize-o a cada entrada nova aqui.

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
