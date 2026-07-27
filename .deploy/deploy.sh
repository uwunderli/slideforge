#!/usr/bin/env bash
# SlideForge – Code auf Prod deployen (SSH/rsync oder Legacy-SFTP).
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
# shellcheck source=ssh-common.sh
source "$SCRIPT_DIR/ssh-common.sh"

MAX_RETRIES=3
RETRY_DELAY=5
CONFIG_UPLOAD="$ROOT/config.php"
if [[ "${DEPLOY_DEMO_MODE:-0}" == "1" ]]; then
  CONFIG_UPLOAD="$(mktemp)"
  trap 'rm -f "$CONFIG_UPLOAD"' EXIT
  sed 's/define('\''DEMO_MODE'\'', false)/define('\''DEMO_MODE'\'', true)/' "$ROOT/config.php" > "$CONFIG_UPLOAD"
fi

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

upload_one_sftp() {
  local rel="$1"
  local src="$ROOT/$rel"
  [[ "$rel" == "config.php" ]] && src="$CONFIG_UPLOAD"
  [[ -f "$src" ]] || return 0
  local remote="${SFTP_REMOTE:-sftp://${SSH_HOST}}"
  [[ -n "${SSH_PORT:-}" && -z "${SFTP_REMOTE:-}" ]] && remote="sftp://${SSH_HOST}:${SSH_PORT}"
  deploy_curl_sftp -T "$src" "${remote}/$rel"
}

upload_batch_sftp() {
  local -a rels=()
  local files=(config.php README.md)
  local dirs=(config src lang seed docker public_html scripts)
  local i=0 failed=0 total=0

  for f in "${files[@]}"; do
    [[ -f "$ROOT/$f" ]] && rels+=("$f")
  done
  for dir in "${dirs[@]}"; do
    [[ -d "$ROOT/$dir" ]] || continue
    while IFS= read -r local; do
      rels+=("${local#$ROOT/}")
    done < <(find "$ROOT/$dir" -type f ! -path "$ROOT/public_html/uploads/*" | sort)
  done

  total=${#rels[@]}
  echo "  Upload einzeln (${total} Datei(en)) …"
  for rel in "${rels[@]}"; do
    i=$((i + 1))
    if ! with_retry upload_one_sftp "$rel"; then
      echo "  FEHLER: $rel" >&2
      failed=1
    elif (( i % 20 == 0 || i == total )); then
      echo "  … ${i}/${total}"
    fi
  done
  return "$failed"
}

sync_code_ssh() {
  local target root
  target="$(deploy_ssh_target)"
  root="$(deploy_remote_root)"
  local -a sources=(README.md config src lang seed docker public_html scripts)
  if [[ "$CONFIG_UPLOAD" == "$ROOT/config.php" ]]; then
    sources=(config.php "${sources[@]}")
  fi

  deploy_ssh "mkdir -p '$root'"

  if command -v rsync >/dev/null && deploy_ssh "command -v rsync >/dev/null 2>&1"; then
    local ssh_e
    ssh_e="$(deploy_rsync_ssh_e)"
    echo "  rsync → ${target}:${root}/"
    # shellcheck disable=SC2086
    rsync -avz \
      -e "$ssh_e" \
      --exclude 'public_html/uploads/' \
      --exclude 'data/' \
      "${sources[@]}" \
      "${target}:${root}/"
  else
    echo "  tar+ssh → ${target}:${root}/"
    tar czf - -C "$ROOT" \
      --exclude='public_html/uploads' \
      --exclude='data' \
      "${sources[@]}" \
      | deploy_ssh "tar xzf - -C '$root'"
  fi

  if [[ "$CONFIG_UPLOAD" != "$ROOT/config.php" ]]; then
    deploy_scp "$CONFIG_UPLOAD" "$(deploy_remote_path config.php)"
  fi
}

cmd="${1:-status}"
label="$(deploy_label)"

case "$cmd" in
  status)
    echo "Remote-Inhalt (${label}):"
    if deploy_use_legacy_sftp; then
      remote="${SFTP_REMOTE:-sftp://${SSH_HOST}}"
      [[ -n "${SSH_PORT:-}" && -z "${SFTP_REMOTE:-}" ]] && remote="sftp://${SSH_HOST}:${SSH_PORT}"
      deploy_curl_sftp --list-only "${remote}/"
    else
      deploy_ssh "ls -la $(deploy_remote_path '')"
    fi
    ;;
  upload-file)
    if deploy_use_legacy_sftp; then
      upload_one_sftp "${3#/}"
    else
      deploy_scp "$2" "$(deploy_remote_path "${3#/}")"
    fi
    echo "OK: $2 -> $3"
    ;;
  sync-code)
    echo "Lade Code-Dateien hoch (${label}, ohne data/ und uploads/) …"
    if [[ "${DEPLOY_DEMO_MODE:-0}" == "1" ]]; then
      echo "  Demo-Modus: DEMO_MODE=true in config.php"
    fi
    file_count=$((
      $(find "$ROOT"/config "$ROOT"/src "$ROOT"/lang "$ROOT"/seed "$ROOT"/docker "$ROOT"/public_html "$ROOT"/scripts -type f \
        ! -path "$ROOT/public_html/uploads/*" 2>/dev/null | wc -l) +
      $( [[ -f "$ROOT/config.php" ]] && echo 1 || echo 0 ) +
      $( [[ -f "$ROOT/README.md" ]] && echo 1 || echo 0 )
    ))
    echo "  ${file_count} Datei(en) …"
    if deploy_use_legacy_sftp; then
      with_retry upload_batch_sftp || { echo "Warnung: Upload fehlgeschlagen." >&2; exit 1; }
    else
      with_retry sync_code_ssh || { echo "Warnung: rsync fehlgeschlagen." >&2; exit 1; }
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
