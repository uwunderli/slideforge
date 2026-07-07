#!/usr/bin/env bash
# Setzt Schreibrechte für eine Präsentation auf Prod (nach SFTP-Upload via push-layout-set-seed.sh).
# Usage: ./.deploy/fix-prod-presentation-perms.sh <presentation-id>
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PRES_ID="${1:?Usage: $0 <presentation-id>}"

ENV_FILE="${DEPLOY_ENV:-$SCRIPT_DIR/ssh.env}"
# shellcheck source=/dev/null
source "$ENV_FILE"

REMOTE="${SFTP_REMOTE:-sftp://${SSH_HOST}:${SSH_PORT:-22}}"
AUTH="${SSH_USER}:${SSH_PASS}"
BASE="data/presentations/${PRES_ID}"

curl_sftp() {
  SSH_AUTH_SOCK= curl -sS --ftp-method nocwd --user "$AUTH" "$@"
}

sftp_chmod() {
  local mode="$1"
  local path="$2"
  curl_sftp -Q "chmod ${mode} ${path}" "${REMOTE}/" >/dev/null
}

echo "Rechte für ${BASE} auf ${SSH_HOST} …"

sftp_chmod 777 "${BASE}"

mapfile -t ENTRIES < <(curl_sftp --list-only "${REMOTE}/${BASE}/" 2>/dev/null | grep -v '^\.\.?$' || true)
for entry in "${ENTRIES[@]}"; do
  [[ -z "$entry" || "$entry" == "." || "$entry" == ".." ]] && continue
  if [[ "$entry" == "assets" ]]; then
    sftp_chmod 777 "${BASE}/assets"
    mapfile -t ASSETS < <(curl_sftp --list-only "${REMOTE}/${BASE}/assets/" 2>/dev/null | grep -v '^\.\.?$' || true)
    for asset in "${ASSETS[@]}"; do
      [[ -z "$asset" || "$asset" == "." || "$asset" == ".." ]] && continue
      sftp_chmod 666 "${BASE}/assets/${asset}"
    done
  else
    sftp_chmod 666 "${BASE}/${entry}"
  fi
done

echo "OK: Rechte gesetzt für ${PRES_ID}"
