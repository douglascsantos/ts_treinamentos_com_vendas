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
import unicodedata
from datetime import datetime
from pathlib import Path

try:
    from PIL import Image
except ImportError:
    print("ERRO: Pillow não está instalado. Rode: python -m pip install Pillow")
    sys.exit(1)

ROOT = Path(__file__).resolve().parent.parent
AGENDA_DIR = ROOT / "agenda"
DATA_FILE = ROOT / "data" / "turmas.json"
IMAGES_DIR = ROOT / "assets" / "images" / "cursos"
CURSOS_DIR = ROOT / "cursos"
IMAGE_EXTS = {".jpg", ".jpeg", ".png", ".webp"}
TARGET_SIZE = (900, 675)  # 4:3, mesma proporção usada no card e na página do curso
DEFAULT_LOCAL = "Presencial · São José do Rio Preto"
DEFAULT_STATUS = "aberta"
VALID_STATUS = {"aberta", "ultimas", "esgotada"}

DRY_RUN = "--dry-run" in sys.argv

# Dicionário de correção de acentos para palavras comuns nos nomes de curso.
# Nomes de arquivo costumam vir sem acento (limitação do sistema de arquivos/
# do jeito que foram salvos) — isso tenta reconstituir a grafia correta para
# as palavras mais usadas nesse tipo de curso. Não é perfeito: revise o nome
# final em data/turmas.json se alguma palavra não constar aqui.
WORD_FIXES = {
    "administracao": "Administração", "medicamentos": "Medicamentos",
    "injetaveis": "Injetáveis", "puncao": "Punção", "venosa": "Venosa",
    "coleta": "Coleta", "sangue": "Sangue", "avaliacao": "Avaliação",
    "feridas": "Feridas", "curativos": "Curativos", "diluicoes": "Diluições",
    "preparacoes": "Preparações", "furo": "Furo", "humanizado": "Humanizado",
    "plantao": "Plantão", "medo": "Medo", "rotinas": "Rotinas",
    "hospitalares": "Hospitalares", "eletrocardiograma": "Eletrocardiograma",
    "monitorizacao": "Monitorização", "oxigenioterapia": "Oxigenioterapia",
    "traqueostomia": "Traqueostomia", "sondagem": "Sondagem",
    "vesical": "Vesical", "enteral": "Enteral", "acesso": "Acesso",
    "vascular": "Vascular", "perfuracao": "Perfuração",
    "reforco": "Reforço", "escolar": "Escolar", "processo": "Processo",
    "seletivo": "Seletivo", "hospitais": "Hospitais", "provas": "Provas",
    "urgencia": "Urgência", "emergencia": "Emergência", "cateter": "Cateter",
    "central": "Central", "hipodermoclise": "Hipodermóclise",
}
MINOR_WORDS = {"de", "da", "do", "das", "dos", "e", "em", "com", "para", "a", "o", "no", "na"}


def fix_word(word: str) -> str:
    key = word.lower()
    if key in WORD_FIXES:
        return WORD_FIXES[key]
    if key in MINOR_WORDS:
        return key
    return key.capitalize()


def titlecase_pt(text: str) -> str:
    words = text.split()
    out = [fix_word(w) for w in words]
    if out:
        out[0] = out[0][0].upper() + out[0][1:] if out[0] else out[0]
    return " ".join(out)


def slugify(text: str) -> str:
    text = unicodedata.normalize("NFKD", text).encode("ascii", "ignore").decode("ascii")
    text = text.lower().strip()
    text = re.sub(r"[^a-z0-9]+", "-", text)
    return text.strip("-")


def parse_price(raw: str) -> float:
    s = raw.strip().replace("R$", "").strip()
    if "," in s and s.rfind(",") > s.rfind("."):
        s = s.replace(".", "").replace(",", ".")
    else:
        s = s.replace(",", "")
    return float(s)


def process_image(src: Path, dest: Path) -> None:
    img = Image.open(src).convert("RGB")
    src_w, src_h = img.size
    target_ratio = TARGET_SIZE[0] / TARGET_SIZE[1]
    src_ratio = src_w / src_h

    if src_ratio > target_ratio:
        new_w = int(src_h * target_ratio)
        left = (src_w - new_w) // 2
        img = img.crop((left, 0, left + new_w, src_h))
    else:
        new_h = int(src_w / target_ratio)
        top = (src_h - new_h) // 2
        img = img.crop((0, top, src_w, top + new_h))

    img = img.resize(TARGET_SIZE, Image.LANCZOS)
    dest.parent.mkdir(parents=True, exist_ok=True)
    img.save(dest, "JPEG", quality=85, optimize=True)


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


def find_image(folder: Path) -> Path | None:
    for f in sorted(folder.iterdir()):
        if f.suffix.lower() in IMAGE_EXTS:
            return f
    return None


def parse_dados_txt(path: Path) -> dict:
    fields: dict[str, str] = {}
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or ":" not in line:
            continue
        key, value = line.split(":", 1)
        fields[key.strip().lower()] = value.strip()
    return fields


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
