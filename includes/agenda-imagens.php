<?php
/**
 * Imagens de destaque da seção "Agenda" da home (data/agenda-imagens.json) —
 * até 3 ativas ao mesmo tempo, uma abaixo da outra, cada uma com legenda
 * definida pelo administrativo. Nunca excluídas, só inativadas (ver
 * equipe/agenda.php) — se ficar sem nenhuma ativa, a home mostra um
 * placeholder "Em atualização" (ver placeholder_em_atualizacao() em
 * includes/functions.php) pra visitante nunca ver a seção vazia.
 */

const AGENDA_IMAGENS_MAX_ATIVAS = 3;

function load_agenda_imagens(): array
{
    $path = __DIR__ . '/../data/agenda-imagens.json';
    if (!is_file($path)) {
        return [];
    }
    $dados = json_decode((string) file_get_contents($path), true);
    return is_array($dados) ? $dados : [];
}

function save_agenda_imagens(array $imagens): void
{
    $path = __DIR__ . '/../data/agenda-imagens.json';
    file_put_contents($path, json_encode(array_values($imagens), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
}

function find_agenda_imagem(string $id): ?array
{
    foreach (load_agenda_imagens() as $img) {
        if (($img['id'] ?? '') === $id) {
            return $img;
        }
    }
    return null;
}

function agenda_imagem_ativa(array $imagem): bool
{
    return ($imagem['ativo'] ?? true) !== false;
}

/** Só as ativas, na ordem em que foram cadastradas — o que a home renderiza. */
function agenda_imagens_ativas(): array
{
    return array_values(array_filter(load_agenda_imagens(), 'agenda_imagem_ativa'));
}

function criar_agenda_imagem(array $dados): array
{
    $imagens = load_agenda_imagens();
    $imagens[] = $dados;
    save_agenda_imagens($imagens);
    return $dados;
}

function atualizar_agenda_imagem(string $id, array $changes): ?array
{
    $imagens = load_agenda_imagens();
    $atualizada = null;
    foreach ($imagens as &$img) {
        if ($img['id'] === $id) {
            $img = array_merge($img, $changes);
            $atualizada = $img;
            break;
        }
    }
    unset($img);
    if ($atualizada) {
        save_agenda_imagens($imagens);
    }
    return $atualizada;
}
