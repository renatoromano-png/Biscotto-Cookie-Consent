/**
 * Biscotto — Scan collector (roadmap §14, v1.1)
 * --------------------------------------------------------------------------
 * Gira SOLO dentro l'iframe nascosto avviato dall'admin in scan-mode
 * (window.__biscottoScanMode impostato dal pre-grant lato server). Con il consenso
 * già forzato ad "accettato", i tag partono: dopo il load + una breve attesa
 * raccogliamo cosa è stato caricato e lo inviamo al parent via postMessage.
 *
 * NON tocca i visitatori: lo scan-mode è gated da nonce + capability admin
 * lato server (vedi class-consentkit-scanner.php).
 * --------------------------------------------------------------------------
 */
(function (window, document) {
  'use strict';

  if (!window.__biscottoScanMode) { return; }

  // Attesa dopo il load per dare tempo ai tag (GA/Ads/Maps) di sparare e
  // impostare cookie / scaricare risorse di terze parti.
  var SETTLE_MS = 4000;

  function hostFrom(url) {
    try { return new URL(url, window.location.href).hostname.toLowerCase(); }
    catch (e) { return ''; }
  }

  function cookieNames() {
    var out = [];
    try {
      (document.cookie || '').split(';').forEach(function (pair) {
        var name = pair.split('=')[0];
        if (name) { name = name.trim(); }
        if (name) { out.push(name); }
      });
    } catch (e) {}
    return out;
  }

  function storageKeys() {
    var out = [];
    ['localStorage', 'sessionStorage'].forEach(function (store) {
      try {
        var s = window[store];
        for (var i = 0; i < s.length; i++) { out.push(s.key(i)); }
      } catch (e) {}
    });
    return out;
  }

  function resourceHosts() {
    var hosts = {};
    try {
      var entries = window.performance.getEntriesByType('resource');
      entries.forEach(function (entry) {
        var h = hostFrom(entry.name);
        if (h) { hosts[h] = true; }
      });
    } catch (e) {}
    return Object.keys(hosts);
  }

  function iframeSrcs() {
    var out = [];
    try {
      Array.prototype.forEach.call(document.querySelectorAll('iframe[src]'), function (f) {
        var src = f.getAttribute('src');
        if (src) { out.push(src); }
      });
    } catch (e) {}
    return out;
  }

  function linkHosts() {
    // <link> esterni (es. Google Fonts) → host di terze parti senza cookie.
    var hosts = {};
    try {
      Array.prototype.forEach.call(document.querySelectorAll('link[href]'), function (l) {
        var h = hostFrom(l.getAttribute('href'));
        if (h) { hosts[h] = true; }
      });
    } catch (e) {}
    return Object.keys(hosts);
  }

  function collectAndSend() {
    var finding = null;
    try {
      // Host unici da risorse + link + src degli iframe.
      var hostSet = {};
      resourceHosts().forEach(function (h) { hostSet[h] = true; });
      linkHosts().forEach(function (h) { hostSet[h] = true; });
      var iframes = iframeSrcs();
      iframes.forEach(function (src) { var h = hostFrom(src); if (h) { hostSet[h] = true; } });

      finding = {
        url: window.location.href,
        cookies: cookieNames(),
        storage: storageKeys(),
        hosts: Object.keys(hostSet),
        iframes: iframes
      };
    } finally {
      // Ripristina lo stato SEMPRE: il pre-grant (consenso forzato) non deve
      // restare nella sessione dell'admin, anche se la raccolta fallisce.
      try { window.localStorage.removeItem('biscotto_consent'); } catch (e) {}
    }

    if (finding) {
      try {
        window.parent.postMessage(
          { __biscottoScan: true, finding: finding },
          window.location.origin
        );
      } catch (e) {}
    }
  }

  function start() { window.setTimeout(collectAndSend, SETTLE_MS); }

  if (document.readyState === 'complete') {
    start();
  } else {
    window.addEventListener('load', start);
  }

})(window, document);
