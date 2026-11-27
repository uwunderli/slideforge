/*
 * SfMarkup — die Text-Auszeichnung der Textobjekte in JavaScript (SlideForge 3.12.32).
 *
 * Gespeichert wird ein Textobjekt weiter als Auszeichnung im Feld `text`
 * (**fett**, *kursiv*, ++unter++, ~~durch~~, [color=#hex]…[/color], [mark=#hex]…[/mark],
 * [size=NN]…[/size], [upper]…[/upper], [sc]…[/sc], `code`, [Text](https://…), Listen «- »/«* »,
 * Überschriften «#»…«######»). Die Regeln sind die von `src/Markdown.php` — und zwar wörtlich:
 * parse() lässt dieselben regulären Ausdrücke in derselben Reihenfolge über den escapeten Text
 * laufen wie Markdown::inline() und liest das entstandene HTML danach in Läufe zurück. So bleiben
 * auch Sonderfälle (überkreuzte Zeichen, ** vor *, [color] um Links) gleich wie in der Präsentation.
 *
 *   SfMarkup.parse(text)    → [{type:'p'|'h'|'li'|'blank', level, scale, color, runs:[Lauf]}]
 *                              Lauf = {text, b, i, u, s, code, color, mark, upper, sc, size, href}
 *   SfMarkup.toHtml(text)   → HTML zum Bearbeiten (escaped), eine <div> je Zeile
 *   SfMarkup.fromHtml(el)   → Auszeichnung aus einem DOM-Element (oder HTML-Text) zurück
 *   SfMarkup.plainText(text)→ reiner Text ohne Zeichen (Zeilen mit \n)
 *
 * Stabil: parse(fromHtml(toHtml(x))) ergibt dieselben Läufe wie parse(x).
 * Leere Zeilen kommen als {type:'blank'} mit; Markdown.php gibt für sie nichts aus
 * (die Bühne lässt sie darum ebenfalls weg), für das Bearbeiten bleiben sie erhalten.
 */
(function (root) {
    'use strict';

    var HEADING_SCALE = { 1: 1.6, 2: 1.35, 3: 1.15, 4: 1.05, 5: 1, 6: 0.9 };
    // \s in PCRE ohne /u = ASCII-Leerraum; JavaScripts \s kennt mehr. Für gleiches Verhalten ausgeschrieben.
    var S = '[ \\t\\n\\x0B\\f\\r]';
    var RE_HEADING = new RegExp('^(#{1,6})' + S + '*(.+)$');
    var RE_LIST_COLOR_FIX = new RegExp('^(' + S + '*)\\[(color|mark)=(#[0-9a-fA-F]{6})\\]' + S + '*([-*])' + S + '+(.+?)\\[\\/\\2\\]' + S + '*$', 'is');
    var RE_LIST = new RegExp('^' + S + '*[-*]' + S + '+(.+)$');
    var RE_LI_COLOR = /^\[color=(#[0-9a-fA-F]{6})\](.+)\[\/color\]$/is;
    var RUN_KEYS = ['b', 'i', 'u', 's', 'code', 'color', 'mark', 'upper', 'sc', 'size', 'href'];

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function unesc(s) {
        return String(s).replace(/&(#x?[0-9a-fA-F]+|amp|lt|gt|quot|apos|nbsp);/g, function (m, e) {
            if (e === 'amp') return '&';
            if (e === 'lt') return '<';
            if (e === 'gt') return '>';
            if (e === 'quot') return '"';
            if (e === 'apos') return "'";
            if (e === 'nbsp') return ' ';
            var n = e.charAt(1) === 'x' || e.charAt(1) === 'X' ? parseInt(e.slice(2), 16) : parseInt(e.slice(1), 10);
            return isFinite(n) ? String.fromCodePoint(n) : m;
        });
    }
    function clampSize(n) {
        n = parseInt(n, 10);
        if (!isFinite(n)) return null;
        return Math.max(6, Math.min(400, n));
    }
    function isBlankLine(line) {
        return /^[ \t\n\r\0\x0B]*$/.test(line); // PHP trim()
    }
    function emptyRun(text) {
        return { text: text || '', b: false, i: false, u: false, s: false, code: false,
            color: null, mark: null, upper: false, sc: false, size: null, href: null };
    }
    function sameProps(a, b) {
        for (var k = 0; k < RUN_KEYS.length; k++) {
            if (a[RUN_KEYS[k]] !== b[RUN_KEYS[k]]) return false;
        }
        return true;
    }
    function mergeRuns(runs) {
        var out = [];
        runs.forEach(function (r) {
            if (r.text === '') return;
            var last = out[out.length - 1];
            if (last && sameProps(last, r)) last.text += r.text;
            else out.push(r);
        });
        return out;
    }

    /* ---------- Markdown::inline() wörtlich (auf escapetem Text) ---------- */
    function inlineHtml(escaped) {
        escaped = escaped.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+|mailto:[^\s)]+)\)/g, function (m, t, u) {
            return '<a href="' + u + '" target="_blank" rel="noopener noreferrer" style="color:inherit; text-decoration:underline;">' + t + '</a>';
        });
        escaped = escaped.replace(/\[color=(#[0-9a-fA-F]{6})\](.+?)\[\/color\]/gis, function (m, c, t) {
            return '<span style="color:' + esc(c) + ';">' + t + '</span>';
        });
        escaped = escaped.replace(/\[mark=(#[0-9a-fA-F]{6})\](.+?)\[\/mark\]/gis, function (m, c, t) {
            return '<span style="background:' + esc(c) + '; border-radius:2px; padding:0 2px;">' + t + '</span>';
        });
        escaped = escaped.replace(/\[size=(\d{1,4})\](.+?)\[\/size\]/gis, function (m, n, t) {
            return '<span style="font-size:' + clampSize(n) + 'px;">' + t + '</span>';
        });
        escaped = escaped.replace(/\*\*(.+?)\*\*/gs, '<strong>$1</strong>');
        escaped = escaped.replace(/__(.+?)__/gs, '<strong>$1</strong>');
        escaped = escaped.replace(/\*(.+?)\*/gs, '<em>$1</em>');
        escaped = escaped.replace(/(?<![a-zA-Z0-9])_(.+?)_(?![a-zA-Z0-9])/gs, '<em>$1</em>');
        escaped = escaped.replace(/\+\+(.+?)\+\+/gs, '<u>$1</u>');
        escaped = escaped.replace(/~~(.+?)~~/gs, '<s>$1</s>');
        escaped = escaped.replace(/\[upper\](.+?)\[\/upper\]/gis, '<span style="text-transform:uppercase;">$1</span>');
        escaped = escaped.replace(/\[sc\](.+?)\[\/sc\]/gis, '<span style="font-variant:small-caps;">$1</span>');
        escaped = escaped.replace(/`(.+?)`/gs, '<code>$1</code>');
        return escaped;
    }

    /* ---------- Farben / Stil-Angaben lesen ---------- */
    function normHex(v) {
        if (!v) return null;
        v = String(v).trim();
        var m = /^#([0-9a-fA-F]{6})$/.exec(v);
        if (m) return '#' + m[1];
        m = /^#([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])$/.exec(v);
        if (m) return ('#' + m[1] + m[1] + m[2] + m[2] + m[3] + m[3]).toLowerCase();
        m = /^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+)\s*)?\)$/i.exec(v);
        if (m) {
            if (m[4] !== undefined && parseFloat(m[4]) === 0) return null;
            return '#' + [m[1], m[2], m[3]].map(function (x) {
                var n = Math.max(0, Math.min(255, parseInt(x, 10)));
                return (n < 16 ? '0' : '') + n.toString(16);
            }).join('');
        }
        return null;
    }
    function parseStyle(str) {
        var out = {};
        String(str || '').split(';').forEach(function (decl) {
            var i = decl.indexOf(':');
            if (i < 0) return;
            var k = decl.slice(0, i).trim().toLowerCase();
            var v = decl.slice(i + 1).trim();
            if (k) out[k] = v;
        });
        return out;
    }
    /** Was ein Inline-Element zum Lauf beiträgt (gleich für parse() und fromHtml()). */
    function tagProps(tag, getAttr) {
        var p = {};
        if (tag === 'strong' || tag === 'b') p.b = true;
        if (tag === 'em' || tag === 'i') p.i = true;
        if (tag === 'u' || tag === 'ins') p.u = true;
        if (tag === 's' || tag === 'strike' || tag === 'del') p.s = true;
        if (tag === 'code') p.code = true;
        if (tag === 'a') {
            var href = getAttr('href');
            if (href) p.href = unesc(href);
        }
        if (tag === 'mark') p.mark = '#ffff00';
        var st = parseStyle(getAttr('style'));
        if (st.color && st.color !== 'inherit') {
            var c = normHex(st.color);
            if (c) p.color = c;
        }
        var bg = st['background-color'] || st.background;
        if (bg) {
            var mk = normHex(bg.split(/\s+/)[0]);
            if (mk) p.mark = mk;
        }
        if (st['font-size']) {
            var fm = /^(\d+(?:\.\d+)?)px$/i.exec(st['font-size']);
            if (fm) p.size = clampSize(Math.round(parseFloat(fm[1])));
        }
        if (st['font-weight'] && (st['font-weight'] === 'bold' || parseInt(st['font-weight'], 10) >= 600)) p.b = true;
        if (st['font-style'] === 'italic') p.i = true;
        var deco = (st['text-decoration'] || '') + ' ' + (st['text-decoration-line'] || '');
        if (tag !== 'a' && /underline/.test(deco)) p.u = true;
        if (/line-through/.test(deco)) p.s = true;
        if (st['text-transform'] === 'uppercase') p.upper = true;
        if (/small-caps/.test(st['font-variant'] || '') || /small-caps/.test(st['font-variant-caps'] || '')) p.sc = true;
        return p;
    }
    function readAttrs(attrStr) {
        var attrs = {};
        var re = /([^\s=\/>]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+)))?/g, m;
        while ((m = re.exec(attrStr || ''))) {
            attrs[m[1].toLowerCase()] = m[2] !== undefined ? m[2] : (m[3] !== undefined ? m[3] : (m[4] !== undefined ? m[4] : ''));
        }
        return attrs;
    }

    /** Das von inlineHtml() erzeugte HTML flach in Läufe lesen. Schliessende Marken nehmen die
     *  jüngste offene gleichen Namens weg — wie der Browser bei überkreuzten <span>/<strong>. */
    function htmlToRuns(html, base) {
        var stack = [];
        var runs = [];
        var re = /<(\/?)([a-zA-Z][a-zA-Z0-9]*)([^>]*)>|([^<]+)/g, m;
        while ((m = re.exec(html))) {
            if (m[4] !== undefined) {
                var r = emptyRun(unesc(m[4]));
                if (base) Object.keys(base).forEach(function (k) { r[k] = base[k]; });
                stack.forEach(function (e) { Object.keys(e.p).forEach(function (k) { r[k] = e.p[k]; }); });
                runs.push(r);
                continue;
            }
            var tag = m[2].toLowerCase();
            if (m[1]) {
                for (var i = stack.length - 1; i >= 0; i--) {
                    if (stack[i].tag === tag) { stack.splice(i, 1); break; }
                }
            } else if (tag !== 'br') {
                var attrs = readAttrs(m[3]);
                stack.push({ tag: tag, p: tagProps(tag, function (n) { return attrs[n] !== undefined ? attrs[n] : null; }) });
            }
        }
        return mergeRuns(runs);
    }

    /* ---------- parse: Markdown::render() Zeile für Zeile ---------- */
    function parse(text) {
        var lines = String(text == null ? '' : text).split(/\r\n|\r|\n/);
        var blocks = [];
        lines.forEach(function (line) {
            if (isBlankLine(line)) {
                blocks.push({ type: 'blank', level: 0, scale: 1, color: null, runs: [] });
                return;
            }
            var m = RE_HEADING.exec(line);
            if (m) {
                var level = m[1].length;
                blocks.push({ type: 'h', level: level, scale: HEADING_SCALE[level] || 1, color: null,
                    runs: htmlToRuns(inlineHtml(esc(m[2]))) });
                return;
            }
            line = line.replace(RE_LIST_COLOR_FIX, '$1$4 [$2=$3]$5[/$2]');
            m = RE_LIST.exec(line);
            if (m) {
                var body = m[1];
                var color = null;
                var cm = RE_LI_COLOR.exec(body);
                if (cm) { color = cm[1]; body = cm[2]; }
                blocks.push({ type: 'li', level: 0, scale: 1, color: color,
                    runs: htmlToRuns(inlineHtml(esc(body)), color ? { color: color } : null) });
                return;
            }
            blocks.push({ type: 'p', level: 0, scale: 1, color: null, runs: htmlToRuns(inlineHtml(esc(line))) });
        });
        return blocks;
    }

    /* ---------- toHtml: zum Bearbeiten ---------- */
    function runToHtml(r) {
        var h = esc(r.text);
        if (r.b) h = '<b>' + h + '</b>';
        if (r.i) h = '<i>' + h + '</i>';
        if (r.u) h = '<u>' + h + '</u>';
        if (r.s) h = '<s>' + h + '</s>';
        if (r.code) h = '<code>' + h + '</code>';
        if (r.href) h = '<a href="' + esc(r.href) + '">' + h + '</a>';
        if (r.sc) h = '<span style="font-variant:small-caps">' + h + '</span>';
        if (r.upper) h = '<span style="text-transform:uppercase">' + h + '</span>';
        if (r.size) h = '<span style="font-size:' + r.size + 'px">' + h + '</span>';
        if (r.mark) h = '<span style="background-color:' + esc(r.mark) + '">' + h + '</span>';
        if (r.color) h = '<span style="color:' + esc(r.color) + '">' + h + '</span>';
        return h;
    }
    function toHtml(text) {
        return parse(text).map(function (b) {
            var inner = b.runs.map(runToHtml).join('');
            if (b.type === 'blank') return '<div><br></div>';
            if (b.type === 'h') {
                return '<div data-sf-h="' + b.level + '" style="font-size:' + b.scale + 'em;font-weight:700">' + (inner || '<br>') + '</div>';
            }
            if (b.type === 'li') {
                return '<div data-sf-li="1" style="display:list-item;list-style:disc outside;margin-left:1.1em' +
                    (b.color ? ';color:' + esc(b.color) : '') + '">' + (inner || '<br>') + '</div>';
            }
            return '<div>' + (inner || '<br>') + '</div>';
        }).join('');
    }

    /* ---------- fromHtml: zurück in die Auszeichnung ---------- */
    var VOID = { br: 1, img: 1, hr: 1, input: 1, wbr: 1, meta: 1, link: 1 };
    var BLOCK = { div: 1, p: 1, li: 1, ul: 1, ol: 1, h1: 1, h2: 1, h3: 1, h4: 1, h5: 1, h6: 1, blockquote: 1, section: 1, article: 1, pre: 1 };

    /** Kleiner Baum aus HTML-Text, damit fromHtml() auch ohne DOM (node) arbeitet. */
    function lightTree(html) {
        var rootNode = { nodeType: 1, tagName: 'DIV', attrs: {}, childNodes: [] };
        var stack = [rootNode];
        var re = /<!--[\s\S]*?-->|<(\/?)([a-zA-Z][a-zA-Z0-9]*)([^>]*?)(\/?)>|([^<]+)|</g, m;
        while ((m = re.exec(String(html)))) {
            var top = stack[stack.length - 1];
            if (m[5] !== undefined || m[0] === '<') {
                top.childNodes.push({ nodeType: 3, nodeValue: unesc(m[5] !== undefined ? m[5] : '<') });
                continue;
            }
            if (m[2] === undefined) continue; // Kommentar
            var tag = m[2].toLowerCase();
            if (m[1]) {
                for (var i = stack.length - 1; i > 0; i--) {
                    if (stack[i].tagName.toLowerCase() === tag) { stack.length = i; break; }
                }
                continue;
            }
            var el = { nodeType: 1, tagName: tag.toUpperCase(), attrs: readAttrs(m[3]), childNodes: [] };
            top.childNodes.push(el);
            if (!VOID[tag] && !m[4]) stack.push(el);
        }
        return rootNode;
    }
    function getAttrOf(node, name) {
        if (node.attrs) return node.attrs[name] !== undefined ? node.attrs[name] : null;
        return node.getAttribute ? node.getAttribute(name) : null;
    }

    function serializeRuns(runs) {
        var ORDER = ['color', 'mark', 'size', 'upper', 'sc', 'href', 'b', 'i', 'u', 's', 'code'];
        function wrap(key, val, inner) {
            if (!val || inner === '') return inner;
            switch (key) {
                case 'color': return '[color=' + val + ']' + inner + '[/color]';
                case 'mark': return '[mark=' + val + ']' + inner + '[/mark]';
                case 'size': return '[size=' + val + ']' + inner + '[/size]';
                case 'upper': return '[upper]' + inner + '[/upper]';
                case 'sc': return '[sc]' + inner + '[/sc]';
                case 'href': return '[' + inner + '](' + val + ')';
                case 'b': return '**' + inner + '**';
                case 'i': return '*' + inner + '*';
                case 'u': return '++' + inner + '++';
                case 's': return '~~' + inner + '~~';
                case 'code': return '`' + inner + '`';
            }
            return inner;
        }
        function ser(list, k) {
            if (k >= ORDER.length) return list.map(function (r) { return r.text; }).join('');
            var key = ORDER[k], out = '', i = 0;
            while (i < list.length) {
                var j = i;
                while (j + 1 < list.length && list[j + 1][key] === list[i][key]) j++;
                out += wrap(key, list[i][key], ser(list.slice(i, j + 1), k + 1));
                i = j + 1;
            }
            return out;
        }
        return ser(mergeRuns(runs), 0);
    }

    function fromHtml(el) {
        var rootNode = (typeof el === 'string') ? lightTree(el) : el;
        var lines = [];
        var cur = null;
        var blockCtx = [{ kind: 'p', level: 0, color: null }];

        function ctx() { return blockCtx[blockCtx.length - 1]; }
        function ensure() {
            if (!cur) {
                var c = ctx();
                cur = { kind: c.kind, level: c.level, color: c.color, runs: [] };
                lines.push(cur);
            }
            return cur;
        }
        function pushText(txt, props) {
            var parts = String(txt).replace(/ /g, ' ').replace(/​/g, '').split(/\r\n|\r|\n/);
            parts.forEach(function (part, idx) {
                if (idx > 0) { ensure(); cur = null; }
                if (part === '') return;
                var r = emptyRun(part);
                Object.keys(props).forEach(function (k) { r[k] = props[k]; });
                ensure().runs.push(r);
            });
        }
        function walk(node, props) {
            var kids = node.childNodes || [];
            for (var n = 0; n < kids.length; n++) {
                var child = kids[n];
                if (child.nodeType === 3) { pushText(child.nodeValue || '', props); continue; }
                if (child.nodeType !== 1) continue;
                var tag = String(child.tagName || '').toLowerCase();
                var ga = function (name) { return getAttrOf(child, name); };
                if (tag === 'br') {
                    ensure();
                    cur = null;
                    continue;
                }
                if (tag === 'script' || tag === 'style') continue;
                if (BLOCK[tag]) {
                    var c = ctx();
                    var nb = { kind: c.kind, level: c.level, color: c.color };
                    var childProps = props;
                    var hAttr = ga('data-sf-h');
                    if (/^h[1-6]$/.test(tag) || hAttr) {
                        nb.kind = 'h';
                        nb.level = hAttr ? Math.max(1, Math.min(6, parseInt(hAttr, 10) || 1)) : parseInt(tag.charAt(1), 10);
                        nb.color = null;
                    } else if (tag === 'li' || ga('data-sf-li')) {
                        nb.kind = 'li';
                        nb.level = 0;
                        nb.color = normHex(parseStyle(ga('style')).color) || null;
                        if (nb.color) {
                            childProps = Object.assign({}, props, { color: nb.color });
                        }
                    } else if (tag === 'div' || tag === 'p' || tag === 'pre') {
                        // Fremd-HTML (eingefügt): Zeichenstil am Block wie an einem <span> übernehmen
                        if (c.kind === 'p' && !ga('data-sf-h')) {
                            childProps = Object.assign({}, props, tagProps('span', ga));
                            delete childProps.size;
                        }
                    }
                    cur = null;
                    blockCtx.push(nb);
                    walk(child, childProps);
                    blockCtx.pop();
                    cur = null;
                    continue;
                }
                walk(child, Object.assign({}, props, tagProps(tag, ga)));
            }
        }
        walk(rootNode, {});

        return lines.map(function (ln) {
            var body = serializeRuns(ln.runs);
            if (ln.kind === 'h') return '#'.repeat(ln.level || 1) + ' ' + body;
            if (ln.kind === 'li') return body === '' ? '- ' : '- ' + body;
            return body;
        }).join('\n');
    }

    function plainText(text) {
        return parse(text).map(function (b) {
            return b.runs.map(function (r) { return r.text; }).join('');
        }).join('\n');
    }

    var api = {
        parse: parse,
        toHtml: toHtml,
        fromHtml: fromHtml,
        plainText: plainText,
        serializeRuns: serializeRuns,
        HEADING_SCALE: HEADING_SCALE
    };
    root.SfMarkup = api;
    if (typeof module === 'object' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : this);
