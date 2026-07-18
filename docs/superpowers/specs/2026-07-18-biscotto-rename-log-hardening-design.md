# Biscotto — rename completo e hardening dell'endpoint `/log`

Data: 2026-07-18
Stato: approvato, pronto per il piano di implementazione

## Contesto

Il plugin è in review su WordPress.org. La review del 18 luglio 2026
(Review ID `R foodandtech-cookie-consent-manager/renatosaka/18Jul26/T1`)
solleva due rilievi. In parallelo esiste un problema di allineamento del
repository.

### Disallineamento del repository

Su WordPress.org sono state caricate le versioni 1.5.1 (12 lug, 09:33 UTC) e
1.5.2 (12 lug, 12:05 UTC), con nome plugin "Biscotto – Cookie Consent" e slug
richiesto `biscotto-cookie-consent`. Quel codice non è mai stato committato: il
repository è fermo alla 1.5.0 con text domain `biscotto`, ultimo commit del
10 luglio. I sorgenti 1.5.1/1.5.2 non sono recuperabili. Il lavoro va rifatto a
partire dallo stato attuale del repository.

### Rilievo 1 — "Calling files remotely" (falso positivo)

Il reviewer cita `includes/class-consentkit-scanner.php` righe 625, 628 e 645
(`www.gstatic.com`, `secure.gravatar.com`, `static.cloudflareinsights.com`).

Sono voci di una tabella statica che mappa hostname → vendor, categoria e URL
della privacy policy. Il plugin non effettua alcuna richiesta verso quegli host:
li usa per riconoscere e classificare le terze parti già presenti nell'HTML del
sito analizzato. Nessuna `wp_remote_get`, nessun enqueue remoto. Non richiede
modifiche al codice, solo una spiegazione nella risposta.

### Rilievo 2 — `permission_callback` su `/log` (fondato)

Citazione dal reviewer:

> DB-writing consent log endpoint is exposed with `__return_true`, and the
> in-callback `wp_rest` nonce is publicly obtainable by anonymous visitors so it
> does not provide a real authorization boundary

Il rilievo è corretto. Il nonce `wp_rest` è disponibile a qualunque visitatore
anonimo, quindi non costituisce una barriera: chiunque può ottenerlo e far
crescere `wp_consentkit_log` senza limite. Il nonce protegge dal CSRF, non
dall'abuso.

La risposta precedente ("endpoint pubblico by design") è già stata respinta e
non va riproposta. Il reviewer stesso riconosce `__return_true` come legittimo
per endpoint intenzionalmente pubblici: l'obiezione riguarda l'assenza di un
limite reale alla scrittura su database.

## Decisioni prese

1. **Rename totale** del layer PHP interno a Biscotto, non solo dell'identità
   pubblica.
2. **Nessuna migrazione** di option o tabella: i siti pilota sono
   riconfigurabili a mano e ripartono da configurazione vuota.
3. **Endpoint `/log` mantenuto pubblico**, con limiti d'abuso reali che non
   dipendono dal nonce (approccio A tra i tre valutati; le alternative scartate
   erano il log differito lato server senza endpoint REST, e la rimozione della
   funzione dalla prima release).

## Obiettivo 1 — Rename completo a Biscotto

### Identità pubblica

| Elemento | Da | A |
|---|---|---|
| Plugin Name | Biscotto – Cookie Consent & Consent Mode | Biscotto – Cookie Consent |
| Slug / cartella | `biscotto` | `biscotto-cookie-consent` |
| Text Domain | `biscotto` | `biscotto-cookie-consent` |
| File principale | `biscotto.php` | `biscotto-cookie-consent.php` |
| File POT | `languages/biscotto.pot` | `languages/biscotto-cookie-consent.pot` |

Il nome "Biscotto – Cookie Consent" e lo slug `biscotto-cookie-consent` sono
quelli già comunicati al team WordPress.org nelle mail del 12 luglio. Vanno
mantenuti identici: introdurre un terzo nome complicherebbe la review.

Il text domain deve coincidere con lo slug in tutte le stringhe tradotte.

### Layer interno

| Categoria | Da | A |
|---|---|---|
| Classi | `ConsentKit_*`, `class ConsentKit` | `Biscotto_*`, `class Biscotto` |
| Costanti | `CONSENTKIT_*` | `BISCOTTO_*` |
| Option | `consentkit_settings` | `biscotto_settings` |
| Tabella | `{prefix}consentkit_log` | `{prefix}biscotto_log` |
| Namespace REST | `consentkit/v1` | `biscotto/v1` |
| Handle enqueue | `consentkit-*` | `biscotto-*` |
| Oggetti localize | `consentkitScan`, `consentkitPolicy`, `consentkitCookies` | `biscottoScan`, `biscottoPolicy`, `biscottoCookies` |
| File classi | `includes/class-consentkit-*.php` | `includes/class-biscotto-*.php` |
| Funzioni bootstrap | `consentkit_activate()`, `consentkit()` | `biscotto_activate()`, `biscotto()` |
| Docblock | `@package ConsentKit` | `@package Biscotto` |

Il core JS (`packages/core`) e le classi CSS sono già stati rinominati nel
commit `74c6bda`: `window.Biscotto`, `biscottoConfig`, `biscotto_consent`,
evento `biscotto:consent`, attributi `data-biscotto-*`, classi `.biscotto-*`.
Su quei file non si interviene.

I nomi degli oggetti localize mantengono un prefisso di almeno 4 caratteri, come
richiesto dalla review precedente.

### Esclusioni esplicite

- I file di terze parti in `includes/data/` (Open Cookie Database, Apache-2.0) e
  il relativo `NOTICE.md` non vanno alterati oltre agli eventuali riferimenti al
  nome del nostro plugin.
- Nessun codice di migrazione da `consentkit_settings` a `biscotto_settings`,
  né dalla vecchia alla nuova tabella di log.

## Obiettivo 2 — Hardening dell'endpoint `/log`

Tutte le modifiche in `includes/class-biscotto-api.php`, salvo dove indicato.

### Registrazione condizionale della rotta

`register_routes()` esce senza registrare nulla quando `log_enabled` è
disattivato. Poiché il log è opzionale e spento di default, nella maggior parte
delle installazioni l'endpoint non esisterà: una richiesta riceve 404.

### Catena dei controlli

Ordine obbligatorio in `log_consent()`:

1. `log_enabled` attivo
2. nonce valido (protezione CSRF)
3. rate limit per `pseudo_id`
4. `action` compresa nell'allowlist
5. deduplica
6. insert

Il rate limit precede la deduplica: invertendoli si potrebbe far eseguire la
query di deduplica senza alcun limite.

### Rate limit

Transient `biscotto_rl_{pseudo_id}` come contatore, scadenza 1 ora, soglia 10
scritture. Al superamento la risposta è `429` con
`{ "logged": false, "error": "rate_limited" }`.

Il `pseudo_id` esiste già: hash SHA-256 di IP + user agent + salt giornaliero,
non reversibile e privo di dati identificativi diretti.

### Deduplica

Se esiste già un record con stesso `pseudo_id`, `policy_version` e `action`
creato nelle ultime 24 ore, la risposta è `200` con
`{ "logged": false, "reason": "duplicate" }` e nessun insert. Questo elimina la
maggior parte del volume, sia legittimo (ricariche di pagina) sia abusivo.

### Retention

Evento cron giornaliero `biscotto_prune_log` che elimina i record più vecchi di
N mesi. Default 12 mesi, configurabile dal pannello admin. L'evento va
schedulato in attivazione e rimosso in disattivazione.

Oltre a limitare la crescita della tabella, la retention risponde al principio
di minimizzazione GDPR.

### Schema della tabella

La tabella nasce nuova (nessuna migrazione), quindi in
`maybe_create_log_table()` si definisce anche `KEY created_at (created_at)`,
necessario sia alla query di deduplica sia alla potatura periodica.

### Commenti nel codice

Il commento attuale sopra `'permission_callback' => '__return_true'` motiva la
scelta con il nonce ed è ciò che ha innescato l'obiezione. Va riscritto: il
nonce è protezione CSRF, l'autorizzazione all'abuso è data da rate limit,
deduplica e retention.

## Obiettivo 3 — Versione e packaging

Versione **1.5.3**: WordPress.org ha già ricevuto la 1.5.2, non è possibile
ricaricare un numero uguale o inferiore.

Da aggiornare in modo coerente:

- header `Version:` in `biscotto-cookie-consent.php`
- costante `BISCOTTO_VERSION`
- `Stable tag:` in `readme.txt`

`tools/build.sh`, `tools/package.sh` e `tools/package.ps1` devono produrre
`dist/biscotto-cookie-consent/` e `biscotto-cookie-consent.zip`. La cartella
dentro lo zip deve chiamarsi esattamente come lo slug.

Il `readme.txt` mantiene la sezione "External services" già presente (Google Tag
Manager, LinkedIn Insight Tag, controllo aggiornamenti GitHub on-demand) e
aggiunge la descrizione dei limiti applicati al log dei consensi.

## Verifica

Prima di ricaricare su WordPress.org:

- Plugin Check senza errori né warning
- installazione WordPress pulita con `WP_DEBUG` a `true`, nessun notice
- percorso del log verificato a mano:
  - log attivo, primo consenso → una riga scritta
  - secondo consenso identico entro 24h → nessuna riga nuova, risposta 200
  - oltre 10 richieste in un'ora → risposta 429
  - log disattivato → la rotta risponde 404
  - potatura: record datati oltre la soglia rimossi all'esecuzione del cron
- scansione cookie ancora funzionante dopo il rename degli handle e degli
  oggetti localize
- salvataggio impostazioni funzionante con la nuova option `biscotto_settings`

## Risposta al team WordPress.org

Da preparare come deliverable separato, concisa, su due punti:

1. Scanner: falso positivo. La tabella citata è una mappa statica di
   classificazione, il plugin non effettua richieste verso quegli host.
2. `/log`: elenco dei limiti reali introdotti (rotta registrata solo a log
   attivo, rate limit, deduplica, retention), senza riproporre l'argomento
   "pubblico by design" già respinto.

Va indicato anche lo slug richiesto, `biscotto-cookie-consent`, coerente con le
comunicazioni precedenti.

L'invio della mail resta a carico dell'utente.

## Fuori ambito

- Migrazione dei dati dalle installazioni pilota
- Rename del core JS e delle classi CSS (già completato in `74c6bda`)
- Merge del branch su `main` e tag di release: da decidere dopo l'esito della
  review
