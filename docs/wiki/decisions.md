# Decisioni Architetturali (ADR)

| ID | Titolo | Stato | Data |
|----|--------|-------|------|
| ADR-001 | Adottare project-wiki come memoria persistente | ACCETTATO | 2026-07-12 |

---

## ADR-001 — Adottare la project-wiki come memoria persistente del progetto
**Data:** 2026-07-12
**Stato:** ACCETTATO
**Contesto:** All'inizio di ogni sessione lo stato del progetto veniva ricostruito a mano da git log, piani e file sparsi. Nessuna fonte unica su "dove eravamo rimasti", bug noti, decisioni prese.
**Decisione:** Usare `docs/wiki/` (project-status, bug-registry, decisions, open-questions) versionata nel repo Git come knowledge base del progetto.
**Razionale:** Zero infrastruttura aggiuntiva, viaggia con `git pull`, briefing di sessione automatico. Alternativa scartata: note esterne (Notion/file locali) — non versionate, si perdono cambiando macchina.
**Conseguenze:** `docs/wiki/` va committata. `project-status.md` viene aggiornato a fine sessione.
