# Design: sync con database cookie pubblico + codice cookie page

Data: 2026-07-04
Stato: approvato dall'utente, in attesa di piano di implementazione

## Contesto

ConsentKit (plugin WordPress, `packages/wordpress`) ha già uno scanner cookie
(v1.1, `class-consentkit-scanner.php`) che rileva cookie/domini di terze parti
e li classifica con mappe interne scritte a mano (`classify_cookie`,
`classify_host`). La roadmap (readme.txt) prometteva un arricchimento della
classificazione tramite un database cookie pubblico ("cookiedatabase-
classification"), rimasto non implementato.

L'utente ha chiesto di concludere lo sviluppo con:
1. La sincronizzazione dei cookie del sito con un database cookie pubblico.
2. La generazione del codice da inserire nella pagina cookie policy del sito.

## Decisione sulla fonte dati (importante, cambia l'approccio originale)

L'idea iniziale era interrogare l'API di **cookiedatabase.org** (il servizio
di Complianz). Verifica fatta in sessione:

- L'API non ha documentazione tecnica pubblica reale (endpoint, parametri,
  auth): le pagine ufficiali e le librerie di terze parti trovate online non
  espongono specifiche utilizzabili. Tutto indica che è un servizio interno
  all'ecosistema Complianz, non una API aperta per plugin terzi.
- I dati restituiti sono comunque etichettati CC BY-NC-ND 4.0
  (NonCommercial-NoDerivatives), che resta un'ambiguità legale per un plugin
  sviluppato commercialmente da un'azienda (Food & Tech) anche a prescindere
  dalla disponibilità tecnica.

**Decisione:** si usa invece **Open Cookie Database**
(github.com/jkwakman/Open-Cookie-Database), licenza **Apache-2.0**, libera da
bundlare, modificare e usare commercialmente. È il dataset open-source
storicamente all'origine dell'ecosistema cookiedatabase.org. Colonne: `ID,
Platform, Category, Cookie / Data Key name, Domain, Description, Retention
period, Data Controller, User Privacy & GDPR Rights Portals, Wildcard match`.

Questo mantiene coerenza con la filosofia già stabilita del progetto: nessuna
chiamata esterna per la classificazione stessa, tutto lato server/locale
(vedi §9 del doc interno `consentkit-project.md`). Fa eccezione solo la
Feature 2 (controllo aggiornamenti, sotto), l'unica chiamata esterna del
plugin: esplicita, avviata solo dall'admin, senza invio di dati personali o
del sito.

## Architettura

### Dati bundlati

- Nuovo file `packages/wordpress/includes/data/open-cookie-database.csv`:
  snapshot vendored del CSV upstream.
- Nuovo file `packages/wordpress/includes/data/NOTICE.md`: attribuzione,
  link licenza Apache-2.0, URL sorgente, data dello snapshot (`vendored_at`).
- La data dello snapshot è anche definita come costante PHP
  (`ConsentKit_Scanner::CSV_SNAPSHOT_DATE` o simile) accanto al parsing,
  per essere confrontata dalla Feature 3 (controllo aggiornamenti) senza
  dover leggere/parsare il NOTICE.md a runtime.
- Il CSV resta uno snapshot vendored: nessun auto-aggiornamento/download
  automatico (vedi Feature 3 per il solo controllo manuale on-demand).

### Mappatura categorie

CSV (`Functional/Analytics/Marketing/Personalization/Security`) → categorie
Garante di ConsentKit (`necessary/analytics/marketing/preferences`):

| CSV Category | ConsentKit category | Motivo |
|---|---|---|
| Functional | necessary | |
| Analytics | analytics | |
| Marketing | marketing | |
| Personalization | preferences | |
| Security | necessary | CSRF/anti-bot/reCAPTCHA sono protezioni tecniche indispensabili, non richiedono consenso (art. 122 Codice Privacy) |

### Lookup

Nuova classe (o estensione dello scanner) che, alla prima invocazione per
request, parsa il CSV in tre strutture in memoria:
- mappa esatta nome-cookie (lowercase) → riga, per le righe non-wildcard;
- lista prefissi wildcard (lowercase) → riga, per le righe `Wildcard match=1`;
- mappa dominio (lowercase, ripulito da suffissi tipo "(3rd party)") → riga.

Nessuna persistenza/cache: il parsing gira solo quando l'admin clicca
l'azione di arricchimento (azione rara, non su ogni request frontend).

## Feature 1 — "Arricchisci dal database" (tab Scansione)

- Nuovo endpoint REST `POST consentkit/v1/scan/enrich` in
  `class-consentkit-scanner.php`, stesso `permission_check` (admin +
  nonce `wp_rest`) degli endpoint scan esistenti.
- Input: l'array dei suggerimenti **già presenti in memoria nel browser**
  dopo una scansione (non ancora importati nel registro). Output: lo stesso
  array con i campi vuoti riempiti dal lookup.
- Regola di riempimento, per riga:
  - se `service` è vuoto (cioè il classificatore interno non ha riconosciuto
    il cookie/dominio — unico caso in cui `classify_cookie`/`classify_host`
    lasciano `service` vuoto) → il lookup riempie `service`, `category`,
    `duration`, `url_policy` tutti insieme;
  - se `service` è già valorizzato (classificazione interna confermata) → il
    lookup riempie **solo** `duration`/`url_policy` se ancora vuoti, senza
    toccare `service`/`category` già decisi dal classificatore interno.
- Nessun match trovato → riga invariata.
- Lato client (`admin/js/scan.js`, `settings-scan.php`): nuovo pulsante
  "Arricchisci dal database" accanto ai risultati scan, abilitato solo
  quando ci sono righe (`rowsData.length`). Al click, POST dei suggerimenti
  correnti a `/scan/enrich`, sostituzione di `rowsData` con la risposta,
  nuovo render.
- Nuova colonna **Durata** nella tabella risultati (il campo esiste già nel
  modello dati e viene già inviato all'import, ma oggi non è mai mostrato
  prima dell'import — va reso visibile perché altrimenti l'arricchimento
  sarebbe invisibile). Il nome servizio, quando c'è un `url_policy`, mostra
  un piccolo link "Info" verso la privacy policy del servizio.
- Nessun salvataggio lato server in questa fase: l'admin importa come già fa
  oggi col pulsante "Aggiungi i selezionati al registro" esistente.

## Feature 2 — "Controlla aggiornamenti database" (tab Scansione)

Il CSV bundlato è uno snapshot statico: senza un modo per sapere se è
superato, resterebbe indietro per sempre. Introduce la **prima chiamata a un
servizio esterno** nella storia del plugin (finora zero, punto di forza
comunicato ai reviewer WP.org). Per questo resta un'azione **esplicita,
avviata solo dall'admin**, mai automatica/in background (niente WP-Cron):

- Nuovo endpoint REST `GET consentkit/v1/scan/db-version` (stesso
  `permission_check` admin+nonce). Lato server, `wp_remote_get` verso
  `https://api.github.com/repos/jkwakman/Open-Cookie-Database/commits?path=open-cookie-database.csv&per_page=1`
  (timeout 10s), estrae la data dell'ultimo commit che ha toccato il CSV,
  la confronta con `CSV_SNAPSHOT_DATE` bundlato. Risposta:
  `{ bundled: 'YYYY-MM-DD', latest: 'YYYY-MM-DD'|null, update_available: bool }`.
- Risultato cachato in un transient (24h) per non consumare il rate limit
  pubblico di GitHub (60 richieste/ora per IP) se l'admin clicca più volte.
- Se `wp_remote_get` fallisce (rete, rate limit, GitHub giù) → risposta
  neutra, nessun errore bloccante: "impossibile verificare ora".
- Nuovo pulsante "Controlla aggiornamenti database" in tab Scansione (sezione
  separata dal pulsante "Arricchisci dal database", non lo sostituisce). Se
  `update_available` → messaggio con la data dell'ultima modifica upstream e
  un link al repository GitHub (nessun download/aggiornamento automatico:
  è solo un promemoria per Renato di rigenerare manualmente il CSV vendored
  in una release futura).
- **Trasparenza:** aggiunta una voce alla FAQ di `readme.txt` ("Invia dati a
  servizi esterni?") che spiega questa unica eccezione: chiamata avviata
  solo su click esplicito dell'admin, nessun dato personale o del sito
  inviato (solo una richiesta GET pubblica all'API GitHub), nessuna
  chiamata automatica/periodica.

## Feature 3 — "Copia codice" (tab Cookie)

- In `settings-cookies.php`, il blocco di testo che oggi spiega gli
  shortcode viene sostituito da un box con:
  - un campo readonly pre-compilato con `[consentkit_cookie_policy]`;
  - un pulsante "Copia" (Clipboard API `navigator.clipboard.writeText`, con
    fallback `document.execCommand('copy')` per compatibilità);
  - una nota più piccola sotto con i due shortcode granulari
    (`[consentkit_cookie_table]`, `[consentkit_consent_settings]`) per chi
    vuole comporre la pagina diversamente.
- Nessuna creazione automatica di pagine WordPress, nessun generatore HTML
  per siti standalone: resta minimale — "genera e copia", come richiesto.

## Versione

Bump a **1.4.0** (feature, non fix): header plugin + costante
`CONSENTKIT_VERSION` + `readme.txt` (Stable tag, Changelog, Upgrade Notice,
rimozione della voce "cookiedatabase-classification" dalla roadmap perché
implementata). Pacchetto installabile rigenerato in `dist/` con lo stesso
metodo .NET ZipFile con slash (vedi memoria `consentkit-zip-packaging`), mai
`Compress-Archive`.

## Fuori scope

- Download/aggiornamento automatico del CSV bundlato (solo notifica manuale,
  vedi Feature 2).
- Controllo aggiornamenti in background/WP-Cron (resta un'azione avviata
  solo dall'admin).
- Arricchimento automatico durante lo scan (resta un'azione esplicita
  separata, su richiesta dell'utente).
- Creazione automatica della pagina cookie policy o generatore standalone
  (non-WP) per la cookie page.
- Integrazione live con l'API di cookiedatabase.org (bloccata dai problemi
  di accesso/licenza descritti sopra).

## Testing

- Lint PHP 8.3 (Laragon) 0 errori/warning su tutti i file toccati/nuovi.
- `node --check` su `admin/js/scan.js` modificato.
- Test manuale su sito locale (jopistacchio o lacasadigiusy): scansione con
  almeno un cookie/dominio non presente nelle mappe interne (per verificare
  che l'arricchimento lo riconosca dal CSV), verifica colonna Durata e link
  Info, verifica pulsante Copia negli appunti, verifica import finale nel
  registro.
- Test manuale del pulsante "Controlla aggiornamenti database": verifica
  risposta con snapshot aggiornato (nessun aggiornamento disponibile) e,
  se possibile, forzando `CSV_SNAPSHOT_DATE` a una data vecchia per vedere
  il messaggio "aggiornamento disponibile" col link corretto; verifica
  comportamento quando l'API GitHub non risponde (timeout/errore).
