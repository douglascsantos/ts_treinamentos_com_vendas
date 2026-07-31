<?php
/**
 * Backup sob demanda, só pro diretor (ver equipe/perfil.php). Gerado na hora
 * em um arquivo temporário fora da pasta pública e apagado do disco assim
 * que termina de enviar — nunca fica salvo dentro do site, pra não virar um
 * arquivo baixável por qualquer um que adivinhe o link.
 */

/**
 * Dump do banco em SQL puro (sem depender de `mysqldump` via shell, que
 * costuma vir desabilitado em hospedagem compartilhada) + zipa. Retorna o
 * caminho do .zip temporário.
 */
/** A extensão `zip` costuma vir habilitada em hospedagem compartilhada, mas nem sempre — checa antes de tentar, pra dar um erro claro em vez de um 500 genérico. */
function backups_checar_extensao_zip(): void
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('A extensão PHP "zip" não está disponível neste servidor — não é possível gerar o .zip.');
    }
}

function gerar_backup_banco_zip(): string
{
    backups_checar_extensao_zip();
    $pdo = db();
    $tabelas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    $sql = "-- Backup do banco TS Treinamentos — gerado em " . date('Y-m-d H:i:s') . "\n\n";
    foreach ($tabelas as $tabela) {
        $create = $pdo->query("SHOW CREATE TABLE `{$tabela}`")->fetch();
        $sql .= "DROP TABLE IF EXISTS `{$tabela}`;\n" . $create['Create Table'] . ";\n\n";

        foreach ($pdo->query("SELECT * FROM `{$tabela}`")->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $colunas = implode(',', array_map(fn ($c) => "`{$c}`", array_keys($linha)));
            $valores = implode(',', array_map(
                fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                array_values($linha)
            ));
            $sql .= "INSERT INTO `{$tabela}` ({$colunas}) VALUES ({$valores});\n";
        }
        $sql .= "\n";
    }

    $tmpSql = tempnam(sys_get_temp_dir(), 'ts_db_');
    file_put_contents($tmpSql, $sql);

    try {
        $caminhoZip = sys_get_temp_dir() . '/backup-banco-' . date('Y-m-d_His') . '.zip';
        $zip = new ZipArchive();
        $zip->open($caminhoZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($tmpSql, 'backup-banco-' . date('Y-m-d') . '.sql');
        $zip->close();
    } finally {
        @unlink($tmpSql);
    }
    return $caminhoZip;
}

/**
 * Zipa o repositório inteiro (código + dados versionados do site). Exclui
 * `.git` (histórico, não é "o site") e o próprio diretório de backups
 * temporários. Uploads sensíveis (boletos, certificados, e-books, dados de
 * aluno) já vivem fora do repositório de propósito (ver includes/storage.php)
 * e por isso não entram aqui — o objetivo deste botão é o código/conteúdo do
 * site, não dado pessoal de cliente.
 */
function gerar_backup_site_zip(): string
{
    backups_checar_extensao_zip();
    $raiz = dirname(__DIR__);
    $excluir = ['.git', 'node_modules'];

    $caminhoZip = sys_get_temp_dir() . '/backup-site-' . date('Y-m-d_His') . '.zip';
    $zip = new ZipArchive();
    $zip->open($caminhoZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $arquivo) {
        if (!$arquivo->isFile()) {
            continue;
        }
        $relativo = str_replace('\\', '/', substr($arquivo->getPathname(), strlen($raiz) + 1));
        if (in_array(explode('/', $relativo)[0], $excluir, true)) {
            continue;
        }
        $zip->addFile($arquivo->getPathname(), $relativo);
    }
    $zip->close();

    return $caminhoZip;
}

/** Envia um .zip gerado como download e apaga o arquivo temporário logo depois. */
function enviar_zip_e_apagar(string $caminhoZip, string $nomeDownload): void
{
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $nomeDownload . '"');
    header('Content-Length: ' . filesize($caminhoZip));
    readfile($caminhoZip);
    @unlink($caminhoZip);
    exit;
}
