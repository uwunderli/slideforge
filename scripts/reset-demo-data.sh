#!/usr/bin/env bash
# SlideForge – Demo-Daten zurücksetzen (Cron: alle 12 Stunden empfohlen).
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
php scripts/seed_demo.php
