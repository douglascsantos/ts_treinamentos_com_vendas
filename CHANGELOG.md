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
