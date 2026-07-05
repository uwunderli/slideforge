#!/usr/bin/env bash
# Versions-Bump, CHANGELOG und README vor Release (wird vom pre-push-Hook aufgerufen).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION_FILE="$ROOT/VERSION"
CHANGELOG="$ROOT/CHANGELOG.md"
README="$ROOT/README.md"

current="$(tr -d '[:space:]' < "$VERSION_FILE")"
IFS=. read -r major minor patch <<< "${current#v}"
patch=$((patch + 1))
new="${major}.${minor}.${patch}"

echo "$new" > "$VERSION_FILE"

today="$(date +%Y-%m-%d)"
last_tag="$(git -C "$ROOT" describe --tags --match 'v*' --abbrev=0 2>/dev/null || true)"
range="${last_tag}..HEAD"
if [[ -z "$last_tag" ]]; then
  range="HEAD"
fi

commits="$(
  git -C "$ROOT" log "$range" --pretty=format:'- %s' \
    | grep -vE '^- chore\(release\): v[0-9]+\.[0-9]+\.[0-9]+$' \
    | head -25
)"
if [[ -z "$commits" ]]; then
  commits="- Siehe Git-Log"
fi

tmp="$(mktemp)"
{
  head -n 7 "$CHANGELOG"
  echo ""
  echo "## [$new] - $today"
  echo ""
  echo "### Changed"
  echo ""
  echo "$commits"
  echo ""
  tail -n +8 "$CHANGELOG"
} > "$tmp"
mv "$tmp" "$CHANGELOG"

sed -i "s|\*\*Release:\*\* \[v[0-9.]*\]|\*\*Release:\*\* [v${new}]|g" "$README"
sed -i "s|releases/tag/v[0-9.]*|releases/tag/v${new}|g" "$README"

echo "Release v${new} vorbereitet (CHANGELOG, README, VERSION)."
