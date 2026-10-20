<?php
/**
 * Sehr kleiner, bewusst eingeschränkter Markdown-Konverter für Textobjekte und Notizen.
 * Verarbeitet Zeile für Zeile (nicht den ganzen Block auf einmal), erkennt daher auch
 * gemischte Inhalte wie Überschrift + Liste im selben Text korrekt.
 * Unterstützt: **fett**, *kursiv*, ++unterstrichen++, ~~durchgestrichen~~, `code`,
 * [Text](https://...), Listen (- oder * pro Zeile), Überschriften (# bis ######,
 * Leerzeichen danach optional),
 * sowie für Teile des Texts: [upper]GROSS[/upper], [sc]Kapitälchen[/sc],
 * [mark=#hex]markiert[/mark], [color=#hex]farbig[/color].
 * WICHTIG: Escaped IMMER zuerst per h() (htmlspecialchars), bevor Markdown-Syntax
 * in HTML umgewandelt wird - der Text kommt von Nutzereingaben und landet auf
 * öffentlich einsehbaren Seiten (view.php), XSS-Sicherheit hat Priorität.
 */
class Markdown
{
    /**
     * @param bool $blockMargin Aussenabstände von Aufzählungen und Überschriften.
     *        Für Notizen erwünscht (Luft zwischen den Blöcken), auf einer FOLIE nicht:
     *        Dort malt der Editor den rohen Text ohne solche Abstände, und jeder
     *        zusätzliche Rand lässt Editor und Ausgabe auseinanderlaufen. Bei
     *        „Jede Zeile einzeln animieren" wird jede Zeile für sich gelesen — dann
     *        summieren sich die Ränder je Zeile auf.
     */
    public static function render(string $raw, bool $blockMargin = true): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        if (!array_filter($lines, fn($l) => trim($l) !== '')) {
            return '';
        }

        $sizes = [1 => '1.6em', 2 => '1.35em', 3 => '1.15em', 4 => '1.05em', 5 => '1em', 6 => '0.9em'];
        $htmlParts = [];
        $listBuffer = [];
        $prevWasBlock = true; // vor der ersten Zeile kein <br> nötig

        $ulMargin = $blockMargin ? '0.2em 0' : '0';
        $flushList = function () use (&$listBuffer, &$htmlParts, $ulMargin) {
            if ($listBuffer) {
                $htmlParts[] = '<ul style="margin:' . $ulMargin . '; padding-left:1.1em; text-align:left;">' . implode('', $listBuffer) . '</ul>';
                $listBuffer = [];
            }
        };

        foreach ($lines as $line) {
            if (trim($line) === '') {
                $flushList();
                continue;
            }
            // Überschrift: # bis ###### - Leerzeichen danach ist OPTIONAL (##Titel genauso wie ## Titel)
            if (preg_match('/^(#{1,6})\s*(.+)$/', $line, $m)) {
                $flushList();
                $level = strlen($m[1]);
                $size = $sizes[$level] ?? '1em';
                $hMargin = $blockMargin ? '0.3em 0 0.15em' : '0';
                $htmlParts[] = '<div style="font-size:' . $size . '; font-weight:700; margin:' . $hMargin . ';">' . self::inline(h($m[2])) . '</div>';
                $prevWasBlock = true;
                continue;
            }
            /* Steht die Auszeichnung VOR dem Strich — „[color=#hex]- Text[/color]" —, so
               ist das trotzdem ein Listeneintrag. Von Hand geschrieben landet der Strich
               oft innerhalb der Klammer; hier wird er davor geholt, damit die Prüfung
               unten greift und die Farbe für die ganze Zeile erhalten bleibt. */
            $line = preg_replace(
                '/^(\s*)\[(color|mark)=(#[0-9a-fA-F]{6})\]\s*([-*])\s+(.+?)\[\/\2\]\s*$/is',
                '$1$4 [$2=$3]$5[/$2]',
                $line
            ) ?? $line;
            // Listeneintrag: - oder * gefolgt von Leerzeichen (jede Zeile einzeln geprüft,
            // nicht mehr "der GANZE Text ist eine Liste" - so funktionieren auch gemischte
            // Inhalte wie Überschrift + Liste im selben Notizfeld).
            if (preg_match('/^\s*[-*]\s+(.+)$/', $line, $m)) {
                /* Ist die ganze Zeile eingefärbt, gehört die Farbe an das <li>: Der
                   Aufzählungspunkt wird vom Listenelement gezeichnet, nicht vom <span>
                   darin, und bliebe sonst in der Grundfarbe stehen. */
                $liStyle = '';
                if (preg_match('/^\[color=(#[0-9a-fA-F]{6})\](.+)\[\/color\]$/is', $m[1], $cm)) {
                    $liStyle = ' style="color:' . h($cm[1]) . ';"';
                    $m[1] = $cm[2];
                }
                $listBuffer[] = '<li' . $liStyle . '>' . self::inline(h($m[1])) . '</li>';
                $prevWasBlock = true;
                continue;
            }
            // Normale Textzeile
            $flushList();
            $htmlParts[] = ($prevWasBlock ? '' : '<br>') . self::inline(h($line));
            $prevWasBlock = false;
        }
        $flushList();
        return implode('', $htmlParts);
    }

    private static function inline(string $escaped): string
    {
        // Links: [Text](https://... oder mailto:...)
        $escaped = preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^\s)]+|mailto:[^\s)]+)\)/',
            fn($m) => '<a href="' . $m[2] . '" target="_blank" rel="noopener noreferrer" style="color:inherit; text-decoration:underline;">' . $m[1] . '</a>',
            $escaped
        );
        // Textfarbe für einen Teil des Texts: [color=#hex]text[/color]
        $escaped = preg_replace_callback(
            '/\[color=(#[0-9a-fA-F]{6})\](.+?)\[\/color\]/is',
            fn($m) => '<span style="color:' . h($m[1]) . ';">' . $m[2] . '</span>',
            $escaped
        );
        // Marker/Textmarker für einen Teil des Texts: [mark=#hex]text[/mark]
        $escaped = preg_replace_callback(
            '/\[mark=(#[0-9a-fA-F]{6})\](.+?)\[\/mark\]/is',
            fn($m) => '<span style="background:' . h($m[1]) . '; border-radius:2px; padding:0 2px; box-decoration-break:clone; -webkit-box-decoration-break:clone;">' . $m[2] . '</span>',
            $escaped
        );
        // Fett: **text** oder __text__
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped);
        $escaped = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $escaped);
        // Kursiv: *text* oder _text_
        $escaped = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $escaped);
        $escaped = preg_replace('/(?<![a-zA-Z0-9])_(.+?)_(?![a-zA-Z0-9])/s', '<em>$1</em>', $escaped);
        // Unterstrichen: ++text++
        $escaped = preg_replace('/\+\+(.+?)\+\+/s', '<u>$1</u>', $escaped);
        // Durchgestrichen: ~~text~~
        $escaped = preg_replace('/~~(.+?)~~/s', '<s>$1</s>', $escaped);
        // Grossbuchstaben für einen Teil des Texts: [upper]text[/upper]
        $escaped = preg_replace('/\[upper\](.+?)\[\/upper\]/is', '<span style="text-transform:uppercase;">$1</span>', $escaped);
        // Kapitälchen für einen Teil des Texts: [sc]text[/sc]
        $escaped = preg_replace('/\[sc\](.+?)\[\/sc\]/is', '<span style="font-variant:small-caps;">$1</span>', $escaped);
        // Inline-Code: `text`
        $escaped = preg_replace(
            '/`(.+?)`/s',
            '<code style="background:rgba(127,127,127,0.25); padding:0 5px; border-radius:3px; font-family:monospace;">$1</code>',
            $escaped
        );
        return $escaped;
    }
}
