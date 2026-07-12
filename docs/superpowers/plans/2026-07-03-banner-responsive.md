# Banner responsive + box-left + auto-contrasto — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rendere il banner ConsentKit responsive (barra ~1/3 su telefoni e tablet verticale, ~1/2 su telefono orizzontale, posizione backend su desktop/tablet orizzontale), aggiungere la posizione "riquadro in basso a sinistra" e garantire il contrasto di testo e pulsanti su qualsiasi sfondo.

**Architecture:** Il comportamento responsive è guidato interamente da **media query CSS** (larghezza + orientamento + altezza) in `banner.css`: una "modalità compatta" appiattisce ogni posizione a barra in basso. La nuova posizione `box-left` è una regola CSS speculare a `box-right` più l'aggiunta della voce nei 4 punti che elencano le posizioni valide (select admin, sanitizzazione PHP, whitelist JS, commento default). L'auto-contrasto del testo è calcolato **lato PHP** (luminanza WCAG del colore di sfondo/accento) al momento dell'iniezione delle variabili CSS.

**Tech Stack:** WordPress plugin — PHP (no framework), CSS vanilla con custom properties `--ck-*`, JS vanilla. Nessun build step: `packages/wordpress/` è la fonte di verità; lo zip installabile si crea da lì.

## Global Constraints

- **Fonte di verità unica:** `packages/wordpress/`. NON modificare `dist/consentkit/` (copia scompattata stale v1.2.0, non tracciata da git).
- **Nessun test automatico** nel progetto: la verifica è manuale (DevTools responsive mode + admin WP) o statica (grep / listing zip). Ogni task elenca controlli concreti con esito atteso.
- **Versione target:** `1.2.4` → **`1.3.0`** (nuova feature). Va aggiornata in 2 punti di `packages/wordpress/consentkit.php`: header `Version:` e `define( 'CONSENTKIT_VERSION', ... )`.
- **Compatibilità colori:** i colori custom arrivano da `sanitize_hex_color()` → sempre `#rrggbb` opachi (nessun alpha).
- **Zip installabile:** radice `consentkit/`, entry con slash `/`. **Mai `Compress-Archive`** (scrive backslash → install rotta). Vedi memoria `consentkit-zip-packaging`.
- **Desktop invariato:** su desktop l'aspetto delle posizioni esistenti (inclusa la barra grande ~50vh introdotta in v1.2.3) non deve cambiare.
- Convenzioni di commit del repo: messaggi in italiano, prefisso tipo `feat(...)`/`fix(...)`, come nella history (es. `feat(wordpress): ...`).

---

### Task 1: Nuova posizione `box-left` (PHP + JS + CSS base)

Aggiunge la quarta posizione "Riquadro in basso a sinistra", speculare a `box-right`, in modalità estesa (desktop/tablet orizzontale). In modalità compatta diventerà barra come le altre (Task 2).

**Files:**
- Modify: `packages/wordpress/admin/views/settings-general.php` (~riga 90)
- Modify: `packages/wordpress/includes/class-consentkit-admin.php` (riga 133)
- Modify: `packages/wordpress/includes/class-consentkit-consent.php` (riga 44)
- Modify: `packages/wordpress/public/js/consent-manager.js` (riga 39)
- Modify: `packages/wordpress/public/css/banner.css` (dopo il blocco `.ck-box-right`, ~riga 62)

**Interfaces:**
- Consumes: nulla di task precedenti.
- Produces: valore posizione `'box-left'` accettato da sanitizzazione PHP e whitelist JS; classe CSS `.ck-box-left` sul nodo `.ck-banner`.

- [ ] **Step 1: Aggiungi l'opzione nel menu impostazioni**

In `packages/wordpress/admin/views/settings-general.php`, dopo la `<option>` di `box-right` (riga 90):

```php
				<option value="box-right" <?php selected( $settings['position'], 'box-right' ); ?>><?php esc_html_e( 'Riquadro in basso a destra', 'consentkit' ); ?></option>
				<option value="box-left" <?php selected( $settings['position'], 'box-left' ); ?>><?php esc_html_e( 'Riquadro in basso a sinistra', 'consentkit' ); ?></option>
```

- [ ] **Step 2: Aggiungi `box-left` alla whitelist di sanitizzazione PHP**

In `packages/wordpress/includes/class-consentkit-admin.php` riga 133, sostituisci l'array:

```php
			$out['position'] = in_array( $input['position'], array( 'bottom-bar', 'modal', 'box-right', 'box-left' ), true ) ? $input['position'] : 'bottom-bar';
```

- [ ] **Step 3: Aggiorna il commento delle posizioni valide nei default**

In `packages/wordpress/includes/class-consentkit-consent.php` riga 44:

```php
			'position'             => 'bottom-bar', // bottom-bar | modal | box-right | box-left
```

- [ ] **Step 4: Aggiungi `box-left` alla whitelist JS**

In `packages/wordpress/public/js/consent-manager.js` riga 39, sostituisci:

```js
  var position = (cfg.position === 'modal' || cfg.position === 'box-right' || cfg.position === 'box-left') ? cfg.position : 'bottom-bar';
```

- [ ] **Step 5: Aggiungi la regola CSS `.ck-box-left` (speculare a `.ck-box-right`)**

In `packages/wordpress/public/css/banner.css`, subito dopo il blocco `.ck-box-right .ck-actions .ck-btn-link { order: 1; }` (riga 62), inserisci:

```css
/* Riquadro compatto ancorato in basso a sinistra (desktop). */
.ck-box-left {
  left: 20px; bottom: 20px; right: auto;
  width: min(400px, calc(100% - 32px));
  border-radius: var(--ck-radius);
  box-shadow: 0 8px 32px rgba(0, 0, 0, .18);
}
.ck-box-left .ck-actions { flex-direction: column; align-items: stretch; }
.ck-box-left .ck-actions .ck-btn { width: 100%; text-align: center; }
.ck-box-left .ck-actions .ck-btn-link { order: 1; }
```

- [ ] **Step 6: Verifica (manuale, admin WP + desktop)**

1. In `wp-admin` → impostazioni ConsentKit: la tendina "Posizione banner" mostra 4 voci, l'ultima "Riquadro in basso a sinistra". Selezionala e salva.
2. Apri il sito su **desktop** (viewport ≥1025px), banner non ancora accettato: il riquadro compare **in basso a sinistra**, largo ~400px, pulsanti impilati. Esito atteso: identico al box-right ma speculare a sinistra.
3. Check statico rapido:

Run: `grep -rn "box-left" packages/wordpress/`
Expected: match in `settings-general.php`, `class-consentkit-admin.php`, `class-consentkit-consent.php`, `consent-manager.js`, `banner.css` (5 file).

- [ ] **Step 7: Commit**

```bash
git add packages/wordpress/admin/views/settings-general.php packages/wordpress/includes/class-consentkit-admin.php packages/wordpress/includes/class-consentkit-consent.php packages/wordpress/public/js/consent-manager.js packages/wordpress/public/css/banner.css
git commit -m "feat(wordpress): nuova posizione banner 'box-left' (riquadro in basso a sinistra)"
```

---

### Task 2: Modalità responsive compatta (telefoni + tablet verticale + telefono orizzontale)

Sostituisce il blocco responsive minimale attuale con il modello a due modalità. In modalità compatta ogni posizione (bottom-bar / modal / box-right / box-left) diventa una barra in basso a piena larghezza, alta ~1/3 (portrait) o ~1/2 (telefono landscape), con tipografia scalata.

**Files:**
- Modify: `packages/wordpress/public/css/banner.css` (blocco `@media (max-width: 600px)` righe 125-130)

**Interfaces:**
- Consumes: classi posizione `.ck-bottom-bar/.ck-modal/.ck-box-right/.ck-box-left` (Task 1).
- Produces: nessuna interfaccia per task successivi (solo CSS).

- [ ] **Step 1: Sostituisci il blocco `@media (max-width: 600px)` del banner**

In `packages/wordpress/public/css/banner.css`, sostituisci INTERAMENTE questo blocco (righe 125-130):

```css
@media (max-width: 600px) {
  .ck-actions { justify-content: stretch; }
  .ck-actions .ck-btn-primary { flex: 1 1 auto; }
  /* Su mobile il riquadro a destra diventa barra a piena larghezza in basso. */
  .ck-box-right { left: 16px; right: 16px; bottom: 16px; width: auto; }
}
```

con:

```css
/* --- Modalità COMPATTA -----------------------------------------------------
   Telefoni (ogni orientamento) + tablet in verticale: qualunque posizione
   scelta nel backend diventa una barra in basso a piena larghezza, alta ~1/3
   dello schermo, con testo e pulsanti scalati. Il telefono in orizzontale
   (viewport basso) usa ~1/2 per restare leggibile (regola dedicata sotto). */
@media (max-width: 600px),
       (min-width: 601px) and (max-width: 1024px) and (orientation: portrait),
       (orientation: landscape) and (max-height: 500px) {
  .ck-banner {
    left: 0; right: 0; bottom: 0; top: auto;
    transform: none;
    width: auto; max-width: none;
    border-left: 0; border-right: 0; border-bottom: 0;
    border-radius: 0;
    display: flex; flex-direction: column; justify-content: center; gap: 14px;
    min-height: 33vh; max-height: 70vh; overflow-y: auto;
    padding: clamp(20px, 5vw, 40px);
  }
  /* Tipografia scalata (vale per tutte le posizioni, non solo bottom-bar). */
  .ck-banner .ck-title { font-size: clamp(21px, 5.6vw, 28px); }
  .ck-banner .ck-text  { font-size: clamp(17px, 4.6vw, 20px); line-height: 1.55; }
  .ck-banner .ck-link  { font-size: clamp(14px, 3.6vw, 16px); }
  /* Azioni: barra a piena larghezza => neutralizza la colonna dei box d'angolo. */
  .ck-banner .ck-actions { flex-direction: row; flex-wrap: wrap; justify-content: stretch; align-items: center; }
  .ck-banner.ck-box-right .ck-actions,
  .ck-banner.ck-box-left  .ck-actions { flex-direction: row; }
  .ck-banner .ck-actions .ck-btn { font-size: clamp(17px, 4.4vw, 19px); padding: 13px 22px; }
  .ck-banner .ck-actions .ck-btn-primary { flex: 1 1 auto; }
  .ck-banner.ck-box-right .ck-actions .ck-btn,
  .ck-banner.ck-box-left  .ck-actions .ck-btn { width: auto; }
}

/* Telefono in orizzontale: barra più alta (~1/2) per leggibilità del testo. */
@media (orientation: landscape) and (max-height: 500px) {
  .ck-banner { min-height: 50vh; }
}
```

- [ ] **Step 2: Verifica (DevTools responsive mode)**

Apri il sito con il banner non accettato e il DevTools in modalità dispositivo. Per ciascun caso, esito atteso:

| Viewport | Orientamento | Esito atteso |
|---|---|---|
| iPhone (390×844) | verticale | Barra in basso piena larghezza, alta ~1/3, testo/pulsanti grandi |
| iPhone (844×390) | orizzontale | Barra in basso piena larghezza, alta ~1/2, testo leggibile |
| iPad (768×1024) | verticale | Barra in basso piena larghezza, alta ~1/3 |
| iPad (1024×768) | orizzontale | **Posizione backend** (es. box-right in basso a dx), dimensione normale |
| Desktop (1440×900) | — | Posizione backend invariata |

Ripeti cambiando la posizione nel backend tra bottom-bar / modal / box-right / box-left: in tutti i viewport "compatti" deve risultare sempre la stessa barra in basso.

- [ ] **Step 3: Commit**

```bash
git add packages/wordpress/public/css/banner.css
git commit -m "feat(wordpress): banner responsive - barra compatta ~1/3 su mobile e tablet verticale, ~1/2 su telefono orizzontale"
```

---

### Task 3: Auto-contrasto testo (PHP) + robustezza contrasto pulsanti (CSS)

Il colore del testo diventa automaticamente chiaro su fondo scuro e scuro su fondo chiaro (calcolo luminanza lato PHP), per testo del banner e testo dei pulsanti. Aggiunge una lieve ombra ai pulsanti pieni per separarli dallo sfondo.

**Files:**
- Modify: `packages/wordpress/includes/class-consentkit-frontend.php` (blocco colori righe 61-76, + nuovo metodo helper)
- Modify: `packages/wordpress/public/css/banner.css` (regola `.ck-btn-primary`, righe 76-80)

**Interfaces:**
- Consumes: `$s['primary_color']`, `$s['primary_text_color']`, `$s['bg_color']`, `$s['text_color']` (impostazioni esistenti).
- Produces: metodo `self::contrast_text_for( string $hex ): string` che ritorna `'#1f2937'` (scuro), `'#ffffff'` (chiaro) o `''` (hex non valido). Emette `--ck-text` / `--ck-primary-contrast` derivati quando l'admin li lascia vuoti.

- [ ] **Step 1: Aggiungi il metodo helper `contrast_text_for` alla classe frontend**

In `packages/wordpress/includes/class-consentkit-frontend.php`, aggiungi questo metodo privato **statico** dentro la classe (es. subito dopo `enqueue_assets()`, dopo la sua `}` di chiusura a riga 90):

```php
	/**
	 * Auto-contrasto (R6): dato un colore di sfondo esadecimale, restituisce
	 * il colore di testo che contrasta meglio — scuro su fondo chiaro, chiaro
	 * su fondo scuro. Usa la luminanza relativa WCAG. Ritorna '' se hex non valido.
	 *
	 * @param string $hex Colore esadecimale, es. '#1f2937'.
	 * @return string '#1f2937' | '#ffffff' | ''
	 */
	private static function contrast_text_for( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return '';
		}
		$channels = array(
			hexdec( substr( $hex, 0, 2 ) ) / 255,
			hexdec( substr( $hex, 2, 2 ) ) / 255,
			hexdec( substr( $hex, 4, 2 ) ) / 255,
		);
		foreach ( $channels as $i => $c ) {
			$channels[ $i ] = ( $c <= 0.03928 ) ? ( $c / 12.92 ) : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}
		$luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
		// Soglia ~0.22 = punto di crossover tra testo #1f2937 e #ffffff.
		return ( $luminance > 0.22 ) ? '#1f2937' : '#ffffff';
	}
```

- [ ] **Step 2: Applica l'auto-contrasto nel blocco iniezione colori**

In `packages/wordpress/includes/class-consentkit-frontend.php`, sostituisci il blocco righe 61-73 (da `// Colori personalizzati...` fino alla chiusura del `foreach`) con:

```php
		// Colori personalizzati via variabili CSS inline (vuoto = default/automatico).
		$primary          = sanitize_hex_color( $s['primary_color'] );
		$primary_contrast = sanitize_hex_color( $s['primary_text_color'] );
		$bg               = sanitize_hex_color( $s['bg_color'] );
		$text             = sanitize_hex_color( $s['text_color'] );

		// R6 — auto-contrasto: se il testo non è impostato ma lo sfondo sì,
		// scegli chiaro/scuro in base alla luminanza dello sfondo.
		if ( ! $text && $bg ) {
			$text = self::contrast_text_for( $bg );
		}
		// Testo dei pulsanti: se non impostato, deriva dall'accento (fondo pieno del bottone).
		if ( ! $primary_contrast && $primary ) {
			$primary_contrast = self::contrast_text_for( $primary );
		}

		$ck_vars = array(
			'--ck-primary'          => $primary,
			'--ck-primary-contrast' => $primary_contrast,
			'--ck-bg'               => $bg,
			'--ck-text'             => $text,
		);
		$ck_css = '';
		foreach ( $ck_vars as $ck_var => $ck_value ) {
			if ( $ck_value ) {
				$ck_css .= $ck_var . ':' . $ck_value . ';';
			}
		}
```

(La riga successiva `if ( '' !== $ck_css ) { wp_add_inline_style( ... ); }` resta invariata.)

- [ ] **Step 3: Ombra sottile ai pulsanti pieni (separazione dallo sfondo)**

In `packages/wordpress/public/css/banner.css`, sostituisci la regola `.ck-btn-primary` (righe 76-79):

```css
.ck-btn-primary {
  background: var(--ck-primary); color: var(--ck-primary-contrast);
  border-color: var(--ck-primary);
}
```

con:

```css
.ck-btn-primary {
  background: var(--ck-primary); color: var(--ck-primary-contrast);
  border-color: var(--ck-primary);
  box-shadow: 0 1px 3px rgba(0, 0, 0, .2);
}
```

- [ ] **Step 4: Verifica (manuale, admin WP)**

1. Imposta nel backend un **fondo scuro** (es. `#1f2937`) lasciando "Colore testo" **vuoto**. Ricarica il sito: titolo e corpo del banner sono **chiari** e leggibili; la X è chiara; i pulsanti pieni hanno testo leggibile e un lieve stacco dallo sfondo. Esito atteso: nessun testo scuro-su-scuro.
2. Imposta un **fondo chiaro** (es. `#ffffff`), testo vuoto: il testo torna **scuro**.
3. Imposta accento `#2563eb` (default) con "Colore testo pulsanti" vuoto: testo pulsanti **bianco** (comportamento di default preservato).
4. Check statico:

Run: `grep -n "contrast_text_for" packages/wordpress/includes/class-consentkit-frontend.php`
Expected: 3 occorrenze (definizione + 2 chiamate).

- [ ] **Step 5: Commit**

```bash
git add packages/wordpress/includes/class-consentkit-frontend.php packages/wordpress/public/css/banner.css
git commit -m "feat(wordpress): auto-contrasto testo banner in base allo sfondo + ombra pulsanti"
```

---

### Task 4: Bump versione 1.3.0 + build zip installabile

Aggiorna la versione e crea lo zip installabile da `packages/wordpress/` con il metodo sicuro (slash, niente `Compress-Archive`).

**Files:**
- Modify: `packages/wordpress/consentkit.php` (riga 6 header, riga 23 define)
- Create: `dist/consentkit-1.3.0.zip`

**Interfaces:**
- Consumes: sorgente completo `packages/wordpress/` con i cambiamenti dei Task 1-3.
- Produces: `dist/consentkit-1.3.0.zip` installabile su WordPress.

- [ ] **Step 1: Bump versione in `consentkit.php` (2 punti)**

In `packages/wordpress/consentkit.php` riga 6:

```php
 * Version:           1.3.0
```

In `packages/wordpress/consentkit.php` riga 23:

```php
define( 'CONSENTKIT_VERSION', '1.3.0' );
```

- [ ] **Step 2: Verifica coerenza versione**

Run: `grep -n "1.3.0" packages/wordpress/consentkit.php`
Expected: 2 righe (header + define). Nessun residuo `1.2.4` in questo file:

Run: `grep -n "1.2.4" packages/wordpress/consentkit.php`
Expected: nessun output.

- [ ] **Step 3: Costruisci lo zip installabile (PowerShell, senza Compress-Archive)**

Esegui questo script PowerShell (crea `dist/consentkit-1.3.0.zip` con radice `consentkit/` e slash negli entry):

```powershell
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$src = 'packages/wordpress'
$zipPath = (Resolve-Path 'dist').Path + '\consentkit-1.3.0.zip'
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
$srcFull = (Resolve-Path $src).Path
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
  Get-ChildItem -Path $srcFull -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Substring($srcFull.Length).TrimStart('\')
    $entryName = 'consentkit/' + ($rel -replace '\\','/')
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $entryName) | Out-Null
  }
} finally {
  $zip.Dispose()
}
Write-Host "Creato: $zipPath"
```

- [ ] **Step 4: Verifica che lo zip abbia gli entry con slash**

```powershell
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead((Resolve-Path 'dist/consentkit-1.3.0.zip'))
$hasMain = $zip.Entries | Where-Object { $_.FullName -eq 'consentkit/consentkit.php' }
$hasBackslash = $zip.Entries | Where-Object { $_.FullName -like '*\*' }
Write-Host ("consentkit/consentkit.php presente: " + [bool]$hasMain)
Write-Host ("entry con backslash (deve essere 0): " + ($hasBackslash | Measure-Object).Count)
$zip.Dispose()
```

Expected: `consentkit/consentkit.php presente: True` e `entry con backslash (deve essere 0): 0`.

- [ ] **Step 5: Commit del bump versione**

(Lo zip in `dist/` non è tracciato da git: si committa solo il sorgente.)

```bash
git add packages/wordpress/consentkit.php
git commit -m "chore(wordpress): bump versione 1.3.0 (banner responsive + box-left + auto-contrasto)"
```

- [ ] **Step 6: Verifica finale su installazione reale (manuale)**

Installa `dist/consentkit-1.3.0.zip` su un WordPress di test (o su lacasadigiusy in staging): l'upload non deve dare "plugin non trovato". Attivato il plugin, ripeti le verifiche dei Task 1-3 e conferma in particolare i **colori realmente configurati su lacasadigiusy** (fondo scuro → testo/pulsanti leggibili, criteri di accettazione 6-7 dello spec).

---

## Self-Review

**1. Spec coverage:**
- R1 (due modalità / trigger CSS) → Task 2 Step 1.
- R2 (altezza 33vh / 50vh landscape + tipografia scalata) → Task 2 Step 1.
- R3 (fix tablet: portrait compatto, landscape esteso) → conseguenza di Task 2 (verificato in Task 2 Step 2, righe iPad).
- R4 (`box-left` nei 5 punti) → Task 1.
- R5 (pulsanti pieni + X/link leggibili + bg opaco + ombra) → Task 3 (pulsanti pieni già presenti; ombra Step 3; X usa già `--ck-text` auto-contrastato; bg opaco garantito da `sanitize_hex_color`, annotato nei Global Constraints).
- R6 (auto-contrasto testo lato PHP) → Task 3 Step 1-2.
- Note impl. (fonte di verità, bump 2 punti, zip senza Compress-Archive) → Global Constraints + Task 4.
- Criteri di accettazione 1-8 → verifiche Task 2 Step 2 (1-4), Task 1 Step 6 (5), Task 3 Step 4 (6-7), Task 4 Step 4/6 (8).

**2. Placeholder scan:** nessun TBD/TODO/"gestisci errori"; ogni step ha codice o comando concreto. ✅

**3. Type/nome consistency:** `contrast_text_for` usato con lo stesso nome/firma in definizione e chiamate (Task 3). Classi CSS `.ck-box-left` coerenti tra Task 1 (base) e Task 2 (override compatto). Versione `1.3.0` coerente tra Task 4 e nome zip. ✅

**Nota sui test:** il progetto non ha un test runner (niente `package.json`/`phpunit`); la TDD classica (test rosso→verde) non è applicabile a CSS/PHP di rendering qui. Le verifiche sono manuali (DevTools responsive, admin WP) e statiche (grep, listing zip) — deviazione consapevole e dichiarata.
