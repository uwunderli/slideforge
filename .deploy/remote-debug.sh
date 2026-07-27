#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
source "$SCRIPT_DIR/ssh.env"
# shellcheck source=ssh-common.sh
source "$SCRIPT_DIR/ssh-common.sh"
LOG="$SCRIPT_DIR/last-ssh-debug.log"
if deploy_use_legacy_sftp; then
  SSHPASS="$SSH_PASS" sshpass -e ssh -v -o StrictHostKeyChecking=accept-new \
    ${SSH_PORT:+-p "$SSH_PORT"} "$(deploy_ssh_target)" "$@" >"$LOG" 2>&1 || true
else
  # shellcheck disable=SC2046
  ssh -v $(deploy_ssh_opts) "$(deploy_ssh_target)" "$@" >"$LOG" 2>&1 || true
fi
tail -20 "$LOG"
