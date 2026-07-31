<?php
/**
 * Contas de aluno (protótipo — sem banco de dados ainda).
 * Persistência via includes/storage.php (mesmo cuidado de local seguro que
 * includes/pedidos.php — CPF é dado pessoal sensível, nunca vai pro git).
 */

const ALUNOS_FILE = 'alunos.json';

const AREAS_ATUACAO = [
    'enfermeiro'      => 'Enfermeiro(a)',
    'biomedico'       => 'Biomédico(a)',
    'fisioterapeuta'  => 'Fisioterapeuta',
    'farmaceutico'    => 'Farmacêutico(a)',
    'estetica'        => 'Profissional da estética',
    'tecnico_enf'     => 'Técnico(a) de enfermagem',
    'auxiliar_enf'    => 'Auxiliar de enfermagem',
    'estudante'       => 'Estudante',
    'outros'          => 'Outros profissionais',
];

const PRONOMES = ['Sr.', 'Sra.'];

/** Pronome sempre implica sexo (M/F) — usado pra estatística de alunos x alunas. */
const PRONOME_PARA_SEXO = ['Sr.' => 'M', 'Sra.' => 'F'];

function pronome_para_sexo(string $pronome): string
{
    return PRONOME_PARA_SEXO[$pronome] ?? '';
}

/** Data no formato Y-m-d, não pode ser futura nem anterior a 1900. */
function validar_data_nascimento(string $data): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $data);
    if (!$d || $d->format('Y-m-d') !== $data) {
        return false;
    }
    if ($d > new DateTime('today')) {
        return false;
    }
    return (int) $d->format('Y') >= 1900;
}

function load_alunos(): array
{
    return storage_read_json(ALUNOS_FILE);
}

function save_alunos(array $alunos): void
{
    storage_write_json(ALUNOS_FILE, $alunos);
}

function normalizar_cpf(string $cpf): string
{
    return preg_replace('/\D/', '', $cpf) ?? '';
}

/** Validação do dígito verificador do CPF (mesmo algoritmo já usado no site antigo). */
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

function normalizar_whatsapp(string $whatsapp): string
{
    return preg_replace('/\D/', '', $whatsapp) ?? '';
}

const ESTADOS_BR = [
    'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
    'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
];

function normalizar_cep(string $cep): string
{
    return preg_replace('/\D/', '', $cep) ?? '';
}

function validar_cep(string $cep): bool
{
    return preg_match('/^\d{8}$/', normalizar_cep($cep)) === 1;
}

function gerar_aluno_id(): string
{
    return 'AL' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

function find_aluno_by_email(string $email): ?array
{
    $email = mb_strtolower(trim($email));
    foreach (load_alunos() as $aluno) {
        if (mb_strtolower($aluno['email'] ?? '') === $email) {
            return $aluno;
        }
    }
    return null;
}

function find_aluno_by_cpf(string $cpf): ?array
{
    $cpf = normalizar_cpf($cpf);
    foreach (load_alunos() as $aluno) {
        if (($aluno['cpf'] ?? '') === $cpf) {
            return $aluno;
        }
    }
    return null;
}

function find_aluno_by_id(string $id): ?array
{
    foreach (load_alunos() as $aluno) {
        if ($aluno['id'] === $id) {
            return $aluno;
        }
    }
    return null;
}

/**
 * Cria uma conta de aluno. Espera $dados já validado (ver area-do-aluno.php)
 * com: pronome, nome, email, whatsapp, cpf, area_atuacao, senha (texto puro
 * — vira hash aqui, nunca é salvo em texto puro).
 */
function criar_aluno(array $dados): array
{
    $alunos = load_alunos();

    $aluno = [
        'id'               => gerar_aluno_id(),
        'pronome'          => $dados['pronome'],
        'sexo'             => pronome_para_sexo($dados['pronome']),
        'nome'             => trim($dados['nome']),
        'email'            => mb_strtolower(trim($dados['email'])),
        'whatsapp'         => normalizar_whatsapp($dados['whatsapp']),
        'cpf'              => normalizar_cpf($dados['cpf']),
        'data_nascimento'  => $dados['data_nascimento'],
        'endereco'         => null,
        'numero'           => null,
        'cep'              => null,
        'cidade'           => null,
        'estado'           => null,
        'foto'             => null,
        'area_atuacao'     => $dados['area_atuacao'],
        'senha_hash'       => password_hash($dados['senha'], PASSWORD_DEFAULT),
        'google_sub'       => null,
        'ativo'            => true,
        'criado_em'        => date('Y-m-d H:i:s'),
    ];

    $alunos[] = $aluno;
    save_alunos($alunos);

    return $aluno;
}

/**
 * Cria uma conta a partir do login com Google (ver includes/google_oauth.php).
 * O e-mail vem do Google (verificado, fixo); o resto — pronome, nome, whatsapp,
 * cpf, data de nascimento, área de atuação (obrigatórios, mesma regra do
 * cadastro normal) e endereço/foto (opcionais) — é preenchido numa etapa extra
 * ver auth/google-completar-cadastro.php, já que o Google só garante e-mail.
 * Sem senha: login dessa conta é sempre via Google.
 */
function criar_aluno_google(string $email, string $googleSub, array $dados): array
{
    $alunos = load_alunos();

    $aluno = [
        'id'               => gerar_aluno_id(),
        'pronome'          => $dados['pronome'],
        'sexo'             => pronome_para_sexo($dados['pronome']),
        'nome'             => trim($dados['nome']),
        'email'            => mb_strtolower(trim($email)),
        'whatsapp'         => normalizar_whatsapp($dados['whatsapp']),
        'cpf'              => normalizar_cpf($dados['cpf']),
        'data_nascimento'  => $dados['data_nascimento'],
        'endereco'         => $dados['endereco'] ?? null,
        'numero'           => $dados['numero'] ?? null,
        'cep'              => $dados['cep'] ?? null,
        'cidade'           => $dados['cidade'] ?? null,
        'estado'           => $dados['estado'] ?? null,
        'foto'             => null,
        'area_atuacao'     => $dados['area_atuacao'],
        'senha_hash'       => null,
        'google_sub'       => $googleSub,
        'ativo'            => true,
        'criado_em'        => date('Y-m-d H:i:s'),
    ];

    $alunos[] = $aluno;
    save_alunos($alunos);

    return $aluno;
}

/** Atualiza campos de uma conta existente pelo id (ex.: vincular google_sub depois). */
function atualizar_aluno(string $id, array $changes): ?array
{
    $alunos = load_alunos();
    $atualizado = null;

    foreach ($alunos as &$aluno) {
        if ($aluno['id'] === $id) {
            $aluno = array_merge($aluno, $changes);
            $atualizado = $aluno;
            break;
        }
    }
    unset($aluno);

    if ($atualizado) {
        save_alunos($alunos);
    }
    return $atualizado;
}

/** Confere e-mail/senha. Retorna o aluno (sem o hash) se ok, null se inválido ou desabilitado. */
function verificar_login(string $email, string $senha): ?array
{
    $aluno = find_aluno_by_email($email);
    if (!$aluno || ($aluno['ativo'] ?? true) === false || !password_verify($senha, $aluno['senha_hash'] ?? '')) {
        return null;
    }
    unset($aluno['senha_hash']);
    return $aluno;
}

function aluno_area_label(string $chave): string
{
    return AREAS_ATUACAO[$chave] ?? $chave;
}

/** Todos os alunos, mais recentes primeiro — pro painel do diretor (ver equipe/alunos.php). */
function listar_alunos(): array
{
    $alunos = load_alunos();
    usort($alunos, fn ($a, $b) => strcmp($b['criado_em'] ?? '', $a['criado_em'] ?? ''));
    return $alunos;
}
