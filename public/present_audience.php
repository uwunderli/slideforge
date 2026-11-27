<?php
/**
 * Zuschauer-Ansicht für lokale Präsentation (Beamer/ zweiter Monitor).
 * Zuschauer-Ansicht: folgt live.php, nur mit Anmeldung.
 * `mit=1` (Mitlaufen, 3.8.0): folgt dem Editor (Kanal «editor») und übernimmt jede gespeicherte Änderung ohne Neuladen der Seite.
 */
require __DIR__ . '/../config.php';
$id = (string)($_GET['id'] ?? '');
// Anzeige-Gerät (27.11.2026): Gerätesitzung nur für diese eine Präsentation (`player.php`, `Geraete`), sonst Anmeldung wie bisher.
$alsGeraet = Geraete::darf($id);
if (!$alsGeraet) {
    Auth::requireLogin();
}
$me = $alsGeraet ? null : Auth::currentUser();

if (!$alsGeraet && !Presentation::canView($id, $me['id'])) {
    http_response_code(403);
    die('Kein Zugriff.');
}

$meta = Presentation::getMeta($id);
$slidesData = Storage::read(Presentation::dir($id) . '/slides.json', ['slides' => []]);
$sections = SlideRenderer::renderSections($slidesData, null);
$screenIndex = isset($_GET['screen']) ? max(0, (int)$_GET['screen']) : 0;
// Folien als JSON: Anzeige-Gerät (nächste Präsentation ohne Neuladen, `player.php?json=1` hat das Ticket eingelöst) und Mitlaufen
// (neuer Speicherstand). Zugriff ist oben geprüft (Gerätesitzung oder canView).
if (($_GET['teil'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok' => true, 'id' => $id, 'title' => (string)($meta['title'] ?? ''),
        'width' => (int)$meta['width'], 'height' => (int)$meta['height'], 'sections' => $sections,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
$geraetModus = $alsGeraet && isset($_GET['geraet']);
$mitModus = ($_GET['mit'] ?? '') === '1';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Permissions-Policy" content="window-management=(self), fullscreen=(self)">
<title><?= h($meta['title']) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/reveal.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/theme/black.css">
<?php /* Schriften (3.9.0): Hub-Schriften und alte eigene Schriften wie im Presenter, sonst fällt der Zuschauer auf Ersatzschriften. */ ?>
<?= FontLibrary::headMarkup('fonts.css.php') ?>
<style>
html,body{margin:0;height:100%;background:#000;} .sf-object{color:#fff;}
.sf-laser-dot{
  position:fixed; z-index:9999; border-radius:50%; pointer-events:none;
  transform:translate(-50%,-50%); opacity:0.92;
}
.sf-laser-trail-dot{
  position:fixed; z-index:9998; border-radius:50%; pointer-events:none;
  transform:translate(-50%,-50%);
}
#sfPresentOverlay{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.82);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;padding:24px;text-align:center;}
#sfPresentOverlay[hidden]{display:none;}
#sfPresentOverlay p{margin:0;color:rgba(255,255,255,.85);font:14px/1.45 system-ui,sans-serif;max-width:360px;}
#sfPresentFsBtn{font:600 15px system-ui,sans-serif;padding:12px 28px;border:none;border-radius:8px;background:#3a6c8d;color:#fff;cursor:pointer;}
#sfPresentFsBtn:hover{filter:brightness(1.12);}
</style>
<?= Exporter::mediaStatusMarkup() ?>
</head>
<body>
<div class="reveal">
  <div class="slides">
    <?= $sections ?>
  </div>
</div>
<?php if ($geraetModus): ?>
<div id="sfGeraetStart" role="button" tabindex="0" style="position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.78);color:#fff;font:600 min(4.5vh,7vw)/1.3 'Open Sans','Segoe UI',sans-serif;text-align:center;padding:4vh;cursor:pointer;outline-offset:-6px"><?= h(t('present.device_start')) ?></div>
<?php endif; ?>
<div id="sfPresentOverlay" hidden>
  <p><?= h(t('present.fullscreen_hint')) ?></p>
  <button type="button" id="sfPresentFsBtn"><?= h(t('present.fullscreen_start')) ?></button>
</div>
<script src="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/reveal.js"></script>
<script src="assets/js/present-laser-audience.js?v=<?= ASSET_VERSION ?>"></script>
<script>
  Reveal.initialize({
    width: <?= (int)$meta['width'] ?>,
    height: <?= (int)$meta['height'] ?>,
    hash: false,
    controls: <?= ($meta['show_controls'] ?? true) ? 'true' : 'false' ?>,
    progress: <?= ($meta['show_progress'] ?? true) ? 'true' : 'false' ?>,
    center: false,
    margin: 0,
    keyboard: false,
    touch: false
  });

  // Ton auf dieser Beamer-Ansicht; Timed-Start nur via live-Befehl der Konsole.
  window.SF_MEDIA_AUDIBLE = true;
  window.SF_MEDIA_FOLLOW_LIVE = true;
  window.SF_MEDIA_UNLOCK_HINT = <?= json_encode(t('present.media_audio_unlock'), JSON_UNESCAPED_UNICODE) ?>;
<?= Exporter::mediaRuntimeJs(false) ?>
<?= Exporter::mediaSlideBootJs(false) ?>

  window.sfLiveId = <?= json_encode($id) ?>;
  window.sfLiveChannel = <?= $mitModus ? "'editor'" : "'present'" ?>;
  (function () {
    var targetScreenIndex = <?= (int)$screenIndex ?>;
    var overlay = document.getElementById('sfPresentOverlay');
    var fsBtn = document.getElementById('sfPresentFsBtn');

    function showOverlay() {
      if (<?= $geraetModus ? 'true' : 'false' ?>) return; // Anzeige-Gerät: eigener Tipp-Hinweis (sfGeraetStart)
      if (window.sfLiveChannel === 'editor') return; // Mitlaufen im Fenster: kein Vollbild-Zwang
      if (overlay) overlay.hidden = false;
    }
    function hideOverlay() {
      if (overlay) overlay.hidden = true;
    }

    async function resolveTargetScreen(index) {
      if (!window.getScreenDetails) return null;
      try {
        var details = await window.getScreenDetails();
        return details.screens[index] || details.screens.find(function (s) { return !s.isPrimary; }) || details.screens[0] || null;
      } catch (e) {
        return null;
      }
    }

    async function enterPresentFullscreen(screenIndex) {
      if (typeof screenIndex === 'number') targetScreenIndex = screenIndex;
      var el = document.documentElement;
      var opts = { navigationUI: 'hide' };
      var screen = await resolveTargetScreen(targetScreenIndex);
      if (screen) opts.screen = screen;
      var req = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
      if (!req) {
        showOverlay();
        return false;
      }
      try {
        await req.call(el, opts);
        hideOverlay();
        return true;
      } catch (e) {
        try {
          await req.call(el);
          hideOverlay();
          return true;
        } catch (e2) {
          showOverlay();
          return false;
        }
      }
    }

    function schedulePositionAttempts() {
      if (!window.opener || window.opener.closed) return;
      var attempts = 0;
      var timer = setInterval(function () {
        attempts++;
        try {
          window.opener.postMessage({ type: 'sf_present_reposition', screenIndex: targetScreenIndex }, window.location.origin);
        } catch (e) {}
        if (attempts >= 6) clearInterval(timer);
      }, 250);
    }

    window.addEventListener('message', function (e) {
      if (e.origin !== window.location.origin) return;
      if (!e.data) return;
      if (e.data.type === 'sf_present') {
        enterPresentFullscreen(typeof e.data.screenIndex === 'number' ? e.data.screenIndex : targetScreenIndex);
      }
    });

    if (fsBtn) {
      fsBtn.addEventListener('click', function () {
        enterPresentFullscreen(targetScreenIndex);
      });
    }

    document.addEventListener('fullscreenchange', function () {
      if (document.fullscreenElement) hideOverlay();
    });

    Reveal.on('ready', function () { enterPresentFullscreen(targetScreenIndex); });
    window.addEventListener('load', function () {
      enterPresentFullscreen(targetScreenIndex);
      schedulePositionAttempts();
    });


    // Tastaturfokus liegt oft im Beamer-Fenster (Presenter/Pfeiltasten): an die Konsole weiterreichen.
    document.addEventListener('keydown', function (e) {
      if (e.ctrlKey || e.metaKey || e.altKey) return;
      if (e.key === 'F5' || ((e.key === 'f' || e.key === 'F') && !document.fullscreenElement)) { e.preventDefault(); enterPresentFullscreen(targetScreenIndex); return; }
      if (window.sfLiveChannel === 'editor') return; // Mitlaufen: die Folie wählt der Editor
      var dir = null;
      if (e.key === 'ArrowRight' || e.key === 'ArrowDown' || e.key === 'PageDown' || e.key === ' ' || e.key === 'Enter') dir = 'next';
      else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp' || e.key === 'PageUp') dir = 'prev';
      if (!dir) return;
      e.preventDefault();
      try {
        if (window.opener && !window.opener.closed) {
          window.opener.postMessage({ type: 'sf_present_key', dir: dir }, window.location.origin);
        }
      } catch (err) { /* Konsole nicht erreichbar */ }
    });
    var lastIndex = null;
    var lastFrag = undefined;
    var lastMediaCmdTs = null;
    var lastRev = null;
    var revBusy = false;
    var revNochmal = false;   // während eines Abrufs neu gespeichert → danach nochmals holen
    var secHtml = null;   // zuletzt geladene Folien-HTML je Folie (Vergleich: nur geänderte Folien tauschen)

    // Mitlaufen: alle Effekte sichtbar wie im Editor.
    function alleSichtbar() {
      if (window.sfLiveChannel !== 'editor') return;
      document.querySelectorAll('.reveal .slides .fragment').forEach(function (el) { el.classList.add('visible'); });
    }
    Reveal.on('slidechanged', alleSichtbar);
    Reveal.on('ready', alleSichtbar);

    // Neuer Speicherstand: Folien holen, nur geänderte <section> austauschen (kein Flackern, Geste/Vollbild/Ton bleiben).
    // nurMerken: erster Stand nach dem Laden — nur festhalten, was angezeigt wird, nichts tauschen.
    function neuerStand(nurMerken) {
      if (revBusy) { revNochmal = true; return; }
      revBusy = true;
      fetch('present_audience.php?id=' + encodeURIComponent(window.sfLiveId) + '&teil=1', { credentials: 'same-origin', cache: 'no-store' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d || !d.ok || d.id !== window.sfLiveId) return;
          var tpl = document.createElement('template');
          tpl.innerHTML = d.sections;
          if (nurMerken) { secHtml = Array.prototype.map.call(tpl.content.children, function (el) { return el.outerHTML; }); return; }
          var neu = Array.prototype.slice.call(tpl.content.children);
          var host = document.querySelector('.reveal .slides');
          var alt = Array.prototype.slice.call(host.children);
          if (alt.length !== neu.length || !secHtml || secHtml.length !== neu.length) {
            host.innerHTML = d.sections;
          } else {
            neu.forEach(function (el, i) {
              if (secHtml[i] === el.outerHTML) return;
              var old = alt[i];
              ['present', 'past', 'future', 'stack'].forEach(function (c) { if (old.classList.contains(c)) el.classList.add(c); });
              if (old.getAttribute('style')) el.setAttribute('style', old.getAttribute('style'));
              if (old.hasAttribute('hidden')) el.setAttribute('hidden', '');
              if (old.hasAttribute('aria-hidden')) el.setAttribute('aria-hidden', old.getAttribute('aria-hidden'));
              old.querySelectorAll('video, audio').forEach(function (m) { try { m.pause(); } catch (e) {} });
              host.replaceChild(el, old);
            });
          }
          secHtml = Array.prototype.map.call(tpl.content.children, function (el) { return el.outerHTML; });
          try { Reveal.configure({ width: d.width, height: d.height }); } catch (e) {}
          Reveal.sync();
          if (typeof lastIndex === 'number') Reveal.slide(lastIndex, 0);
          alleSichtbar();
          if (typeof sfMediaStatusTrackSlide === 'function') { try { sfMediaStatusTrackSlide(Reveal.getCurrentSlide()); } catch (e) {} }
        })
        .catch(function () {})
        .then(function () {
          revBusy = false;
          if (revNochmal) { revNochmal = false; neuerStand(false); }
        });
    }
    // Wechsel des Inhalts ohne Neuladen (Anzeige-Gerät): Live-Stand neu beginnen.
    window.sfLiveReset = function () { lastIndex = null; lastFrag = undefined; lastRev = null; secHtml = null; };

    setInterval(function () {
      fetch('live.php?id=' + encodeURIComponent(window.sfLiveId) + '&channel=' + window.sfLiveChannel)
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.ok || !data.live) return;
          var frag = typeof data.live.frag === 'number' ? data.live.frag : null;
          if (data.live.index !== lastIndex || frag !== lastFrag) {
            lastIndex = data.live.index;
            lastFrag = frag;
            if (frag !== null) { Reveal.slide(data.live.index, 0, frag); }
            else { Reveal.slide(data.live.index, 0); }
            alleSichtbar();
            // Kein lokales sfArmMediaTriggers — sonst Doppelstart mit live-Medienbefehl.
          }
          if (window.sfLiveChannel === 'editor' && typeof data.live.rev === 'number' && data.live.rev !== lastRev) {
            var erst = lastRev === null;
            lastRev = data.live.rev;
            neuerStand(erst);
          }
          var media = data.live.media;
          if (media && media.cmd_ts && media.cmd_ts !== lastMediaCmdTs) {
            lastMediaCmdTs = media.cmd_ts;
            if (typeof sfApplyLiveMediaCommand === 'function') {
              sfApplyLiveMediaCommand(media);
            } else {
              var el = document.querySelector('[data-media-id="' + media.id + '"]');
              if (el) {
                el.muted = false;
                if (media.action === 'play') {
                  if (typeof sfPlayMedia === 'function') sfPlayMedia(el);
                  else { el.play && el.play().catch(function () {}); }
                } else if (media.action === 'pause') { el.pause && el.pause(); }
                else if (media.action === 'stop') { el.pause && el.pause(); try { el.currentTime = 0; } catch (e) {} }
                else if (media.action === 'seek' && typeof media.time === 'number') {
                  try { el.currentTime = Math.max(0, media.time); } catch (e) {}
                }
              }
            }
          }
        })
        .catch(function () {});
    }, 400);

    Reveal.on('ready', function () {
      SlideForgePresentLaserAudience?.init({
        pollUrl: function () { return 'live.php?id=' + encodeURIComponent(window.sfLiveId) + '&channel=' + window.sfLiveChannel; },
      });
    });
  })();
<?php if ($geraetModus): ?>
  // Anzeige-Gerät (Urs 27.11.: «und das tippen setzt dann gleich auch den Ton frei?» — «Das war auch immer ein Problem»).
  // Safari wertet eine Geste nur im Dokument, in dem sie passiert. Darum: EIN Tippen hier gibt Vollbild und Ton frei, und diese Seite
  // bleibt stehen — die nächste Präsentation kommt per postMessage vom Hub-Rahmen und wird hier ausgetauscht, ohne Neuladen.
  (function () {
    var start = document.getElementById('sfGeraetStart');
    var frei = false;
    var ctx = null;
    var STILL = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=';
    function vollbild() {
      try { if (window.top.document.fullscreenElement || window.top.document.webkitFullscreenElement) return; } catch (e) {}
      if (document.fullscreenElement || document.webkitFullscreenElement) return;
      var el = document.documentElement, req = el.requestFullscreen || el.webkitRequestFullscreen;
      if (!req) return;
      try { var p = req.call(el); if (p && p.catch) p.catch(function () {}); } catch (e) {}
    }
    function tonFrei() {
      try {
        var AC = window.AudioContext || window.webkitAudioContext;
        if (AC) {
          ctx = ctx || new AC();
          if (ctx.resume) ctx.resume();
          var b = ctx.createBuffer(1, 1, 22050), s = ctx.createBufferSource();
          s.buffer = b; s.connect(ctx.destination); (s.start || s.noteOn).call(s, 0);
        }
      } catch (e) {}
      try { var a = new Audio(STILL); a.play().catch(function () {}); } catch (e) {}
      document.querySelectorAll('video, audio').forEach(function (el) {
        try {
          var war = el.paused;
          el.muted = false;
          var p = el.play();
          if (p && p.then) p.then(function () { if (war) el.pause(); }).catch(function () {});
        } catch (e) {}
      });
      window.__sfMediaUnlocked = true;
      if (typeof sfFlushPendingMedia === 'function') sfFlushPendingMedia();
    }
    function freigeben() {
      vollbild();
      tonFrei();
      frei = true;
      if (start) start.hidden = true;
    }
    if (start) {
      start.addEventListener('click', freigeben);
      start.addEventListener('keydown', function () { freigeben(); });
    }
    document.addEventListener('keydown', function () { if (!frei) freigeben(); }, true);
    document.addEventListener('pointerdown', function () { if (!frei) freigeben(); else vollbild(); }, true);
    ['fullscreenchange', 'webkitfullscreenchange'].forEach(function (ev) {
      document.addEventListener(ev, function () { /* Ton bleibt frei; Vollbild holt der nächste Tipp zurück */ });
    });

    async function zeige(url) {
      var r = null;
      try {
        var u = url + (url.indexOf('?') < 0 ? '?' : '&') + 'json=1';
        r = await fetch(u, { credentials: 'same-origin', cache: 'no-store' }).then(function (x) { return x.json(); });
      } catch (e) { r = null; }
      if (!r || !r.ok) { location.href = url; return; }
      var d = null;
      try {
        d = await fetch('present_audience.php?id=' + encodeURIComponent(r.id) + '&geraet=1&teil=1', { credentials: 'same-origin', cache: 'no-store' })
          .then(function (x) { return x.json(); });
      } catch (e) { d = null; }
      if (!d || !d.ok) { location.href = url; return; }
      document.querySelectorAll('video, audio').forEach(function (el) { try { el.pause(); } catch (e) {} });
      document.querySelector('.reveal .slides').innerHTML = d.sections;
      window.sfLiveId = d.id;
      // Mitlaufen (3.8.0) beginnt mit `mit=1`; eine Präsentation senden beendet es (folgt wieder dem Presenter).
      window.sfLiveChannel = r.mit ? 'editor' : 'present';
      if (typeof window.sfLiveReset === 'function') window.sfLiveReset();
      document.title = d.title || document.title;
      try { Reveal.configure({ width: d.width, height: d.height }); } catch (e) {}
      Reveal.sync();
      Reveal.slide(0, 0);
      if (frei) {
        document.querySelectorAll('video, audio').forEach(function (el) { try { el.muted = false; } catch (e) {} });
      }
      if (typeof sfMediaStatusTrackSlide === 'function') { try { sfMediaStatusTrackSlide(Reveal.getCurrentSlide()); } catch (e) {} }
    }
    window.addEventListener('message', function (e) {
      if (e.origin !== window.location.origin || !e.data || e.data.type !== 'hub-geraet-zeigen' || typeof e.data.url !== 'string') return;
      zeige(e.data.url);
    });
    // Dem Hub-Rahmen sagen: diese Seite tauscht selbst aus und zeigt den Tipp-Hinweis selbst.
    try { window.parent.postMessage({ type: 'hub-geraet-bereit', inplace: true }, window.location.origin); } catch (e) {}
  })();
<?php endif; ?>
</script>
</body>
</html>
