#!/usr/bin/env python3
"""Fügt Mobile-Fernsteuerung-Slides in seed/feature-tour/*/slides.json ein."""
import json
from copy import deepcopy
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SEED = ROOT / "seed" / "feature-tour"

STRINGS = {
    "de": {
        "remote_title": "Mobile Fernsteuerung",
        "remote_body": "**Smartphone** als Fernbedienung über **HTTPS** — kein gemeinsames WLAN nötig.\n\nTabs: Folie, Vorschau, Uhr, Timer, Laser · **Fortschrittsbalken** am Handy.\n\n**QR-Code** im Present-Menü oder Link teilen.",
        "remote_caption": "Remote-Oberfläche auf dem Smartphone (present_remote.php)",
    },
    "en": {
        "remote_title": "Mobile remote control",
        "remote_body": "Use your **phone** as a remote over **HTTPS** — no shared Wi‑Fi required.\n\nTabs: slide, preview, clock, timer, laser · **progress bar** on the phone.\n\n**QR code** in the present menu or share the remote link.",
        "remote_caption": "Remote UI on a smartphone (present_remote.php)",
    },
    "fr": {
        "remote_title": "Télécommande mobile",
        "remote_body": "**Smartphone** comme télécommande via **HTTPS** — pas besoin du même Wi‑Fi.\n\nOnglets : diapo, aperçu, horloge, minuterie, laser · **barre de progression** sur le téléphone.\n\n**QR code** dans le menu présentation ou lien à partager.",
        "remote_caption": "Interface remote sur smartphone (present_remote.php)",
    },
    "it": {
        "remote_title": "Telecomando mobile",
        "remote_body": "**Smartphone** come telecomando via **HTTPS** — nessun Wi‑Fi condiviso necessario.\n\nTab: slide, anteprima, orologio, timer, laser · **barra di avanzamento** sul telefono.\n\n**QR code** nel menu presentazione o link da condividere.",
        "remote_caption": "Interfaccia remote su smartphone (present_remote.php)",
    },
    "rm": {
        "remote_title": "Telecumanda mobil",
        "remote_body": "**Smartphone** sco telecumanda via **HTTPS** — nagin WLAN commun necessari.\n\nTabs: slide, previsa, ura, timer, laser · **barra da progress** sin telefon.\n\n**QR code** en il menu presentaziun u link da divider.",
        "remote_caption": "Interface remote sin smartphone (present_remote.php)",
    },
}


def heading(oid: str, text: str) -> dict:
    return {
        "id": oid,
        "type": "text",
        "rotation": 0,
        "opacity": 1,
        "animType": "fade-in",
        "animOrder": 1,
        "animAutoAdvance": 0,
        "animDuration": 800,
        "x": 100,
        "y": 60,
        "w": 1720,
        "h": 80,
        "text": text,
        "fontFamily": "Open Sans",
        "fontSize": 56,
        "fontWeight": "bold",
        "italic": False,
        "underline": False,
        "strikethrough": False,
        "uppercase": False,
        "smallCaps": False,
        "animPerLine": False,
        "color": "#ffffff",
        "align": "left",
    }


def body_text(oid: str, text: str) -> dict:
    return {
        "id": oid,
        "type": "text",
        "rotation": 0,
        "opacity": 1,
        "animType": "fade-up",
        "animOrder": 2,
        "animAutoAdvance": 0,
        "animDuration": 1000,
        "x": 100,
        "y": 180,
        "w": 900,
        "h": 500,
        "text": text,
        "fontFamily": "Open Sans",
        "fontSize": 34,
        "fontWeight": "normal",
        "italic": False,
        "underline": False,
        "strikethrough": False,
        "uppercase": False,
        "smallCaps": False,
        "animPerLine": True,
        "color": "#ffffff",
        "align": "left",
    }


def caption(oid: str, text: str) -> dict:
    return {
        "id": oid,
        "type": "text",
        "rotation": 0,
        "opacity": 1,
        "animType": "fade-up",
        "animOrder": 3,
        "animAutoAdvance": 0,
        "animDuration": 0,
        "x": 100,
        "y": 920,
        "w": 1720,
        "h": 50,
        "text": text,
        "fontFamily": "Open Sans",
        "fontSize": 24,
        "fontWeight": "normal",
        "italic": False,
        "underline": False,
        "strikethrough": False,
        "uppercase": False,
        "smallCaps": False,
        "animPerLine": False,
        "color": "#8b92a3",
        "align": "center",
    }


def screenshot_slide(title: str, caption_text: str) -> dict:
    return {
        "id": "remote",
        "background": {"type": "color", "value": "#15181e"},
        "transition": "slide",
        "autoAdvance": 0,
        "notes": "",
        "objects": [
            heading("remoteh", title),
            {
                "id": "remoteimg",
                "type": "image",
                "rotation": 0,
                "opacity": 1,
                "animType": "fade-in",
                "animOrder": 2,
                "animAutoAdvance": 0,
                "animDuration": 1000,
                "x": 160,
                "y": 160,
                "w": 1600,
                "h": 720,
                "src": "asset.php?id=feature0&file=ui-remote.png",
                "stroke": "#61a8e0",
                "strokeWidth": 2,
            },
            caption("remotec", caption_text),
        ],
    }


def text_slide(title: str, body: str) -> dict:
    return {
        "id": "remotetxt",
        "background": {"type": "color", "value": "#111318"},
        "transition": "fade",
        "autoAdvance": 0,
        "notes": "",
        "objects": [heading("rm1", title), body_text("rm1b", body)],
    }


def patch_lang(code: str) -> int:
    path = SEED / code / "slides.json"
    data = json.loads(path.read_text(encoding="utf-8"))
    slides = data["slides"]
    if any(s.get("id") == "remote" for s in slides):
        print(f"  {code}: skip (bereits vorhanden)")
        return len(slides)

    s = STRINGS[code]
    insert = [screenshot_slide(s["remote_title"], s["remote_caption"]), text_slide(s["remote_title"], s["remote_body"])]

    for i, slide in enumerate(slides):
        if slide.get("id") == "export":
            slides[i:i] = insert
            break
    else:
        slides.extend(insert)

    path.write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
    print(f"  {code}: {len(slides)} slides")
    return len(slides)


def main() -> None:
    for code in STRINGS:
        patch_lang(code)
    print("Fertig.")


if __name__ == "__main__":
    main()
