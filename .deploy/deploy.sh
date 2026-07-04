#!/usr/bin/env bash
# SlideForge – Dateien per SFTP auf den Webspace hochladen.
# Nutzt curl (SFTP). Port über SSH_PORT oder SFTP_REMOTE in der env-Datei.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

ENV_FILE="${DEPLOY_ENV:-$SCRIPT_DIR/ssh.env}"
if [[ "${1:-}" == --env ]]; then
  ENV_FILE="$2"
  shift 2
fi

# shellcheck source=/dev/null
source "$ENV_FILE"

if [[ -n "${SSH_PORT:-}" && -z "${SFTP_REMOTE:-}" ]]; then
  SFTP_REMOTE="sftp://${SSH_HOST}:${SSH_PORT}"
fi
REMOTE="${SFTP_REMOTE:-sftp://${SSH_HOST}}"
AUTH="${SSH_USER}:${SSH_PASS}"

MAX_RETRIES=3
RETRY_DELAY=5
CONFIG_UPLOAD="$ROOT/config.php"
if [[ "${DEPLOY_DEMO_MODE:-0}" == "1" ]]; then
  CONFIG_UPLOAD="$(mktemp)"
  trap 'rm -f "$CONFIG_UPLOAD"' EXIT
  sed 's/define('\''DEMO_MODE'\'', false)/define('\''DEMO_MODE'\'', true)/' "$ROOT/config.php" > "$CONFIG_UPLOAD"
fi

curl_sftp() {
  # Ohne Agent: sonst versucht curl zuerst Public-Key und bricht ab (Login denied).
  SSH_AUTH_SOCK= curl -sS --ftp-method nocwd --ftp-create-dirs --user "$AUTH" "$@"
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

upload_one() {
  local rel="$1"
  local src="$ROOT/$rel"
  [[ "$rel" == "config.php" ]] && src="$CONFIG_UPLOAD"
  [[ -f "$src" ]] || return 0
  curl_sftp -T "$src" "${REMOTE}/$rel"
}

upload_batch() {
  local -a rels=()
  local files=(config.php README.md)
  local dirs=(src lang seed docker public_html scripts)
  local i=0
  local failed=0
  local total=0

  for f in "${files[@]}"; do
    [[ -f "$ROOT/$f" ]] && rels+=("$f")
  done

  for dir in "${dirs[@]}"; do
    [[ -d "$ROOT/$dir" ]] || continue
    while IFS= read -r local; do
      rels+=("${local#$ROOT/}")
    done < <(find "$ROOT/$dir" -type f \
      ! -path "$ROOT/public_html/uploads/*" | sort)
  done

  total=${#rels[@]}
  echo "  Upload einzeln (${total} Datei(en)) …"
  for rel in "${rels[@]}"; do
    i=$((i + 1))
    if ! with_retry upload_one "$rel"; then
      echo "  FEHLER: $rel" >&2
      failed=1
    elif (( i % 20 == 0 || i == total )); then
      echo "  … ${i}/${total}"
    fi
  done
  return "$failed"
}

cmd="${1:-status}"
label="${SSH_HOST}${SSH_PORT:+:$SSH_PORT}"

case "$cmd" in
  status)
    echo "Remote-Inhalt (${label}):"
    curl_sftp --list-only "${REMOTE}/"
    ;;
  upload-file)
    curl_sftp -T "$2" "${REMOTE}/${3#/}"
    echo "OK: $2 -> $3"
    ;;
  sync-code)
    echo "Lade Code-Dateien hoch (${label}, ohne data/ und uploads/) …"
    if [[ "${DEPLOY_DEMO_MODE:-0}" == "1" ]]; then
      echo "  Demo-Modus: DEMO_MODE=true in config.php"
    fi
    file_count=$((
      $(find "$ROOT"/src "$ROOT"/lang "$ROOT"/seed "$ROOT"/docker "$ROOT"/public_html "$ROOT"/scripts -type f \
        ! -path "$ROOT/public_html/uploads/*" 2>/dev/null | wc -l) +
      $( [[ -f "$ROOT/config.php" ]] && echo 1 || echo 0 ) +
      $( [[ -f "$ROOT/README.md" ]] && echo 1 || echo 0 )
    ))
    echo "  ${file_count} Datei(en) …"
    if ! with_retry upload_batch; then
      echo "Warnung: Mindestens ein Upload-Block fehlgeschlagen – ggf. erneut sync-code oder einzelne Dateien prüfen." >&2
      exit 1
    fi
    echo "Fertig."
    ;;
  sync-demo)
    "$0" sync-code
    "$0" reset-demo
    ;;
  reset-demo)
    echo "Setze Demo-Daten zurück (${label}) …"
    if [[ "${DEPLOY_DEMO_MODE:-0}" != "1" ]]; then
      echo "Hinweis: DEPLOY_DEMO_MODE ist nicht gesetzt – config.php auf dem Server braucht DEMO_MODE=true." >&2
    fi
    demo_url="${DEMO_URL:-https://slideforge.service7.ch/demo-reset.php}"
    echo "  Aufruf: $demo_url"
    body="$(curl -sS -L "$demo_url" || true)"
    echo "$body"
    echo "Fertig."
    ;;
  *)
    echo "Usage: $0 [--env <file>] {status|upload-file <local> <remote>|sync-code|sync-demo|reset-demo}" >&2
    exit 1
    ;;
esac
