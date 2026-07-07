#!/usr/bin/env python3
"""Erstellt seed/layout-sets/schlicht/ aus seed/templates/ (ohne PHP)."""
import json
import re
import shutil
import secrets
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SEED_NAME = "schlicht"
TARGET = ROOT / "seed" / "layout-sets" / SEED_NAME
TEMPLATE_ORDER = [
    "77da9b45b679c85b",
    "6f46aa3644fc25d5",
    "60c1046a44ddcd8f",
    "4a38cb88d0722919",
    "1040b5e23d947b36",
    "0691915389a265ac",
    "fc35b144beff7c31",
]

ROLE_LABELS = {
    "document_title": "Titel",
    "subtitle": "Untertitel",
    "heading1": "Überschrift 1",
    "heading2": "Überschrift 2",
    "heading3": "Überschrift 3",
    "heading4": "Überschrift 4",
    "heading5": "Überschrift 5",
}


def layout_key(title: str) -> str:
    key = re.sub(r"[^a-z0-9]+", "_", title.lower().strip())
    return key.strip("_") or "layout"


def normalize(text: str) -> str:
    text = text.strip().strip("«»\"'")
    text = re.sub(r"\s+", " ", text)
    return text.lower()


def infer_role(text: str):
    norm = normalize(text)
    if norm in ("titel", "title"):
        return "document_title"
    if norm in ("untertitel", "subtitle"):
        return "subtitle"
    for role, label in ROLE_LABELS.items():
        if norm == label.lower():
            return role
    m = re.match(r"^überschrift\s*(\d)$", norm)
    if m:
        return f"heading{m.group(1)}"
    return None


def prepare_slide(slide: dict, title: str) -> dict:
    slide = json.loads(json.dumps(slide))
    slide["id"] = secrets.token_hex(2)
    slide["layoutKey"] = layout_key(title)
    slide["label"] = title
    assigned = {}
    objects = []
    for obj in slide.get("objects", []):
        if obj.get("type") != "text":
            objects.append(obj)
            continue
        role = obj.get("setRole") or obj.get("logosRole") or ""
        if not role:
            role = infer_role(obj.get("text", "")) or ""
        if role and role not in assigned:
            obj = dict(obj)
            obj["setRole"] = role
            obj.pop("logosRole", None)
            assigned[role] = True
            objects.append(obj)
        elif role:
            continue
        else:
            objects.append(obj)
    slide["objects"] = objects
    return slide


def main():
    if TARGET.exists():
        shutil.rmtree(TARGET)
    (TARGET / "assets").mkdir(parents=True)

    slides = []
    for tid in TEMPLATE_ORDER:
        src = ROOT / "seed" / "templates" / tid
        meta = json.loads((src / "meta.json").read_text(encoding="utf-8"))
        tpl = json.loads((src / "slides.json").read_text(encoding="utf-8"))
        slide = prepare_slide(tpl["slides"][0], meta["title"])
        slides.append(slide)
        assets = src / "assets"
        if assets.is_dir():
            for f in assets.iterdir():
                if f.is_file():
                    shutil.copy2(f, TARGET / "assets" / f.name)

    seed_meta = {
        "title": "Schlicht",
        "width": 1920,
        "height": 1080,
        "template_order": 0,
        "is_layout_set": True,
        "template_shared": True,
        "default_layout_set": True,
        "logosNotesOrder": [
            "document_title",
            "heading1",
            "heading2",
            "heading3",
            "heading4",
            "heading5",
            "normal",
            "list_item",
            "lighttext",
            "prompt",
            "scripture_block",
            "scripture_inline",
        ],
        "elementZones": {
            "slides": ["document_title", "heading1", "heading2", "heading3", "heading4", "heading5", "normal", "list_item"],
            "footer": ["scripture_ref", "scripture_verse"],
            "custom": ["lighttext", "prompt"],
            "unused": ["meta"],
        },
        "safe_margin": 100,
    }

    (TARGET / "meta.json").write_text(
        json.dumps(seed_meta, ensure_ascii=False, indent=4) + "\n",
        encoding="utf-8",
    )
    (TARGET / "slides.json").write_text(
        json.dumps({"slides": slides}, ensure_ascii=False, indent=4) + "\n",
        encoding="utf-8",
    )
    print(f"Erstellt: seed/layout-sets/{SEED_NAME}/ mit {len(slides)} Layout-Folien.")


if __name__ == "__main__":
    main()
