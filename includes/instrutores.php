<?php
/**
 * Contas de instrutor (tabela `instrutores`, MySQL — ver includes/db.php).
 * Área de atuação usa quase a mesma lista do aluno (includes/alunos.php),
 * trocando "estudante" por "médico" — instrutor nunca é estudante.
 */

const AREAS_ATUACAO_INSTRUTOR = [
    'medico'          => 'Médico(a)',
    'enfermeiro'      => 'Enfermeiro(a)',
    'biomedico'       => 'Biomédico(a)',
    'fisioterapeuta'  => 'Fisioterapeuta',
    'farmaceutico'    => 'Farmacêutico(a)',
    'estetica'        => 'Profissional da estética',
    'tecnico_enf'     => 'Técnico(a) de enfermagem',
    'auxiliar_enf'    => 'Auxiliar de enfermagem',
    'outros'          => 'Outros profissionais',
];

function find_instrutor_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM instrutores WHERE email = ? LIMIT 1');
    $stmt->execute([mb_strtolower(trim($email))]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function find_instrutor_by_id(string $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM instrutores WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function listar_instrutores(): array
{
    return db()->query('SELECT * FROM instrutores ORDER BY criado_em DESC')->fetchAll();
}

/** $dados: nome_completo, formacao, cpf, area_atuacao, numero_registro, email, whatsapp, senha. */
function criar_instrutor(array $dados): array
{
    $id = gerar_id('IN');
    $stmt = db()->prepare(
        'INSERT INTO instrutores (id, nome_completo, formacao, cpf, area_atuacao, numero_registro, email, whatsapp, senha_hash, criado_em)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $id,
        trim($dados['nome_completo']),
        trim($dados['formacao']),
        preg_replace('/\D/', '', $dados['cpf']),
        $dados['area_atuacao'],
        $dados['numero_registro'] !== '' ? trim($dados['numero_registro']) : null,
        mb_strtolower(trim($dados['email'])),
        preg_replace('/\D/', '', $dados['whatsapp']),
        password_hash($dados['senha'], PASSWORD_DEFAULT),
        date('Y-m-d H:i:s'),
    ]);
    return find_instrutor_by_id($id);
}

function atualizar_instrutor(string $id, array $changes): ?array
{
    $campos = [];
    $valores = [];
    foreach ($changes as $coluna => $valor) {
        $campos[] = "{$coluna} = ?";
        $valores[] = $valor;
    }
    $valores[] = $id;

    $stmt = db()->prepare('UPDATE instrutores SET ' . implode(', ', $campos) . ' WHERE id = ?');
    $stmt->execute($valores);

    return find_instrutor_by_id($id);
}

/** Confere e-mail/senha. Retorna o registro (sem o hash) se ok, null se inválido/inativo. */
function verificar_login_instrutor(string $email, string $senha): ?array
{
    $instrutor = find_instrutor_by_email($email);
    if (!$instrutor || !(int) $instrutor['ativo'] || !password_verify($senha, $instrutor['senha_hash'])) {
        return null;
    }
    unset($instrutor['senha_hash']);
    return $instrutor;
}
