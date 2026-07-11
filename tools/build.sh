#!/usr/bin/env bash
# Biscotto — build/sync
# Il core (packages/core) è l'unica fonte di verità. Questo script copia il core
# dentro gli adattatori, così non si mantengono copie a mano (vedi project §4.4/§5).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CORE="$ROOT/packages/core"
WP="$ROOT/packages/wordpress"
SA="$ROOT/packages/standalone"

echo "Biscotto build — root: $ROOT"

# --- Adattatore WordPress -------------------------------------------------
mkdir -p "$WP/public/js" "$WP/public/css"
cp "$CORE/src/consent-manager.js"      "$WP/public/js/consent-manager.js"
cp "$CORE/src/consent-mode-default.js" "$WP/public/js/consent-mode-default.js"
cp "$CORE/css/banner.css"              "$WP/public/css/banner.css"
echo "  ✓ WordPress public/ aggiornato"

# --- Adattatore standalone -----------------------------------------------
mkdir -p "$SA"
cp "$CORE/src/consent-manager.js"      "$SA/biscotto.js"
cp "$CORE/src/consent-mode-default.js" "$SA/consent-mode-default.js"
cp "$CORE/css/banner.css"              "$SA/banner.css"
echo "  ✓ standalone/ aggiornato"

echo "Build completata. (Minificazione: opzionale, da aggiungere in CI/CD.)"
