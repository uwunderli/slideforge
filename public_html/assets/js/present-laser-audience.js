/**
 * Laserpointer in der Live-/Zuschauer-Ansicht (view.php, present_audience.php).
 */
(function (global) {
  'use strict';

  const TRAIL_DURATION = 480;
  const TRAIL_MIN_DIST = 0.004;
  const TRAIL_MAX = 14;

  let dot = null;
  let lastLaser = null;
  let trailEls = [];
  let trailHistory = [];
  let trailRaf = null;

  function getViewportRect() {
    const vp = global.document.querySelector('.reveal-viewport') || global.document.querySelector('.reveal');
    return vp ? vp.getBoundingClientRect() : null;
  }

  function ensureDot() {
    if (dot) return;
    dot = global.document.createElement('div');
    dot.className = 'sf-laser-dot';
    dot.setAttribute('aria-hidden', 'true');
    dot.hidden = true;
    global.document.body.appendChild(dot);
  }

  function hideDot() {
    if (dot) dot.hidden = true;
    lastLaser = null;
    clearTrailHistory();
  }

  function clearTrailHistory() {
    trailHistory = [];
    trailEls.forEach(function (el) { el.remove(); });
    trailEls = [];
    if (trailRaf) {
      cancelAnimationFrame(trailRaf);
      trailRaf = null;
    }
  }

  function currentSlideIndex() {
    if (!global.Reveal || typeof global.Reveal.getIndices !== 'function') return 0;
    return (global.Reveal.getIndices() || { h: 0 }).h || 0;
  }

  function laserColor(laser) {
    return (typeof laser.color === 'string' && /^#[0-9a-fA-F]{6}$/.test(laser.color))
      ? laser.color.toLowerCase()
      : '#ff0000';
  }

  function laserSize(laser) {
    return Math.max(8, Math.min(64, parseInt(laser.size, 10) || 24));
  }

  function toScreen(laser) {
    const rect = getViewportRect();
    if (!rect || rect.width < 1 || rect.height < 1) return null;
    return {
      left: rect.left + laser.x * rect.width,
      top: rect.top + laser.y * rect.height,
      size: laserSize(laser),
      color: laserColor(laser),
    };
  }

  function pushTrailPoint(laser) {
    if (!laser.trail) {
      clearTrailHistory();
      return;
    }
    const now = performance.now();
    const last = trailHistory[trailHistory.length - 1];
    if (last) {
      const dx = laser.x - last.x;
      const dy = laser.y - last.y;
      if (dx * dx + dy * dy < TRAIL_MIN_DIST * TRAIL_MIN_DIST) return;
    }
    trailHistory.push({
      x: laser.x,
      y: laser.y,
      color: laserColor(laser),
      size: laserSize(laser),
      slideIndex: laser.slideIndex,
      born: now,
    });
    while (trailHistory.length > TRAIL_MAX) {
      trailHistory.shift();
    }
    ensureTrailLoop();
  }

  function ensureTrailLoop() {
    if (!trailRaf) trailRaf = requestAnimationFrame(tickTrail);
  }

  function tickTrail() {
    const now = performance.now();
    const slideIdx = currentSlideIndex();
    const rect = getViewportRect();

    trailHistory = trailHistory.filter(function (p) {
      return now - p.born < TRAIL_DURATION && p.slideIndex === slideIdx;
    });

    while (trailEls.length > trailHistory.length) {
      trailEls.pop().remove();
    }
    while (trailEls.length < trailHistory.length) {
      const el = global.document.createElement('div');
      el.className = 'sf-laser-trail-dot';
      el.setAttribute('aria-hidden', 'true');
      global.document.body.appendChild(el);
      trailEls.push(el);
    }

    if (!rect || rect.width < 1) {
      trailRaf = trailHistory.length ? requestAnimationFrame(tickTrail) : null;
      return;
    }

    trailHistory.forEach(function (p, i) {
      const el = trailEls[i];
      if (!el) return;
      const age = now - p.born;
      const t = age / TRAIL_DURATION;
      const s = p.size;
      el.style.width = s + 'px';
      el.style.height = s + 'px';
      el.style.background = p.color;
      el.style.boxShadow = '0 0 ' + Math.round(s * 0.55) + 'px ' + p.color;
      el.style.left = (rect.left + p.x * rect.width) + 'px';
      el.style.top = (rect.top + p.y * rect.height) + 'px';
      el.style.opacity = String(0.78 * (1 - t));
      el.style.transform = 'translate(-50%,-50%) scale(' + (1 - t * 0.45) + ')';
      el.hidden = false;
    });

    if (trailHistory.length) {
      trailRaf = requestAnimationFrame(tickTrail);
    } else {
      trailRaf = null;
    }
  }

  function renderLaser(laser) {
    ensureDot();
    if (!laser || !laser.active) {
      hideDot();
      return;
    }
    if (laser.slideIndex != null && laser.slideIndex !== currentSlideIndex()) {
      dot.hidden = true;
      clearTrailHistory();
      lastLaser = null;
      return;
    }
    if (lastLaser && lastLaser.active && laser.trail) {
      pushTrailPoint(lastLaser);
    } else if (!laser.trail) {
      clearTrailHistory();
    }
    const screen = toScreen(laser);
    if (!screen) return;
    dot.style.width = screen.size + 'px';
    dot.style.height = screen.size + 'px';
    dot.style.background = screen.color;
    dot.style.boxShadow = '0 0 ' + Math.round(screen.size * 0.7) + 'px ' + screen.color;
    dot.style.left = screen.left + 'px';
    dot.style.top = screen.top + 'px';
    dot.hidden = false;
    lastLaser = laser;
  }

  function repaintLast() {
    if (lastLaser && lastLaser.active) renderLaser(lastLaser);
    if (trailHistory.length) tickTrail();
  }

  function init(opts) {
    const pollUrl = opts && opts.pollUrl;
    if (!pollUrl) return;

    if (global.Reveal && typeof global.Reveal.on === 'function') {
      global.Reveal.on('slidechanged', hideDot);
      global.Reveal.on('ready', repaintLast);
    }
    global.addEventListener('resize', repaintLast);

    global.setInterval(function () {
      global.fetch(pollUrl)
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.ok && data.live && data.live.laser) {
            renderLaser(data.live.laser);
          } else {
            hideDot();
          }
        })
        .catch(function () {});
    }, 80);
  }

  global.SlideForgePresentLaserAudience = { init };
})(window);
