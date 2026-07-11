/**
 * Biscotto — Core Consent Manager
 * --------------------------------------------------------------------------
 * Vanilla JS, ZERO dipendenze (no jQuery). È il cuore portabile del prodotto:
 * gira identico su WordPress, siti statici, Shopify, ecc.
 *
 * VINCOLO ARCHITETTURALE (vedi consentkit-project.md §4.4):
 *   legge la configurazione ESCLUSIVAMENTE da window.biscottoConfig.
 *   Non conosce WordPress né alcuna piattaforma specifica.
 *
 * Conformità: Linee guida Garante (doc. 9677876) + GDPR/ePrivacy.
 *   - Prior blocking degli script (§13.12)
 *   - Parità Accetta/Rifiuta, X = mantieni default (§13.4)
 *   - Privacy by default: categorie non necessarie su "negato" (§13.5)
 *   - Riproposizione min. 6 mesi + re-consent su policyVersion (§13.6, §13.14)
 *   - Nessun consenso via scroll (§13.2)
 *   - Revoca sempre accessibile (§13.8)
 * --------------------------------------------------------------------------
 */
(function (window, document) {
  'use strict';

  var STORAGE_KEY = 'biscotto_consent';
  var TOGGLEABLE = ['analytics', 'marketing', 'preferences'];

  // --- Config ---------------------------------------------------------------
  var cfg = window.biscottoConfig;
  if (!cfg) {
    if (window.console) console.warn('[Biscotto] window.biscottoConfig mancante: manager non avviato.');
    return;
  }

  var integrations = cfg.integrations || {};
  var bannerCfg = cfg.banner || {};
  var consentDuration = parseInt(cfg.consentDuration, 10) || 365;     // giorni: scadenza del salvataggio
  var repromptAfterDays = parseInt(cfg.repromptAfterDays, 10) || 180; // Garante: min 6 mesi prima di riproporre
  var policyVersion = String(cfg.policyVersion || '1');
  var forceRenewDate = cfg.forceRenewDate ? Date.parse(cfg.forceRenewDate) : null;
  var position = (cfg.position === 'modal' || cfg.position === 'box-right' || cfg.position === 'box-left') ? cfg.position : 'bottom-bar';

  // --- gtag / dataLayer bootstrap ------------------------------------------
  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }

  // --- Stato ----------------------------------------------------------------
  var currentState = defaultState();

  function defaultState() {
    return { necessary: true, analytics: false, marketing: false, preferences: false };
  }
  function nowSec() { return Math.floor(Date.now() / 1000); }

  function readStored() {
    try {
      var raw = window.localStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (e) { return null; }
  }

  function isStoredValid(rec) {
    if (!rec || !rec.categories || !rec.timestamp) return false;
    if (String(rec.policyVersion) !== policyVersion) return false;            // policy cambiata → re-consent
    if (forceRenewDate && rec.timestamp * 1000 < forceRenewDate) return false; // rinnovo forzato da admin
    var ageDays = (nowSec() - rec.timestamp) / 86400;
    if (ageDays > consentDuration) return false;                              // salvataggio scaduto
    // repromptAfterDays è il minimo di permanenza: finché il record è valido NON riproponiamo.
    return true;
  }

  function save(state, action) {
    var rec = {
      version: cfg.version || '1.0',
      policyVersion: policyVersion,
      timestamp: nowSec(),
      action: action,
      categories: state
    };
    try { window.localStorage.setItem(STORAGE_KEY, JSON.stringify(rec)); } catch (e) {}
    notifyServer(rec);
    return rec;
  }

  // Log opzionale lato titolare (popolato dall'adattatore: cfg.logEndpoint + cfg.logNonce)
  function notifyServer(rec) {
    if (!cfg.logEndpoint) return;
    try {
      // Il nonce va nel body: sendBeacon non può impostare header custom, e il
      // server lo verifica da lì (vedi class-consentkit-api.php).
      var body = JSON.stringify({
        nonce: cfg.logNonce || '',
        policyVersion: rec.policyVersion,
        timestamp: rec.timestamp,
        action: rec.action,
        categories: rec.categories
      });
      if (navigator.sendBeacon) {
        navigator.sendBeacon(cfg.logEndpoint, new Blob([body], { type: 'application/json' }));
      } else {
        fetch(cfg.logEndpoint, {
          method: 'POST', keepalive: true,
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.logNonce || '' },
          body: body
        });
      }
    } catch (e) { /* il log è best-effort, non deve mai rompere il frontend */ }
  }

  // --- Applicazione del consenso -------------------------------------------
  function applyState(state) {
    updateConsentMode(state);
    pushDataLayer(state);
    activateScripts(state);   // prior blocking → attivazione
    toggleLinkedIn(state);
  }

  function updateConsentMode(state) {
    if (!integrations.googleConsentMode) return;
    gtag('consent', 'update', {
      ad_storage: state.marketing ? 'granted' : 'denied',
      ad_user_data: state.marketing ? 'granted' : 'denied',
      ad_personalization: state.marketing ? 'granted' : 'denied',
      analytics_storage: state.analytics ? 'granted' : 'denied',
      personalization_storage: state.preferences ? 'granted' : 'denied'
    });
  }

  function pushDataLayer(state) {
    if (!integrations.gtm) return;
    window.dataLayer.push({
      event: 'biscotto_consent_update',
      ck_analytics: state.analytics,
      ck_marketing: state.marketing,
      ck_preferences: state.preferences
    });
  }

  /**
   * Prior blocking (§13.12): attiva gli script bloccati
   *   <script type="text/plain" data-biscotto-category="analytics" data-src="..."></script>
   * Solo dopo l'opt-in della relativa categoria.
   */
  function activateScripts(state) {
    var blocked = document.querySelectorAll('script[type="text/plain"][data-biscotto-category]');
    Array.prototype.forEach.call(blocked, function (node) {
      var cat = node.getAttribute('data-biscotto-category');
      if (!state[cat] || node.getAttribute('data-biscotto-activated')) return;
      var s = document.createElement('script');
      Array.prototype.forEach.call(node.attributes, function (attr) {
        if (attr.name === 'type' || attr.name === 'data-biscotto-category') return;
        if (attr.name === 'data-src') { s.setAttribute('src', attr.value); return; }
        s.setAttribute(attr.name, attr.value);
      });
      if (node.textContent) s.textContent = node.textContent;
      node.parentNode.insertBefore(s, node.nextSibling);
      node.setAttribute('data-biscotto-activated', '1');
    });
  }

  // LinkedIn Insight Tag: caricato solo con consenso marketing (§4.3)
  var liInjected = false;
  function toggleLinkedIn(state) {
    if (!integrations.linkedin || !integrations.linkedinPartnerId) return;
    if (!state.marketing || liInjected) return;
    liInjected = true;
    var pid = String(integrations.linkedinPartnerId);
    window._linkedin_partner_id = pid;
    window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
    window._linkedin_data_partner_ids.push(pid);
    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://snap.licdn.com/li.lms-analytics/insight.min.js';
    document.head.appendChild(s);
  }

  // --- UI helpers -----------------------------------------------------------
  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function (k) {
      if (k === 'text') node.textContent = attrs[k];
      else if (k === 'html') node.innerHTML = attrs[k];
      else node.setAttribute(k, attrs[k]);
    });
    (children || []).forEach(function (c) { if (c) node.appendChild(c); });
    return node;
  }

  function policyLinks() {
    var wrap = el('div', { 'class': 'biscotto-links' });
    if (cfg.privacyPolicyUrl) wrap.appendChild(el('a', { 'class': 'biscotto-link', href: cfg.privacyPolicyUrl, target: '_blank', rel: 'noopener', text: bannerCfg.privacyLabel || 'Privacy policy' }));
    if (cfg.cookiePolicyUrl) wrap.appendChild(el('a', { 'class': 'biscotto-link', href: cfg.cookiePolicyUrl, target: '_blank', rel: 'noopener', text: bannerCfg.cookieLabel || 'Cookie policy' }));
    return wrap.childNodes.length ? wrap : null;
  }

  // --- Banner di prima istanza ---------------------------------------------
  var bannerNode = null;

  function renderBanner() {
    if (bannerNode) return;
    var btnAccept = el('button', { 'class': 'biscotto-btn biscotto-btn-primary', type: 'button', text: bannerCfg.acceptLabel || 'Accetta tutto' });
    var btnReject = el('button', { 'class': 'biscotto-btn biscotto-btn-primary', type: 'button', text: bannerCfg.rejectLabel || 'Rifiuta' });
    var btnManage = el('button', { 'class': 'biscotto-btn biscotto-btn-link', type: 'button', text: bannerCfg.customizeLabel || 'Gestisci preferenze' });
    var btnClose = el('button', { 'class': 'biscotto-close', type: 'button', 'aria-label': bannerCfg.closeLabel || 'Chiudi' , text: '×' });

    btnAccept.addEventListener('click', function () { acceptAll(); });
    btnReject.addEventListener('click', function () { rejectAll(); });
    btnManage.addEventListener('click', function () { openPreferences(); });
    // X = mantieni i default (nessun tracciamento). Non è opt-out (§13.4).
    btnClose.addEventListener('click', function () { keepDefault(); });

    var body = el('div', { 'class': 'biscotto-body' }, [
      el('p', { 'class': 'biscotto-title', text: bannerCfg.title || 'Utilizziamo i cookie' }),
      el('p', { 'class': 'biscotto-text', text: bannerCfg.body || '' }),
      policyLinks()
    ]);

    var actions = el('div', { 'class': 'biscotto-actions' }, [btnManage, btnReject, btnAccept]);

    bannerNode = el('div', {
      'class': 'biscotto-banner biscotto-' + position,
      role: 'dialog', 'aria-modal': 'false', 'aria-label': bannerCfg.title || 'Cookie'
    }, [btnClose, body, actions]);

    document.body.appendChild(bannerNode);
  }

  function removeBanner() {
    if (bannerNode && bannerNode.parentNode) bannerNode.parentNode.removeChild(bannerNode);
    bannerNode = null;
  }

  // --- Pannello preferenze granulari ---------------------------------------
  var prefsNode = null;

  function openPreferences() {
    if (prefsNode) return;
    var rows = [categoryRow('necessary', bannerCfg.necessaryLabel || 'Necessari', true, true)];
    TOGGLEABLE.forEach(function (cat) {
      rows.push(categoryRow(cat, labelFor(cat), currentState[cat], false));
    });

    var btnSave = el('button', { 'class': 'biscotto-btn biscotto-btn-primary', type: 'button', text: bannerCfg.saveLabel || 'Salva preferenze' });
    var btnAcceptAll = el('button', { 'class': 'biscotto-btn biscotto-btn-primary', type: 'button', text: bannerCfg.acceptLabel || 'Accetta tutto' });
    var btnRejectAll = el('button', { 'class': 'biscotto-btn biscotto-btn-primary', type: 'button', text: bannerCfg.rejectLabel || 'Rifiuta' });
    var btnClose = el('button', { 'class': 'biscotto-close', type: 'button', 'aria-label': bannerCfg.closeLabel || 'Chiudi', text: '×' });

    btnSave.addEventListener('click', function () {
      var next = defaultState();
      TOGGLEABLE.forEach(function (cat) {
        var input = prefsNode.querySelector('input[data-cat="' + cat + '"]');
        next[cat] = !!(input && input.checked);
      });
      commit(next, 'custom');
    });
    btnAcceptAll.addEventListener('click', function () { acceptAll(); });
    btnRejectAll.addEventListener('click', function () { rejectAll(); });
    btnClose.addEventListener('click', function () { closePreferences(); });

    var panel = el('div', { 'class': 'biscotto-prefs-panel', role: 'dialog', 'aria-modal': 'true', 'aria-label': bannerCfg.prefsTitle || 'Preferenze cookie' }, [
      btnClose,
      el('p', { 'class': 'biscotto-title', text: bannerCfg.prefsTitle || 'Preferenze cookie' }),
      el('div', { 'class': 'biscotto-prefs-list' }, rows),
      el('div', { 'class': 'biscotto-actions' }, [btnRejectAll, btnSave, btnAcceptAll])
    ]);

    prefsNode = el('div', { 'class': 'biscotto-overlay' }, [panel]);
    document.body.appendChild(prefsNode);
  }

  function closePreferences() {
    if (prefsNode && prefsNode.parentNode) prefsNode.parentNode.removeChild(prefsNode);
    prefsNode = null;
  }

  function labelFor(cat) {
    var labels = bannerCfg.categoryLabels || {};
    return labels[cat] || ({ analytics: 'Analytics', marketing: 'Marketing', preferences: 'Preferenze' }[cat]);
  }

  function categoryRow(cat, label, checked, locked) {
    var input = el('input', { type: 'checkbox', 'data-cat': cat });
    if (checked) input.checked = true;
    if (locked) input.disabled = true;
    var descs = bannerCfg.categoryDescriptions || {};
    var children = [
      el('label', { 'class': 'biscotto-row-head' }, [input, el('span', { text: label })])
    ];
    if (descs[cat]) children.push(el('p', { 'class': 'biscotto-row-desc', text: descs[cat] }));
    return el('div', { 'class': 'biscotto-prefs-row' }, children);
  }

  // --- Pulsante "Rivedi le tue scelte" (revoca sempre accessibile §13.8) ----
  function renderReviewButton() {
    if (document.querySelector('.biscotto-review')) return;
    var btn = el('button', {
      'class': 'biscotto-review', type: 'button',
      'aria-label': bannerCfg.reviewLabel || 'Rivedi le tue scelte sui cookie',
      title: bannerCfg.reviewLabel || 'Rivedi le tue scelte sui cookie',
      text: '🍪'
    });
    btn.addEventListener('click', function () { openPreferences(); });
    document.body.appendChild(btn);
  }

  // Evento pubblico: notifica lo stato del consenso (utile alla pagina cookie
  // policy e a integrazioni custom). detail = { categories, action }.
  function emitConsent(state, action) {
    try {
      var ev;
      if (typeof window.CustomEvent === 'function') {
        ev = new CustomEvent('biscotto:consent', { detail: { categories: state, action: action } });
      } else { // fallback IE
        ev = document.createEvent('CustomEvent');
        ev.initCustomEvent('biscotto:consent', false, false, { categories: state, action: action });
      }
      document.dispatchEvent(ev);
    } catch (e) {}
  }

  // --- Azioni di consenso ---------------------------------------------------
  function commit(state, action) {
    currentState = state;
    save(state, action);
    applyState(state);
    emitConsent(state, action);
    removeBanner();
    closePreferences();
  }

  function acceptAll() { commit({ necessary: true, analytics: true, marketing: true, preferences: true }, 'granted_all'); }
  function rejectAll() { commit(defaultState(), 'rejected_all'); }
  function keepDefault() { commit(defaultState(), 'default_kept'); } // X di chiusura

  // --- Init -----------------------------------------------------------------
  function init() {
    var stored = readStored();
    if (isStoredValid(stored)) {
      currentState = stored.categories;
      applyState(currentState);
    } else if (cfg.showBanner === false) {
      // Sito con soli cookie tecnici (§13.11): nessun banner, default già impostati.
      applyState(currentState);
    } else {
      renderBanner();
    }
    renderReviewButton();
    // Stato iniziale per la pagina cookie policy (anche quando il banner è visibile:
    // riflette i default "negato" finché l'utente non sceglie).
    emitConsent(currentState, stored && stored.action ? stored.action : 'none');
  }

  // API pubblica (utile per integrazioni custom e per il pulsante footer del tema)
  window.Biscotto = {
    open: openPreferences,
    acceptAll: acceptAll,
    rejectAll: rejectAll,
    getConsent: function () { return JSON.parse(JSON.stringify(currentState)); },
    reset: function () { try { window.localStorage.removeItem(STORAGE_KEY); } catch (e) {} location.reload(); }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})(window, document);
