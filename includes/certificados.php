<?php
/**
 * Armazenamento dos PDFs de certificado — mesmo princípio de segurança de
 * includes/storage.php (dado sensível de aluno, nunca dentro do repositório
 * nem servível direto por URL). Nome do arquivo:
 * curso-slug_AAAA-MM-DD_nome-do-aluno-slug_hash.pdf — ver certificado_nome_arquivo().
 *
 * A geração do PDF em si (com QR Code de verificação) ainda não existe — isso
 * é só a pasta e a convenção de nome, prontas pra quando essa parte for
 * construída (ver ROADMAP.md, área do instrutor).
 */
require_once __DIR__ . '/functions.php';

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
