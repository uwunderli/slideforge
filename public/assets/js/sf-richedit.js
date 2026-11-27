/*
 * SfRichEdit — Bearbeiten an Ort mit sichtbarer Formatierung (Texteditor T3/T4, 3.12.36).
 *
 * Arbeitet auf dem Zeilenmodell von SfMarkup.readLines(): [{kind:'p'|'h'|'li', level, color, runs}],
 * Stellen als {l: Zeile, ch: Zeichen}. Eingriffe (Enter, Einfügen, Formatieren der Markierung) ändern
 * das Modell und zeichnen das Feld neu mit SfMarkup.linesToHtml() — so bleibt das HTML im Feld immer
 * im Muster von toHtml(), und fromHtml() liest es sauber zurück (kein execCommand-HTML).
 * Normales Tippen bleibt beim Browser.
 */
(function (root) {
    'use strict';

    var M = root.SfMarkup || (typeof require === 'function' ? require('./sf-markup.js') : null);

    function cloneRun(r) {
        var c = M.emptyRun(r.text);
        M.RUN_KEYS.forEach(function (k) { c[k] = r[k]; });
        return c;
    }
    function cloneLines(lines) {
        return lines.map(function (ln) {
            return { kind: ln.kind, level: ln.level || 0, color: ln.color || null, runs: ln.runs.map(cloneRun) };
        });
    }
    function lineLen(ln) {
        return ln.runs.reduce(function (n, r) { return n + r.text.length; }, 0);
    }
    function cmp(a, b) {
        return a.l !== b.l ? a.l - b.l : a.ch - b.ch;
    }
    function clampPos(lines, p) {
        if (!lines.length) return { l: 0, ch: 0 };
        var l = Math.max(0, Math.min(lines.length - 1, p.l | 0));
        return { l: l, ch: Math.max(0, Math.min(lineLen(lines[l]), p.ch | 0)) };
    }
    function order(lines, a, b) {
        a = clampPos(lines, a); b = clampPos(lines, b);
        return cmp(a, b) <= 0 ? [a, b] : [b, a];
    }
    function merge(lines) {
        lines.forEach(function (ln) {
            var out = [];
            ln.runs.forEach(function (r) {
                if (r.text === '') return;
                var last = out[out.length - 1];
                if (last && M.sameProps(last, r)) last.text += r.text;
                else out.push(r);
            });
            ln.runs = out;
        });
        return lines;
    }
    /** Lauf-Grenze bei ch erzwingen; liefert den Index des ersten Laufs ab ch. */
    function splitAt(ln, ch) {
        var pos = 0;
        for (var i = 0; i < ln.runs.length; i++) {
            var r = ln.runs[i];
            var len = r.text.length;
            if (ch <= pos) return i;
            if (ch < pos + len) {
                var right = cloneRun(r);
                right.text = r.text.slice(ch - pos);
                r.text = r.text.slice(0, ch - pos);
                ln.runs.splice(i + 1, 0, right);
                return i + 1;
            }
            pos += len;
        }
        return ln.runs.length;
    }
    function forRange(lines, a, b, fn) {
        for (var l = a.l; l <= b.l; l++) {
            var ln = lines[l];
            var s = l === a.l ? a.ch : 0;
            var e = l === b.l ? b.ch : lineLen(ln);
            if (s >= e) continue;
            var i1 = splitAt(ln, s);
            var i2 = splitAt(ln, e);
            for (var i = i1; i < i2; i++) fn(ln.runs[i], ln);
        }
    }
    function every(lines, a, b, pred) {
        var all = true, any = false;
        forRange(cloneLines(lines), a, b, function (r) { any = true; if (!pred(r)) all = false; });
        return any && all;
    }
    function isEmptyRange(lines, a, b) {
        var p = order(lines, a, b);
        return cmp(p[0], p[1]) === 0;
    }

    /** Schalter (b, i, u, s, upper, sc) auf der Markierung: alle an → aus, sonst an. */
    function toggle(lines, a, b, key) {
        var p = order(lines, a, b);
        var out = cloneLines(lines);
        var on = !every(out, p[0], p[1], function (r) { return !!r[key]; });
        forRange(out, p[0], p[1], function (r) { r[key] = on; });
        return merge(out);
    }
    /** Wert (color, mark, size) auf der Markierung. toggle: haben schon alle diesen Wert → entfernen.
     *  Der Wert null entfernt. */
    function setValue(lines, a, b, key, val, toggleSame) {
        var p = order(lines, a, b);
        var out = cloneLines(lines);
        if (val != null && toggleSame && every(out, p[0], p[1], function (r) { return r[key] === val; })) val = null;
        forRange(out, p[0], p[1], function (r) { r[key] = val == null ? null : val; });
        return merge(out);
    }
    /** Eigenschaften an einer Stelle: Zeichen vor der Stelle, am Zeilenanfang das Zeichen danach. */
    function propsAt(lines, p, forward) {
        if (!lines.length) return M.emptyRun('');
        p = clampPos(lines, p);
        var ln = lines[p.l];
        var target = (forward || p.ch === 0) ? p.ch : p.ch - 1;
        var pos = 0;
        for (var i = 0; i < ln.runs.length; i++) {
            var len = ln.runs[i].text.length;
            if (target < pos + len) return ln.runs[i];
            pos += len;
        }
        return ln.runs.length ? ln.runs[ln.runs.length - 1] : M.emptyRun('');
    }
    function deleteRange(lines, a, b) {
        var p = order(lines, a, b);
        var out = cloneLines(lines);
        if (!out.length || cmp(p[0], p[1]) === 0) return { lines: out, pos: p[0] };
        var A = out[p[0].l], B = out[p[1].l];
        var keepA = A.runs.slice(0, splitAt(A, p[0].ch));
        var keepB = B.runs.slice(splitAt(B, p[1].ch));
        A.runs = keepA.concat(keepB);
        out.splice(p[0].l + 1, p[1].l - p[0].l);
        return { lines: merge(out), pos: p[0] };
    }
    /** Reinen Text an einer Stelle einfügen (Zeilenumbrüche → neue Zeilen, Format der Stelle). */
    function insertText(lines, at, text, props) {
        var out = cloneLines(lines);
        if (!out.length) out.push({ kind: 'p', level: 0, color: null, runs: [] });
        at = clampPos(out, at);
        var parts = String(text).replace(/​/g, '').replace(/ /g, ' ').split(/\r\n|\r|\n/);
        var ln = out[at.l];
        var i = splitAt(ln, at.ch);
        var tail = ln.runs.splice(i);
        function mk(t) {
            var r = M.emptyRun(t);
            if (props) M.RUN_KEYS.forEach(function (k) { if (k !== 'text') r[k] = props[k]; });
            return r;
        }
        ln.runs.push(mk(parts[0]));
        var cur = ln, l = at.l, ch = at.ch + parts[0].length;
        for (var k = 1; k < parts.length; k++) {
            cur = { kind: ln.kind === 'li' ? 'li' : 'p', level: 0, color: ln.kind === 'li' ? ln.color : null, runs: [mk(parts[k])] };
            out.splice(++l, 0, cur);
            ch = parts[k].length;
        }
        cur.runs = cur.runs.concat(tail);
        return { lines: merge(out), pos: { l: l, ch: ch } };
    }
    /** Enter wie in Word: Zeile teilen; Listenpunkt → neuer Listenpunkt; leerer Listenpunkt → Liste endet. */
    function splitLine(lines, at) {
        var out = cloneLines(lines);
        if (!out.length) out.push({ kind: 'p', level: 0, color: null, runs: [] });
        at = clampPos(out, at);
        var ln = out[at.l];
        if (ln.kind === 'li' && lineLen(ln) === 0) {
            ln.kind = 'p';
            ln.color = null;
            return { lines: out, pos: { l: at.l, ch: 0 } };
        }
        var tail = ln.runs.splice(splitAt(ln, at.ch));
        var kind = ln.kind === 'li' ? 'li' : (ln.kind === 'h' && tail.length ? 'h' : 'p');
        out.splice(at.l + 1, 0, { kind: kind, level: kind === 'h' ? ln.level : 0, color: kind === 'li' ? ln.color : null, runs: tail });
        return { lines: merge(out), pos: { l: at.l + 1, ch: 0 } };
    }

    /* ---------- DOM ---------- */
    /** Stelle {l, ch} im Feld (je Zeile ein <div>, wie linesToHtml) als {node, offset}. */
    function domPoint(el, p) {
        var rows = el.children;
        if (!rows.length) return { node: el, offset: el.childNodes.length };
        var row = rows[Math.max(0, Math.min(rows.length - 1, p.l))];
        var doc = el.ownerDocument;
        var walker = doc.createTreeWalker(row, 4 /* NodeFilter.SHOW_TEXT */, null);
        var acc = 0, t, last = null;
        while ((t = walker.nextNode())) {
            var len = t.nodeValue.length;
            if (p.ch <= acc + len) return { node: t, offset: p.ch - acc };
            acc += len;
            last = t;
        }
        return last ? { node: last, offset: last.nodeValue.length } : { node: row, offset: 0 };
    }
    /** Markierung im Feld als {lines, a, b} (a = Anfang, b = Ende) oder null, wenn sie nicht im Feld liegt. */
    function readSelection(el, base) {
        var sel = el.ownerDocument.getSelection ? el.ownerDocument.getSelection() : null;
        if (!sel || !sel.rangeCount) return null;
        var rg = sel.getRangeAt(0);
        if (!el.contains(rg.startContainer) || !el.contains(rg.endContainer)) return null;
        var pa = { node: rg.startContainer, offset: rg.startOffset };
        var pb = { node: rg.endContainer, offset: rg.endOffset };
        var lines = M.readLines(el, { points: [pa, pb], base: base });
        if (pa.l === undefined) pa = { l: 0, ch: 0 };
        if (pb.l === undefined) pb = { l: pa.l, ch: pa.ch };
        var o = order(lines, { l: pa.l, ch: pa.ch }, { l: pb.l, ch: pb.ch });
        return { lines: lines, a: o[0], b: o[1] };
    }
    /** Feld neu aus den Zeilen zeichnen und die Markierung setzen (nur wenn setSel). */
    function render(el, lines, a, b, setSel) {
        if (!lines.length) lines = [{ kind: 'p', level: 0, color: null, runs: [] }];
        el.innerHTML = M.linesToHtml(lines);
        if (!setSel) return;
        var sel = el.ownerDocument.getSelection();
        if (!sel) return;
        var pa = domPoint(el, a), pb = domPoint(el, b || a);
        var rg = el.ownerDocument.createRange();
        rg.setStart(pa.node, pa.offset);
        rg.setEnd(pb.node, pb.offset);
        sel.removeAllRanges();
        sel.addRange(rg);
    }

    var api = {
        lineLen: lineLen,
        cmp: cmp,
        toggle: toggle,
        setValue: setValue,
        propsAt: propsAt,
        deleteRange: deleteRange,
        insertText: insertText,
        splitLine: splitLine,
        isEmptyRange: isEmptyRange,
        domPoint: domPoint,
        readSelection: readSelection,
        render: render
    };
    root.SfRichEdit = api;
    if (typeof module === 'object' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : this);
