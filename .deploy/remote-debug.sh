#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
source "$SCRIPT_DIR/ssh.env"
export SSHPASS="$SSH_PASS"
LOG="$SCRIPT_DIR/last-ssh-debug.log"
sshpass -e ssh -v -o StrictHostKeyChecking=accept-new "${SSH_USER}@${SSH_HOST}" "$@" >"$LOG" 2>&1 || true
tail -20 "$LOG"
