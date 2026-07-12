# Stato del Progetto — Biscotto (Cookie Consent & Consent Mode per WordPress)

**Ultimo aggiornamento:** 2026-07-12
**Fase corrente:** Rilascio / submission a WordPress.org Plugin Directory

---

## Ultima sessione
**Data:** 2026-07-12
**Fatto:**
- Ricostruito lo stato del progetto a mano (non esisteva ancora una wiki).
- Verificato: plugin rinominato ConsentKit → **Biscotto**, versione **1.5.0**, tutto committato e pushato su `origin/main` (working tree pulito).
- Inizializzata la wiki di progetto in `docs/wiki/`.
**Decisioni prese:** Adottare la project-wiki come memoria persistente del progetto (vedi ADR-001).
**Nuove domande emerse:** Stato attuale della review WordPress.org (vedi Q-001).

---

## Prossimi passi immediati
1. [ ] **Review WordPress.org (in corso):** completare text domain a `biscotto` (13 stringhe residue), testare, ricaricare lo zip su "Add your plugin", rispondere alla mail chiedendo lo slug `biscotto`. Vedi Q-001.
2. [ ] Decidere se committare la cartella `docs/` (spec/piani superpowers + questa wiki) nel repo.

**Release v1.5.0:** ✅ pubblicata su GitHub (2026-07-12). 1.4.0 / 1.4.1 già presenti come `v.1.4.0` / `v.1.4.1`.

**Nota tag:** i tag esistenti mescolano due formati — `vX.Y.Z` (v1.3.3, v1.2.0…) e `v.X.Y.Z` con punto di troppo (v.1.4.0, v.1.4.1…). Per v1.5.0 si è scelto il formato pulito `v1.5.0`.

---

## Dipendenze bloccanti
- Contenuto della review WordPress.org → ❓ da verificare (nel file `.msg`, non ancora aperto)

*(Nessun'altra dipendenza bloccante nota.)*

---

## Roadmap fasi

| Fase | Descrizione | Stato |
|------|-------------|-------|
| — | Feature banner/consenso, responsive, Consent Mode v2 (fino a v1.3.3) | ✅ FATTO |
| — | Enrichment cookie-database + copy-code (v1.4.0/1.4.1) | ✅ FATTO |
| — | Rebrand a "Biscotto" + hardening compliance (v1.5.0) | ✅ FATTO |
| — | Submission / review WordPress.org Plugin Directory | ⏳ IN CORSO |
| — | Tag e release GitHub allineate alle versioni pubblicate | ⏳ DA FARE |

---

## Note e riferimenti
- Plugin principale: `packages/wordpress/biscotto.php` (Version 1.5.0)
- Readme WordPress.org: `packages/wordpress/readme.txt` (Stable tag 1.5.0)
- Core JS/CSS (single source of truth): `packages/core`
- Piani implementativi storici: `docs/superpowers/plans/`
- Packaging zip: `tools/package.ps1` (mai usare `Compress-Archive`)
