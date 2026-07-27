#!/usr/bin/env bash
# Legacy: SFTP-Batch (nur noch für alte Hosts mit SSH_PASS).
# Prod nutzt: ./remote.sh oder deploy.sh (SSH/rsync).
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
source "$SCRIPT_DIR/ssh.env"
export SSHPASS="$SSH_PASS"

batch="${1:-}"
if [[ -z "$batch" ]]; then
  echo "Usage: $0 <sftp-batch-commands>" >&2
  exit 1
fi

printf '%s\n' "$batch" | sshpass -e sftp -o StrictHostKeyChecking=accept-new -b - "${SSH_USER}@${SSH_HOST}"
