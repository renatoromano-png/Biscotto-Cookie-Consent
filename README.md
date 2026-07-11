# Biscotto

Cookie consent manager open source (GPL-2.0), conforme alle Linee guida del Garante Privacy italiano e a GDPR/ePrivacy. **Nessun limite** su pagine, Custom Post Type o pageview.

- ✅ Banner conforme: X di chiusura, parità Accetta/Rifiuta, link informativa, preferenze granulari
- ✅ **Prior blocking** degli script finché manca il consenso
- ✅ Google **Consent Mode v2**, Google Tag Manager, LinkedIn Insight Tag
- ✅ Riproposizione ≥ 6 mesi + **re-consent** al cambio cookie policy
- ✅ Log opzionale dei consensi (pseudonimizzato) per audit GDPR
- ✅ Core JS **zero dipendenze**, riusabile anche fuori da WordPress

## Architettura — core + adattatori

Biscotto è un **monorepo**: un core JavaScript condiviso e adattatori sottili per piattaforma.

```
packages/
├── core/         Core JS portabile (single source of truth)
├── wordpress/    Adattatore WordPress (il plugin)
└── standalone/   Adattatore generico (siti non-WordPress)
```

Il `core` non dipende da nessuna piattaforma: riceve tutto da `window.biscottoConfig`. Gli adattatori si limitano a popolare quell'oggetto e a piazzare il Consent Mode default nel `<head>`.

## Le due modalità d'uso

| | Opzione A — Core standalone | Opzione B — Core + adattatori |
|---|---|---|
| Per chi | Siti statici, Shopify, Webflow, dev | Utenti WordPress + siti non-WP |
| Config | Scritta a mano / `config-generator.html` | WP: pannello admin · Standalone: form |
| Stato | In sviluppo | **Implementata** |

## Installazione

### WordPress
1. Copia `packages/wordpress/` in `wp-content/plugins/biscotto/` (con il core già incluso in `public/`, vedi build).
2. Attiva e configura in **Impostazioni → Biscotto**.

### Siti non-WordPress
Vedi [`packages/standalone/README.md`](packages/standalone/README.md).

## Build

Il core è l'unica fonte di verità. Per propagarlo agli adattatori:

```bash
bash tools/build.sh
```

## Documentazione

- Core e API JS: [`packages/core/README.md`](packages/core/README.md)
- Uso su siti non-WordPress: [`packages/standalone/README.md`](packages/standalone/README.md)
- Conformità normativa (Garante + GDPR/ePrivacy): vedi sezione dedicata nel README del core

## Licenza

GPL-2.0-or-later. Vedi [LICENSE](LICENSE).

> **Disclaimer:** Biscotto fornisce gli strumenti tecnici per la conformità. La conformità complessiva dipende anche dalla corretta informativa privacy e dalla classificazione reale dei cookie del sito. Non costituisce consulenza legale.
