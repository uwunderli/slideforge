<?php
/**
 * Baut eine eigenständige, offline-fähige HTML-Datei aus einer Präsentation.
 * reveal.js selbst wird beim ersten Export von einem CDN geholt und dann lokal
 * gecacht (data/cache/), damit spätere Exporte keine Internetverbindung mehr
 * brauchen und schneller sind.
 *
 * Für den Re-Import wird zusätzlich der komplette Rohdatensatz (Meta + Folien)
 * als verstecktes <script type="application/json"> in die exportierte Datei
 * eingebettet - so kann import.php eine exportierte Datei 1:1 wieder in eine
 * bearbeitbare Präsentation zurückverwandeln.
 */
class Exporter
{
    private const REVEAL_VERSION = '5.1.0';
    private const MARKER_ID = 'slideforge-source-data';

    private const CDN = [
        'reveal.css' => 'https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/reveal.css',
        'theme.css' => 'https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/theme/black.css',
        'reveal.js' => 'https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/reveal.js',
    ];

    /** Holt eine reveal.js-Ressource, gecacht in data/cache/. Gibt '' zurück, falls nicht erreichbar. */
    private static function revealAsset(string $key): string
    {
        $cacheFile = EXPORT_CACHE_PATH . '/reveal-' . self::REVEAL_VERSION . '-' . $key;
        if (file_exists($cacheFile)) {
            return file_get_contents($cacheFile);
        }
        $content = self::fetchRemote(self::CDN[$key]);
        if ($content !== '') {
            @file_put_contents($cacheFile, $content);
        }
        return $content;
    }

    private static function fetchRemote(string $url): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $data = curl_exec($ch);
            curl_close($ch);
            if (is_string($data) && $data !== '') {
                return $data;
            }
        }
        $ctx = stream_context_create(['http' => ['timeout' => 15]]);
        $data = @file_get_contents($url, false, $ctx);
        return is_string($data) ? $data : '';
    }

    /**
     * Ersetzt asset.php-URLs in Hintergründen/Objekten durch entweder Base64-Data-URIs
     * ($mode='inline', für die Einzeldatei) oder relative Pfade 'assets/<datei>'
     * ($mode='zip', Dateien werden zusätzlich in $usedFiles gesammelt).
     */
    public static function resolveAssets(array $slidesData, string $presentationId, string $mode, array &$usedFiles = []): array
    {
        $assetsDir = Presentation::dir($presentationId) . '/assets';
        $mimeMap = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp',
            'mp4' => 'video/mp4', 'webm' => 'video/webm',
            'wav' => 'audio/wav', 'mp3' => 'audio/mpeg', 'mpeg' => 'audio/mpeg',
            'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac',
        ];

        $mimeForFile = function (string $path, string $filename) use ($mimeMap): string {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (isset($mimeMap[$ext])) {
                return $mimeMap[$ext];
            }
            $head = @file_get_contents($path, false, null, 0, 12);
            if (is_string($head) && strlen($head) >= 12 && strncmp($head, 'RIFF', 4) === 0 && substr($head, 8, 4) === 'WAVE') {
                return 'audio/wav';
            }
            if (is_string($head) && strncmp($head, 'ID3', 3) === 0) {
                return 'audio/mpeg';
            }
            return 'application/octet-stream';
        };

        $resolve = function (?string $value) use ($assetsDir, $mode, &$usedFiles, $mimeForFile) {
            if (!$value || strpos($value, 'asset.php?') !== 0) {
                return $value;
            }
            if (!preg_match('/[?&]file=([a-zA-Z0-9._-]+)/', $value, $m)) {
                return $value;
            }
            $filename = $m[1];
            $path = $assetsDir . '/' . $filename;
            if (!is_file($path)) {
                return $value;
            }
            if ($mode === 'zip') {
                $usedFiles[$filename] = $path;
                return 'assets/' . $filename;
            }
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $mime = $mimeForFile($path, $filename);
            return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
        };

        foreach ($slidesData['slides'] as &$slide) {
            if (!empty($slide['background']['value'])) {
                $slide['background']['value'] = $resolve($slide['background']['value']);
            }
            foreach ($slide['objects'] as &$obj) {
                if (!empty($obj['src'])) {
                    $obj['src'] = $resolve($obj['src']);
                }
            }
            unset($obj);
        }
        unset($slide);

        return $slidesData;
    }

    /** CSS + HTML für den Medien-Ladehinweis (unten rechts). */
    public static function mediaStatusMarkup(
        string $loading = 'Medien werden geladen… ({ready}/{total})',
        string $ready = 'Alle Medien bereit.'
    ): string {
        $loading = h($loading);
        $ready = h($ready);
        return <<<HTML
<style>
.sf-media-status{position:fixed;right:18px;bottom:18px;z-index:40;font:12px/1.45 system-ui,sans-serif;color:rgba(255,255,255,.92);background:rgba(0,0,0,.52);padding:7px 12px;border-radius:5px;pointer-events:none;opacity:0;transition:opacity .35s ease;backdrop-filter:blur(4px);max-width:min(280px,calc(100vw - 36px));box-shadow:0 2px 10px rgba(0,0,0,.25)}
.sf-media-status--loading,.sf-media-status--ready{opacity:1}
.sf-media-status--ready{color:#b8e6b8}
.sf-media-status--loading::before{content:'';display:inline-block;width:10px;height:10px;margin-right:8px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:sfMediaSpin .8s linear infinite;vertical-align:-1px}
@keyframes sfMediaSpin{to{transform:rotate(360deg)}}
.sf-media-unlock{position:fixed;left:50%;bottom:28px;transform:translateX(-50%);z-index:50;display:none;align-items:center;gap:10px;padding:12px 18px;border:none;border-radius:10px;background:#3a6c8d;color:#fff;font:600 15px/1.3 system-ui,sans-serif;cursor:pointer;box-shadow:0 6px 24px rgba(0,0,0,.45)}
.sf-media-unlock.is-visible{display:flex}
.sf-media-unlock:hover{filter:brightness(1.08)}
</style>
<div id="sf-media-status" class="sf-media-status sf-media-status--hidden" aria-live="polite" aria-atomic="true"
  data-msg-loading="{$loading}" data-msg-ready="{$ready}"></div>
<button type="button" id="sf-media-unlock" class="sf-media-unlock" hidden>Klicken für Ton</button>
HTML;
    }

    /** JavaScript für Medien-Hydration (Export) und Wiedergabe-Steuerung. */
    public static function mediaRuntimeJs(bool $includeHydration = false): string
    {
        $hydrate = $includeHydration ? <<<'JS'

    function sfHydrateInlineMedia(root) {
      (root || document).querySelectorAll('[data-sf-media-ref]').forEach(function (el) {
        if (el.dataset.sfHydrated === '1') return;
        var ref = el.getAttribute('data-sf-media-ref');
        var node = ref ? document.getElementById(ref) : null;
        if (!node) return;
        try {
          var mime = node.getAttribute('data-mime') || 'application/octet-stream';
          var b64 = (node.textContent || '').trim();
          if (!b64) return;
          var bin = atob(b64);
          var bytes = new Uint8Array(bin.length);
          for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
          el.src = URL.createObjectURL(new Blob([bytes], { type: mime }));
          el.dataset.sfHydrated = '1';
          el.addEventListener('loadeddata', function () {
            el.dispatchEvent(new Event('sf-hydrated', { bubbles: true }));
          }, { once: true });
          el.addEventListener('error', function () {
            el.dispatchEvent(new Event('sf-hydrated', { bubbles: true }));
          }, { once: true });
          el.load();
        } catch (e) {}
      });
    }
JS : '';

        return $hydrate . <<<'JS'

    var sfMediaStatusEl = null;
    var sfMediaStatusWatch = null;
    var sfMediaStatusHideTimer = null;
    function sfMediaStatusInit() {
      sfMediaStatusEl = document.getElementById('sf-media-status');
    }
    function sfMediaStatusTpl(key, ready, total) {
      if (!sfMediaStatusEl) return '';
      var tmpl = sfMediaStatusEl.getAttribute('data-msg-' + key) || '';
      return tmpl.replace('{ready}', String(ready)).replace('{total}', String(total));
    }
    function sfMediaStatusHide() {
      if (!sfMediaStatusEl) return;
      clearTimeout(sfMediaStatusHideTimer);
      sfMediaStatusEl.className = 'sf-media-status sf-media-status--hidden';
      sfMediaStatusEl.textContent = '';
    }
    function sfMediaStatusShow(state, ready, total) {
      if (!sfMediaStatusEl) return;
      clearTimeout(sfMediaStatusHideTimer);
      sfMediaStatusEl.className = 'sf-media-status sf-media-status--' + state;
      if (state === 'loading') {
        sfMediaStatusEl.textContent = sfMediaStatusTpl('loading', ready, total);
      } else if (state === 'ready') {
        sfMediaStatusEl.textContent = sfMediaStatusTpl('ready', total, total);
        sfMediaStatusHideTimer = setTimeout(sfMediaStatusHide, 4500);
      }
    }
    function sfCollectMedia(slideEl) {
      var list = slideEl ? Array.from(slideEl.querySelectorAll('video, audio')) : [];
      var bg = document.querySelector('.slide-background.present video, .slide-background.present audio');
      if (bg) list.push(bg);
      return list.filter(function (el, i, arr) { return arr.indexOf(el) === i; });
    }
    function sfMediaIsReady(el) {
      return !!el.error || el.readyState >= 2;
    }
    function sfMediaStatusTrackSlide(slideEl) {
      if (sfMediaStatusWatch) sfMediaStatusWatch.cancel();
      var els = sfCollectMedia(slideEl);
      if (!els.length) {
        sfMediaStatusHide();
        return;
      }
      var cancelled = false;
      var allReadyFired = false;
      function onReadyEvent() { update(); }
      function detach() {
        els.forEach(function (el) {
          el.removeEventListener('canplay', onReadyEvent);
          el.removeEventListener('loadeddata', onReadyEvent);
          el.removeEventListener('sf-hydrated', onReadyEvent);
          el.removeEventListener('error', onReadyEvent);
        });
      }
      sfMediaStatusWatch = {
        cancel: function () { cancelled = true; detach(); }
      };
      function update() {
        if (cancelled || allReadyFired) return;
        var ready = els.filter(sfMediaIsReady).length;
        var total = els.length;
        if (ready < total) {
          sfMediaStatusShow('loading', ready, total);
        } else {
          allReadyFired = true;
          detach();
          sfMediaStatusShow('ready', total, total);
          document.dispatchEvent(new Event('sf-media-all-ready'));
          sfMediaStatusWatch = null;
        }
      }
      els.forEach(function (el) {
        if (sfMediaIsReady(el)) return;
        el.addEventListener('canplay', onReadyEvent);
        el.addEventListener('loadeddata', onReadyEvent);
        el.addEventListener('sf-hydrated', onReadyEvent);
        el.addEventListener('error', onReadyEvent, { once: true });
      });
      update();
    }
    var sfPendingMedia = [];
    var sfUnlockBtn = null;
    function sfShowMediaUnlock(on) {
      if (!sfUnlockBtn) sfUnlockBtn = document.getElementById('sf-media-unlock');
      if (!sfUnlockBtn) return;
      sfUnlockBtn.hidden = !on;
      sfUnlockBtn.classList.toggle('is-visible', !!on);
    }
    function sfFlushPendingMedia() {
      if (!sfPendingMedia.length) {
        sfShowMediaUnlock(false);
        return;
      }
      var list = sfPendingMedia.slice();
      sfPendingMedia = [];
      sfShowMediaUnlock(false);
      list.forEach(function (el) { sfPlayMedia(el, true); });
    }
    (function sfMediaUnlockSetup() {
      if (window.__sfMediaUnlockSetup) return;
      window.__sfMediaUnlockSetup = true;
      function onGesture() {
        window.__sfMediaUnlocked = true;
        sfFlushPendingMedia();
      }
      ['click', 'keydown', 'touchstart'].forEach(function (ev) {
        document.addEventListener(ev, onGesture, { capture: true });
      });
      document.addEventListener('DOMContentLoaded', function () {
        sfUnlockBtn = document.getElementById('sf-media-unlock');
        if (sfUnlockBtn) {
          sfUnlockBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            window.__sfMediaUnlocked = true;
            sfFlushPendingMedia();
          });
        }
      });
      sfUnlockBtn = document.getElementById('sf-media-unlock');
      if (sfUnlockBtn) {
        sfUnlockBtn.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          window.__sfMediaUnlocked = true;
          sfFlushPendingMedia();
        });
      }
    })();
    function sfWhenMediaReady(el, cb) {
      function run() {
        if (el.readyState >= 2) cb();
        else el.addEventListener('canplay', cb, { once: true });
      }
      if (el.hasAttribute('data-sf-media-ref') && el.dataset.sfHydrated !== '1') {
        el.addEventListener('sf-hydrated', run, { once: true });
        return;
      }
      run();
    }
    function sfPlayMedia(el, fromPending) {
      if (!el || !el.play) return;
      try { el.muted = false; } catch (e) {}
      sfWhenMediaReady(el, function () {
        var p = el.play();
        if (p && p.catch) {
          p.catch(function () {
            if (sfPendingMedia.indexOf(el) === -1) sfPendingMedia.push(el);
            sfShowMediaUnlock(true);
          });
        }
      });
    }
    function sfApplyLiveMediaCommand(media) {
      if (!media || !media.id) return;
      var el = document.querySelector('[data-media-id="' + media.id + '"]');
      if (!el) return;
      if (media.action === 'play') {
        if (!el.paused && !el.ended) return;
        el.dataset.sfTimedArmed = '1';
        if (el.ended) {
          try { el.currentTime = 0; } catch (err) {}
        }
        sfPlayMedia(el);
      } else if (media.action === 'pause') { el.pause && el.pause(); }
      else if (media.action === 'stop') {
        el.pause && el.pause();
        try { el.currentTime = 0; } catch (err) {}
        el.dataset.sfTimedArmed = '1';
        var idx = sfPendingMedia.indexOf(el);
        if (idx >= 0) sfPendingMedia.splice(idx, 1);
        if (!sfPendingMedia.length) sfShowMediaUnlock(false);
      }
    }
    function sfResetMedia(slideEl) {
      if (!slideEl) return;
      slideEl.querySelectorAll('video, audio').forEach(function (el) {
        el.pause();
        try { el.currentTime = 0; } catch (e) {}
        delete el.dataset.sfTimedArmed;
        delete el.dataset.sfTimedAudienceSent;
        var idx = sfPendingMedia.indexOf(el);
        if (idx >= 0) sfPendingMedia.splice(idx, 1);
      });
    }
    function sfArmMediaTriggers(slideEl, playTimers) {
      playTimers.forEach(function (t) { clearTimeout(t); });
      playTimers.length = 0;
      if (!slideEl) return;
      // Nur timed (data-play-delay); pro Folienbesuch höchstens einmal — sonst wirkt
      // fehlendes HTML-loop wie eine Dauerschleife (Re-Arm nach ended/canplay/Live).
      slideEl.querySelectorAll('audio[data-play-delay], video[data-play-delay]').forEach(function (el) {
        if (el.dataset.sfTimedArmed === '1') return;
        el.dataset.sfTimedArmed = '1';
        var delay = parseInt(el.getAttribute('data-play-delay'), 10) || 0;
        sfWhenMediaReady(el, function () {
          if (el.dataset.sfTimedArmed !== '1') return;
          if (!el.loop && el.ended) return;
          if (delay <= 0) sfPlayMedia(el);
          else playTimers.push(setTimeout(function () {
            if (el.dataset.sfTimedArmed !== '1') return;
            if (!el.loop && el.ended) return;
            sfPlayMedia(el);
          }, delay));
        });
      });
    }
    (function sfMediaClickPlaySetup() {
      if (window.__sfMediaClickPlaySetup) return;
      window.__sfMediaClickPlaySetup = true;
      document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        // Native Controls behalten ihr eigenes Verhalten.
        if (t.closest('audio, video')) {
          var mediaSelf = t.closest('audio, video');
          if (mediaSelf && (mediaSelf.getAttribute('data-play-trigger') === 'click')) {
            sfPlayMedia(mediaSelf);
          }
          return;
        }
        var obj = t.closest('.sf-object');
        if (!obj) return;
        var media = obj.querySelector('audio[data-play-trigger], video[data-play-trigger]');
        if (!media) return;
        var trigger = media.getAttribute('data-play-trigger') || 'manual';
        // «Bei Klick»: Klick auf Objektfläche startet. «Manuell»: nur Controls / Medienpanel.
        if (trigger === 'click') {
          e.preventDefault();
          sfPlayMedia(media);
        }
      }, true);
    })();
JS;
    }

    /** Einheitlicher Folien-Medien-Handler für Export, Vorschau und öffentlichen Link. */
    public static function mediaSlideBootJs(bool $includeHydration = false): string
    {
        $hydrateLine = $includeHydration
            ? "      sfHydrateInlineMedia(slideEl || document);\n"
            : '';
        return <<<JS
  (function () {
    const playTimers = [];
    sfMediaStatusInit();
    function onSlideReady(slideEl) {
      sfMediaStatusTrackSlide(slideEl);
{$hydrateLine}      sfArmMediaTriggers(slideEl, playTimers);
    }
    document.addEventListener('sf-media-all-ready', function () {
      var slide = Reveal.getCurrentSlide && Reveal.getCurrentSlide();
      if (slide) sfArmMediaTriggers(slide, playTimers);
    });
    Reveal.on('ready', function (e) { onSlideReady(e.currentSlide); });
    Reveal.on('slidechanged', function (e) {
      sfResetMedia(e.previousSlide);
      onSlideReady(e.currentSlide);
    });
  })();
JS;
    }

    /** Baut die komplette, eigenständige HTML-Seite (für Einzeldatei- und ZIP-Export gleichermassen). */
    public static function buildStandaloneHtml(array $meta, array $resolvedSlidesData): string
    {
        SlideRenderer::resetInlineMedia();
        $sections = SlideRenderer::renderSections($resolvedSlidesData, null);
        $inlineMedia = SlideRenderer::getInlineMedia();
        $mediaScripts = '';
        foreach ($inlineMedia as $ref => $data) {
            $mediaScripts .= '<script type="text/plain" id="' . h($ref) . '" data-mime="' . h($data['mime']) . '">' . $data['b64'] . '</script>' . "\n";
        }

        $revealCss = self::revealAsset('reveal.css');
        $themeCss = self::revealAsset('theme.css');
        $revealJs = self::revealAsset('reveal.js');

        // Rohdaten für den Re-Import einbetten (Meta + bereits aufgelöste Folien).
        $sourceData = json_encode(['meta' => self::metaForReexport($meta), 'slides' => $resolvedSlidesData['slides']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sourceData = str_replace('</script', '<\/script', $sourceData);

        $safeJs = str_replace('</script', '<\/script', $revealJs);

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($meta['title']) ?></title>
<?= FontLibrary::headMarkup('', $resolvedSlidesData, true) ?>
<?php if ($revealCss !== ''): ?>
<style><?= $revealCss ?></style>
<?php else: ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/reveal.css">
<?php endif; ?>
<?php if ($themeCss !== ''): ?>
<style><?= $themeCss ?></style>
<?php else: ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/theme/black.css">
<?php endif; ?>
<style>html,body{margin:0;height:100%;background:#000;} .sf-object{color:#fff;}</style>
<?= self::mediaStatusMarkup() ?>
</head>
<body>
<div class="reveal">
  <div class="slides">
    <?= $sections ?>
  </div>
</div>
<?= $mediaScripts ?>
<script type="application/json" id="<?= self::MARKER_ID ?>"><?= $sourceData ?></script>
<?php if ($revealJs !== ''): ?>
<script><?= $safeJs ?></script>
<?php else: ?>
<script src="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/reveal.js"></script>
<?php endif; ?>
<script>
  Reveal.initialize({
    width: <?= (int)$meta['width'] ?>,
    height: <?= (int)$meta['height'] ?>,
    hash: false,
    controls: <?= ($meta['show_controls'] ?? true) ? 'true' : 'false' ?>,
    progress: <?= ($meta['show_progress'] ?? true) ? 'true' : 'false' ?>,
    center: false, // unsere Objekte sind absolut positioniert (x/y) - reveal.js soll nichts selbst verschieben
    margin: 0
  });

  // Medien: Blob-Hydration (Export), Ladehinweis, automatische Wiedergabe.
<?= self::mediaRuntimeJs(true) ?>
<?= self::mediaSlideBootJs(true) ?>

  // reveal.js zerlegt data-background-video intern am Komma (für mehrere Formate).
  // Eine eingebettete data:video/...;base64,... -URL enthält selbst zwangsläufig ein
  // Komma und wird dadurch fälschlich in zwei kaputte <source>-Elemente zerlegt.
  // Fix: bei Bedarf die Teile wieder zur vollständigen URL zusammensetzen.
  function sfFixBase64BackgroundVideo() {
    document.querySelectorAll('.slide-background video').forEach(function (video) {
      var sources = video.querySelectorAll('source');
      sources.forEach(function (source, i) {
        var src = source.getAttribute('src') || '';
        if (/^data:video\/[a-z0-9.+-]+;base64$/i.test(src)) {
          var next = sources[i + 1] ? (sources[i + 1].getAttribute('src') || '') : '';
          video.setAttribute('src', src + ',' + next);
          video.load();
        }
      });
    });
  }
  Reveal.on('ready', sfFixBase64BackgroundVideo);
  Reveal.on('slidechanged', sfFixBase64BackgroundVideo);
</script>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    public static function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_\-\. äöüÄÖÜß]/u', '', $name);
        $name = trim($name) !== '' ? trim($name) : 'praesentation';
        return $name;
    }

    /**
     * Kehrfunktion zu resolveAssets(): baut aus Base64-Data-URIs (Einzeldatei-Import)
     * oder relativen 'assets/<datei>'-Pfaden (ZIP-Import, $zipAssetsDir gesetzt)
     * wieder echte Dateien in der NEUEN Präsentation und ersetzt die URLs durch
     * frische asset.php-Links.
     */
    public static function importAssets(array $slidesData, string $newPresentationId, ?string $zipAssetsDir = null): array
    {
        $assetsDir = Presentation::dir($newPresentationId) . '/assets';
        if (!is_dir($assetsDir)) {
            mkdir($assetsDir, 0770, true);
        }
        $extFromMime = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
            'video/mp4' => 'mp4', 'video/webm' => 'webm',
            'audio/wav' => 'wav', 'audio/mpeg' => 'mp3', 'audio/ogg' => 'ogg', 'audio/mp4' => 'm4a', 'audio/aac' => 'aac',
        ];

        $rewrite = function (?string $value) use ($assetsDir, $zipAssetsDir, $extFromMime, $newPresentationId) {
            if (!$value) {
                return $value;
            }
            if (preg_match('/^data:([^;]+);base64,(.+)$/s', $value, $m)) {
                $mime = $m[1];
                $ext = $extFromMime[$mime] ?? 'bin';
                $bytes = base64_decode($m[2]);
                if ($bytes === false) {
                    return $value;
                }
                $filename = Storage::generateId(8) . '.' . $ext;
                file_put_contents($assetsDir . '/' . $filename, $bytes);
                return 'asset.php?id=' . urlencode($newPresentationId) . '&file=' . urlencode($filename);
            }
            if ($zipAssetsDir && strpos($value, 'assets/') === 0) {
                $origName = substr($value, strlen('assets/'));
                $srcPath = $zipAssetsDir . '/' . basename($origName);
                if (is_file($srcPath)) {
                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    $filename = Storage::generateId(8) . ($ext !== '' ? '.' . $ext : '');
                    copy($srcPath, $assetsDir . '/' . $filename);
                    return 'asset.php?id=' . urlencode($newPresentationId) . '&file=' . urlencode($filename);
                }
            }
            return $value;
        };

        foreach ($slidesData['slides'] as &$slide) {
            if (!empty($slide['background']['value'])) {
                $slide['background']['value'] = $rewrite($slide['background']['value']);
            }
            foreach ($slide['objects'] as &$obj) {
                if (!empty($obj['src'])) {
                    $obj['src'] = $rewrite($obj['src']);
                }
            }
            unset($obj);
        }
        unset($slide);

        return $slidesData;
    }

    /** Meta-Felder, die im SlideForge-Backup für den Re-Import mitgespeichert werden. */
    public static function metaForReexport(array $meta): array
    {
        $keys = [
            'title', 'width', 'height',
            'show_progress', 'show_controls',
            'safe_margin', 'presentation_duration',
            'layout_set_id', 'footer_text',
            'timebar_stops', 'clock_order',
        ];
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $meta)) {
                $out[$key] = $meta[$key];
            }
        }
        return $out;
    }

    /**
     * Stellt Präsentations-Meta aus einem SlideForge-Backup wieder her.
     *
     * @return array{fields: array<string, mixed>, warnings: list<string>}
     */
    public static function metaFromReimport(array $exportedMeta, string $userId): array
    {
        $fields = [];
        $warnings = [];

        if (array_key_exists('show_progress', $exportedMeta)) {
            $fields['show_progress'] = (bool)$exportedMeta['show_progress'];
        }
        if (array_key_exists('show_controls', $exportedMeta)) {
            $fields['show_controls'] = (bool)$exportedMeta['show_controls'];
        }
        if (array_key_exists('safe_margin', $exportedMeta)) {
            $fields['safe_margin'] = max(0, (int)$exportedMeta['safe_margin']);
        }
        if (array_key_exists('presentation_duration', $exportedMeta)) {
            $fields['presentation_duration'] = max(1, (int)$exportedMeta['presentation_duration']);
        }
        if (array_key_exists('footer_text', $exportedMeta)) {
            $fields['footer_text'] = (string)$exportedMeta['footer_text'];
        }
        if (isset($exportedMeta['timebar_stops']) && is_array($exportedMeta['timebar_stops'])) {
            $fields['timebar_stops'] = $exportedMeta['timebar_stops'];
        }
        if (isset($exportedMeta['clock_order']) && is_array($exportedMeta['clock_order'])) {
            $fields['clock_order'] = $exportedMeta['clock_order'];
        }

        $setId = trim((string)($exportedMeta['layout_set_id'] ?? ''));
        if ($setId !== '') {
            if (LayoutSet::isLayoutSet($setId) && Presentation::canUseTemplate($setId, $userId)) {
                $fields['layout_set_id'] = $setId;
            } else {
                $warnings[] = t('import.layout_set_missing');
            }
        } elseif (array_key_exists('layout_set_id', $exportedMeta)) {
            $fields['layout_set_id'] = '';
        }

        return ['fields' => $fields, 'warnings' => $warnings];
    }
}
