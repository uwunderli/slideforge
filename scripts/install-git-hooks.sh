#!/usr/bin/env bash
# Einmal ausführen: Git-Hooks für Release + Deploy bei Push aktivieren.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

chmod +x .githooks/pre-push .deploy/release-prep.sh scripts/install-git-hooks.sh

git config core.hooksPath .githooks
git config push.followTags true

echo "Git-Hooks aktiv (.githooks/pre-push)."
echo "Bei Commit & Push: Version bump → Prod (BK Biel) → Demo → Tag → Push."
echo "Nur pushen ohne Deploy: SKIP_RELEASE_DEPLOY=1 git push"
