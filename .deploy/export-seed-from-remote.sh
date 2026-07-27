#!/usr/bin/env bash
# Lädt data/presentations/ vom Server und exportiert freigegebene Vorlagen nach seed/templates/.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
export ROOT
# shellcheck source=/dev/null
source "$SCRIPT_DIR/ssh.env"
# shellcheck source=ssh-common.sh
source "$SCRIPT_DIR/ssh-common.sh"

LOCAL_DATA="$ROOT/data/presentations"

echo "Remote-Präsentationen auflisten …"
mapfile -t REMOTE_IDS < <(deploy_ssh "ls -1 '$(deploy_remote_path data/presentations)' 2>/dev/null" | grep -E '^[a-f0-9]+$' || true)

if ((${#REMOTE_IDS[@]} == 0)); then
  echo "Keine Präsentations-Ordner auf dem Server gefunden." >&2
  exit 1
fi

mkdir -p "$LOCAL_DATA"

for id in "${REMOTE_IDS[@]}"; do
  remote_base="$(deploy_remote_path "data/presentations/${id}")"
  local_base="$LOCAL_DATA/${id}"
  mkdir -p "$local_base/assets"

  for file in meta.json slides.json; do
    if deploy_scp_pull "${remote_base}/${file}" "$local_base/${file}" 2>/dev/null; then
      :
    else
      rm -f "$local_base/${file}"
    fi
  done

  mapfile -t ASSETS < <(deploy_ssh "ls -1 '${remote_base}/assets/' 2>/dev/null" | grep -v '^\.\.?$' || true)
  for asset in "${ASSETS[@]}"; do
    [[ -z "$asset" || "$asset" == "." || "$asset" == ".." ]] && continue
    deploy_scp_pull "${remote_base}/assets/${asset}" "$local_base/assets/${asset}" 2>/dev/null || true
  done
done

echo "Export freigegebener Vorlagen …"
if command -v php >/dev/null 2>&1; then
  php "$ROOT/scripts/export_seed_templates.php"
elif command -v python3 >/dev/null 2>&1; then
  python3 << 'PY'
import json, os, shutil, glob
ROOT = os.environ["ROOT"]
PRES = os.path.join(ROOT, "data/presentations")
SEED = os.path.join(ROOT, "seed/templates")
shared = []
for entry in sorted(os.listdir(PRES)):
    if entry.startswith("."):
        continue
    meta_path = os.path.join(PRES, entry, "meta.json")
    if not os.path.isfile(meta_path):
        continue
    with open(meta_path) as f:
        meta = json.load(f)
    if meta.get("is_template") and meta.get("template_shared"):
        shared.append((meta.get("template_order", 0), entry, meta))
shared.sort(key=lambda x: x[0])
for entry in os.listdir(SEED):
    path = os.path.join(SEED, entry)
    if os.path.isdir(path):
        shutil.rmtree(path)
for i, (order, entry, meta) in enumerate(shared):
    target = os.path.join(SEED, entry)
    os.makedirs(target, exist_ok=True)
    seed_meta = {
        "title": meta.get("title", "Vorlage"),
        "width": int(meta.get("width", 1920)),
        "height": int(meta.get("height", 1080)),
        "template_order": int(meta.get("template_order", i)),
    }
    with open(os.path.join(target, "meta.json"), "w") as f:
        json.dump(seed_meta, f, ensure_ascii=False, indent=4)
        f.write("\n")
    shutil.copy2(os.path.join(PRES, entry, "slides.json"), os.path.join(target, "slides.json"))
    src_assets = os.path.join(PRES, entry, "assets")
    if os.path.isdir(src_assets):
        dst_assets = os.path.join(target, "assets")
        os.makedirs(dst_assets, exist_ok=True)
        for af in glob.glob(os.path.join(src_assets, "*")):
            if os.path.isfile(af):
                shutil.copy2(af, os.path.join(dst_assets, os.path.basename(af)))
print(f"Exportiert: {len(shared)} Folienvorlage(n)")
PY
else
  echo "Weder PHP noch Python3 gefunden." >&2
  exit 1
fi

echo "Fertig. seed/templates/ ist bereit für sync-code."
