# Stato del Progetto — Biscotto – Cookie Consent (plugin WordPress)

**Ultimo aggiornamento:** 2026-07-12
**Fase corrente:** Review WordPress.org Plugin Directory (in corso)

---

## Ultima sessione
**Data:** 2026-07-12
**Fatto:**
- Inizializzata la wiki; pubblicate release **v1.5.0** e **v1.5.1** su GitHub.
- Affrontata la review WordPress.org del 10/07 (4 problemi: enqueue, servizi esterni, text domain, nomi JS): enqueue/servizi/nomi già ok in 1.5.0, **text domain completato**.
- **Rinominato il plugin** in "Biscotto – Cookie Consent", slug e text domain **`biscotto-cookie-consent`** (ADR-002). Repo GitHub rinominato in `Biscotto-Cookie-Consent`.
- Risolto errore Plugin Check all'upload: **heredoc → stringa PHP**.
- **v1.5.2**: fix degli ultimi 5 rilievi di Plugin Check — lettura CSV via **WP_Filesystem** (niente fopen/fclose) e `wp_unslash`+`sanitize_key` su `$_GET['page']`.
- **Verificato dal vivo su Laragon**: attivazione pulita, generazione Cookie Policy ok, e **Plugin Check completo = 0 errori / 0 warning**.
**Decisioni prese:** ADR-002 (nome/slug definitivo).
**Nuove domande emerse:** nessuna.

---

## Prossimi passi immediati
1. [ ] **Ricaricare** `dist/biscotto-cookie-consent.zip` (**v1.5.2**) su https://wordpress.org/plugins/developers/add/ — sostituisce la 1.5.1.
2. [ ] **Rispondere** alla mail `plugins@wordpress.org` chiedendo ESPLICITAMENTE lo slug `biscotto-cookie-consent` (i permalink non si cambiano solo nel codice). Bozza rigenerabile.
3. [ ] (Opzionale) Aggiornare `Plugin URI`/`Author URI` header al repo `Biscotto-Cookie-Consent` alla prossima versione.

---

## Dipendenze bloccanti
- Review WordPress.org → ⏳ in corso (attesa ri-upload v1.5.2 + risposta mail)
- Approvazione dello slug `biscotto-cookie-consent` dal team WP → ❓ da confermare (lo slug è ancora modificabile finché non approvato)

---

## Note operative importanti
- **Conflitto locale di classi**: nel WordPress di test c'erano DUE cartelle (`consentkit` vecchia + `biscotto-cookie-consent` nuova). Definiscono le stesse classi `ConsentKit_*` → fatale "Cannot redeclare" se attive entrambe. Risolto eliminando la cartella `consentkit` obsoleta. **Non è un problema su wordpress.org** (ci sarà solo Biscotto). I nomi interni `ConsentKit_*`/`CONSENTKIT_*` restano invariati di proposito (prefisso valido, non richiesto dalla review).

---

## Roadmap fasi

| Fase | Descrizione | Stato |
|------|-------------|-------|
| — | Feature banner/consenso, Consent Mode v2 (fino a v1.3.3) | ✅ FATTO |
| — | Enrichment cookie-database + copy-code (v1.4.0/1.4.1) | ✅ FATTO |
| — | Rebrand + hardening compliance (v1.5.0) | ✅ FATTO |
| — | Rename "Biscotto – Cookie Consent" + fix Plugin Check heredoc (v1.5.1) | ✅ FATTO |
| — | Fix ultimi rilievi Plugin Check → 0/0 (v1.5.2) | ✅ FATTO |
| — | Approvazione WordPress.org Plugin Directory | ⏳ IN CORSO |

---

## Note e riferimenti
- Repo GitHub: https://github.com/renatoromano-png/Biscotto-Cookie-Consent
- Plugin principale: `packages/wordpress/biscotto.php` (v1.5.2, text domain `biscotto-cookie-consent`)
- Core JS/CSS: `packages/core` → sincronizzato in `packages/wordpress/public` da `tools/package.ps1`
- Packaging: `tools/package.ps1` → `dist/biscotto-cookie-consent.zip` (mai `Compress-Archive`)
- WP locale di test: `C:\laragon\www\jopistacchio` (`http://jopistacchio.test/`), Plugin Check installato (Strumenti → Plugin Check)
- PHP lint: `/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe -l`; test DB: `php tests/test-cookie-database.php`
