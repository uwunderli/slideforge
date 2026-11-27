/**
 * SlideForge ↔ Hub primitives (V2.0a).
 * data-hub-page-busy on forms; data-hub-secure-delete for irreversible deletes.
 */
(function (global) {
  'use strict';

  function busyShow(title, detail) {
    if (!global.HubPageBusy || typeof HubPageBusy.show !== 'function') return;
    HubPageBusy.show({ title: title || 'Bitte warten…', detail: detail || '', soft: true });
  }

  function busyHide() {
    if (global.HubPageBusy && typeof HubPageBusy.hide === 'function') HubPageBusy.hide();
  }

  global.SFBusy = { show: busyShow, hide: busyHide };

  /**
   * App-Ort an den Hub-Rahmen (Hub ≥ 2.33.9; Urs 27.11.: «Wenn ich ctrl+r klicke lande ich auf dem Dashboard statt in der Folie»).
   * Meldet { type:'hub-app-ort', pfad:'/editor.php?…' } (Pfad ab App-Basis); der Hub schreibt ihn als `open=` in seine Adresse.
   * Nur direkt unter dem Hub-Fenster und nur an den geprüften Hub-Origin aus cf-theme.js, nie an '*'.
   */
  var ORT_WEG = ['hub_session', 'hub_origin', 'hub_theme', 'hub_accent', 'hub_chrome', 'open', 'hinweis'];
  function hubOrigin() {
    try {
      if (global.ChurchForgeTheme && typeof global.ChurchForgeTheme.hubOrigin === 'function') return global.ChurchForgeTheme.hubOrigin() || '';
    } catch (e) { /* ignore */ }
    try { return global.sessionStorage.getItem('cf_hub_origin') || ''; } catch (e) { return ''; }
  }
  function meldeOrt(extra) {
    if (global.parent === global || global.parent !== global.top) return;
    var ziel = hubOrigin();
    if (!/^https?:\/\/[^/]+$/.test(ziel)) return;
    var q = new URLSearchParams(global.location.search);
    ORT_WEG.forEach(function (k) { q.delete(k); });
    [global.SF_APP_ORT_EXTRA, extra].forEach(function (o) {
      if (!o || typeof o !== 'object') return;
      Object.keys(o).forEach(function (k) { q.set(k, String(o[k])); });
    });
    var pfad = global.location.pathname;
    var s = q.toString();
    pfad = pfad.slice(pfad.lastIndexOf('/')) + (s ? '?' + s : '');
    try { global.parent.postMessage({ type: 'hub-app-ort', pfad: pfad }, ziel); } catch (e) { /* ignore */ }
  }
  global.SFAppOrt = { melde: meldeOrt };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { meldeOrt(); });
  else meldeOrt();

  document.addEventListener(
    'submit',
    function (e) {
      const form = e.target;
      if (!(form instanceof HTMLFormElement)) return;
      if (!form.hasAttribute('data-hub-page-busy')) return;
      const title = form.getAttribute('data-hub-page-busy') || 'Bitte warten…';
      busyShow(title);
    },
    true
  );

  document.addEventListener(
    'submit',
    function (e) {
      const form = e.target;
      if (!(form instanceof HTMLFormElement)) return;
      if (!form.hasAttribute('data-hub-secure-delete')) return;
      if (form.dataset.hubSecureDeleteBypass === '1') {
        delete form.dataset.hubSecureDeleteBypass;
        return;
      }
      e.preventDefault();
      const ask = global.HubSecureDelete && typeof HubSecureDelete.ask === 'function'
        ? HubSecureDelete.ask.bind(HubSecureDelete)
        : null;
      if (!ask) {
        if (global.CfStatus) CfStatus.show('error', 'Sichere Löschmethode nicht geladen');
        return;
      }
      ask({
        title: form.getAttribute('data-hub-secure-delete-title') || 'Löschen?',
        message: form.getAttribute('data-hub-secure-delete-message') || '',
        okLabel: form.getAttribute('data-hub-secure-delete-ok') || 'Endgültig löschen',
        cancelLabel: form.getAttribute('data-hub-secure-delete-cancel') || 'Abbrechen',
        inputLabel: form.getAttribute('data-hub-secure-delete-input') || 'Bestätigungswort',
      }).then(function (ok) {
        if (!ok) return;
        form.dataset.hubSecureDeleteBypass = '1';
        busyShow(form.getAttribute('data-hub-secure-delete-busy') || 'Wird gelöscht…');
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
      });
    },
    true
  );
})(window);
