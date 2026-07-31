<?php
/**
 * Contas de administrativo/diretor (tabela `administradores`, MySQL — ver
 * includes/db.php). Diretor é só `nivel = 'diretor'` na mesma tabela, com
 * mais permissão (cadastra instrutor/administrativo/aluno, vê tudo).
 */

function find_administrador_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM administradores WHERE email = ? LIMIT 1');
    $stmt->execute([mb_strtolower(trim($email))]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function find_administrador_by_id(string $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM administradores WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function listar_administradores(): array
{
    return db()->query('SELECT * FROM administradores ORDER BY criado_em DESC')->fetchAll();
}

/** $dados: nome, email, senha, nivel ('administrativo'|'diretor'). */
function criar_administrador(array $dados): array
{
    $id = gerar_id('AD');
    $stmt = db()->prepare(
        'INSERT INTO administradores (id, nome, email, senha_hash, nivel, criado_em) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $id,
        trim($dados['nome']),
        mb_strtolower(trim($dados['email'])),
        password_hash($dados['senha'], PASSWORD_DEFAULT),
        $dados['nivel'],
        date('Y-m-d H:i:s'),
    ]);
    return find_administrador_by_id($id);
}

function atualizar_administrador(string $id, array $changes): ?array
{
    $campos = [];
    $valores = [];
    foreach ($changes as $coluna => $valor) {
        $campos[] = "{$coluna} = ?";
        $valores[] = $valor;
    }
    $valores[] = $id;

    $stmt = db()->prepare('UPDATE administradores SET ' . implode(', ', $campos) . ' WHERE id = ?');
    $stmt->execute($valores);

    return find_administrador_by_id($id);
}

/** Confere e-mail/senha. Retorna o registro (sem o hash) se ok, null se inválido/inativo. */
function verificar_login_administrador(string $email, string $senha): ?array
{
    $admin = find_administrador_by_email($email);
    if (!$admin || !(int) $admin['ativo'] || !password_verify($senha, $admin['senha_hash'])) {
        return null;
    }
    unset($admin['senha_hash']);
    return $admin;
}
