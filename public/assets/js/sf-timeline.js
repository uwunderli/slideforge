/*
 * SfTimeline — Zeitstrahl-Objekt (type 'timeline', SlideForge 3.12.48).
 *
 * Ein Objekt mit Ereignissen auf einem waagrechten Strahl: Punkt auf dem Strahl, Bezeichnung auf der einen Seite,
 * Blase (Titel + Text) auf der anderen, verbunden mit einer Linie. Gleich verteilt, bei side 'auto' abwechselnd oben/unten.
 *
 * Die Berechnung (layout) ist hier und in src/TimelineRenderer.php GLEICH aufgebaut: dieselben Zahlen, derselbe
 * Zeilenumbruch nach geschätzter Zeichenbreite (keine Messung im Browser), dieselben Pfade. So zeigen Bühne und
 * Präsentation das gleiche Bild. Wer hier etwas ändert, ändert es dort mit.
 *
 * Modell: {events:[{label, sub, title, text, cat, side:'auto'|'top'|'bottom', order}], cats:[{id,name,color}],
 *          showLegend, bubble:{shape:'rect'|'round'|'pill'|'speech', borderWidth, borderDash:'solid'|'dashed'|'dotted',
 *          fillAlpha, radius}, axis:{color,width,arrow,dot}, font:{label,title,text,family,color,labelColor},
 *          anim:{effect,duration,delay,trigger:'click'|'auto',drawAxis}}  (anim/order: Schritt 3, noch ohne Wirkung)
 *
 *   SfTimeline.create(I)              → neues Modell mit drei Beispiel-Ereignissen
 *   SfTimeline.normalize(d)           → bereinigtes Modell (Vorgaben ergänzt, Werte begrenzt)
 *   SfTimeline.layout(d, W, H)        → [Zeichenteil]  {k:'path'|'line'|'circle'|'text', …}
 *   SfTimeline.draw(group, d, W, H)   → zeichnet in eine Konva.Group
 *   SfTimeline.panel(el, d, opts)     → Eingabeliste im Reiter «Zeitstrahl»; opts {I, onChange(rebuild)}
 */
(function () {
  'use strict';

  var HEX = /^#[0-9a-f]{6}$/i;
  var SIDES = ['auto', 'top', 'bottom'];
  var SHAPES = ['rect', 'round', 'pill', 'speech'];
  var DASHES = ['solid', 'dashed', 'dotted'];

  function num(v, def, min, max) {
    var n = Number(v);
    if (!isFinite(n)) n = def;
    return Math.max(min, Math.min(max, n));
  }
  function str(v, max) {
    return (v === null || v === undefined) ? '' : String(v).slice(0, max || 2000);
  }
  function color(v, def) {
    return HEX.test(String(v || '')) ? String(v) : def;
  }
  function pick(v, list, def) {
    return list.indexOf(v) !== -1 ? v : def;
  }
  function r1(v) {
    return Math.round(v * 10) / 10;
  }

  function defaults() {
    return {
      events: [],
      cats: [{ id: 'c1', name: '', color: '#4a90d9' }],
      showLegend: false,
      bubble: { shape: 'round', borderWidth: 3, borderDash: 'solid', fillAlpha: 0.2, radius: 14 },
      axis: { color: '#ffffff', width: 6, arrow: true, dot: 24 },
      font: { label: 32, title: 28, text: 24, family: 'Open Sans', color: '#ffffff', labelColor: '#f5b041' },
      anim: { effect: 'fade', duration: 500, delay: 800, trigger: 'click', drawAxis: true },
    };
  }

  function normalize(d) {
    d = d || {};
    var def = defaults();
    var out = {};
    var cats = Array.isArray(d.cats) ? d.cats : def.cats;
    out.cats = [];
    cats.slice(0, 20).forEach(function (c, i) {
      if (!c || typeof c !== 'object') return;
      var id = /^[a-z0-9_-]{1,24}$/i.test(String(c.id || '')) ? String(c.id) : 'c' + (i + 1);
      out.cats.push({ id: id, name: str(c.name, 80), color: color(c.color, '#4a90d9') });
    });
    if (!out.cats.length) out.cats = def.cats;
    var ids = out.cats.map(function (c) { return c.id; });
    var evs = Array.isArray(d.events) ? d.events : [];
    out.events = evs.slice(0, 60).map(function (e, i) {
      e = (e && typeof e === 'object') ? e : {};
      return {
        label: str(e.label, 200), sub: str(e.sub, 200), title: str(e.title, 300), text: str(e.text, 2000),
        cat: ids.indexOf(e.cat) !== -1 ? e.cat : ids[0],
        side: pick(e.side, SIDES, 'auto'),
        order: Math.round(num(e.order, i + 1, 1, 999)),
      };
    });
    out.showLegend = !!d.showLegend;
    var b = d.bubble || {}, a = d.axis || {}, f = d.font || {}, an = d.anim || {};
    out.bubble = {
      shape: pick(b.shape, SHAPES, def.bubble.shape),
      borderWidth: num(b.borderWidth, def.bubble.borderWidth, 0, 30),
      borderDash: pick(b.borderDash, DASHES, 'solid'),
      fillAlpha: num(b.fillAlpha, def.bubble.fillAlpha, 0, 1),
      radius: num(b.radius, def.bubble.radius, 0, 200),
    };
    out.axis = {
      color: color(a.color, def.axis.color),
      width: num(a.width, def.axis.width, 1, 40),
      arrow: a.arrow === undefined ? true : !!a.arrow,
      dot: num(a.dot, def.axis.dot, 0, 120),
    };
    out.font = {
      label: num(f.label, def.font.label, 8, 200),
      title: num(f.title, def.font.title, 8, 200),
      text: num(f.text, def.font.text, 8, 200),
      family: /^[A-Za-z0-9 _-]{1,60}$/.test(String(f.family || '')) ? String(f.family) : def.font.family,
      color: color(f.color, def.font.color),
      labelColor: color(f.labelColor, def.font.labelColor),
    };
    out.anim = {
      effect: /^[a-z-]{1,24}$/.test(String(an.effect || '')) ? String(an.effect) : def.anim.effect,
      duration: Math.round(num(an.duration, def.anim.duration, 0, 10000)),
      delay: Math.round(num(an.delay, def.anim.delay, 0, 60000)),
      trigger: an.trigger === 'auto' ? 'auto' : 'click',
      drawAxis: an.drawAxis === undefined ? true : !!an.drawAxis,
    };
    return out;
  }

  function create(I) {
    var T = (I && I.tl) || {};
    var d = defaults();
    d.cats = [
      { id: 'c1', name: T.sampleCat1 || 'Ereignis', color: '#4a90d9' },
      { id: 'c2', name: T.sampleCat2 || 'Person', color: '#87b42b' },
    ];
    var title = T.sampleTitle || 'Titel', text = T.sampleText || 'Kurzer Text zum Ereignis';
    d.events = [
      { label: '1500', sub: '', title: title + ' 1', text: text, cat: 'c1', side: 'auto', order: 1 },
      { label: '1600', sub: '', title: title + ' 2', text: text, cat: 'c2', side: 'auto', order: 2 },
      { label: '1700', sub: '', title: title + ' 3', text: text, cat: 'c1', side: 'auto', order: 3 },
    ];
    return normalize(d);
  }

  /* Zeilenumbruch nach geschätzter Zeichenbreite (fett 0.6, sonst 0.55 der Schriftgrösse). Gleich in PHP. */
  function wrap(text, maxW, size, bold) {
    text = String(text || '');
    if (text.replace(/\s+/g, '') === '') return [];
    var maxChars = Math.max(1, Math.floor(maxW / (size * (bold ? 0.6 : 0.55))));
    var lines = [];
    text.replace(/\r\n?/g, '\n').split('\n').forEach(function (para) {
      var words = para.split(/\s+/).filter(function (w) { return w !== ''; });
      if (!words.length) { lines.push(''); return; }
      var line = '';
      words.forEach(function (w) {
        var chars = Array.from(w);
        while (chars.length > maxChars) {
          if (line !== '') { lines.push(line); line = ''; }
          lines.push(chars.slice(0, maxChars).join(''));
          chars = chars.slice(maxChars);
        }
        w = chars.join('');
        if (w === '') return;
        if (line === '') line = w;
        else if (Array.from(line).length + 1 + chars.length <= maxChars) line += ' ' + w;
        else { lines.push(line); line = w; }
      });
      if (line !== '') lines.push(line);
    });
    return lines;
  }

  function rgba(hex, a) {
    var z = parseInt(String(hex).slice(1), 16);
    return 'rgba(' + ((z >> 16) & 255) + ',' + ((z >> 8) & 255) + ',' + (z & 255) + ',' + (Math.round(a * 1000) / 1000) + ')';
  }

  /* Umriss einer Blase; tail = {side:'top'|'bottom', cx, half, tip} für die Sprechblase. Gleich in PHP. */
  function bubblePath(x, y, w, h, r, tail) {
    r = Math.max(0, Math.min(r, w / 2, h / 2));
    var p = [];
    var arc = function (ex, ey) { if (r > 0) p.push('A' + r1(r) + ' ' + r1(r) + ' 0 0 1 ' + r1(ex) + ' ' + r1(ey)); };
    p.push('M' + r1(x + r) + ' ' + r1(y));
    if (tail && tail.side === 'top') {
      p.push('L' + r1(tail.cx - tail.half) + ' ' + r1(y), 'L' + r1(tail.cx) + ' ' + r1(tail.tip), 'L' + r1(tail.cx + tail.half) + ' ' + r1(y));
    }
    p.push('L' + r1(x + w - r) + ' ' + r1(y)); arc(x + w, y + r);
    p.push('L' + r1(x + w) + ' ' + r1(y + h - r)); arc(x + w - r, y + h);
    if (tail && tail.side === 'bottom') {
      p.push('L' + r1(tail.cx + tail.half) + ' ' + r1(y + h), 'L' + r1(tail.cx) + ' ' + r1(tail.tip), 'L' + r1(tail.cx - tail.half) + ' ' + r1(y + h));
    }
    p.push('L' + r1(x + r) + ' ' + r1(y + h)); arc(x, y + h - r);
    p.push('L' + r1(x) + ' ' + r1(y + r)); arc(x + r, y);
    p.push('Z');
    return p.join(' ');
  }

  function dashOf(style, bw) {
    if (style === 'dashed') return [r1(bw * 3), r1(bw * 2)];
    if (style === 'dotted') return [0.1, r1(bw * 2)];
    return null;
  }

  function layout(d, W, H) {
    d = normalize(d);
    W = Math.max(40, Number(W) || 1600);
    H = Math.max(40, Number(H) || 700);
    var f = d.font, ax = d.axis, b = d.bubble;
    var catOf = {};
    d.cats.forEach(function (c) { catOf[c.id] = c; });
    var out = [];
    var n = d.events.length;
    var legendH = (d.showLegend && d.cats.length) ? Math.round(f.text * 1.8) : 0;
    var areaH = H - legendH;
    var axisY = r1(areaH / 2);
    var aw = ax.width;
    var arrowLen = ax.arrow ? Math.max(14, aw * 3.5) : 0;
    var slot = n ? (W - arrowLen) / n : W;
    var dotR = ax.dot / 2;
    var gap = Math.max(12, f.text * 0.6);
    var stem = Math.max(dotR + 24, areaH * 0.12);
    var bwid = b.borderWidth;
    var dash = dashOf(b.borderDash, bwid);

    var evs = d.events.map(function (e, i) {
      var side = e.side === 'top' || e.side === 'bottom' ? e.side : (i % 2 === 0 ? 'top' : 'bottom');
      return { e: e, i: i, cx: slot * (i + 0.5), side: side, cat: catOf[e.cat] || d.cats[0] };
    });

    var axisEnd = W - arrowLen;
    out.push({ k: 'line', x1: 0, y1: axisY, x2: r1(ax.arrow ? axisEnd + 1 : W), y2: axisY, stroke: ax.color, sw: aw, part: 'axis' });
    if (ax.arrow) {
      var ah = arrowLen * 0.6;
      out.push({ k: 'path', d: 'M' + r1(axisEnd) + ' ' + r1(axisY - ah) + ' L' + r1(W) + ' ' + axisY + ' L' + r1(axisEnd) + ' ' + r1(axisY + ah) + ' Z',
        fill: ax.color, stroke: null, sw: 0, dash: null, part: 'axis' });
    }

    evs.forEach(function (v) {
      var near = Infinity;
      evs.forEach(function (o) { if (o !== v && o.side === v.side) near = Math.min(near, Math.abs(o.cx - v.cx)); });
      var bw = Math.max(60, Math.min(near - gap, W * 0.4));
      var bx = Math.max(0, Math.min(W - bw, v.cx - bw / 2));
      var pad = Math.round(f.text * 0.55);
      var tw = bw - 2 * pad;
      var tLines = wrap(v.e.title, tw, f.title, true);
      var xLines = wrap(v.e.text, tw, f.text, false);
      var lhT = f.title * 1.2, lhX = f.text * 1.25;
      var contentH = tLines.length * lhT + (tLines.length && xLines.length ? f.text * 0.35 : 0) + xLines.length * lhX;
      var bh = Math.max(contentH + 2 * pad, f.text * 1.6);
      var by;
      if (v.side === 'top') {
        by = Math.max(0, axisY - stem - bh);
        if (by + bh > axisY - dotR - 4) by = axisY - dotR - 4 - bh;
      } else {
        by = axisY + stem;
        if (by + bh > areaH) by = Math.max(axisY + dotR + 4, areaH - bh);
      }
      var edge = v.side === 'top' ? by + bh : by;
      var col = v.cat.color;
      var radius = b.shape === 'rect' ? 0 : (b.shape === 'pill' ? Math.min(bh, bw) / 2 : b.radius);
      var tail = null, lineFrom = edge;
      if (b.shape === 'speech') {
        var room = Math.abs(axisY - edge) - dotR;
        var th = Math.min(Math.max(8, room) * 0.6, 40);
        var half = Math.min(bw * 0.08, 18) + 6;
        var r = Math.min(radius, bw / 2, bh / 2);
        var tcx = Math.max(bx + r + half, Math.min(bx + bw - r - half, v.cx));
        var tip = v.side === 'top' ? edge + th : edge - th;
        tail = { side: v.side === 'top' ? 'bottom' : 'top', cx: tcx, half: half, tip: tip };
        lineFrom = tip;
      }
      v.box = { x: bx, y: by, w: bw, h: bh };
      out.push({ k: 'line', x1: r1(v.cx), y1: r1(lineFrom), x2: r1(v.cx), y2: axisY, stroke: col, sw: Math.max(1, bwid || 2), part: 'stem', ev: v.i });
      out.push({ k: 'path', d: bubblePath(bx, by, bw, bh, radius, tail), fill: rgba(col, b.fillAlpha),
        stroke: bwid > 0 ? col : null, sw: bwid, dash: dash, part: 'bubble', ev: v.i });
      var ty = by + (bh - contentH) / 2;
      tLines.forEach(function (ln) {
        out.push({ k: 'text', x: r1(bx + pad), y: r1(ty + (lhT - f.title) / 2), w: r1(tw), text: ln, size: f.title, bold: true, color: f.color, align: 'center', family: f.family, part: 'bubble', ev: v.i });
        ty += lhT;
      });
      if (tLines.length && xLines.length) ty += f.text * 0.35;
      xLines.forEach(function (ln) {
        out.push({ k: 'text', x: r1(bx + pad), y: r1(ty + (lhX - f.text) / 2), w: r1(tw), text: ln, size: f.text, bold: false, color: f.color, align: 'center', family: f.family, part: 'bubble', ev: v.i });
        ty += lhX;
      });

      /* Bezeichnung auf der anderen Seite des Strahls */
      var lw = slot * 0.96;
      var lLines = wrap(v.e.label, lw, f.label, true);
      var sSize = Math.round(f.label * 0.65);
      var sLines = wrap(v.e.sub, lw, sSize, false);
      var blockH = lLines.length * f.label * 1.15 + sLines.length * sSize * 1.2;
      var off = dotR + Math.max(8, f.label * 0.3);
      var ly = v.side === 'top' ? axisY + off : axisY - off - blockH;
      lLines.forEach(function (ln) {
        out.push({ k: 'text', x: r1(v.cx - lw / 2), y: r1(ly + f.label * 0.075), w: r1(lw), text: ln, size: f.label, bold: true, color: f.labelColor, align: 'center', family: f.family, part: 'label', ev: v.i });
        ly += f.label * 1.15;
      });
      sLines.forEach(function (ln) {
        out.push({ k: 'text', x: r1(v.cx - lw / 2), y: r1(ly + sSize * 0.1), w: r1(lw), text: ln, size: sSize, bold: false, color: f.labelColor, align: 'center', family: f.family, part: 'label', ev: v.i });
        ly += sSize * 1.2;
      });
    });

    /* Punkte zuletzt, damit sie über Strahl und Verbindungslinie liegen */
    if (dotR > 0) {
      evs.forEach(function (v) {
        out.push({ k: 'circle', cx: r1(v.cx), cy: axisY, r: r1(dotR), fill: v.cat.color, stroke: ax.color, sw: Math.max(1, Math.round(aw * 0.4)), part: 'dot', ev: v.i });
      });
    }

    if (legendH) {
      var ls = f.text * 0.9, sw = Math.round(ls * 0.9), gapL = Math.round(ls * 1.2);
      var items = d.cats.map(function (c) {
        var tw2 = Array.from(c.name || '').length * ls * 0.55;
        return { c: c, w: sw + 8 + tw2 };
      });
      var total = items.reduce(function (s, it) { return s + it.w; }, 0) + gapL * Math.max(0, items.length - 1);
      var lx = Math.max(0, (W - total) / 2);
      var lyc = areaH + legendH / 2;
      items.forEach(function (it) {
        out.push({ k: 'path', d: bubblePath(lx, lyc - sw / 2, sw, sw, 3, null), fill: rgba(it.c.color, Math.max(0.35, b.fillAlpha)),
          stroke: it.c.color, sw: 2, dash: null, part: 'legend' });
        out.push({ k: 'text', x: r1(lx + sw + 8), y: r1(lyc - ls / 2), w: r1(it.w - sw - 8 + 4), text: it.c.name, size: ls, bold: false, color: f.color, align: 'left', family: f.family, part: 'legend' });
        lx += it.w + gapL;
      });
    }
    return out;
  }

  /* ---------- Bühne (Konva) ---------- */
  function draw(group, d, W, H) {
    var K = window.Konva;
    group.destroyChildren();
    group.add(new K.Rect({ name: 'sf-tl-hit', x: 0, y: 0, width: W, height: H, fill: 'rgba(0,0,0,0.001)' }));
    layout(d, W, H).forEach(function (p) {
      var node;
      if (p.k === 'line') {
        node = new K.Line({ points: [p.x1, p.y1, p.x2, p.y2], stroke: p.stroke, strokeWidth: p.sw, lineCap: 'butt' });
      } else if (p.k === 'path') {
        node = new K.Path({ data: p.d, fill: p.fill || undefined, stroke: p.stroke || undefined, strokeWidth: p.stroke ? p.sw : 0,
          dash: p.dash || undefined, lineCap: p.dash && p.dash[0] < 1 ? 'round' : 'butt', lineJoin: 'round' });
      } else if (p.k === 'circle') {
        node = new K.Circle({ x: p.cx, y: p.cy, radius: p.r, fill: p.fill, stroke: p.stroke, strokeWidth: p.sw });
      } else {
        node = new K.Text({ x: p.x, y: p.y, width: p.w, text: p.text, fontSize: p.size, fontFamily: p.family,
          fontStyle: p.bold ? 'bold' : 'normal', fill: p.color, align: p.align, lineHeight: 1, wrap: 'none' });
      }
      node.listening(false);
      group.add(node);
    });
  }

  /* ---------- Reiter «Zeitstrahl» ---------- */
  function esc(s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  var ICON = {
    grip: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>',
    up: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="M6 11l6-6 6 6"/></svg>',
    down: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="M6 13l6 6 6-6"/></svg>',
    del: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12"/><path d="M9 7V4h6v3"/></svg>',
  };

  function iconBtn(act, i, title, icon, disabled) {
    return '<button type="button" class="hub-icon-btn sf-tl-icon-btn" data-tl-act="' + act + '" data-i="' + i + '" title="' + esc(title)
      + '" aria-label="' + esc(title) + '"' + (disabled ? ' disabled' : '') + '>' + icon + '</button>';
  }

  /**
   * Eingabeliste. d ist das Modell des Knotens (wird an Ort geändert). opts.onChange(rebuild): Bühne neu zeichnen und
   * speichern; rebuild = true, wenn sich die Liste selbst geändert hat (Zeile dazu/weg/verschoben) und der Reiter neu aufgebaut werden muss.
   */
  function panel(el, d, opts) {
    var I = (opts && opts.I) || {};
    var T = I.tl || {};
    var onChange = (opts && opts.onChange) || function () {};
    var n = d.events.length;
    var html = '<div class="sf-tl-panel">';
    html += '<div class="options-subtitle">' + esc(T.events || 'Ereignisse') + '</div>';
    html += '<ol class="sf-tl-list">';
    d.events.forEach(function (e, i) {
      var catOpts = d.cats.map(function (c, ci) {
        return '<option value="' + esc(c.id) + '"' + (c.id === e.cat ? ' selected' : '') + '>' + esc(c.name || ((T.category || 'Kategorie') + ' ' + (ci + 1))) + '</option>';
      }).join('');
      var sideOpts = SIDES.map(function (s) {
        var lab = s === 'top' ? (T.sideTop || 'oben') : (s === 'bottom' ? (T.sideBottom || 'unten') : (T.sideAuto || 'automatisch'));
        return '<option value="' + s + '"' + (s === e.side ? ' selected' : '') + '>' + esc(lab) + '</option>';
      }).join('');
      html += '<li class="sf-tl-row" data-i="' + i + '">'
        + '<div class="sf-tl-row-head">'
        + '<span class="sf-tl-grip" data-tl-grip title="' + esc(T.drag || 'Ziehen zum Verschieben') + '" aria-hidden="true">' + ICON.grip + '</span>'
        + '<span class="sf-tl-num">' + (i + 1) + '</span>'
        + '<input type="text" data-f="label" data-i="' + i + '" value="' + esc(e.label) + '" placeholder="' + esc(T.label || 'Bezeichnung') + '" aria-label="' + esc(T.label || 'Bezeichnung') + '" title="' + esc(T.labelHint || '') + '">'
        + iconBtn('up', i, T.moveUp || 'Nach oben', ICON.up, i === 0)
        + iconBtn('down', i, T.moveDown || 'Nach unten', ICON.down, i === n - 1)
        + iconBtn('del', i, T.remove || 'Ereignis löschen', ICON.del, false)
        + '</div>'
        + '<input type="text" data-f="sub" data-i="' + i + '" value="' + esc(e.sub) + '" placeholder="' + esc(T.sub || 'Zusatz (klein)') + '" aria-label="' + esc(T.sub || 'Zusatz (klein)') + '">'
        + '<input type="text" data-f="title" data-i="' + i + '" value="' + esc(e.title) + '" placeholder="' + esc(T.title || 'Titel') + '" aria-label="' + esc(T.title || 'Titel') + '">'
        + '<textarea data-f="text" data-i="' + i + '" rows="2" placeholder="' + esc(T.text || 'Text der Blase') + '" aria-label="' + esc(T.text || 'Text der Blase') + '">' + esc(e.text) + '</textarea>'
        + '<div class="sf-tl-pair">'
        + '<select data-f="cat" data-i="' + i + '" aria-label="' + esc(T.category || 'Kategorie') + '" title="' + esc(T.category || 'Kategorie') + '">' + catOpts + '</select>'
        + '<select data-f="side" data-i="' + i + '" aria-label="' + esc(T.side || 'Seite') + '" title="' + esc(T.side || 'Seite') + '">' + sideOpts + '</select>'
        + '</div>'
        + '</li>';
    });
    html += '</ol>';
    if (!n) html += '<div class="props-empty">' + esc(T.empty || 'Noch keine Ereignisse.') + '</div>';
    html += '<button type="button" class="button button-ghost button-sm hub-btn hub-btn--ghost hub-btn--sm sf-tl-add" data-tl-act="add" title="' + esc(T.addHint || T.add || '') + '">' + esc(T.add || '+ Ereignis') + '</button>';
    html += '<div class="props-video-note">' + esc(T.hint || '') + '</div>';
    if (opts && typeof opts.extraHtml === 'function') html += opts.extraHtml(d, esc);
    html += '</div>';
    el.innerHTML = html;

    var list = el.querySelector('.sf-tl-list');
    el.querySelectorAll('[data-f]').forEach(function (inp) {
      var evName = inp.tagName === 'SELECT' ? 'change' : 'input';
      inp.addEventListener(evName, function () {
        var i = parseInt(inp.dataset.i, 10);
        if (!d.events[i]) return;
        d.events[i][inp.dataset.f] = inp.value;
        onChange(false);
      });
    });
    function move(from, to) {
      if (to < 0 || to >= d.events.length || from === to) return;
      var ev = d.events.splice(from, 1)[0];
      d.events.splice(to, 0, ev);
      onChange(true, { focus: to });
    }
    el.querySelectorAll('[data-tl-act]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var i = parseInt(btn.dataset.i, 10);
        var act = btn.dataset.tlAct;
        if (act === 'up') move(i, i - 1);
        else if (act === 'down') move(i, i + 1);
        else if (act === 'del') { d.events.splice(i, 1); onChange(true); }
        else if (act === 'add') {
          var last = d.events[d.events.length - 1];
          d.events.push({ label: '', sub: '', title: '', text: '', cat: last ? last.cat : d.cats[0].id, side: 'auto', order: d.events.length + 1 });
          onChange(true, { focus: d.events.length - 1 });
        }
      });
    });
    /* Ziehen: nur am Griff (sonst liessen sich die Felder nicht mehr markieren) */
    var dragFrom = -1;
    if (list) {
      list.querySelectorAll('.sf-tl-row').forEach(function (row) {
        var grip = row.querySelector('[data-tl-grip]');
        grip.addEventListener('mousedown', function () { row.draggable = true; });
        row.addEventListener('dragstart', function (ev) {
          dragFrom = parseInt(row.dataset.i, 10);
          row.classList.add('is-dragging');
          try { ev.dataTransfer.effectAllowed = 'move'; ev.dataTransfer.setData('text/plain', String(dragFrom)); } catch (e) { /* alt */ }
        });
        row.addEventListener('dragend', function () {
          row.draggable = false;
          row.classList.remove('is-dragging');
          list.querySelectorAll('.is-drop').forEach(function (r) { r.classList.remove('is-drop'); });
        });
        row.addEventListener('dragover', function (ev) {
          if (dragFrom < 0) return;
          ev.preventDefault();
          list.querySelectorAll('.is-drop').forEach(function (r) { r.classList.remove('is-drop'); });
          row.classList.add('is-drop');
        });
        row.addEventListener('drop', function (ev) {
          ev.preventDefault();
          var to = parseInt(row.dataset.i, 10);
          var from = dragFrom;
          dragFrom = -1;
          move(from, to);
        });
      });
    }
    if (opts && typeof opts.wireExtra === 'function') opts.wireExtra(el, d, onChange);
    if (opts && opts.focus !== undefined) {
      var f = el.querySelector('input[data-f="label"][data-i="' + opts.focus + '"]');
      if (f) f.focus();
    }
  }

  window.SfTimeline = { create: create, normalize: normalize, layout: layout, draw: draw, panel: panel, wrap: wrap, esc: esc };
})();
