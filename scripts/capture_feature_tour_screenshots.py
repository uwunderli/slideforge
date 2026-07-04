#!/usr/bin/env python3
"""Screenshots für seed/feature-tour/assets/ von der Live-Demo."""
import subprocess
import sys
import tempfile
from pathlib import Path

BASE = "https://slideforge.service7.ch"
OUT = Path(__file__).resolve().parent.parent / "seed" / "feature-tour" / "assets"
OUT.mkdir(parents=True, exist_ok=True)

SHOTS = [
    ("ui-login.png", f"{BASE}/login.php", None),
    ("ui-dashboard.png", f"{BASE}/index.php", "admin"),
    ("ui-editor.png", f"{BASE}/editor.php", "admin"),
    ("ui-templates.png", f"{BASE}/templates.php", "admin"),
    ("ui-present.png", f"{BASE}/view.php?token=slideforge-tour", None),
]


def firefox_shot(url: str, out: Path, w: int = 1920, h: int = 1080) -> bool:
    with tempfile.TemporaryDirectory() as td:
        profile = Path(td) / "profile"
        cmd = [
            "firefox",
            "--headless",
            "--screenshot", str(out),
            "--window-size", f"{w},{h}",
            "--profile", str(profile),
            url,
        ]
        try:
            r = subprocess.run(cmd, capture_output=True, text=True, timeout=45)
            return r.returncode == 0 and out.is_file() and out.stat().st_size > 5000
        except (FileNotFoundError, subprocess.TimeoutExpired):
            return False


def main() -> int:
    ok = 0
    for name, url, _ in SHOTS:
        dest = OUT / name
        print(f"  {name} …", end=" ", flush=True)
        if dest.is_file() and dest.stat().st_size > 5000 and name == "ui-login.png":
            print("skip (vorhanden)")
            ok += 1
            continue
        if firefox_shot(url, dest):
            print(f"OK ({dest.stat().st_size // 1024} KB)")
            ok += 1
        else:
            print("fehlgeschlagen")
    print(f"\n{ok}/{len(SHOTS)} Screenshots in {OUT}")
    return 0 if ok >= 2 else 1


if __name__ == "__main__":
    sys.exit(main())
