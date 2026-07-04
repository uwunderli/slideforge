#!/usr/bin/env bash
# SlideForge – Dateien per SFTP auf den Webspace hochladen.
# Nutzt curl (Port 22). sshpass/sftp scheitert bei manchen SFTPGo-Setups an der Auth.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
# shellcheck source=/dev/null
source "$SCRIPT_DIR/ssh.env"

REMOTE="${SFTP_REMOTE:-sftp://${SSH_HOST}}"
AUTH="${SSH_USER}:${SSH_PASS}"

MAX_RETRIES=3
RETRY_DELAY=5

curl_sftp() {
  curl -sS --ftp-method nocwd --user "$AUTH" "$@"
}

with_retry() {
  local attempt=1
  local delay=$RETRY_DELAY
  while true; do
    if "$@"; then
      return 0
    fi
    if (( attempt >= MAX_RETRIES )); then
      echo "Fehler: nach ${MAX_RETRIES} Versuchen abgebrochen." >&2
      return 1
    fi
    echo "Verbindung unterbrochen – neuer Versuch in ${delay}s (${attempt}/${MAX_RETRIES})…" >&2
    sleep "$delay"
    attempt=$((attempt + 1))
    delay=$((delay * 2))
  done
}

upload_batch() {
  local -a args=()
  local files=(config.php README.md)
  local dirs=(src lang seed docker public_html)

  for f in "${files[@]}"; do
    [[ -f "$ROOT/$f" ]] || continue
    args+=(-T "$ROOT/$f" "${REMOTE}/$f")
  done

  for dir in "${dirs[@]}"; do
    [[ -d "$ROOT/$dir" ]] || continue
    while IFS= read -r local; do
      local rel="${local#$ROOT/}"
      args+=(-T "$local" "${REMOTE}/$rel")
    done < <(find "$ROOT/$dir" -type f | sort)
  done

  curl_sftp "${args[@]}"
}

cmd="${1:-status}"

case "$cmd" in
  status)
    echo "Remote-Inhalt (${SSH_HOST}):"
    curl_sftp --list-only "${REMOTE}/"
    ;;
  upload-file)
    curl_sftp -T "$2" "${REMOTE}/${3#/}"
    echo "OK: $2 -> $3"
    ;;
  sync-code)
    echo "Lade Code-Dateien hoch (curl/SFTP, ohne data/ und uploads/) …"
    file_count=$((
      $(find "$ROOT"/src "$ROOT"/lang "$ROOT"/seed "$ROOT"/docker "$ROOT"/public_html -type f 2>/dev/null | wc -l) +
      $( [[ -f "$ROOT/config.php" ]] && echo 1 || echo 0 ) +
      $( [[ -f "$ROOT/README.md" ]] && echo 1 || echo 0 )
    ))
    echo "  ${file_count} Datei(en) …"
    with_retry upload_batch
    echo "Fertig."
    ;;
  *)
    echo "Usage: $0 {status|upload-file <local> <remote>|sync-code}" >&2
    exit 1
    ;;
esac
