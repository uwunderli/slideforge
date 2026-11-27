/**
 * Farbwahl der FolienSchmiede über den Hub-Baustein `HubColorPicker` (3.9.2; Regel Urs 09.11.2026 «Markenfarben und Emojis: ein
 * Standard»: Farbfeld, daneben «▾», Pop-up mit den Markenfarben des Hubs als Punkte; keine eigene Liste, keine Eigenbau-Farbwahl).
 *
 * Jedes `<input type="color">` der Seite wird in die Hülle des Bausteins gesetzt (das Feld selbst bleibt dasselbe Element: id,
 * name und alle bestehenden Zuhörer bleiben). Auch später eingefügte Farbfelder (Eigenschaften, Dialoge) per MutationObserver.
 * Ausnahme je Feld: `data-sf-color="aus"`.
 *
 * Ein Klick auf eine Markenfarbe meldet am Feld `change` (Baustein) und zusätzlich `input`, weil der Editor auf `input` hört.
 * Setzt Code den Wert von aussen, gleicht die Vorschau im «▾» beim Öffnen und beim Überfahren nach.
 */
(function () {
  'use strict';
  var P = window.HubColorPicker;
  if (!P) return;

  function wrap(input) {
    if (!input || input.getAttribute('data-sf-color') === 'aus' || input.closest('[data-hub-color-field]')) return;
    var label = input.getAttribute('aria-label') || input.getAttribute('title') || 'Farbe';
    var tpl = document.createElement('div');
    tpl.innerHTML = P.fieldHtml({ value: input.value, label: '', inputAttrs: 'data-sf-color-tmp' });
    var field = tpl.firstElementChild;
    var tmp = field && field.querySelector('input[data-sf-color-tmp]');
    if (!field || !tmp || !input.parentNode) return;
    field.classList.add('sf-color-field');
    input.parentNode.insertBefore(field, input);
    tmp.parentNode.replaceChild(input, tmp);
    if (!input.getAttribute('aria-label')) input.setAttribute('aria-label', label);
    if (input.disabled) field.classList.add('is-disabled');
    var toggle = field.querySelector('[data-hub-color-toggle]');
    var panel = field.querySelector('.hub-color-dropdown__panel');
    if (toggle) {
      toggle.disabled = input.disabled;
      // Der Editor schaltet Felder je Auswahl ein/aus: «▾» folgt dem Feld.
      new MutationObserver(function () {
        toggle.disabled = input.disabled;
        field.classList.toggle('is-disabled', input.disabled);
      }).observe(input, { attributes: true, attributeFilter: ['disabled'] });
      toggle.addEventListener('click', function () { P.setValue(field, input.value, true); }, true);
      // Menüband-Gruppen schneiden über ihren Rand ab (overflow hidden): Pop-up fest am Bildschirm unter dem «▾».
      toggle.addEventListener('click', function () { setTimeout(place, 0); });
      function place() {
        if (!panel || panel.hidden) return;
        var r = toggle.getBoundingClientRect();
        panel.style.position = 'fixed';
        panel.style.zIndex = '500';
        panel.style.top = Math.round(r.bottom + 4) + 'px';
        panel.style.left = Math.round(Math.max(8, r.left)) + 'px';
        panel.style.right = 'auto';
        var pr = panel.getBoundingClientRect();
        if (pr.right > window.innerWidth - 8) panel.style.left = Math.round(Math.max(8, window.innerWidth - pr.width - 8)) + 'px';
        if (pr.bottom > window.innerHeight - 8 && r.top > pr.height + 8) panel.style.top = Math.round(r.top - pr.height - 4) + 'px';
      }
    }
    field.addEventListener('pointerenter', function () { P.setValue(field, input.value, true); });
    field.addEventListener('click', function (e) {
      if (!e.target.closest('[data-hub-brand-color]')) return;
      setTimeout(function () { input.dispatchEvent(new Event('input', { bubbles: true })); }, 0);
    }, true);
    P.bindField(field);
  }

  function scan(root) {
    if (!root || !root.querySelectorAll) return;
    if (root.matches && root.matches('input[type="color"]')) { wrap(root); return; }
    Array.prototype.forEach.call(root.querySelectorAll('input[type="color"]'), wrap);
  }

  function start() {
    scan(document.body);
    new MutationObserver(function (list) {
      list.forEach(function (m) { Array.prototype.forEach.call(m.addedNodes, function (n) { if (n.nodeType === 1) scan(n); }); });
    }).observe(document.body, { childList: true, subtree: true });
  }
  // Vor den Seitenskripten (Editor startet ebenfalls auf DOMContentLoaded, wird aber später registriert).
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();

  window.SfColor = { wrap: wrap, scan: scan };
})();
