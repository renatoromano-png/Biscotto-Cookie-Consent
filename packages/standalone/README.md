# Biscotto — Standalone (siti non-WordPress)

Usa questa versione su siti statici, Shopify, Webflow, framework JS, ecc. È lo stesso core di `packages/core`, distribuito con la config scritta a mano.

## File

- `consent-mode-default.js` — snippet da inlinare nel `<head>` **prima** di GTM.
- `biscotto.js` — il core consent manager (= `packages/core/src/consent-manager.js`).
- `banner.css` — stili del banner.
- `config-generator.html` — apri nel browser per generare la config con un form.

## Installazione (3 passi)

### 1. Consent Mode default — in cima al `<head>`, PRIMA di GTM

```html
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',personalization_storage:'denied',functionality_storage:'granted',security_storage:'granted',wait_for_update:500});
</script>
<!-- ... qui sotto il tuo snippet GTM/GA4 ... -->
```

### 2. Config + core — prima di `</body>`

```html
<link rel="stylesheet" href="/biscotto/banner.css">
<script>
window.biscottoConfig = {
  version: "1.0",
  policyVersion: "2026-06",      // incrementa per forzare il re-consent
  consentDuration: 365,
  repromptAfterDays: 180,         // minimo 6 mesi (Garante)
  forceRenewDate: null,
  position: "bottom-bar",
  privacyPolicyUrl: "/privacy",
  cookiePolicyUrl: "/cookie-policy",
  integrations: {
    googleConsentMode: true,
    gtm: true,
    linkedin: false,
    linkedinPartnerId: ""
  },
  banner: {
    title: "Utilizziamo i cookie",
    body: "Usiamo cookie tecnici e, previo consenso, cookie di analytics e marketing.",
    acceptLabel: "Accetta tutto",
    rejectLabel: "Rifiuta",
    customizeLabel: "Gestisci preferenze",
    saveLabel: "Salva preferenze",
    closeLabel: "Chiudi",
    reviewLabel: "Rivedi le tue scelte sui cookie",
    prefsTitle: "Preferenze cookie",
    categoryLabels: { analytics: "Analytics", marketing: "Marketing", preferences: "Preferenze" }
  }
};
</script>
<script src="/biscotto/biscotto.js" defer></script>
```

### 3. Prior blocking dei tuoi tag

Tutti gli script che installano cookie non tecnici vanno **bloccati** così, e Biscotto li attiva al consenso della categoria:

```html
<!-- esempio: uno script marketing -->
<script type="text/plain" data-biscotto-category="marketing" data-src="https://example.com/pixel.js"></script>

<!-- esempio: script inline -->
<script type="text/plain" data-biscotto-category="analytics">
  console.log('parte solo dopo consenso analytics');
</script>
```

## API JS

```js
Biscotto.open();        // apre il pannello preferenze
Biscotto.acceptAll();
Biscotto.rejectAll();
Biscotto.getConsent();  // { necessary, analytics, marketing, preferences }
Biscotto.reset();       // cancella e ricarica
```

Per riaprire le preferenze da un link nel footer: `<a href="#" onclick="Biscotto.open();return false">Cookie</a>`.
