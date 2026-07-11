#!/usr/bin/env bash
# Biscotto — packaging del plugin WordPress installabile.
# Produce dist/biscotto/ (cartella da caricare via FTP) e dist/biscotto.zip
# (da caricare da Plugin → Aggiungi nuovo → Carica plugin).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="$ROOT/dist"
PLUGIN="$DIST/biscotto"

# 1) Assicura che il core sia copiato dentro l'adattatore WordPress.
bash "$ROOT/tools/build.sh"

# 2) Assembla la cartella del plugin (nome cartella = slug = biscotto).
rm -rf "$DIST"
mkdir -p "$PLUGIN"
cp -r "$ROOT/packages/wordpress/." "$PLUGIN/"
# include la licenza a livello plugin
cp "$ROOT/LICENSE" "$PLUGIN/LICENSE"

# 3) Crea lo zip (zip se disponibile, altrimenti lo fa PowerShell a parte).
cd "$DIST"
if command -v zip >/dev/null; then
  zip -rq biscotto.zip biscotto
  echo "  ✓ dist/biscotto.zip creato"
else
  echo "  ! 'zip' non disponibile: usa PowerShell -> Compress-Archive (vedi istruzioni)"
fi

echo "Pacchetto pronto in: $PLUGIN"
