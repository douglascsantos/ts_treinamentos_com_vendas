<?php
/**
 * Monitoramento automático de produção — banco de dados, API da InfinitePay,
 * varredura de segurança (arquivo alterado/novo fora do deploy normal, sinais
 * comuns de backdoor/webshell) e backup rotativo (7 arquivos, um por dia da
 * semana, sobrescrito na semana seguinte).
 *
 * Só roda via linha de comando (cron/SSH) — nunca por HTTP, mesmo que alguém
 * adivinhe a URL (mesmo padrão já usado em includes/functions.php pro log de
 * acesso, ver acesso_registrar_e_checar_bloqueio()).
 *
 * Uso: php tools/monitoramento_diario.php [--modo=completo|rapido]
 *   completo (padrão, cron diário): roda todas as checagens + gera backup.
 *   rapido (chamado pelo deploy-check logo após um deploy): roda as
 *   checagens, sem gerar backup (backup é coisa de uma vez por dia, não a
 *   cada deploy).
 *
 * Isso é um "tripwire" best-effort, não uma auditoria de segurança completa —
 * sinaliza o que for fácil de detectar automaticamente (arquivo alterado fora
 * do fluxo normal de deploy, padrões conhecidos de backdoor/webshell), não
 * substitui revisão manual periódica.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/storage.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/administradores.php';
require __DIR__ . '/../includes/pedidos.php';
require __DIR__ . '/../includes/infinitepay.php';
require __DIR__ . '/../includes/backups.php';
require __DIR__ . '/../includes/email.php';

$modo = 'completo';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--modo=')) {
        $modo = substr($arg, 7);
    }
}

/** [nivel ('baixo'|'medio'|'alto'), mensagem] por achado. */
$achados = [];

function monitoramento_log(string $linha): void
{
    $dir = dirname(storage_path(PEDIDOS_FILE));
    @file_put_contents(
        $dir . '/monitoramento.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $linha . "\n",
        FILE_APPEND | LOCK_EX
    );
}

// 1) Banco de dados ----------------------------------------------------
try {
    db()->query('SELECT 1');
    monitoramento_log('Banco de dados OK.');
} catch (Throwable $e) {
    $achados[] = ['alto', 'Banco de dados inacessível: ' . $e->getMessage()];
}

// 2) InfinitePay ---------------------------------------------------------
try {
    $config = require __DIR__ . '/../includes/config.php';
    // Não cria pedido/link nenhum de verdade — só confirma que a API responde
    // (mesmo que a resposta seja "não encontrado", já prova que o servidor
    // está de pé e aceitando conexão).
    [$status] = infinitepay_post('/payment_check', [
        'handle' => $config['infinitepay_handle'],
        'order_nsu' => 'MONITORAMENTO-' . date('YmdHis'),
        'transaction_nsu' => 'monitoramento',
        'slug' => 'monitoramento',
    ]);
    if ($status === 0) {
        $achados[] = ['medio', 'API da InfinitePay não respondeu (falha de rede/timeout).'];
    } else {
        monitoramento_log("InfinitePay OK (status HTTP {$status}).");
    }
} catch (Throwable $e) {
    $achados[] = ['medio', 'Erro ao testar a API da InfinitePay: ' . $e->getMessage()];
}

// 3) Varredura de segurança ----------------------------------------------
$raizSite = dirname(__DIR__);

// 3a) Integridade do deploy — qualquer arquivo rastreado pelo Git que esteja
// MODIFICADO no servidor (diferente do que foi commitado) é um sinal forte:
// nada deveria escrever em arquivo de código depois do deploy. Arquivo NOVO
// (não rastreado) também é sinalizado, exceto os poucos casos já esperados
// (produtos/{slug}.php gerado pelo painel administrativo, ver v2.6.0/v2.7.0
// no CHANGELOG — e a pasta work/, artefato antigo da própria Hostinger).
if (function_exists('shell_exec')) {
    $gitStatus = @shell_exec('cd ' . escapeshellarg($raizSite) . ' && git status --porcelain 2>&1');
    if ($gitStatus !== null) {
        foreach (explode("\n", trim($gitStatus)) as $linha) {
            $linha = trim($linha);
            if ($linha === '') {
                continue;
            }
            $caminho = trim(substr($linha, 2));
            if (str_starts_with($caminho, 'work/') || str_starts_with($caminho, 'produtos/')) {
                continue; // conhecido/esperado, ver comentário acima
            }
            $modificado = $linha[0] !== '?' && $linha[0] !== ' ';
            $achados[] = [
                $modificado ? 'alto' : 'medio',
                ($modificado ? 'Arquivo rastreado pelo Git foi modificado direto no servidor (fora do deploy): ' : 'Arquivo novo, não rastreado, encontrado no servidor: ') . $caminho,
            ];
        }
    } else {
        monitoramento_log('git status não disponível (shell_exec bloqueado ou git ausente) — pulei a checagem de integridade do deploy.');
    }
}

// 3b) Padrões conhecidos de backdoor/webshell — lista curta e específica de
// propósito, pra não gerar alarme falso em cima de código legítimo do site
// (que não usa eval/assert/create_function em lugar nenhum).
$padroesSuspeitos = [
    '/eval\s*\(\s*(base64_decode|gzinflate|gzuncompress|str_rot13)\s*\(/i' => 'eval() de conteúdo decodificado/comprimido em tempo real (payload ofuscado)',
    '/\$_(GET|POST|REQUEST|COOKIE)\s*\[[^\]]+\]\s*\(/i' => 'chamada de função cujo nome vem direto de entrada do usuário',
    '/assert\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/i' => 'assert() com entrada do usuário (técnica antiga de backdoor)',
    '/create_function\s*\(/i' => 'create_function() (obsoleta, comum em webshell antigo)',
    '/preg_replace\s*\([^)]*\/e[\'"]/i' => 'preg_replace() com modificador /e (RCE antigo do PHP)',
    '/(system|shell_exec|passthru|exec)\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/i' => 'execução de comando de shell com entrada do usuário direto',
];
$arquivosVarridos = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raizSite, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $arquivo) {
    if (!$arquivo->isFile() || $arquivo->getExtension() !== 'php') {
        continue;
    }
    $relativo = str_replace('\\', '/', substr($arquivo->getPathname(), strlen($raizSite) + 1));
    if (str_starts_with($relativo, '.git/')) {
        continue;
    }
    if (realpath($arquivo->getPathname()) === realpath(__FILE__)) {
        continue; // este script contém os próprios padrões de busca como texto — nunca varre a si mesmo (por conteúdo, não por nome/caminho fixo, pra não falhar se for copiado/renomeado)
    }
    $arquivosVarridos++;
    $conteudo = @file_get_contents($arquivo->getPathname());
    if ($conteudo === false) {
        continue;
    }
    foreach ($padroesSuspeitos as $padrao => $descricao) {
        if (preg_match($padrao, $conteudo)) {
            $achados[] = ['alto', "Padrão suspeito ({$descricao}) encontrado em: {$relativo}"];
        }
    }
}
monitoramento_log("Varredura de segurança concluída ({$arquivosVarridos} arquivos .php verificados).");

// 4) Backup rotativo (só no modo completo — uma vez por dia) -------------
if ($modo === 'completo') {
    $diasSemana = ['segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'];
    $diaAtual = $diasSemana[((int) date('N')) - 1];
    $dirBackups = dirname(storage_path(PEDIDOS_FILE)) . '/backups';
    if (!is_dir($dirBackups)) {
        @mkdir($dirBackups, 0755, true);
    }

    try {
        $zipSite = gerar_backup_site_zip();
        @rename($zipSite, $dirBackups . "/backup-site-{$diaAtual}.zip");
        monitoramento_log("Backup do site gerado: backup-site-{$diaAtual}.zip");
    } catch (Throwable $e) {
        $achados[] = ['alto', 'Falha ao gerar backup do site: ' . $e->getMessage()];
    }

    try {
        $zipBanco = gerar_backup_banco_zip();
        @rename($zipBanco, $dirBackups . "/backup-banco-{$diaAtual}.zip");
        monitoramento_log("Backup do banco gerado: backup-banco-{$diaAtual}.zip");
    } catch (Throwable $e) {
        $achados[] = ['alto', 'Falha ao gerar backup do banco de dados: ' . $e->getMessage()];
    }
}

// 5) Reporta problemas (só manda e-mail se achou algo — sem spam diário) --
if (!$achados) {
    monitoramento_log('Nenhum problema encontrado.');
    exit(0);
}

$ordemNivel = ['baixo' => 0, 'medio' => 1, 'alto' => 2];
$nivelMaximo = 'baixo';
foreach ($achados as [$nivel, ]) {
    if ($ordemNivel[$nivel] > $ordemNivel[$nivelMaximo]) {
        $nivelMaximo = $nivel;
    }
}

$corpoHtml = email_moldura(
    '🔎 Relatório de monitoramento',
    '<p>Gerado em ' . e(date('d/m/Y H:i:s')) . " (modo: {$modo})</p>"
    . '<ul>' . implode('', array_map(
        fn ($a) => '<li><strong>' . e(mb_strtoupper($a[0])) . ':</strong> ' . e($a[1]) . '</li>',
        $achados
    )) . '</ul>'
);

$assunto = "ALERTA {$nivelMaximo} de segurança — TS Treinamentos";

enviar_email('douglas.santos.mat@gmail.com', $assunto, $corpoHtml);
monitoramento_log("E-mail de alerta enviado pra douglas.santos.mat@gmail.com (nível {$nivelMaximo}).");

// Alerta também pros diretores cadastrados no site — versão mais curta, sem
// jargão técnico de arquivo/linha, só avisando que há algo pra conferir.
try {
    $diretores = array_filter(
        listar_administradores(),
        fn ($a) => ($a['nivel'] ?? '') === 'diretor' && ($a['ativo'] ?? true) !== false
    );
    $corpoDiretor = email_moldura(
        '⚠️ Alerta de segurança do site',
        '<p>O monitoramento automático encontrou um problema de nível <strong>' . e($nivelMaximo) . '</strong> '
        . 'no site TS Treinamentos. O responsável técnico já foi avisado e está verificando.</p>'
    );
    foreach ($diretores as $diretor) {
        if (!empty($diretor['email'])) {
            enviar_email($diretor['email'], $assunto, $corpoDiretor);
        }
    }
    monitoramento_log('Alerta enviado pra ' . count($diretores) . ' diretor(es) cadastrado(s).');
} catch (Throwable $e) {
    monitoramento_log('Falha ao avisar diretores: ' . $e->getMessage());
}

exit($nivelMaximo === 'alto' ? 2 : 1);
