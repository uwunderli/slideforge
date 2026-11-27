/**
 * Globaler Standard: Mausrad auf input[type=number] ändert den Wert (step/min/max).
 *
 * - Hover über dem Feld genügt (kein Fokus nötig)
 * - Hoch scrollen = erhöhen, runter = verringern
 * - Löst `input` und `change` aus (bestehende Listener bleiben gültig)
 * - Disabled / readonly: keine Änderung
 */
(function () {
  'use strict';

  function stepOf(el) {
    const raw = el.getAttribute('step');
    if (raw == null || raw === '' || raw === 'any') return 1;
    const n = parseFloat(raw);
    return Number.isFinite(n) && n > 0 ? n : 1;
  }

  function decimalsOf(step) {
    const s = String(step);
    const i = s.indexOf('.');
    return i < 0 ? 0 : (s.length - i - 1);
  }

  function clamp(el, value) {
    let v = value;
    if (el.min !== '') {
      const min = parseFloat(el.min);
      if (Number.isFinite(min)) v = Math.max(min, v);
    }
    if (el.max !== '') {
      const max = parseFloat(el.max);
      if (Number.isFinite(max)) v = Math.min(max, v);
    }
    return v;
  }

  function formatValue(value, step) {
    const d = decimalsOf(step);
    if (d <= 0) return String(Math.round(value));
    return value.toFixed(d);
  }

  document.addEventListener('wheel', (e) => {
    const el = e.target;
    if (!(el instanceof HTMLInputElement) || el.type !== 'number') return;
    if (el.disabled || el.readOnly) return;

    e.preventDefault();

    const step = stepOf(el);
    const dir = e.deltaY < 0 ? 1 : -1;
    const current = parseFloat(el.value);
    const base = Number.isFinite(current) ? current : 0;
    const next = clamp(el, base + dir * step);
    const nextStr = formatValue(next, step);

    if (el.value === nextStr) return;
    el.value = nextStr;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }, { passive: false, capture: true });
})();
