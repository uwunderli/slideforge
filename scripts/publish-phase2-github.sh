#!/usr/bin/env bash
# Phase 2: GitHub metadata + release (run after: gh auth login)
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if ! gh auth status >/dev/null 2>&1; then
  echo "Bitte zuerst: gh auth login" >&2
  exit 1
fi

gh repo edit uwunderli/slideforge \
  --description "Self-hosted, file-based multi-user editor for reveal.js presentations" \
  --add-topic reveal-js \
  --add-topic presentation \
  --add-topic php \
  --add-topic self-hosted \
  --add-topic no-database \
  --add-topic editor

if gh release view v1.0.0 >/dev/null 2>&1; then
  echo "Release v1.0.0 existiert bereits."
else
  gh release create v1.0.0 \
    --title "v1.0.0 — First public release" \
    --notes-file "$ROOT/.github/RELEASE_v1.0.0.md"
fi

echo "Fertig: https://github.com/uwunderli/slideforge/releases/tag/v1.0.0"
