<?php
/**
 * Loader simples de secret.env (sem dependências externas — hospedagem
 * compartilhada comum não tem Composer). Formato: uma variável por linha,
 * `CHAVE=valor`, linhas em branco e começando com # são ignoradas.
 */

/** Lê e faz cache em memória do conteúdo de secret.env. */
function env_all(): array
{
    static $vars = null;
    if ($vars !== null) {
        return $vars;
    }

    $vars = [];
    $path = __DIR__ . '/../secret.env';
    if (!is_file($path)) {
        return $vars;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $vars[trim($key)] = trim($value);
    }

    return $vars;
}

/** Lê uma variável de secret.env, com valor padrão se ausente/vazia. */
function env(string $key, ?string $default = null): ?string
{
    $vars = env_all();
    $value = $vars[$key] ?? '';
    return $value !== '' ? $value : $default;
}
