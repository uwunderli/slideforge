#!/usr/bin/env bash
# Setzt Schreibrechte für eine Präsentation auf Prod.
# Usage: ./.deploy/fix-prod-presentation-perms.sh <presentation-id>
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PRES_ID="${1:?Usage: $0 <presentation-id>}"

ENV_FILE="${DEPLOY_ENV:-$SCRIPT_DIR/ssh.env}"
# shellcheck source=/dev/null
source "$ENV_FILE"
# shellcheck source=ssh-common.sh
source "$SCRIPT_DIR/ssh-common.sh"

BASE="$(deploy_remote_path "data/presentations/${PRES_ID}")"
echo "Rechte für ${BASE} …"

deploy_ssh "chmod -R u+rwX,go+rX '${BASE}' 2>/dev/null || chmod -R 777 '${BASE}'"
echo "OK: Rechte gesetzt für ${PRES_ID}"
