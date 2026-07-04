(function () {
  'use strict';

  const boot = window.SF_BOOTSTRAP;
  const SF = {
    id: boot.id,
    meta: boot.meta,
    canEdit: boot.canEdit,
    csrfToken: boot.csrfToken,
    slides: [],
    currentIndex: 0,
    stage: null,
    layer: null,
    bgRect: null,
    transformer: null,
    selectedNode: null,
    selectedNodes: [],
    saveTimer: null,
    saving: false,
    previewWindow: null,
    currentBackground: { type: 'color', value: '#111111' },
    manualZoom: null,
    currentZoom: 1,
    activePropsTab: 'form',
    activeFormatGroup: null,
    layersPanelOpen: localStorage.getItem('sf_layers_open') === '1',
    brandColors: boot.brandColors || [],
    textTemplates: boot.textTemplates || [],
    templateMode: !!boot.templateMode,
    presentConfig: boot.presentConfig || null,
    spellConfig: boot.spellcheck || null,
    pixabayConfig: boot.pixabay || null,
    iconifyConfig: boot.iconify || null,
    openclipartConfig: boot.openclipart || null,
    i18n: boot.i18n || {},
  };

  const FONT_OPTIONS = Array.isArray(boot.fontFamilies) && boot.fontFamilies.length
    ? boot.fontFamilies
    : ['Open Sans', 'PT Sans', 'Georgia', 'Arial', 'Times New Roman', 'Courier New', 'Verdana'];

  const I = SF.i18n;

  function applySpellcheckAttrs(el) {
    if (!el) return;
    if (SF.spellConfig?.browserEnabled) {
      el.spellcheck = true;
      el.lang = SF.spellConfig.htmlLang || SF.spellConfig.lang || 'de';
    } else {
      el.spellcheck = false;
    }
  }

  function effectSvg(inner) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + inner + '</svg>';
  }

  const ANIM_ICONS = {
    none: effectSvg('<rect x="7" y="7" width="10" height="10" rx="1.5"/><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/>'),
    'fade-in': effectSvg('<rect x="6" y="8" width="12" height="8" rx="1.5" opacity="0.35"/><rect x="6" y="8" width="12" height="8" rx="1.5" stroke-dasharray="2 2"/>'),
    'fade-out': effectSvg('<rect x="6" y="8" width="12" height="8" rx="1.5"/><rect x="6" y="8" width="12" height="8" rx="1.5" opacity="0.35" stroke-dasharray="2 2"/>'),
    'fade-up': effectSvg('<rect x="7" y="12" width="10" height="6" rx="1.5"/><path d="M12 5v4"/><path d="M9.5 7.5L12 5l2.5 2.5"/>'),
    'fade-down': effectSvg('<rect x="7" y="6" width="10" height="6" rx="1.5"/><path d="M12 19v-4"/><path d="M9.5 16.5L12 19l2.5-2.5"/>'),
    'fade-left': effectSvg('<rect x="6" y="7" width="6" height="10" rx="1.5"/><path d="M19 12h-4"/><path d="M16.5 9.5L19 12l-2.5 2.5"/>'),
    'fade-right': effectSvg('<rect x="12" y="7" width="6" height="10" rx="1.5"/><path d="M5 12h4"/><path d="M7.5 9.5L5 12l2.5 2.5"/>'),
    grow: effectSvg('<rect x="9" y="9" width="6" height="6" rx="1"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/>'),
    shrink: effectSvg('<rect x="8" y="8" width="8" height="8" rx="1"/><path d="M12 6v2M12 16v2M6 12h2M16 12h2M7.8 7.8l1.4 1.4M14.8 14.8l1.4 1.4M16.2 7.8l-1.4 1.4M9.2 14.8l-1.4 1.4"/>'),
    strike: effectSvg('<rect x="6" y="8" width="12" height="8" rx="1.5"/><line x1="5" y1="12" x2="19" y2="12"/>'),
  };

  const TRANSITION_ICONS = {
    none: effectSvg('<rect x="7" y="7" width="10" height="10" rx="1.5"/><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/>'),
    fade: effectSvg('<rect x="5" y="7" width="9" height="10" rx="1.5" opacity="0.45"/><rect x="10" y="7" width="9" height="10" rx="1.5"/>'),
    slide: effectSvg('<rect x="4" y="7" width="7" height="10" rx="1.5"/><rect x="13" y="7" width="7" height="10" rx="1.5"/><path d="M11 12h2"/><path d="M12.5 10.5L14 12l-1.5 1.5"/>'),
    convex: effectSvg('<path d="M4 17V7c4 0 6-4 8-4s4 4 8 4v10"/><rect x="4" y="7" width="16" height="10" rx="1.5" opacity="0.25"/>'),
    concave: effectSvg('<path d="M4 7h16v10c-4 0-6 4-8 4s-4-4-8-4V7z"/><rect x="4" y="7" width="16" height="10" rx="1.5" opacity="0.25"/>'),
    zoom: effectSvg('<rect x="9" y="9" width="6" height="6" rx="1"/><path d="M15 15l3 3"/><circle cx="12" cy="12" r="7"/>'),
  };

  const ANIMATIONS = [
    { value: 'none', label: I.optAnim?.none || 'None', icon: ANIM_ICONS.none },
    { value: 'fade-in', label: I.optAnim?.['fade-in'], icon: ANIM_ICONS['fade-in'] },
    { value: 'fade-out', label: I.optAnim?.['fade-out'], icon: ANIM_ICONS['fade-out'] },
    { value: 'fade-up', label: I.optAnim?.['fade-up'], icon: ANIM_ICONS['fade-up'] },
    { value: 'fade-down', label: I.optAnim?.['fade-down'], icon: ANIM_ICONS['fade-down'] },
    { value: 'fade-left', label: I.optAnim?.['fade-left'], icon: ANIM_ICONS['fade-left'] },
    { value: 'fade-right', label: I.optAnim?.['fade-right'], icon: ANIM_ICONS['fade-right'] },
    { value: 'grow', label: I.optAnim?.grow, icon: ANIM_ICONS.grow },
    { value: 'shrink', label: I.optAnim?.shrink, icon: ANIM_ICONS.shrink },
    { value: 'strike', label: I.optAnim?.strike, icon: ANIM_ICONS.strike },
  ];

  const TRANSITION_OPTIONS = [
    { value: 'none', label: I.optTransition?.none || 'None', icon: TRANSITION_ICONS.none },
    { value: 'fade', label: I.optTransition?.fade || 'Fade', icon: TRANSITION_ICONS.fade },
    { value: 'slide', label: I.optTransition?.slide || 'Slide', icon: TRANSITION_ICONS.slide },
    { value: 'convex', label: I.optTransition?.convex || 'Convex', icon: TRANSITION_ICONS.convex },
    { value: 'concave', label: I.optTransition?.concave || 'Concave', icon: TRANSITION_ICONS.concave },
    { value: 'zoom', label: I.optTransition?.zoom || 'Zoom', icon: TRANSITION_ICONS.zoom },
  ];

  const ANIM_DURATION_OPTIONS = [
    { value: 0, label: I.optSec?.[0] },
    { value: 500, label: I.optSec?.[500] },
    { value: 1000, label: I.optSec?.[1000] },
    { value: 2000, label: I.optSec?.[2000] },
    { value: 3000, label: I.optSec?.[3000] },
    { value: 5000, label: I.optSec?.[5000] },
  ];

  const ANIM_AUTOSTART_OPTIONS = [
    { value: 0, label: I.optAutostartOff },
    { value: 500, label: I.optSec?.[500] },
    { value: 1000, label: I.optSec?.[1000] },
    { value: 2000, label: I.optSec?.[2000] },
    { value: 3000, label: I.optSec?.[3000] },
    { value: 5000, label: I.optSec?.[5000] },
  ];

  const PLAY_TRIGGER_OPTIONS = [
    { value: 'manual', label: I.optPlay?.manual },
    { value: 'click', label: I.optPlay?.click },
    { value: 'timed', label: I.optPlay?.timed },
  ];

  const ARROW_STYLE_OPTIONS = [
    { value: 'right', label: I.optArrow?.right, icon: '→' },
    { value: 'left', label: I.optArrow?.left, icon: '←' },
    { value: 'up', label: I.optArrow?.up, icon: '↑' },
    { value: 'down', label: I.optArrow?.down, icon: '↓' },
    { value: 'double-h', label: I.optArrow?.['double-h'], icon: '⇄' },
    { value: 'double-v', label: I.optArrow?.['double-v'], icon: '⇅' },
  ];

  const BRACKET_STYLE_OPTIONS = [
    { value: 'square-left', label: I.optBracket?.['square-left'], icon: '[' },
    { value: 'square-right', label: I.optBracket?.['square-right'], icon: ']' },
    { value: 'round-left', label: I.optBracket?.['round-left'], icon: '(' },
    { value: 'round-right', label: I.optBracket?.['round-right'], icon: ')' },
    { value: 'curly-left', label: I.optBracket?.['curly-left'], icon: '{' },
    { value: 'curly-right', label: I.optBracket?.['curly-right'], icon: '}' },
  ];

  const BUBBLE_STYLE_OPTIONS = [
    { value: 'rect-left', label: I.optBubble?.['rect-left'], icon: '💬' },
    { value: 'rect-right', label: I.optBubble?.['rect-right'], icon: '💬', flip: true },
    { value: 'oval', label: I.optBubble?.oval, icon: '⬭' },
    { value: 'cloud', label: I.optBubble?.cloud, icon: '☁' },
  ];

  // Punkt-Templates (Anteile 0..1 von Breite/Höhe) für die zusätzlichen Objekt-Formen.
  // Werden sowohl im Konva-Editor als auch (als 0..100-Werte) im PHP-Renderer verwendet,
  // damit Editor-Vorschau und exportierter Foliensatz exakt gleich aussehen.
  const SHAPE_TEMPLATES = {
    triangle: [[0.5, 0], [1, 1], [0, 1]],
  };
  const SHAPE_LABELS = {
    triangle: I.shapeTriangle, star: I.shapeStar, arrow: I.shapeArrow, bracket: I.shapeBracket,
    'speech-bubble': I.shapeSpeechBubble, line: I.shapeLine,
  };
  const SHAPE_OPEN = { line: true, bracket: true }; // offene (ungefüllte) Formen: nur Umriss, kein Fill

  function starPoints(w, h, n) {
    n = Math.max(3, Math.min(20, n || 5));
    const cx = w / 2, cy = h / 2, rx = w / 2, ry = h / 2, innerRatio = 0.5;
    const pts = [];
    for (let i = 0; i < n * 2; i++) {
      const r = (i % 2 === 0) ? 1 : innerRatio;
      const angle = -Math.PI / 2 + i * Math.PI / n;
      pts.push(cx + r * rx * Math.cos(angle), cy + r * ry * Math.sin(angle));
    }
    return pts;
  }

  function arrowPoints(w, h, style) {
    const base = [[0, 0.35], [0.6, 0.35], [0.6, 0.1], [1, 0.5], [0.6, 0.9], [0.6, 0.65], [0, 0.65]];
    const doubleH = [[0, 0.5], [0.2, 0.25], [0.2, 0.35], [0.8, 0.35], [0.8, 0.25], [1, 0.5], [0.8, 0.75], [0.8, 0.65], [0.2, 0.65], [0.2, 0.75]];
    const doubleV = [[0.5, 0], [0.25, 0.2], [0.35, 0.2], [0.35, 0.8], [0.25, 0.8], [0.5, 1], [0.75, 0.8], [0.65, 0.8], [0.65, 0.2], [0.75, 0.2]];
    let tpl;
    if (style === 'left') tpl = base.map(([x, y]) => [1 - x, y]);
    else if (style === 'up') tpl = base.map(([x, y]) => [y, 1 - x]);
    else if (style === 'down') tpl = base.map(([x, y]) => [y, x]);
    else if (style === 'double-h') tpl = doubleH;
    else if (style === 'double-v') tpl = doubleV;
    else tpl = base; // 'right' (Standard)
    const pts = [];
    tpl.forEach(([fx, fy]) => pts.push(fx * w, fy * h));
    return pts;
  }

  function bracketPoints(w, h, style) {
    const isRight = (style || '').indexOf('right') !== -1;
    const pts = [];
    const push = (fx, fy) => pts.push(fx * w, fy * h);
    if ((style || '').indexOf('square') === 0) {
      if (isRight) { push(0, 0); push(1, 0); push(1, 1); push(0, 1); }
      else { push(1, 0); push(0, 0); push(0, 1); push(1, 1); }
    } else if ((style || '').indexOf('round') === 0) {
      // Rund: flach an den Spitzen (oben/unten), stärkste Krümmung in der Mitte -
      // sin^2 sorgt für Krümmung Null an t=0/1, Maximum bei t=0.5.
      const n = 24, amp = 0.85;
      for (let i = 0; i <= n; i++) {
        const t = i / n;
        const bulge = Math.pow(Math.sin(t * Math.PI), 2) * amp;
        push(isRight ? bulge : 1 - bulge, t);
      }
    } else { // curly
      // Geschweift: sanfter, durchgehender Bauch über die ganze Höhe plus eine
      // schmale, scharfe Spitze genau in der Mitte (kein Sprung, alles stetig).
      const n = 40;
      for (let i = 0; i <= n; i++) {
        const t = i / n;
        const d = Math.abs(t - 0.5) * 2; // 0 in der Mitte, 1 an den Enden
        const belly = (1 - Math.pow(d, 1.5)) * 0.35;
        const spike = Math.exp(-Math.pow(d * 6, 2)) * 0.5;
        const x = belly + spike;
        push(isRight ? x : 1 - x, t);
      }
    }
    return pts;
  }

  function bubblePoints(w, h, style) {
    if (style === 'rect-right') {
      const tpl = [[0, 0], [1, 0], [1, 0.75], [0.8, 0.75], [0.8, 1], [0.65, 0.75], [0, 0.75]];
      const pts = []; tpl.forEach(([fx, fy]) => pts.push(fx * w, fy * h)); return pts;
    }
    if (style === 'oval') {
      // Ellipse mit einer Lücke im Rand, in die der Zeiger eingesetzt wird -
      // vorher wurde fälschlich eine VOLLE Ellipse gezeichnet und der Zeiger
      // einfach angehängt, was zu einem sich selbst überschneidenden Polygon führte.
      const n = 32, cx = 0.5, cy = 0.4, rx = 0.48, ry = 0.36;
      const notchStart = 195, notchEnd = 235; // Grad, 0° = oben, im Uhrzeigersinn
      const raw = [];
      let inserted = false;
      for (let i = 0; i <= n; i++) {
        const deg = (i / n) * 360;
        if (deg >= notchStart && deg <= notchEnd) {
          if (!inserted) { raw.push([0.34, 0.73], [0.16, 1], [0.30, 0.76]); inserted = true; }
          continue;
        }
        const rad = (deg - 90) * Math.PI / 180;
        raw.push([cx + rx * Math.cos(rad), cy + ry * Math.sin(rad)]);
      }
      const pts = []; raw.forEach(([fx, fy]) => pts.push(fx * w, fy * h)); return pts;
    }
    if (style === 'cloud') {
      // Gedankenwolke: der Zeiger wird aus den TATSÄCHLICHEN Randpunkten der
      // Wellenform berechnet (nicht aus festen Koordinaten), damit er nahtlos
      // anschliesst - sonst entsteht eine sichtbare Naht/Überschneidung.
      const n = 48, cx = 0.5, cy = 0.42, rx = 0.42, ry = 0.32, bumps = 6, bumpAmp = 0.07;
      const notchStart = 205, notchEnd = 235; // Grad, unten links
      const boundaryPoint = (deg) => {
        const t = (deg / 360) * Math.PI * 2;
        const r = 1 + bumpAmp * Math.sin(bumps * t);
        return [cx + rx * r * Math.cos(t), cy + ry * r * Math.sin(t)];
      };
      const pts = [];
      let inserted = false;
      for (let i = 0; i <= n; i++) {
        const deg = (i / n) * 360;
        if (deg > notchStart && deg < notchEnd) {
          if (!inserted) {
            const [px1, py1] = boundaryPoint(notchStart);
            const [px2, py2] = boundaryPoint(notchEnd);
            const midX = (px1 + px2) / 2, midY = (py1 + py2) / 2;
            const dirX = midX - cx, dirY = midY - cy;
            const dirLen = Math.sqrt(dirX * dirX + dirY * dirY) || 1;
            const tipX = midX + (dirX / dirLen) * 0.32;
            const tipY = midY + (dirY / dirLen) * 0.32 + 0.16;
            pts.push(px1 * w, py1 * h, tipX * w, tipY * h, px2 * w, py2 * h);
            inserted = true;
          }
          continue;
        }
        const [px, py] = boundaryPoint(deg);
        pts.push(px * w, py * h);
      }
      return pts;
    }
    // 'rect-left' (Standard)
    const tpl = [[0, 0], [1, 0], [1, 0.75], [0.35, 0.75], [0.2, 1], [0.2, 0.75], [0, 0.75]];
    const pts = []; tpl.forEach(([fx, fy]) => pts.push(fx * w, fy * h)); return pts;
  }

  function buildShapePoints(shapeType, w, h, obj) {
    obj = obj || {};
    // Rückwärtskompatibilität für ältere shapeType-Werte aus Präsentationen von vor diesem Update.
    if (shapeType === 'arrow-thin' || shapeType === 'arrow-thick') return arrowPoints(w, h, 'right');
    if (shapeType === 'wave-banner') {
      const tpl = [[0, 0.15], [0.25, 0], [0.5, 0.15], [0.75, 0], [1, 0.15], [1, 0.85], [0.75, 1], [0.5, 0.85], [0.25, 1], [0, 0.85]];
      const pts = []; tpl.forEach(([fx, fy]) => pts.push(fx * w, fy * h)); return pts;
    }
    if (shapeType === 'star') return starPoints(w, h, obj.starPoints || 5);
    if (shapeType === 'arrow') return arrowPoints(w, h, obj.arrowStyle || 'right');
    if (shapeType === 'bracket') return bracketPoints(w, h, obj.bracketStyle || 'round-left');
    if (shapeType === 'speech-bubble') return bubblePoints(w, h, obj.bubbleStyle || 'rect-left');
    if (shapeType === 'line') { return [0, h / 2, w, h / 2]; }
    const tpl = SHAPE_TEMPLATES[shapeType] || SHAPE_TEMPLATES.triangle;
    const pts = [];
    tpl.forEach(([fx, fy]) => { pts.push(fx * w, fy * h); });
    return pts;
  }

  function nodeShapeCfg(node) {
    return {
      starPoints: node.getAttr('starPoints'),
      arrowStyle: node.getAttr('arrowStyle'),
      bracketStyle: node.getAttr('bracketStyle'),
      bubbleStyle: node.getAttr('bubbleStyle'),
    };
  }

  // ---------- API ----------
  async function api(action, data) {
    const res = await fetch('api.php?action=' + encodeURIComponent(action) + '&id=' + encodeURIComponent(SF.id), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': SF.csrfToken },
      body: JSON.stringify(Object.assign({ action: action, id: SF.id, csrf_token: SF.csrfToken }, data || {})),
    });
    const json = await res.json();
    if (!json.ok) {
      throw new Error(json.error || 'Unbekannter Fehler');
    }
    return json;
  }

  async function apiGet(action) {
    const res = await fetch('api.php?action=' + encodeURIComponent(action) + '&id=' + encodeURIComponent(SF.id));
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Unbekannter Fehler');
    return json;
  }

  function setSaveStatus(text, isError) {
    const el = document.getElementById('saveStatus');
    if (!el) return;
    el.textContent = text;
    el.style.color = isError ? 'var(--danger)' : 'var(--text-muted)';
  }

  function bindZoomUI() {
    document.getElementById('zoomInBtn')?.addEventListener('click', () => zoomBy(10));
    document.getElementById('zoomOutBtn')?.addEventListener('click', () => zoomBy(-10));
    document.getElementById('zoomFitBtn')?.addEventListener('click', () => zoomFit());
  }

  function bindTemplatePicker() {
    const btn = document.getElementById('applyTemplateBtn');
    if (!btn) return;
    btn.addEventListener('click', async () => {
      const listEl = document.getElementById('templateList');
      listEl.innerHTML = '<p style="color:var(--text-muted); font-size:0.85rem;">' + SF.i18n.loading + '</p>';
      document.getElementById('templateModal').classList.add('open');
      try {
        const res = await apiGet('list_slide_templates');
        renderTemplatePicker(res.mine, res.shared);
      } catch (e) {
        listEl.innerHTML = '<p style="color:var(--danger); font-size:0.85rem;">' + SF.i18n.error + '</p>';
      }
    });
  }

  function renderTemplatePicker(mine, shared) {
    const listEl = document.getElementById('templateList');
    if (!mine.length && !shared.length) {
      listEl.innerHTML = '<p style="color:var(--text-muted); font-size:0.85rem;">' + SF.i18n.empty + '</p>';
      return;
    }
    const renderGroup = (title, items) => {
      if (!items.length) return '';
      return '<div class="options-subtitle" style="margin-top:14px;">' + title + '</div>' +
        items.map(t =>
          '<div class="template-pick-row" data-template-id="' + t.id + '">' +
            '<span>' + escapeHtml(t.title) + '</span>' +
            '<button type="button" class="button button-sm" data-apply-template="' + t.id + '">' + SF.i18n.apply + '</button>' +
          '</div>'
        ).join('');
    };
    listEl.innerHTML = renderGroup(SF.i18n.own, mine) + renderGroup(SF.i18n.shared, shared);
    listEl.querySelectorAll('[data-apply-template]').forEach(btn => {
      btn.addEventListener('click', () => applySlideTemplate(btn.dataset.applyTemplate));
    });
  }

  async function applySlideTemplate(templateId) {
    setSaveStatus('Wende Vorlage an…');
    try {
      await api('apply_slide_template', { index: SF.currentIndex, template_id: templateId });
      const res = await apiGet('get_slides');
      SF.slides = res.slides;
      loadSlideIntoStage(SF.currentIndex);
      await renderSlideFilmstrip();
      document.getElementById('templateModal').classList.remove('open');
      setSaveStatus('Gespeichert');
      reloadPreviewWindow();
    } catch (e) {
      setSaveStatus('Fehler beim Anwenden', true);
      console.error(e);
    }
  }

  // ---------- Bootstrapping ----------
  async function ensureFontsLoaded() {
    if (!document.fonts || !document.fonts.load) return;
    const families = FONT_OPTIONS.slice();
    const weights = ['400', '700'];
    const promises = [];
    families.forEach((f) => {
      weights.forEach((w) => {
        promises.push(document.fonts.load(w + ' 32px "' + f + '"').catch(() => {}));
      });
    });
    try { await Promise.all(promises); } catch (e) { /* ignore */ }
    try { await document.fonts.ready; } catch (e) { /* ignore */ }
  }

  async function init() {
    await ensureFontsLoaded();
    try {
      const res = await apiGet('get_slides');
      SF.slides = res.slides;
    } catch (e) {
      setSaveStatus('Fehler beim Laden', true);
      console.error(e);
      return;
    }
    renderTextTemplateButtons();
    if (SF.templateMode) {
      document.querySelector('[data-objtab="slides"]')?.remove();
      document.querySelector('.obj-tab-panel[data-objtab="slides"]')?.remove();
      const firstTab = document.querySelector('.obj-tab-btn');
      const firstPanel = document.querySelector('.obj-tab-panel');
      if (firstTab) firstTab.classList.add('active');
      if (firstPanel) firstPanel.hidden = false;
    } else {
      SF.currentIndex = Math.min(initialSlideIndexFromUrl(), Math.max(0, SF.slides.length - 1));
      await renderSlideFilmstrip();
    }
    buildStage();
    initTransitionPicker();
    loadSlideIntoStage(SF.currentIndex);
    if (!SF.templateMode) {
      updateFilmstripActive();
      const filmstrip = document.getElementById('slideFilmstrip');
      const activeItem = filmstrip?.querySelector('.filmstrip-item.active');
      if (activeItem) activeItem.scrollIntoView({ block: 'nearest' });
    }
    bindGlobalUI();
    initSpellcheckPanel();
    initPixabayPanel();
    initIconifyPanel();
    initOpenclipartPanel();
    initMediaSearchButtons();
    initMediaLibraryPanel();
    bindZoomUI();
    bindTemplatePicker();
    updatePresentLinkOnSlideChange();
    window.addEventListener('resize', resizeStageToFit);
    const canvasScrollEl = document.querySelector('.canvas-scroll');
    if (canvasScrollEl && window.ResizeObserver) {
      let resizeRaf = null;
      new ResizeObserver(() => {
        cancelAnimationFrame(resizeRaf);
        resizeRaf = requestAnimationFrame(resizeStageToFit);
      }).observe(canvasScrollEl);
    }
  }

  // ---------- Stage / Canvas ----------
  function buildStage() {
    const container = document.getElementById('stageContainer');
    SF.stage = new Konva.Stage({
      container: 'stageContainer',
      width: SF.meta.width,
      height: SF.meta.height,
    });
    SF.layer = new Konva.Layer();
    SF.stage.add(SF.layer);

    SF.bgRect = new Konva.Rect({
      x: 0, y: 0, width: SF.meta.width, height: SF.meta.height,
      fill: '#111111', listening: false,
    });
    SF.layer.add(SF.bgRect);

    if (SF.canEdit) {
      SF.transformer = new Konva.Transformer({
        rotateAnchorOffset: 24,
        anchorSize: 9,
        borderStroke: '#3a6c8d',
        anchorStroke: '#3a6c8d',
        anchorFill: '#ffffff',
        boundBoxFunc: (oldBox, newBox) => {
          if (!SF.transformer?.nodes().length) return newBox;
          if (SF.transformer.nodes().some(isObjectGroup)) return newBox;
          return snapTransformBox(oldBox, newBox, SF.transformer.nodes());
        },
      });
      SF.transformer.on('transformend', () => clearSnapGuides());
      SF.layer.add(SF.transformer);

      initMarqueeSelection();
    }

    resizeStageToFit();
  }

  function initMarqueeSelection() {
    let selectStart = null;
    let selectRect = null;
    let isMarquee = false;

    SF.stage.on('mousedown touchstart', (e) => {
      if (e.target !== SF.stage && e.target !== SF.bgRect) return;
      const pos = SF.stage.getRelativePointerPosition();
      if (!pos) return;
      selectStart = { x: pos.x, y: pos.y };
      isMarquee = false;
    });

    SF.stage.on('mousemove touchmove', () => {
      if (!selectStart) return;
      const pos = SF.stage.getRelativePointerPosition();
      if (!pos) return;
      const dx = pos.x - selectStart.x;
      const dy = pos.y - selectStart.y;
      if (!isMarquee && Math.hypot(dx, dy) < 4) return;
      isMarquee = true;
      if (!selectRect) {
        selectRect = new Konva.Rect({
          fill: 'rgba(58,108,141,0.12)',
          stroke: '#3a6c8d',
          strokeWidth: 1,
          dash: [4, 4],
          listening: false,
          name: 'sf-guide',
        });
        SF.layer.add(selectRect);
      }
      selectRect.setAttrs({
        x: Math.min(selectStart.x, pos.x),
        y: Math.min(selectStart.y, pos.y),
        width: Math.abs(dx),
        height: Math.abs(dy),
      });
      if (SF.transformer) SF.transformer.moveToTop();
      refreshCanvas();
    });

    const finishMarquee = () => {
      if (!selectStart) return;
      if (isMarquee && selectRect) {
        const box = selectRect.getClientRect({ relativeTo: SF.layer });
        const hits = getTopLevelNodes().filter((node) => {
          const b = node.getClientRect({ relativeTo: SF.layer });
          return rectsIntersect(box, b);
        });
        selectNodes(hits);
      } else {
        deselect();
      }
      selectStart = null;
      isMarquee = false;
      if (selectRect) {
        selectRect.destroy();
        selectRect = null;
      }
      refreshCanvas();
    };

    SF.stage.on('mouseup touchend mouseupoutside touchendoutside', finishMarquee);
  }

  function computeFitScale() {
    const wrap = document.querySelector('.canvas-scroll');
    if (!wrap) return 1;
    const availableW = wrap.clientWidth - 64;
    const availableH = wrap.clientHeight - 64;
    const scaleW = availableW / SF.meta.width;
    const scaleH = availableH / SF.meta.height;
    return Math.max(0.05, Math.min(1, scaleW, scaleH));
  }

  function applyStageScale(scale) {
    if (!SF.stage) return;
    SF.currentZoom = scale;
    const w = SF.meta.width * scale, h = SF.meta.height * scale;
    SF.stage.width(w);
    SF.stage.height(h);
    SF.stage.scale({ x: scale, y: scale });
    const bgLayer = document.getElementById('stageBgLayer');
    if (bgLayer) { bgLayer.style.width = w + 'px'; bgLayer.style.height = h + 'px'; }
    const wrapEl = document.getElementById('stageWrap');
    if (wrapEl) { wrapEl.style.width = w + 'px'; wrapEl.style.height = h + 'px'; }
    const label = document.getElementById('zoomLabel');
    if (label) label.textContent = Math.round(scale * 100) + '%';
  }

  function resizeStageToFit() {
    if (!SF.stage) return;
    applyStageScale(SF.manualZoom !== null ? SF.manualZoom : computeFitScale());
  }

  function zoomBy(deltaPercent) {
    const base = SF.manualZoom !== null ? SF.manualZoom : SF.currentZoom;
    const next = Math.max(0.1, Math.min(3, base + deltaPercent / 100));
    SF.manualZoom = next;
    applyStageScale(next);
  }

  function zoomFit() {
    SF.manualZoom = null;
    applyStageScale(computeFitScale());
  }

  function bgSwatchStyle(bg) {
    if (!bg) return 'background:#333';
    if (bg.type === 'color') return 'background:' + bg.value;
    if (bg.type === 'gradient') return 'background:' + (bg.value || '#333');
    if (bg.type === 'image' && bg.value) return "background-image:url('" + bg.value + "'); background-size:cover; background-position:center;";
    if (bg.type === 'video') return 'background:#2a2a2a;';
    return 'background:#333';
  }

  function slideBgColor(slide) {
    const bg = slide?.background;
    if (!bg) return '#333333';
    if (bg.type === 'color' || bg.type === 'gradient') return bg.value || '#333333';
    if (bg.type === 'image') return '#222222';
    return '#333333';
  }

  function updatePresentLink() {
    const link = document.getElementById('presentModeLink');
    if (link) {
      link.href = 'present.php?id=' + encodeURIComponent(SF.id) + '&slide=' + SF.currentIndex;
    }
    const previewLink = document.getElementById('previewTabLink');
    if (previewLink) {
      previewLink.href = 'preview.php?id=' + encodeURIComponent(SF.id) + '&slide=' + SF.currentIndex;
    }
  }

  function initialSlideIndexFromUrl() {
    const raw = new URLSearchParams(window.location.search).get('slide');
    if (raw === null || raw === '') return 0;
    const idx = parseInt(raw, 10);
    return Number.isFinite(idx) && idx >= 0 ? idx : 0;
  }

  function syncPreviewSlideIndex(slideIndex) {
    if (!SF.canEdit) return;
    const win = SF.previewWindow;
    if (!win || win.closed) return;
    const idx = typeof slideIndex === 'number' ? slideIndex : SF.currentIndex;
    try {
      win.postMessage({ type: 'sf_goto', index: idx }, window.location.origin);
    } catch (e) { /* Vorschaufenster nicht erreichbar */ }
  }

  function reloadPreviewWindow(slideIndex) {
    if (!SF.canEdit) return;
    const win = SF.previewWindow;
    if (!win || win.closed) return;
    const idx = typeof slideIndex === 'number' ? slideIndex : SF.currentIndex;
    try {
      win.location.href = 'preview.php?id=' + encodeURIComponent(SF.id) + '&slide=' + idx;
    } catch (e) { /* Vorschaufenster nicht erreichbar */ }
  }

  function updatePresentLinkOnSlideChange() {
    updatePresentLink();
  }

  // ---------- Slide filmstrip (linke Spalte, Tab „Folien") ----------
  function getEditorFilmstripInnerWidth() {
    const container = document.getElementById('slideFilmstrip');
    if (container && container.clientWidth > 0) {
      const cs = getComputedStyle(container);
      const handle = SF.canEdit ? 18 : 0;
      return Math.max(120, Math.floor(
        container.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight) - handle
      ));
    }
    const side = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--editor-side-width'), 10) || 380;
    const handle = SF.canEdit ? 18 : 0;
    return Math.max(120, side - 76 - 28 - handle);
  }

  function filmstripScale() {
    return getEditorFilmstripInnerWidth() / Math.max(1, SF.meta.width);
  }

  function filmstripItemHeight() {
    return Math.max(52, Math.round(SF.meta.height * filmstripScale()));
  }

  function updateFilmstripDimensions() {
    const container = document.getElementById('slideFilmstrip');
    if (!container || !container.querySelector('.editor-filmstrip-item')) return;
    const fsScale = filmstripScale();
    const itemHeight = filmstripItemHeight();
    container.querySelectorAll('.editor-filmstrip-item').forEach((item) => {
      item.style.height = itemHeight + 'px';
      const scaleEl = item.querySelector('.filmstrip-thumb-scale');
      if (scaleEl) {
        scaleEl.style.width = SF.meta.width + 'px';
        scaleEl.style.height = SF.meta.height + 'px';
        scaleEl.style.transform = 'scale(' + fsScale + ')';
      }
    });
  }

  const NOTES_ICON_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h10M4 18h16"/></svg>';

  function slideHasNotes(slide) {
    return !!(slide?.notes && String(slide.notes).trim() !== '');
  }

  function filmstripNotesBadgeHtml() {
    return '<span class="filmstrip-notes-badge" title="' + escapeHtml(SF.i18n.notesTitle || 'Notizen') + '">' + NOTES_ICON_SVG + '</span>';
  }

  function syncFilmstripNotesBadge(slideIndex) {
    const slide = SF.slides[slideIndex];
    if (!slide) return;
    const item = document.querySelector('.editor-filmstrip-item[data-id="' + slide.id + '"]');
    if (!item) return;
    const hasNotes = slideHasNotes(slide);
    item.classList.toggle('has-notes', hasNotes);
    const badge = item.querySelector('.filmstrip-notes-badge');
    if (hasNotes && !badge) {
      item.insertAdjacentHTML('beforeend', filmstripNotesBadgeHtml());
    } else if (!hasNotes && badge) {
      badge.remove();
    }
  }

  async function fetchSlideThumbnails() {
    const res = await apiGet('slide_thumbnails');
    SF.thumbnails = {};
    (res.thumbnails || []).forEach((t) => { SF.thumbnails[t.id] = t; });
    return SF.thumbnails;
  }

  async function refreshCurrentFilmstripThumb() {
    const container = document.getElementById('slideFilmstrip');
    if (!container) return;
    try {
      const res = await apiGet('slide_thumbnails');
      const slide = SF.slides[SF.currentIndex];
      if (!slide) return;
      const thumb = (res.thumbnails || []).find((t) => t.id === slide.id);
      if (!thumb) return;
      SF.thumbnails[slide.id] = thumb;
      const item = container.querySelector('.filmstrip-item[data-id="' + slide.id + '"]');
      if (!item) return;
      item.style.setProperty('--fs-color', thumb.color);
      const scaleEl = item.querySelector('.filmstrip-thumb-scale');
      if (scaleEl) scaleEl.innerHTML = thumb.html;
    } catch (e) {
      console.error(e);
    }
  }

  function updateFilmstripActive() {
    const container = document.getElementById('slideFilmstrip');
    if (!container) return;
    container.querySelectorAll('.filmstrip-item').forEach((item) => {
      const idx = SF.slides.findIndex((s) => s.id === item.dataset.id);
      item.classList.toggle('active', idx === SF.currentIndex);
    });
  }

  function bindFilmstripReorder(container) {
    if (!container || container.dataset.reorderBound) return;
    container.dataset.reorderBound = '1';

    let dragItem = null;
    let dragMoved = false;

    container.addEventListener('mousedown', (e) => {
      if (!SF.canEdit || e.button !== 0) return;
      const item = e.target.closest('.editor-filmstrip-item');
      if (!item || e.target.closest('.tab-action')) return;

      dragItem = item;
      dragMoved = false;
      item.classList.add('dragging');
      document.body.classList.add('editor-filmstrip-dragging');

      const onMove = (ev) => {
        if (!dragItem) return;
        const after = getFilmstripAfterElement(container, ev.clientY);
        const prev = [...container.querySelectorAll('.editor-filmstrip-item')].map((el) => el.dataset.id);
        if (after == null) container.appendChild(dragItem);
        else if (after !== dragItem) container.insertBefore(dragItem, after);
        const next = [...container.querySelectorAll('.editor-filmstrip-item')].map((el) => el.dataset.id);
        if (prev.join(',') !== next.join(',')) {
          dragMoved = true;
          updateFilmstripNumbers(container);
        }
      };

      const onUp = () => {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        document.body.classList.remove('editor-filmstrip-dragging');
        if (!dragItem) return;
        dragItem.classList.remove('dragging');
        dragItem = null;
        if (dragMoved) {
          SF.filmstripSuppressClick = true;
          commitFilmstripOrder();
          setTimeout(() => { SF.filmstripSuppressClick = false; }, 100);
        }
      };

      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
      e.preventDefault();
    });
  }

  function updateFilmstripNumbers(container) {
    container.querySelectorAll('.editor-filmstrip-item').forEach((item, i) => {
      const num = item.querySelector('.filmstrip-num');
      if (num) num.textContent = String(i + 1);
    });
  }

  async function renderSlideFilmstrip() {
    const container = document.getElementById('slideFilmstrip');
    if (!container) return;

    try {
      await fetchSlideThumbnails();
    } catch (e) {
      container.innerHTML = '<p style="color:var(--danger); font-size:0.85rem;">' + (SF.i18n.error || 'Fehler') + '</p>';
      console.error(e);
      return;
    }

    container.innerHTML = '';
    const fsScale = filmstripScale();
    const itemHeight = filmstripItemHeight();

    SF.slides.forEach((slide, i) => {
      const thumb = SF.thumbnails[slide.id] || {};
      const hasNotes = slideHasNotes(slide);
      const tab = document.createElement('div');
      tab.className = 'filmstrip-item editor-filmstrip-item' + (i === SF.currentIndex ? ' active' : '') + (hasNotes ? ' has-notes' : '');
      tab.dataset.id = slide.id;
      tab.style.setProperty('--fs-color', thumb.color || slideBgColor(slide));
      tab.style.height = itemHeight + 'px';

      tab.innerHTML =
        (SF.canEdit ? '<span class="editor-filmstrip-handle" title="' + (SF.i18n.reorderSlide || 'Ziehen zum Sortieren') + '">⋮⋮</span>' : '') +
        '<div class="filmstrip-thumb-scale" style="width:' + SF.meta.width + 'px; height:' + SF.meta.height + 'px; transform:scale(' + fsScale + ');">' +
          (thumb.html || '') +
        '</div>' +
        (hasNotes ? filmstripNotesBadgeHtml() : '') +
        '<span class="filmstrip-num">' + (i + 1) + '</span>' +
        (SF.canEdit
          ? '<span class="editor-filmstrip-actions">' +
              '<button type="button" class="tab-action" data-act="dup" title="' + (SF.i18n.duplicateSlide || 'Duplizieren') + '">⧉</button>' +
              (SF.slides.length > 1 ? '<button type="button" class="tab-action" data-act="del" title="' + (SF.i18n.deleteSlide || 'Löschen') + '">✕</button>' : '') +
            '</span>'
          : '');

      tab.addEventListener('click', (e) => {
        if (SF.filmstripSuppressClick) return;
        if (e.target.closest('[data-act]') || e.target.closest('.editor-filmstrip-handle')) return;
        const idx = SF.slides.findIndex((s) => s.id === tab.dataset.id);
        switchToSlide(idx);
      });

      if (SF.canEdit) {
        const dupBtn = tab.querySelector('[data-act="dup"]');
        if (dupBtn) dupBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          duplicateSlide(SF.slides.findIndex((s) => s.id === tab.dataset.id));
        });
        const delBtn = tab.querySelector('[data-act="del"]');
        if (delBtn) delBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          deleteSlide(SF.slides.findIndex((s) => s.id === tab.dataset.id));
        });
      }

      container.appendChild(tab);
    });

    bindFilmstripReorder(container);
    updateFilmstripDimensions();

    if (window.ResizeObserver && !container.dataset.roBound) {
      container.dataset.roBound = '1';
      new ResizeObserver(() => updateFilmstripDimensions()).observe(container);
    }

    const activeItem = container.querySelector('.filmstrip-item.active');
    if (activeItem) activeItem.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }

  function getFilmstripAfterElement(container, y) {
    const items = [...container.querySelectorAll('.filmstrip-item:not(.dragging)')];
    return items.reduce((closest, child) => {
      const box = child.getBoundingClientRect();
      const offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) {
        return { offset: offset, element: child };
      }
      return closest;
    }, { offset: Number.NEGATIVE_INFINITY }).element;
  }

  async function commitFilmstripOrder() {
    const container = document.getElementById('slideFilmstrip');
    if (!container) return;
    const newOrderIds = [...container.querySelectorAll('.filmstrip-item')].map((el) => el.dataset.id);
    const oldOrderIds = SF.slides.map((s) => s.id);
    if (JSON.stringify(newOrderIds) === JSON.stringify(oldOrderIds)) return;

    const currentId = SF.slides[SF.currentIndex] ? SF.slides[SF.currentIndex].id : null;
    setSaveStatus('Speichere…');
    const res = await api('reorder_slides', { order: newOrderIds });
    SF.slides = res.slides;
    if (currentId) SF.currentIndex = SF.slides.findIndex((s) => s.id === currentId);
    setSaveStatus('Gespeichert');
    await renderSlideFilmstrip();
    reloadPreviewWindow();
  }

  async function switchToSlide(index) {
    if (index === SF.currentIndex) return;
    SF.skipPreviewReload = true;
    await saveCurrentSlide(true);
    SF.skipPreviewReload = false;
    loadSlideIntoStage(index);
    updateFilmstripActive();
    const container = document.getElementById('slideFilmstrip');
    const activeItem = container?.querySelector('.filmstrip-item.active');
    if (activeItem) activeItem.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }

  function loadSlideIntoStage(index, skipHistoryReset) {
    SF.currentIndex = index;
    const slide = SF.slides[index];
    if (!slide) return;

    // Alte Objekte entfernen (ausser Hintergrund + Transformer)
    getTopLevelNodes().forEach((n) => n.destroy());
    SF.layer.find('.sf-badge, .sf-guide').forEach((n) => n.destroy());
    deselect();

    const bg = slide.background || { type: 'color', value: '#111111' };
    SF.currentBackground = bg;
    applyBackgroundVisual(bg);

    (slide.objects || []).forEach((obj) => {
      const node = createNode(obj);
      node.name('sf-node');
      SF.layer.add(node);
      bindNodeEvents(node);
      updateAnimationBadge(node);
    });
    if (SF.transformer) SF.layer.add(SF.transformer);
    drawSafeMarginGuide();
    SF.layer.draw();

    if (SF.canEdit) {
      populateBackgroundControls(bg);
      setTransitionPickerValue(slide.transition || 'slide');
      document.getElementById('autoAdvanceInput').value = slide.autoAdvance || 0;
      const notesEl = document.getElementById('slideNotesInput');
      if (notesEl) notesEl.value = slide.notes || '';
    }
    if (!skipHistoryReset) resetHistoryForCurrentSlide();
    updatePresentLinkOnSlideChange();
    syncPreviewSlideIndex();
    renderLayersPanel();
  }

  // ---------- Hintergrund ----------
  function applyBackgroundVisual(bg) {
    const bgLayer = document.getElementById('stageBgLayer');
    if (bgLayer) bgLayer.innerHTML = '';
    if (!SF.bgRect) return;

    if (bg.type === 'gradient') {
      const w = SF.meta.width, h = SF.meta.height;
      const angle = bg.angle || 0;
      const rad = (angle - 90) * Math.PI / 180;
      const dx = Math.cos(rad), dy = Math.sin(rad);
      const half = Math.sqrt(w * w + h * h) / 2;
      SF.bgRect.fillLinearGradientStartPoint({ x: w / 2 - dx * half, y: h / 2 - dy * half });
      SF.bgRect.fillLinearGradientEndPoint({ x: w / 2 + dx * half, y: h / 2 + dy * half });
      SF.bgRect.fillLinearGradientColorStops([0, bg.color1 || '#3a6c8d', 1, bg.color2 || '#87b42b']);
      SF.bgRect.fillPriority('linear-gradient');
      if (bgLayer) bgLayer.style.background = 'none';
    } else if (bg.type === 'image') {
      SF.bgRect.fillPriority('color');
      SF.bgRect.fill('rgba(0,0,0,0)');
      if (bgLayer) {
        bgLayer.style.background = bg.value ? "center / cover no-repeat url('" + bg.value + "')" : 'none';
      }
    } else if (bg.type === 'video') {
      SF.bgRect.fillPriority('color');
      SF.bgRect.fill('rgba(0,0,0,0)');
      if (bgLayer && bg.value) {
        bgLayer.style.background = 'none';
        const video = document.createElement('video');
        video.src = bg.value;
        video.muted = true; video.loop = true; video.autoplay = true; video.playsInline = true;
        bgLayer.appendChild(video);
      }
    } else if (bg.type === 'none') {
      SF.bgRect.fillPriority('color');
      SF.bgRect.fill('#111111');
      if (bgLayer) bgLayer.style.background = 'none';
    } else {
      SF.bgRect.fillPriority('color');
      SF.bgRect.fill(bg.value || '#111111');
      if (bgLayer) bgLayer.style.background = 'none';
    }
    refreshCanvas();
  }

  function populateBackgroundControls(bg) {
    document.querySelectorAll('.bg-type-btn').forEach(b => b.classList.toggle('active', b.dataset.bgtype === bg.type));
    document.querySelectorAll('.bg-panel').forEach(p => { p.hidden = p.dataset.bgtype !== bg.type; });

    document.getElementById('bgColorInput').value = bg.type === 'color' ? bg.value : '#111111';
    document.getElementById('bgGradColor1').value = bg.color1 || '#3a6c8d';
    document.getElementById('bgGradColor2').value = bg.color2 || '#87b42b';
    document.getElementById('bgGradAngle').value = bg.angle || 90;
    document.getElementById('bgGradAngleVal').textContent = bg.angle || 90;

    const imgWrap = document.getElementById('bgImagePreviewWrap');
    if (bg.type === 'image' && bg.value) {
      document.getElementById('bgImagePreview').src = bg.value;
      imgWrap.hidden = false;
    } else {
      imgWrap.hidden = true;
    }

    const vidWrap = document.getElementById('bgVideoPreviewWrap');
    if (bg.type === 'video' && bg.value) {
      document.getElementById('bgVideoPreview').src = bg.value;
      vidWrap.hidden = false;
    } else {
      vidWrap.hidden = true;
    }
  }

  function buildGradientBg() {
    const c1 = document.getElementById('bgGradColor1').value;
    const c2 = document.getElementById('bgGradColor2').value;
    const angle = parseInt(document.getElementById('bgGradAngle').value, 10) || 0;
    return { type: 'gradient', color1: c1, color2: c2, angle: angle, value: 'linear-gradient(' + angle + 'deg, ' + c1 + ', ' + c2 + ')' };
  }

  function setBgType(type) {
    document.querySelectorAll('.bg-type-btn').forEach(b => b.classList.toggle('active', b.dataset.bgtype === type));
    document.querySelectorAll('.bg-panel').forEach(p => { p.hidden = p.dataset.bgtype !== type; });

    let bg;
    if (type === 'color') {
      bg = { type: 'color', value: document.getElementById('bgColorInput').value };
    } else if (type === 'gradient') {
      bg = buildGradientBg();
    } else if (type === 'image') {
      bg = SF.currentBackground.type === 'image' ? SF.currentBackground : { type: 'image', value: '' };
    } else if (type === 'none') {
      bg = { type: 'none' };
    } else {
      bg = SF.currentBackground.type === 'video' ? SF.currentBackground : { type: 'video', value: '' };
    }
    SF.currentBackground = bg;
    applyBackgroundVisual(bg);
    updateCurrentTabSwatch();
    scheduleSave();
  }

  async function uploadAsset(kind, file) {
    if (!file) return;
    setSaveStatus('Lade ' + (kind === 'video' ? 'Video' : 'Bild') + ' hoch…');
    const formData = new FormData();
    formData.append('id', SF.id);
    formData.append('kind', kind);
    formData.append('csrf_token', SF.csrfToken);
    formData.append('file', file);
    try {
      const res = await fetch('upload.php', { method: 'POST', body: formData });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || 'Upload fehlgeschlagen');
      SF.currentBackground = { type: kind, value: json.url };
      applyBackgroundVisual(SF.currentBackground);
      populateBackgroundControls(SF.currentBackground);
      updateCurrentTabSwatch();
      scheduleSave();
    } catch (e) {
      setSaveStatus('Fehler beim Hochladen', true);
      console.error(e);
    }
  }

  function updateCurrentTabSwatch() {
    const slide = SF.slides[SF.currentIndex];
    if (!slide) return;
    const item = document.querySelector('.filmstrip-item[data-id="' + slide.id + '"]');
    if (!item) return;
    item.style.setProperty('--fs-color', slideBgColor({ background: SF.currentBackground }));
  }

  function renderBrandPalette() {
    const el = document.getElementById('brandPalette');
    if (!el) return;
    el.innerHTML = SF.brandColors.map(c =>
      '<button type="button" class="brand-swatch" style="background:' + c.hex + '" data-color="' + c.hex + '" title="' + (c.name || c.hex) + '"></button>'
    ).join('');
    el.querySelectorAll('.brand-swatch').forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('bgColorInput').value = btn.dataset.color;
        SF.currentBackground = { type: 'color', value: btn.dataset.color };
        applyBackgroundVisual(SF.currentBackground);
        updateCurrentTabSwatch();
        scheduleSave();
      });
    });
  }

  async function duplicateSlide(index) {
    setSaveStatus('Speichere…');
    const res = await api('duplicate_slide', { index });
    SF.slides = res.slides;
    await renderSlideFilmstrip();
    setSaveStatus('Gespeichert');
    reloadPreviewWindow();
  }

  async function deleteSlide(index) {
    if (SF.slides.length <= 1) {
      alert('Die letzte verbleibende Folie kann nicht gelöscht werden.');
      return;
    }
    if (!confirm('Diese Folie wirklich löschen?')) return;
    const res = await api('delete_slide', { index });
    SF.slides = res.slides;
    if (SF.currentIndex >= SF.slides.length) SF.currentIndex = SF.slides.length - 1;
    loadSlideIntoStage(SF.currentIndex);
    await renderSlideFilmstrip();
    reloadPreviewWindow();
  }

  // ---------- Shape creation / conversion ----------
  function defaultIconColor() {
    return SF.brandColors[0]?.hex || '#3a6c8d';
  }

  function isIconSrc(src) {
    if (!src) return false;
    const match = String(src).match(/[?&]file=([^&]+)/);
    if (!match) return false;
    try {
      return /^ic_.*\.svg$/i.test(decodeURIComponent(match[1]));
    } catch {
      return /^ic_.*\.svg$/i.test(match[1]);
    }
  }

  function isClipartIconId(iconId) {
    return String(iconId || '').startsWith('oc:');
  }

  function assetFilenameFromSrc(src) {
    if (!src) return '';
    const match = String(src).match(/[?&]file=([^&]+)/);
    if (!match) return '';
    try {
      return decodeURIComponent(match[1]);
    } catch {
      return match[1];
    }
  }

  function isIconObject(obj) {
    if (isClipartIconId(obj?.iconId)) return false;
    return !!(obj?.iconId || isIconSrc(obj?.src));
  }

  function iconDisplaySrc(src, iconColor) {
    if (!src || !iconColor) return src;
    return src + (src.includes('?') ? '&' : '?') + 'color=' + encodeURIComponent(iconColor);
  }

  function loadImageAsync(node, src, iconColor) {
    const baseSrc = src || node.getAttr('src') || '';
    const color = iconColor ?? node.getAttr('iconColor');
    const displaySrc = (node.getAttr('iconId') || isIconSrc(baseSrc)) && color
      ? iconDisplaySrc(baseSrc, color)
      : baseSrc;
    if (!displaySrc) return;
    const img = new Image();
    img.onload = () => { node.image(img); if (SF.layer) SF.layer.batchDraw(); };
    img.src = displaySrc;
  }

  function applyShapeGradientVisual(node) {
    const type = node.getAttr('objType');
    const angle = node.getAttr('gradAngle') || 0;
    const c1 = node.getAttr('gradColor1') || '#3a6c8d';
    const c2 = node.getAttr('gradColor2') || '#87b42b';
    let w, h, ox = 0, oy = 0;
    if (type === 'ellipse') {
      w = node.radiusX() * 2; h = node.radiusY() * 2;
      ox = -node.radiusX(); oy = -node.radiusY();
    } else if (type === 'shape') {
      w = node.getAttr('baseW') || 100; h = node.getAttr('baseH') || 100;
    } else {
      w = node.width(); h = node.height();
    }
    const rad = (angle - 90) * Math.PI / 180;
    const dx = Math.cos(rad), dy = Math.sin(rad);
    const half = Math.sqrt(w * w + h * h) / 2;
    const cx = ox + w / 2, cy = oy + h / 2;
    node.fillLinearGradientStartPoint({ x: cx - dx * half, y: cy - dy * half });
    node.fillLinearGradientEndPoint({ x: cx + dx * half, y: cy + dy * half });
    node.fillLinearGradientColorStops([0, c1, 1, c2]);
    node.fillPriority('linear-gradient');
  }

  function setShapeFill(node, obj) {
    if (obj.fillType === 'gradient') {
      node.setAttr('fillType', 'gradient');
      node.setAttr('gradColor1', obj.gradColor1 || '#3a6c8d');
      node.setAttr('gradColor2', obj.gradColor2 || '#87b42b');
      node.setAttr('gradAngle', obj.gradAngle !== undefined ? obj.gradAngle : 90);
      node.fillEnabled(true);
      applyShapeGradientVisual(node);
    } else if (obj.fillType === 'none') {
      node.setAttr('fillType', 'none');
      node.fillEnabled(false);
    } else {
      node.setAttr('fillType', 'solid');
      node.fillEnabled(true);
      node.fillPriority('color');
      node.fill(obj.fill || '#cccccc');
    }
  }

  function createNode(obj) {
    const common = {
      id: obj.id,
      rotation: obj.rotation || 0,
      opacity: obj.opacity !== undefined ? obj.opacity : 1,
      draggable: SF.canEdit,
      animType: obj.animType || 'none',
      animOrder: obj.animOrder || 1,
      animAutoAdvance: obj.animAutoAdvance || 0,
      animDuration: obj.animDuration || 0,
    };
    if (obj.type === 'text') {
      const styleParts = [];
      if (obj.italic) styleParts.push('italic');
      if (obj.fontWeight === 'bold') styleParts.push('bold');
      const decoParts = [];
      if (obj.underline) decoParts.push('underline');
      if (obj.strikethrough) decoParts.push('line-through');
      const node = new Konva.Text(Object.assign({}, common, {
        x: obj.x, y: obj.y, width: obj.w,
        text: obj.text || 'Text',
        fontSize: obj.fontSize || 32,
        fontFamily: obj.fontFamily || 'Open Sans',
        fontStyle: styleParts.length ? styleParts.join(' ') : 'normal',
        textDecoration: decoParts.length ? decoParts.join(' ') : '',
        fill: obj.color || '#ffffff',
        align: obj.align || 'left',
        lineHeight: obj.lineHeight || 1.2,
      }));
      node.setAttr('objType', 'text');
      node.setAttr('lineHeight', obj.lineHeight || 1.2);
      node.setAttr('letterSpacing', obj.letterSpacing || 0);
      node.letterSpacing((obj.letterSpacing || 0) * (obj.fontSize || 32));
      node.setAttr('uppercase', !!obj.uppercase);
      node.setAttr('smallCaps', !!obj.smallCaps);
      node.setAttr('animPerLine', !!obj.animPerLine);
      return node;
    }
    if (obj.type === 'ellipse') {
      const rx = (obj.w || 160) / 2, ry = (obj.h || 120) / 2;
      const node = new Konva.Ellipse(Object.assign({}, common, {
        x: (obj.x || 0) + rx, y: (obj.y || 0) + ry,
        radiusX: rx, radiusY: ry,
        stroke: (obj.stroke && obj.stroke !== 'transparent') ? obj.stroke : undefined,
        strokeWidth: obj.strokeWidth || 0,
      }));
      node.setAttr('objType', 'ellipse');
      setShapeFill(node, obj);
      return node;
    }
    if (obj.type === 'image') {
      const node = new Konva.Image(Object.assign({}, common, {
        x: obj.x || 0, y: obj.y || 0,
        width: obj.w || 400, height: obj.h || 260,
        stroke: (obj.stroke && obj.stroke !== 'transparent') ? obj.stroke : undefined,
        strokeWidth: obj.strokeWidth || 0,
        strokeScaleEnabled: false,
      }));
      node.setAttr('objType', 'image');
      node.setAttr('src', obj.src || '');
      if (obj.iconId) node.setAttr('iconId', obj.iconId);
      if (obj.iconColor) node.setAttr('iconColor', obj.iconColor);
      loadImageAsync(node, obj.src, obj.iconColor);
      return node;
    }
    if (obj.type === 'video') {
      const w = obj.w || 400, h = obj.h || 260;
      const group = new Konva.Group(Object.assign({}, common, { x: obj.x || 0, y: obj.y || 0 }));
      const rect = new Konva.Rect({ width: w, height: h, fill: '#0c0e12', stroke: '#3a6c8d', strokeWidth: 1 });
      const label = new Konva.Text({
        width: w, height: h, text: '▶ Video', align: 'center', verticalAlign: 'middle',
        fill: '#8b92a3', fontSize: 22, fontFamily: 'Open Sans',
      });
      group.add(rect);
      group.add(label);
      group.setAttr('objType', 'video');
      group.setAttr('src', obj.src || '');
      group.setAttr('baseW', w);
      group.setAttr('baseH', h);
      group.setAttr('playTrigger', obj.playTrigger || 'manual');
      group.setAttr('playDelay', obj.playDelay || 0);
      group.setAttr('hideControls', !!obj.hideControls);
      group.setAttr('loop', !!obj.loop);
      return group;
    }
    if (obj.type === 'audio') {
      const w = obj.w || 280, h = obj.h || 56;
      const group = new Konva.Group(Object.assign({}, common, { x: obj.x || 0, y: obj.y || 0 }));
      const rect = new Konva.Rect({ width: w, height: h, fill: '#0c0e12', stroke: '#87b42b', strokeWidth: 1, cornerRadius: 6 });
      const label = new Konva.Text({
        width: w, height: h, text: '🔊 Audio', align: 'center', verticalAlign: 'middle',
        fill: '#8b92a3', fontSize: 16, fontFamily: 'Open Sans',
      });
      group.add(rect);
      group.add(label);
      group.setAttr('objType', 'audio');
      group.setAttr('src', obj.src || '');
      group.setAttr('baseW', w);
      group.setAttr('baseH', h);
      group.setAttr('playTrigger', obj.playTrigger || 'manual');
      group.setAttr('playDelay', obj.playDelay || 0);
      group.setAttr('hideControls', !!obj.hideControls);
      group.setAttr('loop', !!obj.loop);
      return group;
    }
    if (obj.type === 'shape') {
      const w = obj.w || 200, h = obj.h || 200;
      const shapeType = obj.shapeType || 'triangle';
      const isOpen = !!SHAPE_OPEN[shapeType];
      const node = new Konva.Line(Object.assign({}, common, {
        x: obj.x || 0, y: obj.y || 0,
        points: buildShapePoints(shapeType, w, h, obj),
        closed: !isOpen,
        stroke: isOpen ? ((obj.stroke && obj.stroke !== 'transparent') ? obj.stroke : '#ffffff') : ((obj.stroke && obj.stroke !== 'transparent') ? obj.stroke : undefined),
        strokeWidth: isOpen ? (obj.strokeWidth || 3) : (obj.strokeWidth || 0),
      }));
      node.setAttr('objType', 'shape');
      node.setAttr('shapeType', shapeType);
      node.setAttr('baseW', w);
      node.setAttr('baseH', h);
      node.setAttr('starPoints', obj.starPoints || 5);
      node.setAttr('arrowStyle', obj.arrowStyle || 'right');
      node.setAttr('bracketStyle', obj.bracketStyle || 'round-left');
      node.setAttr('bubbleStyle', obj.bubbleStyle || 'rect-left');
      if (!isOpen) setShapeFill(node, obj);
      return node;
    }
    if (obj.type === 'group') {
      const group = new Konva.Group(Object.assign({}, common, {
        x: obj.x || 0, y: obj.y || 0,
      }));
      group.setAttr('objType', 'objectGroup');
      (obj.children || []).forEach((childObj) => {
        const child = createNode(childObj);
        group.add(child);
        child.listening(false);
        child.draggable(false);
      });
      updateGroupHitRect(group);
      return group;
    }
    const node = new Konva.Rect(Object.assign({}, common, {
      x: obj.x || 0, y: obj.y || 0,
      width: obj.w || 200, height: obj.h || 120,
      stroke: (obj.stroke && obj.stroke !== 'transparent') ? obj.stroke : undefined,
      strokeWidth: obj.strokeWidth || 0,
    }));
    node.setAttr('objType', 'rect');
    setShapeFill(node, obj);
    return node;
  }

  function shapeFillData(node) {
    const fillType = node.getAttr('fillType') || 'solid';
    if (fillType === 'gradient') {
      return {
        fillType: 'gradient',
        gradColor1: node.getAttr('gradColor1') || '#3a6c8d',
        gradColor2: node.getAttr('gradColor2') || '#87b42b',
        gradAngle: node.getAttr('gradAngle') || 90,
      };
    }
    if (fillType === 'none') {
      return { fillType: 'none' };
    }
    return { fillType: 'solid', fill: node.fill() || '#cccccc' };
  }

  function nodeToObject(node) {
    const type = node.getAttr('objType');
    const base = {
      id: node.id(),
      type: type,
      rotation: Math.round(node.rotation()),
      opacity: Math.round((node.opacity() + Number.EPSILON) * 100) / 100,
      animType: node.getAttr('animType') || 'none',
      animOrder: node.getAttr('animOrder') || 1,
      animAutoAdvance: node.getAttr('animAutoAdvance') || 0,
      animDuration: node.getAttr('animDuration') || 0,
    };
    if (type === 'text') {
      const styleStr = node.fontStyle() || '';
      const decoStr = node.textDecoration() || '';
      return Object.assign(base, {
        x: Math.round(node.x()), y: Math.round(node.y()),
        w: Math.round(node.width() * node.scaleX()), h: Math.round(node.height() * node.scaleY()),
        text: node.text(),
        fontFamily: node.fontFamily(),
        fontSize: Math.round(node.fontSize()),
        fontWeight: styleStr.indexOf('bold') !== -1 ? 'bold' : 'normal',
        italic: styleStr.indexOf('italic') !== -1,
        underline: decoStr.indexOf('underline') !== -1,
        strikethrough: decoStr.indexOf('line-through') !== -1,
        uppercase: !!node.getAttr('uppercase'),
        smallCaps: !!node.getAttr('smallCaps'),
        animPerLine: !!node.getAttr('animPerLine'),
        lineHeight: node.getAttr('lineHeight') || node.lineHeight() || 1.2,
        letterSpacing: node.getAttr('letterSpacing') ?? (node.letterSpacing() / Math.max(1, node.fontSize())),
        color: node.fill(),
        align: node.align(),
      });
    }
    if (type === 'ellipse') {
      const rx = node.radiusX() * node.scaleX(), ry = node.radiusY() * node.scaleY();
      return Object.assign(base, {
        x: Math.round(node.x() - rx), y: Math.round(node.y() - ry),
        w: Math.round(rx * 2), h: Math.round(ry * 2),
        stroke: node.stroke() || 'transparent', strokeWidth: node.strokeWidth() || 0,
      }, shapeFillData(node));
    }
    if (type === 'image') {
      const data = {
        x: Math.round(node.x()), y: Math.round(node.y()),
        w: Math.round(node.width() * node.scaleX()), h: Math.round(node.height() * node.scaleY()),
        src: node.getAttr('src') || '',
        stroke: node.stroke() || 'transparent',
        strokeWidth: node.strokeWidth() || 0,
      };
      const iconId = node.getAttr('iconId');
      const iconColor = node.getAttr('iconColor');
      if (iconId) data.iconId = iconId;
      if (iconColor) data.iconColor = iconColor;
      return Object.assign(base, data);
    }
    if (type === 'video') {
      const baseW = node.getAttr('baseW') || 400, baseH = node.getAttr('baseH') || 260;
      return Object.assign(base, {
        x: Math.round(node.x()), y: Math.round(node.y()),
        w: Math.round(baseW * node.scaleX()), h: Math.round(baseH * node.scaleY()),
        src: node.getAttr('src') || '',
        playTrigger: node.getAttr('playTrigger') || 'manual',
        playDelay: node.getAttr('playDelay') || 0,
        hideControls: !!node.getAttr('hideControls'),
        loop: !!node.getAttr('loop'),
      });
    }
    if (type === 'audio') {
      const baseW = node.getAttr('baseW') || 280, baseH = node.getAttr('baseH') || 56;
      return Object.assign(base, {
        x: Math.round(node.x()), y: Math.round(node.y()),
        w: Math.round(baseW * node.scaleX()), h: Math.round(baseH * node.scaleY()),
        src: node.getAttr('src') || '',
        playTrigger: node.getAttr('playTrigger') || 'manual',
        playDelay: node.getAttr('playDelay') || 0,
        hideControls: !!node.getAttr('hideControls'),
        loop: !!node.getAttr('loop'),
      });
    }
    if (type === 'objectGroup') {
      const parent = node.getParent();
      const rect = node.getClientRect({ relativeTo: parent || SF.layer });
      return {
        id: node.id(),
        type: 'group',
        x: Math.round(node.x()),
        y: Math.round(node.y()),
        w: Math.max(1, Math.round(rect.width)),
        h: Math.max(1, Math.round(rect.height)),
        rotation: Math.round(node.rotation()),
        opacity: Math.round((node.opacity() + Number.EPSILON) * 100) / 100,
        animType: node.getAttr('animType') || 'none',
        animOrder: node.getAttr('animOrder') || 1,
        animAutoAdvance: node.getAttr('animAutoAdvance') || 0,
        animDuration: node.getAttr('animDuration') || 0,
        children: getGroupContentChildren(node).map((child) => nodeToObject(child)),
      };
    }
    if (type === 'shape') {
      const baseW = node.getAttr('baseW') || 200, baseH = node.getAttr('baseH') || 200;
      return Object.assign(base, {
        x: Math.round(node.x()), y: Math.round(node.y()),
        w: Math.round(baseW * node.scaleX()), h: Math.round(baseH * node.scaleY()),
        shapeType: node.getAttr('shapeType') || 'triangle',
        starPoints: node.getAttr('starPoints') || 5,
        arrowStyle: node.getAttr('arrowStyle') || 'right',
        bracketStyle: node.getAttr('bracketStyle') || 'round-left',
        bubbleStyle: node.getAttr('bubbleStyle') || 'rect-left',
        stroke: node.stroke() || 'transparent', strokeWidth: node.strokeWidth() || 0,
      }, shapeFillData(node));
    }
    return Object.assign(base, {
      x: Math.round(node.x()), y: Math.round(node.y()),
      w: Math.round(node.width() * node.scaleX()), h: Math.round(node.height() * node.scaleY()),
      stroke: node.stroke() || 'transparent', strokeWidth: node.strokeWidth() || 0,
    }, shapeFillData(node));
  }

  function normalizeScale(node) {
    const type = node.getAttr('objType');
    if (type === 'ellipse') {
      node.radiusX(Math.max(5, node.radiusX() * node.scaleX()));
      node.radiusY(Math.max(5, node.radiusY() * node.scaleY()));
      node.scaleX(1); node.scaleY(1);
      if (node.getAttr('fillType') === 'gradient') applyShapeGradientVisual(node);
    } else if (type === 'rect') {
      node.width(Math.max(5, node.width() * node.scaleX()));
      node.height(Math.max(5, node.height() * node.scaleY()));
      node.scaleX(1); node.scaleY(1);
      if (node.getAttr('fillType') === 'gradient') applyShapeGradientVisual(node);
    } else if (type === 'image') {
      node.width(Math.max(5, node.width() * node.scaleX()));
      node.height(Math.max(5, node.height() * node.scaleY()));
      node.scaleX(1); node.scaleY(1);
    } else if (type === 'text') {
      node.width(Math.max(20, node.width() * node.scaleX()));
      node.scaleX(1); node.scaleY(1);
    } else if (type === 'shape') {
      const newW = Math.max(10, (node.getAttr('baseW') || 100) * node.scaleX());
      const newH = Math.max(10, (node.getAttr('baseH') || 100) * node.scaleY());
      node.points(buildShapePoints(node.getAttr('shapeType'), newW, newH, nodeShapeCfg(node)));
      node.setAttr('baseW', newW);
      node.setAttr('baseH', newH);
      node.scaleX(1); node.scaleY(1);
      if (node.getAttr('fillType') === 'gradient') applyShapeGradientVisual(node);
    }
    // 'video' (Konva.Group): Skalierung bewusst NICHT zurücksetzen,
    // w/h werden beim Speichern aus baseW/baseH * scale berechnet.
  }

  function scaleChildNode(child, sx, sy) {
    const type = child.getAttr('objType');
    if (type === 'ellipse') {
      child.radiusX(Math.max(5, child.radiusX() * sx));
      child.radiusY(Math.max(5, child.radiusY() * sy));
      child.scaleX(1); child.scaleY(1);
      if (child.getAttr('fillType') === 'gradient') applyShapeGradientVisual(child);
    } else if (type === 'rect') {
      child.width(Math.max(5, child.width() * sx));
      child.height(Math.max(5, child.height() * sy));
      child.scaleX(1); child.scaleY(1);
      if (child.getAttr('fillType') === 'gradient') applyShapeGradientVisual(child);
    } else if (type === 'image') {
      child.width(Math.max(5, child.width() * sx));
      child.height(Math.max(5, child.height() * sy));
      child.scaleX(1); child.scaleY(1);
    } else if (type === 'text') {
      child.width(Math.max(20, child.width() * sx));
      child.scaleX(1); child.scaleY(1);
    } else if (type === 'shape') {
      const newW = Math.max(10, (child.getAttr('baseW') || 100) * sx);
      const newH = Math.max(10, (child.getAttr('baseH') || 100) * sy);
      child.points(buildShapePoints(child.getAttr('shapeType'), newW, newH, nodeShapeCfg(child)));
      child.setAttr('baseW', newW);
      child.setAttr('baseH', newH);
      child.scaleX(1); child.scaleY(1);
      if (child.getAttr('fillType') === 'gradient') applyShapeGradientVisual(child);
    } else if (type === 'video' || type === 'audio') {
      child.setAttr('baseW', Math.max(10, (child.getAttr('baseW') || 100) * sx));
      child.setAttr('baseH', Math.max(10, (child.getAttr('baseH') || 100) * sy));
      const rect = child.findOne('Rect');
      const label = child.findOne('Text');
      if (rect) { rect.width(child.getAttr('baseW')); rect.height(child.getAttr('baseH')); }
      if (label) { label.width(child.getAttr('baseW')); label.height(child.getAttr('baseH')); }
      child.scaleX(1); child.scaleY(1);
    } else if (type === 'objectGroup') {
      child.getChildren().forEach((grand) => {
        grand.x(grand.x() * sx);
        grand.y(grand.y() * sy);
        scaleChildNode(grand, sx, sy);
      });
      child.scaleX(1); child.scaleY(1);
    }
  }

  function normalizeGroupScale(group) {
    const sx = group.scaleX(), sy = group.scaleY();
    if (Math.abs(sx - 1) < 0.0001 && Math.abs(sy - 1) < 0.0001) return;
    group.getChildren().forEach((child) => {
      child.x(child.x() * sx);
      child.y(child.y() * sy);
      if (child.name() === 'sf-group-hit') return;
      scaleChildNode(child, sx, sy);
    });
    group.scaleX(1);
    group.scaleY(1);
    updateGroupHitRect(group);
  }

  function isObjectGroup(node) {
    return node && node.getAttr('objType') === 'objectGroup';
  }

  function getGroupContentChildren(group) {
    return group.getChildren().filter((c) => c.name() !== 'sf-group-hit');
  }

  function updateGroupHitRect(group) {
    if (!isObjectGroup(group)) return;
    const content = getGroupContentChildren(group);
    if (!content.length) return;

    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    content.forEach((child) => {
      const box = child.getClientRect({ relativeTo: group, skipStroke: false });
      minX = Math.min(minX, box.x);
      minY = Math.min(minY, box.y);
      maxX = Math.max(maxX, box.x + box.width);
      maxY = Math.max(maxY, box.y + box.height);
    });

    let hit = group.findOne('.sf-group-hit');
    if (!hit) {
      hit = new Konva.Rect({
        name: 'sf-group-hit',
        fill: 'rgba(0,0,0,0.001)',
        listening: true,
        draggable: false,
      });
      group.add(hit);
      hit.moveToBottom();
    }
    hit.setAttrs({
      x: minX,
      y: minY,
      width: Math.max(1, maxX - minX),
      height: Math.max(1, maxY - minY),
    });
  }

  function isTopLevelNode(node) {
    return node && node.name() === 'sf-node' && node.getParent() === SF.layer;
  }

  function getTopLevelNodes() {
    if (!SF.layer) return [];
    return SF.layer.getChildren().filter((n) => n.name() === 'sf-node');
  }

  function rectsIntersect(a, b) {
    return !(a.x + a.width < b.x || b.x + b.width < a.x || a.y + a.height < b.y || b.y + b.height < a.y);
  }

  function getNodesBounds(nodes, relativeTo) {
    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    nodes.forEach((node) => {
      const box = node.getClientRect({ relativeTo: relativeTo || SF.layer });
      minX = Math.min(minX, box.x);
      minY = Math.min(minY, box.y);
      maxX = Math.max(maxX, box.x + box.width);
      maxY = Math.max(maxY, box.y + box.height);
    });
    return { minX, minY, width: maxX - minX, height: maxY - minY };
  }

  function decomposeRelativeTo(node, reference) {
    const matrix = node.getAbsoluteTransform().copy();
    matrix.multiply(reference.getAbsoluteTransform().copy().invert());
    return matrix.decompose();
  }

  function bakeNodeScale(node, sx, sy) {
    if (Math.abs(sx - 1) < 0.0001 && Math.abs(sy - 1) < 0.0001) {
      node.scaleX(1);
      node.scaleY(1);
      return;
    }
    const type = node.getAttr('objType');
    if (type === 'text') {
      node.width(Math.max(20, node.width() * sx));
      node.scaleX(1);
      node.scaleY(1);
    } else {
      scaleChildNode(node, sx, sy);
    }
  }

  function absoluteTransformToParent(node, newParent) {
    const absPos = node.getAbsolutePosition();
    const absRot = node.getAbsoluteRotation();
    const sx = node.scaleX();
    const sy = node.scaleY();
    node.moveTo(newParent);
    const inv = newParent.getAbsoluteTransform().copy().invert();
    node.position(inv.point(absPos));
    node.rotation(absRot - newParent.getAbsoluteRotation());
    node.scaleX(sx);
    node.scaleY(sy);
  }

  function absoluteTransformToLayer(node) {
    const absPos = node.getAbsolutePosition();
    const absRot = node.getAbsoluteRotation();
    const scale = decomposeRelativeTo(node, SF.layer);
    node.moveTo(SF.layer);
    const layerInv = SF.layer.getAbsoluteTransform().copy().invert();
    node.position(layerInv.point(absPos));
    node.rotation(absRot);
    bakeNodeScale(node, scale.scaleX, scale.scaleY);
  }

  function groupSelectedNodes() {
    if (!SF.canEdit) return;
    const nodes = SF.selectedNodes.filter(isTopLevelNode);
    if (nodes.length < 2) return;

    const bounds = getNodesBounds(nodes, SF.layer);
    const group = new Konva.Group({
      id: 'o' + Math.random().toString(16).slice(2, 10),
      x: bounds.minX,
      y: bounds.minY,
      draggable: true,
      rotation: 0,
      opacity: 1,
      animType: 'none',
      animOrder: 1,
      animAutoAdvance: 0,
      animDuration: 0,
    });
    group.setAttr('objType', 'objectGroup');
    group.name('sf-node');

    const minZ = Math.min(...nodes.map((n) => n.zIndex()));
    SF.layer.add(group);
    group.zIndex(minZ);

    nodes.slice().sort((a, b) => a.zIndex() - b.zIndex()).forEach((node) => {
      removeAnimationBadge(node);
      absoluteTransformToParent(node, group);
      node.name('sf-group-child');
      node.listening(false);
      node.draggable(false);
    });

    updateGroupHitRect(group);
    bindNodeEvents(group);
    if (SF.transformer) SF.transformer.moveToTop();
    selectNodes([group]);
    refreshCanvas();
    scheduleSave();
  }

  function ungroupSelected() {
    if (!SF.canEdit) return;
    const group = SF.selectedNodes.length === 1 ? SF.selectedNodes[0] : null;
    if (!isObjectGroup(group)) return;

    const children = getGroupContentChildren(group);
    const newNodes = [];
    children.forEach((child) => {
      absoluteTransformToLayer(child);
      child.name('sf-node');
      child.listening(true);
      child.draggable(true);
      bindNodeEvents(child);
      newNodes.push(child);
    });
    removeAnimationBadge(group);
    group.destroy();
    selectNodes(newNodes);
    refreshCanvas();
    scheduleSave();
  }

  function removeAnimationBadge(node) {
    if (node._sfBadge) { node._sfBadge.destroy(); node._sfBadge = null; }
  }

  function updateAnimationBadge(node) {
    removeAnimationBadge(node);
    const animType = node.getAttr('animType') || 'none';
    if (animType === 'none' || !SF.layer) return;
    const box = node.getClientRect({ relativeTo: SF.layer });
    const badge = new Konva.Group({ x: box.x - 11, y: box.y - 11, listening: false, name: 'sf-badge' });
    badge.add(new Konva.Circle({ x: 11, y: 11, radius: 11, fill: '#3a6c8d', stroke: '#ffffff', strokeWidth: 1 }));
    badge.add(new Konva.Text({
      x: 0, y: 0, width: 22, height: 22,
      text: String(node.getAttr('animOrder') || 1),
      fontSize: 12, fontFamily: 'JetBrains Mono', fill: '#ffffff',
      align: 'center', verticalAlign: 'middle',
    }));
    SF.layer.add(badge);
    badge.moveToTop();
    if (SF.transformer) SF.transformer.moveToTop();
    node._sfBadge = badge;
  }

  const SNAP_THRESHOLD_PX = 6; // Bildschirm-Pixel

  function clearSnapGuides() {
    if (SF.snapLineX) { SF.snapLineX.destroy(); SF.snapLineX = null; }
    if (SF.snapLineY) { SF.snapLineY.destroy(); SF.snapLineY = null; }
  }

  function getSafeMargin() {
    const margin = Math.max(0, parseInt(SF.meta.safe_margin, 10) || 0);
    if (margin <= 0 || margin * 2 >= SF.meta.width || margin * 2 >= SF.meta.height) return 0;
    return margin;
  }

  function drawSafeMarginGuide() {
    if (SF.marginGuide) { SF.marginGuide.destroy(); SF.marginGuide = null; }
    const margin = getSafeMargin();
    if (margin <= 0) return;
    SF.marginGuide = new Konva.Rect({
      x: margin, y: margin,
      width: SF.meta.width - margin * 2, height: SF.meta.height - margin * 2,
      stroke: 'rgba(255,255,255,0.25)', strokeWidth: 1, dash: [4, 4],
      listening: false, name: 'sf-guide sf-margin-guide',
    });
    SF.layer.add(SF.marginGuide);
    refreshCanvas();
  }

  function getSnapTargets(excludeNodes) {
    const exclude = new Set(excludeNodes || []);
    const targetXs = [0, SF.meta.width / 2, SF.meta.width];
    const targetYs = [0, SF.meta.height / 2, SF.meta.height];
    const margin = getSafeMargin();
    if (margin > 0) {
      targetXs.push(margin, SF.meta.width - margin);
      targetYs.push(margin, SF.meta.height - margin);
    }
    getTopLevelNodes().forEach((n) => {
      if (exclude.has(n)) return;
      const b = n.getClientRect({ relativeTo: SF.layer });
      targetXs.push(b.x, b.x + b.width / 2, b.x + b.width);
      targetYs.push(b.y, b.y + b.height / 2, b.y + b.height);
    });
    return { targetXs, targetYs };
  }

  function drawSnapGuideLines(snappedX, snappedY) {
    clearSnapGuides();
    const guideExtent = Math.max(SF.meta.width, SF.meta.height) * 2;
    if (snappedX !== null) {
      SF.snapLineX = new Konva.Line({
        points: [snappedX, -guideExtent, snappedX, guideExtent],
        stroke: '#e8a33d', strokeWidth: 1, dash: [5, 4], listening: false, name: 'sf-guide',
      });
      SF.layer.add(SF.snapLineX);
      SF.snapLineX.moveToTop();
    }
    if (snappedY !== null) {
      SF.snapLineY = new Konva.Line({
        points: [-guideExtent, snappedY, guideExtent, snappedY],
        stroke: '#e8a33d', strokeWidth: 1, dash: [5, 4], listening: false, name: 'sf-guide',
      });
      SF.layer.add(SF.snapLineY);
      SF.snapLineY.moveToTop();
    }
    if (SF.transformer) SF.transformer.moveToTop();
  }

  function snapTransformBox(oldBox, newBox, excludeNodes) {
    const threshold = SNAP_THRESHOLD_PX / (SF.currentZoom || 1);
    const { targetXs, targetYs } = getSnapTargets(excludeNodes);
    const box = Object.assign({}, newBox);
    const eps = 0.01;

    const oldLeft = oldBox.x;
    const oldRight = oldBox.x + oldBox.width;
    const oldTop = oldBox.y;
    const oldBottom = oldBox.y + oldBox.height;

    const moveLeft = Math.abs(box.x - oldLeft) > eps;
    const moveRight = Math.abs((box.x + box.width) - oldRight) > eps;
    const moveTop = Math.abs(box.y - oldTop) > eps;
    const moveBottom = Math.abs((box.y + box.height) - oldBottom) > eps;

    let snappedX = null;
    let snappedY = null;

    if (moveLeft && !moveRight) {
      for (const tx of targetXs) {
        if (Math.abs(box.x - tx) <= threshold) {
          box.x = tx;
          box.width = oldRight - tx;
          snappedX = tx;
          break;
        }
      }
    } else if (moveRight && !moveLeft) {
      const right = box.x + box.width;
      for (const tx of targetXs) {
        if (Math.abs(right - tx) <= threshold) {
          box.width = tx - box.x;
          snappedX = tx;
          break;
        }
      }
    } else if (moveLeft && moveRight) {
      const cx = box.x + box.width / 2;
      for (const tx of targetXs) {
        if (Math.abs(cx - tx) <= threshold) {
          box.x = tx - box.width / 2;
          snappedX = tx;
          break;
        }
      }
    }

    if (moveTop && !moveBottom) {
      for (const ty of targetYs) {
        if (Math.abs(box.y - ty) <= threshold) {
          box.y = ty;
          box.height = oldBottom - ty;
          snappedY = ty;
          break;
        }
      }
    } else if (moveBottom && !moveTop) {
      const bottom = box.y + box.height;
      for (const ty of targetYs) {
        if (Math.abs(bottom - ty) <= threshold) {
          box.height = ty - box.y;
          snappedY = ty;
          break;
        }
      }
    } else if (moveTop && moveBottom) {
      const cy = box.y + box.height / 2;
      for (const ty of targetYs) {
        if (Math.abs(cy - ty) <= threshold) {
          box.y = ty - box.height / 2;
          snappedY = ty;
          break;
        }
      }
    }

    box.width = Math.max(5, box.width);
    box.height = Math.max(5, box.height);
    drawSnapGuideLines(snappedX, snappedY);
    refreshCanvas();
    return box;
  }

  function updateSnapGuides(node) {
    const threshold = SNAP_THRESHOLD_PX / (SF.currentZoom || 1);
    const box = node.getClientRect({ relativeTo: SF.layer });
    const selfXs = [box.x, box.x + box.width / 2, box.x + box.width];
    const selfYs = [box.y, box.y + box.height / 2, box.y + box.height];
    const { targetXs, targetYs } = getSnapTargets([node]);

    let dx = 0, snappedX = null;
    for (const sx of selfXs) {
      for (const tx of targetXs) {
        if (Math.abs(sx - tx) <= threshold) { dx = tx - sx; snappedX = tx; break; }
      }
      if (snappedX !== null) break;
    }
    let dy = 0, snappedY = null;
    for (const sy of selfYs) {
      for (const ty of targetYs) {
        if (Math.abs(sy - ty) <= threshold) { dy = ty - sy; snappedY = ty; break; }
      }
      if (snappedY !== null) break;
    }

    if (snappedX !== null) node.x(node.x() + dx);
    if (snappedY !== null) node.y(node.y() + dy);

    drawSnapGuideLines(snappedX, snappedY);
    refreshCanvas();
  }

  function bindNodeEvents(node) {
    if (!SF.canEdit) return;
    node.on('click tap', (e) => {
      e.cancelBubble = true;
      if (e.evt.shiftKey) toggleSelectNode(node);
      else selectNodes([node]);
    });
    node.on('dragmove', () => {
      if (isObjectGroup(node)) {
        node.getChildren().forEach((child) => updateAnimationBadge(child));
      } else {
        updateSnapGuides(node);
      }
    });
    node.on('dragend', () => {
      clearSnapGuides();
      if (isObjectGroup(node)) {
        updateGroupHitRect(node);
        getGroupContentChildren(node).forEach((child) => updateAnimationBadge(child));
      } else {
        updateAnimationBadge(node);
      }
      refreshCanvas();
      scheduleSave();
      refreshPropsPanel();
    });
    node.on('transformend', () => {
      clearSnapGuides();
      if (isObjectGroup(node)) {
        normalizeGroupScale(node);
        updateGroupHitRect(node);
        getGroupContentChildren(node).forEach((child) => updateAnimationBadge(child));
      } else {
        normalizeScale(node);
        updateAnimationBadge(node);
      }
      scheduleSave();
      refreshPropsPanel();
    });
    if (node.getAttr('objType') === 'text') {
      node.on('dblclick dbltap', () => editTextInline(node));
    }
  }

  function editTextInline(node) {
    selectNode(node);
    const scale = SF.stage.scaleX();
    const stageBox = SF.stage.container().getBoundingClientRect();
    const pos = node.absolutePosition();

    const textarea = document.createElement('textarea');
    document.body.appendChild(textarea);
    textarea.value = node.text();
    applySpellcheckAttrs(textarea);
    Object.assign(textarea.style, {
      position: 'fixed',
      top: stageBox.top + pos.y + 'px',
      left: stageBox.left + pos.x + 'px',
      width: (node.width() * node.scaleX() * scale) + 'px',
      height: (node.height() * node.scaleY() * scale) + 'px',
      fontSize: (node.fontSize() * scale) + 'px',
      fontFamily: node.fontFamily() + ', sans-serif',
      fontWeight: node.fontStyle() === 'bold' ? 'bold' : 'normal',
      color: node.fill(),
      textAlign: node.align(),
      lineHeight: String(node.getAttr('lineHeight') || node.lineHeight() || 1.2),
      letterSpacing: String((node.getAttr('letterSpacing') ?? (node.letterSpacing() / Math.max(1, node.fontSize()))) + 'em'),
      border: '1px dashed #3a6c8d',
      padding: '0',
      margin: '0',
      overflow: 'hidden',
      background: 'rgba(0,0,0,0.55)',
      outline: 'none',
      resize: 'none',
      zIndex: 1000,
      transformOrigin: 'left top',
      transform: node.rotation() ? 'rotate(' + node.rotation() + 'deg)' : 'none',
      whiteSpace: 'pre-wrap',
    });
    node.hide();
    if (SF.transformer) SF.transformer.hide();
    refreshCanvas();
    textarea.focus();
    textarea.select();

    function autoGrowOverlay() {
      const minH = node.height() * node.scaleY() * scale;
      textarea.style.height = 'auto';
      textarea.style.height = Math.max(minH, textarea.scrollHeight) + 'px';
    }
    textarea.addEventListener('input', autoGrowOverlay);
    setTimeout(autoGrowOverlay, 0);

    let done = false;
    function finish(save) {
      if (done) return;
      done = true;
      if (save) node.text(textarea.value);
      node.show();
      if (SF.transformer) SF.transformer.show();
      document.body.removeChild(textarea);
      document.removeEventListener('mousedown', onOutsideClick);
      refreshCanvas();
      if (save) { scheduleSave(); refreshPropsPanel(); }
    }
    function onOutsideClick(e) {
      if (e.target !== textarea) finish(true);
    }
    textarea.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { e.preventDefault(); finish(false); }
    });
    textarea.addEventListener('blur', () => finish(true));
    setTimeout(() => document.addEventListener('mousedown', onOutsideClick), 0);
  }

  // ---------- Selection & properties panel ----------
  const LAYER_ICON_SVG = {
    text: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h10M4 18h7"/></svg>',
    rect: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="6" width="16" height="12" rx="1.5"/></svg>',
    ellipse: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="12" rx="8" ry="6"/></svg>',
    shape: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M12 3l8 15H4z"/></svg>',
    image: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10.5" r="1.5"/><path d="M21 15l-5-5-9 9"/></svg>',
    video: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M10 9l6 3-6 3V9z"/></svg>',
    audio: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 18V6l10-2v14"/><circle cx="7" cy="18" r="3"/><circle cx="17" cy="16" r="3"/></svg>',
    objectGroup: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
  };

  const ALIGN_EDGE_ICONS = {
    top: '<svg viewBox="0 0 32 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5h26"/><rect x="6" y="5" width="6" height="9" rx="2"/><rect x="15" y="5" width="6" height="13" rx="2"/></svg>',
    vcenter: '<svg viewBox="0 0 32 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h26"/><rect x="6" y="8" width="6" height="8" rx="2"/><rect x="15" y="5.5" width="6" height="13" rx="2"/></svg>',
    bottom: '<svg viewBox="0 0 32 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 19h26"/><rect x="6" y="10" width="6" height="9" rx="2"/><rect x="15" y="6" width="6" height="13" rx="2"/></svg>',
    left: '<svg viewBox="0 0 32 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3v18"/><rect x="5" y="6" width="9" height="6" rx="2"/><rect x="5" y="14" width="13" height="6" rx="2"/></svg>',
    hcenter: '<svg viewBox="0 0 32 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3v18"/><rect x="8" y="6" width="9" height="6" rx="2"/><rect x="6" y="14" width="13" height="6" rx="2"/></svg>',
    right: '<svg viewBox="0 0 32 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M27 3v18"/><rect x="13" y="6" width="14" height="6" rx="2"/><rect x="9" y="14" width="18" height="6" rx="2"/></svg>',
  };

  function alignGridButtonsHtml() {
    const edges = [
      ['top', I.alTop],
      ['left', I.alLeft],
      ['vcenter', I.alVCenter],
      ['hcenter', I.alHCenter],
      ['bottom', I.alBottom],
      ['right', I.alRight],
    ];
    return '<div class="align-grid">' + edges.map(([edge, label]) => {
      const hint = I.alignClickHint ? (' — ' + I.alignClickHint) : '';
      return '<button type="button" class="align-grid-btn" data-align-edge="' + edge + '" title="' + escapeHtml(label + hint) + '" aria-label="' + escapeHtml(label + hint) + '">' +
        '<span class="align-grid-btn-icon" aria-hidden="true">' + (ALIGN_EDGE_ICONS[edge] || '') + '</span>' +
        '<span class="align-grid-btn-label">' + escapeHtml(label) + '</span>' +
      '</button>';
    }).join('') + '</div>';
  }

  function getSlideLayerNodes() {
    if (!SF.layer) return [];
    return getTopLevelNodes().slice().reverse();
  }

  function findNodeById(id) {
    if (!SF.layer || !id) return null;
    return getTopLevelNodes().find((n) => n.id() === id) || null;
  }

  function layerTypeLabel(obj) {
    if (obj.type === 'group') return I.typeGroup || 'Gruppe';
    if (obj.type === 'shape') return SHAPE_LABELS[obj.shapeType] || I.typeShape;
    return ({
      text: I.typeText,
      rect: I.typeRect,
      ellipse: I.typeEllipse,
      image: I.typeImage,
      video: I.typeVideo,
      audio: I.typeAudio,
    }[obj.type] || obj.type);
  }

  function humanizeAssetFilename(filename) {
    if (!filename) return '';
    let base = filename.replace(/\.[^.]+$/, '').replace(/_nobg$/, '');

    const ic = base.match(/^ic_([a-z0-9][a-z0-9-]*)_([a-z0-9][a-z0-9._-]*)_[a-z0-9]+$/i);
    if (ic) return ic[1] + ':' + ic[2];

    const oc = base.match(/^oc_\d+_(.+?)_[a-z0-9]+$/i);
    if (oc) return oc[1].replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

    const px = base.match(/^px(\d+)_/i);
    if (px) return 'Pixabay ' + px[1];

    if (/^[a-f0-9]{8,}$/i.test(base)) return I.typeImage || 'Bild';

    const plain = base.replace(/^img_|^pptx_img|^odp_img|^pdfpage\d+_/i, '').replace(/[-_]+/g, ' ').trim();
    if (!plain) return I.typeImage || 'Bild';
    return plain.replace(/\b\w/g, (c) => c.toUpperCase());
  }

  function layerItemLabel(node) {
    const obj = nodeToObject(node);
    if (obj.type === 'group') {
      const count = (obj.children || []).length;
      return (I.typeGroup || 'Gruppe') + ' (' + count + ')';
    }
    if (obj.type === 'text') {
      const preview = (obj.text || '').replace(/\s+/g, ' ').trim();
      if (!preview) return I.typeText;
      return preview.length > 30 ? preview.slice(0, 30) + '…' : preview;
    }
    if ((obj.type === 'image' || obj.type === 'video' || obj.type === 'audio') && obj.src) {
      if (obj.iconId && !isClipartIconId(obj.iconId)) {
        const label = obj.iconId;
        return label.length > 36 ? label.slice(0, 36) + '…' : label;
      }
      const filename = assetFilenameFromSrc(obj.src);
      if (filename) {
        const label = humanizeAssetFilename(filename);
        if (label) return label.length > 36 ? label.slice(0, 36) + '…' : label;
      }
    }
    return layerTypeLabel(obj);
  }

  function layerIconHtml(node) {
    const type = node.getAttr('objType') || 'rect';
    return LAYER_ICON_SVG[type] || LAYER_ICON_SVG.rect;
  }

  function syncLayersPropsLayout() {
    document.getElementById('propsPanelWrap')?.classList.toggle('layers-menu-open', !!SF.layersPanelOpen);
  }

  function renderLayersPanel() {
    const panel = document.getElementById('propsLayersPanel');
    if (!panel || !SF.canEdit) return;

    const nodes = getSlideLayerNodes();
    const isOpen = SF.layersPanelOpen !== false;
    let html = '<div class="props-layers-accordion' + (isOpen ? ' open' : '') + '" id="propsLayersAccordion">' +
      '<button type="button" class="props-layers-header" id="propsLayersToggle">' +
      '<span>' + escapeHtml(I.layersTitle || 'Ebenen') + '</span>' +
      '<span class="props-layers-count">' + nodes.length + '</span>' +
      '<span class="props-accordion-chevron">▾</span></button>' +
      '<div class="props-layers-body">';

    if (!nodes.length) {
      html += '<div class="props-layers-empty">' + escapeHtml(I.layersEmpty || '') + '</div>';
    } else {
      html += '<p class="props-layers-hint">' + escapeHtml(I.layersHint || '') + '</p>';
      html += '<ul class="props-layers-list">';
      nodes.forEach((node) => {
        const id = node.id();
        const active = SF.selectedNodes.includes(node);
        html += '<li><button type="button" class="props-layer-item' + (active ? ' active' : '') + '" data-layer-id="' + escapeHtml(id) + '">' +
          '<span class="props-layer-icon" aria-hidden="true">' + layerIconHtml(node) + '</span>' +
          '<span class="props-layer-name">' + escapeHtml(layerItemLabel(node)) + '</span>' +
          '</button></li>';
      });
      html += '</ul>';
    }

    html += '</div></div>';
    panel.innerHTML = html;

    document.getElementById('propsLayersToggle')?.addEventListener('click', () => {
      SF.layersPanelOpen = !SF.layersPanelOpen;
      localStorage.setItem('sf_layers_open', SF.layersPanelOpen ? '1' : '0');
      document.getElementById('propsLayersAccordion')?.classList.toggle('open', SF.layersPanelOpen);
      if (SF.layersPanelOpen) {
        SF.activeFormatGroup = null;
        document.querySelectorAll('.props-accordion[data-accordion-name="textProps"] .props-accordion-group').forEach((g) => {
          g.classList.remove('open');
        });
      }
      syncLayersPropsLayout();
    });

    panel.querySelectorAll('[data-layer-id]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const node = findNodeById(btn.dataset.layerId);
        if (node) {
          if (e.shiftKey) toggleSelectNode(node);
          else selectNodes([node]);
        }
      });
    });
    syncLayersPropsLayout();
  }

  function selectNodes(nodes) {
    const list = (Array.isArray(nodes) ? nodes : [nodes]).filter(Boolean);
    SF.selectedNodes = list;
    SF.selectedNode = list.length === 1 ? list[0] : (list.length ? list[list.length - 1] : null);
    SF.layersPanelOpen = list.length !== 1;
    localStorage.setItem('sf_layers_open', list.length === 1 ? '0' : '1');
    if (SF.transformer) {
      if (!list.length) {
        SF.transformer.nodes([]);
      } else if (list.length === 1) {
        const type = list[0].getAttr('objType');
        SF.transformer.resizeEnabled(true);
        SF.transformer.enabledAnchors(type === 'text'
          ? ['middle-left', 'middle-right']
          : ['top-left', 'top-center', 'top-right', 'middle-left', 'middle-right', 'bottom-left', 'bottom-center', 'bottom-right']);
        SF.transformer.nodes([list[0]]);
      } else {
        SF.transformer.enabledAnchors(['top-left', 'top-center', 'top-right', 'middle-left', 'middle-right', 'bottom-left', 'bottom-center', 'bottom-right']);
        SF.transformer.nodes(list);
      }
    }
    SF.layer.draw();
    refreshPropsPanel();
    renderLayersPanel();
    updateSelectionActionButtons();
  }

  function selectNode(node) {
    selectNodes([node]);
  }

  function toggleSelectNode(node) {
    if (!node) return;
    const idx = SF.selectedNodes.indexOf(node);
    if (idx >= 0) {
      const next = SF.selectedNodes.filter((n) => n !== node);
      selectNodes(next);
    } else {
      selectNodes([...SF.selectedNodes, node]);
    }
  }

  function deselect() {
    SF.selectedNodes = [];
    SF.selectedNode = null;
    if (SF.transformer) { SF.transformer.nodes([]); SF.layer.draw(); }
    SF.layersPanelOpen = true;
    localStorage.setItem('sf_layers_open', '1');
    renderPropsPanel(null);
    renderLayersPanel();
    updateSelectionActionButtons();
  }

  function updateSelectionActionButtons() {
    const count = SF.selectedNodes.length;
    const hasSingle = count === 1 && SF.canEdit;
    const hasMultiTopLevel = SF.selectedNodes.filter(isTopLevelNode).length >= 2 && SF.canEdit;
    const canUngroup = count === 1 && isObjectGroup(SF.selectedNodes[0]) && SF.canEdit;
    ['dupObjBtn', 'copyObjBtn', 'cutObjBtn'].forEach((id) => {
      const btn = document.getElementById(id);
      if (btn) btn.disabled = !hasSingle;
    });
    const groupBtn = document.getElementById('groupObjBtn');
    if (groupBtn) groupBtn.disabled = !hasMultiTopLevel;
    const ungroupBtn = document.getElementById('ungroupObjBtn');
    if (ungroupBtn) ungroupBtn.disabled = !canUngroup;
  }

  function refreshPropsPanel() {
    if (SF.selectedNodes.length > 1) {
      const panel = document.getElementById('propsObjectPanel');
      if (panel) {
        const label = (I.multiSelect || '{n} selected').replace('{n}', String(SF.selectedNodes.length));
        panel.innerHTML = '<div class="props-empty">' + escapeHtml(label) + '</div>';
      }
      return;
    }
    renderPropsPanel(SF.selectedNode);
  }

  function renderPropsPanel(node) {
    const panel = document.getElementById('propsObjectPanel');
    if (!panel) return;
    if (!node) {
      panel.innerHTML = '<div class="props-empty">' + I.propsEmpty + '</div>';
      return;
    }
    if (isObjectGroup(node)) {
      const obj = nodeToObject(node);
      const childCount = (obj.children || []).length;
      const countLabel = (I.groupChildCount || '{n} Objekte').replace('{n}', String(childCount));
      let html = '<div class="props-title">' + (I.typeGroup || 'Gruppe') + '</div>';
      html += '<p class="props-video-note">' + escapeHtml(countLabel) + '</p>';
      html += '<div class="props-section">';
      html += '<div class="options-subtitle" style="margin-top:0;">' + I.alignToSlide + '</div>';
      html += alignGridButtonsHtml();
      html += '<div class="options-subtitle">' + I.advanced + '</div>';
      html += '<div class="row">' + fieldNumber('p_x', 'X', obj.x) + fieldNumber('p_y', 'Y', obj.y) + '</div>';
      html += fieldNumber('p_rotation', I.rotation, obj.rotation || 0);
      html += fieldRange('p_opacity', I.opacity, Math.round((obj.opacity || 1) * 100));
      html += '</div>';
      panel.innerHTML = html;
      wirePropsPanel(node, obj);
      return;
    }
    const obj = nodeToObject(node);
    const isText = obj.type === 'text';
    const isShape = obj.type === 'rect' || obj.type === 'ellipse' || obj.type === 'shape';
    const isOpenShape = obj.type === 'shape' && !!SHAPE_OPEN[obj.shapeType];
    const isImage = obj.type === 'image';
    const isVideo = obj.type === 'video';
    const isAudio = obj.type === 'audio';
    const typeLabel = obj.type === 'shape'
      ? (SHAPE_LABELS[obj.shapeType] || I.typeShape)
      : ({ text: I.typeText, ellipse: I.typeEllipse, rect: I.typeRect, image: I.typeImage, video: I.typeVideo, audio: I.typeAudio }[obj.type] || obj.type);

    const tabs = isText ? ['format', 'position', 'effect'] : ['form', 'position', 'effect'];
    if (!tabs.includes(SF.activePropsTab)) SF.activePropsTab = tabs[0];
    const tabLabels = { format: I.tabFormat, form: I.tabForm, position: I.tabPosition, effect: I.tabEffect };

    let html = '<div class="props-title">' + typeLabel + '</div>';
    html += '<div class="page-tabs props-tabs">' + tabs.map(t =>
      '<button type="button" class="page-tab-btn props-tab-btn' + (SF.activePropsTab === t ? ' active' : '') + '" data-propstab="' + t + '">' + tabLabels[t] + '</button>'
    ).join('') + '</div>';

    html += '<div class="props-section">';
    const tab = SF.activePropsTab;

    if (tab === 'format' && isText) {
      const editBody =
        fieldTextarea('p_text', I.text, obj.text) +
        '<label>' + I.formatSelection + '</label>' +
        '<div class="format-toggle-group">' +
          '<button type="button" class="format-toggle-btn" data-wrap="**" style="font-weight:700;" title="' + I.bold + '">B</button>' +
          '<button type="button" class="format-toggle-btn" data-wrap="*" style="font-style:italic;" title="' + I.italic + '">I</button>' +
          '<button type="button" class="format-toggle-btn" data-wrap="++" style="text-decoration:underline;" title="' + I.underline + '">U</button>' +
          '<button type="button" class="format-toggle-btn" data-wrap="~~" style="text-decoration:line-through;" title="' + I.strikethrough + '">S</button>' +
          '<button type="button" class="format-toggle-btn" data-wraptag="upper" style="font-size:0.72em;" title="' + I.uppercase + '">AA</button>' +
          '<button type="button" class="format-toggle-btn" data-wraptag="sc" style="font-variant:small-caps;" title="' + I.smallcaps + '">Aa</button>' +
          '<button type="button" class="format-toggle-btn" id="markSelectionBtn" title="' + I.mark + '">🖊</button>' +
          '<button type="button" class="format-toggle-btn" id="colorSelectionBtn" title="' + I.textColorBtn + '">🎨</button>' +
        '</div>' +
        '<div id="markSelectionPalette" hidden><div class="options-subtitle" style="margin-top:8px;">' + I.markColorPick + '</div><input type="color" id="markColorPicker" value="#fff176" style="width:100%; height:32px; margin-bottom:6px;">' + miniPaletteHtml('markColorPicker') + '</div>' +
        '<div id="colorSelectionPalette" hidden><div class="options-subtitle" style="margin-top:8px;">' + I.textColorPick + '</div><input type="color" id="textColorPicker" value="#3a6c8d" style="width:100%; height:32px; margin-bottom:6px;">' + miniPaletteHtml('textColorPicker') + '</div>' +
        '<div class="props-video-note" style="margin-top:6px;">' + I.selectionHint + '</div>' +
        '<div class="props-video-note">' + I.markdownHint + '</div>';

      const formatBody =
        fieldFormatToggles(obj) +
        '<div class="props-video-note" style="margin-top:8px;">' + I.uppercaseHint + '</div>' +
        '<label>' + I.align + '</label><div class="align-buttons">' +
          ['left', 'center', 'right'].map(a => '<button type="button" class="align-btn' + (obj.align === a ? ' active' : '') + '" data-align="' + a + '">' + a[0].toUpperCase() + '</button>').join('') +
        '</div>';

      const fontBody =
        '<div class="row">' + fieldSelect('p_font', I.font, FONT_OPTIONS, obj.fontFamily) + fieldNumber('p_fontsize', I.size, obj.fontSize) + '</div>' +
        '<div class="row">' +
          '<div><label for="p_lineheight">' + I.lineHeight + '</label><input type="number" id="p_lineheight" min="0.8" max="3" step="0.05" value="' + (obj.lineHeight || 1.2) + '"></div>' +
          '<div><label for="p_letterspacing">' + I.letterSpacing + '</label><input type="number" id="p_letterspacing" min="-0.2" max="1" step="0.05" value="' + (obj.letterSpacing ?? 0) + '"></div>' +
        '</div>';

      let templatesBody = '<div class="props-video-note" style="margin-top:0;">' + escapeHtml(I.templatesEmpty || '') + '</div>';
      if (SF.textTemplates && SF.textTemplates.length) {
        templatesBody = '<div class="format-toggle-group">' + SF.textTemplates.map(t =>
          '<button type="button" class="button button-ghost button-sm" data-apply-text-style="' + t.id + '">' + escapeHtml(t.name) + '</button>'
        ).join('') + '</div>';
      }

      const colorsBody =
        fieldColor('p_color', I.color, obj.color) +
        miniPaletteHtml('p_color') +
        fieldRange('p_opacity', I.opacity, Math.round(obj.opacity * 100));

      html += '<div class="props-accordion" data-accordion-name="textProps">';
      html += accordionGroup('edit', I.groupEdit, editBody);
      html += accordionGroup('format', I.groupFormat, formatBody);
      html += accordionGroup('font', I.groupFont, fontBody);
      html += accordionGroup('colors', I.groupColors, colorsBody);
      html += accordionGroup('templates', I.groupTemplates, templatesBody);
      html += '</div>';
    }

    if (tab === 'form' && isShape && isOpenShape) {
      html += '<div class="row">' + fieldColor('p_stroke', I.lineColor, obj.stroke === 'transparent' ? '#ffffff' : obj.stroke) + fieldNumber('p_strokewidth', I.lineWidth, obj.strokeWidth) + '</div>';
      html += miniPaletteHtml('p_stroke');
      html += fieldRange('p_opacity', I.opacity, Math.round(obj.opacity * 100));
      if (obj.shapeType === 'bracket') {
        html += fieldIconPicker('p_bracketStyle', I.bracketStyle, BRACKET_STYLE_OPTIONS, obj.bracketStyle || 'round-left');
      }
    } else if (tab === 'form' && isShape) {
      html += '<div class="bg-type-tabs mini-tabs">' +
        '<button type="button" class="bg-type-btn fill-type-btn' + ((obj.fillType || 'solid') === 'solid' ? ' active' : '') + '" data-filltype="solid">' + I.fillSolid + '</button>' +
        '<button type="button" class="bg-type-btn fill-type-btn' + (obj.fillType === 'gradient' ? ' active' : '') + '" data-filltype="gradient">' + I.fillGradient + '</button>' +
        '<button type="button" class="bg-type-btn fill-type-btn' + (obj.fillType === 'none' ? ' active' : '') + '" data-filltype="none">' + I.fillNone + '</button>' +
        '</div>';
      if (obj.fillType === 'gradient') {
        html += '<div class="row">' + fieldColor('p_fillGrad1', I.color1, obj.gradColor1) + fieldColor('p_fillGrad2', I.color2, obj.gradColor2) + '</div>';
        html += fieldRangePlain('p_fillGradAngle', I.angle, 0, 360, obj.gradAngle);
      } else if (obj.fillType === 'none') {
        html += '<div class="props-video-note" style="margin-top:0;">' + I.fillNoneHint + '</div>';
      } else {
        html += fieldColor('p_fill', I.fillColor, obj.fill);
        html += miniPaletteHtml('p_fill');
      }
      html += '<div class="row">' + fieldColor('p_stroke', I.borderColor, obj.stroke === 'transparent' ? '#000000' : obj.stroke) + fieldNumber('p_strokewidth', I.borderWidth, obj.strokeWidth) + '</div>';
      html += miniPaletteHtml('p_stroke');
      html += fieldRange('p_opacity', I.opacity, Math.round(obj.opacity * 100));

      if (obj.shapeType === 'star') {
        html += fieldNumber('p_starPoints', I.starPoints, obj.starPoints || 5);
      }
      if (obj.shapeType === 'arrow') {
        html += fieldIconPicker('p_arrowStyle', I.arrowStyle, ARROW_STYLE_OPTIONS, obj.arrowStyle || 'right');
      }
      if (obj.shapeType === 'speech-bubble') {
        html += fieldIconPicker('p_bubbleStyle', I.bubbleStyle, BUBBLE_STYLE_OPTIONS, obj.bubbleStyle || 'rect-left');
      }
    }

    if (tab === 'form' && isImage) {
      const isIcon = isIconObject(obj);
      const previewSrc = isIcon && obj.iconColor ? iconDisplaySrc(obj.src, obj.iconColor) : obj.src;
      const filename = assetFilenameFromSrc(obj.src);
      const isSvgAsset = isIcon || /\.svg$/i.test(filename);
      const previewClass = 'props-asset-preview' + (isSvgAsset ? ' props-asset-preview--svg' : '');
      html += '<div class="' + previewClass + '"><img src="' + previewSrc + '" alt=""></div>';
      if (isIcon) {
        html += fieldColor('p_iconColor', I.iconColor, obj.iconColor || defaultIconColor());
        html += miniPaletteHtml('p_iconColor');
      }
      html += '<button type="button" class="button button-ghost button-sm" id="replaceAssetBtn" style="width:100%;">' + I.replaceImage + '</button>';
      html += '<button type="button" class="button button-ghost button-sm" id="removeBgBtn" style="width:100%; margin-top:8px;">' + I.removeBackground + '</button>';
      html += '<div class="props-video-note" style="margin-top:8px;">' + I.removeBackgroundHint + '</div>';
      html += '<div class="row">' + fieldColor('p_stroke', I.borderColor, (obj.stroke && obj.stroke !== 'transparent') ? obj.stroke : '#ffffff') + fieldNumber('p_strokewidth', I.borderWidth, obj.strokeWidth || 0) + '</div>';
      html += miniPaletteHtml('p_stroke');
      html += fieldRange('p_opacity', I.opacity, Math.round(obj.opacity * 100));
    }

    if (tab === 'form' && (isVideo || isAudio)) {
      html += '<div class="props-video-note">' + I.mediaPlaceholderHint.replace('{type}', isAudio ? I.mediaAudio : I.mediaVideo) + '</div>';
      html += '<button type="button" class="button button-ghost button-sm" id="replaceAssetBtn" style="width:100%;">' + (isAudio ? I.replaceAudio : I.replaceVideo) + '</button>';
      html += fieldRange('p_opacity', I.opacity, Math.round(obj.opacity * 100));
      html += fieldSelectKV('p_playTrigger', I.playTrigger, PLAY_TRIGGER_OPTIONS, obj.playTrigger || 'manual');
      if ((obj.playTrigger || 'manual') === 'timed') {
        html += fieldNumber('p_playDelay', I.playDelay, obj.playDelay || 0);
      }
      html += '<label style="display:flex; align-items:center; gap:8px; margin-top:10px;"><input type="checkbox" id="p_hideControls" style="width:auto;" ' + (obj.hideControls ? 'checked' : '') + '> ' + I.hideControls + '</label>';
      html += '<label style="display:flex; align-items:center; gap:8px; margin-top:8px;"><input type="checkbox" id="p_loop" style="width:auto;" ' + (obj.loop ? 'checked' : '') + '> ' + I.loop + '</label>';
      html += '<div class="props-video-note">' + I.playTriggerHint + '</div>';
    }

    if (tab === 'position') {
      html += '<div class="options-subtitle" style="margin-top:0;">' + I.alignToSlide + '</div>';
      html += alignGridButtonsHtml();

      html += '<div class="options-subtitle">' + I.advanced + '</div>';
      html += '<div class="row">' + fieldNumber('p_x', 'X', obj.x) + fieldNumber('p_y', 'Y', obj.y) + '</div>';
      html += '<div class="row wh-row">' + fieldNumber('p_w', I.width, obj.w) +
        (isText ? '' : fieldNumber('p_h', I.height, obj.h)) +
        (isText ? '' : '<button type="button" id="aspectLockBtn" class="aspect-lock-btn" title="' + I.aspectLock + '">' + (node.getAttr('aspectLocked') ? LOCK_ICON_CLOSED : LOCK_ICON_OPEN) + '</button>') +
        '</div>';
      html += fieldNumber('p_rotation', I.rotation, obj.rotation);

      html += '<div class="props-layer-actions">' +
        '<button type="button" class="layer-btn" data-layer="up">' + ICON_LAYER_UP + ' ' + I.layerUp + '</button>' +
        '<button type="button" class="layer-btn" data-layer="down">' + ICON_LAYER_DOWN + ' ' + I.layerDown + '</button>' +
        '<button type="button" class="layer-btn" data-layer="front">' + ICON_LAYER_TOP + ' ' + I.layerFront + '</button>' +
        '<button type="button" class="layer-btn" data-layer="back">' + ICON_LAYER_BOTTOM + ' ' + I.layerBack + '</button>' +
        '</div>';
    }

    if (tab === 'effect') {
      html += fieldIconPicker('p_anim', I.effect, ANIMATIONS, obj.animType || 'none', 'effect-icon-grid anim-icon-grid');
      if ((obj.animType || 'none') !== 'none') {
        html += fieldNumber('p_animOrder', I.animOrder, obj.animOrder || 1);
        html += fieldSelectKV('p_animDuration', I.animDuration, ANIM_DURATION_OPTIONS, obj.animDuration || 0);
        html += fieldSelectKV('p_animAuto', I.animAutostart, ANIM_AUTOSTART_OPTIONS, obj.animAutoAdvance || 0);
        if (isText) {
          html += '<label style="display:flex; align-items:center; gap:8px; margin-top:12px;"><input type="checkbox" id="p_animPerLine" style="width:auto;" ' + (obj.animPerLine ? 'checked' : '') + '> ' + I.animPerLine + '</label>';
          html += '<div class="props-video-note">' + I.animPerLineHint + '</div>';
        }
        html += '<div class="props-video-note">' + I.animHint + '</div>';
      }
    }

    html += '</div>';
    html += '<button type="button" class="button button-danger button-sm" id="deleteObjBtn" style="width:100%; margin-top:14px;">' + I.deleteObject + '</button>';

    panel.innerHTML = html;
    wirePropsPanel(node, obj);
    if (node && node.getAttr('objType') === 'text') {
      applySpellcheckAttrs(document.getElementById('p_text'));
      scheduleAutoGrowTextarea();
    }
  }

  const LOCK_ICON_OPEN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.75-1.5"/></svg>';
  const LOCK_ICON_CLOSED = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
  const ICON_LAYER_UP = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 15l6-6 6 6"/></svg>';
  const ICON_LAYER_DOWN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>';
  const ICON_LAYER_TOP = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 17l6-6 6 6"/><path d="M6 11l6-6 6 6"/></svg>';
  const ICON_LAYER_BOTTOM = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7l6 6 6-6"/><path d="M6 13l6 6 6-6"/></svg>';

  function miniPaletteHtml(forId) {
    return '<div class="brand-palette mini" data-for="' + forId + '">' +
      SF.brandColors.map(c => '<button type="button" class="brand-swatch" data-color="' + c.hex + '" data-for="' + forId + '" style="background:' + c.hex + '" title="' + (c.name || c.hex) + '"></button>').join('') +
      '</div>';
  }

  function fieldNumber(id, label, value) {
    return '<div><label for="' + id + '">' + label + '</label><input type="number" id="' + id + '" value="' + value + '"></div>';
  }
  function fieldColor(id, label, value) {
    return '<div><label for="' + id + '">' + label + '</label><input type="color" id="' + id + '" value="' + value + '"></div>';
  }
  function fieldRange(id, label, value) {
    return '<div><label for="' + id + '">' + label + ' (' + value + '%)</label><input type="range" id="' + id + '" min="0" max="100" value="' + value + '"></div>';
  }
  function fieldRangePlain(id, label, min, max, value) {
    return '<div><label for="' + id + '">' + label + ' (' + value + '°)</label><input type="range" id="' + id + '" min="' + min + '" max="' + max + '" value="' + value + '"></div>';
  }
  function fieldTextarea(id, label, value) {
    return '<label for="' + id + '">' + label + '</label><textarea id="' + id + '" class="props-text-autoheight" rows="1">' + escapeHtml(value) + '</textarea>';
  }
  function autoGrowTextarea(el) {
    if (!el) return;
    const cs = window.getComputedStyle(el);
    const lineHeight = parseFloat(cs.lineHeight) || 20;
    const padY = parseFloat(cs.paddingTop) + parseFloat(cs.paddingBottom);
    const borderY = parseFloat(cs.borderTopWidth) + parseFloat(cs.borderBottomWidth);
    const lines = (el.value.match(/\n/g) || []).length + 1;
    const lineBased = Math.ceil(lines * lineHeight + padY + borderY + 8);
    el.style.overflow = 'hidden';
    el.style.height = '0px';
    const scrollBased = el.scrollHeight + borderY + 8;
    el.style.height = Math.max(lineBased, scrollBased, 96) + 'px';
  }
  function scheduleAutoGrowTextarea() {
    const run = () => autoGrowTextarea(document.getElementById('p_text'));
    run();
    requestAnimationFrame(run);
    setTimeout(run, 0);
    setTimeout(run, 60);
    setTimeout(run, 180);
  }
  function fieldSelect(id, label, options, selected) {
    return '<div><label for="' + id + '">' + label + '</label><select id="' + id + '">' +
      options.map(o => '<option value="' + o + '"' + (o === selected ? ' selected' : '') + '>' + o + '</option>').join('') +
      '</select></div>';
  }
  function fieldSelectKV(id, label, options, selected) {
    return '<div><label for="' + id + '">' + label + '</label><select id="' + id + '">' +
      options.map(o => '<option value="' + o.value + '"' + (o.value === selected ? ' selected' : '') + '>' + o.label + '</option>').join('') +
      '</select></div>';
  }
  function accordionGroup(id, label, bodyHtml) {
    const isOpen = SF.activeFormatGroup === id;
    return '<div class="props-accordion-group' + (isOpen ? ' open' : '') + '" data-accordion-group="' + id + '">' +
      '<button type="button" class="props-accordion-header">' + label + '<span class="props-accordion-chevron">▾</span></button>' +
      '<div class="props-accordion-body">' + bodyHtml + '</div>' +
    '</div>';
  }
  function fieldIconPicker(id, label, options, selected, groupClass) {
    const cls = 'format-toggle-group effect-icon-grid' + (groupClass ? ' ' + groupClass : '');
    return '<label>' + label + '</label><div class="' + cls + '" id="' + id + '_group" data-picker-field="' + id + '">' +
      options.map(o => '<button type="button" class="format-toggle-btn effect-icon-btn' + (o.value === selected ? ' active' : '') + '" data-icon-value="' + o.value + '" title="' + escapeHtml(o.label) + '" aria-label="' + escapeHtml(o.label) + '"' + (o.flip ? ' style="transform:scaleX(-1);"' : '') + '>' + o.icon + '</button>').join('') +
      '</div>';
  }

  function bindValueIconPicker(fieldId, onPick) {
    const group = document.getElementById(fieldId + '_group');
    if (!group) return;
    group.querySelectorAll('[data-icon-value]').forEach(btn => {
      btn.addEventListener('click', () => {
        group.querySelectorAll('[data-icon-value]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        onPick(btn.dataset.iconValue);
      });
    });
  }

  function getTransitionValue() {
    const hidden = document.getElementById('transitionSelect');
    return hidden ? hidden.value : 'slide';
  }

  function setTransitionPickerValue(value) {
    const hidden = document.getElementById('transitionSelect');
    const group = document.getElementById('transitionPickerGroup');
    if (!hidden || !group) return;
    hidden.value = value || 'slide';
    group.querySelectorAll('[data-icon-value]').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.iconValue === hidden.value);
    });
  }

  function initTransitionPicker() {
    const group = document.getElementById('transitionPickerGroup');
    const hidden = document.getElementById('transitionSelect');
    if (!group || !hidden) return;
    group.innerHTML = TRANSITION_OPTIONS.map(o =>
      '<button type="button" class="format-toggle-btn effect-icon-btn' + (o.value === hidden.value ? ' active' : '') + '" data-icon-value="' + o.value + '" title="' + escapeHtml(o.label) + '" aria-label="' + escapeHtml(o.label) + '">' + o.icon + '</button>'
    ).join('');
    group.querySelectorAll('[data-icon-value]').forEach(btn => {
      btn.addEventListener('click', () => {
        hidden.value = btn.dataset.iconValue;
        setTransitionPickerValue(hidden.value);
        scheduleSave();
      });
    });
  }
  function fieldFormatToggles(obj) {
    const items = [
      { id: 'p_bold', label: 'B', style: 'font-weight:700;', active: obj.fontWeight === 'bold', title: I.bold },
      { id: 'p_italic', label: 'I', style: 'font-style:italic;', active: !!obj.italic, title: I.italic },
      { id: 'p_underline', label: 'U', style: 'text-decoration:underline;', active: !!obj.underline, title: I.underline },
      { id: 'p_strikethrough', label: 'S', style: 'text-decoration:line-through;', active: !!obj.strikethrough, title: I.strikethrough },
      { id: 'p_uppercase', label: 'AA', style: 'font-size:0.72em; letter-spacing:0.02em;', active: !!obj.uppercase, title: I.uppercase },
      { id: 'p_smallcaps', label: 'Aa', style: 'font-variant:small-caps;', active: !!obj.smallCaps, title: I.smallcaps },
    ];
    return '<label>' + I.format + '</label><div class="format-toggle-group">' + items.map(it =>
      '<button type="button" class="format-toggle-btn' + (it.active ? ' active' : '') + '" id="' + it.id + '" data-checked="' + (it.active ? '1' : '0') + '" title="' + it.title + '" style="' + it.style + '">' + it.label + '</button>'
    ).join('') + '</div>';
  }

  function fieldCheckbox(id, label, checked) {
    return '<div><label for="' + id + '">&nbsp;</label><label style="display:flex; align-items:center; gap:6px; font-size:0.9rem; color:var(--text);"><input type="checkbox" id="' + id + '" style="width:auto;" ' + (checked ? 'checked' : '') + '> ' + label + '</label></div>';
  }
  function fieldCheckboxCompact(id, label, checked) {
    return '<label class="checkbox-compact"><input type="checkbox" id="' + id + '" style="width:auto;" ' + (checked ? 'checked' : '') + '> ' + label + '</label>';
  }
  function escapeHtml(s) {
    return (s || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function applyWidth(node, type, w) {
    if (type === 'ellipse') { node.radiusX(w / 2); }
    else if (type === 'video' || type === 'audio') { node.scaleX(w / (node.getAttr('baseW') || w)); }
    else if (type === 'shape') {
      node.points(buildShapePoints(node.getAttr('shapeType'), w, node.getAttr('baseH'), nodeShapeCfg(node)));
      node.setAttr('baseW', w);
    } else { node.width(w); }
    if (node.getAttr('fillType') === 'gradient') applyShapeGradientVisual(node);
  }

  function applyHeight(node, type, h) {
    if (type === 'ellipse') { node.radiusY(h / 2); }
    else if (type === 'video' || type === 'audio') { node.scaleY(h / (node.getAttr('baseH') || h)); }
    else if (type === 'shape') {
      node.points(buildShapePoints(node.getAttr('shapeType'), node.getAttr('baseW'), h, nodeShapeCfg(node)));
      node.setAttr('baseH', h);
    } else { node.height(h); }
    if (node.getAttr('fillType') === 'gradient') applyShapeGradientVisual(node);
  }

  function alignObjectToSlide(node, type, edge, toEdge) {
    const o = nodeToObject(node);
    const w = o.w, h = o.h;
    const m = toEdge ? 0 : getSafeMargin();
    const slideW = SF.meta.width;
    const slideH = SF.meta.height;
    if (type === 'group') {
      if (edge === 'left') node.x(m);
      if (edge === 'hcenter') node.x(Math.round((slideW - w) / 2));
      if (edge === 'right') node.x(slideW - m - w);
      if (edge === 'top') node.y(m);
      if (edge === 'vcenter') node.y(Math.round((slideH - h) / 2));
      if (edge === 'bottom') node.y(slideH - m - h);
      return;
    }
    if (type === 'ellipse') {
      const rx = w / 2, ry = h / 2;
      if (edge === 'left') node.x(m + rx);
      if (edge === 'hcenter') node.x(slideW / 2);
      if (edge === 'right') node.x(slideW - m - rx);
      if (edge === 'top') node.y(m + ry);
      if (edge === 'vcenter') node.y(slideH / 2);
      if (edge === 'bottom') node.y(slideH - m - ry);
    } else {
      if (edge === 'left') node.x(m);
      if (edge === 'hcenter') node.x(Math.round((slideW - w) / 2));
      if (edge === 'right') node.x(slideW - m - w);
      if (edge === 'top') node.y(m);
      if (edge === 'vcenter') node.y(Math.round((slideH - h) / 2));
      if (edge === 'bottom') node.y(slideH - m - h);
    }
  }

  function wirePropsPanel(node, obj) {
    const type = obj.type;
    const on = (id, evt, fn) => { const el = document.getElementById(id); if (el) el.addEventListener(evt, fn); };

    document.querySelectorAll('.props-tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        SF.activePropsTab = btn.dataset.propstab;
        refreshPropsPanel();
      });
    });

    document.querySelectorAll('.brand-palette.mini .brand-swatch').forEach(btn => {
      btn.addEventListener('click', () => {
        const target = document.getElementById(btn.dataset.for);
        if (!target) return;
        target.value = btn.dataset.color;
        target.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });

    if (type === 'text') {
      on('p_text', 'input', (e) => { node.text(e.target.value); refreshCanvas(); scheduleSave(); autoGrowTextarea(e.target); });
      scheduleAutoGrowTextarea();

      document.querySelectorAll('[data-apply-text-style]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const t = SF.textTemplates.find((tt) => tt.id === btn.dataset.applyTextStyle);
          if (!t) return;
          node.fontFamily(t.fontFamily || 'Open Sans');
          node.fontSize(t.fontSize || 32);
          node.fill(t.color || '#ffffff');
          node.align(t.align || 'left');
          const styleParts = [];
          if (t.italic) styleParts.push('italic');
          if (t.fontWeight === 'bold') styleParts.push('bold');
          node.fontStyle(styleParts.length ? styleParts.join(' ') : 'normal');
          const decoParts = [];
          if (t.underline) decoParts.push('underline');
          if (t.strikethrough) decoParts.push('line-through');
          node.textDecoration(decoParts.length ? decoParts.join(' ') : '');
          node.setAttr('fontWeight', t.fontWeight === 'bold' ? 'bold' : 'normal');
          node.setAttr('italic', !!t.italic);
          node.setAttr('underline', !!t.underline);
          node.setAttr('strikethrough', !!t.strikethrough);
          node.setAttr('uppercase', !!t.uppercase);
          node.setAttr('smallCaps', !!t.smallCaps);
          refreshCanvas();
          scheduleSave();
          refreshPropsPanel();
        });
      });

      function wrapTextSelection(before, after) {
        const ta = document.getElementById('p_text');
        if (!ta) return;
        const start = ta.selectionStart, end = ta.selectionEnd;
        if (start === end) { ta.focus(); return; }
        const value = ta.value;
        const newValue = value.slice(0, start) + before + value.slice(start, end) + after + value.slice(end);
        ta.value = newValue;
        node.text(newValue);
        refreshCanvas(); scheduleSave();
        autoGrowTextarea(ta);
        ta.focus();
        ta.setSelectionRange(start + before.length, end + before.length);
      }
      document.querySelectorAll('[data-wrap]').forEach(btn => {
        btn.addEventListener('click', () => { const w = btn.dataset.wrap; wrapTextSelection(w, w); });
      });
      document.querySelectorAll('[data-wraptag]').forEach(btn => {
        btn.addEventListener('click', () => { const t = btn.dataset.wraptag; wrapTextSelection('[' + t + ']', '[/' + t + ']'); });
      });

      function bindColorSelectionTool(btnId, paletteId, pickerId, tagName) {
        const btn = document.getElementById(btnId);
        const palette = document.getElementById(paletteId);
        const picker = document.getElementById(pickerId);
        if (!btn || !palette || !picker) return;
        btn.addEventListener('click', () => { palette.hidden = !palette.hidden; });
        picker.addEventListener('change', () => {
          wrapTextSelection('[' + tagName + '=' + picker.value + ']', '[/' + tagName + ']');
          palette.hidden = true;
        });
        palette.querySelectorAll('.brand-swatch').forEach(sw => {
          sw.addEventListener('click', () => {
            wrapTextSelection('[' + tagName + '=' + sw.dataset.color + ']', '[/' + tagName + ']');
            palette.hidden = true;
          });
        });
      }
      bindColorSelectionTool('markSelectionBtn', 'markSelectionPalette', 'markColorPicker', 'mark');
      bindColorSelectionTool('colorSelectionBtn', 'colorSelectionPalette', 'textColorPicker', 'color');
      on('p_font', 'change', (e) => { node.fontFamily(e.target.value); refreshCanvas(); scheduleSave(); });
      on('p_fontsize', 'input', (e) => {
        node.fontSize(parseInt(e.target.value, 10) || 1);
        const ls = parseFloat(node.getAttr('letterSpacing')) || 0;
        node.letterSpacing(ls * node.fontSize());
        refreshCanvas();
        scheduleSave();
      });
      on('p_lineheight', 'input', (e) => {
        const lh = Math.max(0.8, Math.min(3, parseFloat(e.target.value) || 1.2));
        node.lineHeight(lh);
        node.setAttr('lineHeight', lh);
        refreshCanvas();
        scheduleSave();
      });
      on('p_letterspacing', 'input', (e) => {
        const ls = Math.max(-0.2, Math.min(1, parseFloat(e.target.value) || 0));
        node.setAttr('letterSpacing', ls);
        node.letterSpacing(ls * node.fontSize());
        refreshCanvas();
        scheduleSave();
      });
      on('p_color', 'input', (e) => { node.fill(e.target.value); refreshCanvas(); scheduleSave(); });

      const isChecked = (id) => document.getElementById(id)?.dataset.checked === '1';
      const bindToggle = (id, onToggle) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', () => {
          const next = el.dataset.checked !== '1';
          el.dataset.checked = next ? '1' : '0';
          el.classList.toggle('active', next);
          onToggle(next);
        });
      };

      const updateFontStyle = () => {
        const parts = [];
        if (isChecked('p_italic')) parts.push('italic');
        if (isChecked('p_bold')) parts.push('bold');
        node.fontStyle(parts.length ? parts.join(' ') : 'normal');
        refreshCanvas(); scheduleSave();
      };
      const updateTextDecoration = () => {
        const parts = [];
        if (isChecked('p_underline')) parts.push('underline');
        if (isChecked('p_strikethrough')) parts.push('line-through');
        node.textDecoration(parts.join(' '));
        refreshCanvas(); scheduleSave();
      };
      bindToggle('p_bold', updateFontStyle);
      bindToggle('p_italic', updateFontStyle);
      bindToggle('p_underline', updateTextDecoration);
      bindToggle('p_strikethrough', updateTextDecoration);
      bindToggle('p_uppercase', (state) => { node.setAttr('uppercase', state); scheduleSave(); });
      bindToggle('p_smallcaps', (state) => { node.setAttr('smallCaps', state); scheduleSave(); });

      document.querySelectorAll('.align-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          node.align(btn.dataset.align);
          document.querySelectorAll('.align-btn').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          refreshCanvas(); scheduleSave();
        });
      });
    } else if (type === 'rect' || type === 'ellipse' || type === 'shape') {
      document.querySelectorAll('.fill-type-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const val = btn.dataset.filltype;
          node.setAttr('fillType', val);
          if (val === 'gradient') {
            if (!node.getAttr('gradColor1')) node.setAttr('gradColor1', '#3a6c8d');
            if (!node.getAttr('gradColor2')) node.setAttr('gradColor2', '#87b42b');
            if (node.getAttr('gradAngle') === undefined) node.setAttr('gradAngle', 90);
            node.fillEnabled(true);
            applyShapeGradientVisual(node);
          } else if (val === 'none') {
            node.fillEnabled(false);
          } else {
            node.fillEnabled(true);
            node.fillPriority('color');
            node.fill(node.fill() || '#cccccc');
          }
          refreshCanvas(); scheduleSave(); refreshPropsPanel();
        });
      });
      on('p_fill', 'input', (e) => { node.fill(e.target.value); refreshCanvas(); scheduleSave(); });
      on('p_fillGrad1', 'input', (e) => { node.setAttr('gradColor1', e.target.value); applyShapeGradientVisual(node); refreshCanvas(); scheduleSave(); });
      on('p_fillGrad2', 'input', (e) => { node.setAttr('gradColor2', e.target.value); applyShapeGradientVisual(node); refreshCanvas(); scheduleSave(); });
      on('p_fillGradAngle', 'input', (e) => {
        node.setAttr('gradAngle', parseInt(e.target.value, 10) || 0);
        const label = document.querySelector('label[for="p_fillGradAngle"]');
        if (label) label.textContent = 'Winkel (' + e.target.value + '°)';
        applyShapeGradientVisual(node);
        refreshCanvas(); scheduleSave();
      });
      on('p_stroke', 'input', (e) => { node.stroke(e.target.value); refreshCanvas(); scheduleSave(); });
      on('p_strokewidth', 'input', (e) => { node.strokeWidth(parseInt(e.target.value, 10) || 0); refreshCanvas(); scheduleSave(); });

      function recomputeShapePoints() {
        const baseW = node.getAttr('baseW'), baseH = node.getAttr('baseH');
        node.points(buildShapePoints(node.getAttr('shapeType'), baseW, baseH, nodeShapeCfg(node)));
      }
      on('p_starPoints', 'input', (e) => {
        node.setAttr('starPoints', Math.max(3, Math.min(20, parseInt(e.target.value, 10) || 5)));
        recomputeShapePoints(); refreshCanvas(); scheduleSave();
      });
      function bindIconPicker(fieldId, attrName) {
        const group = document.getElementById(fieldId + '_group');
        if (!group) return;
        group.querySelectorAll('[data-icon-value]').forEach(btn => {
          btn.addEventListener('click', () => {
            group.querySelectorAll('[data-icon-value]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            node.setAttr(attrName, btn.dataset.iconValue);
            recomputeShapePoints(); refreshCanvas(); scheduleSave();
          });
        });
      }
      bindIconPicker('p_arrowStyle', 'arrowStyle');
      bindIconPicker('p_bracketStyle', 'bracketStyle');
      bindIconPicker('p_bubbleStyle', 'bubbleStyle');
    } else if (type === 'image') {
      const replaceBtn = document.getElementById('replaceAssetBtn');
      if (replaceBtn) replaceBtn.addEventListener('click', () => {
        replaceTargetNode = node;
        document.getElementById('objImageInput').click();
      });
      const removeBgBtn = document.getElementById('removeBgBtn');
      if (removeBgBtn) removeBgBtn.addEventListener('click', async () => {
        const filename = assetFilenameFromSrc(node.getAttr('src'));
        if (!filename) {
          setSaveStatus(I.removeBackgroundFailed, true);
          return;
        }
        removeBgBtn.disabled = true;
        setSaveStatus(I.removeBackgroundWorking);
        try {
          const res = await api('remove_image_background', { filename });
          node.setAttr('src', res.url);
          if (isClipartIconId(node.getAttr('iconId'))) {
            node.setAttr('iconId', '');
            node.setAttr('iconColor', '');
          }
          loadImageAsync(node, res.url);
          refreshCanvas();
          scheduleSave();
          refreshPropsPanel();
          setSaveStatus(SF.i18n?.saved || 'Gespeichert');
        } catch (e) {
          setSaveStatus(e.message || I.removeBackgroundFailed, true);
        } finally {
          removeBgBtn.disabled = false;
        }
      });
      on('p_iconColor', 'input', (e) => {
        const color = e.target.value;
        node.setAttr('iconColor', color);
        loadImageAsync(node, node.getAttr('src'), color);
        refreshCanvas();
        scheduleSave();
        const preview = document.querySelector('.props-asset-preview img');
        if (preview) preview.src = iconDisplaySrc(node.getAttr('src'), color);
      });
      on('p_stroke', 'input', (e) => { node.stroke(e.target.value); refreshCanvas(); scheduleSave(); });
      on('p_strokewidth', 'input', (e) => { node.strokeWidth(parseInt(e.target.value, 10) || 0); refreshCanvas(); scheduleSave(); });
    } else if (type === 'video' || type === 'audio') {
      const replaceBtn = document.getElementById('replaceAssetBtn');
      if (replaceBtn) replaceBtn.addEventListener('click', () => {
        replaceTargetNode = node;
        const inputId = type === 'audio' ? 'objAudioInput' : 'objVideoInput';
        document.getElementById(inputId).click();
      });
      on('p_playTrigger', 'change', (e) => {
        node.setAttr('playTrigger', e.target.value);
        scheduleSave();
        refreshPropsPanel();
      });
      on('p_playDelay', 'input', (e) => {
        node.setAttr('playDelay', Math.max(0, parseInt(e.target.value, 10) || 0));
        scheduleSave();
      });
      on('p_hideControls', 'change', (e) => {
        node.setAttr('hideControls', e.target.checked);
        scheduleSave();
      });
      on('p_loop', 'change', (e) => {
        node.setAttr('loop', e.target.checked);
        scheduleSave();
      });
    }

    // ---- Tab "Position" ----
    on('p_x', 'input', (e) => {
      const v = parseInt(e.target.value, 10) || 0;
      node.x(type === 'ellipse' ? v + node.radiusX() * node.scaleX() : v);
      updateAnimationBadge(node);
      refreshCanvas(); scheduleSave();
    });
    on('p_y', 'input', (e) => {
      const v = parseInt(e.target.value, 10) || 0;
      node.y(type === 'ellipse' ? v + node.radiusY() * node.scaleY() : v);
      updateAnimationBadge(node);
      refreshCanvas(); scheduleSave();
    });
    on('p_w', 'input', (e) => {
      const w = parseInt(e.target.value, 10) || 1;
      applyWidth(node, type, w);
      if (node.getAttr('aspectLocked')) {
        const ratio = node.getAttr('aspectRatio') || 1;
        const h = Math.max(1, Math.round(w / ratio));
        applyHeight(node, type, h);
        const hInput = document.getElementById('p_h');
        if (hInput) hInput.value = h;
      }
      updateAnimationBadge(node);
      refreshCanvas(); scheduleSave();
    });
    on('p_h', 'input', (e) => {
      const h = parseInt(e.target.value, 10) || 1;
      applyHeight(node, type, h);
      if (node.getAttr('aspectLocked')) {
        const ratio = node.getAttr('aspectRatio') || 1;
        const w = Math.max(1, Math.round(h * ratio));
        applyWidth(node, type, w);
        const wInput = document.getElementById('p_w');
        if (wInput) wInput.value = w;
      }
      updateAnimationBadge(node);
      refreshCanvas(); scheduleSave();
    });
    on('aspectLockBtn', 'click', () => {
      const locked = !node.getAttr('aspectLocked');
      node.setAttr('aspectLocked', locked);
      if (locked) {
        const o = nodeToObject(node);
        node.setAttr('aspectRatio', o.h ? o.w / o.h : 1);
      }
      refreshPropsPanel();
    });
    on('p_rotation', 'input', (e) => { node.rotation(parseInt(e.target.value, 10) || 0); refreshCanvas(); scheduleSave(); });
    on('p_opacity', 'input', (e) => { node.opacity(parseInt(e.target.value, 10) / 100); refreshCanvas(); scheduleSave(); });

    document.querySelectorAll('.align-grid-btn').forEach(btn => {
      let clickTimer = null;
      const applyAlign = (toEdge) => {
        alignObjectToSlide(node, type, btn.dataset.alignEdge, toEdge);
        updateAnimationBadge(node);
        refreshCanvas();
        scheduleSave();
        refreshPropsPanel();
      };
      btn.addEventListener('click', () => {
        clearTimeout(clickTimer);
        clickTimer = setTimeout(() => applyAlign(false), 250);
      });
      btn.addEventListener('dblclick', (e) => {
        e.preventDefault();
        clearTimeout(clickTimer);
        applyAlign(true);
      });
    });

    document.querySelectorAll('[data-layer]').forEach(btn => {
      btn.addEventListener('click', () => {
        if (btn.dataset.layer === 'up') node.moveUp();
        else if (btn.dataset.layer === 'down') node.moveDown();
        else if (btn.dataset.layer === 'front') node.moveToTop();
        else node.moveToBottom();
        if (node._sfBadge) node._sfBadge.moveToTop();
        if (SF.transformer) SF.transformer.moveToTop();
        refreshCanvas(); scheduleSave(); renderLayersPanel();
      });
    });

    // ---- Tab "Effekt" ----
    bindValueIconPicker('p_anim', (value) => {
      const wasNone = (node.getAttr('animType') || 'none') === 'none';
      node.setAttr('animType', value);
      if (value !== 'none' && wasNone) {
        const siblings = getTopLevelNodes().filter(n => n !== node && (n.getAttr('animType') || 'none') !== 'none');
        node.setAttr('animOrder', siblings.length + 1);
      }
      updateAnimationBadge(node);
      refreshCanvas(); scheduleSave(); refreshPropsPanel();
    });
    on('p_animOrder', 'input', (e) => {
      node.setAttr('animOrder', parseInt(e.target.value, 10) || 1);
      updateAnimationBadge(node);
      scheduleSave();
    });
    on('p_animAuto', 'change', (e) => {
      node.setAttr('animAutoAdvance', parseInt(e.target.value, 10) || 0);
      scheduleSave();
    });
    on('p_animPerLine', 'change', (e) => {
      node.setAttr('animPerLine', e.target.checked);
      scheduleSave();
    });
    on('p_animDuration', 'change', (e) => {
      node.setAttr('animDuration', parseInt(e.target.value, 10) || 0);
      scheduleSave();
    });

    const delBtn = document.getElementById('deleteObjBtn');
    if (delBtn) delBtn.addEventListener('click', () => {
      removeAnimationBadge(node);
      node.destroy();
      deselect();
      refreshCanvas();
      scheduleSave();
    });
  }

  function refreshCanvas() {
    if (SF.transformer) SF.transformer.forceUpdate();
    SF.layer.draw();
  }

  // ---------- Toolbar ----------
  function addShape(type) {
    if (type === 'image') { document.getElementById('objImageInput').click(); return; }
    if (type === 'video') { document.getElementById('objVideoInput').click(); return; }
    if (type === 'audio') { document.getElementById('objAudioInput').click(); return; }

    const centerX = Math.round(SF.meta.width / 2) - 100;
    const centerY = Math.round(SF.meta.height / 2) - 60;
    const id = 'o' + Math.random().toString(16).slice(2, 10);
    let obj;
    if (type === 'text') {
      obj = { id, type: 'text', x: centerX, y: centerY, w: 599, h: 70, text: 'Neuer Text', fontFamily: 'Open Sans', fontSize: 65, fontWeight: 'normal', color: '#ffffff', align: 'left', rotation: 0, opacity: 1 };
    } else if (type === 'markdown-text') {
      const mdText = '## Überschrift\nText mit **fett**, *kursiv* und `Code`.\n- Punkt eins\n- Punkt zwei';
      obj = { id, type: 'text', x: Math.round(SF.meta.width / 2) - 450, y: Math.round(SF.meta.height / 2) - 170, w: 900, h: 340, text: mdText, fontFamily: 'Open Sans', fontSize: 65, fontWeight: 'normal', color: '#ffffff', align: 'left', rotation: 0, opacity: 1 };
    } else if (type === 'ellipse') {
      obj = { id, type: 'ellipse', x: centerX, y: centerY, w: 200, h: 200, fillType: 'solid', fill: '#3a6c8d', stroke: 'transparent', strokeWidth: 0, rotation: 0, opacity: 1 };
    } else if (type === 'rect') {
      obj = { id, type: 'rect', x: centerX, y: centerY, w: 240, h: 140, fillType: 'solid', fill: '#87b42b', stroke: 'transparent', strokeWidth: 0, rotation: 0, opacity: 1 };
    } else if (type === 'line') {
      obj = { id, type: 'shape', shapeType: 'line', x: centerX - 20, y: centerY + 80, w: 240, h: 4, stroke: '#ffffff', strokeWidth: 4, rotation: 0, opacity: 1 };
    } else if (type === 'bracket') {
      obj = { id, type: 'shape', shapeType: 'bracket', x: centerX + 60, y: centerY - 60, w: 60, h: 200, stroke: '#ffffff', strokeWidth: 5, rotation: 0, opacity: 1, bracketStyle: 'round-left' };
    } else if (SHAPE_LABELS[type]) {
      obj = { id, type: 'shape', shapeType: type, x: centerX - 20, y: centerY - 20, w: 220, h: 200, fillType: 'solid', fill: '#3a6c8d', stroke: 'transparent', strokeWidth: 0, rotation: 0, opacity: 1, starPoints: 5, arrowStyle: 'right', bracketStyle: 'round-left', bubbleStyle: 'rect-left' };
    } else {
      obj = { id, type: 'rect', x: centerX, y: centerY, w: 240, h: 140, fillType: 'solid', fill: '#87b42b', stroke: 'transparent', strokeWidth: 0, rotation: 0, opacity: 1 };
    }
    insertNode(createNode(obj));
  }

  function addTextPreset(templateId) {
    const t = SF.textTemplates.find(t => t.id === templateId);
    if (!t) return;
    const w = t.w || 500, h = t.h || 60;
    const centerX = Math.round(SF.meta.width / 2) - w / 2;
    const centerY = Math.round(SF.meta.height / 2) - h / 2;
    const id = 'o' + Math.random().toString(16).slice(2, 10);
    const obj = {
      id, type: 'text', x: centerX, y: centerY, w, h, rotation: 0, opacity: 1,
      text: t.name || 'Text',
      fontFamily: t.fontFamily || 'Open Sans',
      fontSize: t.fontSize || 32,
      fontWeight: t.fontWeight || 'normal',
      italic: !!t.italic,
      underline: !!t.underline,
      strikethrough: !!t.strikethrough,
      uppercase: !!t.uppercase,
      smallCaps: !!t.smallCaps,
      color: t.color || '#ffffff',
      align: t.align || 'left',
    };
    insertNode(createNode(obj));
  }

  function renderTextTemplateButtons() {
    const el = document.getElementById('textTemplateButtons');
    if (!el) return;
    el.innerHTML = SF.textTemplates.map(t =>
      '<button type="button" class="tool-btn-block" data-preset="' + t.id + '">' + escapeHtml(t.name) + '</button>'
    ).join('');
  }

  function insertNode(node) {
    node.name('sf-node');
    SF.layer.add(node);
    bindNodeEvents(node);
    updateAnimationBadge(node);
    if (SF.transformer) SF.transformer.moveToTop();
    selectNode(node);
    refreshCanvas();
    scheduleSave();
  }

  // ---------- Duplizieren / Kopieren / Ausschneiden / Einfügen ----------
  SF.clipboard = null;

  function duplicateNode(node, offset) {
    if (!node || !SF.canEdit) return;
    const obj = cloneObjectWithNewIds(nodeToObject(node));
    obj.x = (obj.x || 0) + (offset ?? 24);
    obj.y = (obj.y || 0) + (offset ?? 24);
    insertNode(createNode(obj));
  }

  function cloneObjectWithNewIds(obj) {
    const copy = JSON.parse(JSON.stringify(obj));
    function assignIds(item) {
      item.id = 'o' + Math.random().toString(16).slice(2, 10);
      if (item.type === 'group' && Array.isArray(item.children)) {
        item.children.forEach(assignIds);
      }
    }
    assignIds(copy);
    return copy;
  }

  function copySelected() {
    if (!SF.selectedNode) return;
    SF.clipboard = nodeToObject(SF.selectedNode);
    SF.pasteCount = 0;
    updatePasteButton();
  }

  function updatePasteButton() {
    const btn = document.getElementById('pasteBtn');
    if (btn) btn.disabled = !SF.clipboard || !SF.canEdit;
  }

  function cutSelected() {
    if (!SF.selectedNode || !SF.canEdit) return;
    copySelected();
    removeAnimationBadge(SF.selectedNode);
    SF.selectedNode.destroy();
    deselect();
    refreshCanvas();
    scheduleSave();
  }

  SF.pasteCount = 0;
  function pasteClipboard() {
    if (!SF.clipboard || !SF.canEdit) return;
    SF.pasteCount++;
    const obj = cloneObjectWithNewIds(SF.clipboard);
    obj.x = (obj.x || 0) + 24 * SF.pasteCount;
    obj.y = (obj.y || 0) + 24 * SF.pasteCount;
    insertNode(createNode(obj));
  }

  let replaceTargetNode = null;

  async function uploadObjectAsset(kind, file) {
    if (!file) return;
    const kindLabel = { video: 'Video', audio: 'Audio', image: 'Bild' }[kind] || kind;
    setSaveStatus('Lade ' + kindLabel + ' hoch…');
    const formData = new FormData();
    formData.append('id', SF.id);
    formData.append('kind', kind);
    formData.append('csrf_token', SF.csrfToken);
    formData.append('file', file);
    try {
      const res = await fetch('upload.php', { method: 'POST', body: formData });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || 'Upload fehlgeschlagen');

      if (replaceTargetNode) {
        const node = replaceTargetNode;
        replaceTargetNode = null;
        node.setAttr('src', json.url);
        if (kind === 'image') loadImageAsync(node, json.url);
        refreshCanvas();
        refreshPropsPanel();
        scheduleSave();
        return;
      }

      const defaultSize = kind === 'audio' ? { w: 280, h: 56 } : { w: 400, h: 240 };
      const centerX = Math.round(SF.meta.width / 2) - defaultSize.w / 2;
      const centerY = Math.round(SF.meta.height / 2) - defaultSize.h / 2;
      const id = 'o' + Math.random().toString(16).slice(2, 10);
      const obj = { id, type: kind, x: centerX, y: centerY, w: defaultSize.w, h: defaultSize.h, rotation: 0, opacity: 1, src: json.url };
      insertNode(createNode(obj));
    } catch (e) {
      setSaveStatus('Fehler beim Hochladen', true);
      console.error(e);
    }
  }

  function initPixabayPanel() {
    if (!SF.pixabayConfig?.enabled || !window.SlideForgePixabay) return;

    async function applyPixabayAsset(mode, url, kind) {
      const isBg = mode === 'background-image' || mode === 'background-video';
      if (isBg) {
        const bgKind = mode === 'background-video' ? 'video' : 'image';
        setBgType(bgKind);
        SF.currentBackground = { type: bgKind, value: url };
        applyBackgroundVisual(SF.currentBackground);
        populateBackgroundControls(SF.currentBackground);
        updateCurrentTabSwatch();
        scheduleSave();
        return;
      }

      const objKind = kind === 'video' ? 'video' : 'image';
      const defaultSize = { w: 400, h: 240 };
      const centerX = Math.round(SF.meta.width / 2) - defaultSize.w / 2;
      const centerY = Math.round(SF.meta.height / 2) - defaultSize.h / 2;
      const id = 'o' + Math.random().toString(16).slice(2, 10);
      const obj = { id, type: objKind, x: centerX, y: centerY, w: defaultSize.w, h: defaultSize.h, rotation: 0, opacity: 1, src: url };
      const node = createNode(obj);
      insertNode(node);
      if (objKind === 'image') loadImageAsync(node, url);
    }

    window.SlideForgePixabay.init({
      id: SF.id,
      csrfToken: SF.csrfToken,
      pixabayConfig: SF.pixabayConfig,
      applyPixabay: applyPixabayAsset,
      refreshMediaLibrary: () => SF.refreshMediaLibrary?.(),
    });
  }

  function initIconifyPanel() {
    if (!SF.iconifyConfig?.enabled || !window.SlideForgeIconify) return;

    async function applyIconifyAsset(url, iconId, iconColor) {
      const defaultSize = { w: 128, h: 128 };
      const centerX = Math.round(SF.meta.width / 2) - defaultSize.w / 2;
      const centerY = Math.round(SF.meta.height / 2) - defaultSize.h / 2;
      const id = 'o' + Math.random().toString(16).slice(2, 10);
      const color = iconColor || defaultIconColor();
      const obj = {
        id, type: 'image', x: centerX, y: centerY, w: defaultSize.w, h: defaultSize.h,
        rotation: 0, opacity: 1, src: url, iconId: iconId || '', iconColor: color,
      };
      const node = createNode(obj);
      insertNode(node);
      loadImageAsync(node, url, color);
    }

    window.SlideForgeIconify.init({
      id: SF.id,
      csrfToken: SF.csrfToken,
      iconifyConfig: SF.iconifyConfig,
      defaultIconColor: defaultIconColor(),
      applyIconify: applyIconifyAsset,
      refreshMediaLibrary: () => SF.refreshMediaLibrary?.(),
    });
  }

  function initOpenclipartPanel() {
    if (!window.SlideForgeOpenclipart) return;

    const cfg = SF.openclipartConfig || {
      enabled: !!document.getElementById('openclipartOpenBtn'),
      i18n: {},
    };
    if (!cfg.enabled) return;

    async function applyOpenclipartAsset(url) {
      const defaultSize = { w: 256, h: 256 };
      const centerX = Math.round(SF.meta.width / 2) - defaultSize.w / 2;
      const centerY = Math.round(SF.meta.height / 2) - defaultSize.h / 2;
      const id = 'o' + Math.random().toString(16).slice(2, 10);
      const obj = {
        id, type: 'image', x: centerX, y: centerY, w: defaultSize.w, h: defaultSize.h,
        rotation: 0, opacity: 1, src: url,
      };
      const node = createNode(obj);
      insertNode(node);
      loadImageAsync(node, url);
    }

    window.SlideForgeOpenclipart.init({
      id: SF.id,
      csrfToken: SF.csrfToken,
      openclipartConfig: cfg,
      defaultIconColor: defaultIconColor(),
      applyOpenclipart: applyOpenclipartAsset,
      refreshMediaLibrary: () => SF.refreshMediaLibrary?.(),
    });
  }

  function initMediaSearchButtons() {
    document.getElementById('pixabayOpenBtn')?.addEventListener('click', () => {
      if (window.SlideForgePixabay?.openPixabayModal) {
        window.SlideForgePixabay.openPixabayModal('object-image');
        return;
      }
      document.getElementById('pixabayModal')?.classList.add('open');
    });

    document.getElementById('iconifyOpenBtn')?.addEventListener('click', () => {
      if (window.SlideForgeIconify?.openIconifyModal) {
        window.SlideForgeIconify.openIconifyModal();
        return;
      }
      const modal = document.getElementById('iconifyModal');
      if (modal) {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.getElementById('iconifyQuery')?.focus();
      }
    });

    document.getElementById('openclipartOpenBtn')?.addEventListener('click', () => {
      if (window.SlideForgeOpenclipart?.openOpenclipartModal) {
        window.SlideForgeOpenclipart.openOpenclipartModal();
        return;
      }
      const modal = document.getElementById('openclipartModal');
      if (modal) {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.getElementById('openclipartQuery')?.focus();
      }
    });
  }

  function initMediaLibraryPanel() {
    const cfg = boot.mediaLibrary?.i18n;
    const listEl = document.getElementById('mediaLibraryList');
    const statusEl = document.getElementById('mediaLibraryStatus');
    const refreshBtn = document.getElementById('mediaLibraryRefresh');
    const cleanupBtn = document.getElementById('mediaLibraryCleanup');
    if (!cfg || !listEl || !statusEl) return;

    function syncCleanupBtn(items) {
      if (!cleanupBtn) return;
      const unusedCount = (items || []).filter((item) => !item.usedOn || !item.usedOn.length).length;
      cleanupBtn.disabled = unusedCount === 0;
    }

    function formatBytes(n) {
      if (n < 1024) return n + ' B';
      if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
      return (n / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function kindLabel(kind) {
      if (kind === 'video') return cfg.kindVideo;
      if (kind === 'audio') return cfg.kindAudio;
      return cfg.kindImage;
    }

    function insertMediaAsset(url, kind) {
      if (!SF.canEdit || !url) return;
      let size;
      if (kind === 'audio') size = { w: 280, h: 56 };
      else if (kind === 'video') size = { w: 400, h: 260 };
      else size = { w: 400, h: 240 };
      const centerX = Math.round(SF.meta.width / 2) - size.w / 2;
      const centerY = Math.round(SF.meta.height / 2) - size.h / 2;
      const id = 'o' + Math.random().toString(16).slice(2, 10);
      const obj = {
        id,
        type: kind === 'video' ? 'video' : (kind === 'audio' ? 'audio' : 'image'),
        x: centerX,
        y: centerY,
        w: size.w,
        h: size.h,
        rotation: 0,
        opacity: 1,
        src: url,
      };
      const node = createNode(obj);
      insertNode(node);
      if (obj.type === 'image') loadImageAsync(node, url);
      scheduleSave();
      statusEl.textContent = cfg.inserted || '';
      setTimeout(() => {
        if (statusEl.textContent === (cfg.inserted || '')) statusEl.textContent = '';
      }, 2000);
      refreshMediaLibrary();
    }

    function setMediaBackground(url, kind) {
      if (!SF.canEdit || !url) return;
      if (kind !== 'image' && kind !== 'video') return;
      setBgType(kind);
      SF.currentBackground = { type: kind, value: url };
      applyBackgroundVisual(SF.currentBackground);
      populateBackgroundControls(SF.currentBackground);
      updateCurrentTabSwatch();
      scheduleSave();
      statusEl.textContent = cfg.backgroundSet || '';
      setTimeout(() => {
        if (statusEl.textContent === (cfg.backgroundSet || '')) statusEl.textContent = '';
      }, 2000);
      refreshMediaLibrary();
    }

    function renderList(items) {
      syncCleanupBtn(items);
      if (!items.length) {
        listEl.innerHTML = '<p class="media-library-empty">' + escapeHtml(cfg.empty) + '</p>';
        return;
      }
      listEl.innerHTML = items.map((item) => {
        const unused = !item.usedOn || !item.usedOn.length;
        const usageHtml = unused
          ? '<span class="media-library-unused">' + escapeHtml(cfg.unused) + '</span>'
          : '<span class="media-library-used">' + escapeHtml(cfg.usedOn) + ' ' +
            item.usedOn.map((i) =>
              '<button type="button" class="media-library-slide-link" data-slide="' + i + '">' +
              escapeHtml(cfg.slideN.replace('{n}', String(i + 1))) + '</button>'
            ).join(' ') + '</span>';
        const thumb = item.kind === 'image'
          ? '<img src="' + escapeHtml(item.url) + '" alt="" class="media-library-thumb">'
          : '<div class="media-library-thumb media-library-thumb--' + item.kind + '">' + escapeHtml(kindLabel(item.kind)) + '</div>';
        const deleteBtn = (SF.canEdit && unused)
          ? '<button type="button" class="button button-danger button-sm media-library-delete" data-file="' + escapeHtml(item.filename) + '">' + escapeHtml(cfg.delete) + '</button>'
          : '';
        const bgBtn = (SF.canEdit && (item.kind === 'image' || item.kind === 'video'))
          ? '<button type="button" class="button button-ghost button-sm media-library-bg-btn">' + escapeHtml(cfg.useBackground || 'Als Hintergrund') + '</button>'
          : '';
        const insertable = SF.canEdit ? ' is-insertable' : '';
        return '<div class="media-library-item' + (unused ? ' is-unused' : '') + insertable + '" data-url="' + escapeHtml(item.url) + '" data-kind="' + escapeHtml(item.kind) + '"' + (SF.canEdit ? ' role="button" tabindex="0"' : '') + '>' +
          thumb +
          '<div class="media-library-meta">' +
            '<div class="media-library-name" title="' + escapeHtml(item.filename) + '">' + escapeHtml(item.filename) + '</div>' +
            '<div class="media-library-details">' + escapeHtml(kindLabel(item.kind)) + ' · ' + formatBytes(item.size) + '</div>' +
            usageHtml +
            (bgBtn ? '<div class="media-library-actions">' + bgBtn + '</div>' : '') +
          '</div>' +
          deleteBtn +
        '</div>';
      }).join('');

      listEl.querySelectorAll('.media-library-item.is-insertable').forEach((row) => {
        const activate = () => insertMediaAsset(row.dataset.url, row.dataset.kind);
        row.addEventListener('click', (e) => {
          if (e.target.closest('.media-library-delete, .media-library-slide-link, .media-library-bg-btn')) return;
          activate();
        });
        row.addEventListener('keydown', (e) => {
          if (e.key !== 'Enter' && e.key !== ' ') return;
          if (e.target.closest('.media-library-delete, .media-library-slide-link, .media-library-bg-btn')) return;
          e.preventDefault();
          activate();
        });
      });

      listEl.querySelectorAll('.media-library-bg-btn').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          const row = btn.closest('.media-library-item');
          if (!row) return;
          setMediaBackground(row.dataset.url, row.dataset.kind);
        });
      });

      listEl.querySelectorAll('.media-library-slide-link').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          const idx = parseInt(btn.dataset.slide, 10);
          if (!isNaN(idx)) switchToSlide(idx);
        });
      });

      listEl.querySelectorAll('.media-library-delete').forEach((btn) => {
        btn.addEventListener('click', async (e) => {
          e.stopPropagation();
          const file = btn.dataset.file;
          if (!file || !confirm(cfg.deleteConfirm.replace('{name}', file))) return;
          try {
            const res = await api('delete_media_asset', { filename: file });
            renderList(res.items || []);
            statusEl.textContent = '';
          } catch (err) {
            statusEl.textContent = err.message || cfg.errorGeneric;
          }
        });
      });
    }

    async function refreshMediaLibrary() {
      statusEl.textContent = cfg.loading;
      try {
        const res = await api('list_media');
        renderList(res.items || []);
        statusEl.textContent = '';
      } catch (e) {
        statusEl.textContent = e.message || cfg.errorGeneric;
        listEl.innerHTML = '';
        syncCleanupBtn([]);
      }
    }

    async function cleanupUnusedMedia() {
      if (!SF.canEdit) return;
      let items = [];
      try {
        const res = await api('list_media');
        items = res.items || [];
      } catch (e) {
        statusEl.textContent = e.message || cfg.errorGeneric;
        return;
      }
      const unused = items.filter((item) => !item.usedOn || !item.usedOn.length);
      if (!unused.length) {
        statusEl.textContent = cfg.cleanupNone || '';
        syncCleanupBtn(items);
        return;
      }
      if (!confirm((cfg.cleanupConfirm || '').replace('{count}', String(unused.length)))) return;
      statusEl.textContent = cfg.loading;
      try {
        const res = await api('cleanup_unused_media', {});
        renderList(res.items || []);
        const count = res.count || 0;
        statusEl.textContent = (cfg.cleanupDone || '').replace('{count}', String(count));
        setTimeout(() => {
          if (statusEl.textContent === (cfg.cleanupDone || '').replace('{count}', String(count))) {
            statusEl.textContent = '';
          }
        }, 3000);
      } catch (e) {
        statusEl.textContent = e.message || cfg.errorGeneric;
      }
    }

    refreshBtn?.addEventListener('click', refreshMediaLibrary);
    cleanupBtn?.addEventListener('click', cleanupUnusedMedia);

    document.querySelectorAll('.media-subnav-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const sub = btn.dataset.mediasub;
        document.querySelectorAll('.media-subnav-btn').forEach((b) => b.classList.toggle('active', b === btn));
        document.querySelectorAll('.media-sub-panel').forEach((p) => {
          const active = p.dataset.mediasub === sub;
          p.classList.toggle('active', active);
          p.hidden = !active;
        });
        if (sub === 'library') refreshMediaLibrary();
      });
    });

    SF.refreshMediaLibrary = refreshMediaLibrary;
  }

  function initSpellcheckPanel() {
    if (!SF.spellConfig?.enabled || !window.SlideForgeSpellcheck) return;

    async function persistSlideData(index) {
      const slide = SF.slides[index];
      if (!slide) return;
      await api('save_slide', {
        index,
        background: slide.background || { type: 'color', value: '#111111' },
        objects: slide.objects || [],
        transition: slide.transition || 'slide',
        autoAdvance: slide.autoAdvance || 0,
        notes: slide.notes || '',
      });
    }

    function replaceSegmentText(text, issue, suggestion) {
      const needle = issue.plainWrong || issue.wrong || '';
      const letter = /[\p{L}\p{M}']/u;

      if (!needle) {
        const start = issue.origOffset ?? 0;
        const len = issue.origLength ?? 0;
        return text.slice(0, start) + suggestion + text.slice(start + len);
      }

      const hint = Math.max(0, (issue.origOffset ?? 0) - 10);
      let idx = text.indexOf(needle, hint);
      if (idx < 0) idx = text.indexOf(needle);
      if (idx < 0 && issue.wrong && issue.wrong !== needle) {
        idx = text.indexOf(issue.wrong, hint);
        if (idx < 0) idx = text.indexOf(issue.wrong);
      }
      if (idx < 0) {
        const start = issue.origOffset ?? 0;
        return text.slice(0, start) + suggestion + text.slice(start + (issue.origLength ?? 0));
      }

      const start = idx;
      let end = idx + needle.length;
      // Nur nach vorne erweitern, wenn LanguageTool nur einen Wortanfang markiert hat (z. B. «Lucka» statt «Luckas»).
      while (end < text.length && letter.test(text[end])) {
        end++;
      }

      return text.slice(0, start) + suggestion + text.slice(end);
    }

    function finishGotoIssue(issue) {
      if (issue.kind === 'title') {
        document.querySelector('[data-settings-open="slides"]')?.click();
        document.getElementById('edTitle')?.focus();
        return;
      }
      if (issue.kind === 'notes') {
        document.getElementById('slideNotesInput')?.focus();
        return;
      }
      const parts = (issue.segmentId || '').split(':');
      const objId = parts[parts.length - 1];
      const node = SF.layer.findOne('#' + objId);
      if (node) {
        selectNode(node);
        editTextInline(node);
      }
    }

    window.SlideForgeSpellcheck.init({
      id: SF.id,
      spellConfig: SF.spellConfig,
      syncCurrentSlide: () => saveCurrentSlide(true),
      async applyCorrection(issue, suggestion) {
        if (issue.segmentId === 'title') {
          const titleEl = document.getElementById('edTitle');
          const text = titleEl?.value ?? SF.meta.title ?? '';
          const next = replaceSegmentText(text, issue, suggestion);
          if (titleEl) titleEl.value = next;
          SF.meta.title = next;
          const headerTitle = document.querySelector('.topbar-context-title');
          if (headerTitle) headerTitle.textContent = next;
          await api('save_meta', { title: next });
          return true;
        }
        const slideIndex = issue.slideIndex;
        if (slideIndex == null || !SF.slides[slideIndex]) return false;
        const slide = SF.slides[slideIndex];

        if (issue.kind === 'notes') {
          const next = replaceSegmentText(slide.notes || '', issue, suggestion);
          slide.notes = next;
          if (SF.currentIndex === slideIndex) {
            const notesEl = document.getElementById('slideNotesInput');
            if (notesEl) notesEl.value = next;
            syncFilmstripNotesBadge(slideIndex);
            scheduleSave();
          } else {
            await persistSlideData(slideIndex);
          }
          return true;
        }

        const parts = (issue.segmentId || '').split(':');
        const objId = parts[parts.length - 1];
        const obj = (slide.objects || []).find((o) => o.id === objId);
        if (!obj) return false;
        obj.text = replaceSegmentText(obj.text || '', issue, suggestion);
        if (SF.currentIndex === slideIndex) {
          const node = SF.layer.findOne('#' + objId);
          if (node) node.text(obj.text);
          refreshCanvas();
          refreshPropsPanel();
          scheduleSave();
        } else {
          await persistSlideData(slideIndex);
        }
        return true;
      },
      gotoIssue(issue) {
        if (issue.kind === 'title') {
          finishGotoIssue(issue);
          return;
        }
        const slideIndex = issue.slideIndex;
        if (slideIndex != null && slideIndex !== SF.currentIndex) {
          saveCurrentSlide(true).then(() => {
            loadSlideIntoStage(slideIndex);
            updateFilmstripActive();
            finishGotoIssue(issue);
          });
        } else {
          finishGotoIssue(issue);
        }
      },
    });
  }

  function bindGlobalUI() {
    if (!SF.canEdit) return;

    document.querySelectorAll('.tool-btn-block').forEach(btn => {
      if (btn.id === 'pixabayOpenBtn' || btn.id === 'iconifyOpenBtn' || btn.id === 'openclipartOpenBtn') return;
      btn.addEventListener('click', () => {
        if (btn.dataset.tool) addShape(btn.dataset.tool);
        else if (btn.dataset.preset) addTextPreset(btn.dataset.preset);
      });
    });

    document.querySelectorAll('.obj-tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.obj-tab-btn').forEach(b => b.classList.toggle('active', b === btn));
        document.querySelectorAll('.obj-tab-panel').forEach(p => {
          p.classList.toggle('active', p.dataset.objtab === btn.dataset.objtab);
        });
      });
    });

    document.getElementById('addSlideBtn')?.addEventListener('click', async () => {
      await saveCurrentSlide(true);
      const res = await api('add_slide', { after_index: SF.currentIndex });
      SF.slides = res.slides;
      SF.currentIndex = SF.currentIndex + 1;
      loadSlideIntoStage(SF.currentIndex);
      await renderSlideFilmstrip();
      reloadPreviewWindow();
    });

    document.getElementById('presentModeLink')?.addEventListener('click', async (e) => {
      updatePresentLinkOnSlideChange();
      if (!SF.spellConfig?.beforePresent || !window.SlideForgeSpellcheck?.ensureCleanBeforePresent) return;
      const link = document.getElementById('presentModeLink');
      const href = link?.href;
      if (!href) return;
      e.preventDefault();
      const allow = await window.SlideForgeSpellcheck.ensureCleanBeforePresent(href);
      if (allow) window.location.href = href;
    });

    (function initSpellcheckBeforePresentToggle() {
      const cb = document.getElementById('spellcheckBeforePresentToggle');
      if (!cb || !SF.spellConfig?.enabled) return;
      cb.addEventListener('change', async () => {
        const enabled = cb.checked;
        try {
          const res = await fetch('user_api.php?action=set_spellcheck_before_present', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: SF.csrfToken, before_present: enabled }),
          });
          const data = await res.json();
          if (!data.ok) {
            cb.checked = !enabled;
            return;
          }
          SF.spellConfig.beforePresent = !!data.before_present;
        } catch (err) {
          cb.checked = !enabled;
        }
      });
    })();

    if (SF.presentConfig?.enabled && window.SlideForgePresentConfig) {
      SF.presentConfigApi = window.SlideForgePresentConfig.init({
        id: SF.id,
        csrfToken: SF.csrfToken,
        i18n: SF.presentConfig.i18n,
        onDisplayOptionChange(field, value) {
          const cb = document.querySelector('input[name="' + field + '"][form="metaSettingsForm"]');
          if (cb) cb.checked = !!value;
        },
      });
    }

    (function initEditorTopbarMenus() {
      const wraps = document.querySelectorAll('[data-editor-menu]');
      if (!wraps.length) return;

      function closeSettingsPanels(wrap) {
        wrap.querySelectorAll('[data-settings-panel]').forEach((p) => { p.hidden = true; });
        const submenu = wrap.querySelector('[data-settings-submenu]');
        if (submenu) submenu.hidden = true;
      }

      function closeWrap(wrap) {
        const btn = wrap.querySelector('[data-menu-btn]');
        if (wrap.hasAttribute('data-settings-menu')) {
          closeSettingsPanels(wrap);
        } else {
          const panel = wrap.querySelector('[data-menu-panel]');
          if (panel) panel.hidden = true;
        }
        if (btn) {
          btn.setAttribute('aria-expanded', 'false');
          btn.classList.remove('open');
        }
      }

      function closeAll() {
        wraps.forEach(closeWrap);
      }

      function isSettingsOpen(wrap) {
        const submenu = wrap.querySelector('[data-settings-submenu]');
        if (submenu && !submenu.hidden) return true;
        return Array.from(wrap.querySelectorAll('[data-settings-panel]')).some((p) => !p.hidden);
      }

      wraps.forEach((wrap) => {
        const btn = wrap.querySelector('[data-menu-btn]');
        if (!btn) return;

        if (wrap.hasAttribute('data-settings-menu')) {
          const submenu = wrap.querySelector('[data-settings-submenu]');
          const panels = wrap.querySelectorAll('[data-settings-panel]');

          btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const willOpen = !isSettingsOpen(wrap);
            closeAll();
            if (willOpen && submenu) {
              submenu.hidden = false;
              btn.setAttribute('aria-expanded', 'true');
              btn.classList.add('open');
            }
          });

          wrap.querySelectorAll('[data-settings-open]').forEach((item) => {
            item.addEventListener('click', (e) => {
              e.stopPropagation();
              const id = item.getAttribute('data-settings-open');
              if (submenu) submenu.hidden = true;
              panels.forEach((p) => { p.hidden = p.getAttribute('data-settings-panel') !== id; });
            });
          });

          wrap.querySelectorAll('[data-settings-back]').forEach((backBtn) => {
            backBtn.addEventListener('click', () => {
              closeSettingsPanels(wrap);
              if (submenu) submenu.hidden = false;
            });
          });

          wrap.querySelectorAll('[data-menu-close]').forEach((closeBtn) => {
            closeBtn.addEventListener('click', () => closeWrap(wrap));
          });
          return;
        }

        const panel = wrap.querySelector('[data-menu-panel]');
        if (!panel) return;

        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          const willOpen = panel.hidden;
          closeAll();
          if (willOpen) {
            updatePresentLinkOnSlideChange();
            if (wrap.hasAttribute('data-present-menu')) {
              SF.presentConfigApi?.refreshScreens?.();
            }
            panel.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
            btn.classList.add('open');
          }
        });

        panel.querySelectorAll('[data-menu-close]').forEach((closeBtn) => {
          closeBtn.addEventListener('click', () => closeWrap(wrap));
        });
        panel.querySelectorAll('.dropdown-menu-item').forEach((item) => {
          item.addEventListener('click', () => closeWrap(wrap));
        });
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeAll();
      });
      document.addEventListener('click', (e) => {
        if (e.target.closest('[data-editor-menu]')) return;
        closeAll();
      });
    })();

    document.getElementById('previewWindowBtn')?.addEventListener('click', () => {
      const w = Math.min(1280, SF.meta.width);
      const h = Math.round(w * (SF.meta.height / SF.meta.width)) + 40;
      const url = 'preview.php?id=' + encodeURIComponent(SF.id) + '&slide=' + SF.currentIndex;
      const name = 'sf_preview_' + SF.id;
      if (SF.previewWindow && !SF.previewWindow.closed) {
        SF.previewWindow.location.href = url;
        SF.previewWindow.focus();
        return;
      }
      SF.previewWindow = window.open(url, name, 'popup=1,width=' + w + ',height=' + h);
    });

    document.getElementById('objImageInput').addEventListener('change', (e) => {
      const f = e.target.files[0]; e.target.value = '';
      uploadObjectAsset('image', f);
    });
    document.getElementById('objVideoInput').addEventListener('change', (e) => {
      const f = e.target.files[0]; e.target.value = '';
      uploadObjectAsset('video', f);
    });
    document.getElementById('objAudioInput').addEventListener('change', (e) => {
      const f = e.target.files[0]; e.target.value = '';
      uploadObjectAsset('audio', f);
    });

    document.querySelectorAll('.bg-type-btn').forEach(btn => {
      btn.addEventListener('click', () => setBgType(btn.dataset.bgtype));
    });

    document.getElementById('bgColorInput').addEventListener('input', (e) => {
      SF.currentBackground = { type: 'color', value: e.target.value };
      applyBackgroundVisual(SF.currentBackground);
      updateCurrentTabSwatch();
      scheduleSave();
    });

    ['bgGradColor1', 'bgGradColor2', 'bgGradAngle'].forEach(id => {
      document.getElementById(id).addEventListener('input', () => {
        document.getElementById('bgGradAngleVal').textContent = document.getElementById('bgGradAngle').value;
        SF.currentBackground = buildGradientBg();
        applyBackgroundVisual(SF.currentBackground);
        updateCurrentTabSwatch();
        scheduleSave();
      });
    });

    document.getElementById('bgImageInput').addEventListener('change', (e) => {
      uploadAsset('image', e.target.files[0]);
      e.target.value = '';
    });
    document.getElementById('bgVideoInput').addEventListener('change', (e) => {
      uploadAsset('video', e.target.files[0]);
      e.target.value = '';
    });
    document.getElementById('bgImageRemove').addEventListener('click', () => {
      SF.currentBackground = { type: 'image', value: '' };
      applyBackgroundVisual(SF.currentBackground);
      populateBackgroundControls(SF.currentBackground);
      updateCurrentTabSwatch();
      scheduleSave();
    });
    document.getElementById('bgVideoRemove').addEventListener('click', () => {
      SF.currentBackground = { type: 'video', value: '' };
      applyBackgroundVisual(SF.currentBackground);
      populateBackgroundControls(SF.currentBackground);
      updateCurrentTabSwatch();
      scheduleSave();
    });

    renderBrandPalette();

    document.getElementById('applyTransitionAllBtn')?.addEventListener('click', async () => {
      const value = getTransitionValue();
      setSaveStatus('Speichere…');
      try {
        const res = await api('apply_transition_all', { transition: value });
        SF.slides = res.slides;
        setSaveStatus('Gespeichert');
        reloadPreviewWindow();
      } catch (e) {
        setSaveStatus('Fehler beim Speichern');
      }
    });
    document.getElementById('undoBtn')?.addEventListener('click', undo);
    document.getElementById('redoBtn')?.addEventListener('click', redo);
    document.getElementById('pasteBtn')?.addEventListener('click', pasteClipboard);
    document.getElementById('dupObjBtn')?.addEventListener('click', () => { if (SF.selectedNode) duplicateNode(SF.selectedNode); });
    document.getElementById('copyObjBtn')?.addEventListener('click', copySelected);
    document.getElementById('cutObjBtn')?.addEventListener('click', cutSelected);
    document.getElementById('groupObjBtn')?.addEventListener('click', groupSelectedNodes);
    document.getElementById('ungroupObjBtn')?.addEventListener('click', ungroupSelected);

    // Akkordeon-Umschaltung, gescoped je Container (data-accordion-name) - funktioniert
    // gleichermassen für das Eigenschaften-Panel (Text) und den Folien-Eigenschaften-Dialog,
    // ohne dass sich mehrere Akkordeons auf der Seite gegenseitig zuklappen.
    document.addEventListener('click', (e) => {
      const header = e.target.closest('.props-accordion-header');
      if (!header) return;
      const container = header.closest('.props-accordion');
      const group = header.closest('.props-accordion-group');
      if (!container || !group) return;
      const groupId = group.dataset.accordionGroup;
      const willOpen = !group.classList.contains('open');
      if (container.dataset.accordionName === 'textProps') {
        SF.activeFormatGroup = willOpen ? groupId : null;
      }
      container.querySelectorAll('.props-accordion-group').forEach((g) => {
        if (willOpen) g.classList.toggle('open', g.dataset.accordionGroup === groupId);
        else g.classList.remove('open');
      });
      if (willOpen && container.dataset.accordionName === 'textProps' && groupId === 'edit') {
        scheduleAutoGrowTextarea();
      }
    });
    document.getElementById('autoAdvanceInput').addEventListener('input', scheduleSave);
    document.getElementById('slideNotesInput')?.addEventListener('input', () => {
      if (SF.slides[SF.currentIndex]) {
        SF.slides[SF.currentIndex].notes = document.getElementById('slideNotesInput')?.value || '';
        syncFilmstripNotesBadge(SF.currentIndex);
      }
      scheduleSave();
    });
    applySpellcheckAttrs(document.getElementById('slideNotesInput'));
    applySpellcheckAttrs(document.getElementById('edTitle'));

    document.addEventListener('keydown', (e) => {
      const tag = (document.activeElement && document.activeElement.tagName) || '';
      if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) return;
      if ((e.key === 'Delete' || e.key === 'Backspace') && SF.selectedNodes.length) {
        e.preventDefault();
        SF.selectedNodes.slice().forEach((node) => {
          removeAnimationBadge(node);
          node.destroy();
        });
        deselect();
        refreshCanvas();
        scheduleSave();
        return;
      }
      if (SF.selectedNodes.length && ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) {
        e.preventDefault();
        const step = e.shiftKey ? 10 : 1;
        SF.selectedNodes.forEach((node) => {
          if (e.key === 'ArrowLeft') node.x(node.x() - step);
          else if (e.key === 'ArrowRight') node.x(node.x() + step);
          else if (e.key === 'ArrowUp') node.y(node.y() - step);
          else if (e.key === 'ArrowDown') node.y(node.y() + step);
          if (!isObjectGroup(node)) updateAnimationBadge(node);
          else node.getChildren().forEach((child) => updateAnimationBadge(child));
        });
        refreshCanvas();
        scheduleSave();
        refreshPropsPanel();
        return;
      }
      const mod = e.ctrlKey || e.metaKey;
      if (!mod) return;
      const key = e.key.toLowerCase();
      if (key === 'z' && !e.shiftKey) { e.preventDefault(); undo(); }
      else if ((key === 'z' && e.shiftKey) || key === 'y') { e.preventDefault(); redo(); }
      else if (key === 'd' && SF.selectedNode) { e.preventDefault(); duplicateNode(SF.selectedNode); }
      else if (key === 'c' && SF.selectedNode) { e.preventDefault(); copySelected(); }
      else if (key === 'x' && SF.selectedNode) { e.preventDefault(); cutSelected(); }
      else if (key === 'v' && SF.clipboard) { e.preventDefault(); pasteClipboard(); }
      else if (key === 'g' && e.shiftKey) { e.preventDefault(); ungroupSelected(); }
      else if (key === 'g' && !e.shiftKey) { e.preventDefault(); groupSelectedNodes(); }
    });
  }

  // ---------- Undo / Redo ----------
  SF.history = [];
  SF.historyIndex = -1;
  SF.restoringHistory = false;

  function snapshotCurrentSlide() {
    const objects = getTopLevelNodes().map(nodeToObject);
    const background = SF.currentBackground;
    const transitionEl = document.getElementById('transitionSelect');
    const transition = transitionEl ? getTransitionValue() : 'slide';
    const autoAdvanceEl = document.getElementById('autoAdvanceInput');
    const autoAdvance = autoAdvanceEl ? (parseInt(autoAdvanceEl.value, 10) || 0) : 0;
    const notesEl = document.getElementById('slideNotesInput');
    const notes = notesEl ? notesEl.value : '';
    return { background, objects, transition, autoAdvance, notes };
  }

  function updateUndoRedoButtons() {
    const undoBtn = document.getElementById('undoBtn');
    const redoBtn = document.getElementById('redoBtn');
    if (undoBtn) undoBtn.disabled = SF.historyIndex <= 0;
    if (redoBtn) redoBtn.disabled = SF.historyIndex >= SF.history.length - 1;
  }

  function resetHistoryForCurrentSlide() {
    if (!SF.canEdit) return;
    SF.history = [snapshotCurrentSlide()];
    SF.historyIndex = 0;
    updateUndoRedoButtons();
  }

  function pushHistory() {
    if (SF.restoringHistory || !SF.canEdit) return;
    const snap = snapshotCurrentSlide();
    const json = JSON.stringify(snap);
    if (SF.historyIndex >= 0 && JSON.stringify(SF.history[SF.historyIndex]) === json) return;
    SF.history = SF.history.slice(0, SF.historyIndex + 1);
    SF.history.push(snap);
    if (SF.history.length > 60) SF.history.shift();
    SF.historyIndex = SF.history.length - 1;
    updateUndoRedoButtons();
  }

  function restoreHistorySnapshot(snap) {
    SF.restoringHistory = true;
    SF.slides[SF.currentIndex].background = snap.background;
    SF.slides[SF.currentIndex].objects = JSON.parse(JSON.stringify(snap.objects));
    SF.slides[SF.currentIndex].transition = snap.transition;
    SF.slides[SF.currentIndex].autoAdvance = snap.autoAdvance;
    SF.slides[SF.currentIndex].notes = snap.notes;
    loadSlideIntoStage(SF.currentIndex, true);
    SF.restoringHistory = false;
    updateUndoRedoButtons();
    scheduleSave();
  }

  function undo() {
    if (!SF.canEdit || SF.historyIndex <= 0) return;
    SF.historyIndex--;
    restoreHistorySnapshot(SF.history[SF.historyIndex]);
  }

  function redo() {
    if (!SF.canEdit || SF.historyIndex >= SF.history.length - 1) return;
    SF.historyIndex++;
    restoreHistorySnapshot(SF.history[SF.historyIndex]);
  }

  // ---------- Saving ----------
  function scheduleSave() {
    setSaveStatus('Ungespeicherte Änderungen…');
    clearTimeout(SF.saveTimer);
    SF.saveTimer = setTimeout(() => saveCurrentSlide(false), 700);
  }

  async function saveCurrentSlide(immediate) {
    clearTimeout(SF.saveTimer);
    if (!SF.canEdit) return;
    pushHistory();
    const objects = getTopLevelNodes().map(nodeToObject);
    const background = SF.currentBackground;
    const transitionEl = document.getElementById('transitionSelect');
    const transition = transitionEl ? getTransitionValue() : 'slide';
    const autoAdvanceEl = document.getElementById('autoAdvanceInput');
    const autoAdvance = autoAdvanceEl ? (parseInt(autoAdvanceEl.value, 10) || 0) : 0;
    const notesEl = document.getElementById('slideNotesInput');
    const notes = notesEl ? notesEl.value : '';

    setSaveStatus('Speichere…');
    try {
      await api('save_slide', { index: SF.currentIndex, background, objects, transition, autoAdvance, notes });
      if (SF.slides[SF.currentIndex]) {
        SF.slides[SF.currentIndex].background = background;
        SF.slides[SF.currentIndex].objects = objects;
        SF.slides[SF.currentIndex].transition = transition;
        SF.slides[SF.currentIndex].autoAdvance = autoAdvance;
        SF.slides[SF.currentIndex].notes = notes;
      }
      setSaveStatus('Gespeichert');
      updateCurrentTabSwatch();
      refreshCurrentFilmstripThumb();
      if (!SF.skipPreviewReload) reloadPreviewWindow();
    } catch (e) {
      setSaveStatus('Fehler beim Speichern', true);
      console.error(e);
    }
  }

  document.addEventListener('DOMContentLoaded', init);
})();
