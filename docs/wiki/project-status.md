# Stato del Progetto — Biscotto – Cookie Consent (plugin WordPress)

**Ultimo aggiornamento:** 2026-08-19
**Fase corrente:** PUBBLICATO su WordPress.org — manutenzione

---

## Ultima sessione
**Data:** 2026-08-19
**Fatto:**
- **APPROVATO** dalla WordPress.org Plugin Directory (16/08). Il rilievo residuo "Calling files remotely" era un **falso positivo** (tabelle statiche lette con `isset()`, nessun fetch): accettato dal team senza ulteriori upload.
- **v1.5.4 pubblicata** via SVN: `trunk` + `tags/1.5.4` + `assets`. Pagina pubblica: https://wordpress.org/plugins/biscotto-cookie-consent
- **v1.5.5 sviluppata e pubblicata**: portata la feature **"Crea pagina Cookie Policy"** che era rimasta sulla vecchia linea `main` — pulsante nel tab Cookie che genera una bozza di pagina WP con template GDPR/Garante e lo shortcode `[biscotto_cookie_policy]` (elenco cookie stile Complianz), + shortcode `[biscotto_last_updated]`. Nuova classe `Biscotto_Policy_Page`. Verificato: Plugin Check 27 check **0/0**, PHP lint pulito, smoke test ok.
- **Convergenza repo**: `main` era divergente e vecchio (classi `consentkit`, file `biscotto.php`). Fatto convergere `main` sul codice pubblicato tramite merge (storia preservata) e ritirato il branch `rebrand-biscotto-wporg`.
**Decisioni prese:** vedi [[decisions]].
**Nuove domande emerse:** nessuna.

---

## Prossimi passi immediati
1. [ ] (Opzionale) Verificare la resa pubblica di banner/icona/screenshot sulla pagina wordpress.org.
2. [ ] Manutenzione ordinaria: aggiornare `Tested up to` alle nuove major di WP; correggere eventuali segnalazioni post-pubblicazione.

---

## Dipendenze bloccanti
- Nessuna. Plugin approvato e live.

---

## Rilascio / pubblicazione
- **Codice → dist**: `powershell -ExecutionPolicy Bypass -File tools\package.ps1` (mai `Compress-Archive`).
- **dist → WordPress.org (SVN)**: `PUBLISH-WPORG.ps1` nella root — checkout in `..\biscotto-wporg-svn` (fuori dal repo), copia `dist` → `trunk`, crea `tags/<versione>`, aggiorna `assets`, conferma prima del `svn commit`. Rileva la versione dallo `Stable tag` del readme. Client: SlikSVN (`C:\Program Files\SlikSvn\bin\svn.exe`); username SVN `renatosaka`.
- **Tag Git**: `RELEASE.bat` (o `git tag -a vX.Y.Z`) dopo la pubblicazione SVN.

---

## Roadmap fasi

| Fase | Descrizione | Stato |
|------|-------------|-------|
| — | Feature banner/consenso, Consent Mode v2 (fino a v1.3.3) | ✅ FATTO |
| — | Enrichment cookie-database + copy-code (v1.4.0/1.4.1) | ✅ FATTO |
| — | Rebrand + hardening compliance (v1.5.0) | ✅ FATTO |
| — | Rename "Biscotto – Cookie Consent" + fix Plugin Check (v1.5.1/1.5.2) | ✅ FATTO |
| — | Review WordPress.org Plugin Directory | ✅ FATTO |
| — | **Approvazione + prima pubblicazione (v1.5.4)** | ✅ FATTO |
| — | **Feature "Crea pagina Cookie Policy" (v1.5.5)** | ✅ FATTO |

---

## Note e riferimenti
- Repo GitHub: https://github.com/renatoromano-png/Biscotto-Cookie-Consent (branch unico: `main`)
- Plugin principale: `packages/wordpress/biscotto-cookie-consent.php` (text domain `biscotto-cookie-consent`, layer PHP interno `Biscotto_*`/`BISCOTTO_*`)
- Core JS/CSS: `packages/core` → sincronizzato in `packages/wordpress/public` da `tools/package.ps1`
- WP locale di test: `C:\laragon\www\biscotto-test` (`biscotto_test` DB, WP_DEBUG on). Plugin Check 2.0.0 installato. Redeploy copiando da `dist\biscotto-cookie-consent`.
- PHP lint: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l`; Plugin Check headless: suite statica via `CLI_Runner` (vedi memoria `biscotto-local-test-env`).
