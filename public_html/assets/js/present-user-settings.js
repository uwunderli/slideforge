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

    function paletteHtml(rowIndex) {
      return '<div class="brand-palette mini" style="margin-top:4px;">' +
        brandColors.map((c) => '<button type="button" class="brand-swatch" data-row="' + rowIndex + '" data-color="' + c.hex + '" style="background:' + c.hex + '" title="' + (c.name || c.hex) + '"></button>').join('') +
        '</div>';
    }

    function persist() {
      savePrefs({ timebarStops: stops.map((s) => ({ ...s })) });
    }

    function renderStops() {
      listEl.innerHTML = stops.map((s, i) => {
        const isLocked = i === 0 || i === stops.length - 1;
        const pctLabel = i === 0 ? 'ab 0% (fix)' : (i === stops.length - 1 ? 'ab 100% (fix)' : 'ab');
        return '<div class="color-row" data-row="' + i + '" style="align-items:flex-start;">' +
          '<input type="color" data-field="color" data-row="' + i + '" value="' + s.color + '">' +
          '<div style="flex:1;">' +
            (isLocked
              ? '<span class="color-row-hex">' + pctLabel + '</span>'
              : '<input type="number" data-field="pct" data-row="' + i + '" min="1" max="99" value="' + s.pct + '" style="width:70px; display:inline-block;"> %')
            + paletteHtml(i) +
          '</div>' +
          (isLocked ? '' : '<button type="button" class="icon-action-btn" data-remove="' + i + '" title="Entfernen"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/><path d="M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13"/></svg></button>') +
        '</div>';
      }).join('');

      listEl.querySelectorAll('[data-field="color"]').forEach((input) => {
        input.addEventListener('input', () => {
          stops[parseInt(input.dataset.row, 10)].color = input.value;
          persist();
        });
      });
      listEl.querySelectorAll('[data-field="pct"]').forEach((input) => {
        input.addEventListener('input', () => {
          stops[parseInt(input.dataset.row, 10)].pct = Math.max(1, Math.min(99, parseInt(input.value, 10) || 1));
          persist();
        });
      });
      listEl.querySelectorAll('.brand-swatch').forEach((btn) => {
        btn.addEventListener('click', () => {
          const row = parseInt(btn.dataset.row, 10);
          stops[row].color = btn.dataset.color;
          renderStops();
          persist();
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
    if (!colorInput || !sizeInput) return;

    const laser = P.laserPointer || { color: '#ff0000', size: 24, trail: false };

    function updatePreview() {
      const color = colorInput.value;
      const size = Math.max(8, Math.min(64, parseInt(sizeInput.value, 10) || 24));
      if (sizeVal) sizeVal.textContent = String(size);
      if (previewDot) {
        previewDot.style.width = size + 'px';
        previewDot.style.height = size + 'px';
        previewDot.style.background = color;
        previewDot.style.boxShadow = '0 0 ' + Math.round(size * 0.7) + 'px ' + color;
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
    updatePreview();

    function persist() {
      const partial = {
        laserPointerColor: colorInput.value,
        laserPointerSize: parseInt(sizeInput.value, 10) || 24,
        laserPointerTrail: trailInput ? !!trailInput.checked : false,
      };
      P.laserPointer = {
        color: partial.laserPointerColor,
        size: partial.laserPointerSize,
        trail: partial.laserPointerTrail,
      };
      savePrefs(partial);
      updatePreview();
    }

    colorInput.addEventListener('input', persist);
    sizeInput.addEventListener('input', persist);
    trailInput?.addEventListener('change', persist);
  })();
})();
