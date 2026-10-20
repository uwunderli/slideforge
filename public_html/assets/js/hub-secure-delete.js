/**
 * HubSecureDelete — Sichere Löschmethode (Wort eintippen).
 * Quelle: hub/public/assets/js/hub-secure-delete.js — Module vendorn, nicht forken.
 *
 * Vorbild ArchiveForge Episode löschen: Bestätigungswort anzeigen,
 * OK erst aktiv wenn Eingabe (case-insensitive) übereinstimmt.
 *
 * API:
 *   HubSecureDelete.ask({
 *     title?, message?, word?, wordHint?,
 *     okLabel?, cancelLabel?, inputLabel?
 *   }) → Promise<boolean>
 *   HubSecureDelete.randomWord() → string
 *
 * Klassen: .hub-secure-delete (Overlay), .hub-secure-delete__dialog,
 *   .hub-secure-delete__word, .hub-secure-delete__input, …
 */
(function (global) {
  'use strict';

  var WORDS = [
    'kirche', 'glocke', 'kerze', 'predigt', 'orgel', 'bibel', 'gemeinde',
    'sonntag', 'licht', 'frieden', 'hoffnung', 'anker', 'taube', 'krone',
    'stern', 'welle', 'berg', 'tal', 'bach', 'wald', 'ahorn', 'eiche',
    'rose', 'lilie', 'honig', 'brot', 'wein', 'salz', 'feuer', 'wasser'
  ];

  var active = null;

  function randomWord() {
    return WORDS[Math.floor(Math.random() * WORDS.length)];
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function ensureHost() {
    var el = document.getElementById('hubSecureDelete');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'hubSecureDelete';
    el.className = 'hub-secure-delete';
    el.hidden = true;
    el.setAttribute('aria-hidden', 'true');
    document.body.appendChild(el);
    return el;
  }

  /**
   * @param {{
   *   title?: string,
   *   message?: string,
   *   word?: string,
   *   wordHint?: string,
   *   wordHintHtml?: string,
   *   extraHtml?: string,
   *   okLabel?: string,
   *   cancelLabel?: string,
   *   inputLabel?: string,
   *   onConfirm?: (host: HTMLElement) => void
   * }} opts
   * @returns {Promise<boolean>}
   */
  function ask(opts) {
    opts = opts || {};
    if (active) {
      try { active.finish(false); } catch (_) {}
    }

    var word = String(opts.word || randomWord()).trim();
    if (!word) word = randomWord();
    var title = opts.title || 'Löschen?';
    var message = opts.message || '';
    var okLabel = opts.okLabel || 'Endgültig löschen';
    var cancelLabel = opts.cancelLabel || 'Abbrechen';
    var inputLabel = opts.inputLabel || 'Bestätigungswort';
    var wordHintHtml = opts.wordHintHtml
      || ('Zur Bestätigung bitte das Wort <strong class="hub-secure-delete__word">' + esc(word) + '</strong> eintippen:');
    var extraHtml = opts.extraHtml ? String(opts.extraHtml) : '';

    var host = ensureHost();
    host.innerHTML =
      '<div class="hub-secure-delete__dialog" role="dialog" aria-modal="true" aria-labelledby="hubSecureDeleteTitle">' +
      '  <h3 class="hub-secure-delete__title" id="hubSecureDeleteTitle">' + esc(title) + '</h3>' +
      (message
        ? '<p class="hub-secure-delete__msg">' + esc(message) + '</p>'
        : '') +
      (extraHtml ? '<div class="hub-secure-delete__extra">' + extraHtml + '</div>' : '') +
      '  <p class="hub-secure-delete__hint">' + wordHintHtml + '</p>' +
      '  <label class="hub-admin-field hub-secure-delete__field">' +
      '    <span>' + esc(inputLabel) + '</span>' +
      '    <input type="text" class="hub-secure-delete__input" autocomplete="off" spellcheck="false"' +
      '      placeholder="' + esc(word) + '" aria-label="' + esc(inputLabel) + '">' +
      '  </label>' +
      '  <div class="hub-secure-delete__actions hub-dialog-actions">' +
      '    <button type="button" class="hub-btn hub-btn--ghost" data-hub-secure-cancel>' + esc(cancelLabel) + '</button>' +
      '    <button type="button" class="hub-btn hub-btn--danger" data-hub-secure-ok disabled>' + esc(okLabel) + '</button>' +
      '  </div>' +
      '</div>';

    var input = host.querySelector('.hub-secure-delete__input');
    var okBtn = host.querySelector('[data-hub-secure-ok]');
    var cancelBtn = host.querySelector('[data-hub-secure-cancel]');
    var target = word.toLowerCase();

    function sync() {
      var typed = String(input && input.value || '').trim().toLowerCase();
      if (okBtn) okBtn.disabled = !(target && typed === target);
    }

    return new Promise(function (resolve) {
      var finished = false;
      function finish(ok) {
        if (finished) return;
        finished = true;
        active = null;
        host.hidden = true;
        host.setAttribute('aria-hidden', 'true');
        host.removeEventListener('click', onBack);
        document.removeEventListener('keydown', onKey);
        input && input.removeEventListener('input', sync);
        cancelBtn && cancelBtn.removeEventListener('click', onCancel);
        okBtn && okBtn.removeEventListener('click', onOk);
        host.innerHTML = '';
        resolve(!!ok);
      }

      function onCancel() { finish(false); }
      function onOk() {
        sync();
        if (okBtn && !okBtn.disabled) {
          if (typeof opts.onConfirm === 'function') {
            try { opts.onConfirm(host); } catch (_) {}
          }
          finish(true);
        }
      }
      function onBack(e) {
        if (e.target === host) finish(false);
      }
      function onKey(e) {
        if (e.key === 'Escape') {
          e.preventDefault();
          finish(false);
          return;
        }
        if (e.key === 'Enter' && e.target === input) {
          e.preventDefault();
          onOk();
        }
      }

      active = { finish: finish };
      host.hidden = false;
      host.setAttribute('aria-hidden', 'false');
      host.addEventListener('click', onBack);
      document.addEventListener('keydown', onKey);
      input && input.addEventListener('input', sync);
      cancelBtn && cancelBtn.addEventListener('click', onCancel);
      okBtn && okBtn.addEventListener('click', onOk);
      sync();
      window.setTimeout(function () {
        if (input) {
          input.value = '';
          sync();
          input.focus();
        }
      }, 30);
    });
  }

  global.HubSecureDelete = {
    ask: ask,
    randomWord: randomWord,
    WORDS: WORDS.slice()
  };
})(typeof window !== 'undefined' ? window : this);
