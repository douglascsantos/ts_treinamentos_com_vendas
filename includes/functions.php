<?php
require_once __DIR__ . '/acesso.php';
if (PHP_SAPI !== 'cli') {
    acesso_registrar_e_checar_bloqueio();
}

/** Escapa texto para saída segura em HTML. */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/** Monta um link wa.me com mensagem pré-preenchida (URL-encoded). */
function wa_link(string $whatsapp, string $message = ''): string
{
    $url = 'https://wa.me/' . preg_replace('/\D/', '', $whatsapp);
    if ($message !== '') {
        $url .= '?text=' . rawurlencode($message);
    }
    return $url;
}

/** URL absoluta (sempre https — obrigatório pra webhook_url da InfinitePay). */
function site_base_url(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'tstreinamento.com';
    return 'https://' . $host;
}

/** Preço formatado em R$ pt-BR. Compartilhado entre turmas e produtos. */
function format_price(float $preco): string
{
    if ($preco <= 0) {
        return 'Gratuito';
    }
    return 'R$ ' . number_format($preco, 2, ',', '.');
}

/** Primeiro nome, pro "Bem-vindo, Fulano" no cabeçalho. */
function primeiro_nome(string $nomeCompleto): string
{
    $partes = explode(' ', trim($nomeCompleto));
    return $partes[0] ?? '';
}

function normalizar_cpf(string $cpf): string
{
    return preg_replace('/\D/', '', $cpf) ?? '';
}

/**
 * Validação do dígito verificador do CPF (mesmo algoritmo já usado no site
 * antigo). Fica em functions.php (não em includes/alunos.php) porque também
 * é usada por equipe/instrutores.php — CPF é conceito genérico, não
 * exclusivo de aluno; um bug real (função indisponível ali, "Call to
 * undefined function") já veio dessa mistura antes.
 */
function validar_cpf(string $cpf): bool
{
    $cpf = normalizar_cpf($cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) {
            $d += (int) $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ((int) $cpf[$t] !== $d) {
            return false;
        }
    }
    return true;
}

/**
 * Reordena uma lista deixando os itens ativos primeiro (dentro de cada grupo,
 * mantém a ordem original — usort é estável desde o PHP 8). Usado pelas
 * listagens do painel interno (produtos, turmas, imagens da agenda): item
 * desabilitado desce pro fim da lista automaticamente, sem sumir.
 */
function ordenar_ativos_primeiro(array $itens, callable $ehAtivo): array
{
    usort($itens, fn ($a, $b) => (int) !$ehAtivo($a) <=> (int) !$ehAtivo($b));
    return $itens;
}

/**
 * Bloco "Em atualização" — mostrado no lugar da agenda/cursos/produtos
 * quando o administrativo inativou tudo daquela seção, pra visitante nunca
 * ver a seção vazia ou quebrada (mesmo componente reaproveitado nas 3 seções
 * — ver index.php e equipe/agenda.php, turmas.php, produtos.php).
 */
function placeholder_em_atualizacao(string $basePath, string $mensagem): void
{
    ?>
    <div class="placeholder-atualizacao">
        <img src="<?= e($basePath) ?>assets/images/logo-ts.png" alt="TS Treinamentos" loading="lazy" />
        <p><?= e($mensagem) ?></p>
    </div>
    <?php
}

/** Mesma lógica de tools/sync_common.py:slugify() — minúsculo, sem acento, só a-z0-9 e hífen. */
function slugify(string $texto): string
{
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    $mapa = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ];
    $texto = strtr($texto, $mapa);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';
    return trim($texto, '-');
}
