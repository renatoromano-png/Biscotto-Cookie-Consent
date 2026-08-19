# ConsentKit — Banner responsive + posizione box-left + contrasto pulsanti

**Data:** 2026-07-03
**Stato:** approvato (design), da implementare
**File principali:** `packages/wordpress/public/css/banner.css`, `packages/wordpress/public/js/consent-manager.js`, `packages/wordpress/admin/views/settings-general.php`, `packages/wordpress/includes/class-consentkit-admin.php`, `packages/wordpress/includes/class-consentkit-consent.php`

## Problema

Su smartphone e tablet il banner non è dimensionato bene:
- La **barra in basso** ha `min-height: 50vh` (mezzo schermo) che scatta anche su tablet, dove risulta troppo grande (~6/10 in verticale, ~8/10 in orizzontale).
- Le altre posizioni (`modal`, `box-right`) su mobile restano compatte/piccole: il trattamento "grande" ha toccato solo la barra → comportamento incoerente.
- I **pulsanti e la X** appaiono rossastri e a basso contrasto su sfondo scuro (combinazione accento rosso scuro + `--ck-bg` scuro).

Un tentativo precedente di ridimensionare è fallito perché "1/3 dello schermo" ha significati diversi a seconda della forma della finestra e non era stato definito il modello responsive.

## Obiettivo

Comportamento responsive coerente, ispirato a Complianz, guidato **da CSS** (media query su larghezza + orientamento), non da JS:

| Fascia | Condizione | Comportamento |
|---|---|---|
| **Estesa** | Desktop + tablet **orizzontale** | Rispetta la posizione scelta nel backend |
| **Compatta** | Telefoni (ogni orientamento) + tablet **verticale** | Barra in basso, piena larghezza, contenuto scalato |

## Requisiti dettagliati

### R1 — Modello a due modalità (CSS)
Trigger della modalità **compatta**:
```
@media (max-width: 600px),
       (min-width: 601px) and (max-width: 1024px) and (orientation: portrait),
       (orientation: landscape) and (max-height: 500px)
```
- `≤600px` → telefoni in verticale (e piccoli).
- `601–1024px` **portrait** → tablet in verticale.
- **landscape + `max-height: 500px`** → telefoni in orizzontale (viewport basso: larghi ~700–900px ma bassi). Il tablet in orizzontale ha altezza ≥ ~760px quindi **non** viene catturato → resta esteso.
- Tutto il resto (desktop, tablet landscape) → modalità **estesa**.

In modalità compatta, **qualsiasi** posizione backend (`bottom-bar`, `modal`, `box-right`, `box-left`) viene forzata a barra in basso a piena larghezza tramite override CSS. La posizione backend conta solo in modalità estesa.

### R2 — Altezza modalità compatta (responsive all'altezza disponibile)
- Telefono verticale + tablet verticale → `min-height: 33vh` (~1/3).
- Telefono **orizzontale** (viewport basso, es. `(orientation: landscape) and (max-height: 500px)`) → `min-height: 50vh` (~1/2), per mantenere il testo leggibile.
- `max-height` con `overflow-y: auto` come salvaguardia se il contenuto eccede.
- Contenuto (titolo, testo, link, pulsanti) scalato con `clamp()` per riempire lo spazio; riuso/affino i clamp già presenti su `.ck-bottom-bar`.

### R3 — Tablet
Conseguenza di R1+R2 (nessuna regola dedicata al "tablet" in quanto tale):
- Tablet **verticale** → barra compatta ~1/3 (non più ~6/10).
- Tablet **orizzontale** → posizione backend, dimensione normale (non più ~8/10).

### R4 — Nuova posizione `box-left`
Riquadro in basso a **sinistra**, speculare a `box-right`. Va aggiunta in tutti i punti dove oggi esistono le 3 posizioni:
1. `admin/views/settings-general.php` → nuova `<option value="box-left">Riquadro in basso a sinistra</option>`.
2. `includes/class-consentkit-admin.php` → whitelist di sanitizzazione (`array( 'bottom-bar', 'modal', 'box-right', 'box-left' )`).
3. `public/js/consent-manager.js` (riga ~39) → whitelist posizioni valide.
4. `public/css/banner.css` → regola `.ck-box-left` (speculare a `.ck-box-right`: `left: 20px; right: auto;`).
5. `includes/class-consentkit-consent.php` (riga ~44) → aggiornare il commento delle posizioni valide.

In modalità compatta `box-left` diventa barra come le altre.

### R5 — Contrasto pulsanti + X
- Pulsanti "Accetta"/"Rifiuta" sempre **pieni**: `background: --ck-primary`, testo `--ck-primary-contrast` (coppia a contrasto garantito). Nessuno stile a solo contorno.
- X (`.ck-close`) e link (`.ck-btn-link`, `.ck-link`) derivati da `--ck-text`/`--ck-muted` con bordo visibile, così restano leggibili su qualsiasi sfondo (non dall'accento rosso).
- Sfondo banner **opaco**: verificare che `--ck-bg` non abbia trasparenza che abbassa il contrasto.
- Salvaguardia: aggiungere un bordo/ombra sottile ai pulsanti così si staccano sempre dallo sfondo anche se l'admin sceglie una coppia colore a basso contrasto.
- **Verifica sul campo:** controllare i colori realmente configurati su lacasadigiusy e confermare il contrasto (target WCAG AA) prima di chiudere.

### R6 — Colore del testo adattivo allo sfondo (auto-contrasto)
Il colore del font deve contrastare automaticamente con lo sfondo: **chiaro su fondo scuro, scuro su fondo chiaro**. Vale per il testo del banner (`--ck-text`) e per il testo dei pulsanti (`--ck-primary-contrast`).

- Calcolo **lato PHP** in `class-consentkit-frontend.php`, dove i colori sono già iniettati come variabili CSS su `:root`. Il CSS puro non è affidabile per questo nel 2026 (`contrast-color()`/`color-contrast()` senza supporto cross-browser stabile).
- Algoritmo: helper che calcola la **luminanza relativa** (WCAG) di un colore esadecimale — linearizzazione sRGB e `L = 0.2126·R + 0.7152·G + 0.0722·B` — e restituisce testo **scuro** (es. `#1f2937`) se il fondo è chiaro, **chiaro** (es. `#ffffff`) se il fondo è scuro (soglia sulla luminanza).
- Applicazione:
  - `--ck-text` → derivato da `--ck-bg` **quando l'admin non ha impostato esplicitamente** un colore testo (campo vuoto = automatico). Se l'admin imposta un testo esplicito, si rispetta la sua scelta.
  - `--ck-primary-contrast` → derivato da `--ck-primary` con la stessa logica, quando non impostato esplicitamente.
- Questo rende superfluo affidarsi a `prefers-color-scheme` per la leggibilità: il contrasto è garantito dal colore di fondo effettivo, non dalla modalità del sistema operativo.
- I default di fabbrica (fondo chiaro `#ffffff` → testo scuro) restano invariati.

## Fuori scope
- Nessun cambiamento alla logica di consenso (accetta/rifiuta/keepDefault, Consent Mode).
- Nessun refactoring non correlato.

## Note di implementazione
- **Fonte di verità unica:** `packages/wordpress/`. La cartella `dist/consentkit/` è una copia scompattata **stale (v1.2.0)** non tracciata da git e **non** va toccata; lo zip si costruisce direttamente da `packages/wordpress/`.
- Bump di versione (attuale v1.2.4 → prossima minor per la feature `box-left` + responsive) in **due punti** di `packages/wordpress/consentkit.php`: header `Version:` e `define( 'CONSENTKIT_VERSION', ... )`.
- Nuovo zip installabile in `dist/consentkit-<versione>.zip`, costruito **senza** `Compress-Archive` (i backslash rompono l'installazione WordPress): radice `consentkit/`, entry con slash `/`, verificando che `consentkit/consentkit.php` sia presente con slash — vedi memoria `consentkit-zip-packaging`.

## Criteri di accettazione
1. Su telefono (verticale) il banner è una barra in basso alta ~1/3, testo e pulsanti leggibili, qualunque sia la posizione backend.
2. Su telefono orizzontale il banner è alto ~1/2 e il testo resta leggibile.
3. Su tablet verticale → barra ~1/3; su tablet orizzontale → posizione backend con dimensioni normali.
4. Su desktop il comportamento delle 4 posizioni è invariato (con l'aggiunta di `box-left`).
5. La posizione "Riquadro in basso a sinistra" è selezionabile nel backend e funziona.
6. Pulsanti e X hanno contrasto adeguato (AA) su sfondo scuro / coi colori di lacasadigiusy.
7. Il testo del banner e dei pulsanti è automaticamente chiaro su fondo scuro e scuro su fondo chiaro, senza bisogno di impostare il colore testo a mano.
8. Modifiche replicate in `dist/`, versione bumpata, zip rigenerato correttamente.
