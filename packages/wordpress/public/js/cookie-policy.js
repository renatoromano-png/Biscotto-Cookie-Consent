/**
 * Biscotto — Cookie policy page helper.
 * Popola lo stato del consenso negli shortcode [consentkit_consent_settings] /
 * [consentkit_cookie_policy] e collega i pulsanti "Gestisci le tue scelte" al
 * pannello preferenze. Si aggiorna in tempo reale via evento biscotto:consent.
 */
(function (window, document) {
  'use strict';

  var cfg = window.consentkitPolicy || {};
  var labels = cfg.categories || {};
  var ORDER = ['necessary', 'analytics', 'marketing', 'preferences'];

  function render(state) {
    state = state || {};
    var lists = document.querySelectorAll('[data-biscotto-consent-state]');
    if (!lists.length) { return; }

    Array.prototype.forEach.call(lists, function (ul) {
      ul.innerHTML = '';
      ORDER.forEach(function (cat) {
        // "necessary" è sempre attivo e non disattivabile.
        var on = cat === 'necessary' ? true : !!state[cat];
        var li = document.createElement('li');
        li.className = 'biscotto-state-row biscotto-state-' + (on ? 'on' : 'off');

        var name = document.createElement('span');
        name.className = 'biscotto-state-name';
        name.textContent = (labels[cat] || cat) + ': ';

        var val = document.createElement('span');
        val.className = 'biscotto-state-val';
        val.textContent = on ? (cfg.granted || 'on') : (cfg.denied || 'off');

        li.appendChild(name);
        li.appendChild(val);
        ul.appendChild(li);
      });
    });
  }

  function currentState() {
    try {
      if (window.Biscotto && typeof window.Biscotto.getConsent === 'function') {
        return window.Biscotto.getConsent();
      }
    } catch (e) {}
    return null;
  }

  function wireButtons() {
    Array.prototype.forEach.call(document.querySelectorAll('.biscotto-policy-manage'), function (btn) {
      if (btn.getAttribute('data-biscotto-wired')) { return; }
      btn.setAttribute('data-biscotto-wired', '1');
      btn.addEventListener('click', function () {
        if (window.Biscotto && typeof window.Biscotto.open === 'function') {
          window.Biscotto.open();
        }
      });
    });
  }

  // Aggiornamento live quando l'utente cambia le scelte nel pannello.
  document.addEventListener('biscotto:consent', function (e) {
    render(e && e.detail ? e.detail.categories : currentState());
  });

  function start() {
    wireButtons();
    render(currentState());
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }

})(window, document);
