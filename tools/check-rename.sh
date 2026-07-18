#!/usr/bin/env bash
# Biscotto — verifica che il rename da ConsentKit sia completo e coerente.
# Uso: bash tools/check-rename.sh
# Exit 0 = tutto a posto, exit 1 = almeno un controllo fallito.

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FAIL=0

# Versione attesa per il rilascio (vedi Global Constraints del piano).
EXPECTED_VERSION="1.5.3"

# Esclusioni: repository git, artefatti di build, corrispondenza email,
# lo script stesso (contiene per forza i pattern vietati) e i documenti
# di progetto che citano la storia del rename.
GREP_EXCL=(
  --exclude-dir=.git
  --exclude-dir=dist
  --exclude-dir=node_modules
  --exclude-dir=.claude
  --exclude-dir=.superpowers
  --exclude=*.eml
  --exclude=check-rename.sh
  --exclude=wporg-review-reply*
  --exclude-dir=specs
  --exclude-dir=plans
)

# --- Controlli negativi: nessuna occorrenza del vecchio nome --------------

check_absent() { # $1 = pattern, $2 = descrizione
  local hits
  hits="$(grep -rn "$1" "$ROOT" "${GREP_EXCL[@]}" 2>/dev/null || true)"
  if [ -n "$hits" ]; then
    echo "FAIL: $2"
    echo "$hits" | head -20
    FAIL=1
  else
    echo "PASS: $2"
  fi
}

check_absent 'ConsentKit'  'nessuna classe/identificatore ConsentKit'
check_absent 'CONSENTKIT'  'nessuna costante CONSENTKIT'
check_absent 'consentkit'  'nessun identificatore consentkit minuscolo'

# --- Controlli positivi: identita' pubblica ------------------------------

WP="$ROOT/packages/wordpress"
MAIN="$WP/biscotto-cookie-consent.php"

check_file_exists() { # $1 = path, $2 = descrizione
  if [ -f "$1" ]; then echo "PASS: $2"; else echo "FAIL: $2 (manca $1)"; FAIL=1; fi
}

check_file_absent() { # $1 = path, $2 = descrizione
  if [ -f "$1" ]; then echo "FAIL: $2 (esiste ancora $1)"; FAIL=1; else echo "PASS: $2"; fi
}

check_file_exists "$MAIN" 'file principale biscotto-cookie-consent.php'
check_file_absent "$WP/biscotto.php" 'vecchio file principale biscotto.php rimosso'
check_file_exists "$WP/languages/biscotto-cookie-consent.pot" 'POT rinominato'
check_file_absent "$WP/languages/biscotto.pot" 'vecchio POT rimosso'

# Text domain nell'header del plugin.
if grep -q "^ \* Text Domain: *biscotto-cookie-consent$" "$MAIN" 2>/dev/null; then
  echo "PASS: header Text Domain = biscotto-cookie-consent"
else
  echo "FAIL: header Text Domain non e' biscotto-cookie-consent"
  FAIL=1
fi

# Nome del plugin: il separatore e' un trattino lungo (EN DASH U+2013), non un
# trattino normale. Va su WordPress.org, quindi si verifica il carattere esatto.
if grep -q "^ \* Plugin Name: *Biscotto – Cookie Consent$" "$MAIN" 2>/dev/null; then
  echo "PASS: header Plugin Name = Biscotto – Cookie Consent"
else
  echo "FAIL: header Plugin Name non e' esattamente 'Biscotto – Cookie Consent' (attenzione al trattino lungo)"
  FAIL=1
fi

# Nessuna stringa tradotta col vecchio text domain 'biscotto'.
OLD_TD="$(grep -rn "'biscotto'" "$WP" --include=*.php 2>/dev/null || true)"
if [ -n "$OLD_TD" ]; then
  echo "FAIL: text domain 'biscotto' ancora usato nelle stringhe tradotte"
  echo "$OLD_TD" | head -20
  FAIL=1
else
  echo "PASS: tutte le stringhe usano il text domain nuovo"
fi

# --- Coerenza della versione ---------------------------------------------

VER_HEADER="$(grep -m1 '^ \* Version:' "$MAIN" 2>/dev/null | tr -dc '0-9.')"
VER_CONST="$(grep -m1 "define( 'BISCOTTO_VERSION'" "$MAIN" 2>/dev/null | grep -o "'[0-9.]*'" | tail -1 | tr -d "'")"
VER_README="$(grep -m1 '^Stable tag:' "$WP/readme.txt" 2>/dev/null | tr -dc '0-9.')"

if [ "$VER_HEADER" = "$EXPECTED_VERSION" ] && [ "$VER_CONST" = "$EXPECTED_VERSION" ] && [ "$VER_README" = "$EXPECTED_VERSION" ]; then
  echo "PASS: versione $EXPECTED_VERSION coerente ovunque"
else
  echo "FAIL: versione attesa '$EXPECTED_VERSION' — header='$VER_HEADER' costante='$VER_CONST' readme='$VER_README'"
  FAIL=1
fi

echo
if [ "$FAIL" -eq 0 ]; then
  echo "check-rename: OK"
else
  echo "check-rename: FALLITO"
fi
exit "$FAIL"
