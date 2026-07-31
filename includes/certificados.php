<?php
/**
 * Certificados/diplomas (tabela `certificados`, MySQL). Emitido quando o
 * administrativo conclui uma turma (equipe/turmas.php, ação "concluir_turma"),
 * depois que o instrutor já finalizou presença/nota (equipe/minha-turma.php).
 * Cada emissão grava um snapshot (nome do aluno, nome do curso, carga
 * horária) que nunca muda depois, mesmo que o cadastro do curso/aluno seja
 * editado — é o registro histórico do que foi realmente cursado.
 *
 * O diploma em si é renderizado como página HTML imprimível (certificado.php,
 * mesmo princípio de meu-contrato.php: sem depender de biblioteca de PDF no
 * servidor — o visitante usa "Imprimir" do navegador pra salvar como PDF).
 * A geração de um arquivo .pdf físico guardado no servidor (ver
 * certificados_dir()/certificado_nome_arquivo() abaixo) fica pra uma fase
 * futura — por enquanto essas funções só documentam a convenção de nome que
 * o arquivo físico usará quando essa parte for construída.
 *
 * Coluna `diretor_id` no banco carrega o "administrativo responsável" da
 * turma (pode ser diretor OU administrativo — a FK só exige que seja alguém
 * da tabela `administradores`; o nome da coluna é herdado do desenho
 * original do schema, ver db/schema.sql).
 */
require_once __DIR__ . '/functions.php';

/** $dados: pedido_id, aluno_id, turma_slug, aluno_nome, curso_nome, carga_horaria, data_emissao (Y-m-d), instrutor_id, administrativo_id. */
function criar_certificado(array $dados): array
{
    $id = gerar_id('CE');
    $codigo = gerar_certificado_hash();
    $nomeArquivo = certificado_nome_arquivo($dados['curso_nome'], $dados['data_emissao'], $dados['aluno_nome'], $codigo);

    $stmt = db()->prepare(
        'INSERT INTO certificados (id, numero_hash, arquivo_pdf, pedido_id, aluno_id, turma_slug, aluno_nome, curso_nome, carga_horaria, data_emissao, instrutor_id, diretor_id, gerado_em)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $id, $codigo, $nomeArquivo, $dados['pedido_id'] ?? '', $dados['aluno_id'], $dados['turma_slug'],
        $dados['aluno_nome'], $dados['curso_nome'], (int) $dados['carga_horaria'], $dados['data_emissao'],
        $dados['instrutor_id'], $dados['administrativo_id'], date('Y-m-d H:i:s'),
    ]);
    return find_certificado_by_id($id);
}

function find_certificado_by_id(string $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM certificados WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Busca pública por código de verificação (numero_hash) — usada em certificado.php, sem exigir login. */
function find_certificado_by_codigo(string $codigo): ?array
{
    $stmt = db()->prepare('SELECT * FROM certificados WHERE numero_hash = ? LIMIT 1');
    $stmt->execute([mb_strtoupper(trim($codigo))]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function listar_certificados_por_turma(string $turmaSlug): array
{
    $stmt = db()->prepare('SELECT * FROM certificados WHERE turma_slug = ? ORDER BY aluno_nome ASC');
    $stmt->execute([$turmaSlug]);
    return $stmt->fetchAll();
}

/** Certificados de um aluno — pra ele ver/imprimir os próprios diplomas (ver minha-conta.php). */
function listar_certificados_por_aluno(string $alunoId): array
{
    $stmt = db()->prepare('SELECT * FROM certificados WHERE aluno_id = ? ORDER BY gerado_em DESC');
    $stmt->execute([$alunoId]);
    return $stmt->fetchAll();
}

/** Resolve o diretório dos certificados, fora do repo em produção (mesmo padrão de ts_site_data/). */
function certificados_dir(): string
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }

    $outside = dirname(__DIR__, 2) . '/ts_site_data/certificados';
    if (is_dir($outside) || @mkdir($outside, 0755, true)) {
        if (is_writable($outside)) {
            return $dir = $outside;
        }
    }

    $fallback = __DIR__ . '/../data/certificados';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0755, true);
    }
    return $dir = $fallback;
}

/** Caminho completo de um certificado a partir do nome do arquivo. */
function certificado_path(string $nomeArquivo): string
{
    return certificados_dir() . '/' . $nomeArquivo;
}

/** Versão "slug" de um texto — pra usar em nome de arquivo. Ver slugify() em includes/functions.php. */
function certificado_slug(string $texto): string
{
    return slugify($texto);
}

/**
 * Nome do arquivo do certificado: curso_data_aluno_hash.pdf
 * $dataEmissao no formato Y-m-d. $hash é o número único de verificação
 * (numero_hash) — o mesmo que vai no QR Code da página de validação pública.
 */
function certificado_nome_arquivo(string $cursoNome, string $dataEmissao, string $alunoNome, string $hash): string
{
    $curso = certificado_slug($cursoNome);
    $aluno = certificado_slug($alunoNome);
    return "{$curso}_{$dataEmissao}_{$aluno}_{$hash}.pdf";
}

/** Gera um código único de verificação (vai no QR Code e na URL pública de validação). */
function gerar_certificado_hash(): string
{
    return strtoupper(bin2hex(random_bytes(8)));
}
