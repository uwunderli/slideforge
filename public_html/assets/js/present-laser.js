/**
 * Laserpointer im Präsentations-Hauptfenster (Maus / Touch / Stift).
 * Sichtbar solange der Pointer gedrückt gehalten wird.
 */
(function (global) {
  'use strict';

  const TRAIL_DURATION = 480;
  const TRAIL_MIN_DIST = 6;

  let config = { color: '#ff0000', size: 24, trail: false };
  let activePointerId = null;
  let dot = null;
  let captureTarget = null;
  let suppressClickUntil = 0;
  let initialized = false;
  let lastLiveNotify = 0;
  let lastClientX = 0;
  let lastClientY = 0;
  let heartbeatTimer = null;
  let trailDots = [];
  let trailRaf = null;
  let lastTrailX = null;
  let lastTrailY = null;

  function stopHeartbeat() {
    if (heartbeatTimer) {
      clearInterval(heartbeatTimer);
      heartbeatTimer = null;
    }
  }

  function startHeartbeat() {
    stopHeartbeat();
    heartbeatTimer = setInterval(function () {
      if (activePointerId === null) return;
      notifyLive(true, lastClientX, lastClientY, true);
    }, 400);
  }

  function getSlideIndex() {
    if (!global.Reveal || typeof global.Reveal.getIndices !== 'function') return 0;
    return (global.Reveal.getIndices() || { h: 0 }).h || 0;
  }

  function getNormalizedPoint(clientX, clientY) {
    const vp = document.querySelector('.reveal-viewport') || document.querySelector('.reveal');
    if (!vp) return null;
    const rect = vp.getBoundingClientRect();
    if (rect.width < 1 || rect.height < 1) return null;
    return {
      x: (clientX - rect.left) / rect.width,
      y: (clientY - rect.top) / rect.height,
    };
  }

  function notifyLive(active, clientX, clientY, force) {
    if (global.parent === global) return;
    const now = performance.now();
    if (active && !force && now - lastLiveNotify < 32) return;
    lastLiveNotify = now;
    if (active) {
      lastClientX = clientX;
      lastClientY = clientY;
    }
    const point = active ? getNormalizedPoint(clientX, clientY) : null;
    global.parent.postMessage({
      type: 'sf-laser-live',
      active: !!active,
      x: point ? point.x : null,
      y: point ? point.y : null,
      slideIndex: getSlideIndex(),
      color: config.color,
      size: config.size,
      trail: !!config.trail,
    }, '*');
  }

  function normalizeSize(size) {
    const n = parseInt(size, 10);
    if (!Number.isFinite(n)) return 24;
    return Math.max(8, Math.min(64, n));
  }

  function normalizeColor(color) {
    if (typeof color === 'string' && /^#[0-9a-fA-F]{6}$/.test(color)) {
      return color.toLowerCase();
    }
    return '#ff0000';
  }

  function applyConfig(c) {
    if (!c || typeof c !== 'object') return;
    if (c.color != null) config.color = normalizeColor(c.color);
    if (c.size != null) config.size = normalizeSize(c.size);
    if (c.trail != null) config.trail = !!c.trail;
    styleDot();
    if (!config.trail) clearTrail();
  }

  function styleDot() {
    if (!dot) return;
    const s = config.size;
    dot.style.width = s + 'px';
    dot.style.height = s + 'px';
    dot.style.background = config.color;
    dot.style.boxShadow = '0 0 ' + Math.round(s * 0.7) + 'px ' + config.color;
  }

  function styleTrailEl(el, size) {
    const s = size || config.size;
    el.style.width = s + 'px';
    el.style.height = s + 'px';
    el.style.background = config.color;
    el.style.boxShadow = '0 0 ' + Math.round(s * 0.55) + 'px ' + config.color;
  }

  function clearTrail() {
    trailDots.forEach(function (d) { d.el.remove(); });
    trailDots = [];
    lastTrailX = null;
    lastTrailY = null;
    if (trailRaf) {
      cancelAnimationFrame(trailRaf);
      trailRaf = null;
    }
  }

  function tickTrail() {
    const now = performance.now();
    for (let i = trailDots.length - 1; i >= 0; i--) {
      const item = trailDots[i];
      const age = now - item.born;
      if (age >= TRAIL_DURATION) {
        item.el.remove();
        trailDots.splice(i, 1);
        continue;
      }
      const t = age / TRAIL_DURATION;
      item.el.style.opacity = String(0.78 * (1 - t));
      item.el.style.transform = 'translate(-50%,-50%) scale(' + (1 - t * 0.45) + ')';
    }
    if (trailDots.length) {
      trailRaf = requestAnimationFrame(tickTrail);
    } else {
      trailRaf = null;
    }
  }

  function ensureTrailLoop() {
    if (!trailRaf) trailRaf = requestAnimationFrame(tickTrail);
  }

  function spawnTrail(x, y) {
    if (!config.trail || activePointerId === null) return;
    if (lastTrailX !== null) {
      const dx = x - lastTrailX;
      const dy = y - lastTrailY;
      if (dx * dx + dy * dy < TRAIL_MIN_DIST * TRAIL_MIN_DIST) return;
    }
    lastTrailX = x;
    lastTrailY = y;
    const el = document.createElement('div');
    el.className = 'sf-laser-trail-dot';
    el.setAttribute('aria-hidden', 'true');
    styleTrailEl(el);
    el.style.left = x + 'px';
    el.style.top = y + 'px';
    el.style.opacity = '0.78';
    document.body.appendChild(el);
    trailDots.push({ el: el, born: performance.now() });
    ensureTrailLoop();
  }

  function moveDot(e) {
    if (!dot) return;
    dot.style.left = e.clientX + 'px';
    dot.style.top = e.clientY + 'px';
  }

  function showDot(e) {
    if (!dot) return;
    dot.hidden = false;
    moveDot(e);
  }

  function hideDot() {
    if (!dot) return;
    dot.hidden = true;
  }

  function onPointerDown(e) {
    if (activePointerId !== null) return;
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    activePointerId = e.pointerId;
    lastTrailX = null;
    lastTrailY = null;
    try { captureTarget.setPointerCapture(e.pointerId); } catch (err) { /* ignore */ }
    showDot(e);
    lastClientX = e.clientX;
    lastClientY = e.clientY;
    notifyLive(true, e.clientX, e.clientY);
    startHeartbeat();
    e.preventDefault();
  }

  function onPointerMove(e) {
    if (e.pointerId !== activePointerId) return;
    spawnTrail(lastClientX, lastClientY);
    moveDot(e);
    notifyLive(true, e.clientX, e.clientY);
    e.preventDefault();
  }

  function onPointerUp(e) {
    if (e.pointerId !== activePointerId) return;
    hideDot();
    stopHeartbeat();
    notifyLive(false, 0, 0);
    suppressClickUntil = Date.now() + 380;
    activePointerId = null;
    try { captureTarget.releasePointerCapture(e.pointerId); } catch (err) { /* ignore */ }
  }

  function onLostCapture(e) {
    if (e.pointerId !== activePointerId) return;
    hideDot();
    stopHeartbeat();
    notifyLive(false, 0, 0);
    activePointerId = null;
  }

  function onClickCapture(e) {
    if (Date.now() < suppressClickUntil) {
      e.preventDefault();
      e.stopImmediatePropagation();
    }
  }

  function showRemoteLaser(data) {
    if (!dot) return;
    if (!data || !data.active || data.x == null || data.y == null) {
      hideDot();
      return;
    }
    if (data.color != null) config.color = normalizeColor(data.color);
    if (data.size != null) config.size = normalizeSize(data.size);
    styleDot();
    const vp = document.querySelector('.reveal-viewport') || document.querySelector('.reveal');
    if (!vp) return;
    const rect = vp.getBoundingClientRect();
    if (rect.width < 1 || rect.height < 1) return;
    dot.hidden = false;
    dot.style.left = (rect.left + data.x * rect.width) + 'px';
    dot.style.top = (rect.top + data.y * rect.height) + 'px';
  }

  function init() {
    if (initialized) return;
    initialized = true;

    dot = document.createElement('div');
    dot.className = 'sf-laser-dot';
    dot.setAttribute('aria-hidden', 'true');
    dot.hidden = true;
    document.body.appendChild(dot);

    captureTarget = document.querySelector('.reveal-viewport')
      || document.querySelector('.reveal')
      || document.body;

    captureTarget.addEventListener('pointerdown', onPointerDown, { passive: false });
    captureTarget.addEventListener('pointermove', onPointerMove, { passive: false });
    captureTarget.addEventListener('pointerup', onPointerUp);
    captureTarget.addEventListener('pointercancel', onPointerUp);
    captureTarget.addEventListener('lostpointercapture', onLostCapture);
    document.addEventListener('click', onClickCapture, true);

    global.addEventListener('message', (e) => {
      if (!e.data) return;
      if (e.data.type === 'sf-laser-config') applyConfig(e.data);
      if (e.data.type === 'sf-laser-remote') showRemoteLaser(e.data);
    });

    applyConfig(global.SF_LASER || null);
  }

  global.SlideForgePresentLaser = { init, applyConfig };
})(window);
