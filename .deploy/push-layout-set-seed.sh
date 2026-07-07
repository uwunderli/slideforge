#!/usr/bin/env bash
# Lädt seed/layout-sets/<name>/ auf den Prod-Server (data/presentations/).
# Schreibt Dateien per PHP (www-data), nicht per SFTP — sonst keine Schreibrechte im Editor.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SEED_NAME="${1:-schlicht}"

ENV_FILE="${DEPLOY_ENV:-$SCRIPT_DIR/ssh.env}"
# shellcheck source=/dev/null
source "$ENV_FILE"

REMOTE="${SFTP_REMOTE:-sftp://${SSH_HOST}:${SSH_PORT:-22}}"
AUTH="${SSH_USER}:${SSH_PASS}"
PROD_URL="${PROD_URL:-https://slides.bkbiel.ch}"
SEED_DIR="$ROOT/seed/layout-sets/$SEED_NAME"

if [[ ! -f "$SEED_DIR/meta.json" || ! -f "$SEED_DIR/slides.json" ]]; then
  echo "Seed nicht gefunden: seed/layout-sets/$SEED_NAME/" >&2
  exit 1
fi

curl_sftp() {
  SSH_AUTH_SOCK= curl -sS --ftp-method nocwd --ftp-create-dirs --user "$AUTH" "$@"
}

sftp_rm_presentation() {
  local id="$1"
  local base="data/presentations/${id}"
  for f in meta.json slides.json acl.json; do
    curl_sftp -Q "rm ${base}/${f}" "${REMOTE}/" >/dev/null 2>&1 || true
  done
  if mapfile -t ASSETS < <(curl_sftp --list-only "${REMOTE}/${base}/assets/" 2>/dev/null | grep -v '^\.\.?$' || true); then
    for asset in "${ASSETS[@]}"; do
      [[ -z "$asset" || "$asset" == "." || "$asset" == ".." ]] && continue
      curl_sftp -Q "rm ${base}/assets/${asset}" "${REMOTE}/" >/dev/null 2>&1 || true
    done
  fi
  curl_sftp -Q "rmdir ${base}/assets" "${REMOTE}/" >/dev/null 2>&1 || true
  curl_sftp -Q "rmdir ${base}" "${REMOTE}/" >/dev/null 2>&1 || true
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
  echo "Alte Dateien per SFTP entfernen …"
  sftp_rm_presentation "$TARGET_ID"
else
  TARGET_ID="$(python3 -c 'import secrets; print(secrets.token_hex(8))')"
  echo "Neues Set anlegen: $TARGET_ID"
fi

PACK="$(mktemp -d)"
ZIP="$(mktemp --suffix=.zip)"
TOKEN="$(python3 -c 'import secrets; print(secrets.token_hex(16))')"
REMOTE_SCRIPT="public_html/_install_layout_set_$$.php"
REMOTE_BASENAME="_install_layout_set_$$.php"
TMPPHP="$(mktemp)"
trap 'rm -rf "$PACK" "$ZIP" "$TMPPHP"' EXIT

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

python3 - "$PACK" "$ZIP" <<'PY'
import sys, zipfile
from pathlib import Path
pack, zpath = Path(sys.argv[1]), sys.argv[2]
with zipfile.ZipFile(zpath, 'w', zipfile.ZIP_DEFLATED) as zf:
    for path in pack.rglob('*'):
        if path.is_file():
            zf.write(path, path.relative_to(pack).as_posix())
PY

STAGING_ZIP="data/cache/layout-push-${TOKEN}.zip"
echo "Installiere per PHP (${TARGET_ID}) …"
curl_sftp -T "$ZIP" "${REMOTE}/${STAGING_ZIP}"

cat > "$TMPPHP" <<PHP
<?php
require __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
\$token = \$_POST['token'] ?? '';
if (!hash_equals('${TOKEN}', \$token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}
\$id = trim(\$_POST['id'] ?? '');
\$zipPath = DATA_PATH . '/cache/layout-push-${TOKEN}.zip';
if (\$id === '' || !preg_match('/^[a-f0-9]{16}\$/', \$id) || !is_file(\$zipPath)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_request']);
    exit;
}
\$dir = Presentation::dir(\$id);
if (is_dir(\$dir)) {
    Presentation::delete(\$id);
}
mkdir(\$dir, 0770, true);
mkdir(\$dir . '/assets', 0770, true);
\$zip = new ZipArchive();
if (\$zip->open(\$zipPath) !== true) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'zip_open_failed']);
    exit;
}
\$zip->extractTo(\$dir);
\$zip->close();
@unlink(\$zipPath);
echo json_encode(['ok' => true, 'id' => \$id, 'dir' => \$dir], JSON_UNESCAPED_UNICODE);
PHP

cleanup_remote() {
  curl_sftp -Q "RM /${REMOTE_SCRIPT}" "${REMOTE}/" 2>/dev/null || true
  curl_sftp -Q "rm ${STAGING_ZIP}" "${REMOTE}/" 2>/dev/null || true
}
trap cleanup_remote EXIT

curl_sftp -T "$TMPPHP" "${REMOTE}/${REMOTE_SCRIPT}"
BODY="$(curl -sS -X POST "${PROD_URL}/${REMOTE_BASENAME}" \
  --data-urlencode "token=${TOKEN}" \
  --data-urlencode "id=${TARGET_ID}")"
echo "$BODY" | python3 -m json.tool

OK="$(echo "$BODY" | python3 -c "import json,sys; print('yes' if json.load(sys.stdin).get('ok') else 'no')" 2>/dev/null || echo no)"
if [[ "$OK" != "yes" ]]; then
  echo "FEHLER: PHP-Installation fehlgeschlagen." >&2
  exit 1
fi

echo "Fertig: Folien-Set „${TITLE}“ auf ${SSH_HOST} (${TARGET_ID}), freigegeben für alle."
