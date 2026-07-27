#!/usr/bin/env bash
# Prüft auf Prod, welche Rolle PHP für einen Benutzer sieht.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
USERNAME="${1:-urs}"
ENV_FILE="${DEPLOY_ENV:-$SCRIPT_DIR/ssh.env}"
# shellcheck source=/dev/null
source "$ENV_FILE"
# shellcheck source=ssh-common.sh
source "$SCRIPT_DIR/ssh-common.sh"

TOKEN="$(python3 -c 'import secrets; print(secrets.token_hex(16))')"
REMOTE_SCRIPT="public_html/_role_check_$$.php"
REMOTE_BASENAME="_role_check_$$.php"
PROD_URL="${PROD_URL:-https://slides.bkbiel.ch}"
TMPPHP="$(mktemp)"

cleanup() {
  deploy_ssh "rm -f '$(deploy_remote_path "$REMOTE_SCRIPT")'" 2>/dev/null || true
  rm -f "$TMPPHP"
}
trap cleanup EXIT

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
\$urs = Auth::findByUsername('${USERNAME}');
echo json_encode([
    'ok' => true,
    'users_file' => USERS_FILE,
    'users_file_exists' => file_exists(USERS_FILE),
    'users_file_readable' => is_readable(USERS_FILE),
    'urs' => \$urs ? [
        'id' => \$urs['id'],
        'username' => \$urs['username'],
        'role' => \$urs['role'] ?? null,
        'is_admin' => (\$urs['role'] ?? 'editor') === 'admin',
    ] : null,
    'admin_count' => Auth::countAdmins(),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
PHP

echo "Lade Diagnose-Skript hoch …"
deploy_scp "$TMPPHP" "$(deploy_remote_path "$REMOTE_SCRIPT")"

echo "Rufe ${PROD_URL}/${REMOTE_BASENAME} auf …"
BODY="$(curl -sS "${PROD_URL}/${REMOTE_BASENAME}?token=${TOKEN}")"
echo "$BODY" | python3 -m json.tool

ROLE="$(echo "$BODY" | python3 -c "import json,sys; d=json.load(sys.stdin); u=d.get('urs') or {}; print(u.get('role',''))")"
if [[ "$ROLE" == "admin" ]]; then
  echo ""
  echo "PHP auf Prod sieht „${USERNAME}“ als admin."
else
  echo ""
  echo "WARNUNG: PHP auf Prod sieht „${USERNAME}“ NICHT als admin (role=${ROLE:-?})."
  exit 1
fi
