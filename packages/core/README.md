# Biscotto — Core

Il cuore portabile di Biscotto: consent manager in JavaScript vanilla, **zero dipendenze**. È la **single source of truth**: gli adattatori `wordpress` e `standalone` ne ricevono una copia tramite `tools/build.sh`.

## Vincolo architetturale

> Il core legge la configurazione **esclusivamente** da `window.biscottoConfig`. Non conosce WordPress né alcuna piattaforma. L'unica differenza tra piattaforme è *chi popola `biscottoConfig`* e *chi piazza lo snippet Consent Mode default nel `<head>`*.

## File

| File | Ruolo |
|---|---|
| `src/consent-manager.js` | Logica completa: banner, preferenze, Consent Mode v2 update, dataLayer, prior blocking, LinkedIn, localStorage |
| `src/consent-mode-default.js` | Snippet Consent Mode v2 **default** (`denied`), da inlinare nel `<head>` prima di GTM |
| `css/banner.css` | Stili banner/preferenze, temabili via variabili `--biscotto-*` |

## Cosa fa il core

1. Legge lo stato salvato (`localStorage` chiave `biscotto_consent`) e ne valuta la validità (scadenza, `policyVersion`, `forceRenewDate`).
2. Se valido → applica il consenso (CM v2 update, dataLayer push, attiva script bloccati). Altrimenti → mostra il banner.
3. Al consenso dell'utente: salva il record (con `timestamp`, `action`, `policyVersion`), aggiorna tutto, opzionalmente invia il log al server (`biscottoConfig.logEndpoint`).
4. Mostra sempre il pulsante "Rivedi le tue scelte" (revoca).

## Conformità

Implementa i requisiti tecnici delle Linee guida del Garante (doc. 9677876) e GDPR/ePrivacy — vedi `biscotto-project.md` §13. In sintesi: parità Accetta/Rifiuta, X = mantieni default, privacy by default, prior blocking, no scroll-consent, riproposizione ≥ 6 mesi, re-consent su cambio policy, revoca sempre accessibile.

## Build

```bash
bash tools/build.sh   # copia il core dentro packages/wordpress e packages/standalone
```
