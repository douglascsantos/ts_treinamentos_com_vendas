# Configurar o carrossel do Instagram

O código já está pronto (`includes/instagram.php`) e funcionando com fotos estáticas reais do
site enquanto isso não for configurado. Para ele passar a puxar fotos, reels e (se possível)
stories automaticamente do Instagram `@tstreinamentos_`, você precisa gerar duas credenciais na
Meta e colocá-las em `secret.env`. É um processo do lado da Meta que só você (dono da conta) pode
fazer — eu não tenho como criar isso por você.

## Pré-requisitos

1. A conta do Instagram (`@tstreinamentos_`) precisa ser **Business** ou **Creator** (não pode
   ser conta pessoal). Se ainda for pessoal: Instagram → Configurações → Conta → Mudar para conta
   profissional.
2. Essa conta profissional precisa estar **conectada a uma Página do Facebook** (pode ser uma
   página nova, só para isso — Instagram → Configurações → Conta vinculada).

## Passo a passo

1. Acesse [developers.facebook.com](https://developers.facebook.com/) e crie um app (tipo
   "Business").
2. No app, adicione o produto **Instagram Graph API** (via "Instagram" > "API setup with
   Facebook Login" ou similar, dependendo da versão atual do painel da Meta).
3. Gere um **token de acesso de usuário** com as permissões:
   - `instagram_basic`
   - `pages_show_list`
   - `pages_read_engagement`
   - (opcional, para tentar Stories) `instagram_manage_stories` — essa exige revisão do app pela
     Meta para uso em produção; sem revisão, só funciona com contas de teste. Se não conseguir
     essa permissão, sem problema: o site já foi feito para funcionar só com fotos/reels do feed
     e ignorar Stories silenciosamente se a chamada falhar.
4. Troque esse token de curta duração por um **token de longa duração** (dura ~60 dias, precisa
   ser renovado periodicamente — a Meta tem um endpoint específico para isso,
   `oauth/access_token` com `grant_type=fb_exchange_token`).
5. Descubra o **Instagram User ID** da conta business (via
   `GET /me/accounts` e depois `GET /{page-id}?fields=instagram_business_account`).
6. Preencha no `secret.env` (na raiz do projeto, já existe o arquivo com os campos vazios):
   ```
   INSTAGRAM_ACCESS_TOKEN=seu_token_aqui
   INSTAGRAM_USER_ID=seu_instagram_user_id_aqui
   ```
7. Pronto — na próxima visita ao site, `includes/instagram.php` já vai tentar buscar as
   publicações reais. O resultado fica em cache por 1 hora (`data/instagram-cache.json`, criado
   automaticamente) para não bater na API a cada visita e não deixar o site lento.

## Sobre o token expirar

Tokens de longa duração da Meta duram cerca de 60 dias e precisam ser renovados manualmente (ou
via um processo automatizado, que ainda não construímos). Quando o token expirar, o site
**não quebra** — ele simplesmente volta a mostrar as fotos estáticas de fallback até você colocar
um token novo em `secret.env`. Vale colocar um lembrete recorrente para renovar.

## Sobre Stories especificamente

A API de Stories da Meta só retorna stories **ativos nas últimas 24h**, e normalmente exige
revisão do aplicativo pela Meta para funcionar fora de contas de teste. Se essa permissão não for
aprovada, o carrossel funciona normalmente só com fotos e reels do feed — os stories
simplesmente não aparecem, sem gerar erro no site.
