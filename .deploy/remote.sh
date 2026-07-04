#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
source "$SCRIPT_DIR/ssh.env"
export SSHPASS="$SSH_PASS"
sshpass -e ssh -o StrictHostKeyChecking=accept-new "${SSH_USER}@${SSH_HOST}" "$@"
