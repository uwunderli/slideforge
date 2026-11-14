(function () {
  'use strict';
  var btn = document.getElementById('presentFullscreenBtn');
  if (!btn) return;
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
  function sync() { btn.setAttribute('aria-pressed', active() ? 'true' : 'false'); }
  btn.addEventListener('click', toggle);
  document.addEventListener('fullscreenchange', sync);
  document.addEventListener('webkitfullscreenchange', sync);
  document.addEventListener('keydown', function (e) {
    if ((e.key === 'f' || e.key === 'F') && !e.ctrlKey && !e.metaKey && !e.altKey &&
        !/^(INPUT|TEXTAREA|SELECT)$/.test((e.target && e.target.tagName) || '') &&
        !(e.target && e.target.isContentEditable)) {
      e.preventDefault();
      toggle();
    }
  });
})();
