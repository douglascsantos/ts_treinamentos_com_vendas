-- Schema v5 — TS Treinamentos em Saúde
-- Banco: u885171151_GlKje (MySQL/MariaDB, Hostinger). Ordem respeita as dependências de FK.
-- O site ainda lê/grava em JSON (includes/storage.php) — migrar o código pra usar essas tabelas
-- é a próxima fase, não fazer parte deste schema. Ver ROADMAP.md item 3.

CREATE TABLE administradores (
  id                 VARCHAR(20)  PRIMARY KEY,
  nome               VARCHAR(150) NOT NULL,
  email              VARCHAR(150) NOT NULL UNIQUE,
  senha_hash         VARCHAR(255) NOT NULL,
  nivel              VARCHAR(20)  NOT NULL DEFAULT 'administrativo', -- 'administrativo' ou 'diretor'
  assinatura_imagem  VARCHAR(255) NULL,   -- upload próprio, usado no certificado quando nivel=diretor
  ativo              TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em          DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE instrutores (
  id                 VARCHAR(20)  PRIMARY KEY,
  nome_completo      VARCHAR(150) NOT NULL,
  formacao           VARCHAR(150) NOT NULL,
  cpf                VARCHAR(11)  NOT NULL UNIQUE,
  area_atuacao       VARCHAR(30)  NOT NULL,      -- mesma lista do aluno, troca "estudante" por "médico"
  numero_registro    VARCHAR(50)  NULL,          -- COREN/CRM/CRBM etc, texto livre
  email              VARCHAR(150) NOT NULL UNIQUE,
  whatsapp           VARCHAR(20)  NOT NULL,
  senha_hash         VARCHAR(255) NOT NULL,
  assinatura_imagem  VARCHAR(255) NULL,   -- upload próprio, usado no certificado
  ativo              TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em          DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE alunos (
  id               VARCHAR(20)  PRIMARY KEY,
  pronome          VARCHAR(10)  NULL,
  sexo             CHAR(1)      NULL,               -- 'M' ou 'F', deriva do pronome
  nome             VARCHAR(150) NOT NULL,
  email            VARCHAR(150) NOT NULL UNIQUE,
  whatsapp         VARCHAR(20)  NULL,
  cpf              VARCHAR(11)  NOT NULL UNIQUE,     -- obrigatório sempre, sem exceção
  data_nascimento  DATE         NULL,
  endereco         VARCHAR(255) NULL,                -- opcional, completado depois em meus-dados.php
  numero           VARCHAR(20)  NULL,
  cep              VARCHAR(8)   NULL,
  cidade           VARCHAR(100) NULL,
  estado           CHAR(2)      NULL,                -- UF
  foto             VARCHAR(255) NULL,                -- opcional, upload/câmera no cadastro via Google
  area_atuacao     VARCHAR(30)  NULL,
  senha_hash       VARCHAR(255) NULL,                -- NULL = conta só por login Google
  google_sub       VARCHAR(50)  NULL UNIQUE,
  criado_em        DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE turmas (
  slug            VARCHAR(150) PRIMARY KEY,
  codigo_turma    VARCHAR(30)  NOT NULL UNIQUE,     -- ex.: CALC-2607-01
  curso           VARCHAR(255) NOT NULL,
  datas           JSON         NOT NULL,
  horario         VARCHAR(10)  NOT NULL,
  local           VARCHAR(255) NOT NULL,
  carga_horaria   INT          NOT NULL DEFAULT 0,   -- horas, vai no certificado
  preco           DECIMAL(10,2) NOT NULL,
  vagas_total     INT          NOT NULL DEFAULT 0,   -- interno, NUNCA aparece pro cliente no site
  vagas_ocupadas  INT          NOT NULL DEFAULT 0,
  status          VARCHAR(20)  NOT NULL,             -- público: aberta/esgotada/encerrada
  instrutor_id    VARCHAR(20)  NULL,
  imagem          VARCHAR(255) NULL,
  descricao       TEXT         NULL,
  CONSTRAINT fk_turma_instrutor FOREIGN KEY (instrutor_id) REFERENCES instrutores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE produtos (
  slug           VARCHAR(150) PRIMARY KEY,
  nome           VARCHAR(255) NOT NULL,
  tipo           VARCHAR(20)  NOT NULL,
  preco          DECIMAL(10,2) NOT NULL,
  estoque        INT          NOT NULL DEFAULT 0,
  imagem         VARCHAR(255) NULL,
  arquivo_ebook  VARCHAR(255) NULL,   -- nome do PDF renomeado no servidor, só pra tipo='book'
  descricao      TEXT         NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pedidos (
  order_nsu           VARCHAR(20)  PRIMARY KEY,
  aluno_id            VARCHAR(20)  NULL,
  tipo                VARCHAR(10)  NOT NULL,
  slug                VARCHAR(150) NOT NULL,
  descricao           VARCHAR(255) NOT NULL,
  preco               DECIMAL(10,2) NOT NULL,
  cupom_codigo        VARCHAR(30)  NULL,
  desconto_valor      DECIMAL(10,2) NOT NULL DEFAULT 0,
  status              VARCHAR(20)  NOT NULL DEFAULT 'pendente',
  status_entrega      VARCHAR(15)  NOT NULL,   -- 'a_caminho'|'enviado'|'entregue' — curso e digital nascem 'entregue', físico nasce 'a_caminho'
  transaction_nsu     VARCHAR(100) NULL,
  invoice_slug        VARCHAR(150) NULL,
  capture_method      VARCHAR(20)  NULL,
  receipt_url         VARCHAR(255) NULL,
  aceite_contrato_em  DATETIME     NULL,
  aceite_contrato_ip  VARCHAR(45)  NULL,
  criado_em           DATETIME     NOT NULL,
  atualizado_em       DATETIME     NULL
  -- aluno_id SEM FK: alunos ainda é JSON, não tabela (ver includes/alunos.php) — mesma
  -- razão pela qual pedido_id não tem FK em cupons/boletos/certificados abaixo.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE certificados (
  id             VARCHAR(20)  PRIMARY KEY,
  numero_hash    VARCHAR(20)  NOT NULL UNIQUE,   -- código público (QR Code + verificação)
  arquivo_pdf    VARCHAR(255) NOT NULL,           -- curso-slug_AAAA-MM-DD_aluno-slug_hash.pdf
  pedido_id      VARCHAR(20)  NOT NULL,          -- order_nsu do pedidos.json — SEM FK (pedidos ainda é JSON, não tabela)
  aluno_id       VARCHAR(20)  NOT NULL,
  turma_slug     VARCHAR(150) NOT NULL,
  aluno_nome     VARCHAR(150) NOT NULL,   -- snapshot no momento da emissão
  curso_nome     VARCHAR(255) NOT NULL,   -- snapshot
  carga_horaria  INT          NOT NULL,   -- snapshot
  data_emissao   DATE         NOT NULL,   -- data impressa no certificado, escolhida pelo instrutor
  instrutor_id   VARCHAR(20)  NOT NULL,   -- assina como instrutor (imagem se tiver, senão texto)
  diretor_id     VARCHAR(20)  NOT NULL,   -- assina como diretor (mesma regra), quem finalizou a turma
  gerado_em      DATETIME     NOT NULL,
  -- aluno_id SEM FK: alunos ainda é JSON, não tabela.
  CONSTRAINT fk_cert_instrutor FOREIGN KEY (instrutor_id) REFERENCES instrutores(id),
  CONSTRAINT fk_cert_diretor FOREIGN KEY (diretor_id) REFERENCES administradores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cupons (
  id              VARCHAR(20)  PRIMARY KEY,
  codigo          VARCHAR(30)  NOT NULL UNIQUE,    -- vai na URL, ex.: ?cupom=CODIGO
  tipo_item       VARCHAR(10)  NOT NULL,            -- 'curso' ou 'produto'
  item_slug       VARCHAR(150) NOT NULL,
  tipo_desconto   VARCHAR(10)  NOT NULL,            -- 'percentual' ou 'fixo'
  valor_desconto  DECIMAL(10,2) NOT NULL,
  expira_em       DATETIME     NOT NULL,
  usado_em        DATETIME     NULL,                -- preenchido no uso -> nunca mais usável (uso único)
  pedido_id       VARCHAR(20)  NULL,                -- order_nsu do pedidos.json — SEM FK (pedidos ainda é JSON)
  criado_por      VARCHAR(20)  NOT NULL,
  criado_em       DATETIME     NOT NULL,
  CONSTRAINT fk_cupom_admin FOREIGN KEY (criado_por) REFERENCES administradores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE gastos (
  id          VARCHAR(20)  PRIMARY KEY,
  tipo        VARCHAR(20)  NOT NULL,   -- 'curso' | 'fixa_mensal' | 'variavel_unica' | 'patrimonio'
  categoria   VARCHAR(40)  NOT NULL,   -- materiais, apostilas, coffee_break, impressoes, professor,
                                        -- terceirizado, outros_curso | aluguel, condominio, luz,
                                        -- internet, alarme, equipamento_fixo, financiamento |
                                        -- variavel_outros | imovel, obra_benfeitoria, equipamento_compra
  turma_slug  VARCHAR(150) NULL,       -- só quando tipo='curso'
  descricao   VARCHAR(255) NULL,
  valor       DECIMAL(10,2) NOT NULL,
  data_gasto  DATE         NOT NULL,
  criado_por  VARCHAR(20)  NOT NULL,
  criado_em   DATETIME     NOT NULL,
  CONSTRAINT fk_gasto_admin FOREIGN KEY (criado_por) REFERENCES administradores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE boletos (
  id              VARCHAR(20)  PRIMARY KEY,
  aluno_id        VARCHAR(20)  NOT NULL,
  pedido_id       VARCHAR(20)  NOT NULL,          -- order_nsu do pedidos.json — SEM FK (pedidos ainda é JSON)
  numero_parcela  INT          NOT NULL,
  arquivo_pdf     VARCHAR(255) NOT NULL,
  vencimento      DATE         NOT NULL,
  status          VARCHAR(15)  NOT NULL DEFAULT 'pendente',  -- 'pendente' | 'pago' ('vencido' é calculado, não gravado)
  pago_em         DATETIME     NULL,
  criado_por      VARCHAR(20)  NOT NULL,
  criado_em       DATETIME     NOT NULL,
  -- aluno_id SEM FK: alunos ainda é JSON, não tabela.
  CONSTRAINT fk_boleto_admin FOREIGN KEY (criado_por) REFERENCES administradores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
