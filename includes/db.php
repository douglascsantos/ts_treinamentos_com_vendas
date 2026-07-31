<?php
/**
 * Conexão com o MySQL (Hostinger) via PDO — primeiras tabelas de verdade do
 * site (instrutores/administradores), o resto continua em JSON por enquanto
 * (ver ROADMAP.md item 3 sobre a migração completa). Credenciais em
 * secret.env (DB_HOST/DB_NAME/DB_USER/DB_PASSWORD/DB_PORT — ver includes/env.php).
 */

/** Conexão PDO, uma por request (sem pool — hospedagem compartilhada comum). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = env('DB_HOST');
    $name = env('DB_NAME');
    $user = env('DB_USER');
    $pass = env('DB_PASSWORD');
    $port = env('DB_PORT', '3306');

    if (!$host || !$name || !$user) {
        // secret.env não encontrado ou incompleto no servidor — ver includes/env.php
        // (procura primeiro um nível acima do repositório, fora do public_html).
        throw new RuntimeException('config_ausente: DB_HOST/DB_NAME/DB_USER não encontrados em secret.env');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        throw new RuntimeException('falha_conexao: ' . $e->getMessage());
    }

    return $pdo;
}

/** Gera um id curto no mesmo padrão dos outros registros (PREFIXO + data + hex aleatório). */
function gerar_id(string $prefixo): string
{
    return $prefixo . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}
