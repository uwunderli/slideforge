/**
 * Einstellungen der FolienSchmiede (3.8.3): automatisches Speichern (Hub-Standard Einstellungen, kein Speichern-Knopf).
 *
 * form[data-sf-autosave]: jede Änderung schickt das Formular (FormData + ajax=1) an die eigene Seite; Antwort {ok, error, message}.
 * Anzeige im [data-sf-save-state] des Formulars: «speichert …» / «gespeichert» / Fehlertext. Geheimfelder ([data-sf-secret]) werden
 * nach dem Speichern geleert und zeigen «unverändert lassen».
 * form[data-sf-submit-on-pick]: der FileForge-Dialog (SfFf.bindFields) löst change am versteckten Feld aus → Formular absenden.
 */
(function () {
  'use strict';

  function bindAutosave(form) {
    var state = form.querySelector('[data-sf-save-state]');
    var timer = null, running = null, again = false;
    function show(kind, text) {
      if (!state) return;
      state.textContent = text;
      state.setAttribute('data-state', kind);
    }
    function send() {
      if (running) { again = true; return; }
      var fd = new FormData(form);
      fd.set('ajax', '1');
      show('busy', 'speichert …');
      running = fetch(window.location.pathname + window.location.search, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Antwort nicht lesbar (' + r.status + ').' }; }); })
        .then(function (j) {
          if (j && j.ok) {
            show('ok', 'gespeichert');
            Array.prototype.forEach.call(form.querySelectorAll('[data-sf-secret]'), function (el) {
              if (el.value !== '') { el.value = ''; el.placeholder = el.getAttribute('data-keep-ph') || el.placeholder; }
            });
          } else {
            show('error', (j && j.error) || 'Nicht gespeichert.');
          }
        })
        .catch(function () { show('error', 'Nicht gespeichert (keine Verbindung).'); })
        .then(function () { running = null; if (again) { again = false; send(); } });
    }
    function schedule() { clearTimeout(timer); timer = setTimeout(send, 350); }
    form.addEventListener('change', schedule);
    // Enter im Feld = speichern; Knöpfe mit eigener Aktion (Duplizieren, Löschen) senden normal und laden die Seite neu.
    form.addEventListener('submit', function (e) {
      if (e.submitter && e.submitter.name === 'action') return;
      e.preventDefault();
      schedule();
    });
  }

  function bindSubmitOnPick(form) {
    form.addEventListener('change', function (e) {
      var t = e.target;
      if (t && t.type === 'hidden' && t.value !== '') form.submit();
    });
  }

  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('form[data-sf-autosave]'), bindAutosave);
    Array.prototype.forEach.call(document.querySelectorAll('form[data-sf-submit-on-pick]'), bindSubmitOnPick);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
