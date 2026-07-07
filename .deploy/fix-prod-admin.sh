#!/usr/bin/env bash
# Setzt role=admin für einen Benutzer auf Prod.
# Schreibt sowohl per SFTP (data/users.json) als auch per PHP (gleicher Pfad wie die App).
# Usage: ./.deploy/fix-prod-admin.sh <username>
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
USERNAME="${1:?Usage: $0 <username>}"

ENV_FILE="${DEPLOY_ENV:-$SCRIPT_DIR/ssh.env}"
# shellcheck source=/dev/null
source "$ENV_FILE"

REMOTE="${SFTP_REMOTE:-sftp://${SSH_HOST}:${SSH_PORT:-22}}"
AUTH="${SSH_USER}:${SSH_PASS}"
PROD_URL="${PROD_URL:-https://slides.bkbiel.ch}"
TOKEN="$(python3 -c 'import secrets; print(secrets.token_hex(16))')"
REMOTE_SCRIPT="public_html/_fix_admin_$$.php"
REMOTE_BASENAME="_fix_admin_$$.php"
TMPPHP="$(mktemp)"
TMP="$(mktemp)"
trap 'rm -f "$TMP" "$TMPPHP"' EXIT

curl_sftp() {
  SSH_AUTH_SOCK= curl -sS --ftp-method nocwd --ftp-create-dirs --user "$AUTH" "$@"
}

cleanup_remote() {
  curl_sftp -Q "RM /${REMOTE_SCRIPT}" "${REMOTE}/" 2>/dev/null || true
}
trap cleanup_remote EXIT

echo "1/2 SFTP: users.json …"
curl_sftp -f "${REMOTE}/data/users.json" -o "$TMP"

python3 - "$TMP" "$USERNAME" <<'PY'
import json, sys
path, username = sys.argv[1], sys.argv[2]
users = json.load(open(path, encoding="utf-8"))
found = False
for u in users:
    if (u.get("username") or "").lower() == username.lower():
        u["role"] = "admin"
        found = True
        print(f"OK: {username} -> admin")
        break
if not found:
    print(f"FEHLER: Benutzer „{username}“ nicht gefunden.", file=sys.stderr)
    sys.exit(1)
json.dump(users, open(path, "w", encoding="utf-8"), ensure_ascii=False, indent=4)
PY

curl_sftp -T "$TMP" "${REMOTE}/data/users.json"

echo "2/2 PHP: Auth::setRole auf Prod …"
cat > "$TMPPHP" <<PHP
<?php
require __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
\$token = \$_GET['token'] ?? '';
if (!hash_equals('${TOKEN}', \$token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}
\$user = Auth::findByUsername('${USERNAME}');
if (!\$user) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'user_not_found']);
    exit;
}
Auth::setRole(\$user['id'], 'admin');
\$updated = Auth::findById(\$user['id']);
echo json_encode([
    'ok' => true,
    'users_file' => USERS_FILE,
    'username' => \$updated['username'] ?? null,
    'role' => \$updated['role'] ?? null,
], JSON_UNESCAPED_UNICODE);
PHP

curl_sftp -T "$TMPPHP" "${REMOTE}/${REMOTE_SCRIPT}"
BODY="$(curl -sS "${PROD_URL}/${REMOTE_BASENAME}?token=${TOKEN}")"
echo "$BODY" | python3 -m json.tool

ROLE="$(echo "$BODY" | python3 -c "import json,sys; print(json.load(sys.stdin).get('role',''))" 2>/dev/null || true)"
if [[ "$ROLE" != "admin" ]]; then
  echo "FEHLER: PHP-Fix fehlgeschlagen." >&2
  exit 1
fi

echo ""
echo "Fertig: „${USERNAME}“ ist auf ${SSH_HOST} Admin."
echo "Bitte einmal abmelden und neu anmelden (oder Browser-Cache leeren)."
