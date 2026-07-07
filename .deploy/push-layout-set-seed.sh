#!/usr/bin/env bash
# Lädt seed/layout-sets/<name>/ auf den Prod-Server (data/presentations/).
# Ersetzt ein vorhandenes freigegebenes Set gleichen Titels.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SEED_NAME="${1:-schlicht}"

ENV_FILE="${DEPLOY_ENV:-$SCRIPT_DIR/ssh.env}"
# shellcheck source=/dev/null
source "$ENV_FILE"

REMOTE="${SFTP_REMOTE:-sftp://${SSH_HOST}:${SSH_PORT:-22}}"
AUTH="${SSH_USER}:${SSH_PASS}"
SEED_DIR="$ROOT/seed/layout-sets/$SEED_NAME"

if [[ ! -f "$SEED_DIR/meta.json" || ! -f "$SEED_DIR/slides.json" ]]; then
  echo "Seed nicht gefunden: seed/layout-sets/$SEED_NAME/" >&2
  exit 1
fi

curl_sftp() {
  SSH_AUTH_SOCK= curl -sS --ftp-method nocwd --ftp-create-dirs --user "$AUTH" "$@"
}

echo "Admin auf dem Server ermitteln …"
USERS_TMP="$(mktemp)"
curl_sftp -f "${REMOTE}/data/users.json" -o "$USERS_TMP"
ADMIN_ID="$(python3 - "$USERS_TMP" <<'PY'
import json, sys
users = json.load(open(sys.argv[1]))
for u in users:
    if u.get("role") == "admin":
        print(u["id"])
        break
PY
)"
rm -f "$USERS_TMP"
if [[ -z "${ADMIN_ID:-}" ]]; then
  echo "Kein Admin-Benutzer auf dem Server gefunden." >&2
  exit 1
fi

TITLE="$(python3 -c "import json; print(json.load(open('$SEED_DIR/meta.json')).get('title',''))")"
echo "Suche vorhandenes Set „${TITLE}“ …"

EXISTING_ID=""
mapfile -t REMOTE_IDS < <(curl_sftp --list-only "${REMOTE}/data/presentations/" 2>/dev/null | grep -E '^[a-f0-9]+$' || true)
for id in "${REMOTE_IDS[@]}"; do
  META_TMP="$(mktemp)"
  if curl_sftp -f "${REMOTE}/data/presentations/${id}/meta.json" -o "$META_TMP" 2>/dev/null; then
    MATCH="$(python3 - "$META_TMP" "$TITLE" <<'PY'
import json, sys
m = json.load(open(sys.argv[1]))
title = sys.argv[2]
if m.get("is_layout_set") and (m.get("title") or "").strip() == title.strip():
    print("yes")
PY
)"
    if [[ "$MATCH" == "yes" ]]; then
      EXISTING_ID="$id"
    fi
  fi
  rm -f "$META_TMP"
  [[ -n "$EXISTING_ID" ]] && break
done

if [[ -n "$EXISTING_ID" ]]; then
  TARGET_ID="$EXISTING_ID"
  echo "Aktualisiere vorhandenes Set: $TARGET_ID"
else
  TARGET_ID="$(python3 -c 'import secrets; print(secrets.token_hex(8))')"
  echo "Neues Set anlegen: $TARGET_ID"
fi

PACK="$(mktemp -d)"
trap 'rm -rf "$PACK"' EXIT

python3 - "$SEED_DIR" "$PACK" "$TARGET_ID" "$ADMIN_ID" <<'PY'
import json, sys, shutil
from datetime import datetime, timezone
from pathlib import Path

seed_dir, pack, target_id, admin_id = sys.argv[1:5]
seed_meta = json.load(open(Path(seed_dir) / "meta.json", encoding="utf-8"))
slides = json.load(open(Path(seed_dir) / "slides.json", encoding="utf-8"))
now = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S+00:00")

raw = json.dumps(slides, ensure_ascii=False)
raw = raw.replace(f"asset.php?id={Path(seed_dir).name}&", f"asset.php?id={target_id}&")
slides = json.loads(raw)

meta = {
    "id": target_id,
    "owner_id": admin_id,
    "title": seed_meta.get("title", "Folien-Set"),
    "width": int(seed_meta.get("width", 1920)),
    "height": int(seed_meta.get("height", 1080)),
    "presentation_duration": 30,
    "safe_margin": int(seed_meta.get("safe_margin", 100)),
    "timebar_stops": [
        {"pct": 0, "color": "#4caf6b"},
        {"pct": 60, "color": "#d9c23a"},
        {"pct": 90, "color": "#dd8a2e"},
        {"pct": 100, "color": "#d9483a"},
    ],
    "show_progress": True,
    "show_controls": False,
    "is_template": True,
    "template_shared": True,
    "template_order": seed_meta.get("template_order", 0),
    "archived": False,
    "created_at": now,
    "updated_at": now,
    "is_layout_set": True,
    "default_layout_set": bool(seed_meta.get("default_layout_set", False)),
}
for key in ("logosLayoutMap", "logosLayoutSlideIds", "logosNotesOrder", "elementZones", "elementTextLinks"):
    if key in seed_meta:
        meta[key] = seed_meta[key]

acl = {
    "shares": [],
    "public": {"enabled": False, "token": None, "permission": "view"},
}

out = Path(pack)
(out / "meta.json").write_text(json.dumps(meta, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
(out / "slides.json").write_text(json.dumps(slides, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
(out / "acl.json").write_text(json.dumps(acl, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
assets = Path(seed_dir) / "assets"
if assets.is_dir():
    shutil.copytree(assets, out / "assets")
else:
    (out / "assets").mkdir()
PY

REMOTE_BASE="data/presentations/${TARGET_ID}"
for file in meta.json slides.json acl.json; do
  curl_sftp -T "$PACK/$file" "${REMOTE}/${REMOTE_BASE}/${file}"
done
if [[ -d "$PACK/assets" ]]; then
  for asset in "$PACK/assets"/*; do
    [[ -f "$asset" ]] || continue
    curl_sftp -T "$asset" "${REMOTE}/${REMOTE_BASE}/assets/$(basename "$asset")"
  done
fi

echo "Fertig: Folien-Set „${TITLE}“ auf ${SSH_HOST} (${TARGET_ID}), freigegeben für alle."
