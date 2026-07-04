#!/usr/bin/env bash
# Demo-Deploy nach slideforge.service7.ch (Quelle: .deploy/ssh.env.demo)
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export DEPLOY_ENV="$SCRIPT_DIR/ssh.env.demo"
export DEPLOY_DEMO_MODE=1
exec "$SCRIPT_DIR/deploy.sh" "$@"
