#!/usr/bin/env python3
"""Screenshots für seed/feature-tour/assets/ von der Live-Demo."""
import re
import subprocess
import sys
import tempfile
import urllib.parse
import urllib.request
from http.cookiejar import CookieJar
from pathlib import Path

try:
    from PIL import Image
except ImportError:
    Image = None  # type: ignore

BASE = "https://slideforge.service7.ch"
OUT = Path(__file__).resolve().parent.parent / "seed" / "feature-tour" / "assets"
OUT.mkdir(parents=True, exist_ok=True)

DEMO_USER = "admin"
DEMO_PASS = "admin"

SHOTS = [
    ("ui-login.png", f"{BASE}/login.php", None),
    ("ui-dashboard.png", f"{BASE}/index.php", "admin"),
    ("ui-editor.png", f"{BASE}/editor.php", "admin"),
    ("ui-templates.png", f"{BASE}/templates.php", "admin"),
    ("ui-present.png", f"{BASE}/view.php?token=slideforge-tour", None),
]

REMOTE_OUT = "ui-remote.png"
REMOTE_VIEWPORT = (390, 844)
CANVAS_SIZE = (1920, 1080)


def demo_login() -> urllib.request.OpenerDirector:
    cj = CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
    data = urllib.parse.urlencode({"username": DEMO_USER, "password": DEMO_PASS}).encode()
    req = urllib.request.Request(
        f"{BASE}/login.php",
        data=data,
        method="POST",
        headers={"Content-Type": "application/x-www-form-urlencoded"},
    )
    opener.open(req, timeout=30)
    return opener


def feature_tour_id(opener: urllib.request.OpenerDirector) -> str | None:
    html = opener.open(f"{BASE}/index.php", timeout=30).read().decode("utf-8", "replace")
    for pat in (
        r'present_remote\.php\?id=([a-f0-9]+)',
        r'present\.php\?id=([a-f0-9]+)[^"\']*SlideForge Feature Tour',
        r'editor\.php\?id=([a-f0-9]+)[^"\']*Feature Tour',
        r'editor\.php\?id=([a-f0-9]+)[^"\']*Feature-Tour',
    ):
        m = re.search(pat, html, re.I)
        if m:
            return m.group(1)
    # Fallback: erste Präsentation mit present_remote-Link
    m = re.search(r'present_remote\.php\?id=([a-f0-9]+)', html)
    return m.group(1) if m else None


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
            r = subprocess.run(cmd, capture_output=True, text=True, timeout=60)
            return r.returncode == 0 and out.is_file() and out.stat().st_size > 5000
        except (FileNotFoundError, subprocess.TimeoutExpired):
            return False


def composite_mobile_shot(mobile: Path, out: Path) -> bool:
    if Image is None:
        return mobile.is_file() and mobile.stat().st_size > 5000
    try:
        img = Image.open(mobile).convert("RGB")
        canvas = Image.new("RGB", CANVAS_SIZE, (21, 24, 30))
        scale = min(
            (CANVAS_SIZE[0] - 160) / img.width,
            (CANVAS_SIZE[1] - 120) / img.height,
            1.0,
        )
        nw, nh = int(img.width * scale), int(img.height * scale)
        img = img.resize((nw, nh), Image.Resampling.LANCZOS)
        x = (CANVAS_SIZE[0] - nw) // 2
        y = (CANVAS_SIZE[1] - nh) // 2
        canvas.paste(img, (x, y))
        canvas.save(out, "PNG", optimize=True)
        return out.is_file() and out.stat().st_size > 5000
    except OSError:
        return False


def capture_remote_playwright() -> bool:
    try:
        from playwright.sync_api import sync_playwright
    except ImportError:
        return False

    dest = OUT / REMOTE_OUT
    print(f"  ui-remote.png …", end=" ", flush=True)
    try:
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True)
            ctx = browser.new_context(
                viewport={"width": REMOTE_VIEWPORT[0], "height": REMOTE_VIEWPORT[1]},
                user_agent=(
                    "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) "
                    "AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1"
                ),
            )
            page = ctx.new_page()
            page.goto(f"{BASE}/login.php", wait_until="domcontentloaded", timeout=60000)
            page.fill('input[name="username"]', DEMO_USER)
            page.fill('input[name="password"]', DEMO_PASS)
            page.click('button[type="submit"]')
            page.wait_for_url("**/index.php**", timeout=30000)
            html = page.content()
            m = re.search(r'present_remote\.php\?id=([a-f0-9]+)', html)
            if not m:
                browser.close()
                print("fehlgeschlagen (keine Präsentations-ID)")
                return False
            pid = m.group(1)
            page.goto(
                f"{BASE}/present_remote.php?id={pid}",
                wait_until="domcontentloaded",
                timeout=60000,
            )
            page.wait_for_selector(".present-remote-wrap", timeout=30000)
            page.add_style_tag(content=".demo-banner { display: none !important; }")
            page.wait_for_timeout(2000)
            with tempfile.TemporaryDirectory() as td:
                raw = Path(td) / "mobile.png"
                page.locator(".present-remote-wrap").screenshot(path=str(raw))
                browser.close()
                if not composite_mobile_shot(raw, dest):
                    print("fehlgeschlagen (composite)")
                    return False
            print(f"OK ({dest.stat().st_size // 1024} KB, id={pid[:8]}…)")
            return True
    except Exception as e:
        print(f"fehlgeschlagen ({e})")
        return False


def capture_remote(opener: urllib.request.OpenerDirector) -> bool:
    if capture_remote_playwright():
        return True
    pid = feature_tour_id(opener)
    if not pid:
        print("  ui-remote.png … fehlgeschlagen (keine Präsentations-ID)")
        return False
    url = f"{BASE}/present_remote.php?id={pid}"
    dest = OUT / REMOTE_OUT
    with tempfile.TemporaryDirectory() as td:
        raw = Path(td) / "mobile.png"
        print(f"  ui-remote.png … (id={pid[:8]}…)", end=" ", flush=True)
        if not firefox_shot(url, raw, *REMOTE_VIEWPORT):
            print("fehlgeschlagen (firefox)")
            return False
        if not composite_mobile_shot(raw, dest):
            print("fehlgeschlagen (composite)")
            return False
        print(f"OK ({dest.stat().st_size // 1024} KB)")
        return True


def main() -> int:
    ok = 0
    only = sys.argv[1:] if len(sys.argv) > 1 else None

    def want(name: str) -> bool:
        return only is None or name in only

    for name, url, _ in SHOTS:
        if not want(name):
            continue
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

    if want(REMOTE_OUT):
        remote_dest = OUT / REMOTE_OUT
        if remote_dest.is_file() and remote_dest.stat().st_size > 5000 and only is None:
            print(f"  ui-remote.png … skip (vorhanden)")
            ok += 1
        else:
            try:
                if capture_remote_playwright():
                    ok += 1
                else:
                    opener = demo_login()
                    if capture_remote(opener):
                        ok += 1
            except OSError as e:
                print(f"  ui-remote.png … fehlgeschlagen ({e})")

    total = (len(SHOTS) if only is None else len(only))
    print(f"\n{ok}/{total} Screenshots in {OUT}")
    return 0 if ok >= 1 else 1


if __name__ == "__main__":
    sys.exit(main())
