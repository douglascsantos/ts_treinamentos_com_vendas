#!/usr/bin/env python3
"""
sync_produtos.py — lê o conteúdo de produtos_ts_site/, processa as imagens e
atualiza data/produtos.json + gera as páginas individuais em produtos/{slug}.php.

Suporta DOIS formatos dentro de produtos_ts_site/ (pode misturar os dois):

1) Arquivo solto (uso rápido, recomendado): uma imagem direto em
   produtos_ts_site/, com o nome no padrão:
       produto NOME DO PRODUTO tipo TIPO valor PRECO estoque QTD.ext
   Ex.: "produto Kit Avaliação de Feridas tipo kit valor 350 estoque 12.jpg"
   TIPO é um de: card, kit, book.
   Para produtos digitais (tipo book), "estoque" pode ser omitido — um e-book
   em PDF nunca esgota:
       produto NOME DO PRODUTO tipo book valor PRECO.ext
   Ex.: "produto Rotinas de Enfermagem tipo book valor 89,90.jpg"

2) Pasta por produto (uso avançado, quando quer controlar descrição):
       produtos_ts_site/nome-do-produto/dados.txt + .../imagem.*
   Ver PRODUTOS_README.md para o formato do dados.txt.

Uso: python tools/sync_produtos.py [--dry-run]

Sempre faz UPSERT por slug (nunca apaga produtos existentes que não estejam
mais na pasta produtos_ts_site/ nessa execução — isso é reportado, não
removido automaticamente). Requer Pillow (`pip install Pillow`).
"""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

try:
    from sync_common import (
        IMAGE_EXTS, decode_glued_name, find_image, parse_dados_txt, parse_price,
        process_image, slugify, titlecase_pt,
    )
except ImportError as exc:
    print(f"ERRO: dependência ausente ({exc}). Rode: python -m pip install Pillow")
    sys.exit(1)

ROOT = Path(__file__).resolve().parent.parent
PRODUTOS_SRC_DIR = ROOT / "produtos_ts_site"
DATA_FILE = ROOT / "data" / "produtos.json"
IMAGES_DIR = ROOT / "assets" / "images" / "produtos"
PRODUTOS_DIR = ROOT / "produtos"
VALID_TIPOS = {"card", "kit", "book"}
TIPOS_DIGITAIS = {"book"}
DIGITAL_ESTOQUE_PLACEHOLDER = 9999  # nunca exibido — produto digital ignora estoque na UI

# PDF de e-book: fica fora do repositório/public_html (mesmo princípio de
# segurança de includes/certificados.php) — nunca com o nome original, pra
# não ficar adivinhável. Liberado só por ebook-download.php, que confere se
# o pedido é do aluno logado e está pago.
EBOOKS_PROTEGIDOS_DIR = ROOT.parent / "ts_site_data" / "ebooks_protegidos"

DRY_RUN = "--dry-run" in sys.argv

PRODUTO_PAGE_TEMPLATE = """<?php
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/produtos.php';
require __DIR__ . '/../includes/produto-page.php';
render_produto_page('{slug}');
"""

# "tipo" e "estoque" nesta ordem, com estoque opcional (produtos digitais).
FILENAME_PATTERN_FULL = re.compile(
    r"^produto\s+(?P<nome>.+?)\s+tipo\s+(?P<tipo>card|kit|book)\s+valor\s+"
    r"(?P<preco>[\d.,]+)\s+estoque\s+(?P<estoque>\d+)$",
    re.IGNORECASE,
)
FILENAME_PATTERN_DIGITAL = re.compile(
    r"^produto\s+(?P<nome>.+?)\s+tipo\s+book\s+valor\s+(?P<preco>[\d.,]+)$",
    re.IGNORECASE,
)

# Segundo padrão real usado para e-books: nome "colado" em camelCase, sem
# espaços, ex.: "ebook_online_AnotacaodeEnfermagem_valor19.90.png".
FILENAME_PATTERN_EBOOK_ONLINE = re.compile(
    r"^ebook[_ ]online[_ ](?P<nome>.+?)[_ ]valor(?P<preco>[\d.,]+)$",
    re.IGNORECASE,
)


def parse_pdf_candidate_slug(path: Path) -> str:
    """Extrai um slug candidato do nome de um PDF de e-book (ex.:
    'ebook_Anotacao_de_Enfermagem_(10.5 x 14.8 cm)_valor19.90.pdf' ->
    'anotacao-de-enfermagem'), pra casar com o produto tipo=book já
    cadastrado pela capa .png. Não tenta extrair preço/tipo do PDF — isso já
    vem do produto existente."""
    stem = path.stem
    stem = re.sub(r"^ebook[_ ]online[_ ]", "", stem, flags=re.IGNORECASE)
    stem = re.sub(r"^ebook[_ ]", "", stem, flags=re.IGNORECASE)
    stem = re.sub(r"\([^)]*\)", "", stem)
    stem = re.sub(r"[_ ]valor[\d.,]+\s*$", "", stem, flags=re.IGNORECASE)
    return slugify(stem)


def sincronizar_ebooks_pdf(by_slug: dict, dry_run: bool) -> tuple[list[str], list[str]]:
    """Casa PDF solto em produtos_ts_site/ com um produto tipo=book pelo slug
    (match exato ou um sendo prefixo do outro), move pra fora do repo com
    nome não adivinhável, e grava produtos[slug]['arquivo_ebook']."""
    vinculados, avisos = [], []
    pdfs = [f for f in sorted(PRODUTOS_SRC_DIR.iterdir()) if f.is_file() and f.suffix.lower() == ".pdf"]
    if not pdfs:
        return vinculados, avisos

    livros = {slug: p for slug, p in by_slug.items() if p.get("tipo") == "book"}

    if not dry_run:
        EBOOKS_PROTEGIDOS_DIR.mkdir(parents=True, exist_ok=True)

    for pdf in pdfs:
        candidato = parse_pdf_candidate_slug(pdf)
        alvo_slug = None
        if candidato in livros:
            alvo_slug = candidato
        else:
            for slug in livros:
                if slug.startswith(candidato) or candidato.startswith(slug):
                    alvo_slug = slug
                    break

        if not alvo_slug:
            avisos.append(f"{pdf.name}: não achei produto tipo=book correspondente (slug candidato '{candidato}') — ignorado.")
            continue

        import secrets
        nome_arquivo = f"{alvo_slug}_{secrets.token_hex(8)}.pdf"
        if not dry_run:
            (EBOOKS_PROTEGIDOS_DIR / nome_arquivo).write_bytes(pdf.read_bytes())
        by_slug[alvo_slug]["arquivo_ebook"] = nome_arquivo
        vinculados.append(f"{pdf.name} -> {alvo_slug} ({nome_arquivo})")

    return vinculados, avisos


def parse_filename(path: Path) -> dict | None:
    stem = path.stem.strip()

    m = FILENAME_PATTERN_FULL.match(stem)
    if m:
        try:
            preco = parse_price(m["preco"])
        except ValueError:
            return None
        return {
            "nome": titlecase_pt(m["nome"]),
            "tipo": m["tipo"].lower(),
            "preco": preco,
            "estoque": int(m["estoque"]),
        }

    m = FILENAME_PATTERN_DIGITAL.match(stem)
    if m:
        try:
            preco = parse_price(m["preco"])
        except ValueError:
            return None
        return {
            "nome": titlecase_pt(m["nome"]),
            "tipo": "book",
            "preco": preco,
            "estoque": DIGITAL_ESTOQUE_PLACEHOLDER,
        }

    m = FILENAME_PATTERN_EBOOK_ONLINE.match(stem)
    if m:
        try:
            preco = parse_price(m["preco"])
        except ValueError:
            return None
        return {
            "nome": decode_glued_name(m["nome"]),
            "tipo": "book",
            "preco": preco,
            "estoque": DIGITAL_ESTOQUE_PLACEHOLDER,
        }

    return None


def load_existing_produtos() -> list[dict]:
    if not DATA_FILE.is_file():
        return []
    return json.loads(DATA_FILE.read_text(encoding="utf-8"))


def write_produto_page(slug: str) -> None:
    (PRODUTOS_DIR / f"{slug}.php").write_text(PRODUTO_PAGE_TEMPLATE.format(slug=slug), encoding="utf-8")


def main() -> int:
    if not PRODUTOS_SRC_DIR.is_dir():
        print(f"Pasta {PRODUTOS_SRC_DIR} não existe — nada para sincronizar.")
        return 0

    existing = load_existing_produtos()
    by_slug = {p["slug"]: p for p in existing}
    added, updated, errors, skipped = [], [], [], []
    seen_slugs: set[str] = set()

    # --- Formato 1: arquivos soltos com dados no nome ---
    loose_files = [f for f in sorted(PRODUTOS_SRC_DIR.iterdir()) if f.is_file() and f.suffix.lower() in IMAGE_EXTS]
    for f in loose_files:
        parsed = parse_filename(f)
        if not parsed:
            skipped.append(
                f"{f.name}: nome não segue o padrão 'produto NOME tipo TIPO valor PRECO [estoque QTD]' — ignorado."
            )
            continue

        slug = slugify(parsed["nome"])
        image_name = f"{slug}.jpg"
        if not DRY_RUN:
            process_image(f, IMAGES_DIR / image_name)

        produto = {
            "slug": slug,
            "nome": parsed["nome"],
            "tipo": parsed["tipo"],
            "preco": parsed["preco"],
            "estoque": parsed["estoque"],
            "imagem": image_name,
            "descricao": "",
        }
        (updated if slug in by_slug else added).append(slug)
        by_slug[slug] = produto
        seen_slugs.add(slug)
        if not DRY_RUN:
            write_produto_page(slug)

    # --- Formato 2: pasta por produto com dados.txt ---
    subfolders = [f for f in sorted(PRODUTOS_SRC_DIR.iterdir()) if f.is_dir()]
    for folder in subfolders:
        dados_path = folder / "dados.txt"
        if not dados_path.is_file():
            errors.append(f"{folder.name}/: falta o arquivo dados.txt — pasta ignorada.")
            continue

        fields = parse_dados_txt(dados_path)
        missing = [k for k in ("nome", "tipo", "preco") if k not in fields]
        if missing:
            errors.append(f"{folder.name}/: faltam campos obrigatórios: {', '.join(missing)} — pasta ignorada.")
            continue

        tipo = fields["tipo"].strip().lower()
        if tipo not in VALID_TIPOS:
            errors.append(f"{folder.name}/: tipo '{tipo}' inválido (use card/kit/book) — pasta ignorada.")
            continue

        try:
            preco = parse_price(fields["preco"])
        except ValueError:
            errors.append(f"{folder.name}/: campo 'preco' inválido ('{fields['preco']}') — pasta ignorada.")
            continue

        if tipo in TIPOS_DIGITAIS:
            estoque = DIGITAL_ESTOQUE_PLACEHOLDER
        else:
            try:
                estoque = int(fields.get("estoque", "").strip())
            except ValueError:
                errors.append(f"{folder.name}/: campo 'estoque' obrigatório/inválido para tipo '{tipo}' — pasta ignorada.")
                continue

        image_src = find_image(folder)
        if not image_src:
            errors.append(f"{folder.name}/: nenhuma imagem encontrada — pasta ignorada.")
            continue

        slug = slugify(folder.name)
        image_name = f"{slug}.jpg"
        if not DRY_RUN:
            process_image(image_src, IMAGES_DIR / image_name)

        produto = {
            "slug": slug, "nome": fields["nome"], "tipo": tipo, "preco": preco,
            "estoque": estoque, "imagem": image_name, "descricao": fields.get("descricao", ""),
        }
        (updated if slug in by_slug else added).append(slug)
        by_slug[slug] = produto
        seen_slugs.add(slug)
        if not DRY_RUN:
            write_produto_page(slug)

    vinculados_ebooks, avisos_ebooks = sincronizar_ebooks_pdf(by_slug, DRY_RUN)
    for produto in by_slug.values():
        produto.setdefault("arquivo_ebook", None)

    result = sorted(by_slug.values(), key=lambda p: p["nome"])

    if not DRY_RUN:
        DATA_FILE.parent.mkdir(parents=True, exist_ok=True)
        DATA_FILE.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    orphaned = [p["slug"] for p in existing if p["slug"] not in seen_slugs]

    print(f"{'[DRY-RUN] ' if DRY_RUN else ''}Sincronização concluída.")
    print(f"  Adicionados: {added or '-'}")
    print(f"  Atualizados: {updated or '-'}")
    if orphaned:
        print(f"  Presentes no site mas sem arquivo/pasta correspondente em produtos_ts_site/ (não removidos): {orphaned}")
    if skipped:
        print("  Arquivos ignorados (não reconhecidos):")
        for s in skipped:
            print(f"    - {s}")
    if errors:
        print("  Avisos/erros:")
        for e in errors:
            print(f"    - {e}")
    if vinculados_ebooks:
        print("  PDFs de e-book vinculados (movidos pra fora do repo, renomeados):")
        for v in vinculados_ebooks:
            print(f"    - {v}")
    if avisos_ebooks:
        print("  Avisos sobre PDFs de e-book:")
        for a in avisos_ebooks:
            print(f"    - {a}")

    return 0


if __name__ == "__main__":
    sys.exit(main())
