<?php
/**
 * Resolução de caminho seguro para arquivos de dados com informação real de
 * clientes/alunos (pedidos, contas, etc.) — protótipo sem banco de dados.
 *
 * IMPORTANTE: o deploy deste site é via `git push` + Hostinger clonando o
 * repositório direto na pasta pública. Se esses arquivos vivessem DENTRO do
 * repositório (mesmo gitignored), um deploy que faça limpeza de arquivos não
 * versionados (`git clean`) poderia apagar dados reais de clientes/alunos.
 * Por isso tentamos gravar num diretório FORA da pasta do site (um nível
 * acima da raiz do repositório) — e só usamos `data/` (dentro do repo) como
 * fallback para desenvolvimento local.
 *
 * Antes de depender disso em produção de verdade: confirme com a Hostinger se
 * o deploy Git faz `git clean`/reset destrutivo, e considere migrar para
 * MySQL assim que possível (ver ROADMAP.md).
 */

/** Resolve o caminho de um arquivo de dados sensível, com cache em memória por request. */
function storage_path(string $filename): string
{
    static $cache = [];
    if (isset($cache[$filename])) {
        return $cache[$filename];
    }

    $outside = dirname(__DIR__, 2) . '/ts_site_data';
    if (is_dir($outside) || @mkdir($outside, 0755, true)) {
        if (is_writable($outside)) {
            return $cache[$filename] = $outside . '/' . $filename;
        }
    }

    $fallbackDir = __DIR__ . '/../data';
    if (!is_dir($fallbackDir)) {
        @mkdir($fallbackDir, 0755, true);
    }
    return $cache[$filename] = $fallbackDir . '/' . $filename;
}

/** Lê um JSON de dados (lock compartilhado). Retorna [] se não existir/inválido. */
function storage_read_json(string $filename): array
{
    $path = storage_path($filename);
    if (!is_file($path)) {
        return [];
    }
    $fh = fopen($path, 'r');
    if (!$fh) {
        return [];
    }
    flock($fh, LOCK_SH);
    $raw = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);

    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : [];
}

/** Grava um JSON de dados por completo (lock exclusivo). */
function storage_write_json(string $filename, array $data): void
{
    $path = storage_path($filename);
    $fh = fopen($path, 'c+');
    if (!$fh) {
        return;
    }
    flock($fh, LOCK_EX);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}
