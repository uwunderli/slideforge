/**
 * Benutzer-Einstellungen im Präsentationsmodus (Uhr-Reihenfolge, Zeitleisten-Farben).
 */
(function () {
  'use strict';

  const P = window.SF_PRESENT;
  if (!P) return;

  const brandColors = P.brandColors || [];
  const i18n = P.i18n || {};
  const CLOCK_META = {
    studio: { label: i18n.clockStudio || 'Studiouhr', icon: '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="24" cy="6.5" r="1.15" fill="currentColor"/><circle cx="22.6" cy="7.8" r="0.95" fill="currentColor"/><circle cx="25.4" cy="7.8" r="0.95" fill="currentColor"/><circle cx="41.5" cy="24" r="1.15" fill="currentColor"/><circle cx="40.1" cy="25.4" r="0.95" fill="currentColor"/><circle cx="40.1" cy="22.6" r="0.95" fill="currentColor"/><circle cx="24" cy="41.5" r="1.15" fill="currentColor"/><circle cx="22.6" cy="40.1" r="0.95" fill="currentColor"/><circle cx="25.4" cy="40.1" r="0.95" fill="currentColor"/><circle cx="6.5" cy="24" r="1.15" fill="currentColor"/><circle cx="7.9" cy="25.4" r="0.95" fill="currentColor"/><circle cx="7.9" cy="22.6" r="0.95" fill="currentColor"/><text x="24" y="27.5" text-anchor="middle" fill="currentColor" font-size="7.5" font-family="monospace" font-weight="700">15:16</text></svg>' },
    analog: { label: i18n.clockAnalog || 'Analog', icon: '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="20" stroke="currentColor" stroke-width="2"/><line x1="24" y1="24" x2="24" y2="12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><line x1="24" y1="24" x2="32" y2="24" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' },
    digital: { label: i18n.clockDigital || 'Digital', icon: '<svg viewBox="0 0 48 32" fill="none"><text x="24" y="22" text-anchor="middle" fill="currentColor" font-size="14" font-family="sans-serif" font-weight="700">12:00</text></svg>' },
  };

  function savePrefs(partial) {
    window.SlideForgePresentLayout?.patchUserPrefs?.(partial);
    if (partial.clockOrder) {
      P.clockOrder = partial.clockOrder;
      window.SlideForgePresentUi?.setClockOrder?.(partial.clockOrder);
    }
    if (partial.timebarStops) {
      P.timebarStops = partial.timebarStops;
      window.SlideForgePresentUi?.setTimebarStops?.(partial.timebarStops);
    }
    if (partial.laserPointerColor) {
      P.laserPointer = P.laserPointer || {};
      P.laserPointer.color = partial.laserPointerColor;
    }
    if (partial.laserPointerSize != null) {
      P.laserPointer = P.laserPointer || {};
      P.laserPointer.size = partial.laserPointerSize;
    }
    if (partial.laserPointerTrail != null) {
      P.laserPointer = P.laserPointer || {};
      P.laserPointer.trail = partial.laserPointerTrail;
    }
    if (partial.laserPointerEnabled != null) {
      P.laserPointer = P.laserPointer || {};
      P.laserPointer.enabled = partial.laserPointerEnabled;
    }
  }

  // ---------- Uhr-Reihenfolge ----------
  (function initClockOrder() {
    const listEl = document.getElementById('presentClockOrderList');
    if (!listEl) return;

    let order = (P.clockOrder && P.clockOrder.length) ? [...P.clockOrder] : ['analog', 'digital', 'studio'];

    function renderOrder() {
      listEl.innerHTML = order.map((id, i) => {
        const m = CLOCK_META[id] || { label: id, icon: '' };
        return '<div class="clock-order-item" draggable="true" data-id="' + id + '" data-index="' + i + '">' +
          '<span class="clock-order-handle" aria-hidden="true">⋮⋮</span>' +
          '<span class="clock-order-icon">' + m.icon + '</span>' +
          '<span class="clock-order-label">' + m.label + '</span>' +
        '</div>';
      }).join('');
      bindDrag();
    }

    function bindDrag() {
      let dragItem = null;
      listEl.querySelectorAll('.clock-order-item').forEach((item) => {
        item.addEventListener('dragstart', (e) => {
          dragItem = item;
          item.classList.add('dragging');
          e.dataTransfer.effectAllowed = 'move';
        });
        item.addEventListener('dragend', () => {
          item.classList.remove('dragging');
          dragItem = null;
          listEl.querySelectorAll('.clock-order-item').forEach((el) => el.classList.remove('drag-over'));
        });
        item.addEventListener('dragover', (e) => {
          e.preventDefault();
          if (!dragItem || dragItem === item) return;
          item.classList.add('drag-over');
        });
        item.addEventListener('dragleave', () => item.classList.remove('drag-over'));
        item.addEventListener('drop', (e) => {
          e.preventDefault();
          item.classList.remove('drag-over');
          if (!dragItem || dragItem === item) return;
          const from = parseInt(dragItem.dataset.index, 10);
          const to = parseInt(item.dataset.index, 10);
          const moved = order.splice(from, 1)[0];
          order.splice(to, 0, moved);
          renderOrder();
          savePrefs({ clockOrder: order });
        });
      });
    }

    renderOrder();
  })();

  // ---------- Zeitleisten-Farbstufen ----------
  (function initTimebarStops() {
    const listEl = document.getElementById('presentTimebarStopsList');
    if (!listEl) return;

    let stops = (P.timebarStops && P.timebarStops.length)
      ? P.timebarStops.map((s) => ({ ...s }))
      : [
        { pct: 0, color: '#4caf6b' }, { pct: 60, color: '#d9c23a' },
        { pct: 90, color: '#dd8a2e' }, { pct: 100, color: '#d9483a' },
      ];

    function escapeHtml(s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function normalizeHex(hex) {
      const h = String(hex || '').trim().toLowerCase();
      if (/^#[0-9a-f]{6}$/.test(h)) return h;
      if (/^#[0-9a-f]{3}$/.test(h)) {
        return '#' + h[1] + h[1] + h[2] + h[2] + h[3] + h[3];
      }
      return h;
    }

    function brandSelectHtml(rowIndex, currentColor) {
      if (!brandColors.length) return '';
      const current = normalizeHex(currentColor);
      const matched = brandColors.find((c) => normalizeHex(c.hex) === current);
      const placeholder = escapeHtml(i18n.brandColors || 'Markenfarben');
      const triggerLabel = matched ? escapeHtml(matched.name || matched.hex) : placeholder;
      const triggerBg = escapeHtml(matched ? matched.hex : (currentColor || '#ccc'));
      const items = brandColors.map((c) => {
        const hex = normalizeHex(c.hex);
        const label = escapeHtml(c.name || c.hex);
        const active = matched && normalizeHex(c.hex) === current ? ' is-active' : '';
        return '<button type="button" class="present-brand-color-option' + active + '" data-row="' + rowIndex + '" data-color="' + escapeHtml(hex) + '" role="option" aria-selected="' + (active ? 'true' : 'false') + '">' +
          '<span class="present-brand-color-option-swatch" style="background:' + escapeHtml(c.hex) + '" aria-hidden="true"></span>' +
          '<span class="present-brand-color-option-label">' + label + '</span>' +
        '</button>';
      }).join('');
      return '<div class="present-brand-color-dropdown" data-row="' + rowIndex + '">' +
        '<button type="button" class="present-brand-color-trigger" data-row="' + rowIndex + '" aria-haspopup="listbox" aria-expanded="false" aria-label="' + placeholder + '">' +
          '<span class="present-brand-color-select-swatch" style="background:' + triggerBg + '" aria-hidden="true"></span>' +
          '<span class="present-brand-color-trigger-label">' + triggerLabel + '</span>' +
          '<span class="present-brand-color-chevron" aria-hidden="true">▾</span>' +
        '</button>' +
        '<div class="present-brand-color-panel" role="listbox" hidden>' + items + '</div>' +
      '</div>';
    }

    function closeAllBrandDropdowns(except) {
      listEl.querySelectorAll('.present-brand-color-dropdown').forEach((dd) => {
        if (except && dd === except) return;
        const panel = dd.querySelector('.present-brand-color-panel');
        const trigger = dd.querySelector('.present-brand-color-trigger');
        if (panel) panel.hidden = true;
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
        dd.classList.remove('is-open');
      });
    }

    function applyBrandColor(row, hex) {
      stops[row].color = hex;
      const colorInput = listEl.querySelector('[data-field="color"][data-row="' + row + '"]');
      if (colorInput) colorInput.value = hex;
      const dd = listEl.querySelector('.present-brand-color-dropdown[data-row="' + row + '"]');
      const match = brandColors.find((c) => normalizeHex(c.hex) === normalizeHex(hex));
      if (dd) {
        const swatch = dd.querySelector('.present-brand-color-select-swatch');
        const label = dd.querySelector('.present-brand-color-trigger-label');
        if (swatch) swatch.style.background = hex;
        if (label) label.textContent = match ? (match.name || match.hex) : (i18n.brandColors || 'Markenfarben');
        dd.querySelectorAll('.present-brand-color-option').forEach((opt) => {
          const on = normalizeHex(opt.dataset.color) === normalizeHex(hex);
          opt.classList.toggle('is-active', on);
          opt.setAttribute('aria-selected', on ? 'true' : 'false');
        });
      }
      persist();
    }

    function persist() {
      savePrefs({ timebarStops: stops.map((s) => ({ ...s })) });
    }

    function renderStops() {
      closeAllBrandDropdowns();
      listEl.innerHTML = stops.map((s, i) => {
        const isLocked = i === 0 || i === stops.length - 1;
        const pctLabel = i === 0 ? 'ab 0% (fix)' : (i === stops.length - 1 ? 'ab 100% (fix)' : 'ab');
        return '<div class="color-row" data-row="' + i + '">' +
          '<input type="color" data-field="color" data-row="' + i + '" value="' + escapeHtml(s.color) + '">' +
          '<div class="color-row-main">' +
            (isLocked
              ? '<span class="color-row-hex">' + pctLabel + '</span>'
              : '<input type="number" data-field="pct" data-row="' + i + '" min="1" max="99" value="' + s.pct + '" style="width:70px; display:inline-block;"> %') +
            brandSelectHtml(i, s.color) +
          '</div>' +
          (isLocked ? '' : '<button type="button" class="icon-action-btn" data-remove="' + i + '" title="Entfernen"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/><path d="M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13"/></svg></button>') +
        '</div>';
      }).join('');

      listEl.querySelectorAll('[data-field="color"]').forEach((input) => {
        input.addEventListener('input', () => {
          const row = parseInt(input.dataset.row, 10);
          stops[row].color = input.value;
          const dd = listEl.querySelector('.present-brand-color-dropdown[data-row="' + row + '"]');
          const match = brandColors.find((c) => normalizeHex(c.hex) === normalizeHex(input.value));
          if (dd) {
            const swatch = dd.querySelector('.present-brand-color-select-swatch');
            const label = dd.querySelector('.present-brand-color-trigger-label');
            if (swatch) swatch.style.background = input.value;
            if (label) label.textContent = match ? (match.name || match.hex) : (i18n.brandColors || 'Markenfarben');
            dd.querySelectorAll('.present-brand-color-option').forEach((opt) => {
              const on = !!match && normalizeHex(opt.dataset.color) === normalizeHex(match.hex);
              opt.classList.toggle('is-active', on);
              opt.setAttribute('aria-selected', on ? 'true' : 'false');
            });
          }
          persist();
        });
      });
      listEl.querySelectorAll('[data-field="pct"]').forEach((input) => {
        input.addEventListener('input', () => {
          stops[parseInt(input.dataset.row, 10)].pct = Math.max(1, Math.min(99, parseInt(input.value, 10) || 1));
          persist();
        });
      });
      listEl.querySelectorAll('.present-brand-color-trigger').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const dd = btn.closest('.present-brand-color-dropdown');
          const panel = dd?.querySelector('.present-brand-color-panel');
          if (!dd || !panel) return;
          const willOpen = panel.hidden;
          closeAllBrandDropdowns(willOpen ? dd : null);
          panel.hidden = !willOpen;
          btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
          dd.classList.toggle('is-open', willOpen);
        });
      });
      listEl.querySelectorAll('.present-brand-color-option').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const row = parseInt(btn.dataset.row, 10);
          applyBrandColor(row, btn.dataset.color);
          closeAllBrandDropdowns();
        });
      });
      listEl.querySelectorAll('[data-remove]').forEach((btn) => {
        btn.addEventListener('click', () => {
          stops.splice(parseInt(btn.dataset.remove, 10), 1);
          renderStops();
          persist();
        });
      });
    }

    document.addEventListener('click', (e) => {
      if (e.target.closest?.('.present-brand-color-dropdown')) return;
      closeAllBrandDropdowns();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeAllBrandDropdowns();
    });

    document.getElementById('presentAddTimebarStopBtn')?.addEventListener('click', () => {
      const lastMiddlePct = stops.length >= 2 ? stops[stops.length - 2].pct : 50;
      const newPct = Math.max(1, Math.min(99, lastMiddlePct + 5));
      stops.splice(stops.length - 1, 0, { pct: newPct, color: '#61a8e0' });
      renderStops();
      persist();
    });

    renderStops();
  })();

  // ---------- Laserpointer ----------
  (function initLaserSettings() {
    const colorInput = document.getElementById('presentLaserColor');
    const sizeInput = document.getElementById('presentLaserSize');
    const sizeVal = document.getElementById('presentLaserSizeVal');
    const previewDot = document.getElementById('presentLaserPreviewDot');
    const trailInput = document.getElementById('presentLaserTrail');
    const paletteEl = document.getElementById('presentLaserPalette');
    const ribbon = document.getElementById('presentRibbon');
    if (!colorInput || !sizeInput) return;

    const laser = P.laserPointer || { color: '#ff0000', size: 24, trail: false, enabled: true };

    function isLaserOn() {
      return P.laserPointer?.enabled !== false;
    }

    function laserColor() {
      return P.laserPointer?.color || colorInput.value || '#ff0000';
    }

    function syncRibbonLaserButtons(on) {
      const active = on !== false;
      const color = laserColor();
      document.querySelectorAll('#presentRibbon [data-ribbon-command="panel_laser"]').forEach((btn) => {
        btn.classList.toggle('active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        btn.style.color = active ? color : '';
      });
    }

    function updatePreview() {
      const color = colorInput.value;
      const size = Math.max(8, Math.min(64, parseInt(sizeInput.value, 10) || 24));
      if (sizeVal) sizeVal.textContent = String(size);
      if (previewDot) {
        previewDot.style.width = size + 'px';
        previewDot.style.height = size + 'px';
        previewDot.style.background = color;
        previewDot.style.boxShadow = '0 0 ' + Math.round(size * 0.7) + 'px ' + color;
        previewDot.style.opacity = isLaserOn() ? '1' : '0.35';
      }
    }

    if (paletteEl && brandColors.length) {
      paletteEl.innerHTML = brandColors.map(function (c) {
        return '<button type="button" class="brand-swatch" data-color="' + c.hex + '" style="background:' + c.hex + '" title="' + (c.name || c.hex) + '"></button>';
      }).join('');
      paletteEl.querySelectorAll('.brand-swatch').forEach(function (btn) {
        btn.addEventListener('click', function () {
          colorInput.value = btn.dataset.color;
          persist();
        });
      });
    }

    colorInput.value = laser.color || '#ff0000';
    sizeInput.value = String(laser.size || 24);
    if (trailInput) trailInput.checked = !!laser.trail;
    P.laserPointer = {
      color: colorInput.value,
      size: parseInt(sizeInput.value, 10) || 24,
      trail: trailInput ? !!trailInput.checked : false,
      enabled: laser.enabled !== false,
    };
    updatePreview();
    syncRibbonLaserButtons(isLaserOn());

    function persist(extra) {
      const partial = Object.assign({
        laserPointerColor: colorInput.value,
        laserPointerSize: parseInt(sizeInput.value, 10) || 24,
        laserPointerTrail: trailInput ? !!trailInput.checked : false,
        laserPointerEnabled: isLaserOn(),
      }, extra || {});
      P.laserPointer = {
        color: partial.laserPointerColor,
        size: partial.laserPointerSize,
        trail: partial.laserPointerTrail,
        enabled: partial.laserPointerEnabled,
      };
      savePrefs(partial);
      updatePreview();
      syncRibbonLaserButtons(partial.laserPointerEnabled !== false);
      window.SlideForgePresentLaserQuick?.sync?.();
    }

    function setEnabled(on) {
      persist({ laserPointerEnabled: !!on });
    }

    colorInput.addEventListener('input', persist);
    sizeInput.addEventListener('input', persist);
    trailInput?.addEventListener('change', persist);

    if (ribbon) {
      ribbon.addEventListener('click', (e) => {
        const btn = e.target.closest?.('[data-ribbon-command="panel_laser"]');
        if (!btn || !ribbon.contains(btn)) return;
        e.preventDefault();
        e.stopPropagation();
        setEnabled(!isLaserOn());
      });
    }

    document.addEventListener('sf:ribbon-rendered', () => {
      syncRibbonLaserButtons(isLaserOn());
    });

    window.SlideForgePresentLaser = {
      setEnabled,
      isEnabled: isLaserOn,
      syncRibbon: () => syncRibbonLaserButtons(isLaserOn()),
    };
  })();

  // ---------- Laser Schnellschalter (Folien steuern) ----------
  (function initLaserQuickToggle() {
    const btn = document.getElementById('presentLaserQuickToggle');
    if (!btn) return;
    const i18n = P.i18n || {};

    function laserColor() {
      return P.laserPointer?.color || P.presentLayout?.laserPointerColor || '#ff0000';
    }

    function isLaserOn() {
      if (window.SlideForgePresentLaser?.isEnabled) {
        return window.SlideForgePresentLaser.isEnabled();
      }
      return P.laserPointer?.enabled !== false;
    }

    function syncQuickToggle() {
      const on = isLaserOn();
      const color = laserColor();
      btn.classList.toggle('is-on', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      btn.title = on ? (i18n.laserToggleOn || '') : (i18n.laserToggleOff || '');
      btn.style.color = on ? color : '';
      window.SlideForgePresentLaser?.syncRibbon?.();
    }

    btn.addEventListener('click', function () {
      const next = !isLaserOn();
      if (window.SlideForgePresentLaser?.setEnabled) {
        window.SlideForgePresentLaser.setEnabled(next);
      } else {
        P.laserPointer = Object.assign({}, P.laserPointer || {}, { enabled: next });
        savePrefs({ laserPointerEnabled: next });
        syncQuickToggle();
      }
    });

    window.SlideForgePresentLaserQuick = { sync: syncQuickToggle };
    syncQuickToggle();
  })();

  // ---------- Fertige Folie im Hintergrund (Ghost) ----------
  (function initSlideGhostSettings() {
    const inlineWrap = document.getElementById('presentGhostInline');
    const inlineOpacity = document.getElementById('presentSlideGhostOpacityInline');
    const inlineVal = document.getElementById('presentSlideGhostOpacityInlineVal');
    const ribbon = document.getElementById('presentRibbon');

    function clampOpacity(v) {
      return Math.max(5, Math.min(80, parseInt(v, 10) || 25));
    }

    function currentGhostOn() {
      if (P.presentLayout && typeof P.presentLayout.showSlideGhost === 'boolean') {
        return P.presentLayout.showSlideGhost;
      }
      return true;
    }

    function currentOpacity() {
      if (P.presentLayout && P.presentLayout.slideGhostOpacity != null) {
        return clampOpacity(P.presentLayout.slideGhostOpacity);
      }
      return clampOpacity(inlineOpacity?.value || 25);
    }

    function syncRibbonGhostButtons(on) {
      document.querySelectorAll('#presentRibbon [data-ribbon-command="panel_ghost"]').forEach((btn) => {
        btn.classList.toggle('active', on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
    }

    function syncGhostUi(show, pct) {
      const on = !!show;
      const val = clampOpacity(pct);
      P.presentLayout = Object.assign({}, P.presentLayout || {}, {
        showSlideGhost: on,
        slideGhostOpacity: val,
      });
      if (inlineWrap) inlineWrap.classList.toggle('is-disabled', !on);
      if (inlineOpacity) {
        inlineOpacity.disabled = !on;
        inlineOpacity.value = String(val);
        inlineOpacity.setAttribute('aria-valuetext', val + '%');
      }
      if (inlineVal) inlineVal.textContent = String(val);
      syncRibbonGhostButtons(on);
      window.SlideForgePresentGhostQuick?.sync?.(on);
    }

    function persistFromUi(show, pct) {
      const val = clampOpacity(pct);
      syncGhostUi(show, val);
      savePrefs({ showSlideGhost: !!show, slideGhostOpacity: val });
    }

    if (inlineOpacity) {
      inlineOpacity.addEventListener('input', () => {
        persistFromUi(currentGhostOn(), inlineOpacity.value);
      });
    }

    if (ribbon) {
      ribbon.addEventListener('click', (e) => {
        const btn = e.target.closest?.('[data-ribbon-command="panel_ghost"]');
        if (!btn || !ribbon.contains(btn)) return;
        e.preventDefault();
        e.stopPropagation();
        persistFromUi(!currentGhostOn(), currentOpacity());
      });
    }

    document.addEventListener('sf:ribbon-rendered', () => {
      syncRibbonGhostButtons(currentGhostOn());
    });

    window.SlideForgePresentGhost = {
      sync: syncGhostUi,
      setEnabled(on) {
        persistFromUi(!!on, currentOpacity());
      },
      isEnabled: currentGhostOn,
    };

    syncGhostUi(currentGhostOn(), currentOpacity());
  })();

  // ---------- Ghost Schnellschalter (Folien steuern) ----------
  (function initGhostQuickToggle() {
    const btn = document.getElementById('presentGhostQuickToggle');
    if (!btn) return;
    const i18n = P.i18n || {};

    function syncGhostQuick(on) {
      const active = typeof on === 'boolean'
        ? on
        : (window.SlideForgePresentGhost?.isEnabled?.() ?? P.presentLayout?.showSlideGhost !== false);
      btn.classList.toggle('is-on', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
      btn.title = active ? (i18n.ghostToggleOn || '') : (i18n.ghostToggleOff || '');
    }

    btn.addEventListener('click', function () {
      if (window.SlideForgePresentGhost?.setEnabled) {
        window.SlideForgePresentGhost.setEnabled(!window.SlideForgePresentGhost.isEnabled());
      }
    });

    window.SlideForgePresentGhostQuick = { sync: syncGhostQuick };
    syncGhostQuick();
  })();
})();
