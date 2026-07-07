#!/usr/bin/env python3
"""Prüft seed/layout-sets/schlicht/ für Neuinstallationen."""
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SEED = ROOT / "seed" / "layout-sets" / "schlicht"
REQUIRED_META = {
    "title", "width", "height", "template_order", "is_layout_set",
    "template_shared", "default_layout_set", "elementZones", "safe_margin",
}
REQUIRED_ZONES = {"slides", "footer", "custom", "unused"}


def fail(msg: str) -> None:
    print(f"FAIL: {msg}")
    sys.exit(1)


def main() -> None:
    meta_path = SEED / "meta.json"
    slides_path = SEED / "slides.json"
    if not meta_path.is_file() or not slides_path.is_file():
        fail(f"Seed fehlt: {SEED}")

    meta = json.loads(meta_path.read_text(encoding="utf-8"))
    slides = json.loads(slides_path.read_text(encoding="utf-8")).get("slides", [])

    missing_meta = REQUIRED_META - set(meta.keys())
    if missing_meta:
        fail(f"meta.json fehlt: {', '.join(sorted(missing_meta))}")
    if not meta.get("is_layout_set"):
        fail("is_layout_set muss true sein")
    if not meta.get("template_shared"):
        fail("template_shared muss true sein")
    if not meta.get("default_layout_set"):
        fail("default_layout_set muss true sein")
    if meta.get("title", "").strip().lower() not in ("schlicht",):
        fail(f"Unerwarteter Titel: {meta.get('title')!r}")

    zones = meta.get("elementZones", {})
    if not isinstance(zones, dict) or REQUIRED_ZONES - set(zones.keys()):
        fail("elementZones unvollständig")

    if len(slides) < 1:
        fail("slides.json ist leer")

    for i, slide in enumerate(slides, 1):
        if not slide.get("layoutKey"):
            fail(f"Folie {i}: layoutKey fehlt")
        if not slide.get("label"):
            fail(f"Folie {i}: label fehlt")
        if not slide.get("id"):
            fail(f"Folie {i}: id fehlt")

    print(f"OK: Schlicht-Seed mit {len(slides)} Layout-Folien, default_layout_set=true, template_shared=true")


if __name__ == "__main__":
    main()
