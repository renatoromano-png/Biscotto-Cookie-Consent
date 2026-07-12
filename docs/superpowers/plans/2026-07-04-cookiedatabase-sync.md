# Cookie Database Sync + Copy-Code Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add cookie-database enrichment (bundled Open Cookie Database), an
opt-in manual dataset-update check, and a one-click cookie-page shortcode
copy box to the ConsentKit WordPress plugin, then ship as v1.4.0.

**Architecture:** A new standalone PHP class (`ConsentKit_Cookie_Database`)
parses a vendored CSV into an in-memory lookup index (exact name, wildcard
prefix, domain) and maps its 6 category labels onto ConsentKit's 4 consent
categories. Two new admin-only REST routes on the existing scanner
(`/scan/enrich`, `/scan/db-version`) expose this to two new buttons in the
Scan tab. The Cookies tab gets a small copy-to-clipboard box for the
existing `[consentkit_cookie_policy]` shortcode. No changes to the frontend
consent-manager runtime or `packages/core`.

**Tech Stack:** PHP 7.4+ (WordPress plugin, `packages/wordpress`), vanilla JS
(no build step, admin-only scripts), WordPress REST API, `wp_remote_get`.

## Global Constraints

- No PHPUnit/test harness exists in this repo — verification is PHP lint
  (`php -l`, PHP 8.3 via the Laragon binary already used in this project) +
  a standalone CLI test script (no WordPress bootstrap) for pure logic +
  manual browser verification on the local WP install for REST/UI wiring.
- PHP lint command used throughout: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" -l <file>`
- Local WP install for manual testing: `C:\laragon\www\jopistacchio` (served
  at `http://jopistacchio.test/`, requires Laragon running). Deploy a
  changed file with:
  `robocopy "packages\wordpress" "C:\laragon\www\jopistacchio\wp-content\plugins\consentkit" /E` (exit code ≤ 1 means success, robocopy convention — do not treat exit code 1 as a failure).
- Never use `Compress-Archive` for the installable zip — always
  `tools/package.ps1` (.NET `ZipFile`, forward slashes). See memory
  `consentkit-zip-packaging`.
- `packages/core` (JS/CSS single source of truth) is untouched by this plan
  — none of these features touch consent-manager runtime behavior.
- Never overwrite a field the admin/classifier already filled — enrichment
  only fills fields that are empty (see Task 3 for the exact per-field
  rule).
- The "Controlla aggiornamenti database" call is the plugin's *only*
  external call, must be admin-triggered only (no cron, no automatic
  background checks), and must be documented in `readme.txt`'s FAQ (Task 7).

---

### Task 1: Vendor the Open Cookie Database CSV + attribution

**Files:**
- Create: `packages/wordpress/includes/data/open-cookie-database.csv`
- Create: `packages/wordpress/includes/data/NOTICE.md`

**Interfaces:**
- Produces: a CSV file at this exact path with header row
  `ID,Platform,Category,Cookie / Data Key name,Domain,Description,Retention period,Data Controller,User Privacy & GDPR Rights Portals,Wildcard match`
  (2264 data rows as of this snapshot; verified via direct download, not an
  AI-summarized fetch). Task 2 depends on this exact header.

- [ ] **Step 1: Create the data directory and download the CSV**

Run:
```bash
mkdir -p "packages/wordpress/includes/data"
curl -s --max-time 20 "https://raw.githubusercontent.com/jkwakman/Open-Cookie-Database/master/open-cookie-database.csv" -o "packages/wordpress/includes/data/open-cookie-database.csv"
```

- [ ] **Step 2: Verify the download — header line and row count**

Run:
```bash
head -c 200 "packages/wordpress/includes/data/open-cookie-database.csv"
wc -l "packages/wordpress/includes/data/open-cookie-database.csv"
```
Expected: first line starts with
`ID,Platform,Category,Cookie / Data Key name,Domain,Description,Retention period,Data Controller,User Privacy & GDPR Rights Portals,Wildcard match`
and the line count is in the low thousands (2264 data rows + 1 header = 2265
lines, at the time this plan was written — an upstream update is fine, the
column order is what matters).

- [ ] **Step 3: Write the attribution notice**

Create `packages/wordpress/includes/data/NOTICE.md`:
```markdown
# Open Cookie Database — vendored snapshot

This directory bundles a copy of the **Open Cookie Database**
(`open-cookie-database.csv`), used by ConsentKit's cookie scanner to fill in
service name, category, retention period and privacy-policy link for cookies
the built-in classifier doesn't recognize by itself.

- Source: https://github.com/jkwakman/Open-Cookie-Database
- License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
- Snapshot date: 2026-07-04
- File: `open-cookie-database.csv` (unmodified from upstream `master`)

This is a static, manually-updated snapshot. ConsentKit does not
automatically download or update this file. The plugin's "Check for
database updates" button (Settings → ConsentKit → Scan) makes an on-demand,
admin-triggered call to the public GitHub API to check whether a newer
snapshot exists upstream — see `class-consentkit-cookie-database.php` and
`class-consentkit-scanner.php`.
```

- [ ] **Step 4: Commit**

```bash
git add packages/wordpress/includes/data/open-cookie-database.csv packages/wordpress/includes/data/NOTICE.md
git commit -m "chore(wordpress): vendor Open Cookie Database CSV (Apache-2.0)"
```

---

### Task 2: `ConsentKit_Cookie_Database` lookup class + standalone test

**Files:**
- Create: `packages/wordpress/includes/class-consentkit-cookie-database.php`
- Test: `tests/test-cookie-database.php`

**Interfaces:**
- Consumes: a CSV file path (Task 1's file in real usage; a small fixture
  file in the test).
- Produces (used by Task 3):
  - `ConsentKit_Cookie_Database::build_index( string $csv_path ): array` —
    returns `array( 'exact' => array, 'wildcard' => array, 'domain' => array )`,
    each entry keyed by lowercase name/domain, value shape
    `array( 'service' => string, 'category' => string, 'duration' => string, 'url_policy' => string )`.
  - `ConsentKit_Cookie_Database::lookup_cookie( string $name, array $index ): ?array`
  - `ConsentKit_Cookie_Database::lookup_domain( string $host, array $index ): ?array`
  - `ConsentKit_Cookie_Database::map_category( string $csv_category ): string`
    (returns one of `necessary|analytics|marketing|preferences`)
  - `ConsentKit_Cookie_Database::SNAPSHOT_DATE` constant (string `'2026-07-04'`,
    must match Task 1's NOTICE.md date), used by Task 5.

- [ ] **Step 1: Write the standalone test script (fails first — class doesn't exist yet)**

Create `tests/test-cookie-database.php`:
```php
<?php
/**
 * Standalone test for ConsentKit_Cookie_Database — no WordPress bootstrap.
 * Run: php tests/test-cookie-database.php
 */

define( 'ABSPATH', __DIR__ . '/' ); // satisfies the class file's guard

require_once __DIR__ . '/../packages/wordpress/includes/class-consentkit-cookie-database.php';

$failures = 0;

function ck_assert( $cond, $label ) {
	global $failures;
	if ( $cond ) {
		echo "PASS: $label\n";
	} else {
		echo "FAIL: $label\n";
		$failures++;
	}
}

// --- Fixture CSV -----------------------------------------------------
$fixture = sys_get_temp_dir() . '/consentkit-test-ocd.csv';
$csv     = <<<CSV
ID,Platform,Category,Cookie / Data Key name,Domain,Description,Retention period,Data Controller,User Privacy & GDPR Rights Portals,Wildcard match
1,Test Analytics,Analytics,_ga_test,,Test analytics cookie,2 years,TestCo,https://example.com/privacy,0
2,Test Consent,Functional,TestConsentBulk-,,Bulk consent wildcard cookie,1 year,TestCo,https://example.com/privacy,1
3,Test Fonts,Personalization,,fonts.testcdn.com (3rd party),Test font domain,,TestCo,https://example.com/privacy,0
4,Test Security,Security,__test_csrf,,CSRF token,session,TestCo,https://example.com/privacy,0
5,Test Legacy,Necessary,legacy_session,,Legacy necessary cookie,session,TestCo,,0
CSV;
file_put_contents( $fixture, $csv );

$index = ConsentKit_Cookie_Database::build_index( $fixture );

// --- Exact cookie match ------------------------------------------------
$m = ConsentKit_Cookie_Database::lookup_cookie( '_ga_test', $index );
ck_assert( null !== $m, 'exact cookie match found' );
ck_assert( 'Test Analytics' === $m['service'], 'exact match service' );
ck_assert( 'analytics' === $m['category'], 'exact match category (Analytics -> analytics)' );
ck_assert( '2 years' === $m['duration'], 'exact match duration' );
ck_assert( 'https://example.com/privacy' === $m['url_policy'], 'exact match url_policy' );

// --- Wildcard cookie match (prefix) ------------------------------------
$m = ConsentKit_Cookie_Database::lookup_cookie( 'TestConsentBulk-XYZ123', $index );
ck_assert( null !== $m, 'wildcard cookie match found' );
ck_assert( 'necessary' === $m['category'], 'wildcard match category (Functional -> necessary)' );

// --- Case-insensitive match ---------------------------------------------
$m = ConsentKit_Cookie_Database::lookup_cookie( '_GA_TEST', $index );
ck_assert( null !== $m, 'cookie match is case-insensitive' );

// --- No match ------------------------------------------------------------
$m = ConsentKit_Cookie_Database::lookup_cookie( 'totally_unknown_cookie', $index );
ck_assert( null === $m, 'unknown cookie returns null' );

// --- Domain match, with "(3rd party)" suffix cleaned --------------------
$m = ConsentKit_Cookie_Database::lookup_domain( 'fonts.testcdn.com', $index );
ck_assert( null !== $m, 'domain match found (suffix stripped)' );
ck_assert( 'preferences' === $m['category'], 'domain match category (Personalization -> preferences)' );

// --- Domain match via subdomain -----------------------------------------
$m = ConsentKit_Cookie_Database::lookup_domain( 'assets.fonts.testcdn.com', $index );
ck_assert( null !== $m, 'subdomain matches a bare domain entry' );

// --- Security -> necessary -----------------------------------------------
$m = ConsentKit_Cookie_Database::lookup_cookie( '__test_csrf', $index );
ck_assert( null !== $m && 'necessary' === $m['category'], 'Security category maps to necessary' );

// --- Literal "Necessary" category value ---------------------------------
$m = ConsentKit_Cookie_Database::lookup_cookie( 'legacy_session', $index );
ck_assert( null !== $m && 'necessary' === $m['category'], 'literal "Necessary" category maps to necessary' );

// --- map_category direct ---------------------------------------------------
ck_assert( 'marketing' === ConsentKit_Cookie_Database::map_category( 'Marketing' ), 'map_category Marketing' );
ck_assert( 'necessary' === ConsentKit_Cookie_Database::map_category( 'Something Unknown' ), 'map_category unknown falls back to necessary' );

unlink( $fixture );

echo "\n" . ( $failures ? "$failures FAILING" : 'ALL PASSING' ) . "\n";
exit( $failures ? 1 : 0 );
```

- [ ] **Step 2: Run the test to verify it fails (class doesn't exist yet)**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" tests/test-cookie-database.php`
Expected: fatal error — `Class "ConsentKit_Cookie_Database" not found`.

- [ ] **Step 3: Implement the class**

Create `packages/wordpress/includes/class-consentkit-cookie-database.php`:
```php
<?php
/**
 * Open Cookie Database (vendored, Apache-2.0) — lookup usato per arricchire
 * i suggerimenti dello scanner quando il classificatore interno non
 * riconosce un cookie/dominio (§14.6). Nessuna chiamata esterna qui: solo
 * parsing locale on-demand del CSV vendored (vedi includes/data/NOTICE.md).
 *
 * @package ConsentKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConsentKit_Cookie_Database {

	/** Data dello snapshot vendored (deve combaciare con includes/data/NOTICE.md). */
	const SNAPSHOT_DATE = '2026-07-04';

	/** Repo GitHub del dataset upstream (usato dal controllo aggiornamenti). */
	const GITHUB_REPO = 'jkwakman/Open-Cookie-Database';

	/** Percorso del file nel repo upstream (per l'endpoint /commits). */
	const GITHUB_CSV_PATH = 'open-cookie-database.csv';

	/**
	 * Parsa il CSV in un indice per lookup: match esatto, prefissi wildcard, domini.
	 * Nessuna cache tra richieste: il parsing gira solo quando serve
	 * (azione admin esplicita), non ad ogni page-load frontend.
	 *
	 * @param string $csv_path Percorso del CSV.
	 * @return array{exact: array, wildcard: array, domain: array}
	 */
	public static function build_index( $csv_path ) {
		$index = array(
			'exact'    => array(),
			'wildcard' => array(),
			'domain'   => array(),
		);

		$handle = @fopen( $csv_path, 'r' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- file può mancare, gestito sotto
		if ( ! $handle ) {
			return $index;
		}

		$header = fgetcsv( $handle );
		if ( ! is_array( $header ) ) {
			fclose( $handle );
			return $index;
		}

		while ( ( $cols = fgetcsv( $handle ) ) !== false ) {
			if ( count( $cols ) !== count( $header ) ) {
				continue; // riga malformata, salta
			}
			$row = array_combine( $header, $cols );

			$entry = array(
				'service'    => isset( $row['Platform'] ) ? trim( (string) $row['Platform'] ) : '',
				'category'   => self::map_category( isset( $row['Category'] ) ? $row['Category'] : '' ),
				'duration'   => isset( $row['Retention period'] ) ? trim( (string) $row['Retention period'] ) : '',
				'url_policy' => isset( $row['User Privacy & GDPR Rights Portals'] ) ? trim( (string) $row['User Privacy & GDPR Rights Portals'] ) : '',
			);

			$name = isset( $row['Cookie / Data Key name'] ) ? trim( (string) $row['Cookie / Data Key name'] ) : '';
			if ( '' !== $name ) {
				$is_wildcard = isset( $row['Wildcard match'] ) && '1' === trim( (string) $row['Wildcard match'] );
				$key         = strtolower( $name );
				if ( $is_wildcard ) {
					if ( ! isset( $index['wildcard'][ $key ] ) ) {
						$index['wildcard'][ $key ] = $entry;
					}
				} elseif ( ! isset( $index['exact'][ $key ] ) ) {
						$index['exact'][ $key ] = $entry;
				}
			}

			$domain = isset( $row['Domain'] ) ? self::clean_domain( $row['Domain'] ) : '';
			if ( '' !== $domain && ! isset( $index['domain'][ $domain ] ) ) {
				$index['domain'][ $domain ] = $entry;
			}
		}

		fclose( $handle );

		return $index;
	}

	/**
	 * Cerca un cookie per nome: match esatto (case-insensitive), poi prefisso wildcard.
	 *
	 * @param string $name  Nome cookie.
	 * @param array  $index Indice da build_index().
	 * @return array|null
	 */
	public static function lookup_cookie( $name, $index ) {
		$key = strtolower( trim( (string) $name ) );
		if ( '' === $key ) {
			return null;
		}
		if ( isset( $index['exact'][ $key ] ) ) {
			return $index['exact'][ $key ];
		}
		foreach ( $index['wildcard'] as $prefix => $entry ) {
			if ( '' !== $prefix && 0 === strpos( $key, $prefix ) ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * Cerca un dominio: match esatto o sottodominio di un dominio in indice.
	 *
	 * @param string $host  Host da cercare.
	 * @param array  $index Indice da build_index().
	 * @return array|null
	 */
	public static function lookup_domain( $host, $index ) {
		$host = strtolower( trim( (string) $host ) );
		if ( '' === $host ) {
			return null;
		}
		if ( isset( $index['domain'][ $host ] ) ) {
			return $index['domain'][ $host ];
		}
		foreach ( $index['domain'] as $domain => $entry ) {
			if ( self::ends_with( $host, '.' . $domain ) ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * Mappa le 6 categorie di Open Cookie Database sulle 4 categorie Garante
	 * di ConsentKit. Sconosciuta/vuota -> 'necessary' (prudente, l'admin rivede).
	 *
	 * @param string $csv_category Categoria come scritta nel CSV.
	 * @return string
	 */
	public static function map_category( $csv_category ) {
		$map = array(
			'functional'      => 'necessary',
			'necessary'       => 'necessary',
			'security'        => 'necessary', // CSRF/anti-bot/reCAPTCHA: protezioni tecniche, art. 122 Codice Privacy
			'analytics'       => 'analytics',
			'marketing'       => 'marketing',
			'personalization' => 'preferences',
		);
		$key = strtolower( trim( (string) $csv_category ) );
		return isset( $map[ $key ] ) ? $map[ $key ] : 'necessary';
	}

	/**
	 * Ripulisce la colonna Domain dai suffissi descrittivi upstream
	 * (es. "cookiebot.com (3rd party)"). Voci malformate che non terminano
	 * con la parentesi restano invariate: finiscono in indice ma non
	 * corrispondono a nessun host reale, innocue.
	 *
	 * @param string $raw Valore grezzo della colonna Domain.
	 * @return string Dominio pulito, lowercase, o stringa vuota.
	 */
	private static function clean_domain( $raw ) {
		$domain = trim( (string) $raw );
		$domain = preg_replace( '/\s*\([^)]*\)\s*$/', '', $domain );
		return strtolower( trim( (string) $domain ) );
	}

	/**
	 * @param string $haystack
	 * @param string $needle
	 * @return bool
	 */
	private static function ends_with( $haystack, $needle ) {
		$len = strlen( $needle );
		if ( 0 === $len ) {
			return true;
		}
		return substr( $haystack, -$len ) === $needle;
	}
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" tests/test-cookie-database.php`
Expected: every line starts with `PASS:`, final line `ALL PASSING`, exit code 0.

- [ ] **Step 5: Lint the new class**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" -l "packages/wordpress/includes/class-consentkit-cookie-database.php"`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add packages/wordpress/includes/class-consentkit-cookie-database.php tests/test-cookie-database.php
git commit -m "feat(wordpress): add ConsentKit_Cookie_Database lookup (Open Cookie Database)"
```

---

### Task 3: REST endpoint `/scan/enrich`

**Files:**
- Modify: `packages/wordpress/consentkit.php:29-34` (require the new class)
- Modify: `packages/wordpress/includes/class-consentkit-scanner.php:90-122` (register_routes)
- Modify: `packages/wordpress/includes/class-consentkit-scanner.php` (new methods, added after `import()`, i.e. after current line 326)

**Interfaces:**
- Consumes: `ConsentKit_Cookie_Database::build_index()`, `::lookup_cookie()`, `::lookup_domain()` (Task 2).
- Produces (used by Task 4): REST route `POST consentkit/v1/scan/enrich`.
  Request body: `{ suggestions: [ {name, service, category, duration, url_policy, source}, ... ] }`
  (same row shape scan.js already holds in `rowsData`). Response:
  `{ suggestions: [ ...same shape, some fields filled... ] }`, same length
  and order as input.

- [ ] **Step 1: Require the new class in the bootstrap file**

In `packages/wordpress/consentkit.php`, the require block currently reads
(lines 29-34):
```php
require_once CONSENTKIT_DIR . 'includes/class-consentkit-consent.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit-frontend.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit-admin.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit-api.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit-scanner.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit-shortcodes.php';
```
Add the new class require before the scanner (which depends on it):
```php
require_once CONSENTKIT_DIR . 'includes/class-consentkit-consent.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit-frontend.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit-admin.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit-api.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit-cookie-database.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit-scanner.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit-shortcodes.php';
```

- [ ] **Step 2: Register the new REST route**

In `packages/wordpress/includes/class-consentkit-scanner.php`, `register_routes()`
currently ends with the `/scan/server` route (closing around line 121-122).
Add a fourth route right after it, so the method reads:
```php
	public function register_routes() {
		$can_manage = array( $this, 'permission_check' );

		register_rest_route(
			'consentkit/v1',
			'/scan/collect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'collect' ),
				'permission_callback' => $can_manage,
			)
		);

		register_rest_route(
			'consentkit/v1',
			'/scan/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import' ),
				'permission_callback' => $can_manage,
			)
		);

		register_rest_route(
			'consentkit/v1',
			'/scan/server',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'scan_server' ),
				'permission_callback' => $can_manage,
			)
		);

		register_rest_route(
			'consentkit/v1',
			'/scan/enrich',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'enrich' ),
				'permission_callback' => $can_manage,
			)
		);
	}
```

- [ ] **Step 3: Add the `enrich()` callback and its private helper**

In `packages/wordpress/includes/class-consentkit-scanner.php`, add these two
methods right after the closing brace of the existing `import()` method
(currently ends at line 326, just before the
`// Classificatore (mappe interne...` section comment at line 328-330):
```php
	/**
	 * Completa i campi vuoti dei suggerimenti (non ancora importati) usando
	 * il database cookie bundlato (Open Cookie Database). Non sovrascrive
	 * mai un campo già valorizzato — vedi enrich_row().
	 *
	 * Body atteso: { suggestions: [ {name, service, category, duration, url_policy, source}, ... ] }
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return WP_REST_Response
	 */
	public function enrich( $request ) {
		$params = $request->get_json_params();
		$rows   = isset( $params['suggestions'] ) && is_array( $params['suggestions'] ) ? $params['suggestions'] : array();

		$csv_path = CONSENTKIT_DIR . 'includes/data/open-cookie-database.csv';
		$index    = ConsentKit_Cookie_Database::build_index( $csv_path );

		$out = array();
		foreach ( $rows as $row ) {
			$out[] = $this->enrich_row( is_array( $row ) ? $row : array(), $index );
		}

		return new WP_REST_Response( array( 'suggestions' => $out ), 200 );
	}

	/**
	 * Arricchisce una riga: se il classificatore interno non aveva
	 * riconosciuto il cookie/dominio (service vuoto), il match del database
	 * riempie servizio+categoria+durata+link insieme; se il servizio era
	 * già stato riconosciuto, il database riempie solo durata/link se ancora
	 * vuoti, senza mai toccare servizio/categoria già decisi.
	 *
	 * @param array $row   Riga suggerimento { name, service, category, duration, url_policy, source }.
	 * @param array $index Indice da ConsentKit_Cookie_Database::build_index().
	 * @return array Riga (eventualmente) arricchita.
	 */
	private function enrich_row( $row, $index ) {
		$name       = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
		$source     = isset( $row['source'] ) && 'domain' === $row['source'] ? 'domain' : 'cookie';
		$service    = isset( $row['service'] ) ? (string) $row['service'] : '';
		$category   = isset( $row['category'] ) ? (string) $row['category'] : '';
		$duration   = isset( $row['duration'] ) ? (string) $row['duration'] : '';
		$url_policy = isset( $row['url_policy'] ) ? (string) $row['url_policy'] : '';

		if ( '' === $name ) {
			return $row;
		}

		$match = 'domain' === $source
			? ConsentKit_Cookie_Database::lookup_domain( $name, $index )
			: ConsentKit_Cookie_Database::lookup_cookie( $name, $index );

		if ( null === $match ) {
			return $row;
		}

		// Solo campo vuoto: 'service' vuoto è l'unico caso in cui il
		// classificatore interno non aveva riconosciuto la riga, quindi solo
		// lì il database può anche decidere servizio+categoria.
		if ( '' === $service ) {
			$service  = $match['service'];
			$category = $match['category'];
		}
		if ( '' === $duration ) {
			$duration = $match['duration'];
		}
		if ( '' === $url_policy ) {
			$url_policy = $match['url_policy'];
		}

		return array(
			'name'       => $name,
			'service'    => $service,
			'category'   => $category,
			'duration'   => $duration,
			'url_policy' => $url_policy,
			'source'     => $source,
		);
	}
```

- [ ] **Step 4: Lint both modified files**

Run:
```bash
"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" -l "packages/wordpress/consentkit.php"
"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" -l "packages/wordpress/includes/class-consentkit-scanner.php"
```
Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Commit**

```bash
git add packages/wordpress/consentkit.php packages/wordpress/includes/class-consentkit-scanner.php
git commit -m "feat(wordpress): REST /scan/enrich endpoint (database enrichment, empty-fields-only)"
```

---

### Task 4: Admin UI — "Arricchisci dal database" button + Durata column

**Files:**
- Modify: `packages/wordpress/includes/class-consentkit-admin.php:57-94` (localize new URL + i18n)
- Modify: `packages/wordpress/admin/js/scan.js` (new function, renderRows changes, wiring)
- Modify: `packages/wordpress/admin/views/settings-scan.php:63-79` (new column, new button)

**Interfaces:**
- Consumes: `POST consentkit/v1/scan/enrich` (Task 3), `cfg.enrichUrl`, `cfg.i18n.*` (new keys below).
- Produces: nothing consumed by later tasks (this is a leaf UI task).

- [ ] **Step 1: Localize the new endpoint URL and strings**

In `packages/wordpress/includes/class-consentkit-admin.php`, inside
`enqueue_admin()`, the `wp_localize_script( 'consentkit-scan', 'ckScan', array(...) )`
call currently has (lines 60-93):
```php
				array(
					'scanNonce'  => ConsentKit_Scanner::scan_nonce(),
					'restNonce'  => wp_create_nonce( 'wp_rest' ),
					'collectUrl' => esc_url_raw( rest_url( 'consentkit/v1/scan/collect' ) ),
					'importUrl'  => esc_url_raw( rest_url( 'consentkit/v1/scan/import' ) ),
					'serverUrl'  => esc_url_raw( rest_url( 'consentkit/v1/scan/server' ) ),
					'origin'     => $origin,
					'timeoutMs'  => 12000,
					'maxUrls'    => 10,
					'categories' => array(
						'necessary'   => __( 'Necessari', 'consentkit' ),
						'analytics'   => __( 'Analytics', 'consentkit' ),
						'marketing'   => __( 'Marketing', 'consentkit' ),
						'preferences' => __( 'Preferenze', 'consentkit' ),
					),
					'i18n'       => array(
						'scanningServer' => __( 'Analisi rapida delle pagine…', 'consentkit' ),
						'scanningHome'   => __( 'Analisi a runtime della homepage…', 'consentkit' ),
						'classifying'  => __( 'Classificazione dei risultati…', 'consentkit' ),
						'done'         => __( 'Scansione completata.', 'consentkit' ),
						'error'        => __( 'Si è verificato un errore.', 'consentkit' ),
						'noUrls'       => __( 'Inserisci almeno un URL.', 'consentkit' ),
						'nothing'      => __( 'Nessun cookie o servizio di terze parti rilevato.', 'consentkit' ),
						'noneSelected' => __( 'Nessuna riga selezionata.', 'consentkit' ),
						'importing'    => __( 'Importazione…', 'consentkit' ),
						/* translators: %d: numero di voci aggiunte al registro. */
						'imported'     => __( '%d voci aggiunte al registro. Ricarica il tab Cookie per vederle.', 'consentkit' ),
						'sourceCookie' => __( 'Cookie', 'consentkit' ),
						'sourceDomain' => __( 'Dominio', 'consentkit' ),
						'tooMany'      => __( 'Massimo 10 URL: ho scansionato i primi 10.', 'consentkit' ),
						/* translators: %d: numero di URL esterni ignorati. */
						'externalSkipped' => __( '%d URL esterni ignorati (si scansiona solo questo sito).', 'consentkit' ),
					),
				)
```
Replace it with (adds `enrichUrl` + 3 new i18n keys — the `dbVersionUrl` and
db-check strings are added in Task 5, not here):
```php
				array(
					'scanNonce'  => ConsentKit_Scanner::scan_nonce(),
					'restNonce'  => wp_create_nonce( 'wp_rest' ),
					'collectUrl' => esc_url_raw( rest_url( 'consentkit/v1/scan/collect' ) ),
					'importUrl'  => esc_url_raw( rest_url( 'consentkit/v1/scan/import' ) ),
					'serverUrl'  => esc_url_raw( rest_url( 'consentkit/v1/scan/server' ) ),
					'enrichUrl'  => esc_url_raw( rest_url( 'consentkit/v1/scan/enrich' ) ),
					'origin'     => $origin,
					'timeoutMs'  => 12000,
					'maxUrls'    => 10,
					'categories' => array(
						'necessary'   => __( 'Necessari', 'consentkit' ),
						'analytics'   => __( 'Analytics', 'consentkit' ),
						'marketing'   => __( 'Marketing', 'consentkit' ),
						'preferences' => __( 'Preferenze', 'consentkit' ),
					),
					'i18n'       => array(
						'scanningServer' => __( 'Analisi rapida delle pagine…', 'consentkit' ),
						'scanningHome'   => __( 'Analisi a runtime della homepage…', 'consentkit' ),
						'classifying'  => __( 'Classificazione dei risultati…', 'consentkit' ),
						'done'         => __( 'Scansione completata.', 'consentkit' ),
						'error'        => __( 'Si è verificato un errore.', 'consentkit' ),
						'noUrls'       => __( 'Inserisci almeno un URL.', 'consentkit' ),
						'nothing'      => __( 'Nessun cookie o servizio di terze parti rilevato.', 'consentkit' ),
						'noneSelected' => __( 'Nessuna riga selezionata.', 'consentkit' ),
						'importing'    => __( 'Importazione…', 'consentkit' ),
						/* translators: %d: numero di voci aggiunte al registro. */
						'imported'     => __( '%d voci aggiunte al registro. Ricarica il tab Cookie per vederle.', 'consentkit' ),
						'sourceCookie' => __( 'Cookie', 'consentkit' ),
						'sourceDomain' => __( 'Dominio', 'consentkit' ),
						'tooMany'      => __( 'Massimo 10 URL: ho scansionato i primi 10.', 'consentkit' ),
						/* translators: %d: numero di URL esterni ignorati. */
						'externalSkipped' => __( '%d URL esterni ignorati (si scansiona solo questo sito).', 'consentkit' ),
						'info'         => __( 'Info', 'consentkit' ),
						'enriching'    => __( 'Ricerca nel database…', 'consentkit' ),
						/* translators: %d: numero di campi completati. */
						'enriched'     => __( '%d campi completati dal database.', 'consentkit' ),
						'enrichedNone' => __( 'Nessun campo aggiuntivo trovato nel database.', 'consentkit' ),
					),
				)
```

- [ ] **Step 2: Add the Durata column and Info link to `renderRows()`**

In `packages/wordpress/admin/js/scan.js`, the `renderRows()` function
currently builds `tdName`/`tdService`/`tdCat`/`tdSource` and appends them
(lines 115-142). Replace that block:
```js
			var tdName = document.createElement( 'td' );
			tdName.textContent = row.name || '';

			var tdService = document.createElement( 'td' );
			tdService.textContent = row.service || '';

			var tdCat = document.createElement( 'td' );
			var sel = document.createElement( 'select' );
			sel.setAttribute( 'data-i', i );
			sel.className = 'ck-scan-cat';
			Object.keys( cfg.categories ).forEach( function ( slug ) {
				var opt = document.createElement( 'option' );
				opt.value = slug;
				opt.textContent = cfg.categories[ slug ];
				if ( slug === row.category ) { opt.selected = true; }
				sel.appendChild( opt );
			} );
			tdCat.appendChild( sel );

			var tdSource = document.createElement( 'td' );
			tdSource.textContent = row.source === 'domain' ? cfg.i18n.sourceDomain : cfg.i18n.sourceCookie;

			tr.appendChild( tdCheck );
			tr.appendChild( tdName );
			tr.appendChild( tdService );
			tr.appendChild( tdCat );
			tr.appendChild( tdSource );
			tbody.appendChild( tr );
```
with:
```js
			var tdName = document.createElement( 'td' );
			tdName.textContent = row.name || '';

			var tdService = document.createElement( 'td' );
			tdService.textContent = row.service || '';
			if ( row.url_policy ) {
				tdService.appendChild( document.createTextNode( ' ' ) );
				var infoLink = document.createElement( 'a' );
				infoLink.href = row.url_policy;
				infoLink.target = '_blank';
				infoLink.rel = 'noopener nofollow';
				infoLink.textContent = cfg.i18n.info;
				tdService.appendChild( infoLink );
			}

			var tdDuration = document.createElement( 'td' );
			tdDuration.textContent = row.duration || '';

			var tdCat = document.createElement( 'td' );
			var sel = document.createElement( 'select' );
			sel.setAttribute( 'data-i', i );
			sel.className = 'ck-scan-cat';
			Object.keys( cfg.categories ).forEach( function ( slug ) {
				var opt = document.createElement( 'option' );
				opt.value = slug;
				opt.textContent = cfg.categories[ slug ];
				if ( slug === row.category ) { opt.selected = true; }
				sel.appendChild( opt );
			} );
			tdCat.appendChild( sel );

			var tdSource = document.createElement( 'td' );
			tdSource.textContent = row.source === 'domain' ? cfg.i18n.sourceDomain : cfg.i18n.sourceCookie;

			tr.appendChild( tdCheck );
			tr.appendChild( tdName );
			tr.appendChild( tdService );
			tr.appendChild( tdDuration );
			tr.appendChild( tdCat );
			tr.appendChild( tdSource );
			tbody.appendChild( tr );
```
Also update the "no rows" placeholder colspan a few lines above (currently
`td.colSpan = 5;`) to `td.colSpan = 6;` since there are now 6 columns.

- [ ] **Step 3: Add the `enrichSuggestions()` function**

In `packages/wordpress/admin/js/scan.js`, add this function right after
`importSelected()` (which currently ends at line 225, just before the
`document.addEventListener( 'DOMContentLoaded', ...)` block):
```js
	function enrichSuggestions() {
		var status = $( 'ck-scan-enrich-status' );
		if ( !rowsData.length ) { return; }
		setStatus( status, cfg.i18n.enriching );
		rest( cfg.enrichUrl, { suggestions: rowsData } )
			.then( function ( res ) {
				var enriched = res && res.suggestions ? res.suggestions : rowsData;
				var changed = 0;
				enriched.forEach( function ( row, i ) {
					var before = rowsData[ i ] || {};
					if ( row.service !== before.service || row.duration !== before.duration ||
						row.url_policy !== before.url_policy || row.category !== before.category ) {
						changed++;
					}
				} );
				renderRows( enriched );
				setStatus( status, changed ? cfg.i18n.enriched.replace( '%d', changed ) : cfg.i18n.enrichedNone );
			} )
			.catch( function () { setStatus( status, cfg.i18n.error ); } );
	}
```

- [ ] **Step 4: Wire the new button**

In `packages/wordpress/admin/js/scan.js`, the `DOMContentLoaded` handler
currently reads (lines 227-237):
```js
	document.addEventListener( 'DOMContentLoaded', function () {
		var start = $( 'ck-scan-start' );
		if ( !start ) { return; }
		start.addEventListener( 'click', startScan );
		$( 'ck-scan-import' ).addEventListener( 'click', importSelected );
		$( 'ck-scan-checkall' ).addEventListener( 'change', function ( e ) {
			Array.prototype.forEach.call( document.querySelectorAll( '.ck-scan-pick' ), function ( cb ) {
				cb.checked = e.target.checked;
			} );
		} );
	} );
```
Add the enrich button wiring:
```js
	document.addEventListener( 'DOMContentLoaded', function () {
		var start = $( 'ck-scan-start' );
		if ( !start ) { return; }
		start.addEventListener( 'click', startScan );
		$( 'ck-scan-import' ).addEventListener( 'click', importSelected );
		$( 'ck-scan-enrich' ).addEventListener( 'click', enrichSuggestions );
		$( 'ck-scan-checkall' ).addEventListener( 'change', function ( e ) {
			Array.prototype.forEach.call( document.querySelectorAll( '.ck-scan-pick' ), function ( cb ) {
				cb.checked = e.target.checked;
			} );
		} );
	} );
```

- [ ] **Step 5: Add the Durata column header and the "Arricchisci" button to the view**

In `packages/wordpress/admin/views/settings-scan.php`, the results table
currently reads (lines 60-79):
```php
	<div id="ck-scan-results" hidden>
		<h2><?php esc_html_e( 'Risultati', 'consentkit' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Seleziona le righe da aggiungere al registro e verifica la categoria proposta. Le voci già presenti nel registro non vengono duplicate.', 'consentkit' ); ?></p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" id="ck-scan-checkall" /></th>
					<th><?php esc_html_e( 'Nome / Dominio', 'consentkit' ); ?></th>
					<th><?php esc_html_e( 'Servizio', 'consentkit' ); ?></th>
					<th><?php esc_html_e( 'Categoria', 'consentkit' ); ?></th>
					<th><?php esc_html_e( 'Origine', 'consentkit' ); ?></th>
				</tr>
			</thead>
			<tbody id="ck-scan-rows"></tbody>
		</table>
		<p>
			<button type="button" class="button button-primary" id="ck-scan-import"><?php esc_html_e( 'Aggiungi i selezionati al registro', 'consentkit' ); ?></button>
			<span id="ck-scan-import-status" class="ck-scan-status" aria-live="polite"></span>
		</p>
	</div>
```
Replace it with (adds Durata column and the enrich button/description
before the import button):
```php
	<div id="ck-scan-results" hidden>
		<h2><?php esc_html_e( 'Risultati', 'consentkit' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Seleziona le righe da aggiungere al registro e verifica la categoria proposta. Le voci già presenti nel registro non vengono duplicate.', 'consentkit' ); ?></p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" id="ck-scan-checkall" /></th>
					<th><?php esc_html_e( 'Nome / Dominio', 'consentkit' ); ?></th>
					<th><?php esc_html_e( 'Servizio', 'consentkit' ); ?></th>
					<th><?php esc_html_e( 'Durata', 'consentkit' ); ?></th>
					<th><?php esc_html_e( 'Categoria', 'consentkit' ); ?></th>
					<th><?php esc_html_e( 'Origine', 'consentkit' ); ?></th>
				</tr>
			</thead>
			<tbody id="ck-scan-rows"></tbody>
		</table>
		<p>
			<button type="button" class="button" id="ck-scan-enrich"><?php esc_html_e( 'Arricchisci dal database', 'consentkit' ); ?></button>
			<span id="ck-scan-enrich-status" class="ck-scan-status" aria-live="polite"></span>
		</p>
		<p class="description"><?php esc_html_e( 'Completa servizio, categoria, durata e link privacy usando Open Cookie Database incluso nel plugin (licenza Apache-2.0, nessuna chiamata esterna). Non sovrascrive mai un campo già compilato.', 'consentkit' ); ?></p>
		<p>
			<button type="button" class="button button-primary" id="ck-scan-import"><?php esc_html_e( 'Aggiungi i selezionati al registro', 'consentkit' ); ?></button>
			<span id="ck-scan-import-status" class="ck-scan-status" aria-live="polite"></span>
		</p>
	</div>
```

- [ ] **Step 6: Lint/check all three modified files**

Run:
```bash
"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" -l "packages/wordpress/includes/class-consentkit-admin.php"
"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" -l "packages/wordpress/admin/views/settings-scan.php"
node --check "packages/wordpress/admin/js/scan.js"
```
Expected: `No syntax errors detected` (both PHP files), no output from `node --check` (success).

- [ ] **Step 7: Manual browser verification**

Prerequisite: Laragon running, site reachable at `http://jopistacchio.test/`.

```bash
robocopy "packages\wordpress" "C:\laragon\www\jopistacchio\wp-content\plugins\consentkit" /E
```
Then in a browser: log into `http://jopistacchio.test/wp-admin/`, go to
Settings → ConsentKit → Scansione, run a scan, click "Arricchisci dal
database". Verify: a Durata column appears with values for recognized
cookies/services, service names with a known privacy URL show an "Info"
link, and the status text reports how many fields were completed. Re-click
the button a second time and confirm the status now says "Nessun campo
aggiuntivo trovato" (nothing left to fill).

- [ ] **Step 8: Commit**

```bash
git add packages/wordpress/includes/class-consentkit-admin.php packages/wordpress/admin/js/scan.js packages/wordpress/admin/views/settings-scan.php
git commit -m "feat(wordpress): 'Arricchisci dal database' button + Durata column (Scan tab)"
```

---

### Task 5: REST endpoint `/scan/db-version` + "Controlla aggiornamenti" button

**Files:**
- Modify: `packages/wordpress/includes/class-consentkit-scanner.php` (register_routes + new method)
- Modify: `packages/wordpress/includes/class-consentkit-admin.php` (localize `dbVersionUrl`, `githubUrl`, new i18n)
- Modify: `packages/wordpress/admin/js/scan.js` (new function + wiring)
- Modify: `packages/wordpress/admin/views/settings-scan.php` (new section + button)

**Interfaces:**
- Consumes: `ConsentKit_Cookie_Database::SNAPSHOT_DATE`, `::GITHUB_REPO`, `::GITHUB_CSV_PATH` (Task 2).
- Produces: REST route `GET consentkit/v1/scan/db-version`, response
  `{ bundled: 'YYYY-MM-DD', latest: 'YYYY-MM-DD'|null, update_available: bool, checked: bool }`.

- [ ] **Step 1: Add the `register_rest_route` call**

In `packages/wordpress/includes/class-consentkit-scanner.php`, extend the
`register_routes()` method written in Task 3 by adding a fifth route right
after `/scan/enrich`:
```php
		register_rest_route(
			'consentkit/v1',
			'/scan/db-version',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'db_version' ),
				'permission_callback' => $can_manage,
			)
		);
```

- [ ] **Step 2: Add the `db_version()` callback**

Add this method right after `enrich_row()` (added in Task 3):
```php
	/**
	 * Controllo manuale, avviato solo dall'admin, della data dell'ultimo
	 * commit upstream che ha toccato il CSV bundlato. Unica chiamata
	 * esterna del plugin: nessun dato personale o del sito viene inviato,
	 * solo una richiesta GET pubblica all'API di GitHub. Cachata 24h per
	 * non consumare il rate limit pubblico di GitHub.
	 *
	 * @return WP_REST_Response
	 */
	public function db_version() {
		$transient_key = 'consentkit_db_version_check';
		$cached        = get_transient( $transient_key );
		if ( is_array( $cached ) ) {
			return new WP_REST_Response( $cached, 200 );
		}

		$bundled = ConsentKit_Cookie_Database::SNAPSHOT_DATE;
		$url     = sprintf(
			'https://api.github.com/repos/%s/commits?path=%s&per_page=1',
			ConsentKit_Cookie_Database::GITHUB_REPO,
			ConsentKit_Cookie_Database::GITHUB_CSV_PATH
		);

		$resp = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				'user-agent' => 'ConsentKit-Scanner/' . CONSENTKIT_VERSION,
				'headers'    => array( 'Accept' => 'application/vnd.github+json' ),
			)
		);

		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			$result = array(
				'bundled'          => $bundled,
				'latest'           => null,
				'update_available' => false,
				'checked'          => false,
			);
			return new WP_REST_Response( $result, 200 );
		}

		$body        = json_decode( wp_remote_retrieve_body( $resp ), true );
		$latest_date = '';
		if ( is_array( $body ) && isset( $body[0]['commit']['committer']['date'] ) ) {
			$latest_date = substr( (string) $body[0]['commit']['committer']['date'], 0, 10 );
		}

		$result = array(
			'bundled'          => $bundled,
			'latest'           => '' !== $latest_date ? $latest_date : null,
			'update_available' => '' !== $latest_date && $latest_date > $bundled,
			'checked'          => true,
		);

		set_transient( $transient_key, $result, DAY_IN_SECONDS );

		return new WP_REST_Response( $result, 200 );
	}
```

- [ ] **Step 3: Lint the modified scanner class**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" -l "packages/wordpress/includes/class-consentkit-scanner.php"`
Expected: `No syntax errors detected`

- [ ] **Step 4: Localize the db-version endpoint and strings**

In `packages/wordpress/includes/class-consentkit-admin.php`, extend the
same `wp_localize_script` array from Task 4's Step 1 by adding
`dbVersionUrl` + `githubUrl` alongside the other URL keys, and 5 more i18n
keys:
```php
					'dbVersionUrl' => esc_url_raw( rest_url( 'consentkit/v1/scan/db-version' ) ),
					'githubUrl'    => 'https://github.com/' . ConsentKit_Cookie_Database::GITHUB_REPO . '/commits/master/' . ConsentKit_Cookie_Database::GITHUB_CSV_PATH,
```
(add these two lines right after the `'enrichUrl'` line), and in the `i18n`
array add:
```php
						'checkingDb'        => __( 'Verifica in corso…', 'consentkit' ),
						'dbUpToDate'        => __( 'Database aggiornato: nessun aggiornamento disponibile.', 'consentkit' ),
						/* translators: %s: data dell'ultimo aggiornamento upstream (AAAA-MM-GG). */
						'dbUpdateAvailable' => __( 'È disponibile un aggiornamento del database (ultima modifica upstream: %s).', 'consentkit' ),
						'dbGithubLink'      => __( 'Vedi su GitHub', 'consentkit' ),
						'dbCheckError'      => __( 'Impossibile verificare ora. Riprova più tardi.', 'consentkit' ),
```
(add these right after the `'enrichedNone'` line from Task 4).

- [ ] **Step 5: Add the `checkDbVersion()` function**

In `packages/wordpress/admin/js/scan.js`, add this function right after
`enrichSuggestions()` (Task 4):
```js
	function checkDbVersion() {
		var status = $( 'ck-scan-dbcheck-status' );
		status.textContent = '';
		setStatus( status, cfg.i18n.checkingDb );
		fetch( cfg.dbVersionUrl, {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.restNonce }
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				status.textContent = '';
				if ( !res || !res.checked ) {
					status.textContent = cfg.i18n.dbCheckError;
					return;
				}
				if ( res.update_available ) {
					status.appendChild( document.createTextNode( cfg.i18n.dbUpdateAvailable.replace( '%s', res.latest ) + ' ' ) );
					var link = document.createElement( 'a' );
					link.href = cfg.githubUrl;
					link.target = '_blank';
					link.rel = 'noopener nofollow';
					link.textContent = cfg.i18n.dbGithubLink;
					status.appendChild( link );
				} else {
					status.textContent = cfg.i18n.dbUpToDate;
				}
			} )
			.catch( function () { status.textContent = cfg.i18n.dbCheckError; } );
	}
```

- [ ] **Step 6: Wire the new button**

In `packages/wordpress/admin/js/scan.js`, extend the `DOMContentLoaded`
handler from Task 4's Step 4 by adding one more line after the
`$( 'ck-scan-enrich' )...` line:
```js
		$( 'ck-scan-dbcheck' ).addEventListener( 'click', checkDbVersion );
```

- [ ] **Step 7: Add the "Database cookie incluso" section to the view**

In `packages/wordpress/admin/views/settings-scan.php`, add this new section
right before the closing `<div id="ck-scan-frames" ...>` line (currently the
last content line before the closing `</div>` of the outer wrapper):
```php
	<hr />
	<h2><?php esc_html_e( 'Database cookie incluso', 'consentkit' ); ?></h2>
	<p class="description">
		<?php
		printf(
			/* translators: %s: data dello snapshot del database bundlato (AAAA-MM-GG). */
			esc_html__( 'Il plugin include una copia locale di Open Cookie Database (licenza Apache-2.0), aggiornata al %s. Usarla non invia alcun dato del tuo sito.', 'consentkit' ),
			esc_html( ConsentKit_Cookie_Database::SNAPSHOT_DATE )
		);
		?>
	</p>
	<p>
		<button type="button" class="button" id="ck-scan-dbcheck"><?php esc_html_e( 'Controlla aggiornamenti database', 'consentkit' ); ?></button>
		<span id="ck-scan-dbcheck-status" class="ck-scan-status" aria-live="polite"></span>
	</p>
	<p class="description"><?php esc_html_e( "Contatta l'API pubblica di GitHub (api.github.com) solo quando clicchi questo pulsante, per verificare la data dell'ultimo aggiornamento del dataset upstream. Nessun aggiornamento automatico, nessun dato del sito inviato.", 'consentkit' ); ?></p>

```

- [ ] **Step 8: Lint/check all modified files**

Run:
```bash
"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" -l "packages/wordpress/includes/class-consentkit-scanner.php"
"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" -l "packages/wordpress/includes/class-consentkit-admin.php"
"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" -l "packages/wordpress/admin/views/settings-scan.php"
node --check "packages/wordpress/admin/js/scan.js"
```
Expected: `No syntax errors detected` for all three PHP files, no output from `node --check`.

- [ ] **Step 9: Manual browser verification**

```bash
robocopy "packages\wordpress" "C:\laragon\www\jopistacchio\wp-content\plugins\consentkit" /E
```
In the browser, reload Settings → ConsentKit → Scansione. Scroll to
"Database cookie incluso", click "Controlla aggiornamenti database".
Verify: status shows "Verifica in corso…" then either "Database
aggiornato" or an "aggiornamento disponibile" message with a working
"Vedi su GitHub" link. Click the button again immediately — response should
be instant (served from the 24h transient cache), confirm via
`preview_network` or DevTools that no second outbound request delay occurs
(same response content).

- [ ] **Step 10: Commit**

```bash
git add packages/wordpress/includes/class-consentkit-scanner.php packages/wordpress/includes/class-consentkit-admin.php packages/wordpress/admin/js/scan.js packages/wordpress/admin/views/settings-scan.php
git commit -m "feat(wordpress): 'Controlla aggiornamenti database' button (Scan tab, first external call)"
```

---

### Task 6: "Copia codice" box (Cookies tab)

**Files:**
- Modify: `packages/wordpress/admin/views/settings-cookies.php:49-58` (replace description block) and its trailing `<script>` block (lines 90-126)

**Interfaces:**
- Consumes: nothing from earlier tasks (fully independent).
- Produces: nothing consumed by later tasks (leaf UI task).

- [ ] **Step 1: Replace the shortcode description block with a copy box**

In `packages/wordpress/admin/views/settings-cookies.php`, the current block
(lines 49-58) reads:
```php
<p class="description">
	<?php esc_html_e( 'Elenca qui i cookie/servizi che il sito usa davvero: popola il registro dal tab Scansione oppure aggiungili a mano. Per le terze parti basta servizio, categoria e link alla loro policy: non serve ogni singolo cookie (Garante).', 'consentkit' ); ?>
</p>

<p class="description">
	<?php esc_html_e( 'Per pubblicare questo elenco nella tua pagina cookie policy usa gli shortcode:', 'consentkit' ); ?>
	<code>[consentkit_cookie_table]</code> <?php esc_html_e( '(tabella cookie per categoria),', 'consentkit' ); ?>
	<code>[consentkit_consent_settings]</code> <?php esc_html_e( '(stato del consenso + pulsante per modificarlo),', 'consentkit' ); ?>
	<code>[consentkit_cookie_policy]</code> <?php esc_html_e( '(entrambi insieme).', 'consentkit' ); ?>
</p>
```
Replace it with:
```php
<p class="description">
	<?php esc_html_e( 'Elenca qui i cookie/servizi che il sito usa davvero: popola il registro dal tab Scansione oppure aggiungili a mano. Per le terze parti basta servizio, categoria e link alla loro policy: non serve ogni singolo cookie (Garante).', 'consentkit' ); ?>
</p>

<div class="ck-copy-code">
	<p class="description"><?php esc_html_e( 'Per pubblicare questo elenco nella tua pagina cookie policy, copia questo codice e incollalo nella pagina:', 'consentkit' ); ?></p>
	<p>
		<input type="text" id="ck-shortcode-copy" class="regular-text code" readonly="readonly" value="[consentkit_cookie_policy]" onclick="this.select();" />
		<button type="button" class="button" id="ck-copy-shortcode"><?php esc_html_e( 'Copia', 'consentkit' ); ?></button>
		<span id="ck-copy-status" class="ck-scan-status" aria-live="polite"></span>
	</p>
	<p class="description">
		<?php esc_html_e( 'In alternativa puoi comporre la pagina con gli shortcode singoli:', 'consentkit' ); ?>
		<code>[consentkit_cookie_table]</code> <?php esc_html_e( '(solo tabella cookie),', 'consentkit' ); ?>
		<code>[consentkit_consent_settings]</code> <?php esc_html_e( '(solo stato consenso + pulsante).', 'consentkit' ); ?>
	</p>
</div>
```

- [ ] **Step 2: Add the copy-to-clipboard handler to the view's inline script**

In `packages/wordpress/admin/views/settings-cookies.php`, the file ends with
an inline `<script>` block (currently lines 90-126) that wires up
`ck-add-cookie`, `ck-remove-row` and `ck-clear-cookies`. Add the copy
handler inside the same IIFE, right after the `ck-clear-cookies` listener
block and before the closing `} )();`:
```js
	// Copia lo shortcode della cookie page negli appunti.
	var copyBtn = document.getElementById( 'ck-copy-shortcode' );
	if ( copyBtn ) {
		copyBtn.addEventListener( 'click', function () {
			var input = document.getElementById( 'ck-shortcode-copy' );
			var status = document.getElementById( 'ck-copy-status' );
			var announce = function () {
				if ( status ) { status.textContent = '<?php echo esc_js( __( 'Copiato!', 'consentkit' ) ); ?>'; }
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( input.value ).then( announce, function () {
					input.select();
					input.setSelectionRange( 0, 99999 );
					document.execCommand( 'copy' );
					announce();
				} );
			} else {
				input.select();
				input.setSelectionRange( 0, 99999 );
				document.execCommand( 'copy' );
				announce();
			}
		} );
	}
```

- [ ] **Step 3: Lint the modified view**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" -l "packages/wordpress/admin/views/settings-cookies.php"`
Expected: `No syntax errors detected`

- [ ] **Step 4: Manual browser verification**

```bash
robocopy "packages\wordpress" "C:\laragon\www\jopistacchio\wp-content\plugins\consentkit" /E
```
In the browser, go to Settings → ConsentKit → Cookie. Verify the readonly
field shows `[consentkit_cookie_policy]`, click "Copia", verify the status
text shows "Copiato!", then paste (Ctrl+V) into the browser's address bar
or any text field to confirm the clipboard actually contains the shortcode
text.

- [ ] **Step 5: Commit**

```bash
git add packages/wordpress/admin/views/settings-cookies.php
git commit -m "feat(wordpress): 'Copia codice' box for the cookie policy shortcode (Cookies tab)"
```

---

### Task 7: Version bump to 1.4.0, readme.txt catch-up, rebuild package

**Files:**
- Modify: `packages/wordpress/consentkit.php:6` and `:23` (version header + constant)
- Modify: `packages/wordpress/readme.txt` (Stable tag, Roadmap, FAQ, Changelog, Upgrade Notice, Description)

**Interfaces:**
- Consumes: nothing (final integration task).
- Produces: `dist/consentkit.zip` (not committed — `dist/` is gitignored).

Note: `readme.txt`'s Changelog/Upgrade Notice sections were last updated at
1.2.4, but the plugin has since shipped 1.3.0–1.3.3 (undocumented in
readme.txt). This task catches that history up alongside the new 1.4.0
entry so the release notes aren't missing four versions' worth of changes.

- [ ] **Step 1: Bump the version header and constant**

In `packages/wordpress/consentkit.php`, line 6 currently reads:
```
 * Version:           1.3.3
```
Change to:
```
 * Version:           1.4.0
```
Line 23 currently reads:
```php
define( 'CONSENTKIT_VERSION', '1.3.3' );
```
Change to:
```php
define( 'CONSENTKIT_VERSION', '1.4.0' );
```

- [ ] **Step 2: Update readme.txt Stable tag**

In `packages/wordpress/readme.txt`, line 7 currently reads:
```
Stable tag: 1.2.4
```
Change to:
```
Stable tag: 1.4.0
```

- [ ] **Step 3: Update the Description bullet list**

In `packages/wordpress/readme.txt`, the bullet list (around line 22-26) ends
with:
```
* Runtime cookie scanner: loads your pages in a hidden iframe (admin only) and detects the cookies and third-party domains actually loaded, then suggests registry entries to review and save.
```
Add two bullets right after it:
```
* Runtime cookie scanner: loads your pages in a hidden iframe (admin only) and detects the cookies and third-party domains actually loaded, then suggests registry entries to review and save.
* Cookie database enrichment: fills in missing service, category, retention period and privacy-policy link using a bundled copy of Open Cookie Database (Apache-2.0), with an optional manual check for dataset updates.
* One-click "copy code" box for building your cookie policy page from the plugin's shortcode.
```

- [ ] **Step 4: Update the Roadmap section**

In `packages/wordpress/readme.txt`, the Roadmap section (around line 32-35)
currently reads:
```
Roadmap (in progress):

* Automatic recognition and classification of the detected cookies through a public database (service, purpose, duration, category).
* Automatic blocking of iframes and embeds (Google Maps, YouTube) and Google Fonts with a "click to load" placeholder.
```
Replace with (the first bullet is now implemented, drop it):
```
Roadmap (in progress):

* Automatic blocking of iframes and embeds (Google Maps, YouTube) and Google Fonts with a "click to load" placeholder.
```

- [ ] **Step 5: Update the "external services" FAQ entry**

In `packages/wordpress/readme.txt`, the FAQ entry (around line 51-52)
currently reads:
```
= Does it send data to external services? =
No. ConsentKit does not communicate with any third-party server. It loads the Google (Consent Mode/GTM) and LinkedIn scripts only after consent and only if you configure them. The optional consent log stays in your site's database and is pseudonymized.
```
Replace with:
```
= Does it send data to external services? =
Only in one specific, opt-in case: the "Check for database updates" button (Settings → ConsentKit → Scan) contacts the public GitHub API only when you click it, to check whether the bundled Open Cookie Database snapshot is outdated. No personal or site data is sent — only a request for the latest commit date of a public file, cached for 24 hours. Everything else stays local: ConsentKit does not otherwise communicate with any third-party server. It loads the Google (Consent Mode/GTM) and LinkedIn scripts only after consent and only if you configure them. The optional consent log stays in your site's database and is pseudonymized.
```

- [ ] **Step 6: Add the Changelog entries (1.3.0 through 1.4.0)**

In `packages/wordpress/readme.txt`, the Changelog section currently starts
with:
```
== Changelog ==

= 1.2.4 =
```
Insert four new entries above it (newest first, so 1.4.0 goes at the very
top of the section, right after the `== Changelog ==` heading):
```
== Changelog ==

= 1.4.0 =
* New: "Enrich from database" button (Scan tab) fills in missing service, category, retention period and privacy-policy link for scan suggestions using a bundled copy of Open Cookie Database (Apache-2.0, no external calls). Never overwrites a field you already set.
* New: "Check for database updates" button (Scan tab) checks, only when you click it, whether a newer snapshot of Open Cookie Database is available upstream on GitHub. No automatic checks, no site data sent.
* New: "Copy code" box (Cookies tab) with the `[consentkit_cookie_policy]` shortcode ready to paste into your cookie policy page.

= 1.3.3 =
* Fixed: mobile action buttons now share equal width regardless of label length; "Manage preferences" moved to its own centered row below Accept/Reject. Landscape phones: reduced typography/padding so the banner fits in about half the screen instead of overflowing.

= 1.3.2 =
* Fixed: primary/link buttons now resist being restyled by the host theme (explicit color/background/border rules) so the accent color and auto-contrast text always render correctly regardless of theme CSS.

= 1.3.1 =
* Fixed: mobile banner text and buttons now actually scale up on the "Bottom bar" position (fixed a CSS specificity issue where base rules were overriding the compact-mode typography).

= 1.3.0 =
* New: banner position "Bottom-left box", mirroring "Bottom-right box".
* Improved: responsive banner behaviour is now driven by width/orientation/height instead of special cases — compact full-width bar on phones (any orientation) and portrait tablets, unchanged desktop appearance otherwise.
* New: automatic text contrast (WCAG relative luminance) when background/accent colors are customized and the text-color fields are left empty.

= 1.2.4 =
```
(leave the existing `= 1.2.4 =` entry and everything below it unchanged).

- [ ] **Step 7: Add the Upgrade Notice entry**

In `packages/wordpress/readme.txt`, the Upgrade Notice section currently
starts with:
```
== Upgrade Notice ==

= 1.2.0 =
```
Add the new entry above it:
```
== Upgrade Notice ==

= 1.4.0 =
Scan tab gains database-enrichment and update-check buttons; Cookies tab gains a one-click "copy shortcode" box for your cookie policy page.

= 1.2.0 =
```

- [ ] **Step 8: Lint the bootstrap file one more time**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" -l "packages/wordpress/consentkit.php"`
Expected: `No syntax errors detected`

- [ ] **Step 9: Rebuild the installable package**

Run (PowerShell):
```powershell
powershell -ExecutionPolicy Bypass -File tools\package.ps1
```
Expected output ends with `Pacchetto pronto: <path>\dist\consentkit.zip`.

- [ ] **Step 10: Verify the zip uses forward slashes and contains the new files**

Run (PowerShell):
```powershell
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead("dist\consentkit.zip")
$zip.Entries | Where-Object { $_.FullName -match 'cookie-database|open-cookie-database|consentkit.php' } | ForEach-Object { $_.FullName }
$zip.Dispose()
```
Expected: entries listed with forward slashes, including
`consentkit/consentkit.php`, `consentkit/includes/class-consentkit-cookie-database.php`,
`consentkit/includes/data/open-cookie-database.csv`.

- [ ] **Step 11: Commit**

```bash
git add packages/wordpress/consentkit.php packages/wordpress/readme.txt
git commit -m "chore(wordpress): bump version 1.4.0 (database enrichment, update check, copy-code)"
```

Note: `dist/` is gitignored — the zip is not committed. The user installs
it by uploading `dist/consentkit.zip` from wp-admin (Plugins → Add New →
Upload Plugin) or via FTP, same as previous releases.
