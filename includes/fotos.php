<?php
/**
 * Armazenamento da foto do aluno — mesmo princípio de segurança de
 * includes/storage.php e includes/certificados.php (dado sensível, nunca
 * dentro do repositório nem servível direto por URL pública ainda; falta
 * construir um endpoint protegido pra exibir, ver ROADMAP.md).
 */

const FOTO_MIME_EXTENSAO = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

const FOTO_TAMANHO_MAXIMO = 5 * 1024 * 1024; // 5MB

/** Resolve o diretório das fotos, fora do repo em produção (mesmo padrão de ts_site_data/). */
function fotos_alunos_dir(): string
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }

    $outside = dirname(__DIR__, 2) . '/ts_site_data/fotos_alunos';
    if (is_dir($outside) || @mkdir($outside, 0755, true)) {
        if (is_writable($outside)) {
            return $dir = $outside;
        }
    }

    $fallback = __DIR__ . '/../data/fotos_alunos';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0755, true);
    }
    return $dir = $fallback;
}

/**
 * Valida e salva a foto enviada (via <input type="file">, tanto arquivo comum
 * quanto o blob capturado pela câmera/webcam no navegador — pro PHP os dois
 * chegam do mesmo jeito em $_FILES). Retorna o nome do arquivo salvo
 * ("{alunoId}.jpg") ou null se não veio arquivo ou é inválido — nunca lança
 * exceção, foto é sempre opcional.
 */
function salvar_foto_aluno(string $alunoId, array $arquivo): ?string
{
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (($arquivo['size'] ?? 0) <= 0 || $arquivo['size'] > FOTO_TAMANHO_MAXIMO) {
        return null;
    }

    $info = @getimagesize($arquivo['tmp_name']);
    if (!$info || !isset(FOTO_MIME_EXTENSAO[$info['mime']])) {
        return null;
    }

    $nomeArquivo = $alunoId . '.' . FOTO_MIME_EXTENSAO[$info['mime']];
    $destino = fotos_alunos_dir() . '/' . $nomeArquivo;

    if (!is_uploaded_file($arquivo['tmp_name']) || !move_uploaded_file($arquivo['tmp_name'], $destino)) {
        return null;
    }

    return $nomeArquivo;
}
