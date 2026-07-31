"""
sync_common.py — funções compartilhadas entre os scripts de sincronização de
conteúdo local (sync_agenda.py, sync_produtos.py, e futuros). Não é executado
diretamente.
"""
from __future__ import annotations

import re
import unicodedata
from pathlib import Path

from PIL import Image

IMAGE_EXTS = {".jpg", ".jpeg", ".png", ".webp"}
TARGET_SIZE = (900, 675)  # 4:3, mesma proporção usada nos cards e nas páginas individuais

# Dicionário de correção de acentos para palavras comuns nos nomes de curso/produto.
# Nomes de arquivo costumam vir sem acento (limitação do sistema de arquivos/do
# jeito que foram salvos) — isso tenta reconstituir a grafia correta para as
# palavras mais usadas. Não é perfeito: revise o nome final no JSON gerado se
# alguma palavra não constar aqui.
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
    "kit": "Kit", "book": "Book", "card": "Card", "avancado": "Avançado",
    "avancados": "Avançados", "basico": "Básico", "basicos": "Básicos",
    "seguranca": "Segurança", "clinica": "Clínica", "clinico": "Clínico",
    "anotacao": "Anotação", "anotacoes": "Anotações", "pratica": "Prática",
    "guia": "Guia", "hipodemoclise": "Hipodermóclise",
    "hipodermoclise": "Hipodermóclise",
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


# Trechos "colados" conhecidos (conector grudado no fim de um pedaço em
# camelCase, sem letra maiúscula própria pra sinalizar a quebra). Mapeamento
# explícito em vez de heurística de sufixo — tentar adivinhar sufixos tipo
# "de"/"em"/"na" quebraria palavras legítimas (ex.: "Saude", "Enfermagem",
# "Semana" não são conector + resto). Só ADICIONE aqui trechos já vistos de
# verdade em nomes de arquivo — nunca um sufixo genérico.
GLUED_NAME_FIXES = {
    "anotacaode": "Anotação de",
    "guiade": "Guia de",
    "hipodemoclisena": "Hipodermóclise na",
    "hipodermoclisena": "Hipodermóclise na",
}


def decode_glued_name(raw: str) -> str:
    """Reconstrói um nome legível a partir de um trecho 'colado' em
    camelCase/PascalCase sem espaços (ex.: 'AnotacaodeEnfermagem' ->
    'Anotação de Enfermagem'). Faz um split por transição minúscula->maiúscula
    e resolve pedaços conhecidos via GLUED_NAME_FIXES; pedaços desconhecidos
    ficam como uma palavra só (sem tentar adivinhar conectores grudados).
    Best-effort: sempre revise o nome final no JSON gerado — se aparecer um
    trecho novo colado, adicione-o em GLUED_NAME_FIXES."""
    spaced = re.sub(r"(?<=[a-z0-9])(?=[A-Z])", " ", raw)
    words: list[str] = []
    for chunk in spaced.split():
        fixed = GLUED_NAME_FIXES.get(chunk.lower())
        words.append(fixed if fixed else fix_word(chunk))
    result = " ".join(words)
    return result[0].upper() + result[1:] if result else result


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
    """Recorta ao centro para 4:3 e redimensiona/otimiza como JPEG."""
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


def find_image(folder: Path) -> Path | None:
    for f in sorted(folder.iterdir()):
        if f.suffix.lower() in IMAGE_EXTS:
            return f
    return None


def parse_dados_txt(path: Path) -> dict:
    """Parser genérico de arquivo `chave: valor` por linha."""
    fields: dict[str, str] = {}
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or ":" not in line:
            continue
        key, value = line.split(":", 1)
        fields[key.strip().lower()] = value.strip()
    return fields
