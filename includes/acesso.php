<?php
/**
 * Log de acesso + lista de bloqueio de IP, em nível de aplicação (hospedagem
 * compartilhada, sem acesso a firewall/servidor). Toda requisição do site
 * passa por aqui (chamado 1x no topo de includes/functions.php) — se o IP
 * estiver bloqueado, corta a resposta ali mesmo, antes de qualquer outra
 * lógica rodar. Guardado fora do repositório (mesmo padrão de storage.php)
 * porque cresce a cada acesso e não é conteúdo do site.
 */
require_once __DIR__ . '/storage.php';

/** IP real do visitante — tenta os headers comuns de proxy reverso antes do REMOTE_ADDR direto. */
function acesso_ip_cliente(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $campo) {
        if (!empty($_SERVER[$campo])) {
            $valor = trim(explode(',', $_SERVER[$campo])[0]);
            if (filter_var($valor, FILTER_VALIDATE_IP)) {
                return $valor;
            }
        }
    }
    return '0.0.0.0';
}

function ips_bloqueados(): array
{
    return storage_read_json('ips-bloqueados.json');
}

function ip_esta_bloqueado(string $ip): bool
{
    return in_array($ip, ips_bloqueados(), true);
}

function bloquear_ip(string $ip): void
{
    $lista = ips_bloqueados();
    if (!in_array($ip, $lista, true)) {
        $lista[] = $ip;
        storage_write_json('ips-bloqueados.json', $lista);
    }
}

function liberar_ip(string $ip): void
{
    storage_write_json('ips-bloqueados.json', array_values(array_diff(ips_bloqueados(), [$ip])));
}

/** Registra a requisição atual (1 linha JSON por acesso) e corta na hora se o IP estiver bloqueado. */
function acesso_registrar_e_checar_bloqueio(): void
{
    $ip = acesso_ip_cliente();

    if (ip_esta_bloqueado($ip)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Acesso bloqueado.';
        exit;
    }

    $linha = json_encode([
        'em'     => date('Y-m-d H:i:s'),
        'ip'     => $ip,
        'metodo' => $_SERVER['REQUEST_METHOD'] ?? '',
        'path'   => mb_substr($_SERVER['REQUEST_URI'] ?? '', 0, 300),
        'ua'     => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
    ], JSON_UNESCAPED_UNICODE) . "\n";

    $fh = @fopen(storage_path('acesso-log.jsonl'), 'a');
    if ($fh) {
        flock($fh, LOCK_EX);
        fwrite($fh, $linha);
        flock($fh, LOCK_UN);
        fclose($fh);
    }
    acesso_rotacionar_se_necessario();
}

/** Evita o log crescer pra sempre — mantém só os últimos ~5000 acessos. Só verifica acima de 3MB pra não ler o arquivo inteiro toda hora. */
function acesso_rotacionar_se_necessario(): void
{
    $path = storage_path('acesso-log.jsonl');
    if (!is_file($path) || filesize($path) < 3 * 1024 * 1024) {
        return;
    }
    $linhas = file($path, FILE_IGNORE_NEW_LINES);
    if ($linhas === false || count($linhas) <= 5000) {
        return;
    }
    file_put_contents($path, implode("\n", array_slice($linhas, -5000)) . "\n");
}

/** Últimas N linhas do log, mais recente primeiro — pra tela de logs do painel. */
function acesso_log_recentes(int $limite = 200): array
{
    $path = storage_path('acesso-log.jsonl');
    if (!is_file($path)) {
        return [];
    }
    $linhas = array_slice(file($path, FILE_IGNORE_NEW_LINES) ?: [], -$limite);
    $registros = array_map(fn ($l) => json_decode($l, true), array_reverse($linhas));
    return array_values(array_filter($registros, 'is_array'));
}

/** IPs distintos vistos recentemente, com contagem de acessos (mais frequente primeiro) — pra tela de bloqueio. */
function acesso_ips_recentes(int $limiteLinhas = 3000): array
{
    $contagem = [];
    foreach (acesso_log_recentes($limiteLinhas) as $registro) {
        $ip = $registro['ip'] ?? '?';
        $contagem[$ip] = ($contagem[$ip] ?? 0) + 1;
    }
    arsort($contagem);
    return $contagem;
}
