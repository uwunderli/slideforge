# Gliederung zusammengefasst auf Folien (Logos-Import)

**Datum:** 24.10.2026 · **Einordnung: nicht sicherheitsrelevant** (Oberfläche und Aufbereitung;
keine Rechte, kein Löschen, kein Versand) · **Stand: gebaut, gemessen, ausgeliefert (24.10.).**

**Auftrag:** Urs über `Entwickler (2)`, 24.10. Sein Wortlaut: «Die möchte ich nun gerne auf
einigen Folien zusammenfassen» · Form: «Titel mit ihren Stichpunkten» · Menge: «Immer so viel
wie Platz da ist…» · und direkt in meinem Fenster: «Man kann sagen, ob man eine Gliederung
zusammengefasst auf Folien anzeigen möchte. Die Überschrift ist dann immer die darüber liegende
Folie.»

**Beispiel:** `/mnt/storage/Projekte/_transfer/logos/manasse-2026-10-24.html` (Logos-Export,
«2. Chr 33,1–17 – Manasse»).

---

## 1. Zwei Dinge, die anders sind als in der Weitergabe beschrieben

**Es sind elf Punkte, nicht zehn.** Unter der Überschrift «Manasses Sünden» (Ebene 2) liegen
**elf** Überschriften der Ebene 4, jede mit zwei Stichpunkten (Stellenangabe, Doppelpunkt,
Zitat). Gemessen am Dokument, nicht gezählt nach Gefühl.

**Heute entsteht daraus nicht «Folie um Folie», sondern gar keine Folie.** Im Folien-Set
«Schlicht - Logos» (`a452ba0246f40ca3`), mit dem Urs arbeitet, stehen die Zonen so:

| Rolle | Zone im Set |
|---|---|
| `heading2` | slides |
| `heading4` | **unused** |
| `list_item` (Stichpunkte) | **custom** (Notizen) |

Die elf Punkte landen also in den Notizen bzw. fallen weg; auf Folien erscheinen sie nur, wenn
man im Dialog «Gliederung» **und** «Stichpunkte» ausdrücklich auf «auf Folien» stellt. Gemessen:
mit den Zonen des Sets trägt **eine** Folie einen der elf Namen (und das ist ein Bibeltext, in
dem «Zeichendeuterei» vorkommt), mit beiden Feldern auf «auf Folien» sind es **elf** Folien,
eine je Punkt.

**Folge für die Einstellung:** Der Schalter sagt «zusammengefasst **auf Folien**» — also bringt
er die beiden Rollen selbst auf Folien. Täte er das nicht, hätte Urs ein Häkchen gesetzt und
sichtbar wäre nichts passiert. **Hat er im Dialog aber ausdrücklich «in die Notizen» oder «aus»
gewählt, gilt seine Wahl**; der Schalter übersteuert keine Wahl von Hand. Beides ist gemessen
(siehe §4).

## 2. Was gebaut ist

### `LayoutSet::roleTextCapacity($setId, $rolle, $setMeta)` — neu, 68 Zeilen

Schätzt, wie viel in das Platzhalter-Feld einer Rolle passt: `['lines' => …,
'charsPerLine' => …, 'chars' => …]`. **`docs/LOGOS_IMPORTER.md` sieht genau das vor**
(«`layout` · Schwelle aus Platzhalter-Grösse + Textvorlage schätzen»), nur gab es die Rechnung
bisher nicht — darum ist keine zweite Mechanik entstanden, sondern die vorgesehene erste.

**Die Höhe des Platzhalters taugt nicht als Mass:** das Inhaltsfeld ist in allen drei Sets nur
**eine Zeile** hoch (`h=78` bei 65 px · 1.2) und wächst beim Füllen nach unten. Gerechnet wird
darum von seiner Oberkante bis zum nächsten Objekt darunter, sonst bis zum unteren Rand, und
als unterer Rand gilt derselbe Abstand, den das Feld links lässt.

Gemessen im Set «Schlicht - Logos»: Inhaltsfeld **9 Zeilen × 50 Zeichen = 450**. Zum Vergleich:
die von Hand eingetragene Zeichengrenze desselben Sets steht auf **500** — zwei unabhängige
Wege, dieselbe Grössenordnung.

**Gegenprobe, dass die Rechnung nicht einfach immer dasselbe sagt** (echte Felder, dieselbe
Funktion): `heading1` 1 × 25 · `heading2` 1 × 38 (durch die Trennlinie bei y=200 begrenzt) ·
`scripture_ref` 1 × 47 · `list_item` 9 × 50.

### `LogosSermonImporter` — eine Option, eine Verteilung

- Neue Option `outlineSummary` (Vorgabe **aus**), in `normalizeOptions` auf `bool` gefiltert.
- Neuer Zweig in `processSection`: trifft er auf eine Überschrift der Ebene 2–5, sammelt er den
  **ganzen Lauf** gleichrangiger Überschriften samt ihren Stichpunkten und verteilt ihn.
- `$emitOutlineSummary(...)`: füllt die Folie bis zur gemessenen Zeilenzahl.
  - **Ein Block wird nie getrennt** — passt er nicht mehr, kommt er ganz auf die nächste Folie.
  - **Die übergeordnete Überschrift steht auf jeder dieser Folien** («die darüber liegende
    Folie»), gefunden durch Rückwärtssuche nach der nächsten Überschrift mit kleinerer
    Ebenenzahl; sonst der Abschnittstitel.
  - **Ohne Mass wird nicht geraten:** kennt das Set keine Feldgrösse, bleibt es bei einem Punkt
    je Folie, also beim bisherigen Verhalten.
  - Gezählt wird in **Zeilen**, nicht in Zeichen: Titel und Stichpunkte fangen je eine neue
    Zeile an. Die Zeichenbreite geht als Umbruchschätzung ein.
  - Unter zwei Punkten wird nichts zusammengefasst — dieselbe Schwelle wie bei der
    Gliederungsübersicht.
- Je Block: `**Titel**` und darunter die Stichpunkte als Liste; zwischen zwei Blöcken eine
  Leerzeile (die in der Zeilenrechnung mitzählt).

### Nebenbei behoben: die Stellenangaben wurden verstümmelt

`formatListItemsMarkdown()` entfernte nach dem Listenzeichen auch eine führende Nummer. Aus
«• **2.** Chr 33,3: …» wurde «Chr 33,3: …» — bei Logos-Predigten der Normalfall, weil die
Bücher 1./2. Chronik, 2. Mose usw. so heissen. Die Nummer wird jetzt nur noch entfernt, wenn
**kein** Listenzeichen davor stand (eine wirklich numerierte Liste kommt ohne Zeichen).
Das betrifft **auch die bisherigen Listenfolien**, nicht nur die neuen.

Gegenprobe, beide Richtungen:

```
• 2. Chr 33,3: Er baute      →  - 2. Chr 33,3: Er baute      (Nummer bleibt)
› 1. Mose 1,1: Am Anfang     →  - 1. Mose 1,1: Am Anfang     (Nummer bleibt)
1. Erster Punkt              →  - Erster Punkt               (Nummer weg)
```

### Dialog und Gedächtnis

- `public_html/import.php`: **ein** Häkchen «Gliederung zusammengefasst auf Folien», bei den
  bestehenden Häkchen, im Haus-Muster (`data-import-opt="outlineSummary"` — die Werte sammelt
  der Dialog von sich aus ein).
- `lang/{de,en,fr,it,rm}.php`: `import.outline_summary` in allen fünf Sprachen.
- `src/Auth.php`: die Wahl wird wie die Nachbaroptionen für den nächsten Import gemerkt.

## 3. Wie es mit der Beispiel-Predigt aussieht

Aus den **elf** Punkten werden **neun Folien**:

| Folie | Punkte | Zeilen (von 9) |
|---|---|---|
| 1 | Wiederaufbau der Opferhöhen | 6 |
| 2 | Errichtung von Altären für Baal | 4 |
| 3 | Anfertigung von Ascheren | 4 |
| 4 | Anbetung des Himmelsheeres | 6 |
| 5 | Altäre im Tempel des HERRN | 7 |
| 6 | Söhne durchs Feuer gehen lassen | 7 |
| 7 | **Zeichendeuterei · Wahrsagerei** | 7 |
| 8 | **Zauberei · Totenbeschwörung** | 7 |
| 9 | Götzenbildnis im Tempel | 5 |

Gesamtzahl der Folien: **28** wie heute · **41** mit beiden Zonen auf «auf Folien» und ohne
Schalter · **37** mit dem Schalter.

**Warum nur neun und nicht drei:** Nicht die Verteilung bremst, sondern der Platz. Ein Punkt
braucht mit seinen zwei Zitaten vier bis sieben der neun Zeilen — zwei volle Punkte gehen
selten zusammen. Der Hebel liegt in der **Schriftgrösse des Inhaltsfelds** im Folien-Set
(heute 65 px); mit derselben Verteilung ergibt das:

| Inhaltsschrift | Platz | Folien für die elf Punkte |
|---|---|---|
| **65 px (heute)** | 9 × 50 | **9** |
| 56 px | 10 × 59 | 7 |
| 48 px | 12 × 68 | 5 |
| 40 px | 15 × 82 | 4 |
| 34 px | 17 × 97 | 3 |

Das ist eine Entscheidung am Folien-Set, nicht am Importer: kleinere Schrift heisst mehr Punkte
je Folie und kleinere Buchstaben im Saal. **Von mir aus am Set nichts geändert.**

## 4. Geprüft

`php -l` über alle fünf geänderten Dateien sauber. Dazu gemessen am echten Export
(nur lesend, `planImport`/`buildSlidesFromLayoutSet` schreiben nichts):

| Fall | Ergebnis |
|---|---|
| Schalter aus | 28 Folien, 0 zusammengefasst — **wie heute** |
| Schalter ein | 37 Folien, 9 zusammengefasst, **0 leere**, **0 Warnungen** |
| Schalter ein, Stichpunkte im Dialog auf «in die Notizen» | **0 zusammengefasst** — die Wahl von Hand gewinnt |
| Schalter ein, Gliederungstiefe 2 (Ebene 4 damit aus) | **0 zusammengefasst** — greift richtig nicht |
| Schalter ein **und** Gliederungsübersicht für den Abschnitt | 38 Folien, beides nebeneinander |
| Stichpunkte doppelt (Folie **und** Notizen)? | **0** — der Zweig verbraucht die Blöcke |

## 5. Offen / gemeldet, nicht behoben

1. **`textMaxCharacters: 'layout'` tut weiterhin nichts.** Das Set «Schlicht - Logos» steht auf
   `listGrouping: 'layout'` und `textMaxCharacters: 500`; der Wert `'layout'` wird im Importer
   zu «keine Grenze» (`$max = ($max === 'layout' …) ? 0 …`). Mit `roleTextCapacity()` liesse
   sich das jetzt einlösen — das ändert aber das Verhalten von Bibeltext und Fliesstext und
   gehört nicht in diesen Auftrag. **Out-of-Scope-Fund, Regel 2.**
2. **Kein `scripts/lint.sh`, kein `release.sh`, keine Prüf-Protokoll-Datei** — weder in
   SlideForge noch im Hub (gesucht am 24.10.). Die Weitergabe nennt alle drei. Ich habe darum
   `php -l` plus die Messungen oben benutzt; **wo die Protokollzeile hin soll, muss mir jemand
   sagen.**
3. **Die Ebenen 3 und 5 bleiben unberührt.** Urs überspringt Ebene 3; der Zweig arbeitet auf
   jeder Ebene 2–5, aber nur innerhalb eines Laufs gleichrangiger Überschriften.

## 6. Umfang, gegen den **Live-Stand** gemessen (nicht gegen HEAD)

| Datei | live (Prod) | Arbeitsbaum | Änderung |
|---|---|---|---|
| `src/LogosSermonImporter.php` | `944038dedfcd3401…` | `71586107ec746376…` | +175 / −4 |
| `src/LayoutSet.php` | `86628f4ecb95b6a3…` | `0430fdd7ad5cdfdc…` | +68 / −1 |
| `src/Auth.php` | `84e2038cb4578662…` | `6c3548f743850513…` | +3 / −1 |
| `public_html/import.php` | `3b13337fcb779387…` | `39e5709234a72303…` | +4 |
| `lang/{de,en,fr,it,rm}.php` | — | — | je +1 Zeile |

Die vier «−»-Zeilen sind: die `use`-Zeile der Abschnitts-Funktion (zwei Namen dazu) und die
zwei Zeilen der Listenformatierung; in `LayoutSet`/`Auth` je die Ankerzeile. **Sonst rein
additiv.**

### Lieferprotokoll (24.10.2026, erledigt)

Freigabe `Entwickler (2)`: «Dann liefere aus — es ist Oberfläche und Import, nicht
sicherheitsrelevant, also ohne Gegenprüfung; der Schalter steht ja auf «aus».»

1. `php -l` über alle neun Dateien sauber.
2. **Vorher auf Prod geprüft** — alle neun Prüfwerte wie in der Tabelle oben erwartet.
3. **Sicherung:** `/data/slideforge/.sicherung/logos-zusammenfassung-20261024/`
   (`src/`, `lang/`, `public_html/`), gegen die laufenden Dateien geprüft.
4. Übertragen mit `scp -p`, neun Dateien.
5. **Nachher auf Prod:** Prüfwerte = Arbeitsbaum, Besitzer und Rechte unverändert
   (`99:100`, `644`); die neuen Stellen sind da (`outlineSummary` 4×/2×/1×,
   `roleTextCapacity` 1×, `import.outline_summary` je Sprache 1×).
6. **Takt-Probe**, jede Abfrage mit Cache-Buster: `login.php` **302** in einem Sprung nach
   `hub.bkbiel.ch/login.php`, `import.php` **302** (läuft, verlangt Anmeldung),
   `register.php` **200**, 3347 Bytes, **0** Meldungszeilen, `sw.php` `slideforge-679`.
   **Keine Versionsanhebung**, weil keine statische Datei geändert wurde.
7. `docs/` wird nicht ausgeliefert (nicht in der Positivliste) — Befund und Prüf-Protokoll
   bleiben im Repo.

> **Rückbau-Satz:** Die fünf Dateien aus der Sicherung zurücklegen (Prüfwerte der
> Live-Spalte oben). Es wandern keine Daten mit, und ohne das Häkchen verhält sich der Import
> wie vorher — die Option ist von sich aus aus.

**Ausgeliefert am 24.10.2026** nach Freigabe (siehe Lieferprotokoll). Prüfskripte der Messung liegen im Arbeitsbereich dieser
Sitzung, nicht im Repo.
