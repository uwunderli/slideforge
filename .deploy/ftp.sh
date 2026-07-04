#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
source "$SCRIPT_DIR/ssh.env"

FTP_HOST="${SSH_HOST}"
FTP_USER="${SSH_USER}"
FTP_PASS="${SSH_PASS}"
FTP_BASE="ftp://${FTP_HOST}"

ftp_curl() {
  curl -sS --ftp-method nocwd --user "${FTP_USER}:${FTP_PASS}" "$@"
}

case "${1:-list}" in
  list)
    path="${2:-/}"
    ftp_curl --list-only "${FTP_BASE}${path}"
    ;;
  ls)
    path="${2:-/}"
    ftp_curl "${FTP_BASE}${path}/"
    ;;
  *)
    echo "Usage: $0 list [path]" >&2
    exit 1
    ;;
esac
