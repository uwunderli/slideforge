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
    transformSnapStart: null,
    activePropsTab: 'format',
    activeFormatGroup: null,
    activeSideTab: localStorage.getItem('sf_side_tab') || 'templates',
    activePosSubtab: localStorage.getItem('sf_pos_subtab') || 'layout',
    activeFormatSubtab: localStorage.getItem('sf_format_subtab') || 'text',
    layersPanelOpen: true,
    objectPanelOpen: true,
    templatesPanelOpen: true,
    mediaPanelOpen: true,
    selectionPanelOpen: true,
    editorViewMode: 'slide',
    gridThumbMin: boot.editorGridThumbMin || 168,
    brandColors: boot.brandColors || [],
    textTemplates: boot.textTemplates || [],
    templateMode: !!boot.templateMode,
    layoutSetMode: !!boot.layoutSetMode,
    masterSlideEditing: !!boot.masterSlideEditing,
    hasLayoutSet: !!boot.hasLayoutSet,
    canImportSlideToSet: !!boot.canImportSlideToSet,
    importLayoutSetId: boot.importLayoutSetId || '',
    logosImporterEnabled: !!boot.logosImporterEnabled,
    logosImportedRoles: boot.logosImportedRoles || [],
    logosExtraRoles: boot.logosExtraRoles || [],
    logosZonesAccordionOpen: boot.logosZonesAccordionOpen !== false,
    logosLayoutMap: boot.logosLayoutMap || {},
    logosLayoutSlideIds: boot.logosLayoutSlideIds || {},
    logosRoleLabels: boot.logosRoleLabels || {},
    logosPlaceholderRoles: boot.logosPlaceholderRoles || [],
    elementTextLinks: boot.elementTextLinks || {},
    elementLinkRoles: boot.elementLinkRoles || [],
    standardElementRoles: boot.standardElementRoles || [],
    logosElementLinkRoles: boot.logosElementLinkRoles || [],
    elementZones: boot.elementZones || {},
    logosImportSettings: boot.logosImportSettings || {},
    elementZoneKeys: boot.elementZoneKeys || ['slides', 'footer', 'custom', 'unused'],
    elementIconHtml: boot.elementIconHtml || {},
    logosBadgeHtml: boot.logosBadgeHtml || '',
    presentConfig: boot.presentConfig || null,
    share: boot.share || null,
    spellConfig: boot.spellcheck || null,
    pixabayConfig: boot.pixabay || null,
    iconifyConfig: boot.iconify || null,
    openclipartConfig: boot.openclipart || null,
    webdav: boot.webdav || null,
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

  function sampleCubicBezier(x0, y0, x1, y1, x2, y2, x3, y3, steps) {
    const pts = [];
    const n = Math.max(2, steps | 0);
    for (let i = 0; i <= n; i++) {
      const t = i / n;
      const u = 1 - t;
      const a = u * u * u;
      const b = 3 * u * u * t;
      const c = 3 * u * t * t;
      const d = t * t * t;
      pts.push(a * x0 + b * x1 + c * x2 + d * x3, a * y0 + b * y1 + c * y2 + d * y3);
    }
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
      // Rund: elliptischer Halbbogen (typografische Klammer), Spitzen mit horizontaler Tangente
      // kappa ≈ 0.55228475 für Kreis-/Ellipsen-Viertelbögen
      const k = 0.55228475;
      const segs = isRight
        ? [
            [0.00, 0.00, k, 0.00, 1.00, 0.5 - k * 0.5, 1.00, 0.50],
            [1.00, 0.50, 1.00, 0.5 + k * 0.5, k, 1.00, 0.00, 1.00],
          ]
        : [
            [1.00, 0.00, 1 - k, 0.00, 0.00, 0.5 - k * 0.5, 0.00, 0.50],
            [0.00, 0.50, 0.00, 0.5 + k * 0.5, 1 - k, 1.00, 1.00, 1.00],
          ];
      segs.forEach((s, si) => {
        const sampled = sampleCubicBezier(s[0], s[1], s[2], s[3], s[4], s[5], s[6], s[7], 16);
        const start = si === 0 ? 0 : 2;
        for (let i = start; i < sampled.length; i += 2) {
          push(sampled[i], sampled[i + 1]);
        }
      });
    } else {
      // Geschweift: typografische Accolade aus 4 kubischen Béziers (PowerPoint-ähnlich)
      // Links: Spitze nach links; rechts: gespiegelt.
      const segs = isRight
        ? [
            [0.08, 0.00, 0.08, 0.06, 0.52, 0.10, 0.52, 0.28],
            [0.52, 0.28, 0.52, 0.42, 0.95, 0.46, 1.00, 0.50],
            [1.00, 0.50, 0.95, 0.54, 0.52, 0.58, 0.52, 0.72],
            [0.52, 0.72, 0.52, 0.90, 0.08, 0.94, 0.08, 1.00],
          ]
        : [
            [0.92, 0.00, 0.92, 0.06, 0.48, 0.10, 0.48, 0.28],
            [0.48, 0.28, 0.48, 0.42, 0.05, 0.46, 0.00, 0.50],
            [0.00, 0.50, 0.05, 0.54, 0.48, 0.58, 0.48, 0.72],
            [0.48, 0.72, 0.48, 0.90, 0.92, 0.94, 0.92, 1.00],
          ];
      segs.forEach((s, si) => {
        const sampled = sampleCubicBezier(s[0], s[1], s[2], s[3], s[4], s[5], s[6], s[7], 12);
        const start = si === 0 ? 0 : 2; // Endpunkt der vorigen Kurve nicht doppelt
        for (let i = start; i < sampled.length; i += 2) {
          push(sampled[i], sampled[i + 1]);
        }
      });
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
    if (shapeType === 'bracket') return bracketPoints(w, h, obj.bracketStyle || 'curly-left');
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

  const RIBBON_COMMAND_DOM_IDS = {
    undo: 'undoBtn',
    redo: 'redoBtn',
    paste: 'pasteBtn',
    duplicate: 'dupObjBtn',
    copy: 'copyObjBtn',
    cut: 'cutObjBtn',
    group: 'groupObjBtn',
    ungroup: 'ungroupObjBtn',
    slide_grid_view: 'slideGridViewBtn',
    spellcheck: 'spellcheckBtn',
    add_slide: 'addSlideBtnRibbon',
    apply_transition_selected: 'applyTransitionSelectedBtn',
  };

  function setRibbonCommandsDisabled(commandKey, disabled) {
    const ribbonEl = document.getElementById('editorRibbon');
    if (!ribbonEl) return;
    const buttons = new Set();
    ribbonEl.querySelectorAll('[data-ribbon-command="' + commandKey + '"]').forEach((el) => {
      if (el.matches('button')) buttons.add(el);
    });
    const domId = RIBBON_COMMAND_DOM_IDS[commandKey];
    if (domId) {
      const el = document.getElementById(domId);
      if (el && ribbonEl.contains(el) && el.matches('button')) buttons.add(el);
    }
    buttons.forEach((btn) => { btn.disabled = disabled; });
  }

  function setRibbonCommandsHidden(commandKey, hidden) {
    const ribbonEl = document.getElementById('editorRibbon');
    if (!ribbonEl) return;
    const nodes = new Set();
    ribbonEl.querySelectorAll('[data-ribbon-command="' + commandKey + '"]').forEach((el) => nodes.add(el));
    const domId = RIBBON_COMMAND_DOM_IDS[commandKey];
    if (domId) {
      const el = document.getElementById(domId);
      if (el && ribbonEl.contains(el)) nodes.add(el);
    }
    nodes.forEach((el) => {
      const cell = el.closest('.ribbon-grid-cell');
      if (cell) cell.hidden = hidden;
      else el.hidden = hidden;
    });
  }

  function syncApplyTransitionSelectedVisibility() {
    setRibbonCommandsHidden('apply_transition_selected', SF.editorViewMode !== 'grid');
  }

  function syncRibbonCommandStates() {
    updateSelectionActionButtons();
    updatePasteButton();
    updateUndoRedoButtons();
    syncApplyTransitionSelectedVisibility();
  }

  function initRibbonCommandDelegation() {
    const ribbonEl = document.getElementById('editorRibbon');
    if (!ribbonEl || ribbonEl.dataset.ribbonCmdDelegate === '1') return;
    ribbonEl.dataset.ribbonCmdDelegate = '1';

    ribbonEl.addEventListener('click', (e) => {
      if (!SF.canEdit) return;

      const toolBtn = e.target.closest('.tool-btn-block, .ribbon-tool-btn, .ribbon-btn[data-tool], .ribbon-insert-icon[data-tool]');
      if (toolBtn && ribbonEl.contains(toolBtn)) {
        if (toolBtn.id === 'pixabayOpenBtn' || toolBtn.id === 'iconifyOpenBtn' || toolBtn.id === 'openclipartOpenBtn') return;
        if (toolBtn.closest('#logosSlideInsertButtons')) return;
        if (toolBtn.dataset.setRole) {
          addLogosPlaceholder(toolBtn.dataset.setRole);
          return;
        }
        if (toolBtn.dataset.tool) {
          addShape(toolBtn.dataset.tool);
          return;
        }
        if (toolBtn.dataset.preset) {
          addTextPreset(toolBtn.dataset.preset);
          return;
        }
      }

      const mediaBtn = e.target.closest('[data-media-action]');
      if (mediaBtn && ribbonEl.contains(mediaBtn)) {
        const action = mediaBtn.dataset.mediaAction;
        if (action === 'image') document.getElementById('objImageInput')?.click();
        else if (action === 'audio') document.getElementById('objAudioInput')?.click();
        else if (action === 'video') document.getElementById('objVideoInput')?.click();
        else if (action === 'pixabay') document.getElementById('pixabayOpenBtn')?.click();
        else if (action === 'iconify') document.getElementById('iconifyOpenBtn')?.click();
        else if (action === 'openclipart') document.getElementById('openclipartOpenBtn')?.click();
        return;
      }

      const bgTypeBtn = e.target.closest('.bg-type-btn[data-bgtype]');
      if (bgTypeBtn && ribbonEl.contains(bgTypeBtn)) {
        const type = bgTypeBtn.dataset.bgtype;
        if (type === 'gradient') {
          openSlideBgGradientModal();
          return;
        }
        setBgType(type);
        if (SF.canEdit && (type === 'image' || type === 'video')) {
          openSlideBgMediaModal(type);
        }
        return;
      }

      const cmdEl = e.target.closest('button[data-ribbon-command], a[data-ribbon-command], button[id], a[id]');
      if (!cmdEl || !ribbonEl.contains(cmdEl) || cmdEl.disabled || cmdEl.getAttribute('aria-disabled') === 'true' || cmdEl.classList.contains('is-master-disabled')) return;

      const cmdKey = cmdEl.dataset.ribbonCommand || ({
        undoBtn: 'undo',
        redoBtn: 'redo',
        pasteBtn: 'paste',
        dupObjBtn: 'duplicate',
        copyObjBtn: 'copy',
        cutObjBtn: 'cut',
        groupObjBtn: 'group',
        ungroupObjBtn: 'ungroup',
        slideGridViewBtn: 'slide_grid_view',
        addSlideBtnRibbon: 'add_slide',
        zoomInBtn: 'zoom_in',
        zoomOutBtn: 'zoom_out',
        zoomFitBtn: 'zoom_fit',
        masterSlideNavBtn: 'master_slide_nav',
        presentModeLink: 'present_mode',
        spellcheckBtn: 'spellcheck',
        applyTransitionSelectedBtn: 'apply_transition_selected',
        applyTransitionAllBtn: 'apply_transition_all',
      }[cmdEl.id] || '');

      switch (cmdKey) {
        case 'undo':
          undo();
          break;
        case 'redo':
          redo();
          break;
        case 'paste':
          pasteClipboard();
          break;
        case 'duplicate':
          if (SF.selectedNode) duplicateNode(SF.selectedNode);
          break;
        case 'copy':
          copySelected();
          break;
        case 'cut':
          cutSelected();
          break;
        case 'group':
          groupSelectedNodes();
          break;
        case 'ungroup':
          ungroupSelected();
          break;
        case 'slide_grid_view':
          toggleSlideGridView();
          break;
        case 'add_slide':
          document.getElementById('addSlideBtn')?.click();
          break;
        case 'zoom_in':
          zoomBy(10);
          break;
        case 'zoom_out':
          zoomBy(-10);
          break;
        case 'zoom_fit':
          zoomFit();
          break;
        case 'master_slide_nav':
          handleMasterSlideNavClick(cmdEl, e);
          break;
        case 'present_mode':
          handlePresentModeLinkClick(cmdEl, e);
          break;
        case 'preview_window':
          openPreviewWindow();
          break;
        case 'present_local':
          openLocalPresentWindow();
          break;
        case 'share':
          e.preventDefault();
          openShareModal();
          break;
        case 'export':
          e.preventDefault();
          openExportModal();
          break;
        case 'spellcheck':
          window.SlideForgeSpellcheck?.openPanel?.();
          break;
        case 'apply_transition_selected':
          applyTransitionSelectedFromRibbon();
          break;
        case 'apply_transition_all':
          applyTransitionAllFromRibbon();
          break;
        default:
          break;
      }
    });
  }

  function openPreviewWindow() {
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
  }

  async function openLocalPresentWindow() {
    const url = 'present_audience.php?id=' + encodeURIComponent(SF.id);
    if (SF.presentConfigApi?.openAudienceWindow) {
      await SF.presentConfigApi.openAudienceWindow(url);
      return;
    }
    document.getElementById('presentLocalBtn')?.click();
  }

  function syncEditorPublicLinkUi(enabled, url) {
    const toggle = document.getElementById('publicLinkToggle');
    const input = document.getElementById('presentPublicLinkInput');
    const copyBtn = document.getElementById('copyPublicLinkBtn');
    if (toggle) toggle.checked = !!enabled;
    if (input) input.value = url || '';
    if (copyBtn) copyBtn.disabled = !enabled || !url;
  }

  let shareModalApi = null;

  async function openShareModal() {
    if (!shareModalApi) return;
    await shareModalApi.open();
  }

  function openExportModal() {
    const modal = document.getElementById('exportModal');
    if (!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeExportModal() {
    const modal = document.getElementById('exportModal');
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
  }

  function initExportModal() {
    const modal = document.getElementById('exportModal');
    if (!modal) return;

    document.getElementById('exportModalClose')?.addEventListener('click', closeExportModal);
    document.getElementById('exportModalDone')?.addEventListener('click', closeExportModal);
    window.SFModalBackdrop?.bindDismiss(modal, closeExportModal);

    modal.querySelectorAll('[data-export-download]').forEach((link) => {
      link.addEventListener('click', async (e) => {
        e.preventDefault();
        const url = link.getAttribute('href');
        if (!url) return;
        if (SF.canEdit) {
          try {
            await saveCurrentSlide(true);
          } catch (err) {
            console.error(err);
          }
        }
        setSaveStatus(I.exportPreparing || 'Export wird vorbereitet…');
        const frame = document.createElement('iframe');
        frame.setAttribute('aria-hidden', 'true');
        frame.style.cssText = 'position:fixed;width:0;height:0;border:0;visibility:hidden;';
        frame.src = url;
        document.body.appendChild(frame);
        setTimeout(() => {
          frame.remove();
          setSaveStatus(I.exportStarted || 'Download gestartet');
        }, 2500);
      });
    });
  }

  function initShareModal() {
    const modal = document.getElementById('shareModal');
    if (!modal || !SF.share?.enabled) return;

    const i18n = SF.share.i18n || {};
    const statusEl = document.getElementById('shareModalStatus');
    const userSelect = document.getElementById('shareUsername');
    const permSelect = document.getElementById('sharePermission');
    const addBtn = document.getElementById('shareAddBtn');
    const listEl = document.getElementById('shareList');
    const publicEnabled = document.getElementById('sharePublicEnabled');
    const publicSaveBtn = document.getElementById('sharePublicSaveBtn');
    const publicBox = document.getElementById('sharePublicLinkBox');
    const publicUrl = document.getElementById('sharePublicUrl');
    const publicCopyBtn = document.getElementById('sharePublicCopyBtn');
    const publicResetBtn = document.getElementById('sharePublicResetBtn');
    let busy = false;

    function setStatus(message, kind) {
      if (!statusEl) return;
      if (!message) {
        statusEl.hidden = true;
        statusEl.textContent = '';
        statusEl.className = 'share-modal-status';
        return;
      }
      statusEl.hidden = false;
      statusEl.textContent = message;
      statusEl.className = 'share-modal-status alert ' + (kind === 'error' ? 'alert-error' : 'alert-success');
    }

    function renderState(state) {
      const users = state.users || [];
      const shares = state.shares || [];
      const pub = state.public || {};

      if (userSelect) {
        userSelect.innerHTML = '';
        if (!users.length) {
          const opt = document.createElement('option');
          opt.value = '';
          opt.textContent = i18n.noOtherUsers || '—';
          userSelect.appendChild(opt);
          userSelect.disabled = true;
          if (addBtn) addBtn.disabled = true;
        } else {
          userSelect.disabled = false;
          if (addBtn) addBtn.disabled = false;
          const placeholder = document.createElement('option');
          placeholder.value = '';
          placeholder.disabled = true;
          placeholder.selected = true;
          placeholder.textContent = i18n.pleaseChoose || '…';
          userSelect.appendChild(placeholder);
          users.forEach((u) => {
            const opt = document.createElement('option');
            opt.value = u.username;
            opt.textContent = u.username + (u.email ? ' (' + u.email + ')' : '')
              + (u.shared ? (i18n.alreadyShared || '') : '');
            userSelect.appendChild(opt);
          });
        }
      }

      if (listEl) {
        listEl.innerHTML = '';
        if (!shares.length) {
          const li = document.createElement('li');
          li.className = 'share-list-empty';
          li.textContent = i18n.noneYet || '';
          listEl.appendChild(li);
        } else {
          shares.forEach((s) => {
            const li = document.createElement('li');
            const label = document.createElement('span');
            const tag = document.createElement('span');
            tag.className = 'perm-tag ' + (s.permission === 'edit' ? 'edit' : 'view');
            tag.textContent = s.permission === 'edit'
              ? (i18n.permEdit || 'edit')
              : (i18n.permView || 'view');
            label.appendChild(document.createTextNode(s.username + ' '));
            label.appendChild(tag);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'button button-ghost button-sm';
            btn.textContent = i18n.remove || 'Remove';
            btn.addEventListener('click', () => removeShare(s.user_id));
            li.appendChild(label);
            li.appendChild(btn);
            listEl.appendChild(li);
          });
        }
      }

      if (publicEnabled) publicEnabled.checked = !!pub.enabled;
      const url = pub.url || '';
      if (publicUrl) publicUrl.value = url;
      if (publicBox) publicBox.hidden = !pub.enabled || !url;
      if (publicResetBtn) publicResetBtn.hidden = !pub.enabled || !url;
      syncEditorPublicLinkUi(!!pub.enabled, url);
    }

    async function refresh() {
      const state = await apiGet('get_share');
      renderState(state);
      return state;
    }

    async function withBusy(fn) {
      if (busy) return;
      busy = true;
      try {
        await fn();
      } catch (err) {
        setStatus(err.message || String(err), 'error');
      } finally {
        busy = false;
      }
    }

    async function removeShare(userId) {
      await withBusy(async () => {
        const state = await api('remove_share', { user_id: userId });
        renderState(state);
        setStatus(state.message || '', 'success');
      });
    }

    function closeShareModal() {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
    }

    shareModalApi = {
      async open() {
        setStatus('', '');
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        await withBusy(async () => {
          await refresh();
        });
      },
    };

    document.getElementById('shareModalClose')?.addEventListener('click', closeShareModal);
    document.getElementById('shareModalDone')?.addEventListener('click', closeShareModal);
    window.SFModalBackdrop?.bindDismiss(modal, closeShareModal);

    addBtn?.addEventListener('click', () => {
      withBusy(async () => {
        const username = userSelect?.value || '';
        if (!username) return;
        const state = await api('add_share', {
          username,
          permission: permSelect?.value === 'edit' ? 'edit' : 'view',
        });
        renderState(state);
        if (state.warning) {
          setStatus((state.message ? state.message + ' ' : '') + state.warning, 'error');
        } else {
          setStatus(state.message || '', 'success');
        }
      });
    });

    publicSaveBtn?.addEventListener('click', () => {
      withBusy(async () => {
        const state = await api('set_public_share', { enabled: !!publicEnabled?.checked });
        renderState(state);
        setStatus(state.message || '', 'success');
      });
    });

    publicCopyBtn?.addEventListener('click', async () => {
      const value = publicUrl?.value || '';
      if (!value) return;
      const original = publicCopyBtn.textContent;
      const done = () => {
        publicCopyBtn.textContent = i18n.copied || 'OK';
        setTimeout(() => { publicCopyBtn.textContent = original; }, 1500);
      };
      try {
        await navigator.clipboard.writeText(value);
        done();
      } catch (e) {
        publicUrl.select();
        document.execCommand('copy');
        done();
      }
    });

    publicResetBtn?.addEventListener('click', () => {
      withBusy(async () => {
        if (!(await SFDialog.confirm(i18n.resetConfirm || '', { danger: true }))) return;
        const state = await api('regenerate_public_token');
        renderState(state);
        setStatus(state.message || '', 'success');
      });
    });
  }

  async function handleMasterSlideNavClick(btn, e) {
    e.preventDefault();
    e.stopPropagation();
    const active = btn.classList.contains('active') || btn.getAttribute('aria-pressed') === 'true';
    let url;
    if (active) {
      const returnId = btn.dataset.returnId || '';
      if (!returnId) return;
      url = 'editor.php?id=' + encodeURIComponent(returnId) +
        '&slide=' + encodeURIComponent(btn.dataset.returnSlide || '0');
    } else {
      url = 'editor.php?id=' + encodeURIComponent(btn.dataset.setId || '') +
        '&return=' + encodeURIComponent(btn.dataset.presentationId || '') +
        '&return_slide=' + encodeURIComponent(String(SF.currentIndex));
    }
    if (SF.canEdit) {
      try {
        await saveCurrentSlide(true);
      } catch (err) {
        console.error(err);
      }
    }
    window.location.href = url;
  }

  function syncMasterSlidePresentCommands() {
    if (!SF.masterSlideEditing) return;
    const tip = window.SF_BOOTSTRAP?.ribbon?.meta?.masterSlideCommandsDisabled
      || SF.i18n?.masterSlideCommandsDisabled
      || 'Nicht verfügbar: Masterfolie wird bearbeitet';
    const ribbonEl = document.getElementById('editorRibbon');
    if (!ribbonEl) return;

    const commandIds = ['present_mode', 'share', 'export', 'preview_tab', 'preview_window', 'present_local'];
    commandIds.forEach((cmdId) => {
      ribbonEl.querySelectorAll('[data-ribbon-command="' + cmdId + '"]').forEach((el) => {
        el.classList.add('is-master-disabled');
        el.setAttribute('aria-disabled', 'true');
        el.title = tip;
        if (el.matches('a')) {
          el.tabIndex = -1;
          el.removeAttribute('href');
        }
        if (el.matches('button') && !el.disabled) {
          el.addEventListener('click', (ev) => { ev.preventDefault(); ev.stopPropagation(); }, true);
        }
        const cell = el.closest('.ribbon-grid-cell');
        if (cell) cell.title = tip;
      });
    });

    const presentMode = document.getElementById('presentModeLink');
    if (presentMode) {
      presentMode.classList.add('is-master-disabled');
      presentMode.setAttribute('aria-disabled', 'true');
      presentMode.tabIndex = -1;
      presentMode.title = tip;
      presentMode.removeAttribute('href');
    }
  }

  async function handlePresentModeLinkClick(link, e) {
    updatePresentLinkOnSlideChange();
    if (!SF.spellConfig?.beforePresent || !window.SlideForgeSpellcheck?.ensureCleanBeforePresent) return;
    const href = link.href;
    if (!href) return;
    e.preventDefault();
    const allow = await window.SlideForgeSpellcheck.ensureCleanBeforePresent(href);
    if (allow) window.location.href = href;
  }

  function closeEditorSettingsMenu() {
    const wrap = document.querySelector('[data-settings-menu]');
    if (!wrap) return;
    wrap.querySelectorAll('[data-settings-panel]').forEach((p) => { p.hidden = true; });
    if (window.SFRibbon && window.SFRibbon.resetFloatingSettingsPanels) {
      window.SFRibbon.resetFloatingSettingsPanels();
    }
  }

  function layoutPickerScale() {
    const listEl = document.getElementById('templatePickerList');
    const host = listEl?.closest('.props-templates-scroll') || listEl;
    const cellW = host ? Math.max(72, (host.clientWidth - 10) / 2) : 100;
    return cellW / SF.meta.width;
  }

  function updateTemplatesPickerLayout() {
    const scroll = document.querySelector('.props-templates-scroll');
    const listEl = document.getElementById('templatePickerList');
    if (!scroll || !listEl || !SF.templatesPanelOpen) {
      updatePropsSidebarOverflow();
      return;
    }
    const innerW = Math.max(160, scroll.clientWidth);
    const cellW = Math.max(72, (innerW - 10) / 2);
    scroll.style.minHeight = '';
    scroll.style.maxHeight = '';
    const scale = cellW / SF.meta.width;
    listEl.querySelectorAll('.layout-picker-thumb-scale').forEach((el) => {
      el.style.width = SF.meta.width + 'px';
      el.style.height = SF.meta.height + 'px';
      el.style.transform = 'scale(' + scale + ')';
    });
    updatePropsSidebarOverflow();
  }

  function layoutPickerThumbBlock(thumbHtml, thumbColor) {
    const scale = layoutPickerScale();
    return '<span class="layout-picker-thumb" style="--lp-color:' + escapeHtml(thumbColor || '#111') + '">' +
      '<span class="layout-picker-thumb-scale" style="width:' + SF.meta.width + 'px;height:' + SF.meta.height + 'px;transform:scale(' + scale + ');">' +
      (thumbHtml || '') + '</span></span>';
  }

  async function loadTemplatePickerPanel() {
    const listEl = document.getElementById('templatePickerList');
    if (!listEl) return;
    const setId = SF.meta.layout_set_id;
    if (!setId) {
      listEl.innerHTML = '<p class="props-video-note">' + escapeHtml(SF.i18n.empty) + '</p>';
      return;
    }
    listEl.innerHTML = '<p class="props-video-note">' + escapeHtml(SF.i18n.loading) + '</p>';
    try {
      const res = await fetch(
        'api.php?action=list_layout_set_layouts&id=' + encodeURIComponent(SF.id) +
        '&layout_set_id=' + encodeURIComponent(setId)
      ).then((r) => r.json());
      if (!res.ok) throw new Error(res.error || 'Fehler');
      renderLayoutPickerInSettings(res.layouts || [], setId);
    } catch (e) {
      listEl.innerHTML = '<p class="props-video-note" style="color:var(--danger);">' + escapeHtml(SF.i18n.error) + '</p>';
    }
  }

  function renderLayoutPickerInSettings(layouts, setId) {
    const listEl = document.getElementById('templatePickerList');
    if (!listEl) return;
    if (!layouts.length) {
      listEl.innerHTML = '<p class="props-video-note">' + escapeHtml(SF.i18n.empty) + '</p>';
      return;
    }
    const currentSlide = SF.slides[SF.currentIndex] || {};
    listEl.innerHTML = layouts.map((l) => {
      const selected = l.layoutKey === currentSlide.layoutKey
        && (!currentSlide.layoutSetSlideId || l.slideId === currentSlide.layoutSetSlideId);
      return '<button type="button" class="layout-picker-item' + (selected ? ' selected' : '') + '" role="option"' +
        ' data-layout-key="' + escapeHtml(l.layoutKey) + '" data-layout-slide-id="' + escapeHtml(l.slideId || '') + '">' +
        '<span class="layout-picker-label">' + escapeHtml(l.title || l.layoutKey) + '</span>' +
        layoutPickerThumbBlock(l.thumbHtml, l.thumbColor) +
        '</button>';
    }).join('');
    listEl.querySelectorAll('.layout-picker-item').forEach((btn) => {
      btn.addEventListener('click', () => {
        applyLayoutFromSet(setId, btn.dataset.layoutKey, btn.getAttribute('data-layout-slide-id') || '');
      });
    });
    syncTemplatePickerSelection();
    requestAnimationFrame(() => {
      updateTemplatesPickerLayout();
      updatePropsSidebarOverflow();
    });
  }

  function syncTemplatePickerSelection() {
    const listEl = document.getElementById('templatePickerList');
    if (!listEl) return;
    const currentSlide = SF.slides[SF.currentIndex] || {};
    listEl.querySelectorAll('.layout-picker-item').forEach((btn) => {
      const selected = btn.dataset.layoutKey === currentSlide.layoutKey
        && (!currentSlide.layoutSetSlideId || btn.getAttribute('data-layout-slide-id') === currentSlide.layoutSetSlideId);
      btn.classList.toggle('selected', !!selected);
    });
  }

  function renderSlideTemplatePickerInSettings(mine, shared) {
    const listEl = document.getElementById('templatePickerList');
    if (!listEl) return;
    if (!mine.length && !shared.length) {
      listEl.innerHTML = '<p class="props-video-note">' + escapeHtml(SF.i18n.empty) + '</p>';
      return;
    }
    const renderGroup = (title, items) => {
      if (!items.length) return '';
      return '<div class="layout-picker-group-title">' + escapeHtml(title) + '</div>' +
        items.map((t) =>
          '<button type="button" class="layout-picker-item" role="option" data-template-id="' + escapeHtml(t.id) + '">' +
            '<span class="layout-picker-label">' + escapeHtml(t.title) + '</span>' +
            layoutPickerThumbBlock(t.thumbHtml, t.thumbColor) +
          '</button>'
        ).join('');
    };
    listEl.innerHTML = renderGroup(SF.i18n.own, mine) + renderGroup(SF.i18n.shared, shared);
    listEl.querySelectorAll('[data-template-id]').forEach((btn) => {
      btn.addEventListener('click', () => applySlideTemplate(btn.dataset.templateId));
    });
  }

  function renderLayoutSetPicker(layouts, setId) {
    const listEl = document.getElementById('templateList');
    if (!layouts.length) {
      listEl.innerHTML = '<p style="color:var(--text-muted); font-size:0.85rem;">' + SF.i18n.empty + '</p>';
      return;
    }
    listEl.innerHTML = layouts.map(l =>
      '<div class="template-pick-row" data-layout-key="' + escapeHtml(l.layoutKey) + '" data-layout-slide-id="' + escapeHtml(l.slideId || '') + '">' +
        '<span>' + escapeHtml(l.title || l.layoutKey) + '</span>' +
        '<button type="button" class="button button-sm" data-apply-layout="' + escapeHtml(l.layoutKey) + '" data-layout-slide-id="' + escapeHtml(l.slideId || '') + '">' + SF.i18n.apply + '</button>' +
      '</div>'
    ).join('');
    listEl.querySelectorAll('[data-apply-layout]').forEach(btn => {
      btn.addEventListener('click', () => {
        const slideId = btn.getAttribute('data-layout-slide-id') || '';
        applyLayoutFromSet(setId, btn.dataset.applyLayout, slideId);
      });
    });
  }

  async function applyLayoutFromSet(setId, layoutKey, layoutSlideId) {
    setSaveStatus('Wende Layout an…');
    try {
      pushHistory();
      const payload = { index: SF.currentIndex, layout_set_id: setId, layout_key: layoutKey };
      if (layoutSlideId) payload.layout_slide_id = layoutSlideId;
      await api('apply_layout_from_set', payload);
      const res = await apiGet('get_slides');
      SF.slides = res.slides;
      loadSlideIntoStage(SF.currentIndex, true);
      pushHistory();
      await renderSlideFilmstrip();
      closeEditorSettingsMenu();
      if (SF.templatesPanelOpen) renderTemplatesPanel();
      setSaveStatus('Gespeichert');
      reloadPreviewWindow();
    } catch (e) {
      setSaveStatus('Fehler beim Anwenden', true);
      console.error(e);
    }
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
      pushHistory();
      await api('apply_slide_template', { index: SF.currentIndex, template_id: templateId });
      const res = await apiGet('get_slides');
      SF.slides = res.slides;
      loadSlideIntoStage(SF.currentIndex, true);
      pushHistory();
      await renderSlideFilmstrip();
      closeEditorSettingsMenu();
      if (SF.templatesPanelOpen) renderTemplatesPanel();
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
    if (SF.templateMode && !SF.layoutSetMode) {
      SF.currentIndex = 0;
    } else {
      SF.currentIndex = SF.templateMode
        ? 0
        : Math.min(initialSlideIndexFromUrl(), Math.max(0, SF.slides.length - 1));
      await loadLayoutSetTitles();
      await renderSlideFilmstrip();
    }
    buildStage();
    initTransitionPicker();
    loadSlideIntoStage(SF.currentIndex);
    renderTemplatesPanel();
    if (!SF.templateMode || SF.layoutSetMode) {
      updateFilmstripActive();
      const filmstrip = document.getElementById('slideFilmstrip');
      const activeItem = filmstrip?.querySelector('.filmstrip-item.active');
      if (activeItem) activeItem.scrollIntoView({ block: 'nearest' });
    }
    bindGlobalUI();
    initRibbonEditorHooks();
    initRibbonObjectColor();
    initRibbonObjectDelete();
    initSpellcheckPanel();
    initPixabayPanel();
    initIconifyPanel();
    initOpenclipartPanel();
    initWebdavPanel();
    initMediaSearchButtons();
    initMediaLibraryPanel();
    initSideTabs();
    initMediaSidebarPanel();
    initPropsSidebarLayoutObserver();
    initElementsPanel();
    updatePresentLinkOnSlideChange();
    window.addEventListener('resize', resizeStageToFit);
    window.addEventListener('resize', updateTemplatesPickerLayout);
    window.addEventListener('resize', () => {
      if (SF.editorViewMode === 'grid') renderSlideGrid();
    });
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
        boundBoxFunc: (_oldBox, newBox) => {
          newBox.width = Math.max(5, newBox.width);
          newBox.height = Math.max(5, newBox.height);
          return newBox;
        },
      });
      SF.transformer.on('transformstart', () => {
        SF.transformSnapStart = null;
      });
      SF.transformer.on('transform', () => snapDuringTransform());
      SF.transformer.on('transformend', () => {
        clearSnapGuides();
        SF.transformSnapStart = null;
      });
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
    const presentHref = 'present.php?id=' + encodeURIComponent(SF.id) + '&slide=' + SF.currentIndex;
    const previewHref = 'preview.php?id=' + encodeURIComponent(SF.id) + '&slide=' + SF.currentIndex;
    const link = document.getElementById('presentModeLink');
    if (link) link.href = presentHref;
    const previewLink = document.getElementById('previewTabLink');
    if (previewLink) previewLink.href = previewHref;
    // Ribbon rendert eigene <a>-Links aus meta.urls — Href bei Folienwechsel nachziehen.
    document.querySelectorAll('a[data-ribbon-command="present_mode"]').forEach((el) => {
      el.href = presentHref;
    });
    document.querySelectorAll('a[data-ribbon-command="preview_tab"]').forEach((el) => {
      el.href = previewHref;
    });
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
    const side = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--editor-filmstrip-width'), 10) || 300;
    const handle = SF.canEdit ? 18 : 0;
    return Math.max(120, side - 28 - handle);
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
      const wrap = item.querySelector('.filmstrip-thumb-wrap');
      if (wrap) {
        wrap.style.height = itemHeight + 'px';
      } else if (!SF.layoutSetMode) {
        item.style.height = itemHeight + 'px';
      }
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

  function humanizeLayoutKey(key) {
    const words = {
      ueberschrift: 'Überschrift',
      und: 'und',
      inhalt: 'Inhalt',
      inhalte: 'Inhalte',
      zwei: 'zwei',
      vergleich: 'Vergleich',
      listenpunkt: 'Listenpunkt',
      text: 'Text',
      document: 'Dokument',
      title: 'Titel',
      subtitle: 'Untertitel',
      heading: 'Überschrift',
      scripture: 'Bibelstelle',
      block: 'Block',
      lighttext: 'Blockzitat',
    };
    return String(key || '').split('_').filter(Boolean).map((part) => {
      const lower = part.toLowerCase();
      if (words[lower]) return words[lower];
      if (/^\d+$/.test(part)) return part;
      return part.charAt(0).toUpperCase() + part.slice(1);
    }).join(' ') || key;
  }

  function slideDisplayLabel(slide) {
    if (!slide) return SF.i18n.unnamedSlide || 'Unbenannte Folie';
    const stored = String(slide.label || '').trim();
    if (stored) return stored;
    const setSlideId = slide.layoutSetSlideId || '';
    if (setSlideId && SF.layoutSetTitlesByKey && SF.layoutSetTitlesByKey['id:' + setSlideId]) {
      return SF.layoutSetTitlesByKey['id:' + setSlideId];
    }
    const key = slide.layoutKey || '';
    if (key && SF.layoutSetTitlesByKey && SF.layoutSetTitlesByKey[key]) {
      return SF.layoutSetTitlesByKey[key];
    }
    if (key && SF.logosRoleLabels && SF.logosRoleLabels[key]) return SF.logosRoleLabels[key];
    if (key) return humanizeLayoutKey(key);
    const roleObj = (slide.objects || []).find((o) => o.setRole || o.logosRole);
    if (roleObj && SF.logosRoleLabels && SF.logosRoleLabels[roleObj.setRole || roleObj.logosRole]) {
      return SF.logosRoleLabels[roleObj.setRole || roleObj.logosRole];
    }
    return SF.i18n.unnamedSlide || 'Unbenannte Folie';
  }

  async function loadLayoutSetTitles() {
    SF.layoutSetTitlesByKey = {};
    if (SF.layoutSetMode || !SF.hasLayoutSet) return;
    const setId = SF.meta?.layout_set_id;
    if (!setId) return;
    try {
      const res = await fetch(
        'api.php?action=list_layout_set_layouts&id=' + encodeURIComponent(SF.id) +
        '&layout_set_id=' + encodeURIComponent(setId)
      ).then((r) => r.json());
      if (!res.ok) return;
      (res.layouts || []).forEach((l) => {
        if (l.slideId && l.title) {
          SF.layoutSetTitlesByKey['id:' + l.slideId] = l.title;
        }
        if (l.layoutKey && l.title && !SF.layoutSetTitlesByKey[l.layoutKey]) {
          SF.layoutSetTitlesByKey[l.layoutKey] = l.title;
        }
      });
    } catch (e) {
      console.error(e);
    }
  }

  function updateEditorContextTitle() {
    if (!SF.layoutSetMode) return;
    const slide = SF.slides[SF.currentIndex];
    const titleEl = document.querySelector('.topbar-context-title');
    if (!titleEl || !slide) return;
    const text = SF.meta.title + ' - ' + slideDisplayLabel(slide);
    titleEl.textContent = text;
    titleEl.title = text;
    const siteSuffix = document.title.includes('·')
      ? document.title.substring(document.title.lastIndexOf('·'))
      : '';
    document.title = 'Editor · ' + text + siteSuffix;
  }

  async function saveSlideLabel(index, label) {
    const slide = SF.slides[index];
    if (!slide) return;
    const trimmed = String(label || '').trim();
    const prev = String(slide.label || '').trim();
    if (trimmed === prev) return;
    setSaveStatus('Speichere…');
    try {
      const res = await api('save_slide_label', { index, label: trimmed });
      SF.slides = res.slides;
      setSaveStatus('Gespeichert');
      updateEditorContextTitle();
    } catch (e) {
      setSaveStatus('Fehler', true);
      console.error(e);
    }
  }

  function bindFilmstripLabelInput(input, slideIndex) {
    if (!input) return;
    input.addEventListener('click', (e) => e.stopPropagation());
    input.addEventListener('mousedown', (e) => e.stopPropagation());
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        input.blur();
      }
    });
    input.addEventListener('blur', () => {
      const trimmed = input.value.trim();
      saveSlideLabel(slideIndex, trimmed);
    });
  }

  function syncFilmstripNotesBadge(slideIndex) {
    const slide = SF.slides[slideIndex];
    if (!slide) return;
    const item = document.querySelector('.editor-filmstrip-item[data-id="' + slide.id + '"]');
    if (!item) return;
    const host = item.querySelector('.filmstrip-thumb-wrap') || item;
    const hasNotes = slideHasNotes(slide);
    item.classList.toggle('has-notes', hasNotes);
    const badge = host.querySelector('.filmstrip-notes-badge');
    if (hasNotes && !badge) {
      host.insertAdjacentHTML('beforeend', filmstripNotesBadgeHtml());
    } else if (!hasNotes && badge) {
      badge.remove();
    }
  }

  function slideThumbnailsUrl() {
    return 'api.php?action=slide_thumbnails&id=' + encodeURIComponent(SF.id);
  }

  async function fetchSlideThumbnails() {
    const res = await fetch(slideThumbnailsUrl()).then((r) => r.json());
    if (!res.ok) throw new Error(res.error || 'Unbekannter Fehler');
    SF.thumbnails = {};
    (res.thumbnails || []).forEach((t) => { SF.thumbnails[t.id] = t; });
    return SF.thumbnails;
  }

  async function refreshCurrentFilmstripThumb() {
    const container = document.getElementById('slideFilmstrip');
    if (!container) return;
    try {
      const res = await fetch(slideThumbnailsUrl()).then((r) => r.json());
      if (!res.ok) throw new Error(res.error || 'Unbekannter Fehler');
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

    const showSlideLabels = SF.layoutSetMode;
    SF.slides.forEach((slide, i) => {
      const thumb = SF.thumbnails[slide.id] || {};
      const hasNotes = slideHasNotes(slide);
      const presentOff = !!slide.presentDisabled;
      const tab = document.createElement('div');
      tab.className = 'filmstrip-item editor-filmstrip-item' +
        (showSlideLabels ? ' has-slide-label' : '') +
        (i === SF.currentIndex ? ' active' : '') +
        (hasNotes ? ' has-notes' : '') +
        (presentOff ? ' is-present-disabled' : '');
      tab.dataset.id = slide.id;
      tab.style.setProperty('--fs-color', thumb.color || slideBgColor(slide));
      if (!showSlideLabels) {
        tab.style.height = itemHeight + 'px';
      }

      const labelHtml = showSlideLabels
        ? (SF.layoutSetMode && SF.canEdit
          ? '<input type="text" class="filmstrip-label-input" value="' + escapeHtml(slideDisplayLabel(slide)) + '" placeholder="' + escapeHtml(SF.i18n.filmstripLabelPlaceholder || 'Layout-Name') + '" title="' + escapeHtml(SF.i18n.filmstripLabelPlaceholder || 'Layout-Name') + '">'
          : '<span class="filmstrip-label-text">' + escapeHtml(slideDisplayLabel(slide)) + '</span>')
        : '';

      tab.innerHTML =
        '<div class="filmstrip-thumb-wrap"' + (showSlideLabels ? ' style="height:' + itemHeight + 'px;"' : '') + '>' +
        (SF.canEdit ? '<span class="editor-filmstrip-handle" title="' + (SF.i18n.reorderSlide || 'Ziehen zum Sortieren') + '">⋮⋮</span>' : '') +
        '<div class="filmstrip-thumb-scale" style="width:' + SF.meta.width + 'px; height:' + SF.meta.height + 'px; transform:scale(' + fsScale + ');">' +
          (thumb.html || '') +
        '</div>' +
        (hasNotes ? filmstripNotesBadgeHtml() : '') +
        '<span class="filmstrip-num">' + (i + 1) + '</span>' +
        (SF.canEdit
          ? '<span class="editor-filmstrip-actions">' +
              '<button type="button" class="tab-action' + (presentOff ? ' is-present-off' : '') + '" data-act="toggle-present" title="' + (presentOff ? (SF.i18n.slidePresentEnabled || 'Beim Präsentieren einblenden') : (SF.i18n.togglePresentDisabled || 'Beim Präsentieren überspringen')) + '">' + (presentOff ? '◉' : '⊘') + '</button>' +
              '<button type="button" class="tab-action" data-act="dup" title="' + (SF.i18n.duplicateSlide || 'Duplizieren') + '">⧉</button>' +
              (SF.canImportSlideToSet
                ? '<button type="button" class="tab-action" data-act="to-set" title="' + escapeHtml(I.importSlideToSet || 'In Set') + '">→</button>'
                : '') +
              (SF.slides.length > 1 ? '<button type="button" class="tab-action" data-act="del" title="' + (SF.i18n.deleteSlide || 'Löschen') + '">✕</button>' : '') +
            '</span>'
          : '') +
        '</div>' +
        labelHtml;

      tab.addEventListener('click', (e) => {
        if (SF.filmstripSuppressClick) return;
        if (e.target.closest('[data-act]') || e.target.closest('.editor-filmstrip-handle') || e.target.closest('.filmstrip-label-input')) return;
        const idx = SF.slides.findIndex((s) => s.id === tab.dataset.id);
        switchToSlide(idx);
      });

      if (SF.canEdit) {
        const labelInput = tab.querySelector('.filmstrip-label-input');
        if (labelInput) bindFilmstripLabelInput(labelInput, i);
        const togglePresentBtn = tab.querySelector('[data-act="toggle-present"]');
        if (togglePresentBtn) togglePresentBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          toggleSlidePresentDisabled(SF.slides.findIndex((s) => s.id === tab.dataset.id));
        });
        const dupBtn = tab.querySelector('[data-act="dup"]');
        if (dupBtn) dupBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          duplicateSlide(SF.slides.findIndex((s) => s.id === tab.dataset.id));
        });
        const toSetBtn = tab.querySelector('[data-act="to-set"]');
        if (toSetBtn) toSetBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          importSlideToLayoutSet(SF.slides.findIndex((s) => s.id === tab.dataset.id));
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
    await commitSlideOrder(newOrderIds);
  }

  function getGridAfterElement(container, x, y) {
    const items = [...container.querySelectorAll('.editor-slide-grid-item:not(.dragging)')];
    if (!items.length) return null;

    let nearestIdx = 0;
    let nearestDist = Infinity;
    items.forEach((child, i) => {
      const box = child.getBoundingClientRect();
      const cx = box.left + box.width / 2;
      const cy = box.top + box.height / 2;
      const dist = (x - cx) * (x - cx) + (y - cy) * (y - cy);
      if (dist < nearestDist) {
        nearestDist = dist;
        nearestIdx = i;
      }
    });

    const nearest = items[nearestIdx];
    const box = nearest.getBoundingClientRect();
    const cx = box.left + box.width / 2;
    const cy = box.top + box.height / 2;
    const sameRow = Math.abs(y - cy) <= box.height / 2;
    const insertAfter = sameRow ? (x > cx) : (y > cy);
    if (insertAfter) return items[nearestIdx + 1] || null;
    return nearest;
  }

  function updateGridNumbers(container) {
    container.querySelectorAll('.editor-slide-grid-item').forEach((item, i) => {
      const num = item.querySelector('.editor-slide-grid-id');
      if (num) num.textContent = String(i + 1);
      item.dataset.index = String(i);
    });
  }

  function bindGridReorder(container) {
    if (!container || container.dataset.reorderBound) return;
    container.dataset.reorderBound = '1';

    let dragItem = null;
    let dragMoved = false;
    let startX = 0;
    let startY = 0;
    let dragging = false;

    container.addEventListener('mousedown', (e) => {
      if (!SF.canEdit || e.button !== 0) return;
      if (e.ctrlKey || e.metaKey || e.shiftKey) return;
      const item = e.target.closest('.editor-slide-grid-item');
      if (!item || e.target.closest('.tab-action')) return;

      dragItem = item;
      dragMoved = false;
      dragging = false;
      startX = e.clientX;
      startY = e.clientY;

      const onMove = (ev) => {
        if (!dragItem) return;
        const dx = ev.clientX - startX;
        const dy = ev.clientY - startY;
        if (!dragging) {
          if (dx * dx + dy * dy < 25) return;
          dragging = true;
          dragItem.classList.add('dragging');
          document.body.classList.add('editor-grid-dragging');
        }
        const after = getGridAfterElement(container, ev.clientX, ev.clientY);
        const prev = [...container.querySelectorAll('.editor-slide-grid-item')].map((el) => el.dataset.id);
        if (after == null) container.appendChild(dragItem);
        else if (after !== dragItem) container.insertBefore(dragItem, after);
        const next = [...container.querySelectorAll('.editor-slide-grid-item')].map((el) => el.dataset.id);
        if (prev.join(',') !== next.join(',')) {
          dragMoved = true;
          updateGridNumbers(container);
        }
      };

      const onUp = () => {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        document.body.classList.remove('editor-grid-dragging');
        if (!dragItem) return;
        dragItem.classList.remove('dragging');
        dragItem = null;
        if (dragMoved) {
          gridSuppressClick = true;
          commitGridOrder();
          setTimeout(() => { gridSuppressClick = false; }, 100);
        }
      };

      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    });
  }

  async function commitSlideOrder(newOrderIds) {
    const oldOrderIds = SF.slides.map((s) => s.id);
    if (JSON.stringify(newOrderIds) === JSON.stringify(oldOrderIds)) return;

    const currentId = SF.slides[SF.currentIndex] ? SF.slides[SF.currentIndex].id : null;
    const selectedIds = [...gridSelectedIndices]
      .map((i) => SF.slides[i] && SF.slides[i].id)
      .filter(Boolean);

    setSaveStatus('Speichere…');
    try {
      const res = await api('reorder_slides', { order: newOrderIds });
      SF.slides = res.slides;
      if (currentId) {
        const idx = SF.slides.findIndex((s) => s.id === currentId);
        if (idx >= 0) SF.currentIndex = idx;
      }
      gridSelectedIndices.clear();
      selectedIds.forEach((id) => {
        const idx = SF.slides.findIndex((s) => s.id === id);
        if (idx >= 0) gridSelectedIndices.add(idx);
      });
      setSaveStatus('Gespeichert');
      await renderSlideFilmstrip();
      if (SF.editorViewMode === 'grid') renderSlideGrid();
      reloadPreviewWindow();
    } catch (e) {
      setSaveStatus('Fehler', true);
      console.error(e);
      await renderSlideFilmstrip();
      if (SF.editorViewMode === 'grid') renderSlideGrid();
    }
  }

  async function commitGridOrder() {
    const grid = document.getElementById('slideGrid');
    if (!grid) return;
    const newOrderIds = [...grid.querySelectorAll('.editor-slide-grid-item')].map((el) => el.dataset.id);
    await commitSlideOrder(newOrderIds);
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
    updateEditorContextTitle();
    renderLayersPanel();
    syncTemplatePickerSelection();
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

  function isFillBgType(type) {
    return type === 'color' || type === 'gradient' || type === 'none';
  }

  function updateBgFillPreview(bg) {
    const swatch = document.getElementById('bgFillPreview');
    const wrap = document.getElementById('bgFillPreviewWrap');
    if (!swatch) return;
    if (bg.type === 'gradient') {
      const value = bg.value || ('linear-gradient(' + (bg.angle ?? 90) + 'deg, ' + (bg.color1 || '#3a6c8d') + ', ' + (bg.color2 || '#87b42b') + ')');
      swatch.style.background = value;
      swatch.classList.remove('is-none');
      if (wrap) wrap.title = I.bgGradient || I.slideBgPreview || '';
    } else if (bg.type === 'none') {
      swatch.style.background = '';
      swatch.classList.add('is-none');
      if (wrap) wrap.title = I.bgNonePreview || 'Kein Hintergrund';
    } else {
      swatch.style.background = bg.value || '#111111';
      swatch.classList.remove('is-none');
      if (wrap) wrap.title = I.bgColor || I.slideBgPreview || '';
    }
  }

  function syncBgPreviewPanels(bg) {
    const mediaPanel = document.querySelector('.ribbon-slide-bg-preview-inner, .ribbon-slide-bg-panel');
    const mediaSep = document.querySelector('.ribbon-slide-bg-sep-media');
    if (mediaPanel) mediaPanel.hidden = false;
    if (mediaSep) mediaSep.hidden = !(bg.type === 'image' || bg.type === 'video');
    document.querySelectorAll('.ribbon-slide-bg-preview-inner .bg-panel, .ribbon-slide-bg-panel .bg-panel').forEach((p) => {
      if (p.dataset.bgtype === 'fill') {
        p.hidden = !isFillBgType(bg.type);
      } else {
        p.hidden = p.dataset.bgtype !== bg.type;
      }
    });
    if (isFillBgType(bg.type)) updateBgFillPreview(bg);
  }

  function populateBackgroundControls(bg) {
    document.querySelectorAll('.bg-type-btn').forEach(b => b.classList.toggle('active', b.dataset.bgtype === bg.type));

    syncBgPreviewPanels(bg);

    document.getElementById('bgColorInput').value = bg.type === 'color' ? bg.value : (bg.type === 'gradient' ? (bg.color1 || '#3a6c8d') : '#111111');
    document.getElementById('bgGradColor1').value = bg.color1 || '#3a6c8d';
    document.getElementById('bgGradColor2').value = bg.color2 || '#87b42b';
    document.getElementById('bgGradAngle').value = bg.angle ?? 90;

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

  function updateSlideBgGradientPreview() {
    const c1 = document.getElementById('bgGradColor1')?.value || '#3a6c8d';
    const c2 = document.getElementById('bgGradColor2')?.value || '#87b42b';
    const angle = parseInt(document.getElementById('bgGradAngle')?.value, 10) || 0;
    const preview = document.getElementById('slideBgGradientPreview');
    const label = document.getElementById('bgGradAngleLabel');
    if (preview) preview.style.background = 'linear-gradient(' + angle + 'deg, ' + c1 + ', ' + c2 + ')';
    if (label) label.textContent = (I.angle || I.bgAngle || 'Winkel') + ' (' + angle + '°)';
  }

  function openSlideBgGradientModal() {
    const modal = document.getElementById('slideBgGradientModal');
    if (!modal) return;
    const bg = SF.currentBackground;
    const c1 = document.getElementById('bgGradColor1');
    const c2 = document.getElementById('bgGradColor2');
    const angle = document.getElementById('bgGradAngle');
    if (bg.type === 'gradient') {
      if (c1) c1.value = bg.color1 || '#3a6c8d';
      if (c2) c2.value = bg.color2 || '#87b42b';
      if (angle) angle.value = String(bg.angle ?? 90);
    }
    updateSlideBgGradientPreview();
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeSlideBgGradientModal() {
    const modal = document.getElementById('slideBgGradientModal');
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
  }

  function applySlideBgGradient() {
    SF.currentBackground = buildGradientBg();
    applyBackgroundVisual(SF.currentBackground);
    populateBackgroundControls(SF.currentBackground);
    updateCurrentTabSwatch();
    scheduleSave();
    closeSlideBgGradientModal();
  }

  function initSlideBgGradientModal() {
    const modal = document.getElementById('slideBgGradientModal');
    if (!modal || modal.dataset.wired === '1') return;
    modal.dataset.wired = '1';
    ['bgGradColor1', 'bgGradColor2', 'bgGradAngle'].forEach((id) => {
      document.getElementById(id)?.addEventListener('input', updateSlideBgGradientPreview);
    });
    document.getElementById('slideBgGradientModalClose')?.addEventListener('click', closeSlideBgGradientModal);
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeSlideBgGradientModal();
    });
    document.getElementById('slideBgGradientModalApply')?.addEventListener('click', applySlideBgGradient);
    if (window.SFModalBackdrop) {
      window.SFModalBackdrop.bindDismiss(modal, closeSlideBgGradientModal);
    }
  }

  function setBgType(type) {
    document.querySelectorAll('.bg-type-btn').forEach(b => b.classList.toggle('active', b.dataset.bgtype === type));

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
    syncBgPreviewPanels(bg);
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
      closeSlideBgMediaModal();
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
    initRibbonBgBrandDropdown();
  }

  function closeRibbonBgBrandDropdown() {
    const panel = document.getElementById('bgBrandPalette');
    const btn = document.getElementById('bgBrandBtn');
    if (panel) {
      panel.hidden = true;
      panel.style.left = '';
      panel.style.top = '';
      panel.style.minWidth = '';
    }
    if (btn) btn.setAttribute('aria-expanded', 'false');
  }

  function positionRibbonBgBrandDropdown() {
    const panel = document.getElementById('bgBrandPalette');
    const btn = document.getElementById('bgBrandBtn');
    if (!panel || !btn) return;
    const rect = btn.getBoundingClientRect();
    panel.style.position = 'fixed';
    panel.style.left = Math.max(8, rect.left) + 'px';
    panel.style.top = (rect.bottom + 4) + 'px';
    panel.style.minWidth = rect.width + 'px';
    panel.style.zIndex = '500';
    const panelRect = panel.getBoundingClientRect();
    if (panelRect.right > window.innerWidth - 8) {
      panel.style.left = Math.max(8, window.innerWidth - panelRect.width - 8) + 'px';
    }
  }

  function toggleRibbonBgBrandDropdown() {
    const panel = document.getElementById('bgBrandPalette');
    const btn = document.getElementById('bgBrandBtn');
    if (!panel || !btn) return;
    const willOpen = panel.hidden;
    closeRibbonBgBrandDropdown();
    closeRibbonBrandDropdown();
    closeRibbonObjectBrandDropdowns();
    if (willOpen) {
      panel.hidden = false;
      btn.setAttribute('aria-expanded', 'true');
      positionRibbonBgBrandDropdown();
    }
  }

  function initRibbonBgBrandDropdown() {
    const panel = document.getElementById('bgBrandPalette');
    if (!panel || panel.dataset.wired === '1') return;
    panel.dataset.wired = '1';
    panel.innerHTML = SF.brandColors.map((c) =>
      '<button type="button" class="brand-swatch" role="menuitem" data-color="' + c.hex + '" title="' + escapeHtml(c.name || c.hex) + '" style="background:' + c.hex + '"></button>'
    ).join('');
    panel.querySelectorAll('.brand-swatch').forEach((btn) => {
      btn.addEventListener('click', () => {
        const colorInput = document.getElementById('bgColorInput');
        if (colorInput) colorInput.value = btn.dataset.color;
        SF.currentBackground = { type: 'color', value: btn.dataset.color };
        applyBackgroundVisual(SF.currentBackground);
        populateBackgroundControls(SF.currentBackground);
        updateCurrentTabSwatch();
        scheduleSave();
        closeRibbonBgBrandDropdown();
      });
    });
    document.getElementById('bgBrandBtn')?.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleRibbonBgBrandDropdown();
    });
    document.addEventListener('click', (e) => {
      if (!e.target.closest('[data-ribbon-brand-menu="bgColor"]')) closeRibbonBgBrandDropdown();
    });
    window.addEventListener('resize', () => {
      const btn = document.getElementById('bgBrandBtn');
      if (panel && btn && !panel.hidden) positionRibbonBgBrandDropdown();
    });
  }

  function updateSlideBgMediaModalPreview() {
    const preview = document.getElementById('slideBgMediaModalPreview');
    const removeBtn = document.getElementById('slideBgMediaModalRemove');
    const kind = SF.slideBgMediaModalKind;
    if (!preview || !kind) return;
    const bg = SF.currentBackground;
    const hasMedia = bg.type === kind && bg.value;
    if (!hasMedia) {
      preview.hidden = true;
      preview.innerHTML = '';
      if (removeBtn) removeBtn.disabled = true;
      syncSlideBgMediaModalSelection();
      return;
    }
    preview.hidden = false;
    if (removeBtn) removeBtn.disabled = false;
    if (kind === 'video') {
      preview.innerHTML = '<video src="' + escapeHtml(bg.value) + '" muted loop autoplay playsinline controls></video>';
    } else {
      preview.innerHTML = '<img src="' + escapeHtml(bg.value) + '" alt="">';
    }
    syncSlideBgMediaModalSelection();
  }

  function syncSlideBgMediaModalSelection() {
    const grid = document.getElementById('slideBgMediaModalExistingGrid');
    if (!grid) return;
    const current = (SF.currentBackground && SF.currentBackground.type === SF.slideBgMediaModalKind)
      ? (SF.currentBackground.value || '')
      : '';
    grid.querySelectorAll('[data-url]').forEach((btn) => {
      btn.classList.toggle('is-selected', !!current && btn.dataset.url === current);
      btn.setAttribute('aria-pressed', btn.classList.contains('is-selected') ? 'true' : 'false');
    });
  }

  let slideBgMediaModalLoadSeq = 0;

  async function loadSlideBgMediaModalExisting(kind) {
    const section = document.getElementById('slideBgMediaModalExisting');
    const grid = document.getElementById('slideBgMediaModalExistingGrid');
    const emptyEl = document.getElementById('slideBgMediaModalExistingEmpty');
    const titleEl = document.getElementById('slideBgMediaModalExistingTitle');
    const mediaKind = kind === 'video' ? 'video' : 'image';
    if (!section || !grid || !emptyEl) return;

    const seq = ++slideBgMediaModalLoadSeq;
    if (titleEl) {
      titleEl.textContent = I.slideMediaExisting || 'Aus dieser Präsentation';
    }
    section.hidden = false;
    grid.innerHTML = '';
    emptyEl.hidden = true;
    emptyEl.textContent = I.slideMediaExistingEmpty || 'Noch keine passenden Medien in dieser Präsentation.';

    let items = [];
    try {
      const res = await api('list_media');
      if (seq !== slideBgMediaModalLoadSeq) return;
      items = (res.items || []).filter((item) => item.kind === mediaKind);
    } catch (err) {
      if (seq !== slideBgMediaModalLoadSeq) return;
      items = [];
    }

    if (seq !== slideBgMediaModalLoadSeq) return;

    if (!items.length) {
      emptyEl.hidden = false;
      return;
    }

    const current = (SF.currentBackground && SF.currentBackground.type === mediaKind)
      ? (SF.currentBackground.value || '')
      : '';

    grid.innerHTML = items.map((item) => {
      const selected = current && item.url === current;
      const label = escapeHtml(item.filename || (mediaKind === 'video' ? 'Video' : 'Bild'));
      const thumb = mediaKind === 'video'
        ? '<span class="slide-bg-media-modal-existing-thumb slide-bg-media-modal-existing-thumb--video">' +
            '<span class="slide-bg-media-modal-existing-play" aria-hidden="true"></span>' +
            '<video src="' + escapeHtml(item.url) + '" muted preload="metadata" playsinline></video>' +
            '<span class="slide-bg-media-modal-existing-name">' + label + '</span>' +
          '</span>'
        : '<img src="' + escapeHtml(item.url) + '" alt="">';
      return '<button type="button" class="slide-bg-media-modal-existing-item' + (selected ? ' is-selected' : '') + '" data-url="' + escapeHtml(item.url) + '" role="listitem" aria-pressed="' + (selected ? 'true' : 'false') + '" title="' + label + '">' +
        thumb +
        '</button>';
    }).join('');
  }

  function applySlideBgMediaFromLibrary(url) {
    const kind = SF.slideBgMediaModalKind === 'video' ? 'video' : 'image';
    if (!url) return;
    setBgType(kind);
    SF.currentBackground = { type: kind, value: url };
    applyBackgroundVisual(SF.currentBackground);
    populateBackgroundControls(SF.currentBackground);
    updateCurrentTabSwatch();
    scheduleSave();
    closeSlideBgMediaModal();
  }

  function openSlideBgMediaModal(kind) {
    if (!SF.canEdit) return;
    const modal = document.getElementById('slideBgMediaModal');
    if (!modal) return;
    const mediaKind = kind === 'video' ? 'video' : 'image';
    SF.slideBgMediaModalKind = mediaKind;
    const title = document.getElementById('slideBgMediaModalTitle');
    if (title) title.textContent = mediaKind === 'video' ? (I.bgVideo || 'Video') : (I.bgImage || 'Bild');
    const fileInput = document.getElementById('slideBgMediaModalFile');
    if (fileInput) {
      fileInput.accept = mediaKind === 'video' ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/gif,image/webp';
      fileInput.value = '';
    }
    const pixabayBtn = document.getElementById('slideBgMediaModalPixabay');
    if (pixabayBtn) {
      pixabayBtn.dataset.pixabayOpen = mediaKind === 'video' ? 'background-video' : 'background-image';
      pixabayBtn.hidden = false;
    }
    const existing = document.getElementById('slideBgMediaModalExisting');
    if (existing) existing.hidden = false;
    updateSlideBgMediaModalPreview();
    loadSlideBgMediaModalExisting(mediaKind);
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeSlideBgMediaModal() {
    const modal = document.getElementById('slideBgMediaModal');
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    SF.slideBgMediaModalKind = null;
  }

  function initSlideBgMediaModal() {
    const modal = document.getElementById('slideBgMediaModal');
    if (!modal || modal.dataset.wired === '1') return;
    modal.dataset.wired = '1';
    document.getElementById('slideBgMediaModalClose')?.addEventListener('click', closeSlideBgMediaModal);
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeSlideBgMediaModal();
    });
    document.getElementById('slideBgMediaModalBrowse')?.addEventListener('click', () => {
      document.getElementById('slideBgMediaModalFile')?.click();
    });
    document.getElementById('slideBgMediaModalFile')?.addEventListener('change', async (e) => {
      const kind = SF.slideBgMediaModalKind === 'video' ? 'video' : 'image';
      const file = e.target.files[0];
      e.target.value = '';
      if (!file) return;
      await uploadAsset(kind, file);
      closeSlideBgMediaModal();
    });
    document.getElementById('slideBgMediaModalRemove')?.addEventListener('click', () => {
      const kind = SF.slideBgMediaModalKind;
      if (kind === 'video') {
        SF.currentBackground = { type: 'video', value: '' };
      } else {
        SF.currentBackground = { type: 'image', value: '' };
      }
      applyBackgroundVisual(SF.currentBackground);
      populateBackgroundControls(SF.currentBackground);
      updateCurrentTabSwatch();
      scheduleSave();
      updateSlideBgMediaModalPreview();
    });
    document.getElementById('slideBgMediaModalPixabay')?.addEventListener('click', () => {
      closeSlideBgMediaModal();
    });
    document.getElementById('slideBgMediaModalExistingGrid')?.addEventListener('click', (e) => {
      const btn = e.target.closest('.slide-bg-media-modal-existing-item[data-url]');
      if (!btn || !modal.contains(btn)) return;
      applySlideBgMediaFromLibrary(btn.dataset.url);
    });
    if (window.SFModalBackdrop) {
      window.SFModalBackdrop.bindDismiss(modal, closeSlideBgMediaModal);
    }
  }

  async function duplicateSlide(index) {
    setSaveStatus('Speichere…');
    const res = await api('duplicate_slide', { index });
    SF.slides = res.slides;
    await renderSlideFilmstrip();
    if (SF.editorViewMode === 'grid') renderSlideGrid();
    setSaveStatus('Gespeichert');
    reloadPreviewWindow();
  }

  function suggestedLayoutKey(slide) {
    const raw = (slideDisplayLabel(slide) || '').toLowerCase();
    const key = raw.replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '').slice(0, 48);
    return key || 'slide';
  }

  async function importSlideToLayoutSet(index) {
    if (!SF.canImportSlideToSet || !SF.importLayoutSetId) return;
    const slide = SF.slides[index];
    if (!slide) return;
    const defaultKey = suggestedLayoutKey(slide);
    const layoutKey = await SFDialog.prompt(I.importSlideToSetPrompt || 'Layout-Schlüssel:', defaultKey);
    if (layoutKey === null) return;
    try {
      setSaveStatus('Speichere…');
      const res = await api('import_slide_to_layout_set', {
        index,
        layout_set_id: SF.importLayoutSetId,
        layout_key: layoutKey.trim(),
      });
      setSaveStatus(res.message || I.importSlideToSetDone || 'Gespeichert');
    } catch (e) {
      setSaveStatus('Fehler', true);
      await SFDialog.alert(e.message || 'Import fehlgeschlagen.');
      console.error(e);
    }
  }

  async function deleteSlide(index) {
    if (SF.slides.length <= 1) {
      await SFDialog.alert('Die letzte verbleibende Folie kann nicht gelöscht werden.');
      return;
    }
    if (!(await SFDialog.confirm('Diese Folie wirklich löschen?', { danger: true }))) return;
    try {
      setSaveStatus('Speichere…');
      const res = await api('delete_slide', { index });
      SF.slides = res.slides;
      if (SF.currentIndex >= SF.slides.length) SF.currentIndex = SF.slides.length - 1;
      gridSelectedIndices.clear();
      gridSelectedIndices.add(SF.currentIndex);
      gridLastClickedIndex = SF.currentIndex;
      loadSlideIntoStage(SF.currentIndex);
      await renderSlideFilmstrip();
      if (SF.editorViewMode === 'grid') renderSlideGrid();
      reloadPreviewWindow();
      setSaveStatus('Gespeichert');
    } catch (e) {
      setSaveStatus('Fehler', true);
      await SFDialog.alert(e.message || 'Folie konnte nicht gelöscht werden.');
      console.error(e);
    }
  }

  async function toggleSlidePresentDisabled(index) {
    if (index < 0) return;
    setSaveStatus('Speichere…');
    const res = await api('toggle_slide_present_disabled', { index });
    SF.slides = res.slides;
    await renderSlideFilmstrip();
    if (SF.editorViewMode === 'grid') renderSlideGrid();
    setSaveStatus('Gespeichert');
    reloadPreviewWindow();
  }

  // ---------- Slide grid (Raster-Ansicht) ----------
  const gridSelectedIndices = new Set();
  let gridLastClickedIndex = null;
  let gridSuppressClick = false;
  let gridThumbSaveTimer = null;
  let gridThumbSliderBound = false;

  function gridThumbMinPx() {
    return SF.gridThumbMin || 168;
  }

  function applyGridThumbMin(px, opts = {}) {
    const min = Math.max(100, Math.min(360, Math.round(px) || 168));
    SF.gridThumbMin = min;
    const grid = document.getElementById('slideGrid');
    if (grid) grid.style.setProperty('--grid-thumb-min', min + 'px');
    const slider = document.getElementById('gridThumbSizeSlider');
    const label = document.getElementById('gridThumbSizeLabel');
    if (slider && !opts.fromSlider) slider.value = String(min);
    if (label) label.textContent = min + ' px';
    if (opts.rerender !== false && SF.editorViewMode === 'grid') renderSlideGrid();
    if (opts.save) scheduleGridThumbSave();
  }

  function scheduleGridThumbSave() {
    if (gridThumbSaveTimer) clearTimeout(gridThumbSaveTimer);
    gridThumbSaveTimer = setTimeout(saveGridThumbMin, 400);
  }

  async function saveGridThumbMin() {
    try {
      const res = await fetch('user_api.php?action=set_editor_grid_thumb_min', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: SF.csrfToken, thumb_min: gridThumbMinPx() }),
      });
      const data = await res.json();
      if (data.thumb_min) applyGridThumbMin(data.thumb_min, { save: false, rerender: false });
    } catch (e) {
      console.error(e);
    }
  }

  function initGridThumbSlider() {
    if (gridThumbSliderBound) return;
    const slider = document.getElementById('gridThumbSizeSlider');
    if (!slider) return;
    gridThumbSliderBound = true;
    applyGridThumbMin(gridThumbMinPx(), { save: false, rerender: false });
    slider.addEventListener('input', () => {
      applyGridThumbMin(parseInt(slider.value, 10) || 168, { fromSlider: true, save: true });
    });
  }

  function transitionOptionLabel(value) {
    const opt = transitionOption(value);
    return opt ? opt.label : (value || 'slide');
  }

  function transitionOption(value) {
    const v = value || 'slide';
    return TRANSITION_OPTIONS.find((o) => o.value === v)
      || TRANSITION_OPTIONS.find((o) => o.value === 'slide')
      || TRANSITION_OPTIONS[0];
  }

  function gridSlideMetaHtml(slide, index) {
    const opt = transitionOption(slide.transition);
    const label = opt.label || (slide.transition || 'slide');
    const icon = opt.icon || TRANSITION_ICONS.slide;
    const aa = Math.max(0, parseInt(slide.autoAdvance, 10) || 0);
    const timeText = aa > 0 ? (aa + 's') : '—';
    return '<div class="editor-slide-grid-meta">' +
      '<span class="editor-slide-grid-id">' + (index + 1) + '</span>' +
      '<span class="editor-slide-grid-sep" aria-hidden="true">|</span>' +
      '<span class="editor-slide-grid-effect-icon" title="' + escapeHtml(I.transitionTitle || 'Übergang') + ': ' + escapeHtml(label) + '" aria-hidden="true">' + icon + '</span>' +
      '<span class="editor-slide-grid-sep" aria-hidden="true">|</span>' +
      '<span class="editor-slide-grid-autoadvance" title="' + escapeHtml(I.autoAdvanceLabel || 'Zeit') + '">' + escapeHtml(timeText) + '</span>' +
      '<span class="editor-slide-grid-effect-label" title="' + escapeHtml(label) + '">' + escapeHtml(label) + '</span>' +
    '</div>';
  }

  function gridCellScale() {
    const min = gridThumbMinPx();
    const grid = document.getElementById('slideGrid');
    if (!grid || !grid.clientWidth) return min / SF.meta.width;
    const gap = 14;
    const cols = Math.max(1, Math.floor((grid.clientWidth + gap) / (min + gap)));
    const cellW = (grid.clientWidth - (cols - 1) * gap) / cols;
    return Math.max(80, cellW) / SF.meta.width;
  }

  function syncEditorViewModeUi() {
    const isGrid = SF.editorViewMode === 'grid';
    const canvasArea = document.getElementById('canvasArea');
    const slideView = document.getElementById('canvasSlideView');
    const gridView = document.getElementById('canvasGridView');
    const gridBtn = document.getElementById('slideGridViewBtn');
    if (canvasArea) canvasArea.classList.toggle('grid-view-active', isGrid);
    if (slideView) slideView.hidden = isGrid;
    if (gridView) gridView.hidden = !isGrid;
    if (gridBtn) gridBtn.classList.toggle('active', isGrid);
    syncApplyTransitionSelectedVisibility();
  }

  function setEditorViewMode(mode) {
    const next = mode === 'grid' ? 'grid' : 'slide';
    if (next === SF.editorViewMode) return;
    if (next === 'grid') {
      if (!document.getElementById('canvasGridView')) return;
      gridSelectedIndices.clear();
      gridSelectedIndices.add(SF.currentIndex);
      gridLastClickedIndex = SF.currentIndex;
      syncRibbonTransitionFieldsFromSlide(SF.currentIndex);
      initGridThumbSlider();
      SF.editorViewMode = 'grid';
      applyGridThumbMin(gridThumbMinPx(), { save: false, rerender: false });
      syncEditorViewModeUi();
      renderSlideGrid();
      if (window.SFRibbon?.setTab) window.SFRibbon.setTab('design');
      return;
    }
    SF.editorViewMode = 'slide';
    syncEditorViewModeUi();
    syncRibbonTransitionFieldsFromSlide(SF.currentIndex);
    updateGridSelectionUi();
  }

  function toggleSlideGridView() {
    setEditorViewMode(SF.editorViewMode === 'grid' ? 'slide' : 'grid');
  }

  function openSlideGridView() {
    setEditorViewMode('grid');
  }

  function closeSlideGridView() {
    setEditorViewMode('slide');
  }

  function ribbonAutoAdvanceValue() {
    const el = document.getElementById('autoAdvanceInput');
    return el ? Math.max(0, parseInt(el.value, 10) || 0) : 0;
  }

  function syncRibbonTransitionFieldsFromSlide(index) {
    const slide = SF.slides[index];
    if (!slide || !SF.canEdit) return;
    setTransitionPickerValue(slide.transition || 'slide');
    const aa = document.getElementById('autoAdvanceInput');
    if (aa) aa.value = slide.autoAdvance || 0;
  }

  function updateGridSelectionUi() {
    const info = document.getElementById('slideGridSelectionInfo');
    const applyBtn = document.getElementById('applyTransitionSelectedBtn');
    const n = gridSelectedIndices.size;
    const tpl = SF.i18n.slideGridSelected || '{n} ausgewählt';
    if (info) info.textContent = tpl.replace('{n}', String(n));
    if (applyBtn) {
      applyBtn.disabled = n === 0 || !SF.canEdit;
    }
  }

  function renderSlideGrid() {
    const grid = document.getElementById('slideGrid');
    if (!grid) return;
    const scale = gridCellScale();
    grid.innerHTML = '';
    SF.slides.forEach((slide, i) => {
      const thumb = SF.thumbnails[slide.id] || {};
      const presentOff = !!slide.presentDisabled;
      const card = document.createElement('div');
      card.setAttribute('role', 'button');
      card.tabIndex = 0;
      card.className = 'editor-slide-grid-item'
        + (gridSelectedIndices.has(i) ? ' selected' : '')
        + (i === SF.currentIndex ? ' is-current' : '')
        + (presentOff ? ' is-present-disabled' : '');
      card.dataset.index = String(i);
      card.dataset.id = slide.id;
      const actionsHtml = SF.canEdit
        ? '<span class="editor-slide-grid-actions">' +
            '<button type="button" class="tab-action' + (presentOff ? ' is-present-off' : '') + '" data-act="toggle-present" title="' +
              (presentOff ? (SF.i18n.slidePresentEnabled || 'Beim Präsentieren einblenden') : (SF.i18n.togglePresentDisabled || 'Beim Präsentieren überspringen')) + '">' +
              (presentOff ? '◉' : '⊘') + '</button>' +
            '<button type="button" class="tab-action" data-act="dup" title="' + (SF.i18n.duplicateSlide || 'Duplizieren') + '">⧉</button>' +
            (SF.canImportSlideToSet
              ? '<button type="button" class="tab-action" data-act="to-set" title="' + escapeHtml(I.importSlideToSet || 'In Set') + '">→</button>'
              : '') +
            (SF.slides.length > 1 ? '<button type="button" class="tab-action" data-act="del" title="' + (SF.i18n.deleteSlide || 'Löschen') + '">✕</button>' : '') +
          '</span>'
        : '';
      card.innerHTML =
        '<div class="editor-slide-grid-thumb">' +
          actionsHtml +
          '<div class="filmstrip-thumb-scale" style="width:' + SF.meta.width + 'px;height:' + SF.meta.height + 'px;transform:scale(' + scale + ');">' +
            (thumb.html || '') +
          '</div>' +
        '</div>' +
        gridSlideMetaHtml(slide, i);
      card.addEventListener('mousedown', (e) => {
        /* Shift/Ctrl: Browser-Textselektion in Miniaturen unterbinden */
        if (e.shiftKey || e.ctrlKey || e.metaKey) e.preventDefault();
      });
      card.addEventListener('click', (e) => {
        if (gridSuppressClick) return;
        if (e.target.closest('[data-act]')) return;
        if (e.detail === 2) {
          switchToSlide(i);
          closeSlideGridView();
          return;
        }
        handleGridSelectionClick(e, i);
      });
      card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          if (e.target.closest('[data-act]')) return;
          handleGridSelectionClick(e, i);
        }
      });
      if (SF.canEdit) {
        const togglePresentBtn = card.querySelector('[data-act="toggle-present"]');
        if (togglePresentBtn) togglePresentBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          toggleSlidePresentDisabled(i);
        });
        const dupBtn = card.querySelector('[data-act="dup"]');
        if (dupBtn) dupBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          duplicateSlide(i);
        });
        const toSetBtn = card.querySelector('[data-act="to-set"]');
        if (toSetBtn) toSetBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          importSlideToLayoutSet(i);
        });
        const delBtn = card.querySelector('[data-act="del"]');
        if (delBtn) delBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          deleteSlide(i);
        });
      }
      grid.appendChild(card);
    });
    bindGridReorder(grid);
    updateGridSelectionUi();
  }

  function handleGridSelectionClick(e, index) {
    e.preventDefault();
    window.getSelection()?.removeAllRanges();
    if (e.shiftKey && gridLastClickedIndex !== null) {
      const a = Math.min(gridLastClickedIndex, index);
      const b = Math.max(gridLastClickedIndex, index);
      for (let i = a; i <= b; i++) gridSelectedIndices.add(i);
    } else if (e.ctrlKey || e.metaKey) {
      if (gridSelectedIndices.has(index)) gridSelectedIndices.delete(index);
      else gridSelectedIndices.add(index);
      gridLastClickedIndex = index;
    } else {
      gridSelectedIndices.clear();
      gridSelectedIndices.add(index);
      gridLastClickedIndex = index;
    }
    syncRibbonTransitionFieldsFromSlide(index);
    document.querySelectorAll('.editor-slide-grid-item').forEach((el) => {
      const idx = parseInt(el.dataset.index, 10);
      el.classList.toggle('selected', gridSelectedIndices.has(idx));
    });
    updateGridSelectionUi();
  }

  async function applyTransitionToIndices(indices) {
    if (!indices.length) return;
    const transition = getTransitionValue();
    const autoAdvance = ribbonAutoAdvanceValue();
    setSaveStatus('Speichere…');
    try {
      const res = await api('apply_transition_slides', { indices, transition, autoAdvance });
      SF.slides = res.slides;
      setSaveStatus('Gespeichert');
      if (SF.editorViewMode === 'grid') renderSlideGrid();
      await renderSlideFilmstrip();
      syncRibbonTransitionFieldsFromSlide(SF.currentIndex);
      reloadPreviewWindow();
    } catch (err) {
      setSaveStatus('Fehler beim Speichern');
    }
  }

  async function applyTransitionSelectedFromRibbon() {
    if (SF.editorViewMode === 'grid') {
      await applyTransitionToIndices([...gridSelectedIndices]);
      return;
    }
    await applyTransitionToIndices([SF.currentIndex]);
  }

  async function applyTransitionAllFromRibbon() {
    const transition = getTransitionValue();
    const autoAdvance = ribbonAutoAdvanceValue();
    setSaveStatus('Speichere…');
    try {
      const res = await api('apply_transition_all', { transition, autoAdvance });
      SF.slides = res.slides;
      setSaveStatus('Gespeichert');
      if (SF.editorViewMode === 'grid') renderSlideGrid();
      await renderSlideFilmstrip();
      reloadPreviewWindow();
    } catch (e) {
      setSaveStatus('Fehler beim Speichern');
    }
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

  function measureImageNaturalSize(src) {
    return new Promise((resolve, reject) => {
      if (!src) {
        reject(new Error('no src'));
        return;
      }
      const img = new Image();
      img.onload = () => {
        const w = img.naturalWidth || img.width || 0;
        const h = img.naturalHeight || img.height || 0;
        if (w > 0 && h > 0) resolve({ w, h, image: img });
        else reject(new Error('no dimensions'));
      };
      img.onerror = () => reject(new Error('load failed'));
      img.src = src;
    });
  }

  function fitImageInsertSize(naturalW, naturalH, opts = {}) {
    const maxW = opts.maxW ?? 480;
    const maxH = opts.maxH ?? 480;
    const minLongest = opts.minLongest ?? 80;
    const fallback = opts.fallback ?? { w: 400, h: 240 };
    if (!naturalW || !naturalH) return { ...fallback };

    let w = naturalW;
    let h = naturalH;
    const down = Math.min(maxW / w, maxH / h, 1);
    w = Math.round(w * down);
    h = Math.round(h * down);
    const longest = Math.max(w, h);
    if (longest > 0 && longest < minLongest) {
      const up = minLongest / longest;
      w = Math.round(w * up);
      h = Math.round(h * up);
    }
    return { w: Math.max(1, w), h: Math.max(1, h) };
  }

  async function insertImageObjectAtCenter(url, extra = {}) {
    const sizeOpts = extra.sizeOptions || {};
    let size = fitImageInsertSize(0, 0, sizeOpts);
    let preloaded = null;
    try {
      const measured = await measureImageNaturalSize(url);
      size = fitImageInsertSize(measured.w, measured.h, sizeOpts);
      preloaded = measured.image;
    } catch (_) { /* fallback size */ }

    const centerX = Math.round(SF.meta.width / 2) - size.w / 2;
    const centerY = Math.round(SF.meta.height / 2) - size.h / 2;
    const id = 'o' + Math.random().toString(16).slice(2, 10);
    const obj = Object.assign({
      id, type: 'image', x: centerX, y: centerY, w: size.w, h: size.h,
      rotation: 0, opacity: 1, src: url, deferImageLoad: true,
    }, extra.objProps || {});
    const node = createNode(obj);
    node.setAttr('aspectRatio', size.w / size.h);
    insertNode(node);
    loadImageAsync(node, url, obj.iconColor, preloaded);
    return node;
  }

  function loadImageAsync(node, src, iconColor, preloaded) {
    if (preloaded) {
      node.image(preloaded);
      if (SF.layer) SF.layer.batchDraw();
      return;
    }
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
      const setRole = obj.setRole || obj.logosRole;
      if (setRole) node.setAttr('setRole', setRole);
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
      if (!obj.deferImageLoad) loadImageAsync(node, obj.src, obj.iconColor);
      return node;
    }
    if (obj.type === 'video') {
      const w = obj.w || 400, h = obj.h || 260;
      const group = new Konva.Group(Object.assign({}, common, { x: obj.x || 0, y: obj.y || 0 }));
      const rect = new Konva.Rect({ width: w, height: h, fill: '#0c0e12', stroke: '#3a6c8d', strokeWidth: 1 });
      const label = new Konva.Text({
        width: w, height: h, text: I.typeVideo || 'Video', align: 'center', verticalAlign: 'middle',
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
        width: w, height: h, text: I.typeAudio || 'Audio', align: 'center', verticalAlign: 'middle',
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
        lineCap: isOpen ? 'round' : 'butt',
        lineJoin: isOpen ? 'round' : 'miter',
        stroke: isOpen ? ((obj.stroke && obj.stroke !== 'transparent') ? obj.stroke : '#ffffff') : ((obj.stroke && obj.stroke !== 'transparent') ? obj.stroke : undefined),
        strokeWidth: isOpen ? (obj.strokeWidth || 3) : (obj.strokeWidth || 0),
      }));
      node.setAttr('objType', 'shape');
      node.setAttr('shapeType', shapeType);
      node.setAttr('baseW', w);
      node.setAttr('baseH', h);
      node.setAttr('starPoints', obj.starPoints || 5);
      node.setAttr('arrowStyle', obj.arrowStyle || 'right');
      node.setAttr('bracketStyle', obj.bracketStyle || 'curly-left');
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
      const textObj = Object.assign(base, {
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
      const setRole = node.getAttr('setRole') || node.getAttr('logosRole');
      if (setRole) textObj.setRole = setRole;
      return textObj;
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
        bracketStyle: node.getAttr('bracketStyle') || 'curly-left',
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

  function nearestSnapCoord(value, targets, threshold) {
    let best = null;
    let bestDist = threshold;
    for (const t of targets) {
      const dist = Math.abs(value - t);
      if (dist <= bestDist) {
        bestDist = dist;
        best = t;
      }
    }
    return best;
  }

  function transformAnchorEdges(anchor) {
    return {
      left: anchor === 'top-left' || anchor === 'middle-left' || anchor === 'bottom-left',
      right: anchor === 'top-right' || anchor === 'middle-right' || anchor === 'bottom-right',
      top: anchor === 'top-left' || anchor === 'top-center' || anchor === 'top-right',
      bottom: anchor === 'bottom-left' || anchor === 'bottom-center' || anchor === 'bottom-right',
    };
  }

  const SNAP_RESIZE_TYPES = new Set(['text', 'rect', 'ellipse', 'image', 'shape']);

  function applyClientRectToNode(node, x, y, w, h) {
    const type = node.getAttr('objType');
    node.scaleX(1);
    node.scaleY(1);
    if (type === 'text') {
      node.position({ x, y });
      node.width(Math.max(20, w));
      return;
    }
    if (type === 'rect' || type === 'image') {
      node.position({ x, y });
      node.width(Math.max(5, w));
      node.height(Math.max(5, h));
      return;
    }
    if (type === 'ellipse') {
      const rx = Math.max(2.5, w / 2);
      const ry = Math.max(2.5, h / 2);
      node.position({ x: x + rx, y: y + ry });
      node.radiusX(rx);
      node.radiusY(ry);
      if (node.getAttr('fillType') === 'gradient') applyShapeGradientVisual(node);
      return;
    }
    if (type === 'shape') {
      const baseW = Math.max(10, w);
      const baseH = Math.max(10, h);
      node.position({ x, y });
      node.points(buildShapePoints(node.getAttr('shapeType'), baseW, baseH, nodeShapeCfg(node)));
      node.setAttr('baseW', baseW);
      node.setAttr('baseH', baseH);
      if (node.getAttr('fillType') === 'gradient') applyShapeGradientVisual(node);
    }
  }

  function snapDuringTransform() {
    if (SF._snapTransformLock) return;
    const nodes = SF.transformer?.nodes() || [];
    if (nodes.length !== 1) return;
    const node = nodes[0];
    if (isObjectGroup(node)) return;
    const type = node.getAttr('objType');
    if (!SNAP_RESIZE_TYPES.has(type)) return;
    if (Math.abs(node.rotation()) > 0.5) return;

    const anchor = SF.transformer.getActiveAnchor();
    if (!anchor || anchor === 'rotater') return;

    SF._snapTransformLock = true;
    try {
    const box = node.getClientRect({ relativeTo: SF.layer });
    if (!SF.transformSnapStart) {
      SF.transformSnapStart = {
        anchor,
        left: box.x,
        right: box.x + box.width,
        top: box.y,
        bottom: box.y + box.height,
      };
    }

    const edges = transformAnchorEdges(anchor);
    const threshold = SNAP_THRESHOLD_PX / (SF.currentZoom || 1);
    const { targetXs, targetYs } = getSnapTargets([node]);
    const start = SF.transformSnapStart;

    let x, y, w, h;
    let snappedX = null;
    let snappedY = null;

    if (edges.left) {
      const tx = nearestSnapCoord(box.x, targetXs, threshold);
      x = tx !== null ? tx : box.x;
      w = start.right - x;
      if (tx !== null) snappedX = tx;
    } else if (edges.right) {
      const tx = nearestSnapCoord(box.x + box.width, targetXs, threshold);
      const right = tx !== null ? tx : box.x + box.width;
      x = start.left;
      w = right - start.left;
      if (tx !== null) snappedX = tx;
    } else {
      x = box.x;
      w = box.width;
    }

    if (edges.top) {
      const ty = nearestSnapCoord(box.y, targetYs, threshold);
      y = ty !== null ? ty : box.y;
      h = start.bottom - y;
      if (ty !== null) snappedY = ty;
    } else if (edges.bottom) {
      const ty = nearestSnapCoord(box.y + box.height, targetYs, threshold);
      const bottom = ty !== null ? ty : box.y + box.height;
      y = start.top;
      h = bottom - start.top;
      if (ty !== null) snappedY = ty;
    } else {
      y = box.y;
      h = box.height;
    }

    applyClientRectToNode(
      node,
      Math.round(x),
      Math.round(y),
      Math.round(Math.max(5, w)),
      Math.round(Math.max(5, h))
    );
    drawSnapGuideLines(snappedX, snappedY);
    if (SF.transformer) SF.transformer.forceUpdate();
    refreshCanvas();
    } finally {
      SF._snapTransformLock = false;
    }
  }

  function updateSnapGuides(node) {
    const threshold = SNAP_THRESHOLD_PX / (SF.currentZoom || 1);
    const box = node.getClientRect({ relativeTo: SF.layer });
    const selfXs = [box.x, box.x + box.width / 2, box.x + box.width];
    const selfYs = [box.y, box.y + box.height / 2, box.y + box.height];
    const { targetXs, targetYs } = getSnapTargets([node]);

    let dx = 0;
    let snappedX = null;
    for (const sx of selfXs) {
      const tx = nearestSnapCoord(sx, targetXs, threshold);
      if (tx !== null) {
        dx = tx - sx;
        snappedX = tx;
        break;
      }
    }
    let dy = 0;
    let snappedY = null;
    for (const sy of selfYs) {
      const ty = nearestSnapCoord(sy, targetYs, threshold);
      if (ty !== null) {
        dy = ty - sy;
        snappedY = ty;
        break;
      }
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
      SF.transformSnapStart = null;
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
    /* Canva-Stil: eine Kante + ein Block, Mitte exakt zentriert */
    top: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16"/><rect x="9" y="5" width="6" height="10" rx="1.5"/></svg>',
    left: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4v16"/><rect x="5" y="9" width="10" height="6" rx="1.5"/></svg>',
    vcenter: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h16"/><rect x="9" y="7" width="6" height="10" rx="1.5"/></svg>',
    hcenter: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16"/><rect x="7" y="9" width="10" height="6" rx="1.5"/></svg>',
    bottom: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19h16"/><rect x="9" y="9" width="6" height="10" rx="1.5"/></svg>',
    right: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M19 4v16"/><rect x="9" y="9" width="10" height="6" rx="1.5"/></svg>',
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

  function positionActionBtnHtml({ attrs, icon, label, title, id, disabled }) {
    return '<button type="button" class="position-action-btn"' +
      (id ? ' id="' + id + '"' : '') +
      (attrs ? ' ' + attrs : '') +
      (disabled ? ' disabled' : '') +
      ' title="' + escapeHtml(title || label) + '" aria-label="' + escapeHtml(title || label) + '">' +
      '<span class="position-action-btn-icon" aria-hidden="true">' + icon + '</span>' +
      '<span class="position-action-btn-label">' + escapeHtml(label) + '</span>' +
      '</button>';
  }

  function alignGridRibbonHtml() {
    const edges = [
      ['top', I.alTop],
      ['left', I.alLeft],
      ['vcenter', I.alVCenter],
      ['hcenter', I.alHCenter],
      ['bottom', I.alBottom],
      ['right', I.alRight],
    ];
    const hint = I.alignClickHint ? (' — ' + I.alignClickHint) : '';
    return '<div class="position-action-grid">' + edges.map(([edge, label]) =>
      positionActionBtnHtml({
        attrs: 'data-align-edge="' + edge + '"',
        icon: ALIGN_EDGE_ICONS[edge] || '',
        label,
        title: label + hint,
      })
    ).join('') + '</div>';
  }

  function getSlideLayerNodes() {
    if (!SF.layer) return [];
    return getTopLevelNodes().slice().reverse();
  }

  function collectLayerOrderFromDom() {
    const list = document.querySelector('.props-layers-list');
    if (!list) return [];
    return [...list.querySelectorAll('li[data-layer-id]')].map((li) => li.dataset.layerId).filter(Boolean);
  }

  /** Content nodes only (excludes bgRect, badges, transformer) — avoids “ghost layers”. */
  function restackContentNodes(bottomToTop) {
    if (!SF.layer) return;
    if (SF.bgRect) SF.bgRect.moveToBottom();
    bottomToTop.forEach((node) => {
      if (node && node.getParent() === SF.layer) node.moveToTop();
    });
    getTopLevelNodes().forEach((node) => {
      if (node._sfBadge) node._sfBadge.moveToTop();
    });
    if (SF.transformer) SF.transformer.moveToTop();
    SF.layer.draw();
  }

  function contentNodesBottomToTop() {
    return getTopLevelNodes().slice().sort((a, b) => a.zIndex() - b.zIndex());
  }

  function applyLayerOrder(topFirstIds) {
    if (!SF.layer || !topFirstIds.length) return;
    const bottomToTop = topFirstIds.slice().reverse()
      .map((id) => findNodeById(id))
      .filter(Boolean);
    restackContentNodes(bottomToTop);
    scheduleSave();
  }

  function nudgeContentLayer(node, action) {
    if (!node || !isTopLevelNode(node)) return;
    const ordered = contentNodesBottomToTop();
    const idx = ordered.indexOf(node);
    if (idx < 0) return;
    if (action === 'up' && idx < ordered.length - 1) {
      ordered.splice(idx, 1);
      ordered.splice(idx + 1, 0, node);
    } else if (action === 'down' && idx > 0) {
      ordered.splice(idx, 1);
      ordered.splice(idx - 1, 0, node);
    } else if (action === 'front') {
      ordered.splice(idx, 1);
      ordered.push(node);
    } else if (action === 'back') {
      ordered.splice(idx, 1);
      ordered.unshift(node);
    } else {
      return;
    }
    restackContentNodes(ordered);
  }

  function bindLayersListDrag(listEl) {
    if (!listEl || listEl.dataset.dragBound) return;
    listEl.dataset.dragBound = '1';
    let dragLi = null;

    listEl.addEventListener('dragstart', (e) => {
      if (!e.target.closest('.props-layer-drag-handle')) {
        e.preventDefault();
        return;
      }
      dragLi = e.target.closest('li[data-layer-id]');
      if (!dragLi) return;
      dragLi.classList.add('dragging');
      if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', dragLi.dataset.layerId || '');
      }
    });

    listEl.addEventListener('dragover', (e) => {
      if (!dragLi) return;
      e.preventDefault();
      const li = e.target.closest('li[data-layer-id]');
      if (li && li !== dragLi) {
        const items = [...listEl.querySelectorAll('li[data-layer-id]')];
        const from = items.indexOf(dragLi);
        const to = items.indexOf(li);
        if (from < 0 || to < 0) return;
        if (from < to) li.after(dragLi);
        else li.before(dragLi);
      } else if (!li) {
        listEl.appendChild(dragLi);
      }
    });

    listEl.addEventListener('drop', (e) => {
      e.preventDefault();
      if (!dragLi) return;
      applyLayerOrder(collectLayerOrderFromDom());
      dragLi.classList.remove('dragging');
      dragLi = null;
      renderLayersPanel();
    });

    listEl.addEventListener('dragend', () => {
      if (dragLi) dragLi.classList.remove('dragging');
      dragLi = null;
    });
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
    const wrap = document.getElementById('propsPanelWrap');
    if (!wrap) return;
    wrap.classList.remove('side-tab-templates', 'side-tab-format', 'side-tab-position', 'side-tab-effects', 'side-tab-spell', 'side-tab-media');
    wrap.classList.add('side-tab-' + (SF.activeSideTab || 'templates'));
    requestAnimationFrame(updatePropsSidebarOverflow);
  }

  function setSideTab(tabId, opts) {
    const tabs = document.querySelectorAll('#propsSideTabs [data-side-tab]');
    const panels = document.querySelectorAll('#propsSidePanels [data-side-panel]');
    if (!tabs.length) return;
    const available = [...tabs].map((t) => t.dataset.sideTab);
    let next = tabId;
    if (!available.includes(next)) next = available[0] || 'templates';
    SF.activeSideTab = next;
    if (!opts || opts.persist !== false) {
      try { localStorage.setItem('sf_side_tab', next); } catch (e) { /* ignore */ }
    }
    tabs.forEach((btn) => {
      const on = btn.dataset.sideTab === next;
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
      btn.classList.toggle('active', on);
    });
    panels.forEach((panel) => {
      const on = panel.dataset.sidePanel === next;
      panel.classList.toggle('is-active', on);
      panel.hidden = !on;
    });
    if (next === 'templates') {
      SF.templatesPanelOpen = true;
      renderTemplatesPanel();
    }
    if (next === 'position') {
      SF.layersPanelOpen = true;
      renderLayersPanel();
      setPosSubtab(SF.activePosSubtab || 'layout', { persist: false });
    }
    if (next === 'media') {
      SF.mediaPanelOpen = true;
      document.getElementById('propsMediaAccordion')?.classList.add('open');
      if (SF.refreshMediaLibrary) SF.refreshMediaLibrary();
    }
    if (next === 'spell' && window.SlideForgeSpellcheck?.ensureOpen) {
      window.SlideForgeSpellcheck.ensureOpen();
    }
    if (next === 'effects' || next === 'format') {
      refreshPropsPanel();
    }
    syncLayersPropsLayout();
    requestAnimationFrame(updateTemplatesPickerLayout);
  }

  function setPosSubtab(subId, opts) {
    const buttons = document.querySelectorAll('#propsPosSubtabs [data-pos-subtab]');
    const panels = document.querySelectorAll('#propsSidePanelPosition [data-pos-panel]');
    if (!buttons.length) return;
    const next = subId === 'layers' ? 'layers' : 'layout';
    SF.activePosSubtab = next;
    if (!opts || opts.persist !== false) {
      try { localStorage.setItem('sf_pos_subtab', next); } catch (e) { /* ignore */ }
    }
    buttons.forEach((btn) => {
      const on = btn.dataset.posSubtab === next;
      btn.classList.toggle('active', on);
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    panels.forEach((panel) => {
      const on = panel.dataset.posPanel === next;
      panel.hidden = !on;
      panel.classList.toggle('is-active', on);
    });
    if (next === 'layers') renderLayersPanel();
  }

  function formatSubtabAvailability(node) {
    const single = !!(node && SF.selectedNodes.length === 1 && !isObjectGroup(node));
    const isText = single && node.getAttr('objType') === 'text';
    return {
      text: isText,
      templates: isText && !!SF.hasLayoutSet,
      /* Fläche/Kontur/Form — auch bei Text (ggf. deaktiviert), sonst nur dieses Panel */
      format: single,
    };
  }

  function setFormatSubtab(subId, opts) {
    const tabBar = document.getElementById('propsFormatSubtabs');
    const buttons = document.querySelectorAll('#propsFormatSubtabs [data-format-subtab]');
    const panels = document.querySelectorAll('#propsSidePanelFormat [data-format-panel]');
    if (!buttons.length || !panels.length) return;

    const avail = (opts && opts.availability) || formatSubtabAvailability(SF.selectedNode);
    const allowed = ['text', 'templates', 'format'].filter((id) => avail[id]);
    let next = subId;
    if (!allowed.includes(next)) {
      next = allowed.includes(SF.activeFormatSubtab) ? SF.activeFormatSubtab : (allowed[0] || 'format');
    }
    if (!allowed.includes(next)) next = 'format';

    SF.activeFormatSubtab = next;
    if (!opts || opts.persist !== false) {
      try { localStorage.setItem('sf_format_subtab', next); } catch (e) { /* ignore */ }
    }

    if (tabBar) {
      const showTabs = allowed.length > 1;
      tabBar.hidden = !showTabs;
      buttons.forEach((btn) => {
        const id = btn.dataset.formatSubtab;
        const visible = !!avail[id];
        btn.hidden = !visible;
        const on = visible && id === next;
        btn.classList.toggle('active', on);
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
      });
    }

    panels.forEach((panel) => {
      const id = panel.dataset.formatPanel;
      const on = id === next;
      panel.hidden = !on;
      panel.classList.toggle('is-active', on);
    });
  }

  function syncFormatSubtabs(node) {
    const avail = formatSubtabAvailability(node);
    let preferred = SF.activeFormatSubtab;
    if (!avail[preferred]) {
      preferred = avail.text ? 'text' : (avail.templates ? 'templates' : 'format');
    }
    setFormatSubtab(preferred, { availability: avail, persist: false });
  }

  function initSideTabs() {
    const tabs = document.getElementById('propsSideTabs');
    if (!tabs || tabs.dataset.wired === '1') return;
    tabs.dataset.wired = '1';
    tabs.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-side-tab]');
      if (!btn || btn.id === 'ribbonCustomizeBtn') return;
      e.preventDefault();
      setSideTab(btn.dataset.sideTab);
    });
    document.getElementById('propsPosSubtabs')?.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-pos-subtab]');
      if (!btn) return;
      e.preventDefault();
      setPosSubtab(btn.dataset.posSubtab);
    });
    document.getElementById('propsFormatSubtabs')?.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-format-subtab]');
      if (!btn || btn.hidden) return;
      e.preventDefault();
      setFormatSubtab(btn.dataset.formatSubtab);
    });
    window.addEventListener('sf-side-tab', (e) => {
      if (e?.detail) setSideTab(String(e.detail));
    });
    setSideTab(SF.activeSideTab || 'templates', { persist: false });
  }

  function initSelectionSidebarPanel() {
    /* Akkordeon entfernt — SoftMaker-Tabs via initSideTabs() */
  }

  function updatePropsSidebarOverflow() {
    const sidebar = document.getElementById('editorRightSidebar');
    const wrap = document.getElementById('propsPanelWrap');
    if (!sidebar || !wrap) return;
    wrap.classList.remove('props-sidebar-overflow');
  }

  function initPropsSidebarLayoutObserver() {
    const sidebar = document.getElementById('editorRightSidebar');
    const wrap = document.getElementById('propsPanelWrap');
    if (!sidebar || !wrap || wrap.dataset.layoutRo === '1') return;
    wrap.dataset.layoutRo = '1';
    if (window.ResizeObserver) {
      new ResizeObserver(() => {
        requestAnimationFrame(() => {
          updatePropsSidebarOverflow();
          updateTemplatesPickerLayout();
        });
      }).observe(sidebar);
    }
    window.addEventListener('resize', updatePropsSidebarOverflow);
  }

  function objectPanelTitle(node) {
    const prefix = I.objectsTitle || 'Objekte';
    if (!node) return prefix;
    return prefix + ' – ' + layerItemLabel(node);
  }

  function wrapObjectPanelContent(title, innerHtml) {
    const isOpen = SF.objectPanelOpen !== false;
    return '<div class="props-object-accordion' + (isOpen ? ' open' : '') + '" id="propsObjectAccordion">' +
      '<button type="button" class="props-layers-header" id="propsObjectToggle">' +
      '<span>' + escapeHtml(title) + '</span>' +
      '<span class="props-accordion-chevron">▾</span></button>' +
      '<div class="props-object-body">' + innerHtml + '</div></div>';
  }

  function bindObjectPanelToggle() {
    document.getElementById('propsObjectToggle')?.addEventListener('click', () => {
      SF.objectPanelOpen = !SF.objectPanelOpen;
      localStorage.setItem('sf_object_open', SF.objectPanelOpen ? '1' : '0');
      document.getElementById('propsObjectAccordion')?.classList.toggle('open', SF.objectPanelOpen);
      syncLayersPropsLayout();
      requestAnimationFrame(updateTemplatesPickerLayout);
    });
  }

  function renderLayersPanel() {
    const panel = document.getElementById('propsLayersPanel');
    if (!panel || !SF.canEdit) return;

    const nodes = getSlideLayerNodes();
    let html = '<div class="props-layers-accordion open" id="propsLayersAccordion">' +
      '<div class="props-layers-body">';

    if (!nodes.length) {
      html += '<div class="props-layers-empty">' + escapeHtml(I.layersEmpty || '') + '</div>';
    } else {
      html += '<p class="props-layers-hint">' + escapeHtml(I.layersHint || '') + '</p>';
      html += '<ul class="props-layers-list">';
      nodes.forEach((node) => {
        const id = node.id();
        const active = SF.selectedNodes.includes(node);
        html += '<li data-layer-id="' + escapeHtml(id) + '">' +
          '<span class="props-layer-drag-handle" draggable="true" title="' + escapeHtml(I.layersDrag || 'Ziehen zum Sortieren') + '" aria-hidden="true">⠿</span>' +
          '<button type="button" class="props-layer-item' + (active ? ' active' : '') + '" data-layer-id="' + escapeHtml(id) + '">' +
          '<span class="props-layer-icon" aria-hidden="true">' + layerIconHtml(node) + '</span>' +
          '<span class="props-layer-name">' + escapeHtml(layerItemLabel(node)) + '</span>' +
          '</button></li>';
      });
      html += '</ul>';
    }

    html += '</div></div>';
    panel.innerHTML = html;

    panel.querySelectorAll('button.props-layer-item').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const node = findNodeById(btn.dataset.layerId);
        if (node) {
          if (e.shiftKey) toggleSelectNode(node);
          else selectNodes([node]);
        }
      });
    });

    bindLayersListDrag(panel.querySelector('.props-layers-list'));
    syncLayersPropsLayout();
  }

  function renderTemplatesPanel() {
    const panel = document.getElementById('propsTemplatesPanel');
    if (!panel) return;

    const setId = SF.meta.layout_set_id;
    if (!setId || SF.layoutSetMode || SF.templateMode || !SF.canEdit) {
      panel.hidden = false;
      panel.innerHTML = '<div class="props-templates-accordion open"><div class="props-templates-body">' +
        '<p class="props-video-note props-templates-hint">' + escapeHtml(I.sideTemplatesEmpty || I.templatePickerHint || '') + '</p>' +
        '</div></div>';
      syncLayersPropsLayout();
      return;
    }

    panel.hidden = false;
    let html = '<div class="props-templates-accordion open" id="propsTemplatesAccordion">' +
      '<div class="props-templates-body">' +
      '<p class="props-video-note props-templates-hint">' + escapeHtml(I.templatePickerHint || '') + '</p>' +
      '<div class="props-templates-scroll"><div class="editor-layout-picker" id="templatePickerList" role="listbox">' +
      '<p class="props-video-note">' + escapeHtml(SF.i18n.loading) + '</p>' +
      '</div></div></div></div>';
    panel.innerHTML = html;
    loadTemplatePickerPanel();
    syncLayersPropsLayout();
    requestAnimationFrame(updateTemplatesPickerLayout);
  }

  function selectNodes(nodes) {
    const list = (Array.isArray(nodes) ? nodes : [nodes]).filter(Boolean);
    SF.selectedNodes = list;
    SF.selectedNode = list.length === 1 ? list[0] : (list.length ? list[list.length - 1] : null);
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
    renderPropsPanel(null);
    renderLayersPanel();
    updateSelectionActionButtons();
    syncRibbonStartTab();
  }

  function updateSelectionActionButtons() {
    const count = SF.selectedNodes.length;
    const hasSingle = count === 1 && SF.canEdit;
    const hasMultiTopLevel = SF.selectedNodes.filter(isTopLevelNode).length >= 2 && SF.canEdit;
    const canUngroup = count === 1 && isObjectGroup(SF.selectedNodes[0]) && SF.canEdit;
    setRibbonCommandsDisabled('duplicate', !hasSingle);
    setRibbonCommandsDisabled('copy', !hasSingle);
    setRibbonCommandsDisabled('cut', !hasSingle);
    setRibbonCommandsDisabled('group', !hasMultiTopLevel);
    setRibbonCommandsDisabled('ungroup', !canUngroup);
    syncRibbonStartTab();
  }

  function textTemplatePreviewCss(t, scale) {
    const ratio = scale || 0.22;
    const size = Math.max(11, Math.min(30, Math.round((t.fontSize || 32) * ratio)));
    const parts = [
      'font-family:' + (t.fontFamily || 'Open Sans') + ',sans-serif',
      'font-size:' + size + 'px',
      'font-weight:' + (t.fontWeight === 'bold' ? 'bold' : 'normal'),
      'font-style:' + (t.italic ? 'italic' : 'normal'),
      'color:' + (t.color || '#ffffff'),
    ];
    const deco = [];
    if (t.underline) deco.push('underline');
    if (t.strikethrough) deco.push('line-through');
    if (deco.length) parts.push('text-decoration:' + deco.join(' '));
    if (t.uppercase) parts.push('text-transform:uppercase');
    if (t.smallCaps) parts.push('font-variant:small-caps');
    return parts.join(';');
  }

  function textTemplateMatchesObject(t, obj) {
    const lhT = t.lineHeight != null ? t.lineHeight : 1.2;
    const lhO = obj.lineHeight != null ? obj.lineHeight : 1.2;
    const lsT = t.letterSpacing != null ? t.letterSpacing : 0;
    const lsO = obj.letterSpacing != null ? obj.letterSpacing : 0;
    return (t.fontFamily || 'Open Sans') === (obj.fontFamily || 'Open Sans')
      && (t.fontSize || 32) === (obj.fontSize || 32)
      && (t.color || '#ffffff') === (obj.color || '#ffffff')
      && (t.fontWeight || 'normal') === (obj.fontWeight || 'normal')
      && !!t.italic === !!obj.italic
      && !!t.underline === !!obj.underline
      && !!t.strikethrough === !!obj.strikethrough
      && !!t.uppercase === !!obj.uppercase
      && !!t.smallCaps === !!obj.smallCaps
      && (t.align || 'left') === (obj.align || 'left')
      && Math.abs(lhT - lhO) < 0.001
      && Math.abs(lsT - lsO) < 0.001;
  }

  function matchTextTemplateFromObject(obj) {
    if (!SF.textTemplates || !SF.textTemplates.length) return null;
    return SF.textTemplates.find((t) => textTemplateMatchesObject(t, obj)) || null;
  }

  function applyRibbonTextTemplate(templateId) {
    const node = ribbonStartTextNode();
    const t = SF.textTemplates.find((tt) => tt.id === templateId);
    if (!node || !t) return;
    applyTextTemplateToNode(node, t);
    refreshCanvas();
    scheduleSave();
    closeRibbonTemplateDropdown();
    syncRibbonStartTab();
  }

  function ribbonTemplateVisibleCount() {
    const gallery = document.getElementById('rb_templateGallery');
    if (!gallery) return 1;
    const tileW = 80; /* 76px tile + 4px gap */
    const width = gallery.clientWidth || 0;
    if (width < 8) return 1;
    return Math.max(1, Math.floor(width / tileW));
  }

  function updateRibbonTemplateTrigger(obj) {
    const matched = obj ? matchTextTemplateFromObject(obj) : null;
    const matchedId = matched ? matched.id : '';
    document.querySelectorAll('#rb_templateGallery .ribbon-template-tile').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.templateId === matchedId);
    });
    document.querySelectorAll('#rb_templatePalette .ribbon-template-item').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.templateId === matchedId);
    });
  }

  function setRibbonTemplateControlsEnabled(enabled) {
    const templateBtn = document.getElementById('rb_templateBtn');
    if (templateBtn) templateBtn.disabled = !enabled;
    document.querySelectorAll('#rb_templateGallery .ribbon-template-tile').forEach((btn) => {
      btn.disabled = !enabled;
    });
    document.querySelectorAll('#rb_templatePalette .ribbon-template-item').forEach((btn) => {
      btn.disabled = !enabled;
    });
  }

  function renderRibbonTemplateGallery(force) {
    const gallery = document.getElementById('rb_templateGallery');
    const panel = document.getElementById('rb_templatePalette');
    if (!gallery || !panel) return;
    const templates = SF.textTemplates || [];
    const visible = templates.length === 0 ? 0 : Math.min(templates.length, ribbonTemplateVisibleCount());
    const key = templates.length + ':' + visible;
    if (!force && gallery.dataset.galleryKey === key && gallery.childElementCount === visible) {
      const node = ribbonStartTextNode();
      setRibbonTemplateControlsEnabled(!!node);
      updateRibbonTemplateTrigger(node ? nodeToObject(node) : null);
      return;
    }
    gallery.dataset.galleryKey = key;
    const sample = I.templateStyleSample || 'AaBbCc';
    const shown = templates.slice(0, visible);

    gallery.innerHTML = shown.map((t) =>
      '<button type="button" class="ribbon-template-tile" role="option" data-template-id="' + escapeHtml(t.id) + '" title="' + escapeHtml(t.name) + '">' +
        '<span class="ribbon-template-tile-preview" style="' + textTemplatePreviewCss(t, 0.22) + '">' + escapeHtml(sample) + '</span>' +
        '<span class="ribbon-template-tile-name">' + escapeHtml(t.name) + '</span>' +
      '</button>'
    ).join('');

    gallery.querySelectorAll('.ribbon-template-tile').forEach((btn) => {
      btn.addEventListener('click', () => applyRibbonTextTemplate(btn.dataset.templateId));
    });

    panel.innerHTML = templates.map((t) =>
      '<button type="button" class="ribbon-template-item" role="menuitem" data-template-id="' + escapeHtml(t.id) + '">' +
        '<span class="ribbon-template-item-preview" style="' + textTemplatePreviewCss(t, 0.2) + '">' + escapeHtml(t.name) + '</span>' +
      '</button>'
    ).join('');
    panel.querySelectorAll('.ribbon-template-item').forEach((btn) => {
      btn.addEventListener('click', () => applyRibbonTextTemplate(btn.dataset.templateId));
    });

    const node = ribbonStartTextNode();
    setRibbonTemplateControlsEnabled(!!node);
    updateRibbonTemplateTrigger(node ? nodeToObject(node) : null);
  }

  function renderRibbonTemplateMenu() {
    renderRibbonTemplateGallery(true);
  }

  function closeRibbonTemplateDropdown() {
    const panel = document.getElementById('rb_templatePalette');
    const btn = document.getElementById('rb_templateBtn');
    if (panel) {
      panel.hidden = true;
      panel.style.left = '';
      panel.style.top = '';
      panel.style.minWidth = '';
    }
    if (btn) btn.setAttribute('aria-expanded', 'false');
  }

  function positionRibbonTemplateDropdown() {
    const panel = document.getElementById('rb_templatePalette');
    const btn = document.getElementById('rb_templateBtn');
    if (!panel || !btn) return;
    const rect = btn.getBoundingClientRect();
    panel.style.left = Math.max(8, Math.min(rect.right - 220, window.innerWidth - 228)) + 'px';
    panel.style.top = (rect.bottom + 4) + 'px';
    panel.style.minWidth = '220px';
  }

  function toggleRibbonTemplateDropdown() {
    const panel = document.getElementById('rb_templatePalette');
    const btn = document.getElementById('rb_templateBtn');
    if (!panel || !btn || btn.disabled) return;
    const willOpen = panel.hidden;
    closeRibbonTemplateDropdown();
    closeRibbonBrandDropdown();
    closeRibbonBgBrandDropdown();
    if (willOpen) {
      panel.hidden = false;
      btn.setAttribute('aria-expanded', 'true');
      positionRibbonTemplateDropdown();
    }
  }

  function initRibbonTemplateGalleryObserver() {
    const wrap = document.getElementById('rb_templateGalleryWrap');
    const gallery = document.getElementById('rb_templateGallery');
    if (!wrap || wrap.dataset.galleryObs === '1') return;
    wrap.dataset.galleryObs = '1';
    let raf = 0;
    const schedule = () => {
      cancelAnimationFrame(raf);
      raf = requestAnimationFrame(() => renderRibbonTemplateGallery(false));
    };
    if (typeof ResizeObserver !== 'undefined') {
      const ro = new ResizeObserver(schedule);
      ro.observe(wrap);
      if (gallery) ro.observe(gallery);
    }
    window.addEventListener('resize', schedule);
  }

  function stripLineListPrefix(line) {
    return line.replace(/^\s*\d+\.\s+/, '').replace(/^\s*[-*]\s+/, '');
  }

  function detectTextListMode(text) {
    const lines = (text || '').split('\n').filter((l) => l.trim() !== '');
    if (!lines.length) return 'none';
    if (lines.every((l) => /^\s*[-*]\s+/.test(l))) return 'bullet';
    if (lines.every((l) => /^\s*\d+\.\s+/.test(l))) return 'number';
    return 'none';
  }

  function applyTextListMode(node, mode) {
    const lines = node.text().split('\n');
    const cleaned = lines.map((line) => (line.trim() === '' ? line : stripLineListPrefix(line)));
    if (mode === 'bullet') {
      node.text(cleaned.map((line) => (line.trim() === '' ? line : '* ' + line)).join('\n'));
    } else if (mode === 'number') {
      let n = 1;
      node.text(cleaned.map((line) => {
        if (line.trim() === '') return line;
        return (n++) + '. ' + line;
      }).join('\n'));
    } else {
      node.text(cleaned.join('\n'));
    }
    refreshCanvas();
    scheduleSave();
  }

  function ribbonStartTextNode() {
    if (SF.selectedNodes.length !== 1 || !SF.selectedNode) return null;
    const type = SF.selectedNode.getAttr('objType');
    return type === 'text' ? SF.selectedNode : null;
  }

  function setRibbonStartEnabled(enabled) {
    const ids = ['rb_bold', 'rb_italic', 'rb_underline', 'rb_strikethrough', 'rb_uppercase', 'rb_smallcaps',
      'rb_font', 'rb_fontsize', 'rb_lineheight', 'rb_letterspacing', 'rb_color', 'rb_opacity',
      'rb_bullet', 'rb_number', 'rb_align_left', 'rb_align_center', 'rb_align_right'];
    ids.forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.disabled = !enabled;
    });
    setRibbonTemplateControlsEnabled(enabled);
    const brandBtn = document.getElementById('rb_brandBtn');
    if (brandBtn) brandBtn.disabled = !enabled;
    document.querySelectorAll('#rb_brandPalette .brand-swatch').forEach((btn) => {
      btn.disabled = !enabled;
    });
    if (!enabled) {
      closeRibbonTemplateDropdown();
      closeRibbonBrandDropdown();
    }
  }

  function closeRibbonBrandDropdown() {
    const panel = document.getElementById('rb_brandPalette');
    const btn = document.getElementById('rb_brandBtn');
    if (panel) panel.hidden = true;
    if (btn) btn.setAttribute('aria-expanded', 'false');
  }

  function toggleRibbonBrandDropdown() {
    const panel = document.getElementById('rb_brandPalette');
    const btn = document.getElementById('rb_brandBtn');
    if (!panel || !btn || btn.disabled) return;
    const willOpen = panel.hidden;
    closeRibbonBrandDropdown();
    closeRibbonBgBrandDropdown();
    closeRibbonObjectBrandDropdowns();
    closeRibbonTemplateDropdown();
    if (willOpen) {
      panel.hidden = false;
      btn.setAttribute('aria-expanded', 'true');
    }
  }

  function syncRibbonStartTab() {
    const node = ribbonStartTextNode();
    if (!node) {
      setRibbonStartEnabled(false);
      ['rb_bold', 'rb_italic', 'rb_underline', 'rb_strikethrough', 'rb_uppercase', 'rb_smallcaps',
        'rb_bullet', 'rb_number', 'rb_align_left', 'rb_align_center', 'rb_align_right'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
          el.classList.remove('active');
          el.dataset.checked = '0';
        }
      });
      updateRibbonTemplateTrigger(null);
      return;
    }
    const obj = nodeToObject(node);
    setRibbonStartEnabled(true);
    const toggles = [
      ['rb_bold', obj.fontWeight === 'bold'],
      ['rb_italic', !!obj.italic],
      ['rb_underline', !!obj.underline],
      ['rb_strikethrough', !!obj.strikethrough],
      ['rb_uppercase', !!obj.uppercase],
      ['rb_smallcaps', !!obj.smallCaps],
    ];
    toggles.forEach(([id, active]) => {
      const el = document.getElementById(id);
      if (!el) return;
      el.dataset.checked = active ? '1' : '0';
      el.classList.toggle('active', active);
    });
    const fontEl = document.getElementById('rb_font');
    if (fontEl) fontEl.value = obj.fontFamily || 'Open Sans';
    const sizeEl = document.getElementById('rb_fontsize');
    if (sizeEl) sizeEl.value = obj.fontSize || 32;
    const lhEl = document.getElementById('rb_lineheight');
    if (lhEl) lhEl.value = obj.lineHeight || 1.2;
    const lsEl = document.getElementById('rb_letterspacing');
    if (lsEl) lsEl.value = obj.letterSpacing ?? 0;
    const colorEl = document.getElementById('rb_color');
    if (colorEl) colorEl.value = obj.color || '#ffffff';
    markActiveBrandSwatches('rb_brandPalette', obj.color || '#ffffff');
    const opEl = document.getElementById('rb_opacity');
    const opVal = Math.round((obj.opacity ?? 1) * 100);
    if (opEl) {
      opEl.value = opVal;
      opEl.setAttribute('aria-label', (I.opacity || 'Deckkraft') + ' ' + opVal + '%');
    }
    const opLabel = document.getElementById('rb_opacityVal');
    if (opLabel) opLabel.textContent = String(opVal);
    const opIcon = document.getElementById('rb_opacityIcon');
    if (opIcon) opIcon.title = (I.opacity || 'Deckkraft') + ' ' + opVal + '%';
    const listMode = detectTextListMode(node.text());
    ['rb_bullet', 'rb_number'].forEach((id) => {
      const el = document.getElementById(id);
      if (!el) return;
      const active = (id === 'rb_bullet' && listMode === 'bullet') || (id === 'rb_number' && listMode === 'number');
      el.dataset.checked = active ? '1' : '0';
      el.classList.toggle('active', active);
    });
    const align = obj.align || 'left';
    ['left', 'center', 'right'].forEach((a) => {
      const el = document.getElementById('rb_align_' + a);
      if (!el) return;
      const active = align === a;
      el.dataset.checked = active ? '1' : '0';
      el.classList.toggle('active', active);
    });
    updateRibbonTemplateTrigger(obj);
  }

  function initRibbonStartTab() {
    const fontEl = document.getElementById('rb_font');
    if (!fontEl || fontEl.dataset.wired === '1') return;
    fontEl.dataset.wired = '1';
    fontEl.innerHTML = FONT_OPTIONS.map((f) =>
      '<option value="' + escapeHtml(f) + '">' + escapeHtml(f) + '</option>'
    ).join('');

    const palette = document.getElementById('rb_brandPalette');
    if (palette) {
      palette.innerHTML = SF.brandColors.map((c) =>
        '<button type="button" class="brand-swatch" role="menuitem" data-color="' + c.hex + '" title="' + escapeHtml(c.name || c.hex) + '" aria-label="' + escapeHtml(c.name || c.hex) + '" style="background:' + c.hex + '"></button>'
      ).join('');
      palette.querySelectorAll('.brand-swatch').forEach((btn) => {
        btn.addEventListener('click', () => {
          const node = ribbonStartTextNode();
          if (!node) return;
          const colorInput = document.getElementById('rb_color');
          if (colorInput) colorInput.value = btn.dataset.color;
          node.fill(btn.dataset.color);
          markActiveBrandSwatches('rb_brandPalette', btn.dataset.color);
          refreshCanvas();
          scheduleSave();
          closeRibbonBrandDropdown();
        });
      });
    }
    document.getElementById('rb_brandBtn')?.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleRibbonBrandDropdown();
    });
    document.addEventListener('click', (e) => {
      if (!e.target.closest('[data-ribbon-brand-menu="textColor"]')) closeRibbonBrandDropdown();
    });
    renderRibbonTemplateMenu();
    initRibbonTemplateGalleryObserver();
    document.getElementById('rb_templateBtn')?.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleRibbonTemplateDropdown();
    });
    document.addEventListener('click', (e) => {
      if (!e.target.closest('[data-ribbon-template-menu]')) closeRibbonTemplateDropdown();
    });

    const bindRbToggle = (id, onToggle) => {
      const el = document.getElementById(id);
      if (!el) return;
      el.addEventListener('click', () => {
        const node = ribbonStartTextNode();
        if (!node) return;
        const next = el.dataset.checked !== '1';
        el.dataset.checked = next ? '1' : '0';
        el.classList.toggle('active', next);
        onToggle(node, next);
      });
    };

    const updateRbFontStyle = (node) => {
      const parts = [];
      if (document.getElementById('rb_italic')?.dataset.checked === '1') parts.push('italic');
      if (document.getElementById('rb_bold')?.dataset.checked === '1') parts.push('bold');
      node.fontStyle(parts.length ? parts.join(' ') : 'normal');
      node.setAttr('fontWeight', document.getElementById('rb_bold')?.dataset.checked === '1' ? 'bold' : 'normal');
      node.setAttr('italic', document.getElementById('rb_italic')?.dataset.checked === '1');
      refreshCanvas();
      scheduleSave();
    };

    const updateRbTextDecoration = (node) => {
      const parts = [];
      if (document.getElementById('rb_underline')?.dataset.checked === '1') parts.push('underline');
      if (document.getElementById('rb_strikethrough')?.dataset.checked === '1') parts.push('line-through');
      node.textDecoration(parts.join(' '));
      node.setAttr('underline', document.getElementById('rb_underline')?.dataset.checked === '1');
      node.setAttr('strikethrough', document.getElementById('rb_strikethrough')?.dataset.checked === '1');
      refreshCanvas();
      scheduleSave();
    };

    bindRbToggle('rb_bold', (node) => updateRbFontStyle(node));
    bindRbToggle('rb_italic', (node) => updateRbFontStyle(node));
    bindRbToggle('rb_underline', (node) => updateRbTextDecoration(node));
    bindRbToggle('rb_strikethrough', (node) => updateRbTextDecoration(node));
    bindRbToggle('rb_uppercase', (node, state) => { node.setAttr('uppercase', state); scheduleSave(); });
    bindRbToggle('rb_smallcaps', (node, state) => { node.setAttr('smallCaps', state); scheduleSave(); });

    const bindRbListToggle = (id, mode) => {
      const el = document.getElementById(id);
      if (!el) return;
      el.addEventListener('click', () => {
        const node = ribbonStartTextNode();
        if (!node) return;
        const current = detectTextListMode(node.text());
        applyTextListMode(node, current === mode ? 'none' : mode);
        syncRibbonStartTab();
      });
    };
    bindRbListToggle('rb_bullet', 'bullet');
    bindRbListToggle('rb_number', 'number');

    ['left', 'center', 'right'].forEach((align) => {
      document.getElementById('rb_align_' + align)?.addEventListener('click', () => {
        const node = ribbonStartTextNode();
        if (!node) return;
        node.align(align);
        refreshCanvas();
        scheduleSave();
        syncRibbonStartTab();
      });
    });

    document.getElementById('rb_font')?.addEventListener('change', (e) => {
      const node = ribbonStartTextNode();
      if (!node) return;
      node.fontFamily(e.target.value);
      refreshCanvas();
      scheduleSave();
    });
    document.getElementById('rb_fontsize')?.addEventListener('input', (e) => {
      const node = ribbonStartTextNode();
      if (!node) return;
      node.fontSize(parseInt(e.target.value, 10) || 1);
      const ls = parseFloat(node.getAttr('letterSpacing')) || 0;
      node.letterSpacing(ls * node.fontSize());
      refreshCanvas();
      scheduleSave();
    });
    document.getElementById('rb_lineheight')?.addEventListener('input', (e) => {
      const node = ribbonStartTextNode();
      if (!node) return;
      const lh = Math.max(0.8, Math.min(3, parseFloat(e.target.value) || 1.2));
      node.lineHeight(lh);
      node.setAttr('lineHeight', lh);
      refreshCanvas();
      scheduleSave();
    });
    document.getElementById('rb_letterspacing')?.addEventListener('input', (e) => {
      const node = ribbonStartTextNode();
      if (!node) return;
      const ls = Math.max(-0.2, Math.min(1, parseFloat(e.target.value) || 0));
      node.setAttr('letterSpacing', ls);
      node.letterSpacing(ls * node.fontSize());
      refreshCanvas();
      scheduleSave();
    });
    document.getElementById('rb_color')?.addEventListener('input', (e) => {
      const node = ribbonStartTextNode();
      if (!node) return;
      node.fill(e.target.value);
      markActiveBrandSwatches('rb_brandPalette', e.target.value);
      refreshCanvas();
      scheduleSave();
    });
    document.getElementById('rb_opacity')?.addEventListener('input', (e) => {
      const node = ribbonStartTextNode();
      if (!node) return;
      const val = parseInt(e.target.value, 10) || 0;
      node.opacity(val / 100);
      const label = document.getElementById('rb_opacityVal');
      if (label) label.textContent = String(val);
      const aria = (I.opacity || 'Deckkraft') + ' ' + val + '%';
      e.target.setAttribute('aria-label', aria);
      const opIcon = document.getElementById('rb_opacityIcon');
      if (opIcon) opIcon.title = aria;
      refreshCanvas();
      scheduleSave();
    });

    document.addEventListener('scroll', () => {
      const templatePanel = document.getElementById('rb_templatePalette');
      if (templatePanel && !templatePanel.hidden) positionRibbonTemplateDropdown();
    }, true);
    window.addEventListener('resize', () => {
      const templatePanel = document.getElementById('rb_templatePalette');
      if (templatePanel && !templatePanel.hidden) positionRibbonTemplateDropdown();
    });

    syncRibbonStartTab();
  }

  function initMediaSidebarPanel() {
    const accordion = document.getElementById('propsMediaAccordion');
    if (!accordion) return;
    accordion.classList.add('open');
    SF.mediaPanelOpen = true;
  }

  function initRibbonEditorHooks() {
    initRibbonStartTab();
    syncRibbonCommandStates();
    if (window.SlideForgeSpellcheck?.rewire) {
      window.SlideForgeSpellcheck.rewire();
    }
  }

  function ribbonObjectColorNode() {
    const node = SF.selectedNode;
    if (!node || SF.selectedNodes.length !== 1) return null;
    const type = node.getAttr('objType');
    if (type === 'text' || type === 'video' || type === 'audio') return null;
    if (type === 'rect' || type === 'ellipse' || type === 'shape' || type === 'image') return node;
    return null;
  }

  function shapeSupportsRibbonFill(node) {
    const type = node.getAttr('objType');
    if (type === 'image') return false;
    if (type === 'shape' && SHAPE_OPEN[node.getAttr('shapeType')]) return false;
    return type === 'rect' || type === 'ellipse' || type === 'shape';
  }

  function setRibbonObjectColorEnabled(enabled, fillSupported) {
    const fillGroup = document.getElementById('ribbonObjectFillGroup');
    const strokeGroup = document.getElementById('ribbonObjectStrokeGroup');
    if (fillGroup) fillGroup.classList.toggle('is-disabled', !enabled);
    if (strokeGroup) strokeGroup.classList.toggle('is-disabled', !enabled);
    ['rb_objStroke', 'rb_objStrokeWidth'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.disabled = !enabled;
    });
    const fillColors = document.getElementById('ribbonObjectFillColors');
    if (fillColors) fillColors.classList.toggle('is-disabled', !enabled || !fillSupported);
    ['rb_objFillNone', 'rb_objFillSolid', 'rb_objFillGradient', 'rb_objFill'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.disabled = !enabled || !fillSupported;
    });
    const opEl = document.getElementById('rb_objOpacity');
    if (opEl) opEl.disabled = !enabled;
    document.querySelectorAll(
      '#rb_objFillBrandSwatches .brand-swatch, #rb_objGrad1BrandSwatches .brand-swatch, #rb_objGrad2BrandSwatches .brand-swatch'
    ).forEach((btn) => {
      btn.disabled = !enabled || !fillSupported;
    });
    document.querySelectorAll('#rb_objStrokeBrandSwatches .brand-swatch').forEach((btn) => {
      btn.disabled = !enabled;
    });
  }

  function closeRibbonObjectBrandDropdowns() {
    /* Objekt-Fläche/Kontur nutzen Inline-Swatches; Dropdowns nur noch andernorts. */
  }

  function positionRibbonObjectBrandDropdown() {}

  function toggleRibbonObjectBrandDropdown() {}

  function brandSwatchHtml() {
    return (SF.brandColors || []).map((c) =>
      '<button type="button" class="brand-swatch" data-color="' + c.hex + '" title="' + escapeHtml(c.name || c.hex) + '" aria-label="' + escapeHtml(c.name || c.hex) + '" style="background:' + c.hex + '"></button>'
    ).join('');
  }

  function markActiveBrandSwatches(containerId, hex) {
    const wrap = document.getElementById(containerId);
    if (!wrap) return;
    const target = String(hex || '').toLowerCase();
    wrap.querySelectorAll('.brand-swatch').forEach((btn) => {
      btn.classList.toggle('active', String(btn.dataset.color || '').toLowerCase() === target);
    });
  }

  function applyObjectFillNone(node) {
    if (!node) return;
    node.setAttr('fillType', 'none');
    node.fillEnabled(false);
    refreshCanvas();
    scheduleSave();
    syncRibbonObjectColor();
  }

  function applyObjectFillSolid(node, color) {
    if (!node) return;
    node.setAttr('fillType', 'solid');
    node.fillEnabled(true);
    node.fillPriority('color');
    node.fill(color || node.fill() || '#cccccc');
    refreshCanvas();
    scheduleSave();
    syncRibbonObjectColor();
  }

  function applyObjectFillGradient(node, c1, c2, angle) {
    if (!node) return;
    node.setAttr('fillType', 'gradient');
    node.setAttr('gradColor1', c1 || '#3a6c8d');
    node.setAttr('gradColor2', c2 || '#87b42b');
    node.setAttr('gradAngle', angle !== undefined ? angle : 90);
    node.fillEnabled(true);
    applyShapeGradientVisual(node);
    refreshCanvas();
    scheduleSave();
    syncRibbonObjectColor();
  }

  function updateObjectGradientPreview() {
    const c1 = document.getElementById('objGradColor1')?.value || '#3a6c8d';
    const c2 = document.getElementById('objGradColor2')?.value || '#87b42b';
    const angle = parseInt(document.getElementById('objGradAngle')?.value, 10) || 0;
    const preview = document.getElementById('objectGradientPreview');
    const label = document.getElementById('objGradAngleLabel');
    if (preview) preview.style.background = 'linear-gradient(' + angle + 'deg, ' + c1 + ', ' + c2 + ')';
    if (label) label.textContent = (I.angle || 'Winkel') + ' (' + angle + '°)';
  }

  function syncObjectFillModePanels(node, fillSupported) {
    const solid = document.getElementById('ribbonObjectFillSolid');
    const gradient = document.getElementById('ribbonObjectFillGradientEditor');
    const opacity = document.getElementById('ribbonObjectFillOpacity');
    const fillType = node && fillSupported
      ? (node.getAttr('fillType') || 'solid')
      : '';
    const isNone = fillType === 'none';
    const isGrad = fillType === 'gradient';
    const isSolid = fillSupported && !!node && !isNone && !isGrad;

    if (solid) solid.hidden = !isSolid;
    if (gradient) gradient.hidden = !isGrad;
    if (opacity) opacity.hidden = !node || (fillSupported && isNone);

    document.getElementById('rb_objFillNone')?.classList.toggle('active', fillSupported && isNone);
    document.getElementById('rb_objFillSolid')?.classList.toggle('active', isSolid);
    document.getElementById('rb_objFillGradient')?.classList.toggle('active', fillSupported && isGrad);

    const c1 = document.getElementById('objGradColor1');
    const c2 = document.getElementById('objGradColor2');
    const angle = document.getElementById('objGradAngle');
    [c1, c2, angle].forEach((el) => {
      if (el) el.disabled = !isGrad;
    });

    if (isGrad && node) {
      if (c1) c1.value = node.getAttr('gradColor1') || '#3a6c8d';
      if (c2) c2.value = node.getAttr('gradColor2') || '#87b42b';
      if (angle) angle.value = String(node.getAttr('gradAngle') !== undefined ? node.getAttr('gradAngle') : 90);
      markActiveBrandSwatches('rb_objGrad1BrandSwatches', c1?.value);
      markActiveBrandSwatches('rb_objGrad2BrandSwatches', c2?.value);
    }
    updateObjectGradientPreview();
  }

  function applyObjectGradientFromEditor(node) {
    if (!node) return;
    const c1 = document.getElementById('objGradColor1')?.value || '#3a6c8d';
    const c2 = document.getElementById('objGradColor2')?.value || '#87b42b';
    const angle = parseInt(document.getElementById('objGradAngle')?.value, 10) || 0;
    updateObjectGradientPreview();
    applyObjectFillGradient(node, c1, c2, angle);
  }

  function syncRibbonObjectColor() {
    const node = ribbonObjectColorNode();
    const fillSupported = node ? shapeSupportsRibbonFill(node) : false;
    if (!node) {
      setRibbonObjectColorEnabled(false, false);
      syncObjectFillModePanels(null, false);
      return;
    }
    setRibbonObjectColorEnabled(true, fillSupported);
    const obj = nodeToObject(node);
    const fillType = node.getAttr('fillType') || obj.fillType || 'solid';
    syncObjectFillModePanels(node, fillSupported);
    const fillEl = document.getElementById('rb_objFill');
    if (fillEl && fillSupported && fillType === 'solid') {
      fillEl.value = node.fill() || obj.fill || '#cccccc';
      markActiveBrandSwatches('rb_objFillBrandSwatches', fillEl.value);
    }
    const strokeVal = (obj.stroke && obj.stroke !== 'transparent') ? obj.stroke : '#ffffff';
    const strokeEl = document.getElementById('rb_objStroke');
    if (strokeEl) strokeEl.value = strokeVal;
    markActiveBrandSwatches('rb_objStrokeBrandSwatches', strokeVal);
    const swEl = document.getElementById('rb_objStrokeWidth');
    if (swEl) swEl.value = String(obj.strokeWidth || 0);
    const opEl = document.getElementById('rb_objOpacity');
    const opVal = Math.round((obj.opacity ?? 1) * 100);
    if (opEl) {
      opEl.value = opVal;
      opEl.setAttribute('aria-label', (I.opacity || 'Deckkraft') + ' ' + opVal + '%');
    }
    const opLabel = document.getElementById('rb_objOpacityVal');
    if (opLabel) opLabel.textContent = String(opVal);
    const opIcon = document.getElementById('rb_objOpacityIcon');
    if (opIcon) opIcon.title = (I.opacity || 'Deckkraft') + ' ' + opVal + '%';
  }

  function initRibbonObjectColor() {
    const fillModes = document.getElementById('ribbonObjectFillModes');
    if (!fillModes || fillModes.dataset.wired === '1') return;
    fillModes.dataset.wired = '1';

    const swatchHtml = brandSwatchHtml();
    const fillSwatches = document.getElementById('rb_objFillBrandSwatches');
    const grad1Swatches = document.getElementById('rb_objGrad1BrandSwatches');
    const grad2Swatches = document.getElementById('rb_objGrad2BrandSwatches');
    const strokeSwatches = document.getElementById('rb_objStrokeBrandSwatches');
    if (fillSwatches) fillSwatches.innerHTML = swatchHtml;
    if (grad1Swatches) grad1Swatches.innerHTML = swatchHtml;
    if (grad2Swatches) grad2Swatches.innerHTML = swatchHtml;
    if (strokeSwatches) strokeSwatches.innerHTML = swatchHtml;

    fillSwatches?.querySelectorAll('.brand-swatch').forEach((btn) => {
      btn.addEventListener('click', () => {
        const node = ribbonObjectColorNode();
        if (!node || !shapeSupportsRibbonFill(node)) return;
        const fillEl = document.getElementById('rb_objFill');
        if (fillEl) fillEl.value = btn.dataset.color;
        applyObjectFillSolid(node, btn.dataset.color);
      });
    });
    strokeSwatches?.querySelectorAll('.brand-swatch').forEach((btn) => {
      btn.addEventListener('click', () => {
        const node = ribbonObjectColorNode();
        if (!node) return;
        const strokeEl = document.getElementById('rb_objStroke');
        if (strokeEl) strokeEl.value = btn.dataset.color;
        node.stroke(btn.dataset.color);
        refreshCanvas();
        scheduleSave();
        syncRibbonObjectColor();
      });
    });
    const wireGradSwatches = (wrap, colorInputId) => {
      wrap?.querySelectorAll('.brand-swatch').forEach((btn) => {
        btn.addEventListener('click', () => {
          const node = ribbonObjectColorNode();
          if (!node || !shapeSupportsRibbonFill(node)) return;
          const colorEl = document.getElementById(colorInputId);
          if (colorEl) colorEl.value = btn.dataset.color;
          applyObjectGradientFromEditor(node);
        });
      });
    };
    wireGradSwatches(grad1Swatches, 'objGradColor1');
    wireGradSwatches(grad2Swatches, 'objGradColor2');

    document.getElementById('rb_objFillNone')?.addEventListener('click', () => {
      const node = ribbonObjectColorNode();
      if (!node || !shapeSupportsRibbonFill(node)) return;
      applyObjectFillNone(node);
    });
    document.getElementById('rb_objFillSolid')?.addEventListener('click', () => {
      const node = ribbonObjectColorNode();
      if (!node || !shapeSupportsRibbonFill(node)) return;
      const color = node.getAttr('gradColor1') || document.getElementById('rb_objFill')?.value || node.fill() || '#3a6c8d';
      applyObjectFillSolid(node, color);
    });
    document.getElementById('rb_objFill')?.addEventListener('input', (e) => {
      const node = ribbonObjectColorNode();
      if (!node || !shapeSupportsRibbonFill(node)) return;
      applyObjectFillSolid(node, e.target.value);
    });
    document.getElementById('rb_objFillGradient')?.addEventListener('click', () => {
      const node = ribbonObjectColorNode();
      if (!node || !shapeSupportsRibbonFill(node)) return;
      const c1 = node.getAttr('gradColor1') || node.fill() || '#3a6c8d';
      const c2 = node.getAttr('gradColor2') || '#87b42b';
      const angle = node.getAttr('gradAngle') !== undefined ? node.getAttr('gradAngle') : 90;
      const c1El = document.getElementById('objGradColor1');
      const c2El = document.getElementById('objGradColor2');
      const angleEl = document.getElementById('objGradAngle');
      if (c1El) c1El.value = c1;
      if (c2El) c2El.value = c2;
      if (angleEl) angleEl.value = String(angle);
      applyObjectFillGradient(node, c1, c2, angle);
    });
    document.getElementById('rb_objStroke')?.addEventListener('input', (e) => {
      const node = ribbonObjectColorNode();
      if (!node) return;
      node.stroke(e.target.value);
      markActiveBrandSwatches('rb_objStrokeBrandSwatches', e.target.value);
      refreshCanvas();
      scheduleSave();
    });
    document.getElementById('rb_objStrokeWidth')?.addEventListener('input', (e) => {
      const node = ribbonObjectColorNode();
      if (!node) return;
      node.strokeWidth(parseInt(e.target.value, 10) || 0);
      refreshCanvas();
      scheduleSave();
    });
    document.getElementById('rb_objOpacity')?.addEventListener('input', (e) => {
      const node = ribbonObjectColorNode();
      if (!node) return;
      const val = parseInt(e.target.value, 10) || 0;
      node.opacity(val / 100);
      const label = document.getElementById('rb_objOpacityVal');
      if (label) label.textContent = String(val);
      const aria = (I.opacity || 'Deckkraft') + ' ' + val + '%';
      e.target.setAttribute('aria-label', aria);
      const opIcon = document.getElementById('rb_objOpacityIcon');
      if (opIcon) opIcon.title = aria;
      refreshCanvas();
      scheduleSave();
    });

    ['objGradColor1', 'objGradColor2', 'objGradAngle'].forEach((id) => {
      document.getElementById(id)?.addEventListener('input', () => {
        const node = ribbonObjectColorNode();
        if (!node || !shapeSupportsRibbonFill(node)) {
          updateObjectGradientPreview();
          return;
        }
        applyObjectGradientFromEditor(node);
      });
    });

    syncRibbonObjectColor();
  }

  function syncRibbonObjectDelete() {
    const btn = document.getElementById('deleteObjBtn');
    if (!btn) return;
    btn.disabled = !(SF.canEdit && SF.selectedNodes.length === 1 && SF.selectedNode);
  }

  function initRibbonObjectDelete() {
    const btn = document.getElementById('deleteObjBtn');
    if (!btn || btn.dataset.wired === '1') return;
    btn.dataset.wired = '1';
    btn.addEventListener('click', () => {
      const node = SF.selectedNode;
      if (!node || SF.selectedNodes.length !== 1 || !SF.canEdit) return;
      removeAnimationBadge(node);
      node.destroy();
      deselect();
      refreshCanvas();
      scheduleSave();
    });
    syncRibbonObjectDelete();
  }

  function refreshPropsPanel() {
    const textPanel = document.getElementById('propsTextPanel');
    const templatesPanel = document.getElementById('propsTextTemplatesPanel');
    const positionPanel = document.getElementById('propsPositionPanel');
    if (SF.selectedNodes.length > 1) {
      const panel = document.getElementById('propsObjectPanel');
      if (textPanel) textPanel.innerHTML = propsPanelEmptyHtml();
      if (templatesPanel) templatesPanel.innerHTML = '';
      if (positionPanel) {
        const label = (I.multiSelect || '{n} selected').replace('{n}', String(SF.selectedNodes.length));
        positionPanel.innerHTML = '<div class="props-empty">' + escapeHtml(label) + '</div>';
      }
      if (panel) {
        const label = (I.multiSelect || '{n} selected').replace('{n}', String(SF.selectedNodes.length));
        panel.innerHTML = '<div class="props-empty">' + escapeHtml(label) + '</div>';
      }
      syncFormatSubtabs(null);
      syncRibbonStartTab();
      syncRibbonObjectColor();
      syncRibbonObjectShapes(null);
      syncRibbonObjectDelete();
      return;
    }
    renderPropsPanel(SF.selectedNode);
    syncRibbonStartTab();
    syncRibbonObjectColor();
    syncRibbonObjectDelete();
  }

  function propsPanelEmptyHtml() {
    const html = escapeHtml(I.propsEmpty || '').replace(/&lt;br\s*\/?&gt;/gi, '<br>');
    return '<div class="props-empty">' + html + '</div>';
  }

  function ribbonPropsSection(label, bodyHtml, extraClass) {
    if (!bodyHtml) return '';
    const noLabel = !label;
    const extra = extraClass ? ' ' + extraClass : '';
    return '<div class="ribbon-group ribbon-props-section' + extra + (noLabel ? ' ribbon-props-section--no-label' : '') + '">' +
      '<div class="ribbon-group-content ribbon-props-section-body">' + bodyHtml + '</div>' +
      (noLabel ? '' : '<div class="ribbon-group-label">' + escapeHtml(label) + '</div>') +
      '</div>';
  }

  function logosPlaceholderSelectHtml(currentRole) {
    const roles = SF.logosPlaceholderRoles.length ? SF.logosPlaceholderRoles : Object.keys(SF.logosRoleLabels);
    const labels = SF.logosRoleLabels || {};
    const options = [{ value: '', label: I.setPlaceholderNone || '—' }];
    roles.forEach((r) => options.push({ value: r, label: labels[r] || r }));
    let html = fieldSelectKV('p_setRole', I.setPlaceholderRole || 'Set-Platzhalter', options, currentRole || '');
    html += '<button type="button" class="button button-ghost button-sm" id="refreshSetPlaceholderBtn" style="width:100%; margin-top:8px;"' +
      (currentRole ? '' : ' disabled') + '>' + escapeHtml(I.setPlaceholderRefresh || 'Aktualisieren') + '</button>';
    html += '<div class="props-video-note" style="margin-top:6px;">' + escapeHtml(I.setPlaceholderRefreshHint || '') + '</div>';
    return html;
  }

  function buildRibbonShapesPropsHtml(node, obj) {
    let html = '';

    if (obj.type === 'shape') {
      const st = obj.shapeType;
      if (st === 'bracket') {
        html += fieldRibbonShapePicker('p_bracketStyle', BRACKET_STYLE_OPTIONS, obj.bracketStyle || 'curly-left');
      } else if (st === 'star') {
        html += fieldRibbonStarPoints('p_starPoints', obj.starPoints || 5);
      } else if (st === 'arrow') {
        html += fieldRibbonShapePicker('p_arrowStyle', ARROW_STYLE_OPTIONS, obj.arrowStyle || 'right');
      } else if (st === 'speech-bubble') {
        html += fieldRibbonShapePicker('p_bubbleStyle', BUBBLE_STYLE_OPTIONS, obj.bubbleStyle || 'rect-left');
      }
      return html;
    }

    if (obj.type === 'image') {
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
      return html;
    }

    if (obj.type === 'video' || obj.type === 'audio') {
      const isAudio = obj.type === 'audio';
      html += '<div class="props-video-note">' + I.mediaPlaceholderHint.replace('{type}', isAudio ? I.mediaAudio : I.mediaVideo) + '</div>';
      html += '<button type="button" class="button button-ghost button-sm" id="replaceAssetBtn" style="width:100%;">' + (isAudio ? I.replaceAudio : I.replaceVideo) + '</button>';
      html += fieldSelectKV('p_playTrigger', I.playTrigger, PLAY_TRIGGER_OPTIONS, obj.playTrigger || 'manual');
      if ((obj.playTrigger || 'manual') === 'timed') {
        html += fieldNumber('p_playDelay', I.playDelay, obj.playDelay || 0);
      }
      html += '<label style="display:flex; align-items:center; gap:8px; margin-top:10px;"><input type="checkbox" id="p_hideControls" style="width:auto;" ' + (obj.hideControls ? 'checked' : '') + '> ' + I.hideControls + '</label>';
      html += '<label style="display:flex; align-items:center; gap:8px; margin-top:8px;"><input type="checkbox" id="p_loop" style="width:auto;" ' + (obj.loop ? 'checked' : '') + '> ' + I.loop + '</label>';
      html += '<div class="props-video-note">' + I.playTriggerHint + '</div>';
    }

    return html;
  }

  function syncRibbonObjectShapes(node) {
    const group = document.getElementById('ribbonObjectShapesGroup');
    const panel = document.getElementById('ribbonObjectShapesPanel');
    if (!group || !panel) return;
    if (!node || SF.selectedNodes.length !== 1 || isObjectGroup(node)) {
      group.hidden = true;
      panel.innerHTML = '';
      return;
    }
    const obj = nodeToObject(node);
    if (obj.type === 'text') {
      group.hidden = true;
      panel.innerHTML = '';
      return;
    }
    const html = buildRibbonShapesPropsHtml(node, obj);
    if (!html) {
      group.hidden = true;
      panel.innerHTML = '';
      return;
    }
    group.hidden = false;
    panel.innerHTML = html;
  }

  const LOCK_ICON_OPEN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.75-1.5"/></svg>';
  const LOCK_ICON_CLOSED = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
  /* Canva-Stil: Pfeil über/unter Linie(n) */
  const ICON_LAYER_UP = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v10"/><path d="M8 8l4-4 4 4"/><path d="M5 18h14"/></svg>';
  const ICON_LAYER_DOWN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 6h14"/><path d="M12 10v10"/><path d="M8 16l4 4 4-4"/></svg>';
  const ICON_LAYER_TOP = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v9"/><path d="M8 7l4-4 4 4"/><path d="M5 15h14"/><path d="M5 19h14"/></svg>';
  const ICON_LAYER_BOTTOM = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 5h14"/><path d="M5 9h14"/><path d="M12 12v9"/><path d="M8 17l4 4 4-4"/></svg>';
  const ICON_POSITION_COPY = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>';
  const ICON_POSITION_PASTE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="4" width="8" height="4" rx="1"/><path d="M8 5H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="10" y="11" width="6" height="6" rx="1"/></svg>';

  function ribbonPositionTallBtn({ id, className, attrs, icon, label, title, disabled }) {
    return '<button type="button" class="ribbon-btn ribbon-btn-tall' + (className ? ' ' + className : '') + '"' +
      (id ? ' id="' + id + '"' : '') +
      (attrs ? ' ' + attrs : '') +
      (disabled ? ' disabled' : '') +
      ' title="' + escapeHtml(title || label) + '" aria-label="' + escapeHtml(title || label) + '">' +
      icon +
      '<span class="ribbon-btn-label">' + escapeHtml(label) + '</span>' +
      '</button>';
  }

  function fieldRibbonNum(id, label, value) {
    return '<label class="ribbon-position-field" for="' + id + '">' +
      '<span class="ribbon-position-field-label">' + escapeHtml(label) + '</span>' +
      '<input type="number" id="' + id + '" value="' + value + '">' +
      '</label>';
  }

  function buildRibbonCoordsHtml(node, obj, isText) {
    const wLabel = I.width || 'B';
    const hLabel = I.height || 'H';
    const rotLabel = I.rotationShort || I.rotation || '°';
    let html = '<div class="ribbon-position-coords">';
    html += '<div class="ribbon-position-coords-row ribbon-position-coords-row--size">';
    html += fieldRibbonNum('p_w', wLabel, obj.w);
    if (!isText) {
      html += fieldRibbonNum('p_h', hLabel, obj.h);
      html += '<div class="ribbon-position-lock-cell">' +
        '<button type="button" id="aspectLockBtn" class="aspect-lock-btn" title="' + escapeHtml(I.aspectLock) + '">' +
        (node.getAttr('aspectLocked') ? LOCK_ICON_CLOSED : LOCK_ICON_OPEN) + '</button></div>';
    }
    html += '</div>';
    html += '<div class="ribbon-position-coords-row ribbon-position-coords-row--place">';
    html += fieldRibbonNum('p_x', 'X', obj.x);
    html += fieldRibbonNum('p_y', 'Y', obj.y);
    html += fieldRibbonNum('p_rotation', rotLabel, obj.rotation || 0);
    html += '</div></div>';
    return html;
  }

  function buildRibbonGroupCoordsHtml(obj) {
    const countLabel = (I.groupChildCount || '{n} Objekte').replace('{n}', String((obj.children || []).length));
    const rotLabel = I.rotationShort || I.rotation || '°';
    let html = '<div class="ribbon-position-coords">';
    html += '<div class="ribbon-position-coords-row ribbon-position-coords-row--group">';
    html += '<span class="ribbon-position-group-badge" title="' + escapeHtml(countLabel) + '">' + escapeHtml(countLabel) + '</span>';
    html += fieldRibbonNum('p_x', 'X', obj.x);
    html += fieldRibbonNum('p_y', 'Y', obj.y);
    html += fieldRibbonNum('p_rotation', rotLabel, obj.rotation || 0);
    html += '<label class="ribbon-position-field ribbon-position-field-range" for="p_opacity">' +
      '<span class="ribbon-position-field-label">%</span>' +
      '<input type="range" id="p_opacity" min="0" max="100" value="' + Math.round((obj.opacity || 1) * 100) + '">' +
      '</label>';
    html += '</div></div>';
    return html;
  }

  function buildRibbonLayerHtml() {
    const layers = [
      ['up', I.layerUp, ICON_LAYER_UP],
      ['down', I.layerDown, ICON_LAYER_DOWN],
      ['front', I.layerFront, ICON_LAYER_TOP],
      ['back', I.layerBack, ICON_LAYER_BOTTOM],
    ];
    return '<div class="position-action-grid">' + layers.map(([action, label, icon]) =>
      positionActionBtnHtml({
        attrs: 'data-layer="' + action + '"',
        icon,
        label,
        title: label,
      })
    ).join('') + '</div>';
  }

  function buildRibbonPositionTransferHtml() {
    const canPaste = !!SF.positionClipboard;
    return '<div class="position-action-grid position-action-grid--2">' +
      positionActionBtnHtml({
        id: 'copyPositionBtn',
        icon: ICON_POSITION_COPY,
        label: I.positionCopyShort || I.positionCopy,
        title: I.positionCopy,
      }) +
      positionActionBtnHtml({
        id: 'pastePositionBtn',
        icon: ICON_POSITION_PASTE,
        label: I.positionPasteShort || I.positionPaste,
        title: I.positionPaste,
        disabled: !canPaste,
      }) +
      '</div>';
  }

  function buildRibbonPositionPanelHtml(node, obj) {
    const isText = obj.type === 'text';
    const isGroup = isObjectGroup(node);
    let html = '';
    /* Canva-Reihenfolge: Anordnen → Ausrichten → Erweitert → Übertragen */
    if (!isGroup && !isText) {
      html += ribbonPropsSection('', buildRibbonLayerHtml());
    }
    html += ribbonPropsSection(I.alignToSlide || I.positionAlign, alignGridRibbonHtml());
    if (isGroup) {
      html += ribbonPropsSection(I.advanced || I.positionSize, buildRibbonGroupCoordsHtml(obj));
    } else {
      html += ribbonPropsSection(I.advanced || I.positionSize, buildRibbonCoordsHtml(node, obj, isText));
    }
    html += ribbonPropsSection(I.positionTransfer || I.positionCopy, buildRibbonPositionTransferHtml());
    return html;
  }

  function renderPositionPanel(node) {
    const panel = document.getElementById('propsPositionPanel');
    if (!panel) return;
    if (!node || SF.selectedNodes.length !== 1) {
      panel.innerHTML = propsPanelEmptyHtml();
      return;
    }
    const obj = nodeToObject(node);
    panel.innerHTML = '<div class="ribbon-props-sections ribbon-position-sections">' +
      buildRibbonPositionPanelHtml(node, obj) + '</div>';
  }

  function buildEffectPropsHtml(node, obj, isText) {
    let html = fieldIconPicker('p_anim', I.effect, ANIMATIONS, obj.animType || 'none', 'effect-icon-grid anim-icon-grid');
    html += propsTransferRowHtml('copyAnimationBtn', 'pasteAnimationBtn', I.animationCopy, I.animationPaste, !!SF.animationClipboard);
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
    return html;
  }

  function applyTextTemplateToNode(node, t) {
    if (!node || !t) return;
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
    if (t.lineHeight != null) {
      node.setAttr('lineHeight', t.lineHeight);
      node.lineHeight(t.lineHeight);
    }
    if (t.letterSpacing != null) {
      node.setAttr('letterSpacing', t.letterSpacing);
      node.letterSpacing(t.letterSpacing * (parseInt(t.fontSize, 10) || node.fontSize() || 32));
    }
  }

  async function refreshSetPlaceholder(node) {
    const target = node || SF.selectedNode;
    if (!target || target.getAttr('objType') !== 'text') return;
    const role = target.getAttr('setRole') || target.getAttr('logosRole');
    if (!role) return;

    const setId = SF.layoutSetMode ? SF.id : (SF.meta?.layout_set_id || '');
    if (!setId || (!SF.hasLayoutSet && !SF.layoutSetMode)) {
      setSaveStatus(I.setPlaceholderRefreshNoSet || 'Kein Folien-Set zugewiesen.', true);
      return;
    }

    const slide = SF.slides[SF.currentIndex];
    const layoutKey = slide?.layoutKey || layoutKeyForLogosRole(role);
    if (!layoutKey) {
      setSaveStatus(I.setPlaceholderRefreshNoLayout || 'Kein Layout für dieses Element gefunden.', true);
      return;
    }

    let layoutSlideId = slide?.layoutSetSlideId || '';
    if (!layoutSlideId) {
      layoutSlideId = (SF.logosLayoutSlideIds || {})[layoutKey] || '';
    }
    if (!layoutSlideId && (role === 'scripture_ref' || role === 'scripture_verse')) {
      layoutSlideId = (SF.logosLayoutSlideIds || {}).scripture_block || '';
    }
    if (!layoutSlideId && SF.layoutSetMode && slide?.id) {
      layoutSlideId = slide.id;
    }

    await applyLayoutFromSet(setId, layoutKey, layoutSlideId);
    const refreshed = findNodeByLogosRole(role);
    if (refreshed) selectNode(refreshed);
    refreshPropsPanel();
  }

  function renderEffectsPanel(node, obj, isText) {
    const panel = document.getElementById('propsEffectsPanel');
    if (!panel) return;
    if (!node || SF.selectedNodes.length !== 1 || isObjectGroup(node)) {
      panel.innerHTML = propsPanelEmptyHtml();
      return;
    }
    panel.innerHTML = '<div class="ribbon-props-sections">' +
      ribbonPropsSection(I.tabEffect || I.sideTabEffects || 'Effekte', buildEffectPropsHtml(node, obj, isText)) +
      '</div>';
  }

  function renderPropsPanel(node) {
    const textPanel = document.getElementById('propsTextPanel');
    const templatesPanel = document.getElementById('propsTextTemplatesPanel');
    const objectPanel = document.getElementById('propsObjectPanel');
    if (!textPanel && !objectPanel && !document.getElementById('propsEffectsPanel')) return;

    if (!node) {
      if (textPanel) textPanel.innerHTML = '';
      if (templatesPanel) templatesPanel.innerHTML = '';
      if (objectPanel) objectPanel.innerHTML = '';
      renderPositionPanel(null);
      renderEffectsPanel(null, null, false);
      syncFormatSubtabs(null);
      syncRibbonObjectColor();
      syncRibbonObjectShapes(null);
      syncRibbonObjectDelete();
      return;
    }

    const obj = nodeToObject(node);
    const isText = obj.type === 'text';

    renderPositionPanel(node);
    renderEffectsPanel(node, obj, isText);

    if (isText) {
      if (objectPanel) objectPanel.innerHTML = '';
      renderTextPropsPanel(textPanel, node, obj);
      renderTextTemplatesPanel(templatesPanel, obj);
      syncFormatSubtabs(node);
      wirePropsPanel(node, obj);
      syncRibbonObjectColor();
      syncRibbonObjectShapes(null);
      syncRibbonObjectDelete();
      return;
    }

    if (textPanel) textPanel.innerHTML = '';
    if (templatesPanel) templatesPanel.innerHTML = '';
    if (objectPanel) objectPanel.innerHTML = '';
    syncFormatSubtabs(node);
    syncRibbonObjectShapes(node);
    wirePropsPanel(node, obj);
    syncRibbonObjectColor();
    syncRibbonObjectDelete();
  }

  function renderTextPropsPanel(panel, node, obj) {
    if (!panel) return;
    SF.activePropsTab = 'format';

    let body = '';
    body += '<label for="p_text" class="sr-only">' + escapeHtml(I.text || 'Text') + '</label>';
    body += '<textarea id="p_text" class="props-text-autoheight" rows="1">' + escapeHtml(obj.text) + '</textarea>';
    body += '<label>' + I.formatSelection + '</label>';
    body += '<div class="format-toggle-group">' +
      '<button type="button" class="format-toggle-btn" data-wrap="**" style="font-weight:700;" title="' + I.bold + '">B</button>' +
      '<button type="button" class="format-toggle-btn" data-wrap="*" style="font-style:italic;" title="' + I.italic + '">I</button>' +
      '<button type="button" class="format-toggle-btn" data-wrap="++" style="text-decoration:underline;" title="' + I.underline + '">U</button>' +
      '<button type="button" class="format-toggle-btn" data-wrap="~~" style="text-decoration:line-through;" title="' + I.strikethrough + '">S</button>' +
      '<button type="button" class="format-toggle-btn" data-wraptag="upper" style="font-size:0.72em;" title="' + I.uppercase + '">AA</button>' +
      '<button type="button" class="format-toggle-btn" data-wraptag="sc" style="font-variant:small-caps;" title="' + I.smallcaps + '">Aa</button>' +
      '<button type="button" class="format-toggle-btn" id="markSelectionBtn" title="' + I.mark + '">🖊</button>' +
      '<button type="button" class="format-toggle-btn" id="colorSelectionBtn" title="' + I.textColorBtn + '">🎨</button>' +
      '</div>';
    body += '<div id="markSelectionPalette" hidden><div class="options-subtitle" style="margin-top:8px;">' + I.markColorPick + '</div><input type="color" id="markColorPicker" value="#fff176" style="width:100%; height:32px; margin-bottom:6px;">' + miniPaletteHtml('markColorPicker') + '</div>';
    body += '<div id="colorSelectionPalette" hidden><div class="options-subtitle" style="margin-top:8px;">' + I.textColorPick + '</div><input type="color" id="textColorPicker" value="#3a6c8d" style="width:100%; height:32px; margin-bottom:6px;">' + miniPaletteHtml('textColorPicker') + '</div>';
    body += propsHelpDisclosure('selection', I.selectionHintTitle || 'Hilfe: Text markieren', I.selectionHint);
    body += propsHelpDisclosure('markdown', I.markdownHintTitle || 'Hilfe: Markdown', I.markdownHint);

    panel.innerHTML = ribbonPropsSection('', body);
    bindPropsHelpDisclosures(panel);
    applySpellcheckAttrs(document.getElementById('p_text'));
    scheduleAutoGrowTextarea();
  }

  function renderTextTemplatesPanel(panel, obj) {
    if (!panel) return;
    if (!SF.hasLayoutSet) {
      panel.innerHTML = '';
      return;
    }
    let phBody = logosPlaceholderSelectHtml(obj.setRole || obj.logosRole || '');
    phBody += '<div class="props-video-note" style="margin-top:8px;">' + I.setPlaceholderHint + '</div>';
    panel.innerHTML = ribbonPropsSection('', phBody, 'props-text-placeholders');
  }

  function propsHelpOpen(id) {
    try {
      return localStorage.getItem('sf_props_help_' + id) === '1';
    } catch (e) {
      return false;
    }
  }

  function propsHelpDisclosure(id, title, bodyHtml) {
    return '<details class="props-help-disclosure"' + (propsHelpOpen(id) ? ' open' : '') + ' data-help-id="' + escapeHtml(id) + '">' +
      '<summary class="props-help-summary">' + escapeHtml(title) + '</summary>' +
      '<div class="props-help-body">' + bodyHtml + '</div>' +
      '</details>';
  }

  function bindPropsHelpDisclosures(root) {
    if (!root) return;
    root.querySelectorAll('.props-help-disclosure[data-help-id]').forEach((el) => {
      if (el.dataset.wired === '1') return;
      el.dataset.wired = '1';
      el.addEventListener('toggle', () => {
        try {
          localStorage.setItem('sf_props_help_' + el.dataset.helpId, el.open ? '1' : '0');
        } catch (e) { /* ignore */ }
      });
    });
  }

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
    return '<div class="props-accordion-group props-panel-accordion' + (isOpen ? ' open' : '') + '" data-accordion-group="' + id + '">' +
      '<button type="button" class="props-layers-header props-panel-accordion-header">' + escapeHtml(label) + '<span class="props-accordion-chevron">▾</span></button>' +
      '<div class="props-accordion-body props-panel-accordion-body">' + bodyHtml + '</div>' +
      '</div>';
  }
  function fieldRibbonShapePicker(id, options, selected) {
    return '<div class="ribbon-tall-row ribbon-shape-style-row" id="' + id + '_group" data-picker-field="' + id + '">' +
      options.map(o => {
        const flipCls = o.flip ? ' ribbon-shape-style-btn--flip' : '';
        const glyph = o.icon || '•';
        return '<button type="button" class="ribbon-btn ribbon-btn-tall ribbon-shape-style-btn' + (o.value === selected ? ' active' : '') + flipCls +
          '" data-icon-value="' + o.value + '" title="' + escapeHtml(o.label) + '" aria-label="' + escapeHtml(o.label) + '">' +
          '<span class="ribbon-shape-glyph" aria-hidden="true">' + glyph + '</span>' +
          '<span class="ribbon-btn-label">' + escapeHtml(o.label) + '</span></button>';
      }).join('') +
      '</div>';
  }

  function fieldRibbonStarPoints(id, value) {
    const fullLabel = I.starPoints || 'Anzahl Zacken';
    const shortLabel = I.starPointsLabel || 'Zacken';
    return '<div class="ribbon-shape-star-field">' +
      '<span class="ribbon-btn-label ribbon-shape-star-label">' + escapeHtml(shortLabel) + '</span>' +
      '<input type="number" id="' + id + '" min="3" max="20" step="1" value="' + value + '" title="' + escapeHtml(fullLabel) + '" aria-label="' + escapeHtml(fullLabel) + '">' +
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
      '<button type="button" class="ribbon-btn ribbon-btn-tall effect-icon-btn' + (o.value === hidden.value ? ' active' : '') + '" data-icon-value="' + o.value + '" title="' + escapeHtml(o.label) + '">' + o.icon + '<span class="ribbon-btn-label">' + escapeHtml(o.label) + '</span></button>'
    ).join('');
    group.querySelectorAll('[data-icon-value]').forEach(btn => {
      btn.addEventListener('click', () => {
        hidden.value = btn.dataset.iconValue;
        setTransitionPickerValue(hidden.value);
        /* Raster: Werte nur vorbereiten — Speichern über «Auf Auswahl/alle anwenden». */
        if (SF.editorViewMode === 'grid') return;
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

  function propsTransferRowHtml(copyId, pasteId, copyLabel, pasteLabel, canPaste) {
    return '<div class="props-transfer-row">' +
      '<button type="button" class="button button-ghost button-sm" id="' + copyId + '">' + escapeHtml(copyLabel) + '</button>' +
      '<button type="button" class="button button-ghost button-sm" id="' + pasteId + '"' + (canPaste ? '' : ' disabled') + '>' + escapeHtml(pasteLabel) + '</button>' +
      '</div>';
  }

  function copyPositionFromNode(node) {
    const obj = nodeToObject(node);
    SF.positionClipboard = {
      x: obj.x,
      y: obj.y,
      w: obj.w,
      h: obj.h,
      rotation: obj.rotation || 0,
      aspectLocked: !!node.getAttr('aspectLocked'),
      aspectRatio: node.getAttr('aspectRatio') || (obj.h ? obj.w / obj.h : 1),
    };
    setSaveStatus(I.propsCopied || 'Kopiert');
  }

  function pastePositionToNode(node) {
    if (!SF.positionClipboard || !node) return;
    const c = SF.positionClipboard;
    const type = node.getAttr('objType');
    node.scaleX(1);
    node.scaleY(1);
    if (type === 'ellipse') {
      node.x(c.x + c.w / 2);
      node.y(c.y + c.h / 2);
      applyWidth(node, type, c.w);
      applyHeight(node, type, c.h);
    } else if (type === 'objectGroup') {
      node.x(c.x);
      node.y(c.y);
      node.rotation(c.rotation || 0);
    } else {
      node.x(c.x);
      node.y(c.y);
      applyWidth(node, type, c.w);
      if (type !== 'text') applyHeight(node, type, c.h);
      node.rotation(c.rotation || 0);
      if (type !== 'text') {
        node.setAttr('aspectLocked', c.aspectLocked);
        node.setAttr('aspectRatio', c.aspectRatio);
      }
    }
    updateAnimationBadge(node);
    if (SF.transformer && SF.selectedNodes.includes(node)) SF.transformer.forceUpdate();
    refreshCanvas();
    scheduleSave();
    refreshPropsPanel();
  }

  function copyAnimationFromNode(node) {
    SF.animationClipboard = {
      animType: node.getAttr('animType') || 'none',
      animOrder: node.getAttr('animOrder') || 1,
      animAutoAdvance: node.getAttr('animAutoAdvance') || 0,
      animDuration: node.getAttr('animDuration') || 0,
      animPerLine: !!node.getAttr('animPerLine'),
    };
    setSaveStatus(I.propsCopied || 'Kopiert');
  }

  function pasteAnimationToNode(node) {
    if (!SF.animationClipboard || !node) return;
    const c = SF.animationClipboard;
    node.setAttr('animType', c.animType);
    node.setAttr('animOrder', c.animOrder);
    node.setAttr('animAutoAdvance', c.animAutoAdvance);
    node.setAttr('animDuration', c.animDuration);
    if (node.getAttr('objType') === 'text') {
      node.setAttr('animPerLine', c.animPerLine);
    }
    updateAnimationBadge(node);
    refreshCanvas();
    scheduleSave();
    refreshPropsPanel();
  }

  function wirePropsTransferButtons(node) {
    const on = (id, event, fn) => {
      const el = document.getElementById(id);
      if (el) el.addEventListener(event, fn);
    };
    on('copyPositionBtn', 'click', () => {
      copyPositionFromNode(node);
      refreshPropsPanel();
    });
    on('pastePositionBtn', 'click', () => pastePositionToNode(node));
    on('copyAnimationBtn', 'click', () => {
      copyAnimationFromNode(node);
      refreshPropsPanel();
    });
    on('pasteAnimationBtn', 'click', () => pasteAnimationToNode(node));
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

      const refreshSetBtn = document.getElementById('refreshSetPlaceholderBtn');
      if (refreshSetBtn) {
        refreshSetBtn.addEventListener('click', async (e) => {
          e.preventDefault();
          e.stopPropagation();
          await refreshSetPlaceholder(node);
        });
      }

      on('p_setRole', 'change', (e) => {
        const role = e.target.value;
        const refreshBtn = document.getElementById('refreshSetPlaceholderBtn');
        if (refreshBtn) refreshBtn.disabled = !role;
        if (role) {
          node.setAttr('setRole', role);
          node.setAttr('logosRole', null);
          const label = SF.logosRoleLabels[role] || role;
          if (!node.text().trim()) {
            const placeholder = '«' + label + '»';
            node.text(placeholder);
            const ta = document.getElementById('p_text');
            if (ta) {
              ta.value = placeholder;
              autoGrowTextarea(ta);
            }
          }
        } else {
          node.setAttr('setRole', null);
          node.setAttr('logosRole', null);
        }
        refreshCanvas();
        scheduleSave();
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
        btn.addEventListener('click', () => {
          const willOpen = palette.hidden;
          document.getElementById('markSelectionPalette')?.setAttribute('hidden', '');
          document.getElementById('colorSelectionPalette')?.setAttribute('hidden', '');
          document.getElementById('markSelectionBtn')?.classList.remove('active');
          document.getElementById('colorSelectionBtn')?.classList.remove('active');
          if (willOpen) {
            palette.hidden = false;
            btn.classList.add('active');
          } else {
            palette.hidden = true;
          }
        });
        picker.addEventListener('change', () => {
          wrapTextSelection('[' + tagName + '=' + picker.value + ']', '[/' + tagName + ']');
          palette.hidden = true;
          btn.classList.remove('active');
        });
        palette.querySelectorAll('.brand-swatch').forEach(sw => {
          sw.addEventListener('click', () => {
            wrapTextSelection('[' + tagName + '=' + sw.dataset.color + ']', '[/' + tagName + ']');
            palette.hidden = true;
            btn.classList.remove('active');
          });
        });
      }
      bindColorSelectionTool('markSelectionBtn', 'markSelectionPalette', 'markColorPicker', 'mark');
      bindColorSelectionTool('colorSelectionBtn', 'colorSelectionPalette', 'textColorPicker', 'color');
    } else if (type === 'rect' || type === 'ellipse' || type === 'shape') {
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

    document.querySelectorAll('[data-align-edge]').forEach(btn => {
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
        const action = btn.dataset.layer === 'up' ? 'up'
          : btn.dataset.layer === 'down' ? 'down'
          : btn.dataset.layer === 'front' ? 'front'
          : 'back';
        nudgeContentLayer(node, action);
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

    wirePropsTransferButtons(node);
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
      const label = I.textFieldDefault || 'Textfeld';
      obj = { id, type: 'text', x: centerX, y: centerY, w: 599, h: 70, text: label, fontFamily: 'Open Sans', fontSize: 65, fontWeight: 'normal', color: '#ffffff', align: 'left', rotation: 0, opacity: 1 };
    } else if (type === 'ellipse') {
      obj = { id, type: 'ellipse', x: centerX, y: centerY, w: 200, h: 200, fillType: 'solid', fill: '#3a6c8d', stroke: 'transparent', strokeWidth: 0, rotation: 0, opacity: 1 };
    } else if (type === 'rect') {
      obj = { id, type: 'rect', x: centerX, y: centerY, w: 240, h: 140, fillType: 'solid', fill: '#87b42b', stroke: 'transparent', strokeWidth: 0, rotation: 0, opacity: 1 };
    } else if (type === 'line') {
      obj = { id, type: 'shape', shapeType: 'line', x: centerX - 20, y: centerY + 80, w: 240, h: 4, stroke: '#ffffff', strokeWidth: 4, rotation: 0, opacity: 1 };
    } else if (type === 'bracket') {
      obj = { id, type: 'shape', shapeType: 'bracket', x: centerX + 60, y: centerY - 100, w: 72, h: 280, stroke: '#ffffff', strokeWidth: 6, rotation: 0, opacity: 1, bracketStyle: 'curly-left' };
    } else if (SHAPE_LABELS[type]) {
      obj = { id, type: 'shape', shapeType: type, x: centerX - 20, y: centerY - 20, w: 220, h: 200, fillType: 'solid', fill: '#3a6c8d', stroke: 'transparent', strokeWidth: 0, rotation: 0, opacity: 1, starPoints: 5, arrowStyle: 'right', bracketStyle: 'curly-left', bubbleStyle: 'rect-left' };
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

  function addLogosPlaceholder(role, placement) {
    const label = SF.logosRoleLabels[role] || role;
    const tplId = SF.elementTextLinks[role];
    const tpl = tplId ? SF.textTemplates.find(t => t.id === tplId) : null;
    const w = placement?.w ?? tpl?.w ?? 1720;
    const h = placement?.h ?? tpl?.h ?? 100;
    const centerX = Math.round((SF.meta.width - w) / 2);
    const centerY = Math.round(SF.meta.height / 3);
    const id = 'o' + Math.random().toString(16).slice(2, 10);
    const obj = {
      id, type: 'text', setRole: role,
      x: placement?.x ?? centerX,
      y: placement?.y ?? centerY,
      w, h, rotation: placement?.rotation ?? 0, opacity: placement?.opacity ?? 1,
      text: placement?.text ?? ('«' + label + '»'),
      fontFamily: placement?.fontFamily ?? tpl?.fontFamily ?? 'Open Sans',
      fontSize: placement ? (parseInt(placement.fontSize, 10) || 48) : (tpl ? (parseInt(tpl.fontSize, 10) || 48) : 48),
      fontWeight: (placement?.fontWeight ?? tpl?.fontWeight) === 'bold' ? 'bold' : 'normal',
      italic: placement?.italic ?? !!tpl?.italic,
      color: placement?.color ?? tpl?.color ?? '#ffffff',
      align: placement?.align ?? tpl?.align ?? 'left',
    };
    insertNode(createNode(obj));
  }

  function layoutKeyForLogosRole(role) {
    const idx = findSlideIndexForLogosRole(role);
    if (idx >= 0 && SF.slides[idx]?.layoutKey) {
      return SF.slides[idx].layoutKey;
    }
    const map = SF.logosLayoutMap || {};
    if (role === 'scripture_ref' || role === 'scripture_verse') {
      return map.scripture_block || map.scripture_ref || 'scripture_block';
    }
    return map[role] || role;
  }

  function findSlideIndexForLogosRole(role) {
    const slideIds = SF.logosLayoutSlideIds || {};
    const cachedId = slideIds[role]
      || (role === 'scripture_ref' || role === 'scripture_verse' ? slideIds.scripture_block : null);
    if (cachedId) {
      const cachedIdx = SF.slides.findIndex((s) => s.id === cachedId);
      if (cachedIdx >= 0) return cachedIdx;
    }
    const searchRoles = role === 'scripture_block'
      ? ['scripture_block', 'scripture_ref', 'scripture_verse']
      : (role === 'scripture_ref' || role === 'scripture_verse')
        ? ['scripture_ref', 'scripture_verse', 'scripture_block']
        : [role];
    for (let i = 0; i < SF.slides.length; i++) {
      const slide = SF.slides[i];
      if (!(slide.layoutKey || '').length) continue;
      const objects = slide.objects || [];
      if (searchRoles.some((r) => objects.some((o) => (o.setRole || o.logosRole) === r))) {
        return i;
      }
    }
    const layoutKey = (SF.logosLayoutMap || {})[role] || role;
    return SF.slides.findIndex((s) => (s.layoutKey || '') === layoutKey);
  }

  function findNodeByLogosRole(role) {
    if (!SF.layer || !role) return null;
    return getTopLevelNodes().find((n) => (n.getAttr('setRole') || n.getAttr('logosRole')) === role) || null;
  }

  async function insertLogosSlideElement(role) {
    if (!SF.layoutSetMode) {
      addLogosPlaceholder(role);
      return;
    }
    let slideIndex = findSlideIndexForLogosRole(role);
    if (slideIndex < 0) {
      await saveCurrentSlide(true);
      const res = await api('add_slide', { after_index: SF.slides.length - 1 });
      SF.slides = res.slides;
      slideIndex = SF.slides.length - 1;
      SF.slides[slideIndex].layoutKey = layoutKeyForLogosRole(role);
      SF.currentIndex = slideIndex;
      loadSlideIntoStage(slideIndex);
      await renderSlideFilmstrip();
      addLogosPlaceholder(role);
      scheduleSave();
      return;
    }
    if (slideIndex !== SF.currentIndex) {
      await switchToSlide(slideIndex);
    }
    const existing = findNodeByLogosRole(role);
    if (existing) {
      selectNode(existing);
      return;
    }
    const templateObj = SF.slides[slideIndex].objects?.find((o) => (o.setRole || o.logosRole) === role);
    addLogosPlaceholder(role, templateObj || undefined);
  }

  function renderTextTemplateButtons() {
    const el = document.getElementById('textTemplateButtons');
    if (!el) return;
    el.innerHTML = SF.textTemplates.map(t =>
      '<button type="button" class="ribbon-tool-btn" data-preset="' + t.id + '">' + escapeHtml(t.name) + '</button>'
    ).join('');
  }

  function slideInsertRolesFromZones(elementZones) {
    const slides = (elementZones && elementZones.slides) ? elementZones.slides : [];
    const insert = [];
    slides.forEach(role => {
      if (role === 'scripture_block') {
        insert.push('scripture_ref', 'scripture_verse');
      } else {
        insert.push(role);
      }
    });
    return [...new Set(insert)];
  }

  function additionalLogosSlideRolesFromZones(elementZones) {
    const roles = slideInsertRolesFromZones(elementZones);
    const standard = new Set(SF.standardElementRoles || []);
    return roles.filter((role) => !standard.has(role));
  }

  function collectElementZonesFromDom(root) {
    const elementZones = {};
    const scope = root || document;
    scope.querySelectorAll('.element-zone-list').forEach(list => {
      const zone = list.dataset.elementZone;
      if (!zone) return;
      elementZones[zone] = [...list.querySelectorAll('li')].map(li => li.dataset.role).filter(Boolean);
    });
    return elementZones;
  }

  function zoneLabelsMap() {
    return {
      slides: I.zoneSlides || 'Folien',
      footer: I.zoneFooter || 'Fußzeile',
      custom: I.zoneCustom || 'Eigener Bereich',
      unused: I.zoneUnused || 'Nicht verwendet',
    };
  }

  function renderElementLinkTableRows(roles, options) {
    const icons = SF.elementIconHtml || {};
    const labels = SF.logosRoleLabels || {};
    const badge = SF.logosBadgeHtml || '';
    const showLogosBadge = options?.showLogosBadge === true;
    return roles.map((role) => {
      const currentTpl = SF.elementTextLinks[role] || '';
      const selectOptions = '<option value="">' + escapeHtml(I.elementLinkNone || 'Standard') + '</option>' +
        SF.textTemplates.map((tt) =>
          '<option value="' + escapeHtml(tt.id) + '"' + (currentTpl === tt.id ? ' selected' : '') + '>' +
          escapeHtml(tt.name) + '</option>'
        ).join('');
      const logosBadge = showLogosBadge && showLogosBadgeForRole(role) ? badge : '';
      return '<tr>' +
        '<td class="element-links-icon-cell"><span class="element-row-icon">' + (icons[role] || '') + '</span></td>' +
        '<td class="element-links-name-cell">' + escapeHtml(labels[role] || role) + logosBadge + '</td>' +
        '<td><select class="element-link-select" data-element-link-role="' + escapeHtml(role) + '">' + selectOptions + '</select></td>' +
        '</tr>';
    }).join('');
  }

  function renderElementLinkTable(roles, showLogosBadge) {
    if (!roles.length) return '';
    return '<table class="data-table element-links-table">' +
      '<thead><tr><th></th><th>' + escapeHtml(I.elementLinkElement || 'Element') + '</th>' +
      '<th>' + escapeHtml(I.elementLinkTextTemplate || 'Textvorlage') + '</th></tr></thead>' +
      '<tbody>' + renderElementLinkTableRows(roles, { showLogosBadge }) + '</tbody></table>';
  }

  function renderElementZonesHtml(zones) {
    const zoneLabels = zoneLabelsMap();
    const icons = SF.elementIconHtml || {};
    const labels = SF.logosRoleLabels || {};
    const zoneKeys = SF.elementZoneKeys.length ? SF.elementZoneKeys : Object.keys(zoneLabels);
    return zoneKeys.map((zone) => {
      const roles = zones[zone] || [];
      const items = roles.map((role) =>
        '<li data-role="' + escapeHtml(role) + '" draggable="true">' +
          '<span class="text-template-drag-handle" aria-hidden="true">⠿</span>' +
          '<span class="element-zone-item-icon" aria-hidden="true">' + (icons[role] || '') + '</span>' +
          '<span>' + escapeHtml(labels[role] || role) + '</span>' +
        '</li>'
      ).join('');
      return '<div class="element-zone-block">' +
        '<div class="element-zone-title">' + escapeHtml(zoneLabels[zone] || zone) + '</div>' +
        '<ul class="element-zone-list" data-element-zone="' + escapeHtml(zone) + '">' + items + '</ul>' +
        '</div>';
    }).join('');
  }

  function bindElementZoneDragDrop(container, onZonesChange) {
    if (!container || container.dataset.zonesBound) return;
    container.dataset.zonesBound = '1';
    let dragItem = null;

    container.addEventListener('dragstart', (e) => {
      const li = e.target.closest('.element-zone-list li');
      if (!li) return;
      dragItem = li;
    });

    container.addEventListener('dragover', (e) => {
      if (!dragItem) return;
      e.preventDefault();
    });

    container.addEventListener('drop', (e) => {
      if (!dragItem) return;
      e.preventDefault();
      const list = e.target.closest('.element-zone-list');
      const li = e.target.closest('.element-zone-list li');
      if (!list) return;
      if (li && li !== dragItem) {
        if (dragItem.parentElement !== list) {
          list.insertBefore(dragItem, li);
        } else {
          const items = [...list.querySelectorAll('li')];
          const from = items.indexOf(dragItem);
          const to = items.indexOf(li);
          if (from >= 0 && to >= 0) {
            if (from < to) li.after(dragItem);
            else li.before(dragItem);
          }
        }
      } else if (!li && dragItem.parentElement !== list) {
        list.appendChild(dragItem);
      }
      dragItem = null;
      if (onZonesChange) onZonesChange();
    });

    container.addEventListener('dragend', () => {
      dragItem = null;
      if (onZonesChange) onZonesChange();
    });
  }

  function bindLogosInsertButtons(container) {
    if (!container || container.dataset.logosInsertBound) return;
    container.dataset.logosInsertBound = '1';
    container.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-set-role]');
      if (!btn || !container.contains(btn)) return;
      e.preventDefault();
      e.stopPropagation();
      insertLogosSlideElement(btn.dataset.setRole);
    });
  }

  function renderLogosSlideInsertButtons(elementZones) {
    const container = document.getElementById('logosSlideInsertButtons');
    const section = document.getElementById('logosSlideInsertSection');
    if (!container) return;
    const roles = additionalLogosSlideRolesFromZones(elementZones);
    const labels = SF.logosRoleLabels || {};
    const icons = SF.elementIconHtml || {};
    const badge = SF.logosBadgeHtml || '';
    if (!roles.length) {
      const emptyText = SF.i18n.logosInsertEmpty || '';
      container.innerHTML = emptyText
        ? '<p class="elements-panel-hint elements-panel-hint-empty">' + escapeHtml(emptyText) + '</p>'
        : '';
      if (section) section.style.display = emptyText ? '' : 'none';
      return;
    }
    if (section) section.style.display = '';
    container.innerHTML = roles.map(role =>
      '<button type="button" class="element-row-btn ribbon-tool-btn" data-set-role="' + escapeHtml(role) + '">' +
      '<span class="element-row-icon" aria-hidden="true">' + (icons[role] || '') + '</span>' +
      '<span class="element-row-label">' + escapeHtml(labels[role] || role) + '</span>' +
      badge + '</button>'
    ).join('');
    bindLogosInsertButtons(container);
  }

  function initElementsPanel() {
    if (!SF.layoutSetMode) return;
    bindLogosInsertButtons(document.getElementById('logosSlideInsertButtons'));
    renderLogosSlideInsertButtons(SF.elementZones || {});
    initElementLinksModal();
  }

  function activeLogosRolesFromZones() {
    const zones = SF.elementZones || {};
    const active = new Set();
    Object.keys(zones).forEach((zone) => {
      if (zone === 'unused') return;
      (zones[zone] || []).forEach((role) => active.add(role));
    });
    return active;
  }

  function showLogosBadgeForRole(role) {
    if (!SF.logosImporterEnabled || !role) return false;
    return activeLogosRolesFromZones().has(role);
  }

  function renderLogosImportSettingsHtml(settings) {
    const s = settings || SF.logosImportSettings || {};
    const h1 = s.h1Opener || 'always_separate';
    const sh = s.scriptureHeading || 'combine_if_layout_fits';
    const lg = String(s.listGrouping ?? 'layout');
    const tc = String(s.textMaxCharacters ?? 500);
    const h1Opts = [
      ['always_separate', I.logosImportH1Separate || 'Immer eigene Folie'],
      ['combine_with_first', I.logosImportH1Combine || 'Mit erstem Inhalt kombinieren'],
    ];
    const scriptureOpts = [
      ['scripture_always_separate', I.logosImportScriptureSeparate || ''],
      ['combine_if_layout_fits', I.logosImportScriptureCombineFit || ''],
      ['always_combined', I.logosImportScriptureCombineAlways || ''],
    ];
    const listOpts = [
      ['1', '1'],
      ['3', '3'],
      ['5', '5'],
      ['0', I.logosImportListUnlimited || 'Unbegrenzt'],
      ['layout', I.logosImportListLayout || 'Layout-abhängig'],
    ];
    const textOpts = [
      ['280', '280'],
      ['500', '500'],
      ['800', '800'],
      ['0', I.logosImportListUnlimited || 'Unbegrenzt'],
      ['layout', I.logosImportListLayout || 'Layout-abhängig'],
    ];
    const selectHtml = (name, cur, opts) =>
      '<select class="logos-import-select" data-logos-import-field="' + name + '">' +
      opts.map(([val, label]) =>
        '<option value="' + escapeHtml(val) + '"' + (String(cur) === val ? ' selected' : '') + '>' + escapeHtml(label) + '</option>'
      ).join('') + '</select>';
    return '<div class="logos-import-settings-form">' +
      '<div class="logos-import-field"><label class="options-subtitle">' + escapeHtml(I.logosImportH1Opener || 'Überschrift 1 – Eröffnungsfolie') + '</label>' + selectHtml('h1Opener', h1, h1Opts) + '</div>' +
      '<div class="logos-import-field"><label class="options-subtitle">' + escapeHtml(I.logosImportScriptureHeading || '') + '</label>' + selectHtml('scriptureHeading', sh, scriptureOpts) + '</div>' +
      '<div class="logos-import-field"><label class="options-subtitle">' + escapeHtml(I.logosImportListGrouping || '') + '</label>' + selectHtml('listGrouping', lg, listOpts) + '</div>' +
      '<div class="logos-import-field"><label class="options-subtitle">' + escapeHtml(I.logosImportTextMaxChars || '') + '</label>' + selectHtml('textMaxCharacters', tc, textOpts) + '</div>' +
      '</div>';
  }

  function collectLogosImportSettingsFromModal() {
    const root = document.getElementById('elementLinksLogosImportPanel');
    if (!root) return SF.logosImportSettings || {};
    const h1 = root.querySelector('[data-logos-import-field="h1Opener"]');
    const sh = root.querySelector('[data-logos-import-field="scriptureHeading"]');
    const lg = root.querySelector('[data-logos-import-field="listGrouping"]');
    const tc = root.querySelector('[data-logos-import-field="textMaxCharacters"]');
    return {
      h1Opener: h1 ? h1.value : 'always_separate',
      scriptureHeading: sh ? sh.value : 'combine_if_layout_fits',
      listGrouping: lg ? (lg.value === 'layout' ? 'layout' : parseInt(lg.value, 10)) : 'layout',
      textMaxCharacters: tc ? (tc.value === 'layout' ? 'layout' : parseInt(tc.value, 10)) : 500,
    };
  }

  let elementLinksMainTab = 'assignments';

  function bindElementLinksMainTabs() {
    const body = document.getElementById('elementLinksModalBody');
    if (!body) return;
    body.querySelectorAll('[data-el-main-tab]').forEach((btn) => {
      btn.addEventListener('click', () => {
        elementLinksMainTab = btn.dataset.elMainTab || 'assignments';
        body.querySelectorAll('[data-el-main-tab]').forEach((b) => {
          b.classList.toggle('active', b.dataset.elMainTab === elementLinksMainTab);
        });
        body.querySelectorAll('[data-el-main-panel]').forEach((p) => {
          p.hidden = p.dataset.elMainPanel !== elementLinksMainTab;
        });
      });
    });
  }

  function renderElementLinksModalBody() {
    const body = document.getElementById('elementLinksModalBody');
    if (!body) return;

    const standardRoles = SF.standardElementRoles.length
      ? SF.standardElementRoles
      : (SF.elementLinkRoles || []);
    const logosRoles = SF.logosImporterEnabled
      ? (SF.logosElementLinkRoles.length ? SF.logosElementLinkRoles : [])
      : [];
    const standardRoleSet = new Set(standardRoles);
    const zones = SF.elementZones || {};
    const slideZoneRoles = (zones.slides || []).filter((role) => !standardRoleSet.has(role));
    const logosOnlyRoles = slideZoneRoles.filter((role) => logosRoles.includes(role));
    const badge = SF.logosBadgeHtml || '';
    const logosEnabled = SF.logosImporterEnabled && logosRoles.length > 0;

    let html = '';
    if (logosEnabled) {
      html += '<div class="page-tabs element-links-main-tabs">';
      html += '<button type="button" class="page-tab-btn' + (elementLinksMainTab === 'assignments' ? ' active' : '') + '" data-el-main-tab="assignments">' + escapeHtml(I.elementLinksTabAssignments || 'Zuweisungen') + '</button>';
      html += '<button type="button" class="page-tab-btn' + (elementLinksMainTab === 'logos-import' ? ' active' : '') + '" data-el-main-tab="logos-import">' + escapeHtml(I.elementLinksTabLogosImport || 'Logos Import') + '</button>';
      html += '</div>';
    }

    html += '<div class="element-links-main-panel" data-el-main-panel="assignments"' + (logosEnabled && elementLinksMainTab !== 'assignments' ? ' hidden' : '') + '>';
    html += '<div class="element-links-modal-layout element-links-modal-layout--two-col">';
    html += '<div class="element-links-modal-col element-links-modal-col--left">';
    html += '<div class="element-links-tab-panel active" data-el-tab-panel="standard">';
    html += '<h3 class="element-links-modal-col-title">' + escapeHtml(I.elementLinksColStandard || 'Standard-Elemente') + '</h3>';
    html += '<p class="elements-panel-hint">' + escapeHtml(I.elementLinksColStandardDesc || '') + '</p>';
    html += renderElementLinkTable(standardRoles, true);
    html += '</div>';

    if (logosEnabled) {
      html += '<div class="element-links-tab-panel active" data-el-tab-panel="logos-elements">';
      html += '<h3 class="element-links-modal-col-title element-links-modal-col-title--logos">' +
        escapeHtml(I.elementLinksColLogosElements || 'Logos Elemente') + badge + '</h3>';
      html += '<p class="elements-panel-hint">' + escapeHtml(I.elementLinksColLogosDesc || '') + '</p>';
      html += renderElementLinkTable(logosOnlyRoles, true);
      html += '</div>';
    }
    html += '</div>';

    if (logosEnabled) {
      html += '<div class="element-links-modal-col element-links-modal-col--right">';
      html += '<div class="element-links-tab-panel active" data-el-tab-panel="logos-zones">';
      html += '<h3 class="element-links-modal-col-title element-links-modal-col-title--logos">' +
        escapeHtml(I.elementLinksColLogosMapping || I.elementLinksZonesTitle || 'Logos-Zuordnung') + '</h3>';
      html += '<p class="elements-panel-hint">' + escapeHtml(I.elementLinksZonesDesc || '') + '</p>';
      html += '<div id="elementLinksModalZones">' + renderElementZonesHtml(zones) + '</div>';
      html += '</div>';
      html += '</div>';
    }
    html += '</div></div>';

    if (logosEnabled) {
      html += '<div class="element-links-main-panel" id="elementLinksLogosImportPanel" data-el-main-panel="logos-import"' + (elementLinksMainTab !== 'logos-import' ? ' hidden' : '') + '>';
      html += renderLogosImportSettingsHtml(SF.logosImportSettings);
      html += '</div>';
    }

    body.innerHTML = html;
    bindElementLinksMainTabs();
  }

  function refreshStandardElementBadges() {
    const root = document.getElementById('standardElementButtons');
    if (!root) return;
    const active = activeLogosRolesFromZones();
    const badgeHtml = SF.logosBadgeHtml || '';
    root.querySelectorAll('[data-set-role]').forEach((btn) => {
      const role = btn.dataset.setRole || '';
      let badge = btn.querySelector('.element-row-logos-badge');
      const shouldShow = SF.logosImporterEnabled && active.has(role);
      if (shouldShow && !badge && badgeHtml) {
        btn.insertAdjacentHTML('beforeend', badgeHtml);
        badge = btn.querySelector('.element-row-logos-badge');
      }
      if (badge) {
        badge.style.display = shouldShow ? '' : 'none';
      }
    });
  }

  function collectElementLinksFromModal() {
    const links = {};
    document.querySelectorAll('#elementLinksModalBody [data-element-link-role]').forEach((sel) => {
      const role = sel.dataset.elementLinkRole;
      if (!role) return;
      links[role] = sel.value || null;
    });
    return links;
  }

  function initElementLinksModal() {
    const openBtn = document.getElementById('configureElementLinksBtn');
    const modal = document.getElementById('elementLinksModal');
    const closeBtn = document.getElementById('elementLinksModalClose');
    const saveBtn = document.getElementById('elementLinksModalSave');
    if (!openBtn || !modal) return;

    const previewZoneInsertButtons = () => {
      if (!SF.logosImporterEnabled) return;
      const zonesRoot = document.getElementById('elementLinksModalZones');
      if (!zonesRoot) return;
      renderLogosSlideInsertButtons(collectElementZonesFromDom(zonesRoot));
    };

    let saveZonesTimer = null;
    const saveZonesLive = async () => {
      if (!SF.logosImporterEnabled) return;
      const zonesRoot = document.getElementById('elementLinksModalZones');
      if (!zonesRoot) return;
      const elementZones = collectElementZonesFromDom(zonesRoot);
      const custom = elementZones.custom || [];
      const payload = {
        elementZones,
        logosNotesOrder: ['normal', 'list_item', ...custom.filter(r => r !== 'normal' && r !== 'list_item')]
      };
      try {
        const res = await api('save_layout_set_settings', payload);
        if (res.elementZones) SF.elementZones = res.elementZones;
        renderElementLinksModalBody();
        const zonesRootUpdated = document.getElementById('elementLinksModalZones');
        if (zonesRootUpdated) {
          bindElementZoneDragDrop(zonesRootUpdated, () => {
            previewZoneInsertButtons();
            if (saveZonesTimer) clearTimeout(saveZonesTimer);
            saveZonesTimer = setTimeout(saveZonesLive, 120);
          });
        }
        refreshStandardElementBadges();
        renderLogosSlideInsertButtons(SF.elementZones || {});
      } catch (e) {
        console.error(e);
      }
    };

    const closeModal = () => {
      modal.classList.remove('open');
      renderLogosSlideInsertButtons(SF.elementZones || {});
    };

    openBtn.addEventListener('click', () => {
      renderElementLinksModalBody();
      const zonesRoot = document.getElementById('elementLinksModalZones');
      if (zonesRoot) {
        bindElementZoneDragDrop(zonesRoot, () => {
          previewZoneInsertButtons();
          if (saveZonesTimer) clearTimeout(saveZonesTimer);
          saveZonesTimer = setTimeout(saveZonesLive, 120);
        });
      }
      modal.classList.add('open');
    });
    closeBtn?.addEventListener('click', closeModal);
    SFModalBackdrop?.bindDismiss(modal, closeModal);

    saveBtn?.addEventListener('click', async () => {
      const elementTextLinks = collectElementLinksFromModal();
      const payload = { elementTextLinks };
      if (SF.logosImporterEnabled) {
        const zonesRoot = document.getElementById('elementLinksModalZones');
        if (zonesRoot) {
          const elementZones = collectElementZonesFromDom(zonesRoot);
          const custom = elementZones.custom || [];
          payload.elementZones = elementZones;
          payload.logosNotesOrder = ['normal', 'list_item', ...custom.filter(r => r !== 'normal' && r !== 'list_item')];
        }
        payload.logosImportSettings = collectLogosImportSettingsFromModal();
      }
      setSaveStatus('Speichere…');
      try {
        const res = await api('save_layout_set_settings', payload);
        SF.elementTextLinks = res.elementTextLinks || elementTextLinks;
        if (res.elementZones) SF.elementZones = res.elementZones;
        if (res.logosImportSettings) SF.logosImportSettings = res.logosImportSettings;
        renderElementLinksModalBody();
        const zonesRootUpdated = document.getElementById('elementLinksModalZones');
        if (zonesRootUpdated) {
          bindElementZoneDragDrop(zonesRootUpdated, previewZoneInsertButtons);
        }
        refreshStandardElementBadges();
        renderLogosSlideInsertButtons(SF.elementZones || {});
        setSaveStatus(I.elementLinksSaved || 'Gespeichert');
        closeModal();
      } catch (e) {
        setSaveStatus('Fehler', true);
        console.error(e);
      }
    });
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
  SF.positionClipboard = null;
  SF.animationClipboard = null;

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
    setRibbonCommandsDisabled('paste', !SF.clipboard || !SF.canEdit);
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
      if (kind === 'image') {
        await insertImageObjectAtCenter(json.url);
        return;
      }
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
      if (objKind === 'image') {
        await insertImageObjectAtCenter(url);
        return;
      }
      const defaultSize = { w: 400, h: 240 };
      const centerX = Math.round(SF.meta.width / 2) - defaultSize.w / 2;
      const centerY = Math.round(SF.meta.height / 2) - defaultSize.h / 2;
      const id = 'o' + Math.random().toString(16).slice(2, 10);
      const obj = { id, type: objKind, x: centerX, y: centerY, w: defaultSize.w, h: defaultSize.h, rotation: 0, opacity: 1, src: url };
      insertNode(createNode(obj));
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
      await insertImageObjectAtCenter(url, {
        sizeOptions: { maxW: 480, maxH: 480, minLongest: 128, fallback: { w: 256, h: 256 } },
      });
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

  function initWebdavPanel() {
    if (!window.SlideForgeWebdav) return;

    const cfg = SF.webdav || { enabled: false, drives: [], i18n: {} };
    if (!cfg.enabled) return;

    async function applyWebdavAsset(mode, url, kind) {
      const mediaKind = kind || 'image';
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

      if (mediaKind === 'image') {
        await insertImageObjectAtCenter(url);
        return;
      }

      const defaultSize = mediaKind === 'audio' ? { w: 280, h: 56 } : { w: 400, h: 240 };
      const centerX = Math.round(SF.meta.width / 2) - defaultSize.w / 2;
      const centerY = Math.round(SF.meta.height / 2) - defaultSize.h / 2;
      const id = 'o' + Math.random().toString(16).slice(2, 10);
      const obj = {
        id, type: mediaKind, x: centerX, y: centerY, w: defaultSize.w, h: defaultSize.h,
        rotation: 0, opacity: 1, src: url,
      };
      insertNode(createNode(obj));
    }

    window.SlideForgeWebdav.init({
      id: SF.id,
      csrfToken: SF.csrfToken,
      webdavConfig: cfg,
      applyWebdav: applyWebdavAsset,
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

    async function insertMediaAsset(url, kind) {
      if (!SF.canEdit || !url) return;
      if (kind === 'image') {
        await insertImageObjectAtCenter(url);
      } else {
        let size;
        if (kind === 'audio') size = { w: 280, h: 56 };
        else size = { w: 400, h: 260 };
        const centerX = Math.round(SF.meta.width / 2) - size.w / 2;
        const centerY = Math.round(SF.meta.height / 2) - size.h / 2;
        const id = 'o' + Math.random().toString(16).slice(2, 10);
        const obj = {
          id,
          type: kind === 'video' ? 'video' : 'audio',
          x: centerX,
          y: centerY,
          w: size.w,
          h: size.h,
          rotation: 0,
          opacity: 1,
          src: url,
        };
        insertNode(createNode(obj));
      }
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
        requestAnimationFrame(updatePropsSidebarOverflow);
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
          if (!file || !(await SFDialog.confirm(cfg.deleteConfirm.replace('{name}', file), { danger: true }))) return;
          try {
            const res = await api('delete_media_asset', { filename: file });
            renderList(res.items || []);
            statusEl.textContent = '';
          } catch (err) {
            statusEl.textContent = err.message || cfg.errorGeneric;
          }
        });
      });
      requestAnimationFrame(updatePropsSidebarOverflow);
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
      if (!(await SFDialog.confirm((cfg.cleanupConfirm || '').replace('{count}', String(unused.length)), { danger: true }))) return;
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

    SF.refreshMediaLibrary = refreshMediaLibrary;
    if (SF.mediaPanelOpen) refreshMediaLibrary();
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

    document.getElementById('addSlideBtn')?.addEventListener('click', async () => {
      await saveCurrentSlide(true);
      const res = await api('add_slide', { after_index: SF.currentIndex });
      SF.slides = res.slides;
      SF.currentIndex = SF.currentIndex + 1;
      loadSlideIntoStage(SF.currentIndex);
      await renderSlideFilmstrip();
      reloadPreviewWindow();
    });

    (function initMetaSettingsAutoSave() {
      const titleEl = document.getElementById('edTitle');
      const widthEl = document.getElementById('edWidth');
      const heightEl = document.getElementById('edHeight');
      const marginEl = document.getElementById('edSafeMargin');
      const durationEl = document.getElementById('edDuration');
      const layoutEl = document.getElementById('edLayoutSet');
      if (!titleEl && !widthEl && !heightEl && !marginEl && !durationEl && !layoutEl) return;

      let timer = null;
      let saving = false;
      let pendingReload = false;

      function collectPayload() {
        const payload = {};
        if (titleEl) {
          const title = (titleEl.value || '').trim();
          if (title !== '') payload.title = title;
        }
        if (widthEl) payload.width = Math.max(100, parseInt(widthEl.value, 10) || SF.meta.width || 1920);
        if (heightEl) payload.height = Math.max(100, parseInt(heightEl.value, 10) || SF.meta.height || 1080);
        if (marginEl) payload.safe_margin = Math.max(0, parseInt(marginEl.value, 10) || 0);
        if (durationEl && !SF.templateMode) {
          payload.presentation_duration = Math.max(1, parseInt(durationEl.value, 10) || 30);
        }
        if (layoutEl && !SF.templateMode) {
          payload.layout_set_id = layoutEl.value || '';
        }
        return payload;
      }

      function needsReload(payload) {
        if (payload.width != null && payload.width !== (SF.meta.width | 0)) return true;
        if (payload.height != null && payload.height !== (SF.meta.height | 0)) return true;
        if (payload.layout_set_id != null && payload.layout_set_id !== (SF.meta.layout_set_id || '')) return true;
        return false;
      }

      async function flush() {
        if (saving || !SF.canEdit) return;
        const payload = collectPayload();
        if (!Object.keys(payload).length) return;
        const reload = needsReload(payload) || pendingReload;
        pendingReload = false;
        saving = true;
        setSaveStatus('Speichere…');
        try {
          const res = await api('save_meta', payload);
          if (res.meta) {
            Object.assign(SF.meta, res.meta);
          } else {
            Object.assign(SF.meta, payload);
          }
          if (titleEl && payload.title) {
            const headerTitle = document.querySelector('.topbar-context-title');
            if (headerTitle) {
              const slideLabel = headerTitle.textContent.includes(' - ')
                ? headerTitle.textContent.split(' - ').slice(1).join(' - ')
                : '';
              headerTitle.textContent = slideLabel ? (payload.title + ' - ' + slideLabel) : payload.title;
            }
          }
          if (payload.safe_margin != null && typeof drawSafeMarginGuide === 'function') {
            drawSafeMarginGuide();
          }
          if (reload) {
            setSaveStatus('Gespeichert');
            window.location.reload();
            return;
          }
          setSaveStatus('Gespeichert');
        } catch (err) {
          setSaveStatus('Fehler beim Speichern', true);
          console.error(err);
        } finally {
          saving = false;
        }
      }

      function scheduleMetaSave(opts) {
        if (opts && opts.reload) pendingReload = true;
        clearTimeout(timer);
        timer = setTimeout(flush, opts && opts.immediate ? 0 : 500);
      }

      titleEl?.addEventListener('change', () => scheduleMetaSave());
      titleEl?.addEventListener('blur', () => scheduleMetaSave({ immediate: true }));
      widthEl?.addEventListener('change', () => scheduleMetaSave({ reload: true }));
      heightEl?.addEventListener('change', () => scheduleMetaSave({ reload: true }));
      marginEl?.addEventListener('change', () => scheduleMetaSave());
      marginEl?.addEventListener('blur', () => scheduleMetaSave({ immediate: true }));
      durationEl?.addEventListener('change', () => scheduleMetaSave());
      durationEl?.addEventListener('blur', () => scheduleMetaSave({ immediate: true }));
      layoutEl?.addEventListener('change', () => scheduleMetaSave({ reload: true, immediate: true }));
    })();

    (function initSpellcheckBeforePresentToggle() {
      const cb = document.getElementById('spellcheckBeforePresentToggle');
      if (!cb || !SF.spellConfig?.enabled) return;
      const syncActive = () => {
        const label = cb.closest('.ribbon-settings-spellcheck');
        if (label) label.classList.toggle('active', !!cb.checked);
      };
      syncActive();
      cb.addEventListener('change', async () => {
        const enabled = cb.checked;
        syncActive();
        try {
          const res = await fetch('user_api.php?action=set_spellcheck_before_present', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: SF.csrfToken, before_present: enabled }),
          });
          const data = await res.json();
          if (!data.ok) {
            cb.checked = !enabled;
            syncActive();
            return;
          }
          SF.spellConfig.beforePresent = !!data.before_present;
        } catch (err) {
          cb.checked = !enabled;
          syncActive();
        }
      });
    })();

    if (SF.presentConfig?.enabled && window.SlideForgePresentConfig) {
      SF.presentConfigApi = window.SlideForgePresentConfig.init({
        id: SF.id,
        csrfToken: SF.csrfToken,
        i18n: SF.presentConfig.i18n,
      });
      document.addEventListener('sf:ribbon-settings-open', (e) => {
        if (e.detail?.panelId === 'present_display') {
          SF.presentConfigApi?.refreshScreens?.();
        }
      });
      SF.presentConfigApi?.refreshScreens?.();
    }

    (function initEditorSettingsPanels() {
      const wrap = document.querySelector('[data-settings-menu]');
      if (!wrap) return;

      function closeSettingsHost() {
        wrap.querySelectorAll('[data-settings-panel]').forEach((p) => { p.hidden = true; });
        if (window.SFRibbon && window.SFRibbon.resetFloatingSettingsPanels) {
          window.SFRibbon.resetFloatingSettingsPanels();
        }
        document.querySelectorAll('#editorRibbon [data-ribbon-settings].active').forEach((b) => {
          b.classList.remove('active');
        });
      }

      wrap.querySelectorAll('[data-menu-close]').forEach((btn) => {
        btn.addEventListener('click', () => closeSettingsHost());
      });
    })();

    function initEditorTopbarMenus() {
      function closeSettingsPanels(wrap) {
        wrap.querySelectorAll('[data-settings-panel]').forEach((p) => { p.hidden = true; });
        const submenu = wrap.querySelector('[data-settings-submenu]');
        if (submenu) submenu.hidden = true;
      }

      function closeWrap(wrap) {
        const btn = wrap.querySelector('[data-menu-btn]');
        if (wrap.hasAttribute('data-settings-menu')) {
          closeSettingsPanels(wrap);
          if (window.SFRibbon && window.SFRibbon.resetFloatingSettingsPanels) {
            window.SFRibbon.resetFloatingSettingsPanels();
          }
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
        document.querySelectorAll('[data-editor-menu]').forEach(closeWrap);
      }

      const wraps = document.querySelectorAll('[data-editor-menu]');
      if (!wraps.length) return;

      function isSettingsOpen(wrap) {
        const submenu = wrap.querySelector('[data-settings-submenu]');
        if (submenu && !submenu.hidden) return true;
        return Array.from(wrap.querySelectorAll('[data-settings-panel]')).some((p) => !p.hidden);
      }

      wraps.forEach((wrap) => {
        if (wrap.dataset.editorMenuWired) return;
        wrap.dataset.editorMenuWired = '1';
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
              if (window.SFRibbon && window.SFRibbon.resetFloatingSettingsPanels) {
                window.SFRibbon.resetFloatingSettingsPanels();
              }
              submenu.hidden = false;
              btn.setAttribute('aria-expanded', 'true');
              btn.classList.add('open');
            }
          });

          wrap.querySelectorAll('[data-settings-open]').forEach((item) => {
            item.addEventListener('click', (e) => {
              e.stopPropagation();
              const id = item.getAttribute('data-settings-open');
              if (window.SFRibbon && window.SFRibbon.resetFloatingSettingsPanels) {
                window.SFRibbon.resetFloatingSettingsPanels();
              }
              if (submenu) submenu.hidden = true;
              panels.forEach((p) => { p.hidden = p.getAttribute('data-settings-panel') !== id; });
            });
          });

          wrap.querySelectorAll('[data-settings-back]').forEach((backBtn) => {
            backBtn.addEventListener('click', () => {
              closeSettingsPanels(wrap);
              if (window.SFRibbon && window.SFRibbon.resetFloatingSettingsPanels) {
                window.SFRibbon.resetFloatingSettingsPanels();
              }
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

      if (!initEditorTopbarMenus.globalWired) {
        initEditorTopbarMenus.globalWired = true;
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape') closeAll();
        });
        document.addEventListener('click', (e) => {
          if (e.target.closest('[data-editor-menu]')) return;
          closeAll();
        });
      }
    }
    initEditorTopbarMenus.globalWired = false;
    initEditorTopbarMenus();
    window.SFEditorMenus = { init: initEditorTopbarMenus };

    document.getElementById('previewWindowBtn')?.addEventListener('click', () => {
      openPreviewWindow();
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

    /* bg-type-btn + apply_transition_all: Delegation in initRibbonCommandDelegation */

    document.getElementById('bgColorInput').addEventListener('input', (e) => {
      SF.currentBackground = { type: 'color', value: e.target.value };
      applyBackgroundVisual(SF.currentBackground);
      populateBackgroundControls(SF.currentBackground);
      updateCurrentTabSwatch();
      scheduleSave();
    });

    renderBrandPalette();
    initSlideBgMediaModal();
    initSlideBgGradientModal();

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

    document.querySelectorAll('.tool-btn-block, .ribbon-tool-btn').forEach((btn) => {
      if (btn.closest('#editorRibbon')) return;
      if (btn.id === 'pixabayOpenBtn' || btn.id === 'iconifyOpenBtn' || btn.id === 'openclipartOpenBtn') return;
      if (btn.closest('#logosSlideInsertButtons')) return;
      if (btn.dataset.toolWiredOutsideRibbon === '1') return;
      btn.dataset.toolWiredOutsideRibbon = '1';
      btn.addEventListener('click', () => {
        if (btn.dataset.setRole) addLogosPlaceholder(btn.dataset.setRole);
        else if (btn.dataset.tool) addShape(btn.dataset.tool);
        else if (btn.dataset.preset) addTextPreset(btn.dataset.preset);
      });
    });

    document.getElementById('slideGridSelectAllBtn')?.addEventListener('click', () => {
      gridSelectedIndices.clear();
      SF.slides.forEach((_, i) => gridSelectedIndices.add(i));
      gridLastClickedIndex = SF.currentIndex;
      syncRibbonTransitionFieldsFromSlide(SF.currentIndex);
      renderSlideGrid();
    });
    document.getElementById('slideGridSelectNoneBtn')?.addEventListener('click', () => {
      gridSelectedIndices.clear();
      gridLastClickedIndex = null;
      renderSlideGrid();
    });
    // Akkordeon-Umschaltung, gescoped je Container (data-accordion-name) - funktioniert
    // gleichermassen für das Eigenschaften-Panel (Text) und den Folien-Eigenschaften-Dialog,
    // ohne dass sich mehrere Akkordeons auf der Seite gegenseitig zuklappen.
    document.addEventListener('click', (e) => {
      const header = e.target.closest('.props-accordion-header, .props-panel-accordion-header');
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
    document.getElementById('autoAdvanceInput').addEventListener('input', () => {
      if (SF.editorViewMode === 'grid') return;
      scheduleSave();
    });
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
      if (e.key === 'Escape') {
        if (SF.editorViewMode === 'grid') {
          e.preventDefault();
          closeSlideGridView();
          return;
        }
      }
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
    const slide = SF.slides[SF.currentIndex] || {};
    return {
      background,
      objects,
      transition,
      autoAdvance,
      notes,
      layoutKey: slide.layoutKey ?? null,
      layoutSetSlideId: slide.layoutSetSlideId ?? null,
      label: slide.label ?? null,
      hasLayoutKey: Object.prototype.hasOwnProperty.call(slide, 'layoutKey'),
      hasLayoutSetSlideId: Object.prototype.hasOwnProperty.call(slide, 'layoutSetSlideId'),
      hasLabel: Object.prototype.hasOwnProperty.call(slide, 'label'),
    };
  }

  function updateUndoRedoButtons() {
    setRibbonCommandsDisabled('undo', SF.historyIndex <= 0);
    setRibbonCommandsDisabled('redo', SF.historyIndex >= SF.history.length - 1);
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
    const slide = SF.slides[SF.currentIndex];
    slide.background = snap.background;
    slide.objects = JSON.parse(JSON.stringify(snap.objects));
    slide.transition = snap.transition;
    slide.autoAdvance = snap.autoAdvance;
    slide.notes = snap.notes;
    if (snap.hasLayoutKey) {
      if (snap.layoutKey) slide.layoutKey = snap.layoutKey;
      else delete slide.layoutKey;
    }
    if (snap.hasLayoutSetSlideId) {
      if (snap.layoutSetSlideId) slide.layoutSetSlideId = snap.layoutSetSlideId;
      else delete slide.layoutSetSlideId;
    }
    if (snap.hasLabel) {
      if (snap.label) slide.label = snap.label;
      else delete slide.label;
    }
    loadSlideIntoStage(SF.currentIndex, true);
    SF.restoringHistory = false;
    updateUndoRedoButtons();
    if (snap.hasLayoutKey || snap.hasLayoutSetSlideId || snap.hasLabel) renderSlideFilmstrip();
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
    const slideMeta = SF.slides[SF.currentIndex] || {};
    /* Raster: Ribbon-Werte sind Entwurf für Mehrfach-Anwenden — nicht still auf die aktuelle Folie speichern. */
    const transition = SF.editorViewMode === 'grid'
      ? (slideMeta.transition || 'slide')
      : (document.getElementById('transitionSelect') ? getTransitionValue() : 'slide');
    const autoAdvance = SF.editorViewMode === 'grid'
      ? (Math.max(0, parseInt(slideMeta.autoAdvance, 10) || 0))
      : ribbonAutoAdvanceValue();
    const notesEl = document.getElementById('slideNotesInput');
    const notes = notesEl ? notesEl.value : '';
    const layoutKey = SF.layoutSetMode || Object.prototype.hasOwnProperty.call(slideMeta, 'layoutKey')
      ? (slideMeta.layoutKey || '')
      : null;
    const label = Object.prototype.hasOwnProperty.call(slideMeta, 'label')
      ? (slideMeta.label || '')
      : null;
    const layoutSetSlideId = Object.prototype.hasOwnProperty.call(slideMeta, 'layoutSetSlideId')
      ? (slideMeta.layoutSetSlideId || '')
      : null;

    setSaveStatus('Speichere…');
    try {
      const payload = { index: SF.currentIndex, background, objects, transition, autoAdvance, notes };
      if (layoutKey !== null) payload.layoutKey = layoutKey;
      if (label !== null) payload.label = label;
      if (layoutSetSlideId !== null) payload.layoutSetSlideId = layoutSetSlideId;
      await api('save_slide', payload);
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

  document.addEventListener('sf:ribbon-rendered', () => {
    if (!SF.canEdit) return;
    initRibbonEditorHooks();
    initRibbonTemplateGalleryObserver();
    requestAnimationFrame(() => renderRibbonTemplateGallery(true));
    updateGridSelectionUi();
    syncApplyTransitionSelectedVisibility();
    syncMasterSlidePresentCommands();
  });

  if (SF.canEdit) {
    initRibbonCommandDelegation();
    initShareModal();
    initExportModal();
  }

  document.addEventListener('DOMContentLoaded', init);
})();
