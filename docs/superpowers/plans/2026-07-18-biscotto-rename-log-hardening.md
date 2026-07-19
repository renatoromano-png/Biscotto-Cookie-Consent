# Biscotto — rename completo e hardening `/log` — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** portare il plugin WordPress allo slug `biscotto-cookie-consent` con rename completo del layer PHP interno a Biscotto, e chiudere il rilievo WordPress.org sull'endpoint REST `/log` introducendo limiti d'abuso reali, rilasciando la versione 1.5.3.

**Architecture:** il rename è una sostituzione meccanica di tre token case-sensitive (`ConsentKit` → `Biscotto`, `CONSENTKIT` → `BISCOTTO`, `consentkit` → `biscotto`) su tutto il repository, più il rename dei file che portano il vecchio nome e il cambio separato del text domain da `biscotto` a `biscotto-cookie-consent`. La correttezza è verificata da uno script `tools/check-rename.sh` scritto *prima* del rename, che deve fallire prima e passare dopo. L'hardening di `/log` resta confinato a `includes/class-biscotto-api.php`, salvo l'impostazione di retention che tocca defaults, sanitizzazione e vista admin.

**Tech Stack:** PHP 7.4+ (WordPress 5.9+), JavaScript senza dipendenze, Bash per build e verifica, PowerShell per il packaging su Windows.

## Global Constraints

- Slug e text domain: `biscotto-cookie-consent` (devono coincidere, esattamente questa stringa).
- Nome plugin: `Biscotto – Cookie Consent` (trattino lungo U+2013, come già comunicato a WordPress.org il 12 luglio).
- Versione da rilasciare: `1.5.3` (WordPress.org ha già ricevuto la 1.5.2; non è possibile ricaricare un numero uguale o inferiore).
- Nessuna migrazione di dati: né da `consentkit_settings` a `biscotto_settings`, né dalla vecchia alla nuova tabella di log. Decisione presa, non reintrodurla.
- I file di terze parti in `packages/wordpress/includes/data/` (Open Cookie Database, Apache-2.0) non vanno alterati nei contenuti; solo i riferimenti al nome del *nostro* plugin nel `NOTICE.md` seguono il rename.
- Il core JS (`packages/core`) e le classi CSS sono già `biscotto` dal commit `74c6bda`: lì restano solo riferimenti in commenti, che il rename globale sistema.
- Ogni task termina con un commit.

## Nota sull'ambiente

**Aggiornato il 19 luglio 2026: Laragon è stato installato durante l'esecuzione del piano.** PHP 8.3.30, MySQL 8.4.3 e Composer sono disponibili, ma **non sono nel PATH** di Git Bash. Usa il percorso esplicito:

```bash
PHP=/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe
"$PHP" -l percorso/del/file.php
```

Conseguenze operative:

- `php -l` **è ora eseguibile**: i passi di lint marcati come opzionali nei task 4 e 5 vanno eseguiti, non saltati.
- `tests/test-cookie-database.php` **è ora eseguibile**: `"$PHP" tests/test-cookie-database.php`.
- `tools/check-rename.sh` (bash + grep) resta il gate del rename.
- La verifica funzionale su WordPress richiede un sito attivo in Laragon con `WP_DEBUG` a `true`. I passi manuali sono specificati nei task 4, 5 e 7.

Stato al momento dell'aggiornamento: tutti e 15 i file PHP passano il lint e la suite di `test-cookie-database.php` passa (16 asserzioni), dopo il rename di massa dei task 2 e 3.

I task 1, 2 e 3 sono stati completati **prima** che Laragon fosse disponibile: il loro codice PHP non è mai stato lintato durante l'esecuzione, ma lo è stato retroattivamente con esito positivo.

---

### Task 1: Script di verifica del rename

Va scritto **per primo** e deve fallire: è il test rosso del rename.

**Files:**
- Create: `tools/check-rename.sh`

**Interfaces:**
- Consumes: niente.
- Produces: `bash tools/check-rename.sh` — exit 0 se il rename è completo e coerente, exit 1 altrimenti. I task 2, 3 e 6 lo usano come criterio di accettazione.

- [ ] **Step 1: Scrivere lo script di verifica**

Crea `tools/check-rename.sh`:

```bash
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

# Il vecchio text domain 'biscotto' non deve comparire come argomento delle
# funzioni di traduzione. Non si puo' cercare 'biscotto' ovunque: restano
# legittimi il nome della funzione di bootstrap (gli identificatori PHP non
# ammettono trattini) e lo slug della pagina admin.
OLD_TD="$(grep -rnE "\b(__|_e|_x|_n|_nx|esc_html__|esc_html_e|esc_html_x|esc_attr__|esc_attr_e|esc_attr_x)\(.*,[[:space:]]*'biscotto'[[:space:]]*[,)]" "$WP" --include=*.php 2>/dev/null || true)"
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
```

- [ ] **Step 2: Eseguire lo script e verificare che fallisca**

Esegui: `bash tools/check-rename.sh`

Atteso: **exit 1**, con almeno questi FAIL — `nessuna classe/identificatore ConsentKit`, `nessuna costante CONSENTKIT`, `nessun identificatore consentkit minuscolo`, `file principale biscotto-cookie-consent.php`, `POT rinominato`, `header Text Domain non è biscotto-cookie-consent`, `header Plugin Name non è esattamente 'Biscotto – Cookie Consent'`, `text domain 'biscotto' ancora usato`, `versione attesa '1.5.3'`.

Se lo script passa a questo punto, è rotto: correggilo prima di proseguire.

- [ ] **Step 3: Commit**

```bash
git add tools/check-rename.sh
git commit -m "test(tools): script di verifica della completezza del rename a Biscotto

Controlla assenza dei token ConsentKit/CONSENTKIT/consentkit, presenza del
file principale e del POT col nome nuovo, text domain coincidente con lo
slug e coerenza della versione fra header, costante e readme.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Rename del layer interno

**Files:**
- Rename: `packages/wordpress/includes/class-consentkit-admin.php` → `class-biscotto-admin.php`
- Rename: `packages/wordpress/includes/class-consentkit-api.php` → `class-biscotto-api.php`
- Rename: `packages/wordpress/includes/class-consentkit-consent.php` → `class-biscotto-consent.php`
- Rename: `packages/wordpress/includes/class-consentkit-cookie-database.php` → `class-biscotto-cookie-database.php`
- Rename: `packages/wordpress/includes/class-consentkit-frontend.php` → `class-biscotto-frontend.php`
- Rename: `packages/wordpress/includes/class-consentkit-scanner.php` → `class-biscotto-scanner.php`
- Rename: `packages/wordpress/includes/class-consentkit-shortcodes.php` → `class-biscotto-shortcodes.php`
- Rename: `packages/wordpress/includes/class-consentkit.php` → `class-biscotto.php`
- Modify: tutti i file `.php`, `.js`, `.css`, `.md`, `.txt` del repository che contengono i tre token
- Modify: `.gitignore`

**Interfaces:**
- Consumes: `bash tools/check-rename.sh` dal Task 1.
- Produces: classe principale `Biscotto` con `Biscotto::get_settings()`; classi `Biscotto_Admin`, `Biscotto_Api`, `Biscotto_Consent`, `Biscotto_Cookie_Database`, `Biscotto_Frontend`, `Biscotto_Scanner`, `Biscotto_Shortcodes`; costanti `BISCOTTO_VERSION`, `BISCOTTO_FILE`, `BISCOTTO_DIR`, `BISCOTTO_URL`, `BISCOTTO_OPTION`; option `biscotto_settings`; tabella `{prefix}biscotto_log`; namespace REST `biscotto/v1`; `Biscotto_Api::TABLE = 'biscotto_log'`. I task 4 e 5 usano questi nomi.

- [ ] **Step 1: Rinominare i file delle classi mantenendo la storia git**

```bash
cd packages/wordpress/includes
for f in class-consentkit-*.php; do git mv "$f" "${f/consentkit-/biscotto-}"; done
git mv class-consentkit.php class-biscotto.php
cd ../../..
ls packages/wordpress/includes/
```

Atteso: otto file `class-biscotto-*.php` / `class-biscotto.php`, nessun `class-consentkit*`.

- [ ] **Step 2: Sostituire i tre token in tutto il repository**

L'ordine è irrilevante perché i tre pattern sono case-sensitive e disgiunti. Le esclusioni impediscono di toccare git, gli artefatti di build, la corrispondenza email e i documenti che raccontano la storia del rename.

```bash
grep -rlZ -e 'ConsentKit' -e 'CONSENTKIT' -e 'consentkit' . \
  --exclude-dir=.git --exclude-dir=dist --exclude-dir=node_modules --exclude-dir=.claude \
  --exclude-dir=.superpowers --exclude-dir=specs --exclude-dir=plans \
  --exclude=*.eml --exclude=check-rename.sh --exclude=wporg-review-reply* \
| xargs -0 sed -i -e 's/ConsentKit/Biscotto/g' -e 's/CONSENTKIT/BISCOTTO/g' -e 's/consentkit/biscotto/g'
```

- [ ] **Step 3: Verificare a campione le sostituzioni chiave**

```bash
grep -n "define( 'BISCOTTO_OPTION'" packages/wordpress/biscotto.php
grep -n "const TABLE" packages/wordpress/includes/class-biscotto-api.php
grep -n "register_rest_route" packages/wordpress/includes/class-biscotto-api.php
grep -n "final class Biscotto" packages/wordpress/includes/class-biscotto.php
grep -rn "biscottoScan\|biscottoPolicy\|biscottoCookies" packages/wordpress --include=*.php | head
```

Atteso, rispettivamente: `define( 'BISCOTTO_OPTION', 'biscotto_settings' );` · `const TABLE = 'biscotto_log';` · `register_rest_route(` con namespace `'biscotto/v1'` · `final class Biscotto {` · almeno un `biscottoScan`, `biscottoPolicy` e `biscottoCookies`.

Nota: gli oggetti localize mantengono così un prefisso di almeno 4 caratteri, requisito già posto dalla review precedente.

- [ ] **Step 4: Aggiornare il riferimento al documento interno nel `.gitignore`**

Il rename globale ha trasformato i riferimenti in commento da `consentkit-project.md` a `biscotto-project.md`. Allinea il `.gitignore`:

```bash
grep -n "project.md" .gitignore
```

Se la riga risulta ancora `consentkit-project.md` (il `.gitignore` è fra i file modificati, quindi dovrebbe essere già a posto), correggila a mano in:

```
# --- Documento di progetto interno (siti pilota, analisi, rollout) ---
biscotto-project.md
```

Segnala all'utente, alla fine del task, che deve rinominare a mano il proprio file locale `consentkit-project.md` in `biscotto-project.md`: è gitignorato, quindi nessuno script lo tocca.

- [ ] **Step 5: Eseguire lo script di verifica**

Esegui: `bash tools/check-rename.sh`

Atteso: i tre controlli negativi ora **PASS** (`nessuna classe/identificatore ConsentKit`, `nessuna costante CONSENTKIT`, `nessun identificatore consentkit minuscolo`). Restano **FAIL** i controlli sull'identità pubblica (file principale, POT, text domain, nome plugin) e sulla versione: li chiudono i task 3 e 6.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor!: rinomina il layer PHP interno da ConsentKit a Biscotto

Classi ConsentKit_* -> Biscotto_*, costanti CONSENTKIT_* -> BISCOTTO_*,
option consentkit_settings -> biscotto_settings, tabella di log
consentkit_log -> biscotto_log, namespace REST consentkit/v1 -> biscotto/v1,
handle di enqueue e oggetti localize, nomi dei file delle classi.

Nessuna migrazione dei dati: le installazioni pilota ripartono da
configurazione vuota, come deciso nello spec.

BREAKING CHANGE: option, tabella di log e namespace REST cambiano nome; le
installazioni esistenti vanno riconfigurate.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Identità pubblica — slug, text domain, nome plugin

**Files:**
- Rename: `packages/wordpress/biscotto.php` → `packages/wordpress/biscotto-cookie-consent.php`
- Rename: `packages/wordpress/languages/biscotto.pot` → `packages/wordpress/languages/biscotto-cookie-consent.pot`
- Modify: `packages/wordpress/biscotto-cookie-consent.php` (header)
- Modify: `packages/wordpress/readme.txt:1`
- Modify: tutti i `.php` di `packages/wordpress` (text domain nelle stringhe tradotte)

**Interfaces:**
- Consumes: i nomi prodotti dal Task 2.
- Produces: text domain `biscotto-cookie-consent` usato da ogni stringa tradotta; file principale `biscotto-cookie-consent.php`.

- [ ] **Step 1: Rinominare file principale e POT**

```bash
git mv packages/wordpress/biscotto.php packages/wordpress/biscotto-cookie-consent.php
git mv packages/wordpress/languages/biscotto.pot packages/wordpress/languages/biscotto-cookie-consent.pot
```

- [ ] **Step 2: Sostituire il text domain in tutte le stringhe tradotte**

Il pattern è la stringa `'biscotto'` fra apici singoli, che nel codice PHP compare solo come secondo argomento delle funzioni di traduzione:

```bash
grep -rlZ "'biscotto'" packages/wordpress --include=*.php \
| xargs -0 sed -i "s/'biscotto'/'biscotto-cookie-consent'/g"
```

Verifica che non sia rimasto nulla e che il conteggio sia plausibile:

```bash
grep -rn "'biscotto'" packages/wordpress --include=*.php | wc -l   # atteso: 0
grep -rn "'biscotto-cookie-consent'" packages/wordpress --include=*.php | wc -l   # atteso: ~64
```

- [ ] **Step 3: Aggiornare l'header del plugin**

In `packages/wordpress/biscotto-cookie-consent.php`, l'header deve diventare (nome accorciato e text domain allineato allo slug; il resto delle righe resta invariato):

```php
/**
 * Plugin Name:       Biscotto – Cookie Consent
 * Plugin URI:        https://github.com/renatoromano-png/Biscotto
 * Description:       GDPR/ePrivacy cookie consent compliant with the Italian DPA (Garante) guidelines: Google Consent Mode v2, GTM and LinkedIn. No page or CPT limits.
 * Version:           1.5.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Food & Tech
 * Author URI:        https://github.com/renatoromano-png
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       biscotto-cookie-consent
 * Domain Path:       /languages
 *
 * @package Biscotto
 */
```

La riga `Version:` resta `1.5.0`: il bump a 1.5.3 è del Task 6.

- [ ] **Step 4: Aggiornare l'intestazione del readme.txt**

In `packages/wordpress/readme.txt`, riga 1:

```
=== Biscotto – Cookie Consent ===
```

- [ ] **Step 5: Aggiornare l'header del file POT**

In `packages/wordpress/languages/biscotto-cookie-consent.pot`, sostituisci nell'intestazione ogni occorrenza del vecchio nome progetto e del vecchio text domain con `biscotto-cookie-consent`:

```bash
sed -i 's/biscotto\.pot/biscotto-cookie-consent.pot/g; s/X-Domain: biscotto$/X-Domain: biscotto-cookie-consent/' packages/wordpress/languages/biscotto-cookie-consent.pot
grep -n "X-Domain\|Project-Id-Version" packages/wordpress/languages/biscotto-cookie-consent.pot
```

Atteso: `X-Domain: biscotto-cookie-consent`. Se le righe hanno forma diversa da quella assunta, correggile a mano allo stesso risultato.

- [ ] **Step 6: Eseguire lo script di verifica**

Esegui: `bash tools/check-rename.sh`

Atteso: tutti i controlli **PASS** tranne `versione attesa '1.5.3'`, che resta necessariamente FAIL fino al Task 6: header, costante e readme dicono ancora 1.5.0, e il bump alla 1.5.3 richiesta dal rilascio avviene solo lì.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "refactor!: slug e text domain diventano biscotto-cookie-consent

Nome plugin accorciato a 'Biscotto - Cookie Consent' e text domain allineato
allo slug richiesto a WordPress.org, come da comunicazioni del 12 luglio.
File principale e POT rinominati di conseguenza.

BREAKING CHANGE: cartella del plugin e text domain cambiano nome.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: `/log` — rotta condizionale, rate limit, deduplica

Risponde direttamente all'obiezione del reviewer: il limite alla scrittura su database non deve dipendere dal nonce.

**Files:**
- Modify: `packages/wordpress/includes/class-biscotto-api.php`

**Interfaces:**
- Consumes: `Biscotto::get_settings()`, `Biscotto_Api::table_name()`, `Biscotto_Api::TABLE` dal Task 2.
- Produces: costanti `Biscotto_Api::RATE_LIMIT_MAX` (int, 10) e `Biscotto_Api::DEDUPE_WINDOW` (int, secondi, 86400). Il Task 5 aggiunge metodi alla stessa classe.

- [ ] **Step 1: Aggiungere le costanti di soglia**

In `packages/wordpress/includes/class-biscotto-api.php`, subito sotto `const TABLE = 'biscotto_log';`:

```php
	/** Scritture massime consentite a uno stesso indirizzo IP in un'ora. */
	const RATE_LIMIT_MAX = 10;

	/**
	 * Righe massime scrivibili nella finestra, a livello di sito.
	 *
	 * Non e' un contatore di richieste: si misura direttamente quante righe
	 * esistono gia' nella tabella. Contare le righe invece delle richieste
	 * evita che richieste rifiutate consumino la quota, non dipende da un
	 * object cache persistente e non e' aggirabile con la concorrenza, perche'
	 * la quantita' misurata e' proprio quella che l'abuso fa crescere.
	 *
	 * Regolabile con il filtro `biscotto_write_ceiling`.
	 */
	const WRITE_CEILING_MAX = 2000;

	/** Finestra del tetto sulle scritture, in secondi. */
	const WRITE_CEILING_WINDOW = HOUR_IN_SECONDS;

	/**
	 * Finestra di deduplica, in secondi.
	 *
	 * Attenzione: la finestra effettiva e' piu' corta di cosi'. Il pseudo_id
	 * include un salt che ruota a mezzanotte UTC, quindi due richieste a
	 * cavallo della mezzanotte producono pseudo_id diversi e non vengono mai
	 * riconosciute come duplicate. La finestra reale va da 0 a 24 ore a
	 * seconda dell'ora in cui arriva la prima richiesta. E' accettabile: la
	 * deduplica riduce il volume, non e' un controllo di sicurezza — quello e'
	 * il rate limit.
	 */
	const DEDUPE_WINDOW = DAY_IN_SECONDS;
```

- [ ] **Step 2: Rendere condizionale la registrazione della rotta**

Sostituisci integralmente il metodo `register_routes()` con:

```php
	public function register_routes() {
		$settings = Biscotto::get_settings();

		// Il log dei consensi e' opzionale e spento di default: se e' spento la
		// rotta non viene registrata affatto e una richiesta riceve 404.
		if ( empty( $settings['log_enabled'] ) ) {
			return;
		}

		register_rest_route(
			'biscotto/v1',
			'/log',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'log_consent' ),
				// Endpoint pubblico per necessita': lo chiamano visitatori anonimi
				// via navigator.sendBeacon per registrare il proprio consenso, quindi
				// non esiste un utente da autorizzare e permission_callback e'
				// __return_true. Il nonce nel body copre il CSRF, non l'abuso: il
				// limite reale alla scrittura su database e' dato dal rate limit per
				// indirizzo IP, dal tetto globale, dalla deduplica a 24 ore e dalla
				// retention periodica.
				'permission_callback' => '__return_true',
			)
		);
	}
```

- [ ] **Step 3: Riordinare i controlli in `log_consent()` e inserire rate limit e deduplica**

Sostituisci integralmente il metodo `log_consent()` con:

```php
	/**
	 * Salva un record di consenso pseudonimizzato.
	 *
	 * Ordine dei controlli: log attivo, nonce (CSRF), rate limit, allowlist
	 * delle azioni, deduplica, insert. Il rate limit precede la deduplica
	 * perche' altrimenti la query di deduplica sarebbe eseguibile senza limite.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return WP_REST_Response
	 */
	public function log_consent( $request ) {
		$settings = Biscotto::get_settings();
		if ( empty( $settings['log_enabled'] ) ) {
			return new WP_REST_Response( array( 'logged' => false ), 200 );
		}

		$params = $request->get_json_params();

		// Nonce nel body: sendBeacon non puo' impostare header custom. Protegge
		// dal CSRF; non e' una barriera di autorizzazione, perche' e' ottenibile
		// da qualunque visitatore anonimo.
		$nonce = isset( $params['nonce'] ) ? sanitize_text_field( $params['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_REST_Response( array( 'logged' => false, 'error' => 'invalid_nonce' ), 403 );
		}

		// Pseudo-ID: hash non reversibile di IP + user agent + salt giornaliero.
		// Permette de-duplica e rate limit senza memorizzare dati identificativi.
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ua     = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$salt   = wp_salt( 'auth' ) . gmdate( 'Y-m-d' );
		$pseudo = hash( 'sha256', $ip . '|' . $ua . '|' . $salt );

		// Rate limit per indirizzo IP. La chiave NON deve contenere lo user
		// agent ne' altri header: sono scelti dal chiamante, che potrebbe
		// variarli a ogni richiesta per ripartire sempre da un contatore
		// vuoto. Falsificare REMOTE_ADDR richiede invece di completare un
		// handshake TCP. L'IP viene comunque passato per hash col salt
		// giornaliero: non serve conservarlo in chiaro.
		$rl_key = 'biscotto_rl_' . hash( 'sha256', $ip . '|' . $salt );
		if ( ! $this->within_limit( $rl_key, self::RATE_LIMIT_MAX, HOUR_IN_SECONDS ) ) {
			return new WP_REST_Response( array( 'logged' => false, 'error' => 'rate_limited' ), 429 );
		}

		$action     = isset( $params['action'] ) ? sanitize_text_field( $params['action'] ) : '';
		$policy     = isset( $params['policyVersion'] ) ? sanitize_text_field( $params['policyVersion'] ) : '';
		$categories = isset( $params['categories'] ) && is_array( $params['categories'] ) ? $params['categories'] : array();

		$allowed = array( 'granted_all', 'rejected_all', 'custom', 'default_kept' );
		if ( ! in_array( $action, $allowed, true ) ) {
			return new WP_REST_Response( array( 'logged' => false, 'error' => 'invalid_action' ), 400 );
		}

		global $wpdb;
		$table = self::table_name();

		// Deduplica: stessa scelta, stessa versione di policy, stesso visitatore
		// entro la finestra -> nessuna riga nuova.
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::DEDUPE_WINDOW );
		$exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE pseudo_id = %s AND policy_version = %s AND `action` = %s AND created_at > %s LIMIT 1",
				$pseudo,
				$policy,
				$action,
				$cutoff
			)
		);

		if ( '' !== $wpdb->last_error ) {
			return new WP_REST_Response( array( 'logged' => false, 'error' => 'db_error' ), 500 );
		}

		if ( $exists ) {
			return new WP_REST_Response( array( 'logged' => false, 'reason' => 'duplicate' ), 200 );
		}

		// Tetto sulle scritture: si contano le righe gia' presenti nella
		// finestra, non le richieste ricevute. Sta qui, dopo la deduplica,
		// perche' misura cio' che stiamo per aggiungere davvero.
		$ceiling = (int) apply_filters( 'biscotto_write_ceiling', self::WRITE_CEILING_MAX );
		$written = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at > %s",
				gmdate( 'Y-m-d H:i:s', time() - self::WRITE_CEILING_WINDOW )
			)
		);

		if ( '' !== $wpdb->last_error ) {
			return new WP_REST_Response( array( 'logged' => false, 'error' => 'db_error' ), 500 );
		}

		if ( $written >= $ceiling ) {
			// Codice distinto dal 429 per IP: qui il log si e' fermato per
			// tutto il sito, non per un singolo visitatore. Il flag permette
			// al pannello di segnalarlo, altrimenti la mancanza di righe
			// resterebbe l'unico sintomo.
			set_transient( 'biscotto_write_ceiling_hit', time(), WEEK_IN_SECONDS );

			return new WP_REST_Response( array( 'logged' => false, 'error' => 'write_ceiling' ), 429 );
		}

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'created_at'     => current_time( 'mysql', true ),
				'pseudo_id'      => $pseudo,
				'policy_version' => $policy,
				'action'         => $action,
				'categories'     => wp_json_encode( $categories ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_REST_Response( array( 'logged' => false, 'error' => 'db_error' ), 500 );
		}

		return new WP_REST_Response( array( 'logged' => true ), 201 );
	}

	/**
	 * Incrementa un contatore a finestra e dice se si e' entro la soglia.
	 *
	 * Concorrenza: leggere-confrontare-scrivere non e' atomico, quindi
	 * richieste simultanee possono leggere lo stesso valore e far avanzare il
	 * contatore di uno invece che di N, superando la soglia. Con un object
	 * cache persistente si usa wp_cache_incr, che e' atomico; senza, il
	 * transient e' l'unica opzione offerta da WordPress e la soglia va intesa
	 * come approssimata per eccesso.
	 *
	 * @param string $key    Chiave del contatore.
	 * @param int    $max    Soglia oltre la quale si rifiuta.
	 * @param int    $window Durata della finestra, in secondi.
	 * @return bool True se la richiesta rientra nella soglia.
	 */
	private function within_limit( $key, $max, $window ) {
		if ( wp_using_ext_object_cache() ) {
			$hits = wp_cache_incr( $key, 1, 'biscotto' );
			if ( false === $hits ) {
				wp_cache_set( $key, 1, 'biscotto', $window );
				$hits = 1;
			}
			return $hits <= $max;
		}

		$hits = (int) get_transient( $key );
		if ( $hits >= $max ) {
			return false;
		}
		set_transient( $key, $hits + 1, $window );

		return true;
	}
```

Nota: `action` è backtickato nella query perché è una parola chiave MySQL.

- [ ] **Step 3b (opzionale, solo se PHP è installato): lint**

Esegui: `php -l packages/wordpress/includes/class-biscotto-api.php`
Atteso: `No syntax errors detected`.

- [ ] **Step 4: Verifica funzionale su WordPress**

Su un'installazione WordPress pulita con `WP_DEBUG` a `true`, con il plugin attivo:

1. **Log disattivato** (default). Da terminale:
   ```bash
   curl -s -o /dev/null -w "%{http_code}\n" -X POST https://SITO/wp-json/biscotto/v1/log
   ```
   Atteso: `404`.

2. **Log attivato** in Impostazioni → Biscotto → Integrazioni. Apri il sito in una finestra anonima, accetta i cookie dal banner. In Adminer/phpMyAdmin:
   ```sql
   SELECT COUNT(*) FROM wp_biscotto_log;
   ```
   Atteso: `1`.

3. **Deduplica.** Cancella il cookie di consenso nel browser e ripeti la stessa scelta.
   Atteso: il conteggio resta `1`.

4. **Rate limit.** Ripeti la richiesta 12 volte con la stessa scelta, variando lo User-Agent a ogni chiamata (per verificare che il limite sia per IP e non aggirabile cambiando header):
   ```bash
   for i in $(seq 1 12); do
     curl -s -o /dev/null -w "%{http_code} " -X POST https://SITO/wp-json/biscotto/v1/log \
       -H 'Content-Type: application/json' \
       -H "User-Agent: curl-test-$i-$RANDOM" \
       -d '{"nonce":"NONCE_DALLA_PAGINA","action":"granted_all","policyVersion":"2026-07","categories":[]}'
   done; echo
   ```
   Atteso: i primi 10 rispondono `201`, dall'undicesimo in poi `429`. Variando lo User-Agent ogni `pseudo_id` è diverso, quindi la deduplica non può mai scattare e non si vedranno `200`: ogni richiesta entro soglia scrive davvero una riga. È proprio questo a rendere il test significativo — prima della correzione ogni User-Agent nuovo azzerava il contatore e tutte e 12 le richieste avrebbero risposto `201`, inserendo 12 righe. Ora la chiave del rate limit è l'IP, che non cambia, quindi il limite scatta.

Il `NONCE_DALLA_PAGINA` si legge dal sorgente della pagina pubblica, in `biscottoConfig.logNonce`.

- [ ] **Step 5: Commit**

```bash
git add packages/wordpress/includes/class-biscotto-api.php
git commit -m "fix(wordpress): limiti reali di scrittura sull'endpoint REST /log

Risponde al rilievo WordPress.org: il nonce wp_rest e' ottenibile da qualunque
visitatore anonimo e non costituisce una barriera di autorizzazione, quindi
l'endpoint scriveva su database senza alcun limite.

- la rotta viene registrata solo se il log dei consensi e' attivo (spento di
  default): altrimenti risponde 404;
- rate limit di 10 scritture all'ora per pseudo_id, con risposta 429;
- deduplica a 24 ore su pseudo_id + policy_version + action;
- commenti riscritti: il nonce e' protezione CSRF, non autorizzazione.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: `/log` — retention configurabile

**Files:**
- Modify: `packages/wordpress/includes/class-biscotto-api.php`
- Modify: `packages/wordpress/includes/class-biscotto-consent.php:61` (defaults)
- Modify: `packages/wordpress/includes/class-biscotto-admin.php:191` (sanitizzazione)
- Modify: `packages/wordpress/admin/views/settings-integrations.php:56` (interfaccia)
- Modify: `packages/wordpress/biscotto-cookie-consent.php` (attivazione e disattivazione)

**Interfaces:**
- Consumes: `Biscotto_Api::table_name()`, `Biscotto::get_settings()`, l'impostazione `log_enabled`.
- Produces: impostazione `log_retention_months` (int, default 12); hook cron `biscotto_prune_log`; metodo `Biscotto_Api::prune_log()`; costante `Biscotto_Api::DEFAULT_RETENTION_MONTHS` (int, 12).

- [ ] **Step 1: Aggiungere costante, hook e indice sullo schema**

In `class-biscotto-api.php`, aggiungi la costante sotto `DEDUPE_WINDOW`:

```php
	/** Mesi di conservazione dei record di log, se non configurato altrimenti. */
	const DEFAULT_RETENTION_MONTHS = 12;
```

Nel costruttore, aggancia la potatura al cron:

```php
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'biscotto_prune_log', array( $this, 'prune_log' ) );
	}
```

In `maybe_create_log_table()`, aggiungi l'indice su `created_at`, necessario sia alla deduplica sia alla potatura. Lo schema completo diventa:

```php
		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			pseudo_id CHAR(64) NOT NULL,
			policy_version VARCHAR(32) NOT NULL,
			action VARCHAR(20) NOT NULL,
			categories TEXT NOT NULL,
			PRIMARY KEY (id),
			KEY pseudo_id (pseudo_id),
			KEY created_at (created_at)
		) {$charset};";
```

- [ ] **Step 2: Implementare la potatura**

Aggiungi in fondo alla classe `Biscotto_Api`, prima della graffa di chiusura:

```php
	/**
	 * Elimina i record di log piu' vecchi della finestra di conservazione.
	 * Eseguito una volta al giorno dall'evento cron biscotto_prune_log.
	 *
	 * @return int Numero di record eliminati.
	 */
	public function prune_log() {
		$settings = Biscotto::get_settings();
		$months   = isset( $settings['log_retention_months'] )
			? absint( $settings['log_retention_months'] )
			: self::DEFAULT_RETENTION_MONTHS;

		if ( $months < 1 ) {
			$months = self::DEFAULT_RETENTION_MONTHS;
		}

		global $wpdb;
		$table  = self::table_name();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $months * MONTH_IN_SECONDS ) );

		return (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff )
		);
	}
```

- [ ] **Step 3: Aggiungere il default dell'impostazione**

In `class-biscotto-consent.php`, nella sezione log di `default_settings()`, sostituisci:

```php
			// --- Log consensi (server-side, opzionale) ---
			'log_enabled'          => 0,
```

con:

```php
			// --- Log consensi (server-side, opzionale) ---
			'log_enabled'          => 0,
			'log_retention_months' => 12,          // conservazione dei record di consenso
```

- [ ] **Step 4: Sanitizzare l'impostazione**

In `class-biscotto-admin.php`, subito dopo la riga `$out['log_enabled'] = empty( $input['log_enabled'] ) ? 0 : 1;`:

```php
		$out['log_retention_months'] = isset( $input['log_retention_months'] )
			? min( 120, max( 1, absint( $input['log_retention_months'] ) ) )
			: $out['log_retention_months'];
```

Limiti: minimo 1 mese, massimo 120 (dieci anni), a prova di valore vuoto o assurdo inviato dal form.

- [ ] **Step 5: Aggiungere il campo nell'interfaccia admin**

In `packages/wordpress/admin/views/settings-integrations.php`, subito dopo la riga di chiusura del blocco che contiene la checkbox `log_enabled` (riga 56 e dintorni), aggiungi:

```php
			<p>
				<label for="biscotto_log_retention_months">
					<?php esc_html_e( 'Conserva i record di consenso per (mesi)', 'biscotto-cookie-consent' ); ?>
				</label><br />
				<input type="number" min="1" max="120" step="1"
					id="biscotto_log_retention_months"
					name="<?php echo esc_attr( $biscotto_opt ); ?>[log_retention_months]"
					value="<?php echo esc_attr( $settings['log_retention_months'] ); ?>" />
				<span class="description">
					<?php esc_html_e( "I record piu' vecchi vengono eliminati automaticamente ogni giorno. Minimizzazione dei dati: conserva solo per il tempo necessario a dimostrare il consenso.", 'biscotto-cookie-consent' ); ?>
				</span>
			</p>
```

Attenzione: la variabile dell'option in questa vista si chiama `$biscotto_opt` (era `$consentkit_opt` prima del Task 2). Verifica il nome effettivo con `grep -n 'biscotto_opt' packages/wordpress/admin/views/settings-integrations.php` e usa quello.

- [ ] **Step 6: Schedulare e rimuovere l'evento cron**

In `packages/wordpress/biscotto-cookie-consent.php`, sostituisci la funzione di attivazione e aggiungi quella di disattivazione:

```php
/**
 * All'attivazione: pre-popola le impostazioni con i default (cookie registry
 * incluso), crea la tabella di log e schedula la potatura giornaliera.
 */
function biscotto_activate() {
	if ( false === get_option( BISCOTTO_OPTION ) ) {
		add_option( BISCOTTO_OPTION, Biscotto_Consent::default_settings() );
	}
	Biscotto_Api::maybe_create_log_table();

	if ( ! wp_next_scheduled( 'biscotto_prune_log' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'biscotto_prune_log' );
	}
}
register_activation_hook( __FILE__, 'biscotto_activate' );

/**
 * Alla disattivazione: rimuove l'evento cron di potatura del log.
 * I dati restano: la rimozione e' compito di uninstall.php.
 */
function biscotto_deactivate() {
	wp_clear_scheduled_hook( 'biscotto_prune_log' );
}
register_deactivation_hook( __FILE__, 'biscotto_deactivate' );
```

- [ ] **Step 7: Verifica funzionale su WordPress**

Con il plugin **riattivato** (l'evento cron si schedula in attivazione, quindi disattiva e riattiva):

1. Impostazioni → Biscotto → Integrazioni mostra il campo "Conserva i record di consenso per (mesi)" con valore `12`. Salva `6` e ricarica: il valore resta `6`.
2. Prova i limiti: salva `0` → deve tornare `1`; salva `999` → deve tornare `120`.
3. Verifica che l'evento sia schedulato. Con WP-CLI:
   ```bash
   wp cron event list | grep biscotto_prune_log
   ```
   Atteso: una riga con ricorrenza `daily`.
4. Prova la potatura: inserisci a mano un record vecchio e forza l'esecuzione.
   ```sql
   INSERT INTO wp_biscotto_log (created_at, pseudo_id, policy_version, action, categories)
   VALUES ('2020-01-01 00:00:00', REPEAT('a',64), '2020-01', 'granted_all', '[]');
   ```
   ```bash
   wp cron event run biscotto_prune_log
   ```
   ```sql
   SELECT COUNT(*) FROM wp_biscotto_log WHERE created_at = '2020-01-01 00:00:00';
   ```
   Atteso: `0`.
5. Disattiva il plugin e verifica che l'evento sparisca:
   ```bash
   wp cron event list | grep biscotto_prune_log
   ```
   Atteso: nessuna riga.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat(wordpress): retention configurabile per il log dei consensi

Evento cron giornaliero biscotto_prune_log che elimina i record oltre la
finestra di conservazione (default 12 mesi, impostabile da 1 a 120 nel
pannello Integrazioni). Schedulato in attivazione, rimosso in disattivazione.

Aggiunge KEY created_at alla tabella di log: serve alla potatura e alla query
di deduplica. Oltre a limitare la crescita della tabella, la retention
risponde al principio di minimizzazione dei dati.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 6: Versione 1.5.3 e packaging

**Files:**
- Modify: `packages/wordpress/biscotto-cookie-consent.php` (header `Version:`, costante)
- Modify: `packages/wordpress/readme.txt` (`Stable tag`, changelog)
- Modify: `tools/package.sh`
- Modify: `tools/package.ps1`

**Interfaces:**
- Consumes: `bash tools/check-rename.sh` dal Task 1, che verifica la coerenza della versione.
- Produces: `dist/biscotto-cookie-consent/` e `dist/biscotto-cookie-consent.zip`.

- [ ] **Step 1: Portare la versione a 1.5.3**

In `packages/wordpress/biscotto-cookie-consent.php`, header e costante:

```php
 * Version:           1.5.3
```

```php
define( 'BISCOTTO_VERSION', '1.5.3' );
```

In `packages/wordpress/readme.txt`:

```
Stable tag: 1.5.3
```

- [ ] **Step 2: Aggiungere la voce di changelog**

In `packages/wordpress/readme.txt`, sotto `== Changelog ==`, come prima voce:

```
= 1.5.3 =
* Plugin renamed to "Biscotto – Cookie Consent"; slug and text domain are now `biscotto-cookie-consent`.
* Internal PHP layer renamed to Biscotto (classes, constants, option, log table, REST namespace). Existing installations must be reconfigured.
* Consent log endpoint hardened: the route is registered only when the log is enabled, with a per-visitor rate limit, 24-hour de-duplication and automatic retention.
* New setting: consent log retention period (default 12 months).
```

- [ ] **Step 3: Aggiornare `tools/package.sh`**

Sostituisci le occorrenze del vecchio slug. Il file diventa, nelle righe interessate:

```bash
DIST="$ROOT/dist"
PLUGIN="$DIST/biscotto-cookie-consent"
```

```bash
# 2) Assembla la cartella del plugin (nome cartella = slug = biscotto-cookie-consent).
```

```bash
if command -v zip >/dev/null; then
  zip -rq biscotto-cookie-consent.zip biscotto-cookie-consent
  echo "  ✓ dist/biscotto-cookie-consent.zip creato"
```

- [ ] **Step 4: Aggiornare `tools/package.ps1`**

Nelle righe interessate:

```powershell
$plugin = Join-Path $dist 'biscotto-cookie-consent'
```

```powershell
# 2) Assembla la cartella del plugin (nome cartella = slug = biscotto-cookie-consent).
```

```powershell
$zipPath = Join-Path $dist 'biscotto-cookie-consent.zip'
```

E nei due commenti d'intestazione:

```powershell
# Produce: dist\biscotto-cookie-consent\  (cartella installabile via FTP)
#          dist\biscotto-cookie-consent.zip (upload da Plugin -> Aggiungi nuovo, o submission WP.org)
```

- [ ] **Step 5: Eseguire lo script di verifica**

Esegui: `bash tools/check-rename.sh`

Atteso: **tutti PASS**, exit 0, con la riga `PASS: versione 1.5.3 coerente ovunque`.

- [ ] **Step 6: Costruire il pacchetto e verificarne la struttura**

```bash
powershell -ExecutionPolicy Bypass -File tools/package.ps1
ls dist/
unzip -l dist/biscotto-cookie-consent.zip | head -20
```

Atteso: esiste `dist/biscotto-cookie-consent/` e `dist/biscotto-cookie-consent.zip`; tutte le voci dello zip iniziano con `biscotto-cookie-consent/`; fra queste compaiono `biscotto-cookie-consent/biscotto-cookie-consent.php` e `biscotto-cookie-consent/readme.txt`.

Se `unzip` non è disponibile:

```powershell
powershell -Command "Add-Type -AssemblyName System.IO.Compression.FileSystem; [System.IO.Compression.ZipFile]::OpenRead((Resolve-Path 'dist\biscotto-cookie-consent.zip')).Entries | Select-Object -First 20 FullName"
```

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "chore(wordpress): bump 1.5.3 e packaging con lo slug biscotto-cookie-consent

Versione allineata fra header, costante BISCOTTO_VERSION e Stable tag; la
1.5.2 e' gia' stata ricevuta da WordPress.org, quindi si sale a 1.5.3.
package.sh e package.ps1 emettono dist/biscotto-cookie-consent/ e
biscotto-cookie-consent.zip.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 7: Documentazione, `.gitignore` e risposta alla review

**Files:**
- Modify: `packages/wordpress/readme.txt` (sezione FAQ / log)
- Modify: `.gitignore`
- Create: `wporg-review-reply-2.txt`
- Delete: `wporg-review-reply.txt`

**Interfaces:**
- Consumes: il comportamento implementato nei task 4 e 5.
- Produces: il testo della risposta, che l'utente invierà a mano.

- [ ] **Step 1: Documentare i limiti del log nel readme**

In `packages/wordpress/readme.txt`, nella sezione `== Frequently Asked Questions ==`, aggiungi in fondo:

```
= How is the optional consent log protected from abuse? =

The log is disabled by default. When it is disabled, the REST route is not registered at all. When enabled, the route is public by necessity — anonymous visitors record their own consent through it via `navigator.sendBeacon`, so there is no user to authorize — but writes are bounded: each visitor is identified by a pseudonymous, non-reversible hash of IP, user agent and a daily salt, limited to 10 writes per hour, and identical choices are de-duplicated within 24 hours. Records older than the configured retention period (12 months by default) are deleted daily.
```

- [ ] **Step 2: Ignorare la corrispondenza email**

In `.gitignore`, prima della sezione `# --- Sistema / editor ---`:

```
# --- Corrispondenza con WordPress.org (non pubblicare) ---
*.eml
```

Verifica che i due file non risultino più fra gli untracked:

```bash
git status --short
```

Atteso: nessuna riga `?? ...eml`.

- [ ] **Step 3: Scrivere la risposta alla review**

Crea `wporg-review-reply-2.txt`. Il file è di lavoro e resta locale: aggiungilo al `.gitignore` insieme al precedente se preferisci non versionarlo.

```
Reply to: WordPress Plugin Directory <plugins@wordpress.org>
Subject: Re: [WordPress Plugin Directory] Review in Progress: ConsentKit
Review ID: R foodandtech-cookie-consent-manager/renatosaka/18Jul26/T1
(Reply ABOVE the "Please reply above this line" marker in the original email.)

---------------------------------------------------------------------------

Hi,

Thanks for the detailed review. I've uploaded a corrected version (1.5.3).

1) Consent log endpoint (permission_callback)

You're right that the wp_rest nonce is obtainable by any anonymous visitor and
therefore is not an authorization boundary. The endpoint has to stay public —
anonymous visitors record their own consent through it via navigator.sendBeacon,
so there is no user to authorize — but it no longer writes to the database
without a real limit:

- the route is registered only when the consent log is enabled (it is disabled
  by default), so on a default install the endpoint does not exist and returns
  404;
- each visitor is identified by a non-reversible SHA-256 hash of IP, user agent
  and a daily salt, and is limited to 10 writes per hour (429 beyond that);
- identical submissions (same visitor, same policy version, same choice) are
  de-duplicated within a 24-hour window and write nothing;
- records are deleted automatically by a daily cron job once they exceed the
  configured retention period (12 months by default).

The nonce is still verified, but only as CSRF protection; the code comments now
say so explicitly, instead of presenting it as an access control.

2) Calling files remotely

I believe this one is a false positive. The three lines you cited in
class-biscotto-scanner.php (formerly class-consentkit-scanner.php) are entries
in a static lookup table that maps a hostname to a vendor name, a cookie
category and the vendor's privacy policy URL. The plugin never requests
anything from those hosts: it scans the HTML the site itself produces, and uses
this table to recognise and classify the third-party resources that are already
there, so the administrator can build an accurate cookie policy. There is no
wp_remote_get, no enqueue and no iframe pointing at them — only string
comparisons against hostnames found in the site's own markup.

The only outbound request the plugin makes is the on-demand GitHub API call
documented under "External services" in readme.txt, which happens solely when an
administrator clicks "Check for database updates".

3) Slug

Please reserve the slug "biscotto-cookie-consent" (display name "Biscotto –
Cookie Consent") instead of "foodandtech-cookie-consent-manager", as in my
previous messages. The text domain matches that slug throughout.

Tested on a clean WordPress install with WP_DEBUG enabled; Plugin Check reports
no errors or warnings.

Thanks!

Renato
```

- [ ] **Step 4: Rimuovere la bozza superata**

```bash
git rm --cached wporg-review-reply.txt 2>/dev/null || true
rm -f wporg-review-reply.txt
```

La bozza precedente conteneva l'argomento "public by design" già respinto: va eliminata per non rischiare di inviarla per sbaglio.

- [ ] **Step 5: Verifica finale complessiva**

```bash
bash tools/check-rename.sh
git status --short
```

Atteso: `check-rename: OK` con exit 0; nessun file `.eml` fra gli untracked.

Poi, sull'installazione WordPress pulita con `WP_DEBUG` a `true`, ripercorri la checklist dello spec:

- Plugin Check senza errori né warning;
- nessun notice PHP in `wp-content/debug.log` dopo attivazione, configurazione e visita del sito;
- banner funzionante, scansione cookie funzionante dopo il rename di handle e oggetti localize;
- salvataggio impostazioni funzionante con la nuova option `biscotto_settings`;
- i quattro casi dell'endpoint log (404 a log spento, insert, duplicato, 429).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "docs(wordpress): documenta i limiti del log e prepara la risposta alla review

Nuova FAQ nel readme che spiega perche' l'endpoint di log e' pubblico e quali
limiti ne governano la scrittura. Aggiunge *.eml al .gitignore: la
corrispondenza con WordPress.org non va versionata. Sostituisce la bozza di
risposta precedente, che ripeteva l'argomento gia' respinto.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Dopo il piano

L'invio della mail e il caricamento dello zip su WordPress.org restano a carico dell'utente: nessun task li automatizza.

Merge su `main` e tag di release sono fuori ambito, da decidere dopo l'esito della review.
