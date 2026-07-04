#!/usr/bin/env bash
# SlideForge – Demo-Daten zurücksetzen (für Cron auf der Testinstanz).
# Löscht Benutzer, Präsentationen, Uploads und Cache. config.json bleibt erhalten.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DATA="$ROOT/data"
UPLOADS="$ROOT/public_html/uploads"

if [[ ! -d "$DATA" ]]; then
  echo "data/ nicht gefunden – Abbruch." >&2
  exit 1
fi

echo "Setze Demo-Daten zurück …"

echo '[]' > "$DATA/users.json"
echo '[]' > "$DATA/invites.json"

rm -rf "$DATA/presentations"/*
mkdir -p "$DATA/presentations"
rm -rf "$DATA/cache"/*
mkdir -p "$DATA/cache"

find "$UPLOADS" -mindepth 1 ! -name '.gitkeep' -delete 2>/dev/null || true
mkdir -p "$UPLOADS/avatars" "$UPLOADS/fonts"
touch "$UPLOADS/.gitkeep" "$UPLOADS/avatars/.gitkeep" "$UPLOADS/fonts/.gitkeep"

echo "Fertig. Nächster Registrierter wird wieder Administrator."
