#!/usr/bin/env python3
"""
sync_agenda.py — lê o conteúdo de agenda/, processa as imagens e atualiza
data/turmas.json + gera as páginas individuais em cursos/{slug}.php.

Suporta DOIS formatos dentro de agenda/ (pode misturar os dois):

1) Arquivo solto (uso rápido, recomendado): uma imagem direto em agenda/,
   com o nome no padrão:
       curso NOME DO CURSO DD-MM-AA HHMM valor PRECO.ext
   Ex.: "curso furo humanizado 22-8-26 0900 valor 1450.jpeg"
   Duas imagens com o mesmo nome de curso viram datas diferentes da MESMA turma.

2) Pasta por curso (uso avançado, quando quer controlar status/descrição):
       agenda/nome-do-curso/dados.txt + agenda/nome-do-curso/imagem.*
   Ver AGENDA_README.md para o formato do dados.txt.

Uso: python tools/sync_agenda.py [--dry-run]

Sempre faz UPSERT por slug (nunca apaga turmas existentes que não estejam
mais na pasta agenda/ nessa execução — isso é reportado, não removido
automaticamente). Requer Pillow (`pip install Pillow`).
"""
from __future__ import annotations

import json
import re
import sys
from datetime import datetime
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

try:
    from sync_common import (
        IMAGE_EXTS, find_image, parse_dados_txt, parse_price, process_image,
        slugify, titlecase_pt,
    )
except ImportError as exc:
    print(f"ERRO: dependência ausente ({exc}). Rode: python -m pip install Pillow")
    sys.exit(1)

ROOT = Path(__file__).resolve().parent.parent
AGENDA_DIR = ROOT / "agenda"
DATA_FILE = ROOT / "data" / "turmas.json"
IMAGES_DIR = ROOT / "assets" / "images" / "cursos"
CURSOS_DIR = ROOT / "cursos"
DEFAULT_LOCAL = "Presencial · São José do Rio Preto"
DEFAULT_STATUS = "aberta"
VALID_STATUS = {"aberta", "ultimas", "esgotada"}

DRY_RUN = "--dry-run" in sys.argv

CURSO_PAGE_TEMPLATE = """<?php
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/turmas.php';
require __DIR__ . '/../includes/curso-page.php';
render_curso_page('{slug}');
"""

FILENAME_PATTERN = re.compile(
    r"^curso\s+(?P<nome>.+?)\s+(?P<dia>\d{1,2})-(?P<mes>\d{1,2})-(?P<ano>\d{2})\s+"
    r"(?P<hora>\d{3,4})\s+valor\s+(?P<preco>[\d.,]+)$",
    re.IGNORECASE,
)


def parse_filename(path: Path) -> dict | None:
    """Extrai curso/data/horário/preço de um nome de arquivo como
    'curso furo humanizado 22-8-26 0900 valor 1450.jpeg'. Retorna None se
    o nome não seguir o padrão esperado."""
    m = FILENAME_PATTERN.match(path.stem.strip())
    if not m:
        return None

    dia, mes, ano = int(m["dia"]), int(m["mes"]), int(m["ano"])
    ano_completo = 2000 + ano
    try:
        data_iso = datetime(ano_completo, mes, dia).strftime("%Y-%m-%d")
    except ValueError:
        return None

    hora_str = m["hora"].zfill(4)
    horario = f"{hora_str[:-2]}:{hora_str[-2:]}"

    try:
        preco = parse_price(m["preco"])
    except ValueError:
        return None

    return {
        "curso": titlecase_pt(m["nome"]),
        "data_iso": data_iso,
        "horario": horario,
        "preco": preco,
    }


def load_existing_turmas() -> list[dict]:
    if not DATA_FILE.is_file():
        return []
    return json.loads(DATA_FILE.read_text(encoding="utf-8"))


def main() -> int:
    if not AGENDA_DIR.is_dir():
        print(f"Pasta {AGENDA_DIR} não existe — nada para sincronizar.")
        return 0

    existing = load_existing_turmas()
    by_slug = {t["slug"]: t for t in existing}
    added, updated, errors, skipped = [], [], [], []

    # --- Formato 1: arquivos soltos com dados no nome ---
    loose_files = [f for f in sorted(AGENDA_DIR.iterdir()) if f.is_file() and f.suffix.lower() in IMAGE_EXTS]
    occurrences: dict[str, dict] = {}  # slug -> {curso, datas: set, horario, preco, imagem_src}

    for f in loose_files:
        parsed = parse_filename(f)
        if not parsed:
            skipped.append(f"{f.name}: nome não segue o padrão 'curso NOME DD-MM-AA HHMM valor PRECO' — ignorado.")
            continue
        slug = slugify(parsed["curso"])
        if slug not in occurrences:
            occurrences[slug] = {
                "curso": parsed["curso"],
                "datas": [],
                "horario": parsed["horario"],
                "preco": parsed["preco"],
                "imagem_src": f,
            }
        occurrences[slug]["datas"].append(parsed["data_iso"])
        if parsed["preco"] != occurrences[slug]["preco"]:
            errors.append(
                f"{f.name}: preço ({parsed['preco']}) diferente do já visto para "
                f"'{parsed['curso']}' ({occurrences[slug]['preco']}) — mantendo o primeiro valor encontrado."
            )

    for slug, occ in occurrences.items():
        image_name = f"{slug}.jpg"
        if not DRY_RUN:
            process_image(occ["imagem_src"], IMAGES_DIR / image_name)

        turma = {
            "slug": slug,
            "curso": occ["curso"],
            "datas": sorted(set(occ["datas"])),
            "horario": occ["horario"],
            "local": DEFAULT_LOCAL,
            "preco": occ["preco"],
            "status": DEFAULT_STATUS,
            "imagem": image_name,
            "descricao": "",
        }
        (updated if slug in by_slug else added).append(slug)
        by_slug[slug] = turma
        if not DRY_RUN:
            (CURSOS_DIR / f"{slug}.php").write_text(CURSO_PAGE_TEMPLATE.format(slug=slug), encoding="utf-8")

    # --- Formato 2: pasta por curso com dados.txt ---
    subfolders = [f for f in sorted(AGENDA_DIR.iterdir()) if f.is_dir()]
    for folder in subfolders:
        dados_path = folder / "dados.txt"
        if not dados_path.is_file():
            errors.append(f"{folder.name}/: falta o arquivo dados.txt — pasta ignorada.")
            continue

        fields = parse_dados_txt(dados_path)
        missing = [k for k in ("curso", "datas", "horario", "preco") if k not in fields]
        if missing:
            errors.append(f"{folder.name}/: faltam campos obrigatórios: {', '.join(missing)} — pasta ignorada.")
            continue

        try:
            datas_iso = [
                datetime.strptime(d.strip(), "%d/%m/%Y").strftime("%Y-%m-%d")
                for d in fields["datas"].split(",") if d.strip()
            ]
            if not datas_iso:
                raise ValueError("nenhuma data válida")
        except ValueError as exc:
            errors.append(f"{folder.name}/: campo 'datas' inválido ({exc}) — use DD/MM/AAAA. Pasta ignorada.")
            continue

        try:
            preco = parse_price(fields["preco"])
        except ValueError:
            errors.append(f"{folder.name}/: campo 'preco' inválido ('{fields['preco']}') — pasta ignorada.")
            continue

        status = fields.get("status", DEFAULT_STATUS).strip().lower()
        if status not in VALID_STATUS:
            errors.append(f"{folder.name}/: status '{status}' inválido — usando '{DEFAULT_STATUS}'.")
            status = DEFAULT_STATUS

        image_src = find_image(folder)
        if not image_src:
            errors.append(f"{folder.name}/: nenhuma imagem encontrada — pasta ignorada.")
            continue

        slug = slugify(folder.name)
        image_name = f"{slug}.jpg"
        if not DRY_RUN:
            process_image(image_src, IMAGES_DIR / image_name)

        turma = {
            "slug": slug, "curso": fields["curso"], "datas": datas_iso,
            "horario": fields["horario"], "local": fields.get("local", DEFAULT_LOCAL),
            "preco": preco, "status": status, "imagem": image_name,
            "descricao": fields.get("descricao", ""),
        }
        (updated if slug in by_slug else added).append(slug)
        by_slug[slug] = turma
        if not DRY_RUN:
            (CURSOS_DIR / f"{slug}.php").write_text(CURSO_PAGE_TEMPLATE.format(slug=slug), encoding="utf-8")

    result = sorted(by_slug.values(), key=lambda t: t["datas"][0])

    if not DRY_RUN:
        DATA_FILE.parent.mkdir(parents=True, exist_ok=True)
        DATA_FILE.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    seen_slugs = set(occurrences.keys()) | {slugify(f.name) for f in subfolders}
    orphaned = [t["slug"] for t in existing if t["slug"] not in seen_slugs]

    print(f"{'[DRY-RUN] ' if DRY_RUN else ''}Sincronização concluída.")
    print(f"  Adicionados: {added or '-'}")
    print(f"  Atualizados: {updated or '-'}")
    if orphaned:
        print(f"  Presentes no site mas sem arquivo/pasta correspondente em agenda/ (não removidos): {orphaned}")
    if skipped:
        print("  Arquivos ignorados (não reconhecidos):")
        for s in skipped:
            print(f"    - {s}")
    if errors:
        print("  Avisos/erros:")
        for e in errors:
            print(f"    - {e}")

    return 0


if __name__ == "__main__":
    sys.exit(main())
