# Decisioni Architetturali (ADR)

| ID | Titolo | Stato | Data |
|----|--------|-------|------|
| ADR-001 | Adottare project-wiki come memoria persistente | ACCETTATO | 2026-07-12 |
| ADR-002 | Nome e slug del plugin per WordPress.org | ACCETTATO | 2026-07-12 |

---

## ADR-002 — Nome e slug del plugin per WordPress.org
**Data:** 2026-07-12
**Stato:** ACCETTATO
**Contesto:** La review WordPress.org ha bocciato il nome "ConsentKit" (potenziale marchio + somiglianza con altro plugin) e il revisore aveva riservato d'ufficio lo slug `foodandtech-cookie-consent-manager`. La regola WP impone text domain = slug.
**Decisione:** Nome display "Biscotto – Cookie Consent", slug e text domain `biscotto-cookie-consent`. Chiediamo al team WP di riservare questo slug al posto di `foodandtech-cookie-consent-manager`.
**Razionale:** "Biscotto" è un marchio distintivo (nessun conflitto con "ConsentKit"); accostare "Cookie Consent" segue il pattern raccomandato da WP (termine distintivo + descrizione), riducendo il rischio dell'obiezione "troppo generico" che avrebbe avuto il solo "biscotto". Scartato: (a) accettare `foodandtech-cookie-consent-manager` — butta via il brand; (b) solo "Biscotto" — più a rischio genericità.
**Conseguenze:** Rinominati header, readme, text domain (167 stringhe), nome cartella/zip in `package.ps1`. Lo slug resta modificabile finché il plugin non è approvato. Nomi interni (`ConsentKit_*`, `CONSENTKIT_*`, `class-consentkit-*.php`) invariati: prefisso valido, non richiesto dalla review.

## ADR-001 — Adottare la project-wiki come memoria persistente del progetto
**Data:** 2026-07-12
**Stato:** ACCETTATO
**Contesto:** All'inizio di ogni sessione lo stato del progetto veniva ricostruito a mano da git log, piani e file sparsi. Nessuna fonte unica su "dove eravamo rimasti", bug noti, decisioni prese.
**Decisione:** Usare `docs/wiki/` (project-status, bug-registry, decisions, open-questions) versionata nel repo Git come knowledge base del progetto.
**Razionale:** Zero infrastruttura aggiuntiva, viaggia con `git pull`, briefing di sessione automatico. Alternativa scartata: note esterne (Notion/file locali) — non versionate, si perdono cambiando macchina.
**Conseguenze:** `docs/wiki/` va committata. `project-status.md` viene aggiornato a fine sessione.
