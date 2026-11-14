(function () {
  'use strict';
  var root = document.documentElement;
  function active() { return !!(document.fullscreenElement || document.webkitFullscreenElement); }
  function toggle() {
    try {
      if (active()) {
        (document.exitFullscreen || document.webkitExitFullscreen).call(document);
      } else {
        var req = root.requestFullscreen || root.webkitRequestFullscreen;
        if (req) req.call(root);
      }
    } catch (e) { /* Vollbild nicht verfuegbar */ }
  }
  function sync() {
    var on = active() ? 'true' : 'false';
    document.querySelectorAll('#presentFullscreenBtn, [data-ribbon-command="settings_fullscreen"]').forEach(function (b) {
      b.setAttribute('aria-pressed', on);
    });
  }
  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('#presentFullscreenBtn, [data-ribbon-command="settings_fullscreen"]');
    if (!btn) return;
    e.preventDefault();
    toggle();
    /* Fokus abgeben, damit Presenter-Tasten nicht am Knopf haengen bleiben */
    if (btn.blur) btn.blur();
  });
  document.addEventListener('fullscreenchange', sync);
  document.addEventListener('webkitfullscreenchange', sync);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'F5') { e.preventDefault(); if (!active()) toggle(); return; }
    if ((e.key === 'f' || e.key === 'F') && !e.ctrlKey && !e.metaKey && !e.altKey &&
        !/^(INPUT|TEXTAREA|SELECT)$/.test((e.target && e.target.tagName) || '') &&
        !(e.target && e.target.isContentEditable)) {
      e.preventDefault();
      toggle();
    }
  });
})();
