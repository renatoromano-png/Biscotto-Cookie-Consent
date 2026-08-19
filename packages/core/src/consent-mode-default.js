/**
 * Biscotto — Google Consent Mode v2 DEFAULT
 * --------------------------------------------------------------------------
 * Questo snippet DEVE essere iniettato nel <head> PRIMA dello snippet GTM/GA4
 * (vedi biscotto-project.md §4.2, §13.7). Imposta tutto su "denied" finché
 * l'utente non presta il consenso: è il presupposto del prior blocking lato
 * Google.
 *
 * - WordPress: emesso da class-biscotto-frontend.php su wp_head priorità 1.
 * - Siti statici / standalone: copia-incolla questo blocco in cima al <head>,
 *   prima di GTM. NON usare async/defer.
 * --------------------------------------------------------------------------
 */
window.dataLayer = window.dataLayer || [];
function gtag() { dataLayer.push(arguments); }
gtag('consent', 'default', {
  ad_storage: 'denied',
  ad_user_data: 'denied',
  ad_personalization: 'denied',
  analytics_storage: 'denied',
  personalization_storage: 'denied',
  functionality_storage: 'granted',
  security_storage: 'granted',
  wait_for_update: 500
});
