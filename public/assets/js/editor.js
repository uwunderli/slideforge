(function () {
  'use strict';

  const boot = window.SF_BOOTSTRAP;
  const SF = {
    keyArea: 'stage', // zuletzt angeklickter Bereich für Pfeiltasten (3.12.3): stage | film | grid | other
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
    deckBusy: false,
    saving: false,
    currentBackground: { type: 'color', value: '#111111' },
    manualZoom: null,
    currentZoom: 1,
    transformSnapStart: null,
    activePropsTab: 'format',
    activeFormatGroup: null,
    notesHeight: Math.max(60, Math.min(900, parseInt((boot.editorUi && boot.editorUi.notesHeight), 10) || 140)),
    /* Trennerbreiten (HubSplit), Schlüssel ohne «split:», serverseitig in editor_ui.splits */
    splits: Object.assign({}, (boot.editorUi && boot.editorUi.splits && typeof boot.editorUi.splits === 'object' && !Array.isArray(boot.editorUi.splits)) ? boot.editorUi.splits : {}),
    /* Eingeklappte Seitenleisten (HubSplit-Griff, 3.9.4), serverseitig in editor_ui.collapsed */
    collapsed: Object.assign({}, (boot.editorUi && boot.editorUi.collapsed && typeof boot.editorUi.collapsed === 'object' && !Array.isArray(boot.editorUi.collapsed)) ? boot.editorUi.collapsed : {}),
    /* Mitlaufen: Geräte spielen beim Folienwechsel die Effekte ab (3.11.0), serverseitig in editor_ui.mitAnim */
    mitAnim: !!(boot.editorUi && boot.editorUi.mitAnim),
    /* Abspielen (3.11.0): Effekt-Schritt für den Live-Kanal «editor» (reveal-Fragment, -1 = Schritt 0); null = nicht am Abspielen */
    playFrag: null,
    activeSideTab: (boot.editorUi && boot.editorUi.sideTab) || 'templates',
    activePosSubtab: (boot.editorUi && boot.editorUi.posSubtab) || 'layout',
    activeFormatSubtab: (boot.editorUi && boot.editorUi.formatSubtab) || 'text',
    /* Effekte-Untertab bei gewähltem Objekt (3.12.5): in | out | order, serverseitig in editor_ui.fxSubtab */
    activeFxSubtab: ['in', 'out', 'order'].indexOf(boot.editorUi && boot.editorUi.fxSubtab) !== -1 ? boot.editorUi.fxSubtab : 'in',
    layersPanelOpen: true,
    objectPanelOpen: boot.editorUi && typeof boot.editorUi.objectOpen === 'boolean'
      ? !!boot.editorUi.objectOpen
      : true,
    templatesPanelOpen: true,
    mediaPanelOpen: true,
    selectionPanelOpen: true,
    editorViewMode: 'slide',
    gridThumbMin: boot.editorGridThumbMin || 168,
    // Markenfarben des Hubs (Palette, 3.9.2); ohne Antwort des Hubs die Liste des Bausteins HubColorPicker
    brandColors: (boot.brandColors && boot.brandColors.length) ? boot.brandColors
      : (window.HubColorPicker ? HubColorPicker.brandColors().filter((c) => c && !c.break) : []),
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
    i18n: boot.i18n || {},
  };

  const FONT_OPTIONS = Array.isArray(boot.fontFamilies) && boot.fontFamilies.length
    ? boot.fontFamilies
    : ['Open Sans', 'PT Sans', 'Georgia', 'Arial', 'Times New Roman', 'Courier New', 'Verdana'];

  const I = SF.i18n;
  let editorUiSaveTimer = null;

  function persistEditorUi(partial) {
    if (!partial || typeof partial !== 'object') return;
    SF.editorUi = Object.assign({}, SF.editorUi || boot.editorUi || {}, partial);
    clearTimeout(editorUiSaveTimer);
    editorUiSaveTimer = setTimeout(() => {
      fetch('user_api.php?action=set_editor_ui', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ui: {
            sideTab: SF.activeSideTab,
            posSubtab: SF.activePosSubtab,
            formatSubtab: SF.activeFormatSubtab,
            fxSubtab: SF.activeFxSubtab,
            objectOpen: SF.objectPanelOpen !== false,
            notesHeight: SF.notesHeight,
            splits: SF.splits,
            collapsed: SF.collapsed,
            mitAnim: !!SF.mitAnim,
          },
          csrf_token: SF.csrfToken,
        }),
      }).catch(() => {});
    }, 280);
  }

  /* 3.12.5: Die Klapp-Abschnitte des Effekte-Reiters (3.10.2) sind weg; ihre gemerkten Schlüssel fx_in_/fx_out_ auch. */
  (function altEffektKlappenWeg() {
    const c = SF.collapsed || {};
    const alt = Object.keys(c).filter((k) => /^fx_(in|out)_(auf|zu)$/.test(k));
    if (!alt.length) return;
    alt.forEach((k) => delete c[k]);
    persistEditorUi({});
  })();

  function migrateEditorUiFromLocalStorage() {
    let changed = false;
    try {
      const side = localStorage.getItem('sf_side_tab');
      const pos = localStorage.getItem('sf_pos_subtab');
      const format = localStorage.getItem('sf_format_subtab');
      const objectOpen = localStorage.getItem('sf_object_open');
      if (side) {
        SF.activeSideTab = side;
        changed = true;
      }
      if (pos) {
        SF.activePosSubtab = pos === 'layers' ? 'layers' : 'layout';
        changed = true;
      }
      if (format) {
        SF.activeFormatSubtab = format;
        changed = true;
      }
      if (objectOpen !== null) {
        SF.objectPanelOpen = objectOpen === '1';
        changed = true;
      }
      if (changed) {
        persistEditorUi({});
        localStorage.removeItem('sf_side_tab');
        localStorage.removeItem('sf_pos_subtab');
        localStorage.removeItem('sf_format_subtab');
        localStorage.removeItem('sf_object_open');
      }
    } catch (e) { /* ignore */ }
  }
  migrateEditorUiFromLocalStorage();

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

  /* Ausblende-Effekte (animExit.type, 3.10.0): nur echte Ausgänge. */
  const EXIT_ANIMATIONS = [
    { value: 'none', label: I.optExit?.none || 'Keiner', icon: ANIM_ICONS.none },
    { value: 'fade', label: I.optExit?.fade || 'Ausblenden', icon: ANIM_ICONS['fade-out'] },
    { value: 'up', label: I.optExit?.up || 'Nach oben ausblenden', icon: ANIM_ICONS['fade-up'] },
    { value: 'down', label: I.optExit?.down || 'Nach unten ausblenden', icon: ANIM_ICONS['fade-down'] },
    { value: 'left', label: I.optExit?.left || 'Nach links ausblenden', icon: ANIM_ICONS['fade-left'] },
    { value: 'right', label: I.optExit?.right || 'Nach rechts ausblenden', icon: ANIM_ICONS['fade-right'] },
    { value: 'shrink', label: I.optExit?.shrink || 'Schrumpfend ausblenden', icon: ANIM_ICONS.shrink },
    { value: 'grow', label: I.optExit?.grow || 'Wachsend ausblenden', icon: ANIM_ICONS.grow },
  ];
  const EXIT_TYPES = EXIT_ANIMATIONS.map((a) => a.value).filter((v) => v !== 'none');

  /** Ausblende-Effekt bereinigt; null = keiner. */
  function normExit(e) {
    if (!e || typeof e !== 'object' || EXIT_TYPES.indexOf(e.type) === -1) return null;
    return {
      type: e.type,
      order: Math.max(1, parseInt(e.order, 10) || 1),
      duration: Math.max(0, parseInt(e.duration, 10) || 0),
      auto: Math.max(0, parseInt(e.auto, 10) || 0),
    };
  }
  function nodeExit(node) {
    return node ? normExit(node.getAttr('animExit')) : null;
  }
  function hatEinblenden(node) {
    return (node.getAttr('animType') || 'none') !== 'none';
  }

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

  /* Spitze und Schaft einstellbar (3.12.10, Urs 27.11.: «dass man die Grösse der Spitze verändern kann»):
     head = Länge der Spitze in % der Pfeillänge (Vorgabe 40, beim Doppelpfeil je Spitze die Hälfte),
     shaft = Dicke des Schafts in % der Breite (Vorgabe 30). Vorgaben ergeben genau die bisherige Form.
     Gleiche Rechnung in src/SlideRenderer.php::arrowPoints. */
  function arrowPoints(w, h, style, head, shaft) {
    const L = Math.max(5, Math.min(95, Number(head) || 40)) / 100;
    const d = L / 2;
    const sh1 = Math.min(0.8, Math.max(0.05, (Number(shaft) || 30) / 100)) / 2;
    const sh2 = Math.min(0.5, Math.max(0.05, (Number(shaft) || 30) / 100)) / 2;
    const a = 1 - L;
    const base = [[0, 0.5 - sh1], [a, 0.5 - sh1], [a, 0.1], [1, 0.5], [a, 0.9], [a, 0.5 + sh1], [0, 0.5 + sh1]];
    const lo = 0.5 - sh2, hi = 0.5 + sh2;
    const doubleH = [[0, 0.5], [d, 0.25], [d, lo], [1 - d, lo], [1 - d, 0.25], [1, 0.5], [1 - d, 0.75], [1 - d, hi], [d, hi], [d, 0.75]];
    const doubleV = [[0.5, 0], [0.25, d], [lo, d], [lo, 1 - d], [0.25, 1 - d], [0.5, 1], [0.75, 1 - d], [hi, 1 - d], [hi, d], [0.75, d]];
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
    if (shapeType === 'arrow') return arrowPoints(w, h, obj.arrowStyle || 'right', obj.arrowHead, obj.arrowShaft);
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
      arrowHead: node.getAttr('arrowHead'),
      arrowShaft: node.getAttr('arrowShaft'),
      bracketStyle: node.getAttr('bracketStyle'),
      bubbleStyle: node.getAttr('bubbleStyle'),
    };
  }

  // ---------- API ----------
  /* Strukturändernde Aktionen: vorher den anstehenden Automatikspeicher
     abschliessen. saveCurrentSlide adressiert die Folie über ihre POSITION
     (index). Feuert der 700-ms-Timer erst, nachdem Duplizieren/Löschen/
     Verschieben die Reihenfolge geändert hat, landet der Inhalt auf der
     falschen Folie. Zusätzlich eine Sperre, damit zwei Struktureingriffe
     nicht ineinanderlaufen und beide auf einem veralteten SF.slides rechnen. */
  const DECK_ACTIONS = [
    'add_slide', 'duplicate_slide', 'delete_slide', 'reorder_slides',
    'apply_slide_template', 'apply_layout_from_set', 'import_slide_to_layout_set',
    'clip_paste_slides', 'find_replace', 'notes_outline_style', 'insert_presentation_file',
    'restore_slides',
  ];
  /* Verlauf der Folien-Aktionen (siehe deckBegin/deckStep). */
  SF.deckUndo = [];
  SF.deckRedo = [];

  /* Warteschlange der Folien-Schritte (3.9.5). Früher warf ein zweiter Schritt während
     eines laufenden «Bitte warten – vorheriger Schritt läuft noch.» (Urs 27.11. bei «Neue Folie»).
     Jetzt läuft ein Schritt nach dem anderen: gleiche Aktion während sie läuft oder wartet
     (Doppelklick) wird still verworfen, andere Folien-Aktionen warten und laufen danach.
     SF.deckBusy = true, solange ein Schritt läuft; api() ruft Folien-Aktionen darin direkt auf. */
  SF.deckChain = Promise.resolve();
  SF.deckPending = new Set();

  /** fn als Folien-Schritt einreihen. key = gleiche Aktion verwerfen, solange sie läuft/wartet; null = immer einreihen. */
  function deckRun(key, fn) {
    if (key && SF.deckPending.has(key)) return Promise.resolve();
    if (key) SF.deckPending.add(key);
    const step = SF.deckChain.then(async () => {
      SF.deckBusy = true;
      try {
        return await fn();
      } finally {
        SF.deckBusy = false;
        if (key) SF.deckPending.delete(key);
      }
    });
    SF.deckChain = step.catch(() => {});
    return step;
  }

  /** Wie deckRun, für Bedien-Wege: Fehler sachlich anzeigen statt still abbrechen. */
  function deckUi(key, fn) {
    return deckRun(key, fn).catch((e) => {
      console.error(e);
      setSaveStatus('Fehler', true);
      if (window.CfStatus) CfStatus.show('error', (e && e.message) || 'Der Folien-Schritt ist fehlgeschlagen.');
    });
  }

  /** Folien-Nummern beim Auslösen in IDs umrechnen; wartet der Schritt, haben sich die Nummern evtl. verschoben. */
  function slideIdsAt(indices) {
    return indices.map((i) => (SF.slides[i] ? SF.slides[i].id : null)).filter(Boolean);
  }

  function slideIndicesOf(ids) {
    return ids.map((id) => SF.slides.findIndex((s) => s.id === id)).filter((i) => i >= 0);
  }

  async function api(action, data) {
    if (DECK_ACTIONS.indexOf(action) !== -1) {
      /* Ausserhalb eines Schritts (z. B. neuer Aufrufweg): selbst einreihen statt dazwischenfunken. */
      if (!SF.deckBusy) return deckRun(null, () => apiDeckSend(action, data));
      return apiDeckSend(action, data);
    }
    return apiSend(action, data);
  }

  async function apiDeckSend(action, data) {
    if (SF.saveTimer) {
      clearTimeout(SF.saveTimer);
      SF.saveTimer = null;
      await saveCurrentSlide(true);
    }
    return apiSend(action, data);
  }

  async function apiSend(action, data) {
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
    find_replace: 'findReplaceBtn',
    notes_to_path: 'notesToPathBtn',
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
        if (action === 'image' || action === 'audio' || action === 'video') pickObjectMedia(action);
        else if (action === 'pixabay') document.getElementById('pixabayOpenBtn')?.click();
        else if (action === 'iconify') document.getElementById('iconifyOpenBtn')?.click();
        else if (action === 'openclipart') document.getElementById('openclipartOpenBtn')?.click();
        else if (action === 'fromurl') openInsertFromUrl();
        else if (action === 'insert_presentation') insertPresentationFile();
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
        findReplaceBtn: 'find_replace',
        notesToPathBtn: 'notes_to_path',
      }[cmdEl.id] || '');

      switch (cmdKey) {
        case 'undo':
          undo();
          break;
        case 'delete_slides':
          deleteSlides(shortcutSlideIndices());
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
        /* add_slide: kein Fall hier. Der Menüband-Knopf trägt data-ribbon-trigger="addSlideBtn"
           (config/ribbon_commands.php), ribbon.js klickt #addSlideBtn schon; ein zweiter Klick hier
           legte die Folie zweimal an bzw. löste «Bitte warten» aus (3.9.5). */
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
        case 'play_slide':
          openPlayOverlay();
          break;
        case 'export':
          e.preventDefault();
          openExportModal();
          break;
        case 'spellcheck':
          window.SlideForgeSpellcheck?.openPanel?.();
          break;
        case 'find_replace':
          openFindReplace();
          break;
        case 'notes_to_path':
          openNotesOutline();
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

  // Export-Dialog (3.2.6 wiederhergestellt: 3.2.0 hatte ihn mit den Freigabe-Funktionen gelöscht, der Aufruf in der
  // Initialisierung blieb → ReferenceError, Editor ohne Folien)
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
        if (window.CfStatus) CfStatus.show('info', I.exportPreparing || 'Export wird vorbereitet…');
        const busyTitle = I.exportPreparing || 'Export wird vorbereitet…';
        if (window.SFBusy) SFBusy.show(busyTitle);
        else if (window.HubPageBusy) HubPageBusy.show({ title: busyTitle, soft: true });
        try {
          const startRes = await fetch(url + (url.includes('?') ? '&' : '?') + 'async=1', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
          });
          const startJson = await startRes.json();
          if (!startJson.ok || !startJson.job_id) {
            throw new Error(startJson.error || I.exportFailed || 'Export fehlgeschlagen');
          }
          const jobId = startJson.job_id;
          for (;;) {
            const stRes = await fetch('export.php?action=status&job=' + encodeURIComponent(jobId), {
              credentials: 'same-origin',
            });
            const stJson = await stRes.json();
            if (!stJson.ok) throw new Error(stJson.error || I.exportFailed || 'Export fehlgeschlagen');
            const status = stJson.job && stJson.job.status;
            if (status === 'done') {
              if (window.SFBusy) SFBusy.hide();
              else if (window.HubPageBusy) HubPageBusy.hide();
              window.location.href = 'export.php?action=download&job=' + encodeURIComponent(jobId);
              setSaveStatus(I.exportStarted || 'Download gestartet');
              if (window.CfStatus) CfStatus.show('ok', I.exportStarted || 'Download gestartet');
              return;
            }
            if (status === 'failed') {
              throw new Error((stJson.job && stJson.job.error) || I.exportFailed || 'Export fehlgeschlagen');
            }
            await new Promise((r) => setTimeout(r, 700));
          }
        } catch (err) {
          if (window.SFBusy) SFBusy.hide();
          else if (window.HubPageBusy) HubPageBusy.hide();
          const msg = err?.message || I.exportFailed || 'Export fehlgeschlagen';
          setSaveStatus(msg);
          if (window.CfStatus) CfStatus.show('error', msg);
        }
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

    const commandIds = ['present_mode', 'export', 'preview_tab', 'preview_device', 'play_slide'];
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
      if (res.missing) {
        listEl.innerHTML = '<p class="props-video-note">' + escapeHtml(SF.i18n.layoutSetMissing || SF.i18n.empty) + '</p>';
        return;
      }
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
        '<button type="button" class="button button-sm hub-btn hub-btn--primary hub-btn--sm" data-apply-layout="' + escapeHtml(l.layoutKey) + '" data-layout-slide-id="' + escapeHtml(l.slideId || '') + '">' + SF.i18n.apply + '</button>' +
      '</div>'
    ).join('');
    listEl.querySelectorAll('[data-apply-layout]').forEach(btn => {
      btn.addEventListener('click', () => {
        const slideId = btn.getAttribute('data-layout-slide-id') || '';
        applyLayoutFromSet(setId, btn.dataset.applyLayout, slideId);
      });
    });
  }

  function applyLayoutFromSet(setId, layoutKey, layoutSlideId) {
    return deckUi('apply_layout_from_set', () => applyLayoutFromSetNow(setId, layoutKey, layoutSlideId));
  }

  async function applyLayoutFromSetNow(setId, layoutKey, layoutSlideId) {
    setSaveStatus('Wende Layout an…');
    try {
      pushHistory();
      const payload = { index: SF.currentIndex, layout_set_id: setId, layout_key: layoutKey };
      /* In der Rasteransicht gilt die Mehrfachauswahl: Die Vorlage wird allen
         gewählten Folien zugewiesen, nicht nur der aktuellen. */
      if (SF.editorViewMode === 'grid' && gridSelectedIndices.size > 0) {
        payload.indices = [...gridSelectedIndices];
      }
      if (layoutSlideId) payload.layout_slide_id = layoutSlideId;
      await api('apply_layout_from_set', payload);
      const res = await apiGet('get_slides');
      SF.slides = res.slides;
      loadSlideIntoStage(SF.currentIndex, true);
      pushHistory();
      await renderSlideFilmstrip();
      if (SF.editorViewMode === 'grid') renderSlideGrid();
      closeEditorSettingsMenu();
      if (SF.templatesPanelOpen) renderTemplatesPanel();
      setSaveStatus('Gespeichert');
      mitlaufMelden(true);
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
            '<button type="button" class="button button-sm hub-btn hub-btn--primary hub-btn--sm" data-apply-template="' + t.id + '">' + SF.i18n.apply + '</button>' +
          '</div>'
        ).join('');
    };
    listEl.innerHTML = renderGroup(SF.i18n.own, mine) + renderGroup(SF.i18n.shared, shared);
    listEl.querySelectorAll('[data-apply-template]').forEach(btn => {
      btn.addEventListener('click', () => applySlideTemplate(btn.dataset.applyTemplate));
    });
  }

  function applySlideTemplate(templateId) {
    return deckUi('apply_slide_template', () => applySlideTemplateNow(templateId));
  }

  async function applySlideTemplateNow(templateId) {
    setSaveStatus('Wende Vorlage an…');
    try {
      pushHistory();
      const payload = { index: SF.currentIndex, template_id: templateId };
      /* In der Rasteransicht gilt die Mehrfachauswahl, wie bei applyLayoutFromSet. */
      if (SF.editorViewMode === 'grid' && gridSelectedIndices.size > 0) {
        payload.indices = [...gridSelectedIndices];
      }
      await api('apply_slide_template', payload);
      const res = await apiGet('get_slides');
      SF.slides = res.slides;
      loadSlideIntoStage(SF.currentIndex, true);
      pushHistory();
      await renderSlideFilmstrip();
      if (SF.editorViewMode === 'grid') renderSlideGrid();
      closeEditorSettingsMenu();
      if (SF.templatesPanelOpen) renderTemplatesPanel();
      setSaveStatus('Gespeichert');
      mitlaufMelden(true);
    } catch (e) {
      setSaveStatus('Fehler beim Anwenden', true);
      console.error(e);
    }
  }

  // ---------- Bootstrapping ----------
  // Nur die Schriften laden, die die Folien brauchen (3.9.10): mit ~1000 Hub-Schriften wäre «alle laden» tausende Downloads.
  function usedFontFamilies(slides) {
    const set = new Set(['Open Sans']);
    const walk = (v, depth) => {
      if (!v || typeof v !== 'object' || depth > 12) return;
      if (Array.isArray(v)) { v.forEach((x) => walk(x, depth + 1)); return; }
      Object.keys(v).forEach((k) => {
        if (k === 'fontFamily' && typeof v[k] === 'string' && v[k].trim()) set.add(v[k].trim());
        else walk(v[k], depth + 1);
      });
    };
    walk(slides, 0);
    return Array.from(set).slice(0, 60);
  }

  function loadFontFamily(f, weights) {
    if (!document.fonts || !document.fonts.load || !f) return Promise.resolve();
    const fam = String(f).replace(/["\\]/g, '');
    return Promise.all((weights || ['400', '700']).map((w) => document.fonts.load(w + ' 32px "' + fam + '"').catch(() => {})))
      .then(() => { sfFontEpoch++; });   // SfRichText misst danach neu
  }

  /* ---------- SfRichText: Textobjekt mit sichtbarer Formatierung (Texteditor T2, 3.12.33) ----------
     Unterklasse von Konva.Text: alle Getter/Setter (text(), fontSize(), fill(), fontStyle(),
     textDecoration(), align(), lineHeight(), letterSpacing(), fontFamily(), fontVariant(), width())
     bleiben dieselben, und text() liefert weiter die Auszeichnung. Nur das Zeichnen und die Höhe
     kommen von hier: SfMarkup.parse() (gleiche Regeln wie Markdown.php) → Läufe → Zeilen nach
     Breite. Nachbau der CSS-Darstellung im SlideRenderer (white-space:pre-wrap, line-height ohne
     Einheit, Überschriften 1.6em…0.9em fett, Listen mit 1.1em Einzug und immer linksbündig,
     Leerzeilen ohne eigene Höhe wie in Markdown::render). Trefferfläche = Rechteck des Objekts
     (Konva.Text._hitFunc mit width() × height()). Ohne SfMarkup fällt alles auf Konva.Text zurück. */
  let sfFontEpoch = 0;
  let sfMeasureCtx = null;
  if (document.fonts && document.fonts.addEventListener) {
    document.fonts.addEventListener('loadingdone', () => { sfFontEpoch++; if (SF.layer) SF.layer.batchDraw(); });
  }
  function sfRichMeasureCtx() {
    if (!sfMeasureCtx) sfMeasureCtx = document.createElement('canvas').getContext('2d');
    return sfMeasureCtx;
  }
  function sfRichFamily(f) {
    return '"' + String(f || 'Open Sans').replace(/["\\]/g, '') + '", sans-serif';
  }
  function sfRichFont(st, px, familyCss) {
    return (st.italic ? 'italic ' : '') + (st.sc ? 'small-caps ' : '') + (st.bold ? 'bold ' : '') + px + 'px ' + familyCss;
  }

  function sfRichLayout(node) {
    const ctx = sfRichMeasureCtx();
    const hasLs = 'letterSpacing' in ctx;
    const F = node.fontSize() || 32;
    const LH = node.lineHeight() || 1.2;
    const W = Math.max(1, node.width() || 1);
    const ls = node.letterSpacing() || 0;
    const baseStyle = String(node.fontStyle() || 'normal');
    const baseBold = /bold|[6-9]00/.test(baseStyle);
    const baseItalic = /italic/.test(baseStyle);
    const baseSc = node.fontVariant() === 'small-caps' || !!node.getAttr('smallCaps');
    const baseUpper = !!node.getAttr('uppercase');
    const family = sfRichFamily(node.fontFamily());
    const align = node.align() || 'left';
    const mCache = {};
    function metrics(font, px) {
      if (mCache[font]) return mCache[font];
      ctx.font = font;
      const m = ctx.measureText('Hg');
      const asc = (typeof m.fontBoundingBoxAscent === 'number') ? m.fontBoundingBoxAscent : px * 0.9;
      const desc = (typeof m.fontBoundingBoxDescent === 'number') ? m.fontBoundingBoxDescent : px * 0.25;
      return (mCache[font] = { asc: asc, desc: desc });
    }
    function measure(font, txt) {
      ctx.font = font;
      if (hasLs) ctx.letterSpacing = '0px';
      return ctx.measureText(txt).width + ls * Array.from(txt).length;
    }

    const blocks = window.SfMarkup.parse(node.text() || '').filter((b) => b.type !== 'blank');
    const lines = [];
    let y = 0;
    blocks.forEach((b) => {
      const isH = b.type === 'h';
      const isLi = b.type === 'li';
      const blockPx = F * (isH ? b.scale : 1);
      const indent = isLi ? 1.1 * F : 0;
      const avail = Math.max(1, W - indent);
      const lineAlign = isLi ? 'left' : align;
      const strutFont = sfRichFont({ bold: baseBold || isH, italic: baseItalic, sc: baseSc }, blockPx, family);
      const strutM = metrics(strutFont, blockPx);

      const pieces = [];
      b.runs.forEach((r) => {
        const st = { bold: baseBold || isH || r.b, italic: baseItalic || r.i, sc: baseSc || r.sc };
        const px = r.size || blockPx;
        const font = sfRichFont(st, px, r.code ? 'monospace' : family);
        const m = metrics(font, px);
        const txt = (baseUpper || r.upper) ? r.text.toUpperCase() : r.text;
        const pad = r.code ? 5 : (r.mark ? 2 : 0);
        const parts = txt.split(/(\s+)/).filter((s) => s !== '');
        parts.forEach((t, idx) => {
          const padL = idx === 0 ? pad : 0;
          const padR = idx === parts.length - 1 ? pad : 0;
          pieces.push({ text: t, space: /^\s+$/.test(t), font: font, px: px, run: r, m: m,
            padL: padL, w: measure(font, t) + padL + padR });
        });
      });

      // Umbruch nur an Leerraum (wie pre-wrap); Leerraum vor dem Umbruch hängt an der alten Zeile.
      const bl = [{ items: [], w: 0 }];
      let i = 0;
      while (i < pieces.length) {
        let ln = bl[bl.length - 1];
        if (pieces[i].space) { ln.items.push(pieces[i]); ln.w += pieces[i].w; i++; continue; }
        let j = i, ww = 0;
        while (j < pieces.length && !pieces[j].space) { ww += pieces[j].w; j++; }
        if (ln.items.some((p) => !p.space) && ln.w + ww > avail + 0.01) {
          ln = { items: [], w: 0 };
          bl.push(ln);
        }
        for (let k = i; k < j; k++) { ln.items.push(pieces[k]); ln.w += pieces[k].w; }
        i = j;
      }

      bl.forEach((ln, idx) => {
        let above = 0, below = 0;
        const consider = (m, px) => {
          const hl = (LH * px - (m.asc + m.desc)) / 2;
          above = Math.max(above, m.asc + hl);
          below = Math.max(below, m.desc + hl);
        };
        consider(strutM, blockPx);
        ln.items.forEach((p) => consider(p.m, p.px));
        let last = ln.items.length - 1;
        let tw = ln.w;
        while (last >= 0 && ln.items[last].space) { tw -= ln.items[last].w; last--; }
        const x0 = indent + (lineAlign === 'center' ? (avail - tw) / 2 : (lineAlign === 'right' ? (avail - tw) : 0));
        lines.push({
          y: y, baseline: y + above, x0: x0, items: ln.items, lastContent: last,
          color: b.color || null,
          bullet: (isLi && idx === 0) ? { font: strutFont, x: indent - measure(strutFont, '•') - 0.35 * F } : null,
        });
        y += above + below;
      });
    });
    return { lines: lines, height: lines.length ? y : F * LH };
  }

  function sfRichDraw(node, context) {
    const L = node._sfGetLayout();
    const ctx = context._context || context;
    const fill = node.fill() || '#000000';
    const deco = String(node.textDecoration() || '');
    const baseU = /underline/.test(deco);
    const baseS = /line-through/.test(deco);
    const ls = node.letterSpacing() || 0;
    ctx.save();
    ctx.textBaseline = 'alphabetic';
    ctx.textAlign = 'left';
    const hasLs = 'letterSpacing' in ctx;
    L.lines.forEach((line) => {
      if (line.bullet) {
        ctx.font = line.bullet.font;
        if (hasLs) ctx.letterSpacing = '0px';
        ctx.fillStyle = line.color || fill;
        ctx.fillText('•', line.bullet.x, line.baseline);
      }
      if (hasLs) ctx.letterSpacing = ls + 'px';
      let x = line.x0;
      line.items.forEach((p, idx) => {
        const r = p.run;
        const top = line.baseline - p.m.asc;
        const h = p.m.asc + p.m.desc;
        if (r.mark) { ctx.fillStyle = r.mark; ctx.fillRect(x, top, p.w, h); }
        if (r.code) { ctx.fillStyle = 'rgba(127,127,127,0.25)'; ctx.fillRect(x, top, p.w, h); }
        const color = r.color || line.color || fill;
        ctx.fillStyle = color;
        ctx.font = p.font;
        if (!p.space) ctx.fillText(p.text, x + p.padL, line.baseline);
        if (idx <= line.lastContent) {
          const th = Math.max(1, p.px / 16);
          if (r.u || r.href || baseU) ctx.fillRect(x, line.baseline + p.px * 0.1, p.w, th);
          if (r.s || baseS) ctx.fillRect(x, line.baseline - p.px * 0.28, p.w, th);
        }
        x += p.w;
      });
    });
    ctx.restore();
  }

  const SfRichText = (window.Konva && Konva.Text && window.SfMarkup) ? class extends Konva.Text {
    constructor(config) {
      super(config);
      this.on('uppercaseChange smallCapsChange', () => {
        this._sfLayout = null;
        const layer = this.getLayer();
        if (layer) layer.batchDraw();
      });
    }
    _setTextData() {
      super._setTextData();
      this._sfLayout = null;
    }
    _sfGetLayout() {
      if (!this._sfLayout || this._sfLayoutEpoch !== sfFontEpoch) {
        this._sfLayout = sfRichLayout(this);
        this._sfLayoutEpoch = sfFontEpoch;
      }
      return this._sfLayout;
    }
    getHeight() {
      const h = this.attrs.height;
      if (h === undefined || h === 'auto') return this._sfGetLayout().height;
      return h;
    }
    _sceneFunc(context) {
      sfRichDraw(this, context);
    }
  } : null;

  async function ensureFontsLoaded(slides) {
    if (!document.fonts || !document.fonts.load) return;
    try { await Promise.all(usedFontFamilies(slides).map((f) => loadFontFamily(f))); } catch (e) { /* ignore */ }
    try { await document.fonts.ready; } catch (e) { /* ignore */ }
  }

  // Fehlt eine Schrift (nicht hochgeladen/nicht geladen), soll die Bühne wie die Präsentation (SlideRenderer: «font-family:X,sans-serif»)
  // auf die serifenlose Ersatzschrift fallen, nicht auf die Serif-Vorgabe des Canvas. Der gespeicherte Familienname bleibt unverändert.
  function addCanvasFontFallback() {
    const proto = window.Konva && Konva.Text && Konva.Text.prototype;
    if (!proto || typeof proto._getContextFont !== 'function' || proto._sfSansFallback) return;
    const orig = proto._getContextFont;
    proto._getContextFont = function () {
      const f = orig.call(this);
      return /(?:^|[\s,])(?:sans-serif|serif|monospace)\s*$/.test(f) ? f : f + ', sans-serif';
    };
    proto._sfSansFallback = true;
  }

  async function init() {
    addCanvasFontFallback();
    try {
      const res = await apiGet('get_slides');
      SF.slides = res.slides;
    } catch (e) {
      setSaveStatus('Fehler beim Laden', true);
      console.error(e);
      return;
    }
    await ensureFontsLoaded(SF.slides);
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
    initCtrlWheelZoom();
    bindGlobalUI();
    initRibbonEditorHooks();
    initRibbonObjectColor();
    initRibbonObjectDelete();
    initSpellcheckPanel();
    initPixabayPanel();
    initIconifyPanel();
    initOpenclipartPanel();
    initMediaSearchButtons();
    bindNotesResize();
    bindEditorSplit();
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

  /**
   * Präsentation ab einer bestimmten Folie öffnen. Geht bewusst über denselben Weg
   * wie der Knopf im Menüband, damit die Rechtschreibprüfung vor dem Präsentieren
   * auch bei der Taste greift.
   */
  function presentLinkAvailable() {
    const link = document.getElementById('presentModeLink')
      || document.querySelector('a[data-ribbon-command="present_mode"]');
    /* Kein oder gesperrter Link heisst: hier wird nicht präsentiert (Folien-Set,
       Master-Folie). Dann bleibt F5 beim Browser und lädt wie gewohnt neu. */
    return !!link && link.getAttribute('aria-disabled') !== 'true' && !!link.getAttribute('href');
  }

  async function startPresentation(index) {
    const i = Math.max(0, Math.min(index | 0, SF.slides.length - 1));
    const href = 'present.php?id=' + encodeURIComponent(SF.id) + '&slide=' + i;
    if (SF.spellConfig?.beforePresent && window.SlideForgeSpellcheck?.ensureCleanBeforePresent) {
      const allow = await window.SlideForgeSpellcheck.ensureCleanBeforePresent(href);
      if (!allow) return;
    }
    window.location.href = href;
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

  // Mitlaufen (3.8.0, Urs 27.11.: «ein Player, der mir immer anzeigt, was ich gerade formatiere»): Folie und Speicherstand gehen in
  // den Live-Zustand, Kanal «editor» (live.php). Geräte und das «Web»-Fenster im Modus Mitlaufen fragen ihn im gewohnten Rhythmus ab.
  // Kein eigener Takt: gemeldet wird nur beim Folienwechsel und nach dem Speichern.
  let mitlaufTimer = null;
  let mitlaufSaved = false;
  function mitlaufMelden(saved) {
    if (!SF.canEdit || SF.masterSlideEditing) return;
    if (saved) mitlaufSaved = true;
    clearTimeout(mitlaufTimer);
    mitlaufTimer = setTimeout(() => { sendMitlauf(false); }, 150);
  }

  async function sendMitlauf(retry) {
    const saved = mitlaufSaved;
    mitlaufSaved = false;
    try {
      const res = await fetch('live.php?id=' + encodeURIComponent(SF.id), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ index: SF.currentIndex, frag: typeof SF.playFrag === 'number' ? SF.playFrag : null, anim: !!SF.mitAnim, channel: 'editor', source: 'editor', saved, csrf_token: SF.csrfToken }),
      });
      const json = await res.json().catch(() => null);
      if (json && json.error === 'csrf' && json.csrf_token && !retry) {
        SF.csrfToken = json.csrf_token;
        mitlaufSaved = mitlaufSaved || saved;
        await sendMitlauf(true);
      }
    } catch (e) { /* Mitlaufen ist Zugabe: Bearbeiten und Speichern laufen weiter */ }
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
    if (!container.clientWidth) return;   /* eingeklappt (3.9.4): beim Ausklappen rechnet der ResizeObserver neu */
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
    updateFilmstripSelection();
  }

  function bindFilmstripReorder(container) {
    if (!container || container.dataset.reorderBound) return;
    container.dataset.reorderBound = '1';

    let dragItem = null;
    let dragMoved = false;
    let block = null;

    container.addEventListener('mousedown', (e) => {
      if (!SF.canEdit || e.button !== 0) return;
      const item = e.target.closest('.editor-filmstrip-item');
      if (!item || e.target.closest('.tab-action')) return;

      dragItem = item;
      dragMoved = false;
      block = null;
      /* Mehrfachauswahl (3.11.1): Strg/Umschalt-Klick ändert nur die Auswahl, zieht keinen Block. */
      const mitZusatz = e.ctrlKey || e.metaKey || e.shiftKey;
      const startY = e.clientY;
      let blockGeprueft = false;
      item.classList.add('dragging');
      document.body.classList.add('editor-filmstrip-dragging');

      const onMove = (ev) => {
        if (!dragItem) return;
        if (!blockGeprueft && !mitZusatz && Math.abs(ev.clientY - startY) >= 5) {
          blockGeprueft = true;
          block = slideDragBlockStart(container, '.editor-filmstrip-item', dragItem);
        }
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
        const order = dragMoved && block
          ? slideDragBlockOrder(container, '.editor-filmstrip-item', dragItem.dataset.id, block)
          : null;
        slideDragBlockEnd(container, '.editor-filmstrip-item');
        dragItem = null;
        block = null;
        if (dragMoved) {
          SF.filmstripSuppressClick = true;
          if (order) commitSlideOrder(order);
          else commitFilmstripOrder();
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

  /* Nur wenn das Element ausserhalb des sichtbaren Bereichs liegt: knapp in den Blick, ohne Animation. */
  function scrollIntoViewIfHidden(scroller, el) {
    if (!scroller || !el) return;
    const sr = scroller.getBoundingClientRect();
    const er = el.getBoundingClientRect();
    if (er.top < sr.top || er.bottom > sr.bottom) el.scrollIntoView({ block: 'nearest' });
  }

  async function renderSlideFilmstrip() {
    const ae = document.activeElement;
    const hatteFilmstreifenFokus = !!(ae && ae.closest && ae.closest('#slideFilmstrip'));
    const container = document.getElementById('slideFilmstrip');
    if (!container) return;

    try {
      await fetchSlideThumbnails();
    } catch (e) {
      container.innerHTML = '<p style="color:var(--danger); font-size:0.85rem;">' + (SF.i18n.error || 'Fehler') + '</p>';
      console.error(e);
      return;
    }

    /* Bildlauf beim Neuaufbau halten (Urs 27.11.: «spult der Filmstreifen immer von anfang»):
       Stand vor dem Leeren merken, nach dem Aufbau im selben Schritt (vor dem Zeichnen) zurücksetzen. */
    const keepScrollTop = container.scrollTop;
    container.innerHTML = '';
    container.setAttribute('role', 'listbox');
    container.setAttribute('aria-multiselectable', 'true');
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
      /* Mehrfachauswahl (3.7.0): Strg/Cmd-Klick einzeln, Umschalt-Klick Bereich. */
      const istGewaehlt = gridSelectedIndices.size > 1 && gridSelectedIndices.has(i);
      if (istGewaehlt) tab.classList.add('selected');
      tab.setAttribute('role', 'option');
      tab.setAttribute('aria-selected', istGewaehlt || (gridSelectedIndices.size <= 1 && i === SF.currentIndex) ? 'true' : 'false');
      tab.setAttribute('aria-label', (SF.i18n.slideLabel || 'Folie') + ' ' + (i + 1));
      /* Anwählbar, damit die Eingabemarke hier stehen kann: Daran erkennen die
         Tastenkürzel, dass Strg+C/X/V/D der Folie gelten und nicht dem Objekt. */
      tab.tabIndex = 0;
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
        if (SF.canEdit && (e.ctrlKey || e.metaKey || e.shiftKey)) {
          e.preventDefault();
          filmstripSelectClick(e, idx);
          return;
        }
        if (gridSelectedIndices.size > 1) {
          gridSelectedIndices.clear();
          gridSelectedIndices.add(idx);
          gridLastClickedIndex = idx;
          updateFilmstripSelection();
        }
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

    container.scrollTop = keepScrollTop;
    const activeItem = container.querySelector('.filmstrip-item.active');
    scrollIntoViewIfHidden(container, activeItem);
    /* Stand die Eingabemarke vor dem Neuzeichnen im Filmstreifen, gehört sie auch
       danach dorthin: Sonst gälte das nächste Strg+C wieder dem Objekt, und ein
       zweites Ausschneiden hintereinander wäre nicht möglich. */
    if (hatteFilmstreifenFokus && activeItem) activeItem.focus({ preventScroll: true });
  }

  /** Alle Folien wählen (3.12.16, Urs 27.11.): Rasteransicht und Filmstreifen, Befehl «Alle Folien wählen» (Vorgabe Ctrl+A). */
  function selectAllSlides() {
    gridSelectedIndices.clear();
    SF.slides.forEach((_, i) => gridSelectedIndices.add(i));
    gridLastClickedIndex = SF.currentIndex;
    window.getSelection()?.removeAllRanges();
    if (SF.editorViewMode === 'grid') {
      syncRibbonTransitionFieldsFromSlide(SF.currentIndex);
      renderSlideGrid();
    } else {
      updateFilmstripSelection();
    }
  }

  /** Strg/Cmd- oder Umschalt-Klick in der Folienleiste: Auswahl ändern, Folie bleibt offen. */
  function filmstripSelectClick(e, index) {
    if (index < 0) return;
    window.getSelection()?.removeAllRanges();
    if (!gridSelectedIndices.size) gridSelectedIndices.add(SF.currentIndex);
    if (e.shiftKey) {
      const anker = gridLastClickedIndex !== null ? gridLastClickedIndex : SF.currentIndex;
      const a = Math.min(anker, index);
      const b = Math.max(anker, index);
      gridSelectedIndices.clear();
      for (let i = a; i <= b; i++) gridSelectedIndices.add(i);
    } else {
      if (gridSelectedIndices.has(index) && gridSelectedIndices.size > 1) gridSelectedIndices.delete(index);
      else gridSelectedIndices.add(index);
      gridLastClickedIndex = index;
    }
    updateFilmstripSelection();
  }

  function updateFilmstripSelection() {
    const container = document.getElementById('slideFilmstrip');
    if (!container) return;
    const mehrere = gridSelectedIndices.size > 1;
    container.querySelectorAll('.editor-filmstrip-item').forEach((item) => {
      const idx = SF.slides.findIndex((s) => s.id === item.dataset.id);
      const gewaehlt = mehrere ? gridSelectedIndices.has(idx) : idx === SF.currentIndex;
      item.classList.toggle('selected', mehrere && gewaehlt);
      item.setAttribute('aria-selected', gewaehlt ? 'true' : 'false');
    });
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

  /* GRID_DRAG_HYSTERESE — die gezogene Karte bleibt im Rasterfluss und belegt
     weiterhin eine Zelle. Nach jedem Einfuegen ruecken die Nachbarn nach, der
     naechstgelegene Mittelpunkt wechselt, und die naechste Mausbewegung schiebt
     zurueck: das Raster flackert. Der Filmstreifen hat das nicht, weil er nur
     Elemente OBERHALB des Cursors betrachtet, also richtungsgebunden entscheidet.
     Hier dasselbe in zwei Dimensionen: erst Zeile ueber die Zeilenmitte waehlen,
     dann innerhalb der Zeile nur einfuegen, wenn der Cursor die Kartenmitte
     wirklich um GRID_DRAG_HYSTERESE ueberschritten hat. */
  const GRID_DRAG_HYSTERESE = 12;

  function getGridAfterElement(container, x, y) {
    const items = [...container.querySelectorAll('.editor-slide-grid-item:not(.dragging)')];
    if (!items.length) return null;

    const boxes = items.map((el) => ({ el: el, box: el.getBoundingClientRect() }));

    /* Zeile bestimmen: alle Karten, deren vertikale Ausdehnung den Cursor enthaelt.
       Liegt der Cursor zwischen zwei Zeilen, gilt die naechstgelegene. */
    let row = boxes.filter((b) => y >= b.box.top && y <= b.box.bottom);
    if (!row.length) {
      let best = null;
      let bestDy = Infinity;
      boxes.forEach((b) => {
        const cy = b.box.top + b.box.height / 2;
        const dy = Math.abs(y - cy);
        if (dy < bestDy) { bestDy = dy; best = b; }
      });
      if (!best) return null;
      const topRef = best.box.top;
      row = boxes.filter((b) => Math.abs(b.box.top - topRef) < 2);
    }

    /* Innerhalb der Zeile die erste Karte, deren Mitte der Cursor noch nicht
       ueberschritten hat. Die Hysterese verhindert das Hin- und Herkippen genau
       an der Mittellinie. */
    for (let i = 0; i < row.length; i++) {
      const cx = row[i].box.left + row[i].box.width / 2;
      if (x < cx - GRID_DRAG_HYSTERESE) return row[i].el;
    }

    /* Hinter die letzte Karte dieser Zeile: das naechste Element im Gesamtraster. */
    const lastEl = row[row.length - 1].el;
    const idx = items.indexOf(lastEl);
    return items[idx + 1] || null;
  }

  function updateGridNumbers(container) {
    container.querySelectorAll('.editor-slide-grid-item').forEach((item, i) => {
      const num = item.querySelector('.editor-slide-grid-id');
      if (num) num.textContent = String(i + 1);
      item.dataset.index = String(i);
    });
  }

  /* Notizfeld per Ziehen an der Trennlinie hoeher/niedriger machen.
     Nach oben ziehen vergroessert, die Hoehe steht in --notes-height und
     wird pro Nutzer serverseitig gespeichert (Regel user-prefs-server). */
  function applyNotesHeight(px) {
    const h = Math.max(60, Math.min(900, Math.round(px)));
    SF.notesHeight = h;
    const panel = document.querySelector('.notes-panel-editor');
    if (panel) panel.style.setProperty('--notes-height', h + 'px');
    return h;
  }

  /* Notizen formatiert bearbeiten (3.12.41, Urs 27.11.: «kleinen Editor für Markdown Befehle also Fett, kursiv und Farben.
     Gespeichert wird es wie jetzt»). #slideNotesInput ist ein contenteditable wie das Bearbeiten an Ort der Textobjekte:
     Inhalt SfMarkup.toHtml(notes), zurück mit SfMarkup.fromHtml() (gleiche Regeln wie Markdown.php); Enter, Einfügen
     (nur reiner Text) und Formatieren der Markierung über das Zeilenmodell (SfRichEdit), kein execCommand.
     Geladene Notizen gehen unverändert zurück, solange niemand im Feld schreibt (notesEd.raw). */
  /* 3.12.49 (Urs 27.11.: Leiste «etwas zu klotzig», «über die Schaltflächen im Ribbon lösen»): formatiert wird über
     Menüband «Start» › Zeichen/Schriftfarbe, wie beim Bearbeiten an Ort (T4). active = Notizfeld hat den Fokus oder
     zuletzt gehabt und seither nur ins Menüband geklickt; dann wirken die Knöpfe auf notesEd.saved. */
  const notesEd = { raw: '', saved: null, active: false };
  const NOTES_RIBBON_BTN_SEL = '#rb_bold, #rb_italic, #rb_underline, #rb_strikethrough, #rb_uppercase, #rb_smallcaps, #rb_mark_clear';
  const NOTES_RIBBON_ZONE_SEL = NOTES_RIBBON_BTN_SEL + ', #ribbonStartZeichen, #ribbonStartColors';
  function notesField() { return document.getElementById('slideNotesInput'); }
  function notesRich() { return !!(window.SfMarkup && window.SfRichEdit); }
  /* Grundfarbe des Felds: vom Browser beim Zusammenfügen kopierte Farben fallen weg (wie base() beim Bearbeiten an Ort). */
  function notesBase(el) { return { color: getComputedStyle(el).color }; }
  function getNotesText() {
    return notesField() ? notesEd.raw : '';
  }
  function setNotesText(text) {
    const el = notesField();
    notesEd.raw = String(text || '');
    notesEd.saved = null;
    if (!el) return;
    if (notesRich()) el.innerHTML = window.SfMarkup.toHtml(notesEd.raw);
    else el.textContent = notesEd.raw;
    syncNotesEmpty(el);
    syncNotesFormatState(null);
  }
  function syncNotesEmpty(el) {
    el.classList.toggle('is-empty', !(el.textContent || '').replace(/\u200b/g, '').length);
  }
  /* Feld geändert: Auszeichnung lesen, in die Folie schreiben, speichern. */
  function notesChanged() {
    const el = notesField();
    if (!el) return;
    notesEd.raw = notesRich()
      ? window.SfMarkup.fromHtml(el, { base: notesBase(el) })
      : String(el.innerText || '').replace(/\n$/, '');
    syncNotesEmpty(el);
    const slide = SF.slides[SF.currentIndex];
    if (slide) {
      slide.notes = notesEd.raw;
      syncFilmstripNotesBadge(SF.currentIndex);
    }
    scheduleSave();
  }
  /* Markierung im Feld; liegt der Fokus im Menüband (Farbfeld), gilt die zuletzt gemerkte. */
  function notesSelection() {
    const el = notesField();
    if (!el || !notesRich()) return null;
    const R = window.SfRichEdit;
    const cur = R.readSelection(el, notesBase(el));
    if (cur) return cur;
    if (!notesEd.saved) return null;
    return { lines: window.SfMarkup.readLines(el, { base: notesBase(el) }), a: notesEd.saved.a, b: notesEd.saved.b };
  }
  /** fn(lines, a, b) → lines auf die Markierung; false = keine Markierung. */
  function notesApply(fn) {
    const el = notesField();
    const cur = notesSelection();
    if (!el || !cur || window.SfRichEdit.isEmptyRange(cur.lines, cur.a, cur.b)) return false;
    window.SfRichEdit.render(el, fn(cur.lines, cur.a, cur.b), cur.a, cur.b, document.activeElement === el);
    notesEd.saved = { a: cur.a, b: cur.b };
    notesChanged();
    syncNotesFormatState({ lines: window.SfMarkup.readLines(el, { base: notesBase(el) }), a: cur.a, b: cur.b });
    return true;
  }
  function notesToggle(key) {
    return notesApply((lines, a, b) => window.SfRichEdit.toggle(lines, a, b, key));
  }
  function notesSetValue(key, val) {
    return notesApply((lines, a, b) => window.SfRichEdit.setValue(lines, a, b, key, val, false));
  }
  /** Modell-Eingriff an der Schreibmarke (Enter, Einfügen): fn(lines, a, b) → {lines, pos} */
  function notesModelEdit(fn) {
    const el = notesField();
    const cur = el && notesRich() ? window.SfRichEdit.readSelection(el, notesBase(el)) : null;
    if (!cur) return false;
    const res = fn(cur.lines, cur.a, cur.b);
    if (!res) return false;
    window.SfRichEdit.render(el, res.lines, res.pos, res.pos, true);
    notesChanged();
    return true;
  }
  /* Menüband zeigt im Notizfeld den Zustand an der Schreibmarke (bzw. am Anfang der Markierung); nur Zeichen und
     Schriftfarbe sind aktiv, Schriftart/Grösse/Absatz bleiben grau. */
  function syncNotesFormatState(cur) {
    if (!notesEd.active) return;
    const R = window.SfRichEdit;
    const r = (cur && R) ? R.propsAt(cur.lines, cur.a, R.cmp(cur.a, cur.b) !== 0) : {};
    Object.keys(RB_INLINE_KEY).forEach((id) => {
      const btn = document.getElementById(id);
      if (!btn) return;
      const on = !!r[RB_INLINE_KEY[id]];
      btn.disabled = false;
      btn.dataset.checked = on ? '1' : '0';
      btn.classList.toggle('active', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    [['rb_color', r.color], ['rb_mark', r.mark]].forEach(([id, val]) => {
      const inp = document.getElementById(id);
      if (!inp) return;
      inp.disabled = false;
      if (document.activeElement !== inp && /^#[0-9a-f]{6}$/i.test(val || '')) inp.value = val;
    });
    ['rb_mark_clear', 'rb_color_apply', 'rb_mark_apply'].forEach((id) => {
      const b = document.getElementById(id);
      if (b) b.disabled = false;
    });
  }
  function notesRibbonOn() {
    if (notesEd.active || !notesRich()) return;
    notesEd.active = true;
    setRibbonStartEnabled(false);   // nur beim Einschalten: ein fokussiertes Farbfeld nicht kurz sperren (Fokus ginge verloren)
    syncNotesFormatState(notesSelection());
  }
  /* Fokus weg von Notizfeld und Menüband (z. B. Klick auf die Bühne): Menüband wieder fürs gewählte Objekt. */
  function notesRibbonOff() {
    if (!notesEd.active) return;
    notesEd.active = false;
    Object.keys(RB_INLINE_KEY).forEach((id) => document.getElementById(id)?.removeAttribute('aria-pressed'));
    syncRibbonStartTab();
  }
  function initNotesEditor() {
    const el = notesField();
    if (!el || el.dataset.bound) return;
    el.dataset.bound = '1';
    applySpellcheckAttrs(el);
    el.addEventListener('input', notesChanged);
    /* Format-Befehle des Browsers (z. B. ein anders belegtes Ctrl+B) nicht ins Feld lassen: sonst fremdes HTML. */
    el.addEventListener('beforeinput', (e) => {
      if (String(e.inputType || '').startsWith('format')) e.preventDefault();
    });
    el.addEventListener('keydown', (e) => {
      if (!notesRich()) return;
      if (e.key === 'Enter' && !e.isComposing && !e.ctrlKey && !e.metaKey && !e.altKey && !e.shiftKey) {
        e.preventDefault();
        notesModelEdit((lines, a, b) => {
          const d = window.SfRichEdit.deleteRange(lines, a, b);
          return window.SfRichEdit.splitLine(d.lines, d.pos);
        });
      }
    });
    /* Einfügen nur als reiner Text (Zeilenumbrüche → neue Zeilen, Format der Schreibmarke). */
    el.addEventListener('paste', (e) => {
      e.preventDefault();
      const txt = (e.clipboardData && e.clipboardData.getData('text/plain')) || '';
      if (!txt) return;
      if (!notesRich()) {
        const sel = window.getSelection();
        if (sel && sel.rangeCount) {
          const rg = sel.getRangeAt(0);
          rg.deleteContents();
          rg.insertNode(document.createTextNode(txt));
          rg.collapse(false);
        }
        notesChanged();
        return;
      }
      notesModelEdit((lines, a, b) => {
        const R = window.SfRichEdit;
        const props = R.propsAt(lines, a);
        const d = R.deleteRange(lines, a, b);
        return R.insertText(d.lines, d.pos, txt, props);
      });
    });
    el.addEventListener('drop', (e) => { e.preventDefault(); });
    document.addEventListener('selectionchange', () => {
      if (!notesRich()) return;
      const cur = window.SfRichEdit.readSelection(el, notesBase(el));
      if (!cur) return;
      notesEd.saved = { a: cur.a, b: cur.b };
      syncNotesFormatState(cur);
    });

    /* Menüband auf die Notizen umleiten (3.12.49): Knöpfe «Zeichen» nehmen beim mousedown den Fokus nicht,
       damit Markierung und Fokus im Feld bleiben; Felder (Schriftfarbe) bekommen den Fokus, es gilt notesEd.saved. */
    el.addEventListener('focusin', notesRibbonOn);
    document.addEventListener('mousedown', (e) => {
      if (!notesEd.active) return;
      const t = e.target;
      if (el.contains(t)) return;
      if (t && t.closest && t.closest(NOTES_RIBBON_ZONE_SEL)) {
        if (t.closest(NOTES_RIBBON_BTN_SEL)) e.preventDefault();
        return;
      }
      notesRibbonOff();
    }, true);
    document.addEventListener('focusin', (e) => {
      const t = e.target;
      if (!notesEd.active || el.contains(t) || (t && t.closest && t.closest(NOTES_RIBBON_ZONE_SEL))) return;
      notesRibbonOff();
    });
  }

  /* Notizen ein-/ausklappen (3.12.19, Urs 27.11.: «wie die Seitenleiste … bleibt der Play Button unten im Bild»).
     Gemerkt je Person in editor_ui.collapsed.sf_editor_notes; Befehl «Notizen ein-/ausblenden» im Katalog. */
  function toggleNotesPanel(force) {
    const panel = document.getElementById('notesPanel');
    const btn = document.getElementById('notesToggleBtn');
    if (!panel) return;
    const aus = typeof force === 'boolean' ? force : !panel.classList.contains('is-notes-off');
    panel.classList.toggle('is-notes-off', aus);
    SF.collapsed = SF.collapsed || {};
    if (aus) SF.collapsed.sf_editor_notes = true;
    else delete SF.collapsed.sf_editor_notes;
    if (btn) {
      const label = aus ? btn.dataset.labelShow : btn.dataset.labelHide;
      btn.setAttribute('aria-expanded', aus ? 'false' : 'true');
      if (label) { btn.setAttribute('aria-label', label); btn.title = label; }
    }
    if (!aus) document.getElementById('slideNotesInput')?.focus({ preventScroll: true });
    persistEditorUi({});
  }
  document.getElementById('notesToggleBtn')?.addEventListener('click', () => toggleNotesPanel());

  function bindNotesResize() {
    const handle = document.getElementById('notesResizeHandle');
    const input = document.getElementById('slideNotesInput');
    if (!handle || !input || handle.dataset.bound) return;
    handle.dataset.bound = '1';
    applyNotesHeight(SF.notesHeight);

    /* Wie der Hub-Trenner (HubSplit), nur senkrecht: Zeiger ziehen, Pfeiltasten je 24 px,
       Doppelklick zurück auf die Standardhöhe. Die Bühne passt sich über ihren ResizeObserver an. */
    handle.addEventListener('pointerdown', (e) => {
      if (e.button !== 0) return;
      e.preventDefault();
      const startY = e.clientY;
      const startH = input.getBoundingClientRect().height;
      document.body.classList.add('notes-resizing');

      const onMove = (ev) => {
        ev.preventDefault();
        applyNotesHeight(startH + (startY - ev.clientY));
      };
      const onUp = () => {
        document.removeEventListener('pointermove', onMove);
        document.removeEventListener('pointerup', onUp);
        document.removeEventListener('pointercancel', onUp);
        document.body.classList.remove('notes-resizing');
        persistEditorUi({ notesHeight: SF.notesHeight });
      };
      document.addEventListener('pointermove', onMove, { passive: false });
      document.addEventListener('pointerup', onUp);
      document.addEventListener('pointercancel', onUp);
    });

    handle.addEventListener('keydown', (e) => {
      if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
      e.preventDefault();
      applyNotesHeight(SF.notesHeight + (e.key === 'ArrowUp' ? 24 : -24));
      persistEditorUi({ notesHeight: SF.notesHeight });
    });

    handle.addEventListener('dblclick', () => {
      applyNotesHeight(140);
      persistEditorUi({ notesHeight: SF.notesHeight });
    });
  }

  /* Ablage für HubSplit (Signatur von HubUserPrefsUi, wie FileForge ff-ribbon.js): «split:»-Schlüssel
     liegen je Person auf dem Server (editor_ui.splits über user_api.php set_editor_ui), alles andere
     (z. B. Schwebedialog-Lage von hub-float-dialog.js) wie bisher im Browser unter «sf_ui:». */
  function ensureUserPrefsUi() {
    if (window.HubUserPrefsUi) return;
    /* «split:<key>:off» = Seitenleiste eingeklappt (HubSplit 2.34.2); ohne Eintrag gilt der Stand im Markup (is-side-off/is-detail-off). */
    const offKey = (key) => {
      const m = /^split:([a-z0-9_]{1,40}):off$/.exec(String(key || ''));
      return m ? m[1] : null;
    };
    const splitKey = (key) => {
      const k = String(key || '');
      return k.indexOf('split:') === 0 ? k.slice(6) : null;
    };
    const local = {
      get(k) {
        try {
          const raw = localStorage.getItem('sf_ui:' + k);
          return raw == null ? null : JSON.parse(raw);
        } catch (_) { return null; }
      },
      set(k, v) {
        try {
          if (v == null) localStorage.removeItem('sf_ui:' + k);
          else localStorage.setItem('sf_ui:' + k, JSON.stringify(v));
        } catch (_) { /* ignore */ }
      },
    };
    window.HubUserPrefsUi = {
      ensure: () => Promise.resolve(),
      flush: () => {},
      get(key, fallback) {
        const ck = offKey(key);
        if (ck !== null) return Object.prototype.hasOwnProperty.call(SF.collapsed, ck) ? true : fallback;
        const sk = splitKey(key);
        if (sk !== null) return Object.prototype.hasOwnProperty.call(SF.splits, sk) ? SF.splits[sk] : fallback;
        const v = local.get(String(key || ''));
        return v == null ? fallback : v;
      },
      set(key, value) {
        const ck = offKey(key);
        if (ck !== null) {
          if (value === true) SF.collapsed[ck] = true;
          else delete SF.collapsed[ck];
          persistEditorUi({});
          return;
        }
        const sk = splitKey(key);
        if (sk !== null) {
          if (!/^[a-z0-9_]{1,40}$/.test(sk)) return;
          const px = parseInt(String(value == null ? '' : value), 10);
          if (px > 0) SF.splits[sk] = px;
          else delete SF.splits[sk];
          persistEditorUi({});
          return;
        }
        local.set(String(key || ''), value);
      },
      migrateLocal(lsKey, uiKey) {
        try {
          const raw = localStorage.getItem(lsKey);
          if (raw == null || raw === '') return;
          if (this.get(uiKey, null) == null) {
            let val = raw;
            try { val = JSON.parse(raw); } catch (_) { /* Text */ }
            this.set(uiKey, val);
          }
          localStorage.removeItem(lsKey);
        } catch (_) { /* ignore */ }
      },
    };
  }

  /* Folienleiste links und Seitenleiste rechts per Trenner verbreitern (Hub-Vorlage Lab › Layout)
     und per Griff ein-/ausklappen (3.9.4, wie PD; HubSplit 2.34.2, Stand in editor_ui.collapsed).
     Miniaturen und Bühne folgen über ihre ResizeObserver (updateFilmstripDimensions, resizeStageToFit). */
  function bindEditorSplit() {
    const root = document.querySelector('.editor-layout');
    if (!root || !window.HubSplit) return;
    ensureUserPrefsUi();
    window.HubSplit.bind(root, {
      sideVar: '--editor-filmstrip-width',
      sideStorage: 'sf_editor_filmstrip',
      sideMin: 160,
      sideDefault: 300,
      detailVar: '--editor-props-width',
      detailStorage: 'sf_editor_props',
      detailMin: 260,
      detailDefault: 300,
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
    let block = null;

    container.addEventListener('mousedown', (e) => {
      if (!SF.canEdit || e.button !== 0) return;
      if (e.ctrlKey || e.metaKey || e.shiftKey) return;
      const item = e.target.closest('.editor-slide-grid-item');
      if (!item || e.target.closest('.tab-action')) return;

      dragItem = item;
      dragMoved = false;
      dragging = false;
      block = null;
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
          /* Gehört die Kachel zu einer Mehrfachauswahl, wandern alle markierten mit (3.11.1). */
          block = slideDragBlockStart(container, '.editor-slide-grid-item', dragItem);
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
        const order = dragMoved && block
          ? slideDragBlockOrder(container, '.editor-slide-grid-item', dragItem.dataset.id, block)
          : null;
        slideDragBlockEnd(container, '.editor-slide-grid-item');
        dragItem = null;
        block = null;
        if (dragMoved) {
          gridSuppressClick = true;
          if (order) commitSlideOrder(order);
          else commitGridOrder();
          setTimeout(() => { gridSuppressClick = false; }, 100);
        }
      };

      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    });
  }

  /* Mehrere Folien verschieben (3.11.1, Urs 27.11.): Wird eine Folie gezogen, die zur
     Mehrfachauswahl gehört, wandern alle markierten als Block in ihrer bisherigen Reihenfolge
     an die Stelle der gezogenen Kachel. Die gezogene Kachel ist die Einfügemarke und trägt
     die Anzahl, die übrigen markierten bleiben halbtransparent an ihrem Platz. */
  function slideDragBlockStart(container, itemSel, dragItem) {
    const idx = SF.slides.findIndex((s) => s.id === dragItem.dataset.id);
    if (gridSelectedIndices.size < 2 || !gridSelectedIndices.has(idx)) return null;
    const ids = slideIdsAt([...gridSelectedIndices].sort((a, b) => a - b));
    if (ids.length < 2) return null;
    const set = new Set(ids);
    container.querySelectorAll(itemSel).forEach((el) => {
      if (el !== dragItem && set.has(el.dataset.id)) el.classList.add('slide-drag-companion');
    });
    dragItem.classList.add('slide-drag-lead');
    const badge = document.createElement('span');
    badge.className = 'slide-drag-count';
    badge.setAttribute('aria-hidden', 'true');
    badge.textContent = String(ids.length);
    dragItem.appendChild(badge);
    return ids;
  }

  function slideDragBlockEnd(container, itemSel) {
    container.querySelectorAll(itemSel).forEach((el) => el.classList.remove('slide-drag-companion', 'slide-drag-lead'));
    container.querySelectorAll('.slide-drag-count').forEach((el) => el.remove());
  }

  /** Neue Reihenfolge: an der Stelle der gezogenen Kachel steht der ganze Block. */
  function slideDragBlockOrder(container, itemSel, dragId, blockIds) {
    const set = new Set(blockIds);
    const out = [];
    container.querySelectorAll(itemSel).forEach((el) => {
      const id = el.dataset.id;
      if (id === dragId) out.push(...blockIds);
      else if (!set.has(id)) out.push(id);
    });
    return out;
  }

  /** Strg+Umschalt+Pfeil (3.11.1): markierte Folien um eine Stelle verschieben; Lücken bleiben,
      eine Folie am Rand oder direkt hinter einer blockierten bleibt stehen. Ein Rückgängig-Schritt. */
  function moveSelectedSlides(dir) {
    if (!SF.canEdit) return Promise.resolve();
    const ids = slideIdsAt(shortcutSlideIndices());
    return deckUi(null, () => {
      const order = SF.slides.map((s) => s.id);
      const set = new Set(ids);
      if (dir < 0) {
        for (let i = 1; i < order.length; i++) {
          if (set.has(order[i]) && !set.has(order[i - 1])) {
            const t = order[i - 1]; order[i - 1] = order[i]; order[i] = t;
          }
        }
      } else {
        for (let i = order.length - 2; i >= 0; i--) {
          if (set.has(order[i]) && !set.has(order[i + 1])) {
            const t = order[i + 1]; order[i + 1] = order[i]; order[i] = t;
          }
        }
      }
      return commitSlideOrderNow(order);
    });
  }

  function commitSlideOrder(newOrderIds) {
    return deckUi(null, () => commitSlideOrderNow(newOrderIds));
  }

  async function commitSlideOrderNow(newOrderIds) {
    const oldOrderIds = SF.slides.map((s) => s.id);
    if (JSON.stringify(newOrderIds) === JSON.stringify(oldOrderIds)) return;

    const currentId = SF.slides[SF.currentIndex] ? SF.slides[SF.currentIndex].id : null;
    const selectedIds = [...gridSelectedIndices]
      .map((i) => SF.slides[i] && SF.slides[i].id)
      .filter(Boolean);

    const before = await deckBegin();
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
      mitlaufMelden(true);
      deckCommit(before);
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

  async function switchToSlide(index, opts) {
    if (index === SF.currentIndex) return;
    if (play.el && !(opts && opts.keepPlay)) closePlayOverlay(false);
    SF.skipPreviewReload = true;
    await saveCurrentSlide(true);
    SF.skipPreviewReload = false;
    loadSlideIntoStage(index);
    updateFilmstripActive();
    const container = document.getElementById('slideFilmstrip');
    scrollIntoViewIfHidden(container, container?.querySelector('.filmstrip-item.active'));
  }

  function loadSlideIntoStage(index, skipHistoryReset) {
    SF.currentIndex = index;
    const slide = SF.slides[index];
    if (!slide) return;
    /* In der Folienansicht folgt die Auswahl der geöffneten Folie; Strg/Umschalt-Klick
       in der Folienleiste erweitert sie, ohne die Folie zu wechseln. */
    if (SF.editorViewMode !== 'grid') {
      gridSelectedIndices.clear();
      gridSelectedIndices.add(index);
      gridLastClickedIndex = index;
    }
    // Hub-Adresse folgt der offenen Folie (Ctrl+R im Hub öffnet sie wieder, Urs 27.11.)
    if (window.SFAppOrt && !SF.templateMode) window.SFAppOrt.melde({ slide: index });

    // Alte Objekte entfernen (ausser Hintergrund + Transformer)
    getTopLevelNodes().forEach((n) => n.destroy());
    SF.layer.find('.sf-badge, .sf-guide').forEach((n) => n.destroy());
    deselect();

    const bg = slide.background || { type: 'color', value: '#111111' };
    SF.currentBackground = bg;
    applyBackgroundVisual(bg);

    /* Doppelte Kennungen heilen: Vor einer Korrektur im Renderer übernahmen Kopien
       eines Vorlagen-Schmucks die Kennung der Vorlage. Zwei Objekte mit derselben
       Kennung lassen sich in der Ebenenliste nicht auseinanderhalten — ein Klick wählte
       immer dasselbe. Beim nächsten Speichern ist die Folie dauerhaft in Ordnung. */
    (function () {
      const gesehen = new Set();
      (slide.objects || []).forEach((obj) => {
        const id = String(obj.id || '');
        if (id === '' || gesehen.has(id)) {
          obj.id = 'x' + Math.random().toString(36).slice(2, 9);
        }
        gesehen.add(String(obj.id));
      });
    })();

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
      setNotesText(slide.notes || '');
    }
    if (!skipHistoryReset) resetHistoryForCurrentSlide();
    updatePresentLinkOnSlideChange();
    mitlaufMelden(false);
    updateEditorContextTitle();
    renderLayersPanel();
    /* Die rechte Leiste gehört zur neuen Folie: `deselect()` ganz oben hat sie
       gezeichnet, als die alten Objekte schon weg und die neuen noch nicht da
       waren — die Effekt-Liste blieb darum leer, bis man auf die Folie klickte
       (Urs, 24.10.2026). */
    refreshPropsPanel();
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

  async function uploadAsset(kind, src) {
    if (!src) return;
    setSaveStatus('Übernimmt ' + (kind === 'video' ? 'Video' : 'Bild') + ' aus FileForge…');
    try {
      const json = await storeMediaAsset(kind, src);
      SF.currentBackground = { type: kind, value: json.url };
      applyBackgroundVisual(SF.currentBackground);
      populateBackgroundControls(SF.currentBackground);
      updateCurrentTabSwatch();
      scheduleSave();
      closeSlideBgMediaModal();
    } catch (e) {
      setSaveStatus(e.message || 'Die Datei liess sich nicht übernehmen.', true);
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
    document.getElementById('slideBgMediaModalBrowse')?.addEventListener('click', async () => {
      const kind = SF.slideBgMediaModalKind === 'video' ? 'video' : 'image';
      const src = await pickMediaSource(kind);
      if (!src) return;
      await uploadAsset(kind, src);
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
    await duplicateSlides(slideActionIndices(index));
  }

  /** Mehrere Folien duplizieren — ein Schritt im Verlauf. Von hinten nach vorn,
      damit die Kopien die noch offenen Nummern nicht verschieben. */
  function duplicateSlides(indices) {
    const ids = slideIdsAt(indices);
    return deckUi('duplicate_slide', () => duplicateSlidesNow(slideIndicesOf(ids)));
  }

  async function duplicateSlidesNow(indices) {
    if (!indices.length) return;
    const before = await deckBegin();
    try {
      setSaveStatus('Speichere…');
      for (const i of [...indices].sort((a, b) => b - a)) {
        const res = await api('duplicate_slide', { index: i });
        SF.slides = res.slides;
      }
      await renderSlideFilmstrip();
      if (SF.editorViewMode === 'grid') renderSlideGrid();
      setSaveStatus('Gespeichert');
      mitlaufMelden(true);
    } catch (e) {
      setSaveStatus('Fehler', true);
      if (window.CfStatus) CfStatus.show('error', e.message);
    }
    deckCommit(before);
  }

  /** Knopf an einer Folie: Gehört sie zur Mehrfachauswahl, gilt die ganze Auswahl. */
  function slideActionIndices(index) {
    if (index < 0) return [];
    const sel = shortcutSlideIndices();
    return sel.length > 1 && sel.includes(index) ? sel : [index];
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
    await deckUi('import_slide_to_layout_set', async () => {
      const idx = SF.slides.findIndex((s) => s.id === slide.id);
      if (idx < 0) return;
      try {
        setSaveStatus('Speichere…');
        const res = await api('import_slide_to_layout_set', {
          index: idx,
          layout_set_id: SF.importLayoutSetId,
          layout_key: layoutKey.trim(),
        });
        setSaveStatus(res.message || I.importSlideToSetDone || 'Gespeichert');
      } catch (e) {
        setSaveStatus('Fehler', true);
        await SFDialog.alert(e.message || 'Import fehlgeschlagen.');
        console.error(e);
      }
    });
  }

  async function deleteSlide(index) {
    await deleteSlides(slideActionIndices(index));
  }

  /** Eine oder mehrere Folien löschen (Urs 27.11.: «kann ich auch mehrere Folien gleichzeitig löschen?»).
      Die letzte Folie bleibt; rückgängig mit Strg+Z. */
  async function deleteSlides(indices) {
    indices = [...new Set(indices)].filter((i) => i >= 0 && i < SF.slides.length).sort((a, b) => a - b);
    if (!indices.length) return;
    if (indices.length >= SF.slides.length) {
      await SFDialog.alert(SF.slides.length <= 1
        ? 'Die letzte verbleibende Folie kann nicht gelöscht werden.'
        : 'Alle Folien zu löschen geht nicht: Mindestens eine Folie muss bleiben.');
      return;
    }
    const frage = indices.length === 1
      ? 'Diese Folie wirklich löschen?'
      : (I.deleteSlidesConfirm || '{n} Folien löschen?').replace('{n}', String(indices.length));
    /* Steht die Rückfrage schon, löst eine weitere Entf-Taste keine zweite aus. */
    if (SF.deleteAsking) return;
    SF.deleteAsking = true;
    let ja = false;
    try {
      ja = await SFDialog.confirm(frage, { danger: true });
    } finally {
      SF.deleteAsking = false;
    }
    if (!ja) return;
    const ids = slideIdsAt(indices);
    await deckUi('delete_slide', () => deleteSlidesNow(ids));
  }

  async function deleteSlidesNow(ids) {
    const indices = slideIndicesOf(ids).sort((a, b) => a - b);
    if (!indices.length || indices.length >= SF.slides.length) return;
    const before = await deckBegin();
    const currentId = currentSlideId();
    const removedIds = new Set(ids);
    try {
      setSaveStatus('Speichere…');
      /* Von hinten nach vorn, sonst verschieben sich die Nummern der noch zu löschenden Folien. */
      for (const i of [...indices].reverse()) {
        const res = await api('delete_slide', { index: i });
        SF.slides = res.slides;
      }
      setSaveStatus('Gespeichert');
    } catch (e) {
      setSaveStatus('Fehler', true);
      await SFDialog.alert(e.message || 'Folie konnte nicht gelöscht werden.');
      console.error(e);
    }
    if (SF.currentIndex >= SF.slides.length) SF.currentIndex = SF.slides.length - 1;
    await afterDeckChange(removedIds.has(currentId) ? null : currentId);
    deckCommit(before);
  }

  async function toggleSlidePresentDisabled(index) {
    if (index < 0) return;
    setSaveStatus('Speichere…');
    const res = await api('toggle_slide_present_disabled', { index });
    SF.slides = res.slides;
    await renderSlideFilmstrip();
    if (SF.editorViewMode === 'grid') renderSlideGrid();
    setSaveStatus('Gespeichert');
    mitlaufMelden(true);
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

  /* Ctrl/Cmd + Mausrad (auch Zwei-Finger-Zoom am Trackpad) zoomt die Folie bzw. die Miniaturen
     der Rasteransicht statt der ganzen Seite (Urs 27.11.2026). */
  function initCtrlWheelZoom() {
    const onWheel = (ev, grid) => {
      if (!(ev.ctrlKey || ev.metaKey) || !ev.deltaY) return;
      ev.preventDefault();
      const dir = ev.deltaY < 0 ? 1 : -1;
      if (grid) applyGridThumbMin(gridThumbMinPx() + dir * 16, { save: true });
      else zoomBy(dir * 10);
    };
    document.getElementById('stageWrap')?.addEventListener('wheel', (ev) => onWheel(ev, false), { passive: false });
    document.getElementById('slideGrid')?.addEventListener('wheel', (ev) => onWheel(ev, true), { passive: false });
    /* Klick aufs Mausrad (mittlere Taste) setzt zurück: Folie auf «Einpassen», Raster auf Standardgrösse (Urs 27.11.). */
    const onMiddle = (ev, grid) => {
      if (ev.button !== 1) return;
      ev.preventDefault();
      if (ev.type !== 'mousedown') return;
      if (grid) applyGridThumbMin(168, { save: true });
      else zoomFit();
    };
    ['mousedown', 'auxclick'].forEach((t) => {
      document.getElementById('stageWrap')?.addEventListener(t, (ev) => onMiddle(ev, false));
      document.getElementById('slideGrid')?.addEventListener(t, (ev) => onMiddle(ev, true));
    });
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
    /* Bildlauf halten wie im Filmstreifen (kein Springen an den Anfang beim Löschen/Verschieben). */
    const gridScroller = grid.closest('.editor-slide-grid-scroll') || grid;
    const keepGridScroll = gridScroller.scrollTop;
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
    gridScroller.scrollTop = keepGridScroll;
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
    /* Raster und Filmstreifen gleich (3.12.22, Urs 27.11.: «Grid und Filmstreifen sind nicht gleich mit der Markierung»):
       die zuletzt angeklickte Folie wird auch die offene Folie; die Auswahl im Raster bleibt. */
    if (gridSelectedIndices.has(index) && index !== SF.currentIndex) {
      switchToSlide(index).then(() => updateFilmstripSelection()).catch(() => {});
    } else {
      updateFilmstripSelection();
    }
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
      mitlaufMelden(true);
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
      mitlaufMelden(true);
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

  async function openInsertFromUrl() {
    if (!SF.canEdit) return;
    const answer = await SFDialog.prompt({
      title: I.fromUrlTitle || 'Von URL einfügen',
      message: I.fromUrlMessage || '',
      placeholder: I.fromUrlPlaceholder || 'https://…',
    });
    const url = (answer || '').trim();
    if (!url) return;

    let data = null;
    try {
      const res = await fetch('media_url.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': SF.csrfToken },
        body: JSON.stringify({ id: SF.id, url: url, csrf_token: SF.csrfToken }),
      });
      data = await res.json();
    } catch (_) {
      await SFDialog.alert(I.fromUrlFailed || 'Das Einfügen ist fehlgeschlagen.');
      return;
    }
    if (!data || !data.ok) {
      await SFDialog.alert((data && data.error) || I.fromUrlFailed || 'Das Einfügen ist fehlgeschlagen.');
      return;
    }

    if (data.kind === 'image') {
      await insertImageObjectAtCenter(data.url);
    } else {
      const size = data.kind === 'audio' ? { w: 280, h: 56 } : { w: 400, h: 240 };
      const obj = {
        id: 'o' + Math.random().toString(16).slice(2, 10),
        type: data.kind,
        x: Math.round(SF.meta.width / 2) - size.w / 2,
        y: Math.round(SF.meta.height / 2) - size.h / 2,
        w: size.w, h: size.h, rotation: 0, opacity: 1, src: data.url,
      };
      insertNode(createNode(obj));
    }
    SF.refreshMediaLibrary?.();
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

  /* Verlaufsfarbe mit Deckkraft (0–100) als rgba; gleiche Rechnung in SlideRenderer::gradStop (3.12.23). */
  function gradRgba(hex, alpha) {
    const a = Math.max(0, Math.min(100, alpha === undefined || alpha === null || alpha === '' ? 100 : Number(alpha))) / 100;
    const m = /^#?([0-9a-f]{6})$/i.exec(String(hex || '').trim());
    if (!m) return hex;
    const n = parseInt(m[1], 16);
    return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')';
  }
  /* Farbpunkte des Verlaufs (3.12.29): [{c:'#hex', a:0–100, p:0–100}], mindestens zwei. Ältere Objekte haben nur
     gradColor1/2 (+ gradAlpha1/2) → zwei Punkte bei 0 % und 100 %. gradColor1/2 und gradAlpha1/2 bleiben als erster/letzter
     Punkt gespeichert (PPTX/ODP-Export). Gleiche Regeln in SlideRenderer::gradStopsOf. */
  function normGradStops(list) {
    if (!Array.isArray(list)) return null;
    const out = list.map((s) => ({
      c: /^#[0-9a-f]{6}$/i.test(String(s && s.c || '')) ? String(s.c) : '#3a6c8d',
      a: Number.isFinite(parseInt(s && s.a, 10)) ? Math.max(0, Math.min(100, parseInt(s.a, 10))) : 100,
      p: Number.isFinite(parseInt(s && s.p, 10)) ? Math.max(0, Math.min(100, parseInt(s.p, 10))) : 0,
    })).slice(0, 12);
    return out.length >= 2 ? out : null;
  }
  function getGradStops(node) {
    const own = normGradStops(node.getAttr('gradStops'));
    if (own) return own;
    return [
      { c: node.getAttr('gradColor1') || '#3a6c8d', a: gradAlpha(node, 1), p: 0 },
      { c: node.getAttr('gradColor2') || '#87b42b', a: gradAlpha(node, 2), p: 100 },
    ];
  }
  function setGradStops(node, stops) {
    const list = normGradStops(stops) || getGradStops(node);
    node.setAttr('gradStops', list);
    const sorted = list.slice().sort((x, y) => x.p - y.p);
    node.setAttr('gradColor1', sorted[0].c);
    node.setAttr('gradColor2', sorted[sorted.length - 1].c);
    node.setAttr('gradAlpha1', sorted[0].a);
    node.setAttr('gradAlpha2', sorted[sorted.length - 1].a);
  }
  function gradCss(stops, angle) {
    const sorted = stops.slice().sort((x, y) => x.p - y.p);
    return 'linear-gradient(' + angle + 'deg, ' + sorted.map((s) => gradRgba(s.c, s.a) + ' ' + s.p + '%').join(', ') + ')';
  }

  function gradAlpha(node, i) {
    const v = node.getAttr('gradAlpha' + i);
    return v === undefined || v === null ? 100 : Math.max(0, Math.min(100, parseInt(v, 10)));
  }

  function applyShapeGradientVisual(node) {
    const type = node.getAttr('objType');
    const angle = node.getAttr('gradAngle') || 0;
    const c1 = gradRgba(node.getAttr('gradColor1') || '#3a6c8d', gradAlpha(node, 1));
    const c2 = gradRgba(node.getAttr('gradColor2') || '#87b42b', gradAlpha(node, 2));
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
    /* Länge der Verlaufslinie wie CSS linear-gradient (Präsentation, Miniaturen): |w·sin α| + |h·cos α|; vorher die Diagonale,
       dadurch lagen die Farbpunkte auf der Bühne weiter auseinander als in der Präsentation (3.12.34, Urs 27.11.). */
    const a = angle * Math.PI / 180;
    const half = (Math.abs(w * Math.sin(a)) + Math.abs(h * Math.cos(a))) / 2;
    const cx = ox + w / 2, cy = oy + h / 2;
    node.fillLinearGradientStartPoint({ x: cx - dx * half, y: cy - dy * half });
    node.fillLinearGradientEndPoint({ x: cx + dx * half, y: cy + dy * half });
    const flat = [];
    getGradStops(node).slice().sort((x, y) => x.p - y.p).forEach((s) => flat.push(s.p / 100, gradRgba(s.c, s.a)));
    node.fillLinearGradientColorStops(flat.length >= 4 ? flat : [0, c1, 1, c2]);
    node.fillPriority('linear-gradient');
  }

  function setShapeFill(node, obj) {
    if (obj.fillType === 'gradient') {
      node.setAttr('fillType', 'gradient');
      node.setAttr('gradColor1', obj.gradColor1 || '#3a6c8d');
      node.setAttr('gradColor2', obj.gradColor2 || '#87b42b');
      node.setAttr('gradAngle', obj.gradAngle !== undefined ? obj.gradAngle : 90);
      node.setAttr('gradAlpha1', obj.gradAlpha1 !== undefined ? obj.gradAlpha1 : 100);
      node.setAttr('gradAlpha2', obj.gradAlpha2 !== undefined ? obj.gradAlpha2 : 100);
      if (normGradStops(obj.gradStops)) node.setAttr('gradStops', normGradStops(obj.gradStops));
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

  /* ---------- Tabelle ----------
     Eine Tabelle ist eine Konva-Gruppe: je Zelle ein Rahmen-Rechteck und ein Text.
     Die Zeilenhöhe richtet sich nach dem Inhalt (gemessen, nicht geraten) — so steht
     die Tabelle auf der Folie gleich wie im Editor, denn der Renderer lässt die
     Zeilen ebenfalls vom Inhalt wachsen. */
  const TABLE_DEFAULTS = {
    fontFamily: 'Open Sans', fontSize: 44, color: '#ffffff', align: 'left',
    borderColor: '#ffffff', borderWidth: 2, cellPadding: 10, lineHeight: 1.25,
    headerRow: true, headerColor: '', headerFill: 'transparent', headerFillOpacity: 1,
    stripeRows: false, stripeFill: 'transparent', stripeOpacity: 0.15,
  };

  /**
   * Füllfarbe einer Zeile aus Farbe und Füllstärke. Gibt `null` zurück, wenn nichts
   * zu füllen ist — eine Konva-Form ohne `fill` zeichnet keine Fläche.
   */
  function tableFuellung(hex, anteil) {
    const farbe = String(hex || '');
    if (!farbe || farbe === 'transparent') return null;
    const a = Math.max(0, Math.min(1, Number(anteil ?? 1)));
    if (!(a > 0)) return null;
    if (a >= 1) return farbe;
    const m = /^#?([0-9a-f]{6})$/i.exec(farbe);
    if (!m) return farbe;
    const z = parseInt(m[1], 16);
    return 'rgba(' + ((z >> 16) & 255) + ',' + ((z >> 8) & 255) + ',' + (z & 255) + ',' + a.toFixed(3) + ')';
  }

  function normalizeTableRows(rows) {
    if (!Array.isArray(rows) || rows.length === 0) return [['', ''], ['', '']];
    const cols = rows.reduce((m, r) => Math.max(m, Array.isArray(r) ? r.length : 0), 0) || 1;
    return rows.map((r) => {
      const row = Array.isArray(r) ? r.slice(0, cols) : [];
      while (row.length < cols) row.push('');
      return row.map((c) => (c === null || c === undefined ? '' : String(c)));
    });
  }

  function tableAttr(group, key) {
    const v = group.getAttr(key);
    return (v === undefined || v === null) ? TABLE_DEFAULTS[key] : v;
  }

  /** Spaltenbreiten in Pixeln aus den Anteilen. */
  function tableColPixels(group, cols) {
    const totalW = Math.max(40, group.getAttr('tableW') || 800);
    let parts = group.getAttr('tableColWidths');
    if (!Array.isArray(parts) || parts.length !== cols) {
      parts = new Array(cols).fill(1);
    }
    const sauber = parts.map((p) => Math.max(0, Number(p) || 0));
    const sum = sauber.reduce((a, b) => a + b, 0);
    /* Sind alle Anteile null (oder Unsinn), werden die Spalten gleich breit —
       sonst käme eine Tabelle mit Breite null heraus, also eine unsichtbare. */
    if (sum <= 0) return new Array(cols).fill(totalW / cols);
    return sauber.map((p) => (p / sum) * totalW);
  }

  /** Zeichnet die Gruppe neu und gibt die Gesamthöhe zurück. */
  function drawTable(group) {
    const rows = normalizeTableRows(group.getAttr('tableRows'));
    group.setAttr('tableRows', rows);
    const cols = rows[0].length;
    const colW = tableColPixels(group, cols);
    const pad = Math.max(0, Number(tableAttr(group, 'cellPadding')) || 0);
    const fontSize = Math.max(6, Number(tableAttr(group, 'fontSize')) || 32);
    const fontFamily = tableAttr(group, 'fontFamily');
    const color = tableAttr(group, 'color');
    const align = tableAttr(group, 'align');
    const lineHeight = Number(tableAttr(group, 'lineHeight')) || 1.25;
    const borderColor = tableAttr(group, 'borderColor');
    const borderWidth = Math.max(0, Number(tableAttr(group, 'borderWidth')) || 0);
    const headerRow = !!tableAttr(group, 'headerRow');
    const headerColor = tableAttr(group, 'headerColor') || color;
    const kopfFuellung = headerRow
      ? tableFuellung(tableAttr(group, 'headerFill'), tableAttr(group, 'headerFillOpacity'))
      : null;
    const streifenFuellung = tableAttr(group, 'stripeRows')
      ? tableFuellung(tableAttr(group, 'stripeFill'), tableAttr(group, 'stripeOpacity'))
      : null;

    group.destroyChildren();

    /* Erst messen, dann zeichnen: die Höhe einer Zeile ist die höchste Zelle darin.
       Der Markdown-Auszeichnungen wegen wird roh gemessen — ein `**` mehr oder
       weniger verschiebt die Höhe nicht nennenswert. */
    const texts = [];
    const rowH = rows.map((row, r) => {
      let hoch = fontSize * lineHeight;
      row.forEach((cell, c) => {
        const t = new Konva.Text({
          text: String(cell || ''),
          width: Math.max(10, colW[c] - 2 * pad),
          fontSize: fontSize,
          fontFamily: fontFamily,
          fontStyle: (headerRow && r === 0) ? 'bold' : 'normal',
          lineHeight: lineHeight,
          fill: (headerRow && r === 0) ? headerColor : color,
          align: align,
          listening: false,
        });
        texts.push({ node: t, r: r, c: c });
        hoch = Math.max(hoch, t.height());
      });
      return Math.round(hoch + 2 * pad);
    });

    let y = 0;
    rows.forEach((row, r) => {
      let x = 0;
      /* Kopfzeile zuerst; sonst jede zweite Zeile unterhalb der Kopfzeile. */
      const fuellung = (headerRow && r === 0)
        ? kopfFuellung
        : ((streifenFuellung && (r - (headerRow ? 1 : 0)) % 2 === 1) ? streifenFuellung : null);
      row.forEach((cell, c) => {
        group.add(new Konva.Rect({
          x: x, y: y, width: colW[c], height: rowH[r],
          fill: fuellung || undefined,
          stroke: (borderWidth > 0 && borderColor && borderColor !== 'transparent') ? borderColor : undefined,
          strokeWidth: borderWidth,
          listening: false,
        }));
        x += colW[c];
      });
      y += rowH[r];
    });
    texts.forEach((eintrag) => {
      let x = 0;
      for (let i = 0; i < eintrag.c; i++) x += colW[i];
      let oben = 0;
      for (let i = 0; i < eintrag.r; i++) oben += rowH[i];
      eintrag.node.x(x + pad);
      eintrag.node.y(oben + pad);
      group.add(eintrag.node);
    });

    const gesamtH = rowH.reduce((a, b) => a + b, 0);
    group.setAttr('tableH', gesamtH);
    group.setAttr('tableRowH', rowH);
    /* Trefferfläche, damit die ganze Tabelle anklickbar ist und nicht nur die Linien:
       ein fast durchsichtiges Rechteck zuunterst, dasselbe Mittel wie bei den
       Objektgruppen (`sf-group-hit`). Eine Gruppe kennt kein `hitFunc` — das ist
       Sache einer Form, und der Aufruf warf einen Fehler, bevor die Tabelle
       überhaupt auf die Folie kam (Urs, 24.10.). */
    const treffer = new Konva.Rect({
      name: 'sf-table-hit',
      x: 0,
      y: 0,
      width: Math.max(40, group.getAttr('tableW') || 800),
      height: Math.max(20, gesamtH),
      fill: 'rgba(0,0,0,0.001)',
      listening: true,
    });
    group.add(treffer);
    treffer.moveToBottom();

    /* Spaltenbreite ziehen: über jeder Trennlinie liegt ein schmaler Griff. Gezogen
       wird nur er; die Tabelle selbst wird erst beim Loslassen neu gezeichnet, denn
       `destroyChildren()` würde den Griff mitten im Ziehen zerstören. */
    let grenze = 0;
    for (let i = 0; i < cols - 1; i++) {
      grenze += colW[i];
      const griff = new Konva.Rect({
        name: 'sf-table-col',
        x: grenze - 5,
        y: 0,
        width: 10,
        height: gesamtH,
        fill: 'rgba(0,0,0,0.001)',
        draggable: true,
        dragBoundFunc: function (pos) {
          /* Nur waagrecht: die Höhe bleibt, wo sie beim Anfassen war. */
          const y = this.getAttr('zieheY');
          return { x: pos.x, y: (y === undefined || y === null) ? pos.y : y };
        },
      });
      griff.setAttr('colIndex', i);
      const zeigen = (farbe, zeiger) => {
        griff.fill(farbe);
        const buehne = SF.stage && SF.stage.container();
        if (buehne) buehne.style.cursor = zeiger;
        refreshCanvas();
      };
      griff.on('mouseenter', () => zeigen('rgba(255,255,255,0.28)', 'col-resize'));
      griff.on('mouseleave', () => { if (!griff.isDragging()) zeigen('rgba(0,0,0,0.001)', ''); });
      griff.on('dragstart', () => {
        griff.setAttr('zieheY', griff.absolutePosition().y);
        zeigen('rgba(255,255,255,0.55)', 'col-resize');
      });
      griff.on('dragend', () => {
        const breiten = tableColPixels(group, cols);
        const nr = griff.getAttr('colIndex');
        const links = breiten.slice(0, nr).reduce((a, b) => a + b, 0);
        const rechts = links + breiten[nr] + breiten[nr + 1];
        const mindestens = 24;
        const neu = Math.min(Math.max(griff.x() + 5, links + mindestens), rechts - mindestens);
        breiten[nr] = neu - links;
        breiten[nr + 1] = rechts - neu;
        /* Pixel taugen als Anteile: `tableColPixels()` rechnet sie über ihre Summe
           ohnehin wieder auf die Tabellenbreite um. */
        group.setAttr('tableColWidths', breiten);
        const buehne = SF.stage && SF.stage.container();
        if (buehne) buehne.style.cursor = '';
        drawTable(group);
        refreshCanvas();
        scheduleSave();
      });
      group.add(griff);
    }
    return gesamtH;
  }

  /** Zeitstrahl neu zeichnen (nach jeder Änderung am Modell oder an der Grösse). */
  function drawTimeline(group) {
    window.SfTimeline.draw(group, group.getAttr('tlData'), group.getAttr('tlW') || 1600, group.getAttr('tlH') || 700);
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
      animExit: normExit(obj.animExit) || undefined,
    };
    if (obj.type === 'text') {
      const styleParts = [];
      if (obj.italic) styleParts.push('italic');
      if (obj.fontWeight === 'bold') styleParts.push('bold');
      const decoParts = [];
      if (obj.underline) decoParts.push('underline');
      if (obj.strikethrough) decoParts.push('line-through');
      // SfRichText (3.12.33) zeichnet die Auszeichnung formatiert; ohne sf-markup.js wie bisher Konva.Text.
      const TextClass = SfRichText || Konva.Text;
      const node = new TextClass(Object.assign({}, common, {
        x: obj.x, y: obj.y, width: obj.w,
        text: obj.text || 'Text',
        fontVariant: obj.smallCaps ? 'small-caps' : 'normal',
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
        /* Trefferfläche mindestens 12 Punkte breit: Eine 2 Punkte dünne Linie liesse
           sich sonst kaum anklicken — bei 80 % Zoom sind das keine zwei Bildpunkte.
           Betrifft nur das Anwählen, nicht die Darstellung. */
        hitStrokeWidth: Math.max(12, (isOpen ? (obj.strokeWidth || 3) : (obj.strokeWidth || 0)) + 8),
      }));
      node.setAttr('objType', 'shape');
      node.setAttr('shapeType', shapeType);
      node.setAttr('baseW', w);
      node.setAttr('baseH', h);
      node.setAttr('starPoints', obj.starPoints || 5);
      node.setAttr('arrowStyle', obj.arrowStyle || 'right');
      node.setAttr('arrowHead', obj.arrowHead || 40);
      node.setAttr('arrowShaft', obj.arrowShaft || 30);
      node.setAttr('bracketStyle', obj.bracketStyle || 'curly-left');
      node.setAttr('bubbleStyle', obj.bubbleStyle || 'rect-left');
      if (!isOpen) setShapeFill(node, obj);
      return node;
    }
    if (obj.type === 'table') {
      const group = new Konva.Group(Object.assign({}, common, {
        x: obj.x || 0, y: obj.y || 0,
      }));
      group.setAttr('objType', 'table');
      group.setAttr('tableRows', normalizeTableRows(obj.rows));
      group.setAttr('tableColWidths', Array.isArray(obj.colWidths) ? obj.colWidths.slice() : null);
      group.setAttr('tableW', obj.w || 800);
      group.setAttr('tableH', obj.h || 300);
      Object.keys(TABLE_DEFAULTS).forEach((k) => {
        if (obj[k] !== undefined) group.setAttr(k, obj[k]);
      });
      drawTable(group);
      return group;
    }
    if (obj.type === 'timeline') {
      /* Zeitstrahl (3.12.48): Modell in `tlData`, Zeichnen und Berechnung in sf-timeline.js. */
      const group = new Konva.Group(Object.assign({}, common, {
        x: obj.x || 0, y: obj.y || 0,
      }));
      group.setAttr('objType', 'timeline');
      group.setAttr('tlData', window.SfTimeline.normalize(obj));
      group.setAttr('tlW', Math.max(40, obj.w || 1600));
      group.setAttr('tlH', Math.max(40, obj.h || 700));
      drawTimeline(group);
      return group;
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
        gradAlpha1: gradAlpha(node, 1),
        gradAlpha2: gradAlpha(node, 2),
        gradStops: getGradStops(node),
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
    const baseExit = nodeExit(node);
    if (baseExit) base.animExit = baseExit;
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
    if (type === 'table') {
      const rows = normalizeTableRows(node.getAttr('tableRows'));
      const breite = Math.round((node.getAttr('tableW') || 800) * (node.scaleX() || 1));
      const hoehe = Math.round((node.getAttr('tableH') || 200) * (node.scaleY() || 1));
      const tabelle = Object.assign(base, {
        x: Math.round(node.x()), y: Math.round(node.y()),
        w: breite, h: hoehe,
        rows: rows,
      });
      const spalten = node.getAttr('tableColWidths');
      if (Array.isArray(spalten) && spalten.length === rows[0].length) {
        tabelle.colWidths = spalten.slice();
      }
      Object.keys(TABLE_DEFAULTS).forEach((k) => {
        tabelle[k] = tableAttr(node, k);
      });
      return tabelle;
    }
    if (type === 'timeline') {
      const daten = JSON.parse(JSON.stringify(window.SfTimeline.normalize(node.getAttr('tlData'))));
      return Object.assign(base, {
        x: Math.round(node.x()), y: Math.round(node.y()),
        w: Math.round((node.getAttr('tlW') || 1600) * (node.scaleX() || 1)),
        h: Math.round((node.getAttr('tlH') || 700) * (node.scaleY() || 1)),
      }, daten);
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
        arrowHead: node.getAttr('arrowHead') || 40,
        arrowShaft: node.getAttr('arrowShaft') || 30,
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
    } else if (type === 'table') {
      /* Nur die Breite wird eingebacken, die Höhe ergibt sich aus dem Inhalt —
         sonst sähe die Tabelle nach dem Neuladen anders aus als beim Ziehen, weil
         der Renderer die Zeilen ebenfalls vom Inhalt wachsen lässt. */
      node.setAttr('tableW', Math.max(60, (node.getAttr('tableW') || 800) * node.scaleX()));
      node.scaleX(1); node.scaleY(1);
      drawTable(node);
    } else if (type === 'timeline') {
      node.setAttr('tlW', Math.max(40, (node.getAttr('tlW') || 1600) * node.scaleX()));
      node.setAttr('tlH', Math.max(40, (node.getAttr('tlH') || 700) * node.scaleY()));
      node.scaleX(1); node.scaleY(1);
      drawTimeline(node);
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
    } else if (type === 'timeline') {
      child.setAttr('tlW', Math.max(40, (child.getAttr('tlW') || 1600) * sx));
      child.setAttr('tlH', Math.max(40, (child.getAttr('tlH') || 700) * sy));
      child.scaleX(1); child.scaleY(1);
      drawTimeline(child);
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

  /** Plaketten-Text: «1» nur ein, «1→2» ein und aus, «→2» nur aus. */
  function animBadgeText(node) {
    const ein = hatEinblenden(node) ? String(Math.max(1, parseInt(node.getAttr('animOrder'), 10) || 1)) : '';
    const aus = nodeExit(node);
    if (!ein && !aus) return '';
    return aus ? ein + '→' + aus.order : ein;
  }

  /** Schritt-Abzeichen der Effektliste: Pille je Schritt, «5 → 6» lesbar auch zweistellig (3.12.2). */
  function animBadgeHtml(node) {
    const ein = hatEinblenden(node) ? String(Math.max(1, parseInt(node.getAttr('animOrder'), 10) || 1)) : '';
    const aus = nodeExit(node);
    const titel = animBadgeText(node);
    let html = '<span class="props-effect-order" title="' + escapeHtml(titel) + '">';
    if (ein) html += '<span class="props-effect-pill">' + escapeHtml(ein) + '</span>';
    if (aus) {
      html += '<span class="props-effect-arrow" aria-hidden="true">→</span>'
        + '<span class="props-effect-pill props-effect-pill--out">' + escapeHtml(String(aus.order)) + '</span>';
    }
    return html + '</span>';
  }

  function updateAnimationBadge(node) {
    removeAnimationBadge(node);
    const text = animBadgeText(node);
    if (!text || !SF.layer) return;
    const box = node.getClientRect({ relativeTo: SF.layer });
    /* Pille mit gemessener Textbreite (min. Kreis 22 px, 7 px Rand je Seite) — «12→13» passt (3.12.2). */
    const schrift = new Konva.Text({ x: 0, y: 0, height: 22, text: text, fontSize: 12, fontFamily: 'JetBrains Mono', fill: '#ffffff', verticalAlign: 'middle' });
    const breite = Math.max(22, Math.ceil(schrift.width()) + 14);
    schrift.x(Math.round((breite - schrift.width()) / 2));
    const badge = new Konva.Group({ x: box.x - 11, y: box.y - 11, listening: false, name: 'sf-badge' });
    badge.add(new Konva.Rect({ x: 0, y: 0, width: breite, height: 22, cornerRadius: 11, fill: '#3a6c8d', stroke: '#ffffff', strokeWidth: 1 }));
    badge.add(schrift);
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

  /* Ctrl/Cmd+Klick wählt das Objekt eine Ebene tiefer unter dem Mauszeiger, jeder weitere Ctrl+Klick noch eine tiefer,
     nach dem untersten wieder das oberste (3.12.24, Urs 27.11.: «ein Objekt tiefer wählt, mit dem nächsten Klick noch tiefer»).
     Umschalt+Klick bleibt Mehrfachauswahl. Gesucht wird über die Umrisse (Rahmen) der Objekte. */
  function selectDeeperAtPointer(clicked) {
    const pos = SF.stage && SF.stage.getPointerPosition();
    if (!pos) { selectNodes([clicked]); return; }
    const unter = getTopLevelNodes().filter((n) => {
      if (!n.isVisible || !n.isVisible()) return false;
      const r = n.getClientRect();
      return pos.x >= r.x && pos.x <= r.x + r.width && pos.y >= r.y && pos.y <= r.y + r.height;
    }).sort((a, b) => b.zIndex() - a.zIndex());   // oberstes zuerst
    if (unter.length < 2) { selectNodes([clicked]); return; }
    const jetzt = SF.selectedNodes.length === 1 ? unter.indexOf(SF.selectedNodes[0]) : -1;
    const idx = jetzt >= 0 ? (jetzt + 1) % unter.length : 1;
    const naechstes = unter[idx];
    selectNodes([naechstes]);
    /* 3.12.25 (Urs 27.11.: «… verschiebe ich doch das Objekt darüber und ich kann die Vierecke am Rahmen gar nicht fassen»):
       die Objekte darüber nehmen keine Maus an, solange das tiefere gewählt ist; die nächste andere Auswahl gibt sie frei. */
    const darueber = unter.slice(0, idx);
    darueber.forEach((n) => n.listening(false));
    SF.deepCovers = darueber;
    if (SF.layer) SF.layer.batchDraw();
  }

  SF.deepCovers = [];
  function restoreDeepCovers() {
    if (!SF.deepCovers || !SF.deepCovers.length) return;
    SF.deepCovers.forEach((n) => { try { if (n.getLayer()) n.listening(true); } catch (e) { /* schon weg */ } });
    SF.deepCovers = [];
    if (SF.layer) SF.layer.batchDraw();
  }

  function bindNodeEvents(node) {
    if (!SF.canEdit) return;
    node.on('click tap', (e) => {
      e.cancelBubble = true;
      if (e.evt.shiftKey) toggleSelectNode(node);
      else if (e.evt.ctrlKey || e.evt.metaKey) selectDeeperAtPointer(node);
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
    if (node.getAttr('objType') === 'table') {
      node.on('dblclick dbltap', () => editTableCell(node));
    }
    if (node.getAttr('objType') === 'timeline') {
      node.on('dblclick dbltap', () => openTimelineTab(node));
    }
  }

  /**
   * Zelle einer Tabelle beschriften: Doppelklick öffnet ein Eingabefeld genau über
   * der getroffenen Zelle — dasselbe Vorgehen wie beim Textobjekt, nur dass der Text
   * in die Zelle zurückgeschrieben wird. Tab springt zur nächsten Zelle.
   */
  function editTableCell(group, zeile, spalte) {
    selectNode(group);
    const rows = normalizeTableRows(group.getAttr('tableRows'));
    const cols = rows[0].length;
    const colW = tableColPixels(group, cols);
    const rowH = group.getAttr('tableRowH') || [];
    if (!rowH.length) return;

    let r = zeile, c = spalte;
    if (r === undefined || c === undefined) {
      const p = group.getRelativePointerPosition();
      if (!p) return;
      let x = 0;
      c = 0;
      for (let i = 0; i < cols; i++) {
        if (p.x < x + colW[i] || i === cols - 1) { c = i; break; }
        x += colW[i];
      }
      let y = 0;
      r = 0;
      for (let i = 0; i < rows.length; i++) {
        if (p.y < y + rowH[i] || i === rows.length - 1) { r = i; break; }
        y += rowH[i];
      }
    }
    if (r < 0 || c < 0 || r >= rows.length || c >= cols) return;

    const pad = Math.max(0, Number(tableAttr(group, 'cellPadding')) || 0);
    const fontSize = Math.max(6, Number(tableAttr(group, 'fontSize')) || 32);
    const scale = SF.stage.scaleX();
    const stageBox = SF.stage.container().getBoundingClientRect();
    const basis = group.absolutePosition();
    let offX = 0;
    for (let i = 0; i < c; i++) offX += colW[i];
    let offY = 0;
    for (let i = 0; i < r; i++) offY += rowH[i];

    const textarea = document.createElement('textarea');
    document.body.appendChild(textarea);
    textarea.value = rows[r][c] || '';
    applySpellcheckAttrs(textarea);
    Object.assign(textarea.style, {
      position: 'fixed',
      top: (stageBox.top + basis.y + offY * scale) + 'px',
      left: (stageBox.left + basis.x + offX * scale) + 'px',
      width: (colW[c] * scale) + 'px',
      height: (rowH[r] * scale) + 'px',
      fontSize: (fontSize * scale) + 'px',
      fontFamily: tableAttr(group, 'fontFamily') + ', sans-serif',
      fontWeight: (tableAttr(group, 'headerRow') && r === 0) ? 'bold' : 'normal',
      color: tableAttr(group, 'color'),
      textAlign: tableAttr(group, 'align'),
      lineHeight: String(tableAttr(group, 'lineHeight')),
      border: '1px dashed #87b42b',
      padding: (pad * scale) + 'px',
      margin: '0',
      boxSizing: 'border-box',
      overflow: 'hidden',
      background: 'rgba(0,0,0,0.75)',
      outline: 'none',
      resize: 'none',
      zIndex: 1000,
      whiteSpace: 'pre-wrap',
    });
    if (SF.transformer) SF.transformer.hide();
    refreshCanvas();
    textarea.focus();
    textarea.select();

    let fertig = false;
    function schliessen(speichern, weiterZu) {
      if (fertig) return;
      fertig = true;
      if (speichern) {
        const neu = normalizeTableRows(group.getAttr('tableRows'));
        neu[r][c] = textarea.value;
        group.setAttr('tableRows', neu);
        drawTable(group);
      }
      if (SF.transformer) SF.transformer.show();
      document.body.removeChild(textarea);
      document.removeEventListener('mousedown', aussenKlick);
      refreshCanvas();
      if (speichern) { scheduleSave(); refreshPropsPanel(); }
      if (weiterZu) setTimeout(() => editTableCell(group, weiterZu.r, weiterZu.c), 0);
    }
    function aussenKlick(e) {
      if (e.target !== textarea) schliessen(true);
    }
    textarea.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { e.preventDefault(); schliessen(false); return; }
      if (e.key === 'Tab') {
        e.preventDefault();
        let nr = r, nc = c + (e.shiftKey ? -1 : 1);
        if (nc >= cols) { nc = 0; nr = Math.min(rows.length - 1, r + 1); }
        if (nc < 0) { nc = cols - 1; nr = Math.max(0, r - 1); }
        schliessen(true, { r: nr, c: nc });
      }
    });
    textarea.addEventListener('blur', () => schliessen(true));
    setTimeout(() => document.addEventListener('mousedown', aussenKlick), 0);
  }

  /* Bearbeiten an Ort (Texteditor T3, 3.12.36): contenteditable-Feld mit sichtbarer Formatierung statt
     des Rohtexts. Inhalt SfMarkup.toHtml(text), Grundstil vom Objekt; Masse in Folien-px, das Feld wird
     per transform auf die Bühne skaliert (so bleiben [size=NN]-Angaben im Feld in Folien-px). Enter und
     Einfügen laufen über das Zeilenmodell (SfRichEdit), damit beim Speichern SfMarkup.fromHtml() sauberes
     HTML liest. Esc verwirft, Klick daneben/Verlassen speichert. Ohne sf-markup.js/sf-richedit.js: Rohtext. */
  function editTextInline(node) {
    if (!window.SfMarkup || !window.SfRichEdit) { editTextInlineRaw(node); return; }
    if (SF.inlineEdit) SF.inlineEdit.finish(true);
    selectNode(node);
    const R = window.SfRichEdit;
    const el = document.createElement('div');
    el.className = 'sf-inline-rich';
    el.contentEditable = 'true';
    el.setAttribute('role', 'textbox');
    el.setAttribute('aria-multiline', 'true');
    el.setAttribute('aria-label', I.text || 'Text');
    el.innerHTML = window.SfMarkup.toHtml(node.text());
    applySpellcheckAttrs(el);
    document.body.appendChild(el);

    const base = () => ({ color: node.fill(), size: node.fontSize() });
    function restyle() {
      const scale = SF.stage.scaleX();
      const stageBox = SF.stage.container().getBoundingClientRect();
      const pos = node.absolutePosition();
      const st = String(node.fontStyle() || 'normal');
      const fs = node.fontSize() || 32;
      Object.assign(el.style, {
        top: stageBox.top + pos.y + 'px',
        left: stageBox.left + pos.x + 'px',
        width: Math.max(1, node.width()) + 'px',
        minHeight: Math.max(1, node.height()) + 'px',
        padding: '0',   // SfRichText zeichnet ohne Innenabstand
        fontFamily: sfRichFamily(node.fontFamily()),
        fontSize: fs + 'px',
        fontWeight: /bold|[6-9]00/.test(st) ? 'bold' : 'normal',
        fontStyle: /italic/.test(st) ? 'italic' : 'normal',
        textDecoration: node.textDecoration() || 'none',
        color: node.fill(),
        textAlign: node.align() || 'left',
        lineHeight: String(node.lineHeight() || 1.2),
        letterSpacing: (node.letterSpacing() || 0) + 'px',
        textTransform: node.getAttr('uppercase') ? 'uppercase' : 'none',
        fontVariant: (node.getAttr('smallCaps') || node.fontVariant() === 'small-caps') ? 'small-caps' : 'normal',
        transform: (node.rotation() ? 'rotate(' + node.rotation() + 'deg) ' : '') +
          'scale(' + (scale * node.scaleX()) + ',' + (scale * node.scaleY()) + ')',
      });
    }
    restyle();
    node.hide();
    if (SF.transformer) SF.transformer.hide();
    refreshCanvas();
    el.focus();
    try {
      const rg = document.createRange();
      rg.selectNodeContents(el);
      const sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(rg);
    } catch (e) { /* Markierung ist Komfort */ }

    let done = false;
    let dirty = false;
    const ie = {
      node, el, restyle,
      markDirty() { dirty = true; },
      base,
      /* T4: Format-Bedienelemente, die die Bearbeitung nicht beenden */
      keepZone: (t) => !!(t && t.closest && inlineEditKeepZone(t)),
      finish,
      /** Schalter auf die Markierung; false = keine Markierung (dann wirkt der Knopf aufs ganze Objekt). */
      applyToggle: (key) => applyToSelection((lines, a, b) => R.toggle(lines, a, b, key)),
      /** Wert (color, mark, size) auf die Markierung; gleich dem Grundstil = Angabe entfernen. */
      applyValue(key, val, toggleSame) {
        let v = val;
        if (key === 'color' && v && String(v).toLowerCase() === String(node.fill() || '').toLowerCase()) v = null;
        if (key === 'size' && v && Math.round(v) === Math.round(node.fontSize())) v = null;
        return applyToSelection((lines, a, b) => R.setValue(lines, a, b, key, v, !!toggleSame));
      },
    };
    SF.inlineEdit = ie;
    let saved = null;   // letzte Markierung im Feld {a, b} — bleibt, wenn der Fokus in ein Format-Feld geht

    function applyToSelection(fn) {
      const focused = document.activeElement === el;
      let cur = R.readSelection(el, base());
      if (!cur && saved) cur = { lines: window.SfMarkup.readLines(el, { base: base() }), a: saved.a, b: saved.b };
      if (!cur || R.isEmptyRange(cur.lines, cur.a, cur.b)) return false;
      R.render(el, fn(cur.lines, cur.a, cur.b), cur.a, cur.b, focused);
      saved = { a: cur.a, b: cur.b };
      dirty = true;
      syncInlineState(cur.a, cur.b);
      return true;
    }
    /* Knöpfe zeigen den Zustand an der Schreibmarke (bzw. am Anfang der Markierung). */
    function syncInlineState(a, b) {
      const lines = window.SfMarkup.readLines(el, { base: base() });
      const r = R.propsAt(lines, a, !!b && R.cmp(a, b) !== 0);
      const st = String(node.fontStyle() || '');
      const deco = String(node.textDecoration() || '');
      const on = {
        rb_bold: !!r.b || /bold|[6-9]00/.test(st),
        rb_italic: !!r.i || /italic/.test(st),
        rb_underline: !!r.u || /underline/.test(deco),
        rb_strikethrough: !!r.s || /line-through/.test(deco),
        rb_uppercase: !!r.upper || !!node.getAttr('uppercase'),
        rb_smallcaps: !!r.sc || !!node.getAttr('smallCaps'),
      };
      Object.keys(on).forEach((id) => {
        const b2 = document.getElementById(id);
        if (!b2) return;
        b2.classList.toggle('active', on[id]);
        b2.setAttribute('aria-pressed', on[id] ? 'true' : 'false');
      });
      const sizeEl = document.getElementById('rb_fontsize');
      if (sizeEl && document.activeElement !== sizeEl) sizeEl.value = r.size || Math.round(node.fontSize());
      const colorEl = document.getElementById('rb_color');
      if (colorEl && document.activeElement !== colorEl && /^#[0-9a-f]{6}$/i.test(r.color || node.fill() || '')) colorEl.value = r.color || node.fill();
    }
    function onSelChange() {
      if (done) return;
      const cur = R.readSelection(el, base());
      if (!cur) return;
      saved = { a: cur.a, b: cur.b };
      syncInlineState(cur.a, cur.b);
    }
    document.addEventListener('selectionchange', onSelChange);

    /** Modell-Eingriff an der Markierung: fn(lines, a, b) → {lines, pos} */
    function modelEdit(fn) {
      const cur = R.readSelection(el, base());
      if (!cur) return false;
      const res = fn(cur.lines, cur.a, cur.b);
      if (!res) return false;
      R.render(el, res.lines, res.pos, res.pos, true);
      dirty = true;
      return true;
    }

    function finish(save) {
      if (done) return;
      done = true;
      if (SF.inlineEdit === ie) SF.inlineEdit = null;
      if (save && dirty) node.text(window.SfMarkup.fromHtml(el, { base: base() }));
      node.show();
      if (SF.transformer) SF.transformer.show();
      el.remove();
      document.removeEventListener('mousedown', onOutsideDown, true);
      document.removeEventListener('selectionchange', onSelChange);
      refreshCanvas();
      if (save && dirty) scheduleSave();
      refreshPropsPanel();
      ['rb_bold', 'rb_italic', 'rb_underline', 'rb_strikethrough', 'rb_uppercase', 'rb_smallcaps'].forEach((id) => document.getElementById(id)?.removeAttribute('aria-pressed'));
      syncRibbonStartTab();
    }
    function onOutsideDown(e) {
      if (el.contains(e.target)) return;
      if (ie.keepZone(e.target)) {
        if (e.target.closest(INLINE_EDIT_BTN_SEL)) e.preventDefault();   // Fokus und Markierung bleiben im Feld
        return;
      }
      finish(true);
    }
    el.addEventListener('input', () => { dirty = true; });
    el.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); finish(false); return; }
      if (e.key === 'Enter' && !e.isComposing && !e.ctrlKey && !e.metaKey && !e.altKey) {
        e.preventDefault();
        modelEdit((lines, a, b) => {
          const d = R.deleteRange(lines, a, b);
          return R.splitLine(d.lines, d.pos);
        });
      }
    });
    /* Einfügen nur als reiner Text, Zeilenumbrüche bleiben (neue Zeilen), Format der Schreibmarke. */
    el.addEventListener('paste', (e) => {
      e.preventDefault();
      const txt = (e.clipboardData && e.clipboardData.getData('text/plain')) || '';
      if (!txt) return;
      modelEdit((lines, a, b) => {
        const props = R.propsAt(lines, a);
        const d = R.deleteRange(lines, a, b);
        return R.insertText(d.lines, d.pos, txt, props);
      });
    });
    el.addEventListener('drop', (e) => { e.preventDefault(); });
    el.addEventListener('blur', () => {
      setTimeout(() => {
        if (done) return;
        const ae = document.activeElement;
        if (ae && (ae === el || el.contains(ae) || ie.keepZone(ae))) return;
        if (!document.hasFocus()) return;   // Fenster gewechselt: weiter bearbeiten
        finish(true);
      }, 0);
    });
    setTimeout(() => { if (!done) document.addEventListener('mousedown', onOutsideDown, true); }, 0);
  }

  /* Bedienelemente, die eine laufende Bearbeitung an Ort nicht beenden (Texteditor T4, 3.12.37):
     Menüband «Zeichen»/«Farben» und die Format-Knöpfe der Format-Spalte. Bei den Knöpfen verhindert
     mousedown den Fokuswechsel, damit Fokus und Markierung im Feld bleiben; Felder (Grösse, Farbe)
     bekommen den Fokus, die Markierung merkt sich das Feld (saved). */
  const INLINE_EDIT_BTN_SEL = '#rb_bold, #rb_italic, #rb_underline, #rb_strikethrough, #rb_uppercase, #rb_smallcaps, #rb_mark_clear, ' +
    '.format-toggle-btn[data-wrap], .format-toggle-btn[data-wraptag], #markSelectionBtn, #colorSelectionBtn';
  const INLINE_EDIT_ZONE_SEL = INLINE_EDIT_BTN_SEL + ', #ribbonStartZeichen, #ribbonStartColors, ' +
    '#markSelectionPalette, #colorSelectionPalette';
  function inlineEditKeepZone(t) {
    return !!(t && t.closest && t.closest(INLINE_EDIT_ZONE_SEL));
  }
  /* Run-Schlüssel der Menüband-Knöpfe und der Format-Spalte (data-wrap/-wraptag) */
  const RB_INLINE_KEY = { rb_bold: 'b', rb_italic: 'i', rb_underline: 'u', rb_strikethrough: 's', rb_uppercase: 'upper', rb_smallcaps: 'sc' };
  const WRAP_INLINE_KEY = { '**': 'b', '*': 'i', '++': 'u', '~~': 's', upper: 'upper', sc: 'sc' };

  /* Rohtext-Bearbeitung (bis 3.12.35), nur noch ohne sf-markup.js/sf-richedit.js. */
  function editTextInlineRaw(node) {
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
    table: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="5" width="16" height="14" rx="1.5"/><path d="M4 10h16"/><path d="M4 14.5h16"/><path d="M9.5 10v9"/><path d="M15 10v9"/></svg>',
    rect: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="6" width="16" height="12" rx="1.5"/></svg>',
    timeline: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 12h16"/><path d="M17 9l3 3-3 3"/><circle cx="7" cy="12" r="1.6"/><circle cx="13" cy="12" r="1.6"/><path d="M7 10V5"/><path d="M13 14v5"/></svg>',
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

  /* Kapitaelchen im Canvas sichtbar machen — der HTML-Renderer setzt dafuer
     font-variant:small-caps, Konva kann das ueber fontVariant. Grossschreibung zeichnet
     seit 3.12.33 SfRichText selbst (Attribut uppercase). */
  function refreshTextCase(node) {
    if (!node || typeof node.fontVariant !== "function") return;
    node.fontVariant(node.getAttr("smallCaps") ? "small-caps" : "normal");
    if (SF.layer) SF.layer.batchDraw();
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
      table: I.typeTable,
      timeline: I.typeTimeline,
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
    wrap.classList.remove('side-tab-templates', 'side-tab-format', 'side-tab-position', 'side-tab-effects', 'side-tab-spell', 'side-tab-media', 'side-tab-timeline');
    wrap.classList.add('side-tab-' + (SF.activeSideTab || 'templates'));
    requestAnimationFrame(updatePropsSidebarOverflow);
  }

  function setSideTab(tabId, opts) {
    const tabs = document.querySelectorAll('#propsSideTabs [data-side-tab]');
    const panels = document.querySelectorAll('#propsSidePanels [data-side-panel]');
    if (!tabs.length) return;
    /* Ausgeblendete Reiter (Zeitstrahl ohne markierten Zeitstrahl) sind nicht wählbar. */
    const available = [...tabs].filter((t) => !t.hidden).map((t) => t.dataset.sideTab);
    let next = tabId;
    if (!available.includes(next)) next = available[0] || 'templates';
    SF.activeSideTab = next;
    if (!opts || opts.persist !== false) {
      persistEditorUi({ sideTab: next });
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
    if (next === 'clipboard' && typeof refreshClipboard === 'function') {
      // Liste erst beim Öffnen holen, nicht bei jedem Seitenaufbau.
      refreshClipboard();
    }
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
      persistEditorUi({ posSubtab: next });
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
      persistEditorUi({ formatSubtab: next });
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
      persistEditorUi({ objectOpen: SF.objectPanelOpen });
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
    restoreDeepCovers();
    /* Rahmen mit Ziehpunkten immer zuoberst (3.12.26, Urs 27.11.: «wenn ich das Objekt vergrössern möchte, verschiebe ich das Objekt»):
       nach Ebenen-Änderungen (Vordergrund, Ebenen-Liste) lag ein Objekt über dem Rahmen und fing die Ziehpunkte ab. */
    if (SF.transformer) SF.transformer.moveToTop();
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
    restoreDeepCovers();
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
    const hasMultiTopLevel = SF.selectedNodes.filter(isTopLevelNode).length >= 2 && SF.canEdit;
    const canUngroup = count === 1 && isObjectGroup(SF.selectedNodes[0]) && SF.canEdit;
    const hasAny = count >= 1 && SF.canEdit;   // 3.12.17: auch mehrere Objekte
    setRibbonCommandsDisabled('duplicate', !hasAny);
    setRibbonCommandsDisabled('copy', !hasAny);
    setRibbonCommandsDisabled('cut', !hasAny);
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

  /* Auszeichnung (z. B. mark) auf den ganzen Text eines Textobjekts; null entfernt sie (3.12.49). */
  function setTextObjectRunValue(node, key, val) {
    const M = window.SfMarkup, R = window.SfRichEdit;
    if (!M || !R || !M.readLines || !R.lineLen) return false;
    const lines = M.readLines(M.toHtml(String(node.text() || '')), { base: { color: node.fill(), size: node.fontSize() } });
    if (!lines.length) return false;
    const a = { l: 0, ch: 0 };
    const b = { l: lines.length - 1, ch: R.lineLen(lines[lines.length - 1]) };
    if (R.isEmptyRange(lines, a, b)) return false;
    node.text(M.linesToMarkup(R.setValue(lines, a, b, key, val, false)));
    refreshCanvas();
    scheduleSave();
    refreshPropsPanel();
    return true;
  }

  function ribbonStartTextNode() {
    if (SF.selectedNodes.length !== 1 || !SF.selectedNode) return null;
    const type = SF.selectedNode.getAttr('objType');
    return type === 'text' ? SF.selectedNode : null;
  }

  function setRibbonStartEnabled(enabled) {
    const ids = ['rb_bold', 'rb_italic', 'rb_underline', 'rb_strikethrough', 'rb_uppercase', 'rb_smallcaps',
      'rb_font', 'rb_fontsize', 'rb_lineheight', 'rb_letterspacing', 'rb_color', 'rb_color_apply', 'rb_mark', 'rb_mark_apply', 'rb_mark_clear', 'rb_opacity',
      'rb_bullet', 'rb_number', 'rb_align_left', 'rb_align_center', 'rb_align_right'];
    ids.forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.disabled = !enabled;
    });
    setRibbonTemplateControlsEnabled(enabled);
    if (!enabled) {
      closeRibbonTemplateDropdown();
    }
  }

  function syncRibbonStartTab() {
    if (notesEd.active) { syncNotesFormatState(notesSelection()); return; }   // 3.12.49: Menüband gehört den Notizen
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
    if (fontEl) {
      if (window.SfFontSelect) window.SfFontSelect.setValue(fontEl, obj.fontFamily || 'Open Sans');
      else fontEl.value = obj.fontFamily || 'Open Sans';
    }
    const sizeEl = document.getElementById('rb_fontsize');
    if (sizeEl) sizeEl.value = obj.fontSize || 32;
    const lhEl = document.getElementById('rb_lineheight');
    if (lhEl) lhEl.value = obj.lineHeight || 1.2;
    const lsEl = document.getElementById('rb_letterspacing');
    if (lsEl) lsEl.value = obj.letterSpacing ?? 0;
    const colorEl = document.getElementById('rb_color');
    if (colorEl) colorEl.value = obj.color || '#ffffff';
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
    // 3.9.10: durchsuchbare Schriftwahl; das <select> bleibt versteckt die Quelle des Werts (change-Handler unten unverändert).
    if (window.SfFontSelect) {
      window.SfFontSelect.attach(fontEl, {
        families: FONT_OPTIONS,
        hub: Array.isArray(boot.fontHubFamilies) ? boot.fontHubFamilies : [],
        user: boot.fontRecentUser || '',
      });
    }

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
        /* 3.12.49: Notizfeld aktiv → auf die Markierung der Notizen (ohne Markierung zurück ins Feld). */
        if (notesEd.active && RB_INLINE_KEY[id]) {
          if (!notesToggle(RB_INLINE_KEY[id])) notesField()?.focus();
          return;
        }
        const node = ribbonStartTextNode();
        if (!node) return;
        /* T4: Bearbeiten an Ort mit Markierung → nur die Markierung; sonst wie bisher das ganze Objekt. */
        const ie = SF.inlineEdit && SF.inlineEdit.node === node ? SF.inlineEdit : null;
        if (ie && RB_INLINE_KEY[id] && ie.applyToggle(RB_INLINE_KEY[id])) return;
        const next = el.dataset.checked !== '1';
        el.dataset.checked = next ? '1' : '0';
        el.classList.toggle('active', next);
        onToggle(node, next);
        if (ie) ie.restyle();
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
    bindRbToggle('rb_smallcaps', (node, state) => { node.setAttr('smallCaps', state); refreshTextCase(node); scheduleSave(); });

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
      loadFontFamily(e.target.value).then(() => refreshCanvas());   // Schrift erst bei Wahl laden, dann neu zeichnen
    });
    document.getElementById('rb_fontsize')?.addEventListener('input', (e) => {
      const node = ribbonStartTextNode();
      if (!node) return;
      const ie = SF.inlineEdit && SF.inlineEdit.node === node ? SF.inlineEdit : null;
      const want = parseInt(e.target.value, 10);
      if (ie && want > 0 && ie.applyValue('size', Math.max(6, Math.min(400, want)))) return;   // T4: nur die Markierung
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
      if (notesEd.active) {   // 3.12.49: Schriftfarbe auf die gemerkte Markierung der Notizen
        if (/^#[0-9a-f]{6}$/i.test(e.target.value)) notesSetValue('color', e.target.value.toLowerCase());
        return;
      }
      const node = ribbonStartTextNode();
      if (!node) return;
      const ie = SF.inlineEdit && SF.inlineEdit.node === node ? SF.inlineEdit : null;
      if (ie && /^#[0-9a-f]{6}$/i.test(e.target.value) && ie.applyValue('color', e.target.value.toLowerCase())) return;   // T4
      node.fill(e.target.value);
      refreshCanvas();
      scheduleSave();
    });
    /* Markerfarbe (3.12.49): Notizfeld bzw. Bearbeiten an Ort → Markierung; nur Objekt gewählt → ganzer Text.
       Beim Bearbeiten an Ort ohne Markierung bleibt es still (der offene Editor würde den ganzen Text überschreiben). */
    const applyRbMark = (val) => {
      if (notesEd.active) {
        if (!notesSetValue('mark', val)) notesField()?.focus();
        return;
      }
      const node = ribbonStartTextNode();
      if (!node) return;
      const ie = SF.inlineEdit && SF.inlineEdit.node === node ? SF.inlineEdit : null;
      if (ie) { ie.applyValue('mark', val); return; }
      setTextObjectRunValue(node, 'mark', val);
    };
    document.getElementById('rb_mark')?.addEventListener('input', (e) => {
      if (/^#[0-9a-f]{6}$/i.test(e.target.value)) applyRbMark(e.target.value.toLowerCase());
    });
    document.getElementById('rb_mark_clear')?.addEventListener('click', () => applyRbMark(null));
    /* 3.12.52 (Urs 27.11.): «A»/Marker wenden die zuletzt gewählte Farbe an. Gemerkt wird jede Wahl im Farbfeld
       (auch Markenpunkte); das Nachführen an der Schreibmarke setzt nur value ohne Ereignis und zählt nicht. */
    const rbLastPick = { rb_color: null, rb_mark: '#fff176' };
    const rbShowPick = (id) => {
      const btn = document.querySelector('.ribbon-color-apply[data-apply-for="' + id + '"]');
      const val = rbLastPick[id] || document.getElementById(id)?.value || '';
      if (btn && /^#[0-9a-f]{6}$/i.test(val)) btn.style.setProperty('--apply-color', val);
    };
    ['rb_color', 'rb_mark'].forEach((id) => {
      const inp = document.getElementById(id);
      inp?.addEventListener('input', (e) => {
        if (/^#[0-9a-f]{6}$/i.test(inp.value)) { rbLastPick[id] = inp.value.toLowerCase(); rbShowPick(id); }
      });
      rbShowPick(id);
    });
    document.querySelectorAll('.ribbon-color-apply[data-apply-for]').forEach((btn) => {
      btn.addEventListener('mousedown', (e) => e.preventDefault());   // Markierung im Text/Notizfeld behalten
      btn.addEventListener('click', () => {
        const id = btn.dataset.applyFor;
        const inp = document.getElementById(id);
        const val = rbLastPick[id] || inp?.value;
        if (!inp || !/^#[0-9a-f]{6}$/i.test(val || '')) return;
        inp.value = val;
        inp.dispatchEvent(new Event('input', { bubbles: true }));
      });
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

  function updateObjectGradientPreview(node) {
    const angle = parseInt(document.getElementById('objGradAngle')?.value, 10) || 0;
    const preview = document.getElementById('objectGradientPreview');
    const label = document.getElementById('objGradAngleLabel');
    const n = node || ribbonObjectColorNode();
    if (preview && n && (n.getAttr('fillType') === 'gradient')) preview.style.background = gradCss(getGradStops(n), angle);
    if (label) label.textContent = (I.angle || 'Winkel') + ' (' + angle + '°)';
  }

  /* Liste der Farbpunkte zeichnen (nur bei Objektwechsel, Hinzufügen, Entfernen — nicht bei jeder Eingabe, sonst springt der Regler). */
  function renderGradStops(node, enabled) {
    const box = document.getElementById('objGradStops');
    const add = document.getElementById('objGradAddStop');
    if (add) add.disabled = !enabled || !node || getGradStops(node).length >= 12;
    if (!box) return;
    if (!node || !enabled) { box.innerHTML = ''; return; }
    const stops = getGradStops(node);
    const tAlpha = escapeHtml(I.gradAlpha || 'Deckkraft');
    const tPos = escapeHtml(I.gradPos || 'Position');
    box.innerHTML = stops.map((s, i) => {
      const name = escapeHtml((I.gradColorN || 'Farbe {n}').replace('{n}', String(i + 1)));
      return '<div class="sf-grad-stop" data-i="' + i + '">'
        + '<div class="sf-grad-stop-head"><span>' + name + '</span>'
        + '<label class="ribbon-color-picker-wrap ribbon-object-grad-picker"><span class="sr-only">' + name + '</span>'
        + '<input type="color" data-k="c" value="' + escapeHtml(s.c) + '" title="' + name + '"></label>'
        + (stops.length > 2 ? '<button type="button" class="hub-icon-btn sf-grad-del" data-del="' + i + '" title="' + escapeHtml(I.gradRemove || 'Farbe entfernen')
          + '" aria-label="' + escapeHtml(I.gradRemove || 'Farbe entfernen') + '">' + ICON_TRASH + '</button>' : '')
        + '</div>'
        + '<span class="sf-grad-sub">' + tAlpha + '</span>'
        + '<input type="range" data-k="a" min="0" max="100" step="1" value="' + s.a + '" data-hub-range data-hub-range-unit="%" aria-label="' + name + ': ' + tAlpha + '">'
        + '<span class="sf-grad-sub" title="' + escapeHtml(I.gradPosHint || '') + '">' + tPos + '</span>'
        + '<input type="range" data-k="p" min="0" max="100" step="1" value="' + s.p + '" data-hub-range data-hub-range-unit="%" aria-label="' + name + ': ' + tPos + '" title="' + escapeHtml(I.gradPosHint || '') + '">'
        + '</div>';
    }).join('');
  }

  function gradStopsChanged(node) {
    applyShapeGradientVisual(node);
    updateObjectGradientPreview(node);
    refreshCanvas();
    scheduleSave();
  }

  (function bindGradStops() {
    const box = document.getElementById('objGradStops');
    const add = document.getElementById('objGradAddStop');
    if (box) {
      box.addEventListener('input', (e) => {
        const el = e.target;
        const row = el.closest && el.closest('.sf-grad-stop');
        const node = ribbonObjectColorNode();
        if (!row || !el.dataset.k || !node || !shapeSupportsRibbonFill(node)) return;
        const stops = getGradStops(node);
        const s = stops[parseInt(row.dataset.i, 10)];
        if (!s) return;
        if (el.dataset.k === 'c') s.c = el.value;
        else s[el.dataset.k] = Math.max(0, Math.min(100, parseInt(el.value, 10) || 0));
        setGradStops(node, stops);
        gradStopsChanged(node);
      });
      box.addEventListener('click', (e) => {
        const del = e.target.closest && e.target.closest('[data-del]');
        const node = ribbonObjectColorNode();
        if (!del || !node) return;
        const stops = getGradStops(node);
        if (stops.length <= 2) return;
        stops.splice(parseInt(del.dataset.del, 10), 1);
        setGradStops(node, stops);
        gradStopsChanged(node);
        renderGradStops(node, true);
      });
    }
    add?.addEventListener('click', () => {
      const node = ribbonObjectColorNode();
      if (!node || !shapeSupportsRibbonFill(node)) return;
      const stops = getGradStops(node);
      if (stops.length >= 12) return;
      /* Neuer Punkt in der Mitte der grössten Lücke, mit der Farbe des Punkts davor. */
      const sorted = stops.slice().sort((x, y) => x.p - y.p);
      let best = 0, at = 50, col = sorted[0].c;
      for (let i = 0; i < sorted.length - 1; i++) {
        const gap = sorted[i + 1].p - sorted[i].p;
        if (gap > best) { best = gap; at = Math.round((sorted[i].p + sorted[i + 1].p) / 2); col = sorted[i].c; }
      }
      stops.push({ c: col, a: 100, p: at });
      setGradStops(node, stops);
      gradStopsChanged(node);
      renderGradStops(node, true);
    });
  })();

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

    const angle = document.getElementById('objGradAngle');
    if (angle) angle.disabled = !isGrad;
    if (isGrad && node && angle) angle.value = String(node.getAttr('gradAngle') !== undefined ? node.getAttr('gradAngle') : 90);
    /* Liste nur neu zeichnen, wenn ein anderes Objekt gewählt ist oder der Modus wechselt (sonst springt der Regler beim Ziehen). */
    const schluessel = isGrad && node ? node.id() + ':' + getGradStops(node).length : '';
    const box = document.getElementById('objGradStops');
    if (box && box.dataset.for !== schluessel) { renderGradStops(node, isGrad); box.dataset.for = schluessel; }
    else { const add = document.getElementById('objGradAddStop'); if (add) add.disabled = !isGrad || getGradStops(node).length >= 12; }
    updateObjectGradientPreview();
  }

  function applyObjectGradientFromEditor(node) {
    if (!node) return;
    const angle = parseInt(document.getElementById('objGradAngle')?.value, 10) || 0;
    node.setAttr('gradAngle', angle);
    applyShapeGradientVisual(node);
    updateObjectGradientPreview(node);
    refreshCanvas();
    scheduleSave();
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
    }
    const strokeVal = (obj.stroke && obj.stroke !== 'transparent') ? obj.stroke : '#ffffff';
    const strokeEl = document.getElementById('rb_objStroke');
    if (strokeEl) strokeEl.value = strokeVal;
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
      const angleEl = document.getElementById('objGradAngle');
      if (angleEl) angleEl.value = String(angle);
      applyObjectFillGradient(node, c1, c2, angle);
    });
    document.getElementById('rb_objStroke')?.addEventListener('input', (e) => {
      const node = ribbonObjectColorNode();
      if (!node) return;
      node.stroke(e.target.value);
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

    ['objGradAngle'].forEach((id) => {
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
      renderTimelinePanel(null);
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
    html += '<button type="button" class="button button-ghost button-sm hub-btn hub-btn--ghost hub-btn--sm" id="refreshSetPlaceholderBtn" style="width:100%; margin-top:8px;"' +
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
        /* Spitze und Schaft in einer Zeile über die volle Breite (3.12.13, Urs 27.11.: «Mach die Regler in einer Zeile»). */
        html += '<div class="sf-arrow-sliders">'
          + fieldArrowRange('p_arrowHead', I.arrowHead || 'Spitze', 5, 95, obj.arrowHead || 40)
          + fieldArrowRange('p_arrowShaft', I.arrowShaft || 'Schaft', 5, 80, obj.arrowShaft || 30)
          + '</div>';
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
      }
      html += '<button type="button" class="button button-ghost button-sm hub-btn hub-btn--ghost hub-btn--sm" id="replaceAssetBtn" style="width:100%;">' + I.replaceImage + '</button>';
      html += '<button type="button" class="button button-ghost button-sm hub-btn hub-btn--ghost hub-btn--sm" id="removeBgBtn" style="width:100%; margin-top:8px;">' + I.removeBackground + '</button>';
      html += '<div class="props-video-note" style="margin-top:8px;">' + I.removeBackgroundHint + '</div>';
      return html;
    }

    if (obj.type === 'table') {
      const zeilen = obj.rows.length;
      const spalten = obj.rows[0] ? obj.rows[0].length : 0;
      const prozent = (v) => Math.round(Math.max(0, Math.min(1, Number(v ?? 1))) * 100);
      /* Ein Farbfeld kann kein «durchsichtig» anzeigen: dann steht die erste
         Markenfarbe drin, und die Füllstärke entscheidet, ob man sie sieht. */
      const farbwert = (v) => (!v || v === 'transparent') ? defaultIconColor() : v;

      html += '<div class="props-video-note">' + escapeHtml((I.tableSizeHint || '{r} × {c}').replace('{r}', String(zeilen)).replace('{c}', String(spalten))) + '</div>';
      html += '<div style="display:flex; gap:6px; margin-top:8px;">'
        + tableBtn('tblRowAdd', I.tableRowAdd || '+ Zeile')
        + tableBtn('tblRowDel', I.tableRowDel || '− Zeile')
        + '</div>';
      html += '<div style="display:flex; gap:6px; margin-top:6px;">'
        + tableBtn('tblColAdd', I.tableColAdd || '+ Spalte')
        + tableBtn('tblColDel', I.tableColDel || '− Spalte')
        + '</div>';

      html += tableHead(I.tableSectionText || 'Text');
      html += fieldNumber('p_tblFontSize', escapeHtml(I.tableFontSize || 'Schriftgrösse'), obj.fontSize || 44);
      html += tableColorField('p_tblColor', I.tableTextColor || 'Textfarbe', obj.color || '#ffffff');

      html += tableHead(I.tableSectionHeader || 'Kopfzeile');
      html += tableCheck('p_tblHeader', I.tableHeaderRow || 'Kopfzeile hervorheben', obj.headerRow);
      if (obj.headerRow) {
        html += tableColorField('p_tblHeaderColor', I.tableHeaderColor || 'Schriftfarbe', obj.headerColor || obj.color || '#ffffff');
        html += tableColorField('p_tblHeaderFill', I.tableHeaderFill || 'Hintergrund', farbwert(obj.headerFill));
        html += fieldRange('p_tblHeaderFillOpacity', escapeHtml(I.tableFillStrength || 'Füllstärke'),
          (!obj.headerFill || obj.headerFill === 'transparent') ? 0 : prozent(obj.headerFillOpacity));
      }

      html += tableHead(I.tableSectionStripes || 'Zeilen abwechselnd');
      html += tableCheck('p_tblStripe', I.tableStripeRows || 'Zeilen abwechselnd färben', obj.stripeRows);
      if (obj.stripeRows) {
        html += tableColorField('p_tblStripeFill', I.tableStripeColor || 'Farbe', farbwert(obj.stripeFill));
        html += fieldRange('p_tblStripeOpacity', escapeHtml(I.tableFillStrength || 'Füllstärke'), prozent(obj.stripeOpacity));
      }

      html += tableHead(I.tableSectionBorder || 'Rahmen');
      html += tableColorField('p_tblBorderColor', I.tableBorderColor || 'Rahmenfarbe', obj.borderColor || '#ffffff');
      html += fieldNumber('p_tblBorderWidth', escapeHtml(I.tableBorderWidth || 'Rahmenstärke'), obj.borderWidth === undefined ? 2 : obj.borderWidth);

      html += '<div class="props-video-note" style="margin-top:10px;">' + escapeHtml(I.tableEditHint || 'Doppelklick in eine Zelle zum Schreiben, Tab springt zur nächsten.')
        + ' ' + escapeHtml(I.tableColDragHint || 'Die Linie zwischen zwei Spalten ziehen ändert die Spaltenbreite.') + '</div>';
      /* Eine eigene Hülle: in der schmalen Seitenleiste stünden Beschriftungen sonst
         mittig und die Felder halb so breit wie der Platz. */
      return '<div class="props-table-fields">' + html + '</div>';
    }

    if (obj.type === 'timeline') {
      html += '<button type="button" class="button button-ghost button-sm hub-btn hub-btn--ghost hub-btn--sm" id="tlOpenTabBtn" style="width:100%;" title="'
        + escapeHtml((I.tl && I.tl.openHint) || '') + '">' + escapeHtml((I.tl && I.tl.open) || 'Zeitstrahl bearbeiten') + '</button>';
      return html;
    }

    if (obj.type === 'video' || obj.type === 'audio') {
      const isAudio = obj.type === 'audio';
      html += '<div class="props-video-note">' + I.mediaPlaceholderHint.replace('{type}', isAudio ? I.mediaAudio : I.mediaVideo) + '</div>';
      html += '<button type="button" class="button button-ghost button-sm hub-btn hub-btn--ghost hub-btn--sm" id="replaceAssetBtn" style="width:100%;">' + (isAudio ? I.replaceAudio : I.replaceVideo) + '</button>';
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
      '<input type="number" id="' + id + '" value="' + value + '" aria-label="' + escapeHtml(label) + '">' +
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
      '<input type="range" id="p_opacity" min="0" max="100" value="' + Math.round((obj.opacity || 1) * 100) + '" data-hub-range data-hub-range-unit="%" aria-label="%">' +
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

  /** Schritt-Nummer mit «Mit vorherigem» / «Nach vorherigem». */
  function effektSchrittHtml(id, wert, texte) {
    const t = texte || {};
    const mitTitel = t.mitTitel || I.animWithPrevHint || 'Gleichzeitig mit dem letzten Schritt dieser Folie';
    const nachTitel = t.nachTitel || I.animAfterPrevHint || 'Ein Klick nach dem letzten Schritt dieser Folie';
    /* ‹ Nummer › über den Hub-Baustein HubNumberStep (3.12.14, vorher Eigenbau 3.12.11). */
    return '<div><label for="' + id + '">' + escapeHtml(I.animStep || 'Schritt (Klick-Nr.)') + '</label>'
      + '<input type="number" id="' + id + '" min="1" step="1" value="' + (parseInt(wert, 10) || 1) + '" data-hub-step aria-label="'
      + escapeHtml(I.animStep || 'Schritt (Klick-Nr.)') + '"></div>'
      + '<div class="props-transfer-row props-fx-row">'
      + '<button type="button" class="button button-ghost button-sm hub-btn hub-btn--ghost hub-btn--sm" id="' + id + '_mit" title="'
      + escapeHtml(mitTitel) + '">' + hubSpriteIcon('link') + '<span>' + escapeHtml(t.mit || I.animWithPrev || 'Mit vorherigem') + '</span></button>'
      + '<button type="button" class="button button-ghost button-sm hub-btn hub-btn--ghost hub-btn--sm" id="' + id + '_nach" title="'
      + escapeHtml(nachTitel) + '">' + hubSpriteIcon('play') + '<span>' + escapeHtml(t.nach || I.animAfterPrev || 'Nach vorherigem') + '</span></button>'
      + '</div>';
  }


  /** Abschnitt «Ausblenden» im Effekte-Reiter. */
  function buildExitPropsHtml(node) {
    const ex = nodeExit(node);
    let html = fieldIconPicker('p_exit', escapeHtml(I.exitEffect || 'Effekt'), EXIT_ANIMATIONS, ex ? ex.type : 'none', 'effect-icon-grid anim-icon-grid');
    if (ex) {
      /* Ausblenden (Urs 27.11.: «müsste das hier nicht ‹mit nächstem› sein?»): «Mit nächstem» = zusammen mit dem nächsten
         Einblenden eines anderen Objekts (tauscht beide mit einem Klick), «Beim nächsten Klick» = ein Klick nach dem eigenen Einblenden. */
      html += effektSchrittHtml('p_exitOrder', ex.order, {
        mit: 'Mit nächstem',
        mitTitel: 'Gleichzeitig mit dem nächsten Objekt, das danach eingeblendet wird',
        nach: 'Beim nächsten Klick',
        nachTitel: 'Ein Klick nach dem eigenen Einblenden',
      });
      html += fxSelectPairHtml(
        { id: 'p_exitDuration', icon: 'clock', label: I.animDuration, options: ANIM_DURATION_OPTIONS, value: ex.duration },
        { id: 'p_exitAuto', icon: 'refresh', label: I.animAutostart, options: ANIM_AUTOSTART_OPTIONS, value: ex.auto }
      );
      html += '<div class="props-video-note">' + escapeHtml(I.exitHint
        || 'Das Objekt verschwindet bei diesem Schritt. Hat ein anderes Objekt dieselbe Nummer beim Einblenden, tauscht ein Klick die beiden.') + '</div>';
    }
    return html;
  }

  function buildEffectPropsHtml(node, obj, isText) {
    let html = fieldIconPicker('p_anim', I.effect, ANIMATIONS, obj.animType || 'none', 'effect-icon-grid anim-icon-grid');
    html += propsTransferRowHtml('copyAnimationBtn', 'pasteAnimationBtn', I.animationCopy, I.animationPaste, !!SF.animationClipboard, ['files', 'inbox']);
    if ((obj.animType || 'none') !== 'none') {
      html += effektSchrittHtml('p_animOrder', obj.animOrder || 1);
      html += fxSelectPairHtml(
        { id: 'p_animDuration', icon: 'clock', label: I.animDuration, options: ANIM_DURATION_OPTIONS, value: obj.animDuration || 0 },
        { id: 'p_animAuto', icon: 'refresh', label: I.animAutostart, options: ANIM_AUTOSTART_OPTIONS, value: obj.animAutoAdvance || 0 }
      );
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

  /** Alle Objekte der Folie, auch die in Gruppen — ein Effekt kann überall hängen. */
  function animierbareKnoten() {
    const liste = [];
    getTopLevelNodes().forEach((n) => {
      liste.push(n);
      if (isObjectGroup(n)) getGroupContentChildren(n).forEach((k) => liste.push(k));
    });
    return liste;
  }

  function knotenMitEffekt() {
    return animierbareKnoten().filter((n) => hatEinblenden(n) || !!nodeExit(n));
  }

  /**
   * Höchster Schritt der Folie (Einblenden und Ausblenden). Der eigene Wert des
   * gerade bearbeiteten Feldes zählt nicht mit, sonst zählte «nach vorherigem»
   * bei jedem Klick weiter. Beim Einblenden zählt auch das eigene Ausblenden nicht.
   */
  function hoechsterSchritt(node, feld) {
    let max = 0;
    animierbareKnoten().forEach((n) => {
      const ex = nodeExit(n);
      if (n !== node || feld === 'exit') {
        if (hatEinblenden(n) && !(n === node && feld === 'in')) {
          max = Math.max(max, parseInt(n.getAttr('animOrder'), 10) || 1);
        }
      }
      if (ex && n !== node) max = Math.max(max, ex.order);
    });
    return max;
  }

  /** Schritt des eigenen Einblendens (ohne Einblenden: höchster Schritt der übrigen Objekte). */
  function eigenerEinSchritt(node) {
    return hatEinblenden(node) ? (parseInt(node.getAttr('animOrder'), 10) || 1) : hoechsterSchritt(node, 'exit');
  }

  /** Kleinster Einblende-Schritt eines anderen Objekts nach dem eigenen Einblenden; sonst eigener Schritt + 1. */
  function naechsterEinSchritt(node) {
    const eigen = eigenerEinSchritt(node);
    let best = 0;
    animierbareKnoten().forEach((n) => {
      if (n === node || !hatEinblenden(n)) return;
      const o = parseInt(n.getAttr('animOrder'), 10) || 1;
      if (o > eigen && (!best || o < best)) best = o;
    });
    return best || eigen + 1;
  }

  /** Ausblenden kommt nach dem eigenen Einblenden (mind. ein Schritt später). */
  function exitNachEinblenden(node) {
    const ex = nodeExit(node);
    if (!ex || !hatEinblenden(node)) return;
    const ein = parseInt(node.getAttr('animOrder'), 10) || 1;
    if (ex.order <= ein) node.setAttr('animExit', Object.assign({}, ex, { order: ein + 1 }));
  }

  const ICON_TRASH = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16"/><path d="M9 7V5h6v2"/><path d="M6 7l1 13h10l1-13"/><path d="M10 11v6M14 11v6"/></svg>';

  /** Die Effekte der Folie in der Reihenfolge, in der sie erscheinen. */
  function effekteDerFolie() {
    const alle = animierbareKnoten();
    return alle
      .map((n, i) => {
        const ex = nodeExit(n);
        const nurAus = !hatEinblenden(n) && !!ex;
        return { node: n, nurAus: nurAus, nr: nurAus ? ex.order : Math.max(1, parseInt(n.getAttr('animOrder'), 10) || 1), tiefe: i };
      })
      .filter((e) => hatEinblenden(e.node) || e.nurAus)
      /* Gleiche Nummer = gleichzeitig; dann entscheidet die Lage im Stapel. */
      .sort((a, b) => (a.nr - b.nr) || (a.tiefe - b.tiefe));
  }

  function animationLabel(typ) {
    const eintrag = ANIMATIONS.find((a) => a.value === typ);
    return (eintrag && eintrag.label) || typ || '';
  }

  function exitLabel(typ) {
    const eintrag = EXIT_ANIMATIONS.find((a) => a.value === typ);
    return (eintrag && eintrag.label) || typ || '';
  }

  /** Effekt-Beschreibung für die Liste: «Einblenden → Ausblenden». */
  function effektBeschreibung(node) {
    const teile = [];
    if (hatEinblenden(node)) teile.push(animationLabel(node.getAttr('animType')));
    const ex = nodeExit(node);
    if (ex) teile.push(exitLabel(ex.type));
    return teile.join(' → ');
  }

  function findeAnimKnoten(id) {
    return animierbareKnoten().find((n) => n.id() === id) || null;
  }

  function effektWeg(node) {
    ['animType', 'animOrder', 'animDuration', 'animAutoAdvance', 'animPerLine', 'animExit'].forEach((k) => node.setAttr(k, undefined));
    updateAnimationBadge(node);
  }

  /**
   * Was im Effekte-Bereich steht, wenn **nichts** ausgewählt ist (Urs, 24.10.2026):
   * die Liste aller Effekte der Folie — jeder einzeln löschbar, die Reihenfolge
   * mit Ziehen änderbar — und darunter der Schalter, der alle wegnimmt. Der
   * Folien-Übergang gehört nicht dazu, der sitzt im Menüband.
   */
  function buildSlideEffectsHtml() {
    const eintraege = effekteDerFolie();
    const anzahl = eintraege.length;
    let body = '';

    if (!anzahl) {
      body += '<div class="props-video-note">' + escapeHtml(I.effectsClearNone || 'Kein Objekt dieser Folie hat einen Effekt.') + '</div>';
    } else {
      body += '<ul class="props-effects-list">';
      /* Auf der Folie gewählte Objekte (auch Teile einer gewählten Gruppe) sind in der Liste markiert (Urs 27.11.: «Wenn angewählt, markier das hier auch»). */
      const gewaehlt = (n) => {
        for (let p = n; p && p.getParent; p = p.getParent()) if (SF.selectedNodes.includes(p)) return true;
        return false;
      };
      eintraege.forEach((e) => {
        const an = gewaehlt(e.node);
        body += '<li' + (an ? ' class="active" aria-current="true"' : '') + ' data-effect-id="' + escapeHtml(e.node.id()) + '" data-anim-order="' + e.nr + '" tabindex="0" title="'
          + escapeHtml(I.effectsRowOpen || 'Klick: Objekt wählen · Doppelklick: Effekt bearbeiten') + '">'
          + '<span class="props-layer-drag-handle" draggable="true" title="'
          + escapeHtml(I.effectsListDrag || 'Ziehen ändert die Reihenfolge') + '" aria-hidden="true">⠿</span>'
          + animBadgeHtml(e.node)
          + '<span class="props-effect-text">'
          + '<span class="props-effect-name">' + escapeHtml(layerItemLabel(e.node)) + '</span>'
          + '<span class="props-effect-anim">' + escapeHtml(effektBeschreibung(e.node)) + '</span>'
          + '</span>'
          + '<button type="button" class="props-effect-del" data-effect-id="' + escapeHtml(e.node.id()) + '" title="'
          + escapeHtml(I.effectsRowDelete || 'Diesen Effekt löschen') + '" aria-label="'
          + escapeHtml(I.effectsRowDelete || 'Diesen Effekt löschen') + '">' + ICON_TRASH + '</button>'
          + '</li>';
      });
      body += '</ul>';
      body += '<div class="props-video-note">' + escapeHtml(I.effectsListHint
        || 'Ziehen ändert die Reihenfolge. Objekte mit derselben Nummer erscheinen gleichzeitig.') + '</div>';
      body += '<button type="button" class="button button-ghost button-sm hub-btn hub-btn--ghost hub-btn--sm" id="clearSlideEffectsBtn"'
        + ' style="width:100%; margin-top:8px;">'
        + escapeHtml(I.effectsClear || 'Alle Effekte dieser Folie löschen') + '</button>';
    }

    return '<div class="ribbon-props-sections">'
      + ribbonPropsSection(I.tabEffect || I.sideTabEffects || 'Effekte', body)
      + '</div>';
  }

  /**
   * Neue Nummern aus der Reihenfolge in der Liste. Zwei Objekte, die vorher
   * dieselbe Nummer hatten und nebeneinander geblieben sind, behalten eine
   * gemeinsame Nummer — sonst ginge «gleichzeitig» bei jedem Ziehen verloren.
   * Das gezogene Objekt bekommt immer eine eigene Nummer.
   */
  function effektNummernAusListe(listEl, gezogeneId) {
    const zeilen = [...listEl.querySelectorAll('li[data-effect-id]')].map((li) => ({
      id: li.dataset.effectId,
      alt: parseInt(li.dataset.animOrder, 10) || 1,
    }));
    let nr = 0;
    return zeilen.map((z, i) => {
      const vorige = i > 0 ? zeilen[i - 1] : null;
      const zusammen = vorige !== null && z.alt === vorige.alt
        && z.id !== gezogeneId && vorige.id !== gezogeneId;
      if (!zusammen) nr++;
      return { id: z.id, nr: nr };
    });
  }

  function wireSlideEffectsPanel() {
    const panel = document.getElementById('propsEffectsPanel');
    if (!panel) return;

    panel.querySelectorAll('.props-effect-del').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const node = findeAnimKnoten(btn.dataset.effectId);
        if (!node) return;
        effektWeg(node);
        refreshCanvas();
        scheduleSave();
        refreshPropsPanel();
      });
    });

    bindEffectsListDrag(panel.querySelector('.props-effects-list'));

    /* Doppelklick (oder Eingabe) auf einen Eintrag wählt das Objekt auf der Folie und zeigt seine Effekte
       Einblenden/Ausblenden zum Bearbeiten (Urs 27.11.: «doppelklick springt gleich auf das Objekt und öffnet die Effekte»). */
    const oeffneEffekt = (li) => {
      const node = li && findeAnimKnoten(li.dataset.effectId);
      if (!node) return;
      SF.activeFxSubtab = 'in';
      persistEditorUi({});
      selectNode(node);
      document.getElementById('fxTab_in')?.focus();
    };
    /* Ein Klick wählt das Objekt auf der Folie, die Liste bleibt stehen und markiert es (Urs 27.11.: «Bei einem Klick schon auf das
       Objekt springen und es im Fenster markieren»). Zweiter Klick auf dieselbe Zeile kurz danach = Doppelklick → Effekt bearbeiten;
       von Hand gezählt, weil die Liste nach dem ersten Klick neu gezeichnet wird und der Browser dann kein dblclick meldet. */
    const waehleObjekt = (li) => {
      const node = li && findeAnimKnoten(li.dataset.effectId);
      if (!node) return;
      const id = li.dataset.effectId;
      SF.activeFxSubtab = 'order';
      persistEditorUi({});
      selectNode(node);
      const neu = [...panel.querySelectorAll('.props-effects-list > li[data-effect-id]')].find((x) => x.dataset.effectId === id);
      if (neu) neu.focus({ preventScroll: true });
    };
    panel.querySelectorAll('.props-effects-list > li[data-effect-id]').forEach((li) => {
      li.addEventListener('click', (e) => {
        if (e.target.closest('.props-effect-del, .props-layer-drag-handle')) return;
        e.preventDefault();
        const jetzt = Date.now();
        const vorher = SF.fxListClick;
        SF.fxListClick = { id: li.dataset.effectId, t: jetzt };
        if (vorher && vorher.id === li.dataset.effectId && jetzt - vorher.t < 450) {
          SF.fxListClick = null;
          oeffneEffekt(li);
          return;
        }
        waehleObjekt(li);
      });
      li.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' || e.target !== li) return;
        e.preventDefault();
        oeffneEffekt(li);
      });
    });

    const btn = document.getElementById('clearSlideEffectsBtn');
    if (!btn) return;
    btn.addEventListener('click', async () => {
      const treffer = knotenMitEffekt();
      if (!treffer.length) return;
      if (!(await SFDialog.confirm(
        (I.effectsClearConfirm || 'Alle Effekte dieser Folie löschen?').replace('{n}', String(treffer.length)),
        { danger: true }
      ))) return;
      treffer.forEach(effektWeg);
      refreshCanvas();
      scheduleSave();
      setSaveStatus((I.effectsClearDone || 'Effekte gelöscht: {n}').replace('{n}', String(treffer.length)));
      refreshPropsPanel();
    });
  }

  /** Ziehen in der Effekt-Liste — dasselbe Vorgehen wie in der Ebenen-Liste. */
  function bindEffectsListDrag(listEl) {
    if (!listEl || listEl.dataset.dragBound) return;
    listEl.dataset.dragBound = '1';
    let dragLi = null;

    listEl.addEventListener('dragstart', (e) => {
      if (!e.target.closest('.props-layer-drag-handle')) {
        e.preventDefault();
        return;
      }
      dragLi = e.target.closest('li[data-effect-id]');
      if (!dragLi) return;
      dragLi.classList.add('dragging');
      if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', dragLi.dataset.effectId || '');
      }
    });

    listEl.addEventListener('dragover', (e) => {
      if (!dragLi) return;
      e.preventDefault();
      const li = e.target.closest('li[data-effect-id]');
      if (li && li !== dragLi) {
        const zeilen = [...listEl.querySelectorAll('li[data-effect-id]')];
        const von = zeilen.indexOf(dragLi);
        const nach = zeilen.indexOf(li);
        if (von < 0 || nach < 0) return;
        if (von < nach) li.after(dragLi);
        else li.before(dragLi);
      } else if (!li) {
        listEl.appendChild(dragLi);
      }
    });

    listEl.addEventListener('drop', (e) => {
      e.preventDefault();
      if (!dragLi) return;
      const gezogen = dragLi.dataset.effectId;
      effektNummernAusListe(listEl, gezogen).forEach((z) => {
        const node = findeAnimKnoten(z.id);
        if (!node) return;
        if (hatEinblenden(node)) {
          node.setAttr('animOrder', z.nr);
          exitNachEinblenden(node);
        } else {
          const ex = nodeExit(node);
          if (ex) node.setAttr('animExit', Object.assign({}, ex, { order: z.nr }));
        }
        updateAnimationBadge(node);
      });
      dragLi.classList.remove('dragging');
      dragLi = null;
      refreshCanvas();
      scheduleSave();
      refreshPropsPanel();
    });

    listEl.addEventListener('dragend', () => {
      if (dragLi) dragLi.classList.remove('dragging');
      dragLi = null;
    });
  }

  /* ---- Abspielen (3.11.0, Urs 27.11.: «… dass ich die Animation gleich im Editor und im Vorschaufenster testen kann») ----
     3.12.3 (Urs 27.11.: «←→ im Editor die Animationen weiter klickt … und wenn die Effekte auf der Folie fertig sind springt er
     auf die nächste Folie» / «Braucht es Esc?»): Das Abspiel-Bild (iframe preview.php?play=1, derselbe Renderer wie die
     Präsentation, ganze Präsentation ab der aktuellen Folie) liegt genau über der Folie (#stageWrap) und lässt Klicks durch.
     Jede Bearbeitungsabsicht beendet das Abspielen und wirkt sofort: Klick irgendwo (auf der Bühne wird wie sonst ausgewählt),
     jede Taste ausser den Abspiel-Tasten (Katalog scope «play»); Esc beendet zusätzlich. Der Editor folgt der Folie im
     Hintergrund (switchToSlide), Mitlaufen folgt Folie und Schritt (frag im Live-Kanal «editor»). Kleiner Hinweis oben:
     «Abspielen · Folie x · Schritt y/z». */
  const play = { el: null, frame: null, slide: 0, step: 0, total: 0, ready: false, prevFocus: null, sync: null };

  /* Abspielen-Knopf über der Folie (3.12.7, vorher im Effekte-Bereich). */
  const stagePlayBtn = document.getElementById('stagePlayBtn');
  if (stagePlayBtn) {
    stagePlayBtn.addEventListener('click', () => openPlayOverlay());
    /* Kürzel im Mouseover (DESIGN Hub § Tastenkürzel), immer das aktuell eingestellte. */
    const tipBase = stagePlayBtn.title;
    stagePlayBtn.addEventListener('mouseenter', () => {
      const h = window.SFRibbon && typeof window.SFRibbon.hub === 'function' ? window.SFRibbon.hub() : null;
      const k = h && typeof h.formatShortcut === 'function' ? h.formatShortcut('stage_play') : '';
      stagePlayBtn.title = k ? tipBase + ' (' + k + ')' : tipBase;
    });
  }

  function wirePlayBar(panel, node) {
    panel.querySelectorAll('[data-fx-play-one]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        playEffectOnStage(node, btn.dataset.fxPlayOne);
      });
    });
  }

  /* go: 'next' (Vorgabe, auch ▶ und →) = aktuelle Folie ab Schritt 0; 'prev' (←) = Endzustand, dann einen Schritt zurück. */
  async function openPlayOverlay(go) {
    if (play.el || SF.masterSlideEditing || SF.editorViewMode === 'grid') return;
    const host = document.getElementById('canvasArea');
    const wrap = document.getElementById('stageWrap');
    if (!host || !wrap || !SF.slides[SF.currentIndex]) return;
    play.prevFocus = document.activeElement;
    play.slide = SF.currentIndex;
    play.step = 0;
    play.total = 0;
    play.ready = false;
    play.sync = null;
    const el = document.createElement('div');
    el.className = 'sf-play-hint';
    const closeTip = I.animPlayClose || 'Abspielen beenden (Esc)';
    const helpTip = I.animPlayHelp || '';
    el.innerHTML = '<span class="sf-play-step" role="status" aria-live="polite">' + escapeHtml(I.animPlayLoading || 'Folie wird geladen…') + '</span>'
      + '<button type="button" class="hub-icon-btn sf-play-close" title="' + escapeHtml(closeTip + (helpTip ? ' · ' + helpTip : '')) + '" aria-label="' + escapeHtml(closeTip) + '">'
      + '<span aria-hidden="true">✕</span></button>';
    /* 3.12.21 (Urs 27.11.: «Mach das auch in die Liste hier»): Anzeige in der Notizen-Leiste an Stelle des Abspielen-Knopfs;
       ohne Notizen-Leiste (nur lesen) oben über der Folie wie bisher. */
    const playBtn = document.getElementById('stagePlayBtn');
    if (playBtn && playBtn.closest('.notes-panel-header')) {
      el.classList.add('sf-play-hint--bar');
      playBtn.after(el);
    } else {
      host.appendChild(el);
    }
    const frame = document.createElement('iframe');
    frame.className = 'sf-play-frame';
    frame.title = I.animPlay || 'Abspielen';
    frame.setAttribute('tabindex', '-1');
    frame.setAttribute('aria-hidden', 'true');
    wrap.appendChild(frame);
    play.el = el;
    play.frame = frame;
    el.querySelector('.sf-play-close').addEventListener('click', () => closePlayOverlay(true));
    /* Fokus aus Knöpfen/Panels nehmen: sonst löste Leertaste/Eingabe den Knopf nochmals aus. */
    const ae = document.activeElement;
    if (ae && ae !== document.body && typeof ae.blur === 'function' && !(ae.closest && ae.closest('#canvasArea'))) ae.blur();
    document.addEventListener('keydown', playKeydown, true);
    document.addEventListener('pointerdown', playPointerdown, true);
    try {
      if (SF.canEdit && window.SFEditor) await window.SFEditor.flush();
    } catch (e) { /* Speichern meldet selbst */ }
    if (!play.el || play.el !== el) return;
    frame.addEventListener('load', () => {
      let ok = false;
      try { ok = !!(frame.contentWindow && frame.contentWindow.sfPlay); } catch (e) { ok = false; }
      if (!ok && play.el === el) {
        const st = el.querySelector('.sf-play-step');
        if (st) { st.textContent = I.animPlayError || 'Folie konnte nicht geladen werden.'; st.classList.add('is-error'); }
      }
    });
    frame.src = 'preview.php?id=' + encodeURIComponent(SF.id) + '&slide=' + SF.currentIndex + '&play=1'
      + (go === 'prev' ? '&go=prev' : '') + '&_=' + Date.now();
  }

  /* restoreFocus nur bei Esc/✕; bei Klick oder anderer Taste geht die Eingabe dorthin, wo sie hingehört. */
  function closePlayOverlay(restoreFocus) {
    if (!play.el) return;
    document.removeEventListener('keydown', playKeydown, true);
    document.removeEventListener('pointerdown', playPointerdown, true);
    play.el.remove();
    if (play.frame) play.frame.remove();
    play.el = null;
    play.frame = null;
    play.ready = false;
    play.sync = null;
    if (SF.playFrag !== null) {
      SF.playFrag = null;
      mitlaufMelden(false);
    }
    const back = play.prevFocus;
    play.prevFocus = null;
    if (restoreFocus === true && back && back !== document.body && document.contains(back) && back.focus) back.focus({ preventScroll: true });
  }

  function playControl(dir) {
    try {
      const w = play.frame && play.frame.contentWindow;
      if (w && w.sfPlay) w.sfPlay[dir]();
    } catch (e) { /* Seite im iframe noch nicht bereit */ }
  }

  /* Klick irgendwo ausser auf ✕: Abspielen endet, der Klick wirkt weiter (Bühne wählt aus, Filmstreifen wechselt …). */
  function playPointerdown(e) {
    if (!play.el) return;
    if (e.target && e.target.closest && e.target.closest('.sf-play-close')) return;
    closePlayOverlay(false);
  }

  /* Tasten beim Abspielen (3.12.1/3.12.3): Befehle aus dem Katalog, scope «play» (Vorgaben Leertaste/Eingabe/→/↓/Bild↓ weiter,
     ←/↑/Bild↑ zurück, Esc beenden), umstellbar unter «Weitere Befehle». Jede andere Taste beendet das Abspielen und wirkt wie sonst. */
  const PLAY_MOD_KEYS = ['Control', 'Shift', 'Alt', 'Meta', 'AltGraph', 'CapsLock'];
  function playKeyCommand(e) {
    if (PLAY_MOD_KEYS.includes(e.key)) return 'ignore';
    return window.SFScopeCmd ? window.SFScopeCmd(e, 'play') : '';
  }
  function runPlayCommand(cmd) {
    if (cmd === 'play_close') closePlayOverlay(true);
    else if (cmd === 'play_next') playControl('next');
    else if (cmd === 'play_prev') playControl('prev');
    else if (cmd !== 'ignore') closePlayOverlay(false);
  }
  function playKeydown(e) {
    if (!play.el) return;
    const closeBtn = play.el.querySelector('.sf-play-close');
    const plain = !e.ctrlKey && !e.metaKey && !e.altKey;
    if (plain && e.key === 'Enter' && document.activeElement === closeBtn) return; // Eingabe auf ✕ beendet
    const cmd = playKeyCommand(e);
    if (cmd && cmd !== 'ignore') {
      e.preventDefault();
      e.stopPropagation();
    }
    runPlayCommand(cmd);
  }

  function renderPlayStep() {
    const st = play.el && play.el.querySelector('.sf-play-step');
    if (!st) return;
    st.classList.remove('is-error');
    st.textContent = (I.animPlayStatus || 'Abspielen · Folie {s} · Schritt {x}/{y}')
      .replace('{s}', String(play.slide + 1)).replace('{x}', String(play.step)).replace('{y}', String(play.total));
  }

  /* Editor folgt der abgespielten Folie im Hintergrund; nacheinander, damit schnelles Weiterklicken nicht überholt. */
  function playFollowSlide() {
    play.sync = (play.sync || Promise.resolve()).then(async () => {
      if (!play.el || play.slide === SF.currentIndex || !SF.slides[play.slide]) return;
      await switchToSlide(play.slide, { keepPlay: true });
    }).catch(() => {}).then(() => { if (play.el) mitlaufMelden(false); });
  }

  window.addEventListener('message', (e) => {
    if (!play.el || !play.frame || e.origin !== window.location.origin || e.source !== play.frame.contentWindow) return;
    const d = e.data || {};
    if (d.type === 'sf-play-step') {
      const last = Math.max(0, SF.slides.length - 1);
      play.slide = Math.max(0, Math.min(last, parseInt(d.slide, 10) || 0));
      play.step = Math.max(0, parseInt(d.step, 10) || 0);
      play.total = Math.max(0, parseInt(d.total, 10) || 0);
      if (!play.ready) { play.ready = true; play.frame.classList.add('is-ready'); }
      renderPlayStep();
      SF.playFrag = play.step - 1;
      playFollowSlide();
    } else if (d.type === 'sf-play-key') {
      /* Taste aus dem Abspiel-iframe (falls es doch den Fokus hat): gleiche Regeln wie playKeydown. */
      const ev = { key: String(d.key || ''), code: String(d.code || ''), ctrlKey: !!d.ctrlKey, metaKey: !!d.metaKey, altKey: !!d.altKey, shiftKey: !!d.shiftKey };
      runPlayCommand(playKeyCommand(ev));
    }
  });

  /* Kleines ▶ in den Kopfzeilen Einblenden/Ausblenden: diesen Effekt einmal auf der Konva-Bühne nachspielen.
     Nur Darstellung: Tween von/zu den gespeicherten Werten, danach exakt der alte Zustand; nichts wird gespeichert. */
  const PLAY_SHIFT = 40;      // wie die Richtungseffekte im Renderer (translate 40px)
  const PLAY_DEFAULT_MS = 600;
  function playEffectOnStage(node, teil) {
    if (!node || !SF.layer || typeof Konva === 'undefined' || node._sfPlaying) return;
    let type = '';
    let ms = 0;
    if (teil === 'out') {
      const ex = nodeExit(node);
      if (!ex) return;
      type = 'exit-' + ex.type;
      ms = ex.duration;
    } else {
      type = node.getAttr('animType') || 'none';
      ms = parseInt(node.getAttr('animDuration'), 10) || 0;
    }
    if (type === 'none') return;
    const dur = (ms > 0 ? ms : PLAY_DEFAULT_MS) / 1000;
    const base = { x: node.x(), y: node.y(), opacity: node.opacity(), scaleX: node.scaleX(), scaleY: node.scaleY() };
    const box = node.getClientRect({ relativeTo: node.getParent() });
    const cx = box.x + box.width / 2;
    const cy = box.y + box.height / 2;
    const scaled = (k) => ({ scaleX: base.scaleX * k, scaleY: base.scaleY * k, x: cx + (base.x - cx) * k, y: cy + (base.y - cy) * k });
    let from = null;
    let to = null;
    switch (type) {
      case 'fade-in': from = { opacity: 0 }; to = { opacity: base.opacity }; break;
      case 'fade-out': case 'exit-fade': from = {}; to = { opacity: 0 }; break;
      case 'fade-up': from = { opacity: 0, y: base.y + PLAY_SHIFT }; to = { opacity: base.opacity, y: base.y }; break;
      case 'fade-down': from = { opacity: 0, y: base.y - PLAY_SHIFT }; to = { opacity: base.opacity, y: base.y }; break;
      case 'fade-left': from = { opacity: 0, x: base.x + PLAY_SHIFT }; to = { opacity: base.opacity, x: base.x }; break;
      case 'fade-right': from = { opacity: 0, x: base.x - PLAY_SHIFT }; to = { opacity: base.opacity, x: base.x }; break;
      case 'grow': from = {}; to = scaled(1.3); break;
      case 'shrink': from = {}; to = scaled(0.7); break;
      case 'strike': from = { opacity: base.opacity * 0.4 }; to = { opacity: base.opacity }; break;
      case 'exit-up': from = {}; to = { opacity: 0, y: base.y - PLAY_SHIFT }; break;
      case 'exit-down': from = {}; to = { opacity: 0, y: base.y + PLAY_SHIFT }; break;
      case 'exit-left': from = {}; to = { opacity: 0, x: base.x - PLAY_SHIFT }; break;
      case 'exit-right': from = {}; to = { opacity: 0, x: base.x + PLAY_SHIFT }; break;
      case 'exit-shrink': from = {}; to = Object.assign({ opacity: 0 }, scaled(0.7)); break;
      case 'exit-grow': from = {}; to = Object.assign({ opacity: 0 }, scaled(1.3)); break;
      default: return;
    }
    node._sfPlaying = true;
    const hadTransformer = SF.transformer && SF.transformer.nodes().includes(node);
    if (hadTransformer) SF.transformer.visible(false);
    removeAnimationBadge(node);
    node.setAttrs(from);
    SF.layer.batchDraw();
    const restore = () => {
      node.setAttrs(base);
      node._sfPlaying = false;
      if (hadTransformer && SF.transformer) SF.transformer.visible(true);
      updateAnimationBadge(node);
      refreshCanvas();
    };
    const tween = new Konva.Tween(Object.assign({ node, duration: dur, easing: Konva.Easings.EaseInOut }, to, {
      onFinish: () => {
        /* Ende kurz stehen lassen, dann zurück in den Bearbeitungsstand. */
        setTimeout(() => { tween.destroy(); restore(); }, 400);
      },
    }));
    tween.play();
  }

  function renderEffectsPanel(node, obj, isText) {
    const panel = document.getElementById('propsEffectsPanel');
    if (!panel) return;
    const einzeln = !!(node && SF.selectedNodes.length === 1 && !isObjectGroup(node));
    const aktiv = einzeln ? SF.activeFxSubtab : 'order';
    let inhalt = '';
    let ex = null;
    if (aktiv === 'order') {
      if (SF.canEdit) {
        inhalt = buildSlideEffectsHtml();
        if (!einzeln) {
          inhalt += '<div class="props-video-note props-fx-pick">' + escapeHtml(I.fxPickObjectHint
            || 'Objekt wählen, um Ein- und Ausblenden zu bearbeiten.') + '</div>';
        }
      } else {
        inhalt = propsPanelEmptyHtml();
      }
    } else if (aktiv === 'in') {
      const hatEin = (obj.animType || 'none') !== 'none';
      inhalt = fxPlayOneHtml('in', I.animIn || 'Einblenden', hatEin) + buildEffectPropsHtml(node, obj, isText);
    } else {
      ex = nodeExit(node);
      inhalt = fxPlayOneHtml('out', I.animOut || 'Ausblenden', !!ex) + buildExitPropsHtml(node);
    }
    panel.innerHTML = fxTabsHtml(aktiv, einzeln)
      + '<div class="props-fx-tabpanel" id="fxTabPanel" role="tabpanel" aria-labelledby="fxTab_' + aktiv + '">'
      + '<div class="props-fx-body">' + inhalt + '</div></div>';
    if (aktiv === 'order' && SF.canEdit) {
      wireSlideEffectsPanel();
      const an = panel.querySelector('.props-effects-list > li.active');
      if (an) an.scrollIntoView({ block: 'nearest' });
    }
    wireFxTabs(panel);
    wirePlayBar(panel, einzeln ? node : null);
  }

  /* Untertabs im Effekte-Reiter (3.12.5, Urs 27.11.: «drei Tabs … Einblenden, ausblenden und Reihenfolge? Je mit einem Icon»):
     gleiche Klassen wie Position («Grösse & Lage | Ebenen»), role=tablist, Pfeiltasten wechseln. Ohne gewähltes Objekt
     nur «Reihenfolge»; mit Objekt der zuletzt benutzte Tab (editor_ui.fxSubtab), Vorgabe Einblenden. */
  const FX_TABS = [
    { id: 'in', icon: 'sun', label: () => I.animIn || 'Einblenden' },
    { id: 'out', icon: 'moon', label: () => I.animOut || 'Ausblenden' },
    { id: 'order', icon: 'list', label: () => I.fxOrder || 'Reihenfolge' },
  ];

  function fxTabsHtml(aktiv, einzeln) {
    const pick = I.fxPickObject || 'Objekt wählen';
    return '<div class="props-pos-subtabs props-fx-subtabs" role="tablist" aria-label="' + escapeHtml(I.tabEffect || I.sideTabEffects || 'Effekte') + '">'
      + FX_TABS.map((t) => {
        const an = t.id === aktiv;
        const aus = !einzeln && t.id !== 'order';
        return '<button type="button" class="props-pos-subtab props-fx-subtab' + (an ? ' active' : '') + '" role="tab" id="fxTab_' + t.id
          + '" data-fx-subtab="' + t.id + '" aria-selected="' + (an ? 'true' : 'false') + '" aria-controls="fxTabPanel" tabindex="' + (an ? '0' : '-1') + '"'
          + (aus ? ' disabled aria-disabled="true" title="' + escapeHtml(pick) + '"' : '') + '>'
          + hubSpriteIcon(t.icon) + '<span>' + escapeHtml(t.label()) + '</span></button>';
      }).join('')
      + '</div>';
  }

  function wireFxTabs(panel) {
    const leiste = panel.querySelector('.props-fx-subtabs');
    if (!leiste) return;
    const wechsle = (id) => {
      if (['in', 'out', 'order'].indexOf(id) === -1 || id === SF.activeFxSubtab) return;
      SF.activeFxSubtab = id;
      persistEditorUi({});
      refreshPropsPanel();
      document.getElementById('fxTab_' + id)?.focus();
    };
    leiste.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-fx-subtab]');
      if (!btn || btn.disabled) return;
      e.preventDefault();
      wechsle(btn.dataset.fxSubtab);
    });
    leiste.addEventListener('keydown', (e) => {
      if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(e.key) === -1) return;
      const tabs = [...leiste.querySelectorAll('[data-fx-subtab]:not([disabled])')];
      if (tabs.length < 2) return;
      const i = Math.max(0, tabs.indexOf(document.activeElement));
      let n = i;
      if (e.key === 'ArrowLeft') n = (i - 1 + tabs.length) % tabs.length;
      if (e.key === 'ArrowRight') n = (i + 1) % tabs.length;
      if (e.key === 'Home') n = 0;
      if (e.key === 'End') n = tabs.length - 1;
      e.preventDefault();
      wechsle(tabs[n].dataset.fxSubtab);
    });
  }

  /** Kleines ▶ oben im Tab: zeigt diesen Effekt einmal auf der Bühne (3.11.0). */
  function fxPlayOneHtml(teil, titel, abspielbar) {
    if (!abspielbar) return '';
    const tip = (I.animPlayOne || 'Diesen Effekt einmal auf der Bühne zeigen') + ' (' + titel + ')';
    return '<div class="props-fx-tabhead">'
      + '<button type="button" class="hub-icon-btn props-fx-play" data-fx-play-one="' + teil + '" title="' + escapeHtml(tip)
      + '" aria-label="' + escapeHtml(tip) + '"><span aria-hidden="true">▶</span></button>'
      + '</div>';
  }

  /* ---------- Reiter «Zeitstrahl» (3.12.48) ----------
     Der Reiter ist nur sichtbar, solange genau ein Zeitstrahl markiert ist (Urs 27.11.). Eingaben ändern das Modell
     im Knoten, zeichnen die Bühne neu und laufen über scheduleSave() (Speichern und Rückgängig wie jede Änderung). */
  function isTimelineNode(node) {
    return !!(node && node.getAttr && node.getAttr('objType') === 'timeline');
  }

  function renderTimelinePanel(node, opts) {
    const tab = document.getElementById('propsSideTabTimeline');
    const panel = document.getElementById('propsTimelinePanel');
    const tl = (isTimelineNode(node) && SF.selectedNodes.length === 1) ? node : null;
    if (tab) tab.hidden = !tl;
    if (!tl) {
      SF.tlPanelNode = null;
      if (panel) panel.innerHTML = '';
      if (SF.activeSideTab === 'timeline') setSideTab('format', { persist: false });
      return;
    }
    if (!panel) return;
    /* Während des Tippens nicht neu aufbauen (sonst springt die Schreibmarke weg). */
    const force = !!(opts && opts.force);
    if (!force && SF.tlPanelNode === tl && panel.contains(document.activeElement)) return;
    SF.tlPanelNode = tl;
    const daten = window.SfTimeline.normalize(tl.getAttr('tlData'));
    tl.setAttr('tlData', daten);
    window.SfTimeline.panel(panel, daten, {
      I: I,
      colors: (SF.brandColors || []).map((c) => c && c.hex),
      focus: opts && opts.focus,
      onChange: (rebuild, info) => {
        tl.setAttr('tlData', window.SfTimeline.normalize(daten));
        drawTimeline(tl);
        refreshCanvas();
        scheduleSave();
        if (rebuild) renderTimelinePanel(tl, { force: true, focus: info && info.focus });
      },
    });
  }

  function openTimelineTab(node) {
    if (!isTimelineNode(node)) return;
    if (SF.selectedNode !== node || SF.selectedNodes.length !== 1) selectNode(node);
    renderTimelinePanel(node, { force: true });
    setSideTab('timeline');
  }

  function renderPropsPanel(node) {
    const textPanel = document.getElementById('propsTextPanel');
    const templatesPanel = document.getElementById('propsTextTemplatesPanel');
    const objectPanel = document.getElementById('propsObjectPanel');
    if (!textPanel && !objectPanel && !document.getElementById('propsEffectsPanel')) return;

    renderTimelinePanel(node);
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
    body += '<div id="markSelectionPalette" hidden><div class="options-subtitle" style="margin-top:8px;">' + I.markColorPick + '</div><input type="color" id="markColorPicker" value="#fff176" style="width:100%; height:32px; margin-bottom:6px;">' + '</div>';
    body += '<div id="colorSelectionPalette" hidden><div class="options-subtitle" style="margin-top:8px;">' + I.textColorPick + '</div><input type="color" id="textColorPicker" value="#3a6c8d" style="width:100%; height:32px; margin-bottom:6px;">' + '</div>';
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

  function fieldNumber(id, label, value) {
    return '<div><label for="' + id + '">' + label + '</label><input type="number" id="' + id + '" value="' + value + '" data-hub-step></div>';
  }
  function fieldColor(id, label, value) {
    return '<div><label for="' + id + '">' + label + '</label><input type="color" id="' + id + '" value="' + value + '"></div>';
  }
  /** Häkchen-Zeile in den Tabellen-Eigenschaften. */
  function tableCheck(id, label, checked) {
    return '<label class="props-check-row"><input type="checkbox" id="' + id + '" style="width:auto;"'
      + (checked ? ' checked' : '') + '> ' + escapeHtml(label) + '</label>';
  }
  /** Zwischenüberschrift in den Tabellen-Eigenschaften. */
  function tableHead(label) {
    return '<div class="options-subtitle">' + escapeHtml(label) + '</div>';
  }
  /** Farbfeld mit den Markenfarben darunter. */
  function tableColorField(id, label, value) {
    return fieldColor(id, escapeHtml(label), value);
  }
  /** Kleiner Knopf für die Tabellen-Eigenschaften (Zeile/Spalte mehr oder weniger). */
  function tableBtn(id, label) {
    return '<button type="button" class="button button-ghost button-sm hub-btn hub-btn--ghost hub-btn--sm" id="'
      + id + '" style="flex:1;">' + escapeHtml(label) + '</button>';
  }
  function fieldRange(id, label, value) {
    return '<div><label for="' + id + '">' + label + ' (' + value + '%)</label><input type="range" id="' + id + '" min="0" max="100" value="' + value + '" data-hub-range data-hub-range-unit="%"></div>';
  }
  function fieldRangePlain(id, label, min, max, value) {
    return '<div><label for="' + id + '">' + label + ' (' + value + '°)</label><input type="range" id="' + id + '" min="' + min + '" max="' + max + '" value="' + value + '" data-hub-range data-hub-range-unit="°"></div>';
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

  /* Regler für Pfeil-Spitze/-Schaft (3.12.10), Aufbau wie der Deckkraft-Regler im Positionsbereich. */
  function fieldArrowRange(id, label, min, max, value) {
    const v = parseInt(value, 10) || min;
    /* Regler + Zahlenfeld über HubNumberStep (data-hub-range, Standard seit Hub 2.37.0). */
    return '<label class="sf-arrow-slider" for="' + id + '" title="' + escapeHtml(label) + '">'
      + '<span class="sf-arrow-slider-label">' + escapeHtml(label) + '</span>'
      + '<input type="range" id="' + id + '" min="' + min + '" max="' + max + '" value="' + v + '" aria-label="' + escapeHtml(label) + '" data-hub-range data-hub-range-unit="%">'
      + '</label>';
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
    } else if (type === 'table') {
      /* Die Tabelle rechnet mit `tableW`, nicht mit der Breite der Gruppe. */
      node.setAttr('tableW', Math.max(60, w));
      drawTable(node);
    } else if (type === 'timeline') {
      node.setAttr('tlW', Math.max(40, w));
      drawTimeline(node);
    } else { node.width(w); }
    if (node.getAttr('fillType') === 'gradient') applyShapeGradientVisual(node);
  }

  function applyHeight(node, type, h) {
    if (type === 'ellipse') { node.radiusY(h / 2); }
    else if (type === 'video' || type === 'audio') { node.scaleY(h / (node.getAttr('baseH') || h)); }
    else if (type === 'shape') {
      node.points(buildShapePoints(node.getAttr('shapeType'), node.getAttr('baseW'), h, nodeShapeCfg(node)));
      node.setAttr('baseH', h);
    } else if (type === 'table') {
      /* Nichts: die Höhe einer Tabelle wächst mit ihrem Inhalt — von Hand gesetzt
         wäre der Wert beim nächsten Zeichnen wieder weg. */
    } else if (type === 'timeline') {
      node.setAttr('tlH', Math.max(40, h));
      drawTimeline(node);
    } else { node.height(h); }
    if (node.getAttr('fillType') === 'gradient') applyShapeGradientVisual(node);
  }

  /** Symbol aus dem Hub-Icon-Satz (Sprite des Hubs, wie sf-ff.js devIcon); Name nur nach Muster. */
  function hubSpriteIcon(name) {
    const n = /^[a-z0-9-]{1,24}$/.test(String(name || '')) ? String(name) : 'list';
    return '<svg class="props-fx-ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="'
      + escapeHtml(location.origin + '/assets/icons/hub-icons.svg#hub-i-' + n) + '"></use></svg>';
  }

  /** Zwei Knöpfe nebeneinander; mit `icons` [links, rechts] je Symbol + Text (Effekte, 3.12.2). */
  function propsTransferRowHtml(copyId, pasteId, copyLabel, pasteLabel, canPaste, icons) {
    const ico = (i) => (icons && icons[i] ? hubSpriteIcon(icons[i]) : '');
    return '<div class="props-transfer-row' + (icons ? ' props-fx-row' : '') + '">' +
      '<button type="button" class="button button-ghost button-sm hub-btn hub-btn--ghost hub-btn--sm" id="' + copyId + '">' + ico(0) + '<span>' + escapeHtml(copyLabel) + '</span></button>' +
      '<button type="button" class="button button-ghost button-sm hub-btn hub-btn--ghost hub-btn--sm" id="' + pasteId + '"' + (canPaste ? '' : ' disabled') + '>' + ico(1) + '<span>' + escapeHtml(pasteLabel) + '</span></button>' +
      '</div>';
  }

  /** Zwei Auswahlfelder nebeneinander (Dauer + Automatisch starten), Beschriftung mit Symbol. */
  function fxSelectPairHtml(a, b) {
    const feld = (f) => fieldSelectKV(f.id, hubSpriteIcon(f.icon) + '<span>' + f.label + '</span>', f.options, f.value);
    return '<div class="props-fx-row props-fx-selects">' + feld(a) + feld(b) + '</div>';
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
      animExit: nodeExit(node),
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
    node.setAttr('animExit', c.animExit ? Object.assign({}, c.animExit) : undefined);
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
      /* T4: während Bearbeiten an Ort wirken die Knöpfe auf die Markierung im Feld (ohne Markierung: nichts). */
      const inlineFor = () => (SF.inlineEdit && SF.inlineEdit.node === node ? SF.inlineEdit : null);
      document.querySelectorAll('[data-wrap]').forEach(btn => {
        btn.addEventListener('click', () => {
          const w = btn.dataset.wrap;
          const ie = inlineFor();
          if (ie) { ie.applyToggle(WRAP_INLINE_KEY[w]); return; }
          wrapTextSelection(w, w);
        });
      });
      document.querySelectorAll('[data-wraptag]').forEach(btn => {
        btn.addEventListener('click', () => {
          const t = btn.dataset.wraptag;
          const ie = inlineFor();
          if (ie) { ie.applyToggle(WRAP_INLINE_KEY[t]); return; }
          wrapTextSelection('[' + t + ']', '[/' + t + ']');
        });
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
          const ie = inlineFor();
          if (ie) {
            if (/^#[0-9a-f]{6}$/i.test(picker.value)) ie.applyValue(tagName, picker.value.toLowerCase(), true);   // gleiche Farbe nochmals = entfernen
          } else {
            wrapTextSelection('[' + tagName + '=' + picker.value + ']', '[/' + tagName + ']');
          }
          palette.hidden = true;
          btn.classList.remove('active');
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
      /* Zahlenfeld daneben setzt der Hub-Baustein (data-hub-range) und löst am Regler «input» aus. */
      on('p_arrowHead', 'input', (e) => {
        node.setAttr('arrowHead', Math.max(5, Math.min(95, parseInt(e.target.value, 10) || 40)));
        recomputeShapePoints(); refreshCanvas(); scheduleSave();
      });
      on('p_arrowShaft', 'input', (e) => {
        node.setAttr('arrowShaft', Math.max(5, Math.min(80, parseInt(e.target.value, 10) || 30)));
        recomputeShapePoints(); refreshCanvas(); scheduleSave();
      });
      bindIconPicker('p_bracketStyle', 'bracketStyle');
      bindIconPicker('p_bubbleStyle', 'bubbleStyle');
    } else if (type === 'timeline') {
      on('tlOpenTabBtn', 'click', () => openTimelineTab(node));
    } else if (type === 'table') {
      function tabelleNeu() {
        drawTable(node);
        refreshCanvas();
        scheduleSave();
        refreshPropsPanel();
      }
      function rowsJetzt() {
        return normalizeTableRows(node.getAttr('tableRows'));
      }
      on('tblRowAdd', 'click', () => {
        const rows = rowsJetzt();
        rows.push(new Array(rows[0].length).fill(''));
        node.setAttr('tableRows', rows);
        tabelleNeu();
      });
      on('tblRowDel', 'click', () => {
        const rows = rowsJetzt();
        // Eine Zeile muss bleiben, sonst verschwindet die Tabelle lautlos.
        if (rows.length <= 1) return;
        rows.pop();
        node.setAttr('tableRows', rows);
        tabelleNeu();
      });
      on('tblColAdd', 'click', () => {
        const rows = rowsJetzt().map((r) => r.concat(['']));
        const breiten = node.getAttr('tableColWidths');
        if (Array.isArray(breiten)) node.setAttr('tableColWidths', breiten.concat([1]));
        node.setAttr('tableRows', rows);
        tabelleNeu();
      });
      on('tblColDel', 'click', () => {
        const alt = rowsJetzt();
        if (!alt[0] || alt[0].length <= 1) return;
        const rows = alt.map((r) => r.slice(0, -1));
        const breiten = node.getAttr('tableColWidths');
        if (Array.isArray(breiten)) node.setAttr('tableColWidths', breiten.slice(0, -1));
        node.setAttr('tableRows', rows);
        tabelleNeu();
      });
      /* Beim Schieben eines Reglers steht der Wert in der Beschriftung — die wird
         hier nachgeführt, statt die ganze Leiste neu zu zeichnen (das schlösse ein
         offenes Farbfenster). */
      function reglerBeschriftung(id, text, wert) {
        const l = document.querySelector('label[for="' + id + '"]');
        if (l) l.textContent = text + ' (' + wert + '%)';
      }
      function tabelleZeichnen() {
        drawTable(node); refreshCanvas(); scheduleSave();
      }
      on('p_tblHeader', 'change', (e) => {
        node.setAttr('headerRow', !!e.target.checked);
        tabelleNeu();
      });
      on('p_tblStripe', 'change', (e) => {
        const an = !!e.target.checked;
        node.setAttr('stripeRows', an);
        if (an) {
          /* Eingeschaltet soll man sofort etwas sehen. */
          const farbe = node.getAttr('stripeFill');
          if (!farbe || farbe === 'transparent') node.setAttr('stripeFill', defaultIconColor());
          if (!(Number(node.getAttr('stripeOpacity')) > 0)) node.setAttr('stripeOpacity', 0.15);
        }
        tabelleNeu();
      });
      on('p_tblFontSize', 'input', (e) => {
        node.setAttr('fontSize', Math.max(6, parseInt(e.target.value, 10) || 44));
        tabelleZeichnen();
      });
      on('p_tblColor', 'input', (e) => {
        node.setAttr('color', e.target.value);
        tabelleZeichnen();
      });
      on('p_tblHeaderColor', 'input', (e) => {
        node.setAttr('headerColor', e.target.value);
        tabelleZeichnen();
      });
      on('p_tblHeaderFill', 'input', (e) => {
        node.setAttr('headerFill', e.target.value);
        if (!(Number(node.getAttr('headerFillOpacity')) > 0)) node.setAttr('headerFillOpacity', 1);
        const regler = document.getElementById('p_tblHeaderFillOpacity');
        if (regler) {
          regler.value = String(Math.round(Math.max(0, Math.min(1, Number(node.getAttr('headerFillOpacity') ?? 1))) * 100));
          reglerBeschriftung('p_tblHeaderFillOpacity', I.tableFillStrength || 'Füllstärke', regler.value);
        }
        tabelleZeichnen();
      });
      on('p_tblHeaderFillOpacity', 'input', (e) => {
        const wert = Math.max(0, Math.min(100, parseInt(e.target.value, 10) || 0));
        node.setAttr('headerFillOpacity', wert / 100);
        reglerBeschriftung('p_tblHeaderFillOpacity', I.tableFillStrength || 'Füllstärke', wert);
        tabelleZeichnen();
      });
      on('p_tblStripeFill', 'input', (e) => {
        node.setAttr('stripeFill', e.target.value);
        tabelleZeichnen();
      });
      on('p_tblStripeOpacity', 'input', (e) => {
        const wert = Math.max(0, Math.min(100, parseInt(e.target.value, 10) || 0));
        node.setAttr('stripeOpacity', wert / 100);
        reglerBeschriftung('p_tblStripeOpacity', I.tableFillStrength || 'Füllstärke', wert);
        tabelleZeichnen();
      });
      on('p_tblBorderColor', 'input', (e) => {
        node.setAttr('borderColor', e.target.value);
        drawTable(node); refreshCanvas(); scheduleSave();
      });
      on('p_tblBorderWidth', 'input', (e) => {
        node.setAttr('borderWidth', Math.max(0, parseInt(e.target.value, 10) || 0));
        drawTable(node); refreshCanvas(); scheduleSave();
      });
    } else if (type === 'image') {
      const replaceBtn = document.getElementById('replaceAssetBtn');
      if (replaceBtn) replaceBtn.addEventListener('click', () => {
        pickObjectMedia('image', node);
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
        pickObjectMedia(type === 'audio' ? 'audio' : 'video', node);
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
        // Neu: ein Klick nach dem letzten Schritt der Folie («nach vorherigem»).
        node.setAttr('animOrder', hoechsterSchritt(node, 'in') + 1);
      }
      exitNachEinblenden(node);
      updateAnimationBadge(node);
      refreshCanvas(); scheduleSave(); refreshPropsPanel();
    });
    const setzeEinSchritt = (nr) => {
      node.setAttr('animOrder', Math.max(1, nr));
      exitNachEinblenden(node);
      updateAnimationBadge(node);
      refreshCanvas(); scheduleSave(); refreshPropsPanel();
    };
    on('p_animOrder', 'change', (e) => setzeEinSchritt(parseInt(e.target.value, 10) || 1));
    on('p_animOrder_mit', 'click', () => setzeEinSchritt(hoechsterSchritt(node, 'in') || 1));
    on('p_animOrder_nach', 'click', () => setzeEinSchritt(hoechsterSchritt(node, 'in') + 1));

    const setzeExit = (teil) => {
      const ex = nodeExit(node);
      if (!ex) return;
      node.setAttr('animExit', Object.assign({}, ex, teil));
      exitNachEinblenden(node);
      updateAnimationBadge(node);
      refreshCanvas(); scheduleSave();
    };
    bindValueIconPicker('p_exit', (value) => {
      const ex = nodeExit(node);
      if (value === 'none') {
        node.setAttr('animExit', undefined);
      } else if (ex) {
        node.setAttr('animExit', Object.assign({}, ex, { type: value }));
      } else {
        node.setAttr('animExit', { type: value, order: hoechsterSchritt(node, 'exit') + 1, duration: 0, auto: 0 });
      }
      exitNachEinblenden(node);
      updateAnimationBadge(node);
      refreshCanvas(); scheduleSave(); refreshPropsPanel();
    });
    on('p_exitOrder', 'change', (e) => { setzeExit({ order: Math.max(1, parseInt(e.target.value, 10) || 1) }); refreshPropsPanel(); });
    on('p_exitOrder_mit', 'click', () => { setzeExit({ order: naechsterEinSchritt(node) }); refreshPropsPanel(); });
    on('p_exitOrder_nach', 'click', () => { setzeExit({ order: eigenerEinSchritt(node) + 1 }); refreshPropsPanel(); });
    on('p_exitDuration', 'change', (e) => setzeExit({ duration: parseInt(e.target.value, 10) || 0 }));
    on('p_exitAuto', 'change', (e) => setzeExit({ auto: parseInt(e.target.value, 10) || 0 }));
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
    if (SF.inlineEdit) SF.inlineEdit.restyle();   // Bearbeiten an Ort: Grundstil des Objekts nachführen (T4)
    if (SF.transformer) SF.transformer.forceUpdate();
    SF.layer.draw();
  }

  // ---------- Toolbar ----------
  function addShape(type) {
    if (type === 'image' || type === 'video' || type === 'audio') { pickObjectMedia(type); return; }

    const centerX = Math.round(SF.meta.width / 2) - 100;
    const centerY = Math.round(SF.meta.height / 2) - 60;
    const id = 'o' + Math.random().toString(16).slice(2, 10);
    let obj;
    if (type === 'text') {
      obj = { id, type: 'text', x: centerX, y: centerY, w: 599, h: 70, text: 'Neuer Text', fontFamily: 'Open Sans', fontSize: 65, fontWeight: 'normal', color: '#ffffff', align: 'left', rotation: 0, opacity: 1 };
    } else if (type === 'markdown-text') {
      const label = I.textFieldDefault || 'Textfeld';
      obj = { id, type: 'text', x: centerX, y: centerY, w: 599, h: 70, text: label, fontFamily: 'Open Sans', fontSize: 65, fontWeight: 'normal', color: '#ffffff', align: 'left', rotation: 0, opacity: 1 };
    } else if (type === 'table') {
      /* Drei Spalten, Kopfzeile und zwei Zeilen — eine Tabelle, die man sofort
         sieht und füllt, statt einer leeren Fläche. Breite wie ein Textfeld. */
      const kopf = I.tableHeaderCell || 'Überschrift';
      obj = Object.assign({
        id, type: 'table', x: centerX - 200, y: centerY, w: 1000, h: 200, rotation: 0, opacity: 1,
        rows: [[kopf, kopf, kopf], ['', '', ''], ['', '', '']],
        colWidths: [1, 1, 1],
      }, TABLE_DEFAULTS);
    } else if (type === 'timeline') {
      /* Zeitstrahl mit drei Beispiel-Ereignissen über die Folienbreite (3.12.48). */
      const tw = Math.round(SF.meta.width * 0.84), th = Math.round(SF.meta.height * 0.64);
      obj = Object.assign({
        id, type: 'timeline', rotation: 0, opacity: 1,
        x: Math.round((SF.meta.width - tw) / 2), y: Math.round((SF.meta.height - th) / 2), w: tw, h: th,
      }, window.SfTimeline.create(I, (SF.brandColors || []).map((c) => c && c.hex)));
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
      /* Neue Folie für die Rolle: als Folien-Schritt einreihen (3.9.5). */
      await deckUi('logos_add_slide:' + role, async () => {
        if (findSlideIndexForLogosRole(role) >= 0) return;
        await saveCurrentSlide(true);
        const res = await api('add_slide', { after_index: SF.slides.length - 1 });
        SF.slides = res.slides;
        const idx = SF.slides.length - 1;
        SF.slides[idx].layoutKey = layoutKeyForLogosRole(role);
        SF.currentIndex = idx;
        loadSlideIntoStage(idx);
        await renderSlideFilmstrip();
        addLogosPlaceholder(role);
        scheduleSave();
      });
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

  /* Mehrere Objekte (3.12.17, Urs 27.11.: «Kann ich auch alle Objekte die ich angewählt habe kopieren?»):
     Kopieren/Ausschneiden/Duplizieren nehmen alle gewählten Objekte in ihrer Ebenen-Reihenfolge; Einfügen setzt alle
     und wählt sie. Auf einer anderen Folie landen sie an derselben Stelle, auf derselben Folie je 24 px versetzt. */
  function gewaehlteObjekte() {
    const list = (SF.selectedNodes && SF.selectedNodes.length) ? SF.selectedNodes : (SF.selectedNode ? [SF.selectedNode] : []);
    const top = list.filter((n) => n && n.getLayer && n.getLayer() && isTopLevelNode(n));
    return (top.length ? top : list.filter(Boolean)).slice().sort((x, y) => x.zIndex() - y.zIndex());
  }

  function insertNodes(nodes) {
    if (!nodes.length) return;
    nodes.forEach((node) => {
      node.name('sf-node');
      SF.layer.add(node);
      bindNodeEvents(node);
      updateAnimationBadge(node);
    });
    if (SF.transformer) SF.transformer.moveToTop();
    if (nodes.length === 1) selectNode(nodes[0]);
    else selectNodes(nodes);
    refreshCanvas();
    scheduleSave();
  }

  function duplicateNode(node, offset) {
    if (!node || !SF.canEdit) return;
    const liste = gewaehlteObjekte().includes(node) ? gewaehlteObjekte() : [node];
    const d = offset ?? 24;
    insertNodes(liste.map((n) => {
      const obj = cloneObjectWithNewIds(nodeToObject(n));
      obj.x = (obj.x || 0) + d;
      obj.y = (obj.y || 0) + d;
      return createNode(obj);
    }));
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
    const liste = gewaehlteObjekte();
    if (!liste.length) return;
    SF.clipboard = liste.map(nodeToObject);
    SF.clipboardSlideId = SF.slides[SF.currentIndex] ? SF.slides[SF.currentIndex].id : null;
    SF.pasteCount = 0;
    updatePasteButton();
  }

  function updatePasteButton() {
    setRibbonCommandsDisabled('paste', !SF.clipboard || !SF.canEdit);
  }

  function cutSelected() {
    const liste = gewaehlteObjekte();
    if (!liste.length || !SF.canEdit) return;
    copySelected();
    liste.forEach((n) => { removeAnimationBadge(n); n.destroy(); });
    deselect();
    refreshCanvas();
    scheduleSave();
  }

  SF.pasteCount = 0;
  function pasteClipboard() {
    if (!SF.clipboard || !SF.canEdit) return;
    const items = Array.isArray(SF.clipboard) ? SF.clipboard : [SF.clipboard];
    const hier = SF.slides[SF.currentIndex] ? SF.slides[SF.currentIndex].id : null;
    if (hier !== SF.clipboardSlideId) { SF.clipboardSlideId = hier; SF.pasteCount = 0; }
    else SF.pasteCount++;
    const d = 24 * SF.pasteCount;
    insertNodes(items.map((it) => {
      const obj = cloneObjectWithNewIds(it);
      obj.x = (obj.x || 0) + d;
      obj.y = (obj.y || 0) + d;
      return createNode(obj);
    }));
  }

  let replaceTargetNode = null;

  /* Medien wählt man im FileForge-Dialog des Hubs (Urs 27.11.: «FF Piker verwenden, bei Bilder, Audio Video auch»);
     Hochladen vom Rechner bietet der Dialog selbst an. upload.php holt die Datei als die Person über AppCall und legt sie
     als Kopie in assets/ ab, damit sie in der .cfs steckt. Dateitypen hier nur als Filter; geprüft wird in upload.php. */
  const MEDIA_ACCEPT = { image: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], video: ['mp4', 'webm'], audio: ['mp3', 'wav', 'ogg', 'm4a'] };
  const MEDIA_LABEL = { video: 'Video', audio: 'Audio', image: 'Bild' };

  async function pickMediaSource(kind) {
    if (!SF.canEdit) return null;
    if (!window.SfFf || !window.SfFf.pickMedia) {
      setSaveStatus('Der Dateidialog von FileForge ist nicht erreichbar.', true);
      return null;
    }
    return window.SfFf.pickMedia(MEDIA_ACCEPT[kind] || MEDIA_ACCEPT.image, (MEDIA_LABEL[kind] || 'Datei') + ' wählen');
  }

  /** {ref} aus dem FileForge-Dialog an upload.php → {ok, url, filename} */
  async function storeMediaAsset(kind, src) {
    const formData = new FormData();
    formData.append('id', SF.id);
    formData.append('kind', kind);
    formData.append('csrf_token', SF.csrfToken);
    formData.append('ref', src.ref);
    const res = await fetch('upload.php', { method: 'POST', body: formData });
    const json = await res.json().catch(() => ({ ok: false }));
    if (!json.ok) throw new Error(json.error || 'Die Datei liess sich nicht übernehmen.');
    return json;
  }

  /** Menüband «Einfügen» und «Ersetzen» in den Eigenschaften: Datei wählen, übernehmen, einfügen bzw. ersetzen. */
  async function pickObjectMedia(kind, targetNode) {
    replaceTargetNode = targetNode || null;
    const src = await pickMediaSource(kind);
    if (!src) { replaceTargetNode = null; return; }
    await uploadObjectAsset(kind, src);
  }

  async function uploadObjectAsset(kind, src) {
    if (!src) return;
    setSaveStatus('Übernimmt ' + (MEDIA_LABEL[kind] || kind) + ' aus FileForge…');
    try {
      const json = await storeMediaAsset(kind, src);

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
      replaceTargetNode = null;
      setSaveStatus(e.message || 'Die Datei liess sich nicht übernehmen.', true);
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
          ? '<button type="button" class="media-library-delete hub-btn hub-btn--danger hub-btn--sm" data-file="' + escapeHtml(item.filename) + '">' + escapeHtml(cfg.delete) + '</button>'
          : '';
        const bgBtn = (SF.canEdit && (item.kind === 'image' || item.kind === 'video'))
          ? '<button type="button" class="media-library-bg-btn hub-btn hub-btn--ghost hub-btn--sm">' + escapeHtml(cfg.useBackground || 'Als Hintergrund') + '</button>'
          : '';
        const insertable = SF.canEdit ? ' is-insertable' : '';
        return '<div class="media-library-item' + (unused ? ' is-unused' : '') + insertable + '" data-url="' + escapeHtml(item.url) + '" data-kind="' + escapeHtml(item.kind) + '"' + (SF.canEdit ? ' role="button" tabindex="0"' : '') + '>' +
          thumb +
          '<div class="media-library-meta">' +
            '<div class="media-library-name" title="' + escapeHtml(item.filename) + '">' + escapeHtml(item.filename) + '</div>' +
            '<div class="media-library-details">' + escapeHtml(kindLabel(item.kind)) + ' · ' + formatBytes(item.size) + '</div>' +
            usageHtml +
            // Aktionen in eigener Zeile unter dem Text (3.3.7): «Löschen» lag neben dem Namen und überdeckte ihn in der schmalen Leiste.
            ((bgBtn || deleteBtn) ? '<div class="media-library-actions">' + bgBtn + deleteBtn + '</div>' : '') +
          '</div>' +
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

    /* «Aus Dateien einfügen»: Datei im FileForge-Dialog wählen, Art aus der Endung, dann wie Menüband «Einfügen». */
    async function insertFromFiles() {
      if (!SF.canEdit) return;
      if (!window.SfFf || !window.SfFf.pickMedia) {
        statusEl.textContent = 'Der Dateidialog von FileForge ist nicht erreichbar.';
        return;
      }
      const accept = [].concat(MEDIA_ACCEPT.image, MEDIA_ACCEPT.video, MEDIA_ACCEPT.audio);
      const src = await window.SfFf.pickMedia(accept, cfg.insertFromFiles || 'Aus Dateien einfügen');
      if (!src) return;
      const ext = String(src.name || src.ref || '').split('.').pop().toLowerCase();
      const kind = MEDIA_ACCEPT.video.includes(ext) ? 'video' : (MEDIA_ACCEPT.audio.includes(ext) ? 'audio' : 'image');
      replaceTargetNode = null;
      await uploadObjectAsset(kind, src);
      refreshMediaLibrary();
    }

    refreshBtn?.addEventListener('click', refreshMediaLibrary);
    cleanupBtn?.addEventListener('click', cleanupUnusedMedia);
    document.getElementById('mediaLibraryInsertFf')?.addEventListener('click', insertFromFiles);

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
            setNotesText(next);
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

    document.getElementById('addSlideBtn')?.addEventListener('click', () => deckUi('add_slide', async () => {
      await saveCurrentSlide(true);
      const before = await deckBegin();
      try {
        const res = await api('add_slide', { after_index: einfuegeNach() });
        SF.slides = res.slides;
      } catch (e) {
        if (window.CfStatus) CfStatus.show('error', e.message);
        return;
      }
      await afterDeckChange(SF.slides[SF.currentIndex + 1] ? SF.slides[SF.currentIndex + 1].id : null);
      deckCommit(before);
    }));

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

    /* bg-type-btn + apply_transition_all: Delegation in initRibbonCommandDelegation */

    document.getElementById('bgColorInput').addEventListener('input', (e) => {
      SF.currentBackground = { type: 'color', value: e.target.value };
      applyBackgroundVisual(SF.currentBackground);
      populateBackgroundControls(SF.currentBackground);
      updateCurrentTabSwatch();
      scheduleSave();
    });

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

    document.getElementById('slideGridSelectAllBtn')?.addEventListener('click', () => selectAllSlides());
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
    initNotesEditor();   // 3.12.41: Eingabe, Einfügen, Leiste, Rechtschreibprüfung
    applySpellcheckAttrs(document.getElementById('edTitle'));

    /*
     * Tastenkürzel der Menüband-Befehle (3.12.0): Welche Taste, bestimmt die Person im Hub-Dialog «Ribbon anpassen»
     * (Vorgaben config/ribbon_commands.php); HubRibbon fragt hier zuerst nach der Bedeutung. true = erledigt,
     * 'native' = Taste weiterreichen (Browser bzw. die festen Bühnen-Kürzel unten), sonst klickt der Hub den Knopf.
     */
    const SFS = window.SFShortcuts = window.SFShortcuts || {
      handlers: {},
      register(id, fn) { this.handlers[id] = fn; },
      run(id, e) { const fn = this.handlers[id]; return fn ? fn(e) : undefined; },
    };
    const inField = (e) => !!(window.HubShortcuts && window.HubShortcuts.isEditable(e.target));
    SFS.register('undo', () => { undo(); return true; });
    SFS.register('tool:timeline', () => { if (!SF.canEdit) return 'native'; addShape('timeline'); return true; });
    SFS.register('redo', () => { redo(); return true; });
    /* Im Filmstreifen und im Raster beziehen sich Kopieren/Ausschneiden/Einfügen/Duplizieren auf ganze Folien. */
    SFS.register('copy', () => {
      if (slideShortcutContext()) { copySelectedToClipboard(); return true; }
      if (SF.selectedNode) { copySelected(); return true; }
      return 'native';
    });
    SFS.register('cut', () => {
      if (slideShortcutContext()) { cutSlidesToClipboard(); return true; }
      if (SF.selectedNode) { cutSelected(); return true; }
      return 'native';
    });
    SFS.register('paste', () => {
      if (slideShortcutContext()) { pasteSlidesShortcut(); return true; }
      if (SF.clipboard) { pasteClipboard(); return true; }
      return 'native';
    });
    SFS.register('duplicate', () => {
      if (slideShortcutContext()) { duplicateSlidesShortcut(); return true; }
      if (SF.selectedNode) { duplicateNode(SF.selectedNode); return true; }
      return 'native';
    });
    SFS.register('group', () => { groupSelectedNodes(); return true; });
    SFS.register('ungroup', () => { ungroupSelected(); return true; });
    SFS.register('find_replace', () => { openFindReplace(); return true; });
    /* Texteditor T4 (3.12.37): Ctrl+B/I/U (Vorgaben im Katalog) — im Bearbeiten an Ort auf die Markierung
       (ohne Markierung aufs Objekt), auf der Folienfläche aufs gewählte Textobjekt; sonst dem Feld/Browser lassen. */
    const textFormatKey = (rbId) => (e) => {
      const ie = SF.inlineEdit;
      const inInline = !!(ie && e && e.target && ie.el.contains(e.target));
      /* 3.12.41: im Notizfeld auf die Markierung der Notizen */
      const notesEl = notesField();
      if (notesEl && notesRich() && e && e.target && notesEl.contains(e.target)) {
        notesToggle(RB_INLINE_KEY[rbId]);
        return true;
      }
      if (!inInline && (inField(e) || !ribbonStartTextNode() || !stagePlayContext())) return 'native';
      const btn = document.getElementById(rbId);
      if (!btn || btn.disabled) return 'native';
      btn.click();
      return true;
    };
    SFS.register('text_bold', textFormatKey('rb_bold'));
    SFS.register('text_italic', textFormatKey('rb_italic'));
    SFS.register('text_underline', textFormatKey('rb_underline'));
    /* 3.12.49: Markerfarbe anwenden / Kein Marker (Kürzel frei wählbar) — gleicher Weg wie die Knöpfe im Menüband. */
    const markKey = (clear) => (e) => {
      const notesEl = notesField();
      const inNotes = !!(notesEl && notesRich() && e && e.target && notesEl.contains(e.target));
      const ie = SF.inlineEdit;
      const inInline = !!(ie && e && e.target && ie.el.contains(e.target));
      if (!inNotes && !inInline && (inField(e) || !ribbonStartTextNode() || !stagePlayContext())) return 'native';
      const ctl = document.getElementById(clear ? 'rb_mark_clear' : 'rb_mark');
      if (!ctl || ctl.disabled) return 'native';
      if (clear) ctl.click();
      else ctl.dispatchEvent(new Event('input', { bubbles: true }));
      return true;
    };
    SFS.register('text_mark', markKey(false));
    SFS.register('text_mark_clear', markKey(true));
    /* Entf: im Filmstreifen/Raster Folien löschen, sonst weiter an die Bühne (markierte Objekte löschen). */
    SFS.register('delete_slides', () => {
      if (SF.canEdit && slideShortcutContext()) { deleteSlides(shortcutSlideIndices()); return true; }
      return 'native';
    });
    /* Strg+Umschalt+↑/↓ (3.11.1): markierte Folien um eine Stelle verschieben. */
    const moveSlidesKey = (dir) => () => {
      if (SF.canEdit && slideShortcutContext() && !(document.activeElement && document.activeElement.isContentEditable)) {
        moveSelectedSlides(dir);
        return true;
      }
      return 'native';
    };
    SFS.register('slide_move_up', moveSlidesKey(-1));
    SFS.register('slide_move_down', moveSlidesKey(1));
    /* F5 startet die Präsentation ab der ersten Folie; «Ab aktueller Folie» (Umschalt+F5, Strg+P) ist ein Weiterer Befehl. */
    SFS.register('present_mode', (e) => {
      if (inField(e) || !presentLinkAvailable()) return 'native';
      startPresentation(0);
      return true;
    });

    /*
     * Weitere Befehle (3.12.1, Hub 2.36.3, Urs 27.11.): bisher fest abgefragte Tasten stehen im Katalog (config/ribbon_commands.php,
     * kuerzelOnly) und sind im Dialog «Ribbon anpassen» › «Weitere Befehle» umstellbar. Der Zusammenhang (nur im Filmstreifen, nicht
     * in Eingabefeldern …) wird hier geprüft; passt er nicht, 'native' = Taste weiterreichen. Fest bleiben nur die Pfeiltasten
     * (auch mit Umschalt) zum Verschieben markierter Objekte auf der Folienfläche (Bühnen-Standard) und Tab/Eingabe im Abspielen-Fenster.
     */
    const slideNav = (fn) => (e) => {
      if (inField(e) || SF.editorViewMode === 'grid' || !slideShortcutContext()) return 'native';
      const last = SF.slides.length - 1;
      const next = Math.max(0, Math.min(last, fn(SF.currentIndex, last)));
      if (next !== SF.currentIndex) {
        switchToSlide(next).then(() => {
          document.querySelector('#slideFilmstrip .filmstrip-item.active')?.focus({ preventScroll: true });
        });
      }
      return true;
    };
    /* Ctrl+A im Filmstreifen/Raster: alle Folien; sonst (Feld, Folienfläche) wie gewohnt. */
    SFS.register('slides_select_all', (e) => {
      if (inField(e) || !slideShortcutContext()) return 'native';
      selectAllSlides();
      return true;
    });
    SFS.register('slides_extend_prev', (e) => {
      if (inField(e) || !slideShortcutContext()) return 'native';
      extendSlideSelection(e, -1);
      return true;
    });
    SFS.register('slides_extend_next', (e) => {
      if (inField(e) || !slideShortcutContext()) return 'native';
      extendSlideSelection(e, 1);
      return true;
    });
    SFS.register('slide_prev', slideNav((i) => i - 1));
    SFS.register('slide_next', slideNav((i) => i + 1));
    SFS.register('slide_first', slideNav(() => 0));
    SFS.register('slide_last', slideNav((i, last) => last));
    /* Umschalt+F5 / Strg+P: ab der aktuellen Folie. Lässt sich hier nicht präsentieren, bleibt Strg+P beim Browser (Drucken). */
    SFS.register('present_current', (e) => {
      if (inField(e) || !presentLinkAvailable()) return 'native';
      startPresentation(SF.currentIndex);
      return true;
    });
    /* Zoom der Folie (Vorgabe ohne Kürzel; Strg+Mausrad bleibt). In der Rasteransicht die Grösse der Miniaturen. */
    SFS.register('zoom_in', () => {
      if (SF.editorViewMode === 'grid') applyGridThumbMin(gridThumbMinPx() + 16, { save: true });
      else zoomBy(10);
      return true;
    });
    SFS.register('zoom_out', () => {
      if (SF.editorViewMode === 'grid') applyGridThumbMin(gridThumbMinPx() - 16, { save: true });
      else zoomBy(-10);
      return true;
    });
    SFS.register('zoom_fit', () => {
      if (SF.editorViewMode === 'grid') applyGridThumbMin(168, { save: true });
      else zoomFit();
      return true;
    });
    /* Abspielen per Kürzel (3.12.8, Urs 27.11.: «Kann ich dem kein ShortCut verpassen?»), Vorgabe Ctrl+Enter, im Anpassen-Dialog änderbar. */
    SFS.register('stage_play', () => {
      if (!SF.canEdit || play.el || SF.masterSlideEditing || SF.editorViewMode === 'grid' || !SF.slides[SF.currentIndex]) return 'native';
      openPlayOverlay();
      return true;
    });
    SFS.register('notes_toggle', () => { toggleNotesPanel(); return true; });
    SFS.register('notes_focus', () => {
      const n = document.getElementById('slideNotesInput');
      if (!n || !n.getClientRects().length) return 'native';
      n.focus();
      return true;
    });

    /* Befehl eines eigenen Zusammenhangs (scope) aus dem Katalog: Rasteransicht, Folienfläche, Abspielen. */
    const scopeCmd = (e, scope) => {
      const h = window.SFRibbon && typeof window.SFRibbon.hub === 'function' ? window.SFRibbon.hub() : null;
      return h && typeof h.commandForEvent === 'function' ? h.commandForEvent(e, scope) : '';
    };
    window.SFScopeCmd = scopeCmd;

    /* Zuletzt angeklickter Bereich (3.12.3, Urs 27.11.: «↓↑ … scrollt der Filmstreifen, springt nicht von Folie zu Folie»):
       Nach Klick/Ziehen im Filmstreifen geht der Fokus oft verloren (Einträge werden neu gezeichnet), activeElement = body.
       Die Pfeiltasten wirken deshalb dort, wo zuletzt geklickt oder fokussiert wurde: Filmstreifen (Folienwechsel),
       Folienfläche (Abspielen bzw. Objekte verschieben), Rasteransicht. */
    const keyAreaOf = (t) => {
      if (!t || !t.closest) return 'other';
      if (t.closest('#slideFilmstrip')) return 'film';
      if (t.closest('#slideGridPanel')) return 'grid';
      if (t.closest('#canvasArea')) return 'stage';
      return 'other';
    };
    document.addEventListener('pointerdown', (e) => { SF.keyArea = keyAreaOf(e.target); }, true);
    document.addEventListener('focusin', (e) => {
      if (e.target && e.target !== document.body && e.target !== document.documentElement) SF.keyArea = keyAreaOf(e.target);
    }, true);

    document.addEventListener('keydown', (e) => {
      if (e.defaultPrevented) return;   // vom Menüband (HubRibbon) schon erledigt
      const tag = (document.activeElement && document.activeElement.tagName) || '';
      /* 3.12.36: auch contenteditable (Bearbeiten an Ort) zählt als Eingabefeld — sonst Entf/Pfeile auf die Objekte. */
      const inInput = ['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) || !!(document.activeElement && document.activeElement.isContentEditable);
      /* Rasteransicht (3.11.0, Kürzel seit 3.12.1 im Katalog, scope 'grid'): Esc schliesst (auch aus einem Feld),
         ←/→ eine Folie, ↑/↓ eine Zeile; wählt die Folie wie ein Klick ohne Zusatztaste. */
      if (SF.editorViewMode === 'grid') {
        const g = scopeCmd(e, 'grid');
        if (g === 'grid_close') { e.preventDefault(); closeSlideGridView(); return; }
        const dir = { grid_left: 'ArrowLeft', grid_right: 'ArrowRight', grid_up: 'ArrowUp', grid_down: 'ArrowDown' }[g];
        if (dir && !inInput) { e.preventDefault(); gridArrowMove(dir); return; }
      }
      if (inInput) return;
      /* Folienfläche (3.12.20, Urs 27.11.: «Wenn ich in der Folie bin macht ctrl+a die Markierung für alle Elemente»):
         alle Objekte wählen, Vorgabe Ctrl+A (scope 'stage'; im Filmstreifen/Raster wählt Ctrl+A die Folien). */
      if (stagePlayContext() && scopeCmd(e, 'stage') === 'objects_select_all') {
        e.preventDefault();
        const alle = getTopLevelNodes();
        if (alle.length) selectNodes(alle);
        return;
      }
      /* Ebenen per Kürzel (3.12.31, Urs 27.11.: «Gibt es für die Ebenen hoch und runter auch ein ShortCut?»): Vorgaben wie
         Google Slides Ctrl+↑/↓ eine Ebene, Ctrl+Umschalt+↑/↓ ganz nach vorne/hinten; vor dem Pfeil-Verschieben geprüft. */
      /* 3.12.39 (Urs 27.11.: «verschiebt einfach, da geht nix nach hinten und nach vorne»): nicht an stagePlayContext binden — stand
         der Fokus noch in der Seitenleiste, griff das Pfeil-Verschieben darunter. Gleiche Bedingung wie das Verschieben. */
      if (SF.selectedNodes.length && SF.canEdit && SF.editorViewMode !== 'grid' && !slideShortcutContext()) {
        const lc = scopeCmd(e, 'stage');
        const ebene = { stage_layer_up: 'up', stage_layer_down: 'down', stage_layer_front: 'front', stage_layer_back: 'back' }[lc];
        if (ebene) {
          e.preventDefault();
          const liste = SF.selectedNodes.filter((n) => n.getParent() === SF.layer).slice().sort((a, b) => a.zIndex() - b.zIndex());
          /* Nach oben/vorne von oben her, nach unten/hinten von unten her, damit sich die Auswahl nicht gegenseitig überholt. */
          (ebene === 'up' || ebene === 'back' ? liste.reverse() : liste).forEach((n) => nudgeContentLayer(n, ebene));
          if (SF.transformer) SF.transformer.moveToTop();
          refreshCanvas(); scheduleSave(); renderLayersPanel();
          return;
        }
      }
      /* Folienfläche (scope 'stage'): markierte Objekte löschen (Vorgabe Entf, Rücktaste). */
      if (SF.selectedNodes.length && scopeCmd(e, 'stage') === 'delete_objects') {
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
      /* Folienfläche ohne Markierung (3.12.3, scope 'stage'; 3.12.12 gleiche Vorgaben wie beim Vorführen: Leertaste/Eingabe/→/↓/Bild↓ weiter,
         ←/↑/Bild↑ zurück): Vorgaben spielen die Folie ab (Schritt 0, dann weiter
         über Effekte und Folienübergänge), ←/Bild↑ ab Endzustand einen Schritt zurück. Nicht mit Fokus im Filmstreifen, Menüband oder Panels. */
      if (!SF.selectedNodes.length && !play.el && stagePlayContext()) {
        const sc = scopeCmd(e, 'stage');
        if (sc === 'stage_play_next' || sc === 'stage_play_prev') {
          e.preventDefault();
          openPlayOverlay(sc === 'stage_play_prev' ? 'prev' : 'next');
          return;
        }
      }
      /* Fest (Bühnen-Standard, DESIGN.md Hub § Tastenkürzel 8): Pfeiltasten verschieben markierte Objekte, mit Umschalt um 10 px. */
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
    const notes = getNotesText();
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
    setRibbonCommandsDisabled('undo', SF.historyIndex <= 0 && !SF.deckUndo.length);
    setRibbonCommandsDisabled('redo', SF.historyIndex >= SF.history.length - 1 && !SF.deckRedo.length);
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
    /* Neue Änderung: was bei den Folien-Aktionen zum Wiederholen bereitlag, gilt nicht mehr. */
    SF.deckRedo = [];
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

  /* Ein Verlauf für beides: Jede Folien-Aktion setzt den Folien-Inhaltsverlauf zurück
     (deckCommit), darum liegen Inhaltsschritte immer nach der letzten Folien-Aktion.
     Rückgängig nimmt zuerst die Inhaltsschritte, dann die Folien-Aktionen. */
  function undo() {
    if (!SF.canEdit) return;
    if (SF.historyIndex > 0) {
      SF.historyIndex--;
      restoreHistorySnapshot(SF.history[SF.historyIndex]);
      return;
    }
    /* Läuft ein Folien-Schritt noch, ist sein Eintrag noch nicht im Verlauf: trotzdem einreihen. */
    if (SF.deckUndo.length || SF.deckBusy) deckStep(-1);
  }

  function redo() {
    if (!SF.canEdit) return;
    if (SF.historyIndex < SF.history.length - 1) {
      SF.historyIndex++;
      restoreHistorySnapshot(SF.history[SF.historyIndex]);
      return;
    }
    if (SF.deckRedo.length || SF.deckBusy) deckStep(1);
  }

  // ---------- Verlauf der Folien-Aktionen (3.7.0) ----------
  /* Einfügen, Löschen, Ausschneiden, Duplizieren, Neue Folie, Verschieben und
     Präsentation einfügen legen vor und nach dem Schritt die Folienliste ab.
     Rückgängig stellt Reihenfolge und Bestand über `restore_slides` wieder her;
     Folien, die es noch gibt, behalten ihren heutigen Inhalt, damit spätere
     Bearbeitungen nicht verloren gehen. Medien in assets/ bleiben liegen. */
  const DECK_HISTORY_MAX = 30;

  function cloneSlides(list) {
    return JSON.parse(JSON.stringify(list || []));
  }

  function currentSlideId() {
    return SF.slides[SF.currentIndex] ? SF.slides[SF.currentIndex].id : null;
  }

  /** Vor einer Folien-Aktion: anstehende Änderung speichern, Stand merken. null = nicht aufzeichnen. */
  async function deckBegin() {
    if (!SF.canEdit) return null;
    if (SF.saveTimer) {
      clearTimeout(SF.saveTimer);
      SF.saveTimer = null;
      await saveCurrentSlide(true);
    }
    return { slides: cloneSlides(SF.slides), current: currentSlideId() };
  }

  /** Nach einer gelungenen Folien-Aktion: Schritt in den Verlauf legen. */
  function deckCommit(before) {
    if (!before) return;
    const afterIds = SF.slides.map((s) => s.id).join(',');
    if (afterIds === before.slides.map((s) => s.id).join(',')) return;
    SF.deckUndo.push({
      before: before.slides,
      beforeCurrent: before.current,
      after: cloneSlides(SF.slides),
      afterCurrent: currentSlideId(),
    });
    if (SF.deckUndo.length > DECK_HISTORY_MAX) SF.deckUndo.shift();
    SF.deckRedo = [];
    resetHistoryForCurrentSlide();
  }

  /** Folienliste neu zeichnen nach einer Folien-Aktion; Auswahl = aktuelle Folie. */
  async function afterDeckChange(currentId) {
    let idx = currentId ? SF.slides.findIndex((s) => s.id === currentId) : -1;
    if (idx < 0) idx = Math.min(SF.currentIndex, SF.slides.length - 1);
    if (idx < 0) idx = 0;
    gridSelectedIndices.clear();
    gridSelectedIndices.add(idx);
    gridLastClickedIndex = idx;
    loadSlideIntoStage(idx);
    await renderSlideFilmstrip();
    if (SF.editorViewMode === 'grid') renderSlideGrid();
    mitlaufMelden(true);
  }

  /** dir -1 = Rückgängig, +1 = Wiederholen. Reiht sich hinter laufende Folien-Schritte ein,
      jeder Tastendruck ein Schritt; der Eintrag wird erst beim Ausführen gelesen. */
  function deckStep(dir) {
    return deckUi(null, () => deckStepNow(dir));
  }

  async function deckStepNow(dir) {
    const from = dir < 0 ? SF.deckUndo : SF.deckRedo;
    const to = dir < 0 ? SF.deckRedo : SF.deckUndo;
    const entry = from[from.length - 1];
    if (!entry) return;
    try {
      setSaveStatus('Speichere…');
      if (SF.saveTimer) {
        clearTimeout(SF.saveTimer);
        SF.saveTimer = null;
        await saveCurrentSlide(true);
      }
      const now = cloneSlides(SF.slides);
      const nowById = new Map(now.map((s) => [s.id, s]));
      const target = dir < 0 ? entry.before : entry.after;
      const list = target.map((s) => nowById.get(s.id) || s);
      const res = await api('restore_slides', { slides: list });
      /* Der heutige Stand wird zum Gegenstück, damit Wiederholen auch spätere
         Bearbeitungen zurückbringt. */
      if (dir < 0) entry.after = now;
      else entry.before = now;
      from.pop();
      to.push(entry);
      SF.slides = res.slides;
      await afterDeckChange(dir < 0 ? entry.beforeCurrent : entry.afterCurrent);
      setSaveStatus('Gespeichert');
    } catch (e) {
      setSaveStatus('Fehler', true);
      if (window.CfStatus) CfStatus.show('error', e.message);
    }
    updateUndoRedoButtons();
  }

  // ---------- Saving ----------
  function scheduleSave() {
    setSaveStatus('Ungespeicherte Änderungen…');
    clearTimeout(SF.saveTimer);
    SF.saveTimer = setTimeout(() => saveCurrentSlide(false), 700);
  }
  /* Speichern in FileForge (sf-ff.js): zuerst die laufende Folienänderung zum Server bringen. */
  window.SFEditor = {
    flush: async function () {
      if (SF.saveTimer) {
        clearTimeout(SF.saveTimer);
        SF.saveTimer = null;
        await saveCurrentSlide(true);
      }
      /* Läuft gerade ein Speichern, auch darauf warten (Abspielen lädt sonst den alten Stand, 3.11.0). */
      if (SF.saveRun) await SF.saveRun;
    },
    /* Dialog «Auf Gerät (Mitlaufen)», Schalter «Animationen beim Folienwechsel abspielen» (3.11.0, sf-ff.js). */
    mitAnim: function () { return !!SF.mitAnim; },
    setMitAnim: function (on) {
      SF.mitAnim = !!on;
      persistEditorUi({});
      mitlaufMelden(false);
    },
  };

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
    const notes = getNotesText();
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
      /* Id ist massgeblich; index bleibt nur als Rückfall für alte Stände. */
      const payload = { index: SF.currentIndex, slide_id: (slideMeta && slideMeta.id) || '', background, objects, transition, autoAdvance, notes };
      if (layoutKey !== null) payload.layoutKey = layoutKey;
      if (label !== null) payload.label = label;
      if (layoutSetSlideId !== null) payload.layoutSetSlideId = layoutSetSlideId;
      const run = api('save_slide', payload);
      SF.saveRun = run.then(() => {}, () => {});
      await run;
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
      if (!SF.skipPreviewReload) mitlaufMelden(true);
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
    initExportModal();
    initFindReplace();
    initNotesOutline();
  }

  document.addEventListener('DOMContentLoaded', init);

  /* ---- Folien-Zwischenablage ------------------------------------------------
     Ganze Folien in eine andere Präsentation übernehmen. Die Ablage liegt
     benutzerbezogen auf dem Server, damit sie den Wechsel der Präsentation und
     des Geräts übersteht. Bilder werden erst beim Einfügen in die Zielpräsentation
     kopiert — siehe SlideClipboard::fetchFor(). */
  let clipEntries = [];

  async function clipUserApi(action, payload) {
    const res = await fetch('user_api.php?action=' + encodeURIComponent(action), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': SF.csrfToken },
      body: JSON.stringify(payload || {})
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.ok === false) throw new Error(data.error || 'Fehler');
    return data;
  }

  function renderClipList() {
    const box = document.getElementById('slideClipList');
    if (!box) return;
    if (!clipEntries.length) {
      box.innerHTML = '<p class="slide-clip-empty">' + escapeHtml(I.clipEmpty || '') + '</p>';
      return;
    }
    box.innerHTML = clipEntries.map((e) => {
      const w = e.width || 1920;
      const h = e.height || 1080;
      /* Miniatur wie in der Import-Vorschau: das gerenderte Folien-HTML in einem
         foreignObject, damit es auf die Kachelgrösse skaliert. */
      const thumb = e.thumb
        ? '<span class="slide-clip-thumb">'
          + '<svg viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="xMidYMid slice" width="100%" height="100%">'
          + '<foreignObject width="' + w + '" height="' + h + '">'
          + '<div xmlns="http://www.w3.org/1999/xhtml" style="width:100%;height:100%;">' + e.thumb + '</div>'
          + '</foreignObject></svg></span>'
        : '';
      return '<label class="slide-clip-item">'
        + '<input type="checkbox" value="' + escapeHtml(e.id) + '" checked>'
        + thumb
        + '<span class="slide-clip-text">'
        + '<span class="slide-clip-title">' + escapeHtml(e.title || '') + '</span>'
        + '<span class="slide-clip-source">' + escapeHtml(I.clipFrom || '') + ' ' + escapeHtml(e.source_title || '') + '</span>'
        + '</span></label>';
    }).join('');
  }

  function clipCheckedIds() {
    const box = document.getElementById('slideClipList');
    if (!box) return [];
    return [...box.querySelectorAll('input[type=checkbox]:checked')].map((c) => c.value);
  }

  /* Liste nachladen — beim ersten Öffnen des Reiters und nach jedem Kopieren. */
  async function refreshClipboard() {
    try {
      const data = await clipUserApi('clip_list', {});
      clipEntries = data.clipboard || [];
      renderClipList();
    } catch (e) {
      if (window.CfStatus) CfStatus.show('error', e.message);
    }
  }

  async function copySelectedToClipboard() {
    /* Im Raster zählt die Mehrfachauswahl, sonst die Folie, an der gerade
       gearbeitet wird — so heisst „zum Clipboard hinzufügen" in beiden Ansichten
       dasselbe. */
    const indices = shortcutSlideIndices();
    if (!indices.length) {
      if (window.CfStatus) CfStatus.show('info', I.clipSelectNone || '');
      return;
    }
    try {
      const data = await api('clip_copy_slides', { indices });
      clipEntries = data.clipboard || [];
      renderClipList();
      if (window.CfStatus) {
        CfStatus.show('ok', (I.clipCopied || '{n}').replace('{n}', String(data.copied || indices.length)));
      }
    } catch (e) {
      if (window.CfStatus) CfStatus.show('error', e.message);
    }
  }


  /* ---- Suchen und Ersetzen -------------------------------------------------
     Gesucht wird auf dem Server über alle Folien, nicht nur über die geladene:
     der Editor hält immer nur die aktuelle Folie als Konva-Bühne. Das Ergebnis
     ist eine Liste von Folien; ein Klick darauf springt hin. */

  let findLastMatches = [];

  function findReplaceModal() {
    return document.getElementById('findReplaceModal');
  }

  function openFindReplace() {
    const modal = findReplaceModal();
    if (!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    const input = document.getElementById('findSearchInput');
    if (input) { input.focus(); input.select(); }
  }

  function closeFindReplace() {
    const modal = findReplaceModal();
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
  }

  function findReplaceParams() {
    return {
      search: (document.getElementById('findSearchInput')?.value || ''),
      case_sensitive: !!document.getElementById('findCaseSensitive')?.checked,
      include_notes: !!document.getElementById('findIncludeNotes')?.checked,
    };
  }

  function renderFindResults(matches) {
    findLastMatches = matches || [];
    const box = document.getElementById('findReplaceResults');
    const status = document.getElementById('findReplaceStatus');
    if (!box) return;
    box.innerHTML = '';
    if (findLastMatches.length === 0) {
      if (status) status.textContent = I.findNoMatches || '';
      return;
    }
    if (status) {
      status.textContent = (I.findMatches || '{n}').replace('{n}', String(findLastMatches.length));
    }
    findLastMatches.forEach((m) => {
      const row = document.createElement('button');
      row.type = 'button';
      row.className = 'find-replace-hit';
      row.title = I.findGoto || '';
      row.innerHTML = '<span class="find-replace-hit-no">' + (m.index + 1) + '</span>'
        + '<span class="find-replace-hit-text"><span class="find-replace-hit-label"></span>'
        + '<span class="find-replace-hit-excerpt"></span></span>'
        + '<span class="find-replace-hit-count">' + (I.findHits || '{n}').replace('{n}', String(m.count)) + '</span>';
      row.querySelector('.find-replace-hit-label').textContent = m.label || '';
      row.querySelector('.find-replace-hit-excerpt').textContent = m.excerpt || '';
      row.addEventListener('click', async () => {
        await switchToSlide(m.index);
      });
      box.appendChild(row);
    });
  }

  function runFind() {
    return deckUi(null, () => runFindNow());
  }

  async function runFindNow() {
    const p = findReplaceParams();
    const status = document.getElementById('findReplaceStatus');
    if (p.search === '') {
      if (status) status.textContent = '';
      renderFindResults([]);
      return;
    }
    try {
      const data = await api('find_replace', p);
      renderFindResults(data.matches || []);
    } catch (e) {
      if (status) status.textContent = e.message;
    }
  }

  async function runReplace() {
    const p = findReplaceParams();
    if (p.search === '') return;
    const ok = await SFDialog.confirm(I.findConfirmReplace || '', { danger: true });
    if (!ok) return;
    p.replace = document.getElementById('findReplaceInput')?.value || '';
    await deckUi('find_replace', () => runReplaceNow(p));
  }

  async function runReplaceNow(p) {
    const status = document.getElementById('findReplaceStatus');
    try {
      const data = await api('find_replace', p);
      if (data.slides) {
        SF.slides = data.slides;
        loadSlideIntoStage(Math.min(SF.currentIndex, SF.slides.length - 1));
        await renderSlideFilmstrip();
        if (SF.editorViewMode === 'grid') renderSlideGrid();
        mitlaufMelden(true);
      }
      renderFindResults([]);
      const text = (I.findReplaced || '{n}').replace('{n}', String(data.replaced || 0));
      if (status) status.textContent = text;
      if (window.CfStatus) CfStatus.show('ok', text);
    } catch (e) {
      if (status) status.textContent = e.message;
      if (window.CfStatus) CfStatus.show('error', e.message);
    }
  }

  /* Stellt die Gliederung am Anfang der Notizen zwischen den beiden Schreibweisen um —
     für Präsentationen, die vor dieser Wahlmöglichkeit importiert wurden, und um es sich
     nachträglich anders zu überlegen. Beide Richtungen, darum ein Dialog statt zweier
     Knöpfe im Menüband. */

  function notesOutlineModal() {
    return document.getElementById('notesOutlineModal');
  }

  function openNotesOutline() {
    const modal = notesOutlineModal();
    if (!modal) return;
    const status = document.getElementById('notesOutlineStatus');
    if (status) status.textContent = '';
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeNotesOutline() {
    const modal = notesOutlineModal();
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
  }

  function applyNotesOutlineStyle(style) {
    return deckUi('notes_outline_style', () => applyNotesOutlineStyleNow(style));
  }

  async function applyNotesOutlineStyleNow(style) {
    const status = document.getElementById('notesOutlineStatus');
    setSaveStatus('Stelle Notizen um…');
    try {
      const data = await api('notes_outline_style', { style });
      SF.slides = data.slides;
      loadSlideIntoStage(Math.min(SF.currentIndex, SF.slides.length - 1), true);
      await renderSlideFilmstrip();
      if (SF.editorViewMode === 'grid') renderSlideGrid();
      mitlaufMelden(true);
      setSaveStatus('Gespeichert');
      const text = (I.notesOutlineDone || '{n}').replace('{n}', String(data.changed || 0));
      if (status) status.textContent = text;
      if (window.CfStatus) CfStatus.show('ok', text);
    } catch (e) {
      setSaveStatus('Fehler beim Umstellen', true);
      if (status) status.textContent = e.message;
      if (window.CfStatus) CfStatus.show('error', e.message);
    }
  }

  function initNotesOutline() {
    document.getElementById('notesOutlineClose')?.addEventListener('click', closeNotesOutline);
    document.getElementById('notesOutlineDone')?.addEventListener('click', closeNotesOutline);
    notesOutlineModal()?.addEventListener('click', (e) => {
      if (e.target === notesOutlineModal()) closeNotesOutline();
    });
    notesOutlineModal()?.querySelectorAll('[data-outline-style]').forEach((btn) => {
      btn.addEventListener('click', () => applyNotesOutlineStyle(btn.dataset.outlineStyle));
    });
    document.getElementById('notesToPathBtn')?.addEventListener('click', openNotesOutline);
  }

  function initFindReplace() {
    document.getElementById('findReplaceClose')?.addEventListener('click', closeFindReplace);
    document.getElementById('findReplaceDone')?.addEventListener('click', closeFindReplace);
    document.getElementById('findDoSearchBtn')?.addEventListener('click', runFind);
    document.getElementById('findDoReplaceBtn')?.addEventListener('click', runReplace);
    findReplaceModal()?.addEventListener('click', (e) => {
      if (e.target === findReplaceModal()) closeFindReplace();
    });
    document.getElementById('findSearchInput')?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); runFind(); }
    });
    document.getElementById('findReplaceBtn')?.addEventListener('click', openFindReplace);
  }


  /* ---- Tastenkürzel im Filmstreifen ---------------------------------------
     Dieselben Tasten wie auf der Folienfläche, aber auf ganze Folien bezogen:
     Strg+C/X/V/D. Massgeblich ist, wo die Eingabemarke steht — liegt sie im
     Filmstreifen (oder ist die Rasteransicht offen), gelten die Folien, sonst
     weiterhin das angewählte Objekt. Darum haben die Einträge `tabindex`. */

  /* Pfeiltasten in der Rasteransicht (3.11.0): Zeilenlänge aus der Lage der Karten (gleiche Oberkante = eine Zeile). */
  /* Auswahl mit Umschalt+Pfeil erweitern (3.12.27, Urs 27.11.): von der zuletzt angeklickten Folie (Anker) bis zum
     wandernden Ende, im Filmstreifen ±1, im Raster ←/→ ±1 und ↑/↓ je eine Zeile. Die offene Folie bleibt. */
  function gridPerRow() {
    const cards = [...document.querySelectorAll('#slideGrid .editor-slide-grid-item')];
    if (!cards.length) return 1;
    const top0 = cards[0].offsetTop;
    const n = cards.findIndex((c) => c.offsetTop !== top0);
    return n < 1 ? cards.length : n;
  }
  function extendSlideSelection(e, richtung) {
    const last = SF.slides.length - 1;
    if (last < 0) return;
    const anker = gridLastClickedIndex !== null ? gridLastClickedIndex : SF.currentIndex;
    if (SF.selEnd === undefined || SF.selEnd === null || !gridSelectedIndices.has(SF.selEnd)) SF.selEnd = anker;
    const zeile = SF.editorViewMode === 'grid' && (e.key === 'ArrowUp' || e.key === 'ArrowDown') ? gridPerRow() : 1;
    SF.selEnd = Math.max(0, Math.min(last, SF.selEnd + richtung * zeile));
    gridSelectedIndices.clear();
    for (let i = Math.min(anker, SF.selEnd); i <= Math.max(anker, SF.selEnd); i++) gridSelectedIndices.add(i);
    if (SF.editorViewMode === 'grid') {
      document.querySelectorAll('.editor-slide-grid-item').forEach((el) => {
        el.classList.toggle('selected', gridSelectedIndices.has(parseInt(el.dataset.index, 10)));
      });
      updateGridSelectionUi();
      const card = document.querySelector('#slideGrid .editor-slide-grid-item[data-index="' + SF.selEnd + '"]');
      if (card) { card.focus({ preventScroll: true }); card.scrollIntoView({ block: 'nearest' }); }
    } else {
      updateFilmstripSelection();
      const items = document.querySelectorAll('#slideFilmstrip .editor-filmstrip-item');
      const item = [...items].find((it) => SF.slides.findIndex((s) => s.id === it.dataset.id) === SF.selEnd);
      if (item) item.scrollIntoView({ block: 'nearest' });
    }
  }

  function gridArrowMove(key) {
    const cards = [...document.querySelectorAll('#slideGrid .editor-slide-grid-item')];
    if (!cards.length) return;
    const ae = document.activeElement && document.activeElement.closest && document.activeElement.closest('.editor-slide-grid-item');
    let from = ae ? parseInt(ae.dataset.index, 10) : (gridLastClickedIndex !== null ? gridLastClickedIndex : SF.currentIndex);
    if (!Number.isFinite(from)) from = 0;
    const top0 = cards[0].offsetTop;
    let perRow = cards.findIndex((c) => c.offsetTop !== top0);
    if (perRow < 1) perRow = cards.length;
    let to = from;
    if (key === 'ArrowLeft') to -= 1;
    else if (key === 'ArrowRight') to += 1;
    else if (key === 'ArrowUp') to -= perRow;
    else to += perRow;
    to = Math.max(0, Math.min(cards.length - 1, to));
    gridSelectedIndices.clear();
    gridSelectedIndices.add(to);
    gridLastClickedIndex = to;
    syncRibbonTransitionFieldsFromSlide(to);
    cards.forEach((el) => {
      el.classList.toggle('selected', parseInt(el.dataset.index, 10) === to);
    });
    updateGridSelectionUi();
    cards[to].focus({ preventScroll: true });
    cards[to].scrollIntoView({ block: 'nearest' });
  }

  /* Fokus auf der Folienfläche (oder nirgends), Folienansicht, kein Masterfolien-Bearbeiten, kein offener Dialog. */
  function stagePlayContext() {
    if (!SF.canEdit || SF.editorViewMode === 'grid' || SF.masterSlideEditing) return false;
    const ae = document.activeElement;
    if (ae && ae.isContentEditable) return false;
    const noFocus = !ae || ae === document.body || ae === document.documentElement;
    if (noFocus ? SF.keyArea !== 'stage' : !(ae.closest && ae.closest('#canvasArea'))) return false;
    if (document.querySelector('[aria-modal="true"]:not([hidden])')) return false;
    return !!SF.slides[SF.currentIndex];
  }

  function slideShortcutContext() {
    if (SF.editorViewMode === 'grid') return true;
    const ae = document.activeElement;
    if (ae && ae.closest && ae.closest('#slideFilmstrip')) return true;
    /* Fokus verloren (body), zuletzt im Filmstreifen geklickt: Folien-Kürzel wirken dort (3.12.3). */
    return SF.keyArea === 'film' && (!ae || ae === document.body || ae === document.documentElement);
  }

  /** Folien, auf die sich ein Folien-Tastenkürzel bezieht. */
  /* Einfügen (Folie, Zwischenablage, Präsentation) nach der gewählten Folie, bei mehreren nach der letzten — im Raster
     wie im Filmstreifen (3.12.22, Urs 27.11.). */
  function einfuegeNach() {
    const ziel = shortcutSlideIndices();
    return ziel.length ? ziel[ziel.length - 1] : SF.currentIndex;
  }

  function shortcutSlideIndices() {
    /* Raster und Folienleiste teilen dieselbe Auswahl (3.7.0). */
    let indices = [...gridSelectedIndices].filter((i) => i >= 0 && i < SF.slides.length);
    if (!indices.length) indices = [SF.currentIndex];
    return indices.sort((a, b) => a - b);
  }

  function cutSlidesToClipboard() {
    return deckUi('cut_slides', () => cutSlidesToClipboardNow());
  }

  async function cutSlidesToClipboardNow() {
    const indices = shortcutSlideIndices();
    if (!indices.length) return;
    if (indices.length >= SF.slides.length) {
      await SFDialog.alert('Die letzte verbleibende Folie kann nicht ausgeschnitten werden.');
      return;
    }
    const before = await deckBegin();
    const currentId = currentSlideId();
    const removedIds = new Set(indices.map((i) => SF.slides[i].id));
    try {
      setSaveStatus('Speichere…');
      /* Erst in die Ablage, dann löschen — scheitert das Kopieren, bleibt alles
         stehen. Gelöscht wird von hinten nach vorn, sonst verschieben sich die
         Nummern der noch zu löschenden Folien. */
      const data = await api('clip_copy_slides', { indices });
      clipEntries = data.clipboard || [];
      let slides = null;
      for (const i of [...indices].reverse()) {
        const res = await api('delete_slide', { index: i });
        slides = res.slides;
      }
      if (slides) SF.slides = slides;
      if (SF.currentIndex >= SF.slides.length) SF.currentIndex = SF.slides.length - 1;
      await afterDeckChange(removedIds.has(currentId) ? null : currentId);
      deckCommit(before);
      renderClipList();
      setSaveStatus('Gespeichert');
      if (window.CfStatus) {
        CfStatus.show('ok', (I.clipCut || '{n}').replace('{n}', String(indices.length)));
      }
    } catch (e) {
      setSaveStatus('Fehler beim Ausschneiden', true);
      if (window.CfStatus) CfStatus.show('error', e.message);
    }
  }

  /** Strg+V: die angehakten Einträge, sonst der zuletzt kopierte. */
  /** Ribbon «Präsentation einfügen»: .pptx/.odp/.xlsx/.chs/.zip/.html/.cfs ab der gewählten Folie einfügen.
      Datei im FileForge-Dialog des Hubs wählen (Urs 27.11.: «FF Piker verwenden»; Hochladen vom Rechner bietet der Dialog),
      api.php holt sie als die Person über AppCall. */
  const INSERT_PRES_ACCEPT = ['pptx', 'odp', 'xlsx', 'chs', 'zip', 'html', 'htm', 'cfs'];
  async function insertPresentationFile() {
    if (!SF.canEdit) return;
    if (!window.SfFf || !window.SfFf.pickMedia) {
      if (window.CfStatus) CfStatus.show('error', 'Der Dateidialog von FileForge ist nicht erreichbar.');
      return;
    }
    const src = await window.SfFf.pickMedia(INSERT_PRES_ACCEPT, I.insertPresTitle || 'Präsentation einfügen');
    if (!src) return;
    await deckUi('insert_presentation_file', () => insertPresentationFileNow(src));
  }

  async function insertPresentationFileNow(src) {
    const before = await deckBegin();
    try {
      if (window.CfStatus) CfStatus.show('info', I.insertPresBusy || '…');
      const data = await api('insert_presentation_file', { ref: src.ref, after_index: einfuegeNach() });
      SF.slides = data.slides;
      await afterDeckChange(currentSlideId());
      deckCommit(before);
      if (window.CfStatus) CfStatus.show('ok', (I.insertPresDone || '{n}').replace('{n}', String(data.inserted || 0)));
      (data.warnings || []).forEach((w) => { if (window.CfStatus) CfStatus.show('info', w); });
    } catch (e) {
      if (window.CfStatus) CfStatus.show('error', e.message);
    }
  }

  function pasteSlidesShortcut() {
    return deckUi('clip_paste_slides', () => pasteSlidesShortcutNow());
  }

  async function pasteSlidesShortcutNow() {
    let ids = clipCheckedIds();
    if (!ids.length && clipEntries.length) {
      ids = [clipEntries[0].id];
    }
    if (!ids.length) {
      if (window.CfStatus) CfStatus.show('info', I.clipEmpty || '');
      return;
    }
    try {
      const before = await deckBegin();
      const data = await api('clip_paste_slides', { clip_ids: ids, after_index: einfuegeNach() });
      SF.slides = data.slides;
      await afterDeckChange(currentSlideId());
      deckCommit(before);
      if (window.CfStatus) {
        CfStatus.show('ok', (I.clipPasted || '{n}').replace('{n}', String(ids.length)));
      }
      (data.warnings || []).forEach((w) => { if (window.CfStatus) CfStatus.show('info', w); });
    } catch (e) {
      if (window.CfStatus) CfStatus.show('error', e.message);
    }
  }

  async function duplicateSlidesShortcut() {
    await duplicateSlides(shortcutSlideIndices());
  }

  function pasteFromClipboard() {
    return deckUi('clip_paste_slides', () => pasteFromClipboardNow());
  }

  async function pasteFromClipboardNow() {
    const ids = clipCheckedIds();
    if (!ids.length) {
      if (window.CfStatus) CfStatus.show('info', I.clipSelectNone || '');
      return;
    }
    try {
      const before = await deckBegin();
      const data = await api('clip_paste_slides', { clip_ids: ids, after_index: einfuegeNach() });
      SF.slides = data.slides;
      await afterDeckChange(currentSlideId());
      deckCommit(before);
      if (window.CfStatus) {
        CfStatus.show('ok', (I.clipPasted || '{n}').replace('{n}', String(ids.length)));
      }
      (data.warnings || []).forEach((w) => { if (window.CfStatus) CfStatus.show('info', w); });
    } catch (e) {
      if (window.CfStatus) CfStatus.show('error', e.message);
    }
  }

  document.getElementById('slideClipCopyBtn')?.addEventListener('click', copySelectedToClipboard);
  document.getElementById('slideClipPasteBtn')?.addEventListener('click', pasteFromClipboard);
  document.getElementById('slideClipClearBtn')?.addEventListener('click', async () => {
    try {
      await clipUserApi('clip_clear', { csrf_token: SF.csrfToken });
      clipEntries = [];
      renderClipList();
    } catch (e) {
      if (window.CfStatus) CfStatus.show('error', e.message);
    }
  });

})();

