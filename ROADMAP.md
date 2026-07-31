# Roadmap — TS Treinamentos em Saúde

Itens combinados com o cliente para fases futuras. Nada aqui está implementado ainda — é o
registro do que falta construir, para não se perder entre uma conversa e outra.

## 1. E-commerce (checkout de verdade)

Hoje a "compra" é só um botão que abre o WhatsApp com mensagem pré-preenchida (em
`includes/curso-page.php` e na Agenda da home). Falta:
- Carrinho/checkout de verdade, com pagamento online (PIX/cartão).
- Suporte à modalidade "matrícula 15% + saldo no 1º dia de aula" (já existia no site antigo em
  WordPress — ver `old_site/funcionalidades_sistema.txt` para a regra completa).
- Cursos agendáveis (aluno escolhe data/horário no checkout).
- Cupons de desconto.
- Emissão de recibo/nota.

## 2. Área do Aluno

Login para o aluno acessar, depois da compra:
- O que comprou (histórico de pedidos).
- Parte financeira (o que já pagou, o que falta pagar — relevante por causa da matrícula 15%).
- Imprimir o contrato (modelo já existe em `old_site/contrato-modelo.pdf`, o site antigo já tinha
  aceite eletrônico com registro de IP/data — ver `old_site/funcionalidades_sistema.txt`).
- Histórico escolar e presenças.
- Imprimir certificados (o site antigo já tinha emissão com QR Code de verificação pública —
  mesmo arquivo de referência acima documenta como funcionava).
- Assistir aulas disponíveis online (conteúdo em vídeo).

## 0. Agente de produtos (curto prazo — próximo item a construir)

O carrossel "Produtos TS Treinamentos" (cards, kits de treinamento e books) já está pronto no
site (`includes/produtos.php`, `includes/produto-page.php`, seção em `index.php`, CSS em
`assets/css/style.css`), lendo de `data/produtos.json` — mas esse arquivo está vazio, então a
seção fica oculta até ter conteúdo. Falta:
- Definir junto com o cliente o formato de conteúdo em `produtos_ts_site/` (pasta local, fora do
  git, já criada) — provavelmente algo parecido com o padrão de `agenda/` (nome do arquivo
  codificando produto/tipo/preço/estoque, ou pasta + `dados.txt`).
- Construir o agente `produtos-sync` (espelhando `.claude/agents/agenda-sync.md` e
  `tools/sync_agenda.py`) que processa `produtos_ts_site/`, gera as imagens, atualiza
  `data/produtos.json` e cria a página de venda em `produtos/{slug}.php`.
- Depois que houver produtos reais publicados, adicionar "Produtos" ao menu principal
  (`includes/header.php`) apontando para `#produtos` — não foi adicionado ainda de propósito,
  para não deixar um link de menu apontando pra uma seção vazia/inexistente.

## 3. CRM / área administrativa

Painel interno para a equipe (não para o aluno), com controle de acesso por função — cada
funcionário só vê/mexe no que precisa:
- Acompanhamento pedagógico dos alunos.
- Parte financeira (pagamentos, pendências, matrícula 15%+saldo).
- Upload de vídeos (para a Área do Aluno assistir aulas).
- Controle geral do site: cursos, turmas, conteúdo da agenda (hoje isso é manual via pasta
  `agenda/` + agente `agenda-sync` — no CRM isso vira uma tela de verdade).
- Permissões por usuário/função (multi-usuário — a equipe toda vai usar, não só o dono).

## Como isso se conecta ao que já existe

Os itens 1 e 2 dependem um do outro (não dá pra ter Área do Aluno completa sem checkout de
verdade gerando pedidos reais). O item 3 (CRM) é o que dá vida ao conteúdo que aparece nos itens
1 e 2 — por isso banco de dados (MySQL) provavelmente entra nessa fase, substituindo o
`data/turmas.json` atual por tabelas de verdade (`cursos`, `turmas`, `pedidos`, `alunos`,
`certificados` — ver esboço de schema em `old_site/funcionalidades_sistema.txt`, seção final).

Quando entrarmos nessa fase, o pedido é para retomar esta conversa a partir daqui.
