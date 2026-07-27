#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
source "$SCRIPT_DIR/ssh.env"
# shellcheck source=ssh-common.sh
source "$SCRIPT_DIR/ssh-common.sh"
deploy_ssh "$@"
