# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


## [3.12.41] - 2026-11-27

### Added
- **Notizen formatiert bearbeiten** (Urs 27.11.): Das Notizfeld im Editor zeigt Fett, Kursiv, Unterstrichen, Farben und Marker so, wie sie in der Präsentation erscheinen. Kleine Leiste über dem Feld (Fett, Kursiv, Unterstrichen, Schriftfarbe, Marker, Formatierung entfernen) wirkt auf die Markierung; Ctrl+B/I/U ebenso (Kürzel aus dem Katalog). Gespeichert wird wie bisher die Auszeichnung; Einfügen nur als reiner Text.

## [3.12.40] - 2026-11-27

### Changed
- **Folienübergang wie PowerPoint** (Urs 27.11.): Der Übergang gehört zur kommenden Folie und gilt für beide Seiten des Wechsels (vorher spielte die abtretende Folie ihren eigenen Übergang, die kommende ihren – ein Gemisch). Rückwärts gilt der Übergang der Folie, von der man kommt. Ausgeblendete Folien zählen nicht. Bestehende Präsentationen mit gemischten Übergängen wirken dadurch teilweise anders.

## [3.12.39] - 2026-11-27

### Fixed
- **Ebenen-Kürzel greifen** (Urs 27.11.): Ctrl+↑/↓ (und mit Umschalt) verschoben das Objekt, wenn der Fokus noch in der Seitenleiste stand; jetzt wechseln sie die Ebene, sobald Objekte markiert sind.

## [3.12.38] - 2026-11-27

### Added
- **Tastentipps für Seitenleiste und Filmstreifen** (Urs 27.11.: «Alt anschliessend Ctrl bringt die Tabs der rechten Werkzeugliste und dann weiter…», «erneutes Ctrl geht auf den Filmstreifen»): Nach Alt schaltet Ctrl reihum Menüband → rechte Seitenleiste → Filmstreifen (bzw. Raster) → Menüband. In der Seitenleiste: Buchstabe öffnet den Reiter, dann die Unter-Reiter (Position, Format, Effekte), dann Knöpfe und Felder; Esc eine Stufe zurück. Im Filmstreifen tippt man die Foliennummer, auch mehrstellig (15); eindeutige Nummern springen sofort, sonst Enter oder kurze Pause; auch zu weggerollten Folien. Alt lange halten zeigt auch die Foliennummern und die Kürzel der Ebenen-Knöpfe und von «Neue Folie». Braucht Hub 2.37.4.
## [3.12.37] - 2026-11-27

### Changed
- **Menüband und Format-Spalte formatieren die markierte Textstelle** (Urs 27.11., Texteditor Häppchen T4): Beim Bearbeiten an Ort wirken Fett, Kursiv, Unterstrichen, Durchgestrichen, Gross, Kapitälchen, Schriftgrösse und Textfarbe im Menüband sowie die Knöpfe, Marker und Textfarbe der Format-Spalte nur auf die markierte Stelle; nochmals klicken nimmt die Formatierung wieder weg. Ohne Markierung oder ohne Bearbeiten wirken sie wie bisher aufs ganze Textobjekt. Die Knöpfe zeigen beim Bearbeiten den Zustand an der Schreibmarke. Klicks ins Menüband beenden die Bearbeitung nicht mehr.
- **Tastenkürzel Ctrl+B / Ctrl+I / Ctrl+U** für Fett, Kursiv und Unterstrichen (im Dialog «Ribbon anpassen» › Weitere Befehle › Text änderbar): beim Bearbeiten auf die Markierung, auf der Folienfläche aufs gewählte Textobjekt.

## [3.12.36] - 2026-11-27

### Changed
- **Texte an Ort mit sichtbarer Formatierung bearbeiten** (Urs 27.11., Texteditor Häppchen T3): Doppelklick auf ein Textobjekt öffnet jetzt ein Bearbeitungsfeld, das den Text so zeigt wie die Folie — fett, kursiv, Farben, Marker, Grössen, Überschriften und Aufzählungspunkte — statt des Rohtexts mit den Zeichen. Schrift, Grösse, Farbe, Ausrichtung, Zeilenhöhe, Zeichenabstand, Gross/Kapitälchen und Drehung kommen vom Objekt; das Feld wächst in der Höhe mit. Esc verwirft, Klick daneben speichert. Eingefügt wird nur reiner Text (Zeilenumbrüche bleiben). Enter in einem Aufzählungspunkt beginnt einen neuen Punkt, Enter auf einem leeren Punkt beendet die Aufzählung (wie Word). Gespeichert wird weiter die Auszeichnung; unveränderte Texte werden nicht angetastet. Rechtschreibprüfung wie bisher.

## [3.12.35] - 2026-11-27

### Added
- **Präsentation am Ende ausblenden** (Urs 27.11.: «Wenn man in der letzten Folie ist … dann blenden wir die Präsentation einfach aus»): Steht der Präsentationsmodus auf der letzten Folie und sind alle Schritte gezeigt, blendet «weiter» (Tasten, Knopf, Beamer-Fenster, Fernsteuerung, Auto-Weiter) sanft zu Schwarz aus; «zurück» blendet wieder ein, nochmals «weiter» tut nichts. Beamer-Fenster und Anzeige-Geräte folgen über das neue Feld `blank` im Live-Kanal; ältere Folgende ohne das Feld zeigen die Folie weiter.

## [3.12.34] - 2026-11-27

### Fixed
- **Verlauf auf der Bühne wie in der Präsentation** (Urs 27.11.: Miniatur und Bühne sahen verschieden aus): Die Bühne zog den Verlauf über die Diagonale des Objekts, Präsentation und Miniaturen über Höhe/Breite (CSS). Jetzt rechnet die Bühne gleich.

## [3.12.33] - 2026-11-27

### Changed
- **Textobjekte zeigen im Editor die Formatierung** (Urs 27.11., Texteditor Häppchen T2): Auf der Folie im Editor steht der Text jetzt so, wie ihn die Präsentation zeigt — fett, kursiv, unterstrichen, durchgestrichen, Teilfarben, Marker, Teilgrösse, Gross, Kapitälchen, Code, Links, Überschriften und Aufzählungspunkte — statt des Rohtexts mit den Zeichen. Auch «Gross» fürs ganze Objekt ist auf der Bühne sichtbar. Wie in der Präsentation geben Leerzeilen keinen zusätzlichen Abstand, und sehr lange Wörter werden nicht mehr mitten im Wort umbrochen. Bearbeitet wird vorläufig weiter im Rohtext (Doppelklick); Bearbeiten mit sichtbarer Formatierung folgt.

## [3.12.32] - 2026-11-27

### Added
- **Text-Auszeichnung auch im Editor lesbar** (Urs 27.11., Texteditor Häppchen T1): Neue Datei `sf-markup.js` liest die Auszeichnung der Textobjekte (**fett**, *kursiv*, ++unter++, ~~durch~~, Farbe, Marker, Gross, Kapitälchen, `code`, Links, Listen, Überschriften) nach genau den Regeln der Präsentation in formatierte Abschnitte und schreibt sie aus einer Bearbeitung wieder zurück. Noch ohne sichtbare Wirkung; die Bühne nutzt es ab dem nächsten Schritt.
- **Teilgrösse im Text** `[size=NN]…[/size]` (NN in Pixel, 6–400): Präsentation, Vorschau und Exporte über HTML zeigen den Textteil in dieser Grösse.

## [3.12.31] - 2026-11-27

### Added
- **Kürzel für die Ebenen** (Urs 27.11.): Ctrl+↑ / Ctrl+↓ eine Ebene nach vorne/hinten, Ctrl+Umschalt+↑ / Ctrl+Umschalt+↓ ganz nach vorne/hinten, für alle markierten Objekte auf der Folienfläche (wie Google Slides). Änderbar unter Weitere Befehle; im Filmstreifen bleibt Ctrl+Umschalt+↑/↓ «Folie verschieben».

## [3.12.30] - 2026-11-27

### Fixed
- **Farbwähler im Verlauf bleibt auf dem Bildschirm** (Urs 27.11., zwei Monitore): Das Farbfeld jedes Verlaufspunkts steht links neben dem Namen statt am rechten Rand der Seitenspalte; der Farbwähler des Browsers öffnet so weiter innen.

## [3.12.29] - 2026-11-27

### Added
- **Verlauf mit beliebig vielen Farben** (Urs 27.11.: «ab wann die neue Farbe beginnt», «weitere Farben dazu fügen»): Der Verlauf ist eine Liste von Farbpunkten, je mit Farbe, Deckkraft und Position (0 % = Anfang, 100 % = Ende). «+ Farbe» setzt einen Punkt in die grösste Lücke, der Papierkorb entfernt einen (mindestens zwei bleiben, höchstens zwölf). Editor, Vorschau und Präsentation gleich; bestehende Verläufe bleiben wie sie sind.

## [3.12.28] - 2026-11-27

### Changed
- **Übersetzungen nachgeführt** (Urs 27.11.): fehlende Texte in Englisch (138), Französisch (204), Italienisch (211) und Rätoromanisch (211) ergänzt; keine Oberfläche fällt mehr auf Deutsch zurück. Rätoromanisch braucht eine Durchsicht durch jemanden, der Rumantsch Grischun spricht.

## [3.12.27] - 2026-11-27

### Added
- **Folienauswahl mit Umschalt+Pfeil erweitern** (Urs 27.11.): Im Filmstreifen und im Raster erweitert Umschalt+↑/↓/←/→ die Auswahl ab der angeklickten Folie (im Raster ↑/↓ je eine Zeile). Befehle «Folienauswahl … erweitern» unter Weitere Befehle. Auf der Folienfläche bleibt Umschalt+Pfeil das Verschieben um 10 px.

## [3.12.26] - 2026-11-27

### Fixed
- **Ziehpunkte am Rahmen greifen wieder** (Urs 27.11.): Der Auswahlrahmen kommt bei jeder Auswahl ganz nach oben. Vorher konnte ein Objekt (z. B. nach Ebenen-Änderungen) über dem Rahmen liegen; ein Griff an den Ziehpunkt verschob dann das Objekt statt es zu vergrössern.

## [3.12.25] - 2026-11-27

### Fixed
- **Tiefer gewähltes Objekt lässt sich fassen** (Urs 27.11.): Nach Ctrl+Klick nehmen die Objekte darüber keine Maus mehr an, bis du etwas anderes wählst oder auf eine leere Stelle klickst. So lassen sich Rahmen, Ecken und das Objekt selbst ziehen, ohne das obere zu verschieben.

## [3.12.24] - 2026-11-27

### Added
- **Ctrl+Klick wählt tiefer** (Urs 27.11.): Liegt ein Objekt über einem anderen, wählt Ctrl+Klick (Mac: Cmd+Klick) das Objekt eine Ebene darunter, jeder weitere Ctrl+Klick noch eine tiefer, nach dem untersten wieder das oberste. Umschalt+Klick bleibt Mehrfachauswahl.

## [3.12.23] - 2026-11-27

### Added
- **Verlauf mit Deckkraft je Farbe** (Urs 27.11.: «Alpha Kanal wählen», harter Schnitt): Unter Farbe 1 und Farbe 2 je ein Regler «Deckkraft» mit Zahl (0–100 %). Mit Farbe 2 auf 0 % läuft der Verlauf weich ins Durchsichtige aus. Editor, Vorschau und Präsentation gleich; bestehende Verläufe bleiben deckend (100 %).

## [3.12.22] - 2026-11-27

### Changed
- **Raster und Filmstreifen gleich** (Urs 27.11.): Ein Klick im Raster macht die Folie auch im Filmstreifen zur offenen Folie. Einfügen (Zwischenablage, neue Folie, Präsentation einfügen) setzt nach der gewählten Folie ein, bei mehreren nach der letzten.
- Miniatur-Grösse im Raster: Zahl mit «px» vom Hub-Baustein, doppelte Anzeige entfernt.

## [3.12.21] - 2026-11-27

### Changed
- **Abspiel-Anzeige in der Notizen-Leiste** (Urs 27.11.): «Abspielen · Folie x · Schritt y/z ✕» steht beim Abspielen mittig in der Notizen-Leiste an Stelle des Knopfs, nicht mehr über der Folie.

## [3.12.20] - 2026-11-27

### Added
- **Alle Objekte der Folie wählen** (Urs 27.11.): Auf der Folienfläche markiert Ctrl+A alle Objekte (Befehl «Folienfläche: alle Objekte wählen», änderbar unter Weitere Befehle). Im Filmstreifen und Raster wählt Ctrl+A weiterhin alle Folien, in Textfeldern den Text.

## [3.12.19] - 2026-11-27

### Added
- **Notizen ein- und ausblenden** (Urs 27.11.): Pfeil-Knopf links in der Notizen-Leiste klappt die Notizen ein wie die Seitenspalten; eingeklappt bleibt die Leiste mit «▶ Abspielen» unten. Gemerkt je Person; Befehl «Notizen ein-/ausblenden» unter Weitere Befehle (ohne Vorgabe-Kürzel).

## [3.12.18] - 2026-11-27

### Changed
- **Abspielen in der Notizen-Leiste** (Urs 27.11.): «▶ Abspielen» sitzt mittig in der Kopfzeile «Notizen zu dieser Folie» statt schwebend über der Folie.

## [3.12.17] - 2026-11-27

### Changed
- **Mehrere Objekte kopieren** (Urs 27.11.): Kopieren, Ausschneiden, Duplizieren und Einfügen nehmen alle gewählten Objekte (Ebenen-Reihenfolge bleibt); nach dem Einfügen sind alle gewählt. Auf einer anderen Folie landen sie an derselben Stelle, auf derselben Folie versetzt.

## [3.12.16] - 2026-11-27

### Added
- **Befehl «Alle Folien wählen»** (Urs 27.11.): im Filmstreifen und in der Rasteransicht, Vorgabe Ctrl+A, änderbar unter Ribbon anpassen › Weitere Befehle. In Feldern und auf der Folienfläche bleibt Ctrl+A wie gewohnt. Der Knopf «Alle» im Raster nimmt denselben Befehl.

## [3.12.15] - 2026-11-27

### Fixed
- **Markierung in der Effektliste** nimmt die Hub-Akzentfarbe statt einer festen Farbe (hell/dunkel).

## [3.12.14] - 2026-11-27

### Changed
- **Zahlenfelder und Schieber nach Hub-Standard** (Urs 27.11., Hub 2.37 HubNumberStep): Grosse Zahlenfelder (Kontur, Schritt, Tabelle, Medien-Verzögerung) als ‹ Zahl › mit grossen Knöpfen; kleine Felder (Menüband, Position/Grösse, Zacken) bleiben ohne Knöpfe (Urs 27.11.); alle Schieber (Deckkraft, Verlaufswinkel, Pfeil Spitze/Schaft, Laser, Rastergrösse) mit Zahlenfeld daneben. Eigenbau aus 3.12.11/3.12.13 entfernt.

## [3.12.13] - 2026-11-27

### Changed
- **Pfeil-Regler in einer Zeile mit Wert** (Urs 27.11.): «Spitze» und «Schaft» stehen nebeneinander über die volle Breite, je mit Zahlenfeld (%), das man kopieren und eintippen kann; Regler und Zahl bleiben gleich.

## [3.12.12] - 2026-11-27

### Changed
- **Abspielen im Editor wie Vorführen** (Urs 27.11.): Auf der Folienfläche gelten dieselben Vorgaben wie beim Vorführen: weiter mit Leertaste, Eingabe, →, ↓, Bild↓, zurück mit ←, ↑, Bild↑ (je Person änderbar unter Weitere Befehle).
- **Zeitsteuerung läuft beim Abspielen**: «Automatisch starten nach» und «Folie automatisch weiter» laufen jetzt auch im Abspielen-Modus des Editors.

## [3.12.11] - 2026-11-27

### Changed
- **Schritt (Klick-Nr.) mit grossen Knöpfen** (Urs 27.11.): Unter Einblenden und Ausblenden steht der Schritt als ‹ Nummer › in einer Zeile; die Knöpfe zählen eins hinunter/hinauf (mindestens 1), die Zahl bleibt von Hand eintippbar.

## [3.12.10] - 2026-11-27

### Added
- **Pfeil: Spitze und Schaft einstellbar** (Urs 27.11.): Format › Formen zeigt beim Pfeil die Regler «Spitze» (Länge der Spitze) und «Schaft» (Dicke). Editor und Präsentation rechnen gleich; bestehende Pfeile behalten ihre Form (Vorgaben 40 % / 30 %).

## [3.12.9] - 2026-11-27

### Changed
- **Effektliste: ein Klick wählt das Objekt** (Urs 27.11.): Klick auf eine Zeile unter Effekte › Reihenfolge wählt das Objekt auf der Folie, die Liste bleibt offen und markiert es. Doppelklick (oder Eingabe) öffnet wie bisher Einblenden zum Bearbeiten.

## [3.12.8] - 2026-11-27

### Added
- **Kürzel für «Abspielen»** (Urs 27.11.): Befehl «Abspielen (Knopf über der Folie)» im Katalog (Ribbon anpassen › Weitere Befehle), Vorgabe Ctrl+Enter, je Person änderbar; das Kürzel steht im Mouseover des Knopfs.

## [3.12.7] - 2026-11-27

### Changed
- **Abspielen über der Folie** (Urs 27.11.): Der Knopf «▶ Abspielen» steht oben in der Mitte der Bühne und ist in jedem Reiter sichtbar (vorher nur im Effekte-Bereich). Beim Abspielen steht an derselben Stelle der Hinweis mit Schritt und ✕.

## [3.12.6] - 2026-11-27

### Changed
- **Effekte › Reihenfolge markiert die Auswahl** (Urs 27.11.): Objekte, die auf der Folie gewählt sind (auch Teile einer gewählten Gruppe), sind in der Effektliste hervorgehoben und werden in den sichtbaren Bereich gerollt.

## [3.12.5] - 2026-11-27

### Changed
- Effekte-Reiter mit drei Untertabs wie bei Position: Einblenden, Ausblenden, Reihenfolge (je mit Symbol). Reihenfolge = Liste aller Effekte der Folie; ohne gewähltes Objekt nur sie, Einblenden/Ausblenden gesperrt mit Hinweis «Objekt wählen». Mit Objekt gilt der zuletzt benutzte Tab (je Person gemerkt), Vorgabe Einblenden; Doppelklick in der Liste springt auf Einblenden.

### Removed
- Klapp-Abschnitte Einblenden/Ausblenden (3.10.2) samt gemerkter Schlüssel fx_in_/fx_out_ und CSS.

## [3.12.4] - 2026-11-27

### Added
- Effektliste: Doppelklick wählt das Objekt und öffnet seine Effekte.

## [3.12.3] - 2026-11-27

### Added
- Editor: →/Leertaste/Bild↓ auf der Folienfläche (ohne markiertes Objekt) spielen die aktuelle Folie ab und gehen Schritt für Schritt weiter; nach dem letzten Effekt mit dem eingestellten Folienübergang zur nächsten Folie. ←/Bild↑ geht zurück, am Folienanfang auf die vorige Folie im Endzustand. Neue «Weitere Befehle» «Folienfläche: abspielen, weiter/zurück» (änderbar).

### Changed
- Abspielen ist kein Fenster mehr: das Bild liegt genau über der Folie, oben ein kleiner Hinweis «Abspielen · Folie x · Schritt y/z». Jeder Klick (auf der Bühne wird wie sonst ausgewählt) und jede andere Taste beenden es sofort und wirken gleich; Esc beendet ebenfalls. Der Editor folgt der abgespielten Folie (Filmstreifen, Mitlaufen mit Schritt).

### Fixed
- ↑/↓/←/→ nach Klick oder Ziehen im Filmstreifen wechseln die Folie wieder, statt den Filmstreifen zu scrollen: die Pfeiltasten wirken im zuletzt angeklickten Bereich (Filmstreifen, Folienfläche, Raster), auch wenn der Fokus verloren ging.

## [3.12.2] - 2026-11-27

### Changed
- Effekte › Einblenden/Ausblenden: «Animation kopieren/einfügen», «Mit vorherigem/Nach vorherigem» (bzw. «Mit nächstem/Beim nächsten Klick») und «Dauer»/«Automatisch starten» je in einer Zeile mit zwei gleich breiten Spalten, Knöpfe und Beschriftungen mit Hub-Symbolen; bei sehr schmaler Seitenleiste untereinander.
- Effektliste: Schritt-Abzeichen als Pillen («5 → 6», Ausblenden umrandet), auch zweistellig lesbar; Plakette auf der Bühne misst die Textbreite.
## [3.12.1] - 2026-11-27

### Changed
- «Ribbon anpassen» › «Weitere Befehle» (braucht Hub 2.36.3, Urs 27.11.): alle bisher fest abgefragten Tasten stehen mit ihrer Vorgabe im Katalog und sind umstellbar — Folien im Filmstreifen (vorige/nächste/erste/letzte), Präsentieren ab aktueller Folie (Umschalt+F5, Strg+P), Zoom +/−/einpassen und Notizen (Vorgabe leer), markierte Objekte löschen, Rasteransicht (Pfeile, Esc), Abspielen (weiter/zurück/schliessen), Zweitkürzel für Wiederholen, Folien löschen und Folien verschieben. Fest bleiben die Pfeiltasten zum Verschieben von Objekten auf der Folie.

## [3.12.0] - 2026-11-27

### Changed
- Menüband und «Ribbon anpassen» (Editor und Präsentation) laufen über die Hub-Bausteine (HubRibbon, braucht Hub 2.36.1): gleiche Optik, eigener Dialog entfällt.
- Tastenkürzel je Befehl und je Reiter im Anpassen-Dialog einstellbar, mit Prüfung auf doppelte und Browser-Kürzel; Alt antippen zeigt Tastentipps; Strg+F1 klappt das Menüband ein.
- Neue Befehle erscheinen in angepassten Menübändern an ihrer Standardstelle, entfernte bleiben weg (Feld `known` statt Nachrüst-Stand).

### Added
- Befehle «Folie nach oben/unten» (Strg+Umschalt+↑/↓).

## [3.9.8] - 2026-11-27

### Added
- Klick aufs Mausrad setzt den Zoom auf «Einpassen» bzw. die Rasteransicht auf Standardgrösse zurück.

## [3.9.7] - 2026-11-27

### Fixed
- Anzeige-Gerät/Mitlaufen: Hinweis «Zum Starten tippen» verschwindet nach dem Tippen.

## [3.9.6] - 2026-11-27

### Added
- Ctrl/Cmd + Mausrad zoomt die Folie im Editor bzw. die Miniaturen der Rasteransicht.

## [3.9.5] - 2026-11-27

- **«Neue Folie» ohne «Bitte warten»** (Urs 27.11.): Der Menüband-Knopf löste «Neue Folie» doppelt aus (ribbon.js-Trigger und Editor-Dispatcher), die zweite Auslösung lief in die Sperre. Doppelung entfernt; Folien-Schritte laufen jetzt nacheinander in einer Warteschlange: gleiche Aktion während sie läuft wird still verworfen, andere warten und laufen danach. Rückgängig/Wiederholen reihen sich ein, kein Schritt fehlt mehr im Verlauf. ASSET 732.

## [3.9.4] - 2026-11-27

- **Folienleiste und Seitenleiste ein- und ausklappbar wie im PD** (Urs 27.11.: «Mach die Seitenlisten noch ein und ausklapbar wie im PD»): Griff mittig am Rand der Spalte (`.hub-split__grip`, Pfeil zeigt die Richtung, Knopf mit Enter/Leertaste, Beschriftung «… ausblenden/einblenden»), Logik in `HubSplit` (Hub 2.34.2, Kopie `public/assets/js/hub-split.js` als Rückfall). Die Spalte bleibt mit Breite 0 im Raster, die Bühne wird breiter. Stand je Person serverseitig in `editor_ui.collapsed` und schon im ersten Bild gesetzt. Braucht Hub 2.34.2 (sonst bleibt der Griff verborgen). ASSET 731.

## [3.9.2] - 2026-11-27

- **Farbwahl nach Hub-Standard** (Regel Urs 09.11. «Markenfarben und Emojis: ein Standard»): Jedes Farbfeld der FolienSchmiede (Editor: Hintergrund, Textfarbe, Füllung, Verlauf, Kontur, Eigenschaften, Markieren/Textfarbe in der Auswahl, Iconify; Textvorlagen; Traktanden- und Logos-Import; Präsentieren: Zeitleiste, Laserpointer) ist jetzt `HubColorPicker`: Farbfeld, daneben «▾» mit den Markenfarben des Hubs als Punkte. `sf-color.js` hüllt jedes `<input type="color">` in den Baustein (Feld, id und Zuhörer bleiben; auch später eingefügte Felder). Pop-up im Menüband fest am Bildschirm, damit die Gruppenkante es nicht abschneidet.
- **Eine Quelle:** Markenfarben kommen nur noch aus der Palette des Hubs (`SfPalette`, signierter App-Aufruf `GET api/palette.php`, 10 Min. Zwischenspeicher in `data/cache/hub-palette.json`, als `<script id="hub-palette">` in jeder Seite).
- **Weg (Altes raus):** eigene Markenfarben-Liste (`brand_colors` in config.json, Vorgaben in config.php, Bearbeiten im Reiter Farben, Nachholen in `--settings`), alle eigenen Farb-Schnellwahlen (Menüband-«Marken ▾», Swatch-Reihen, Import-Farbmenü, Präsentieren-Farbmenü) samt CSS und Sprachschlüsseln. Reiter Farben zeigt die Hub-Palette nur noch an, Admins mit Knopf «Im Hub bearbeiten» (Hub › Einstellungen › Branding).
## [3.9.1] - 2026-11-27

- **Editor-Spalten verstellbar wie im Hub** (Urs: «Die Spalten kann ich auch nicht vergrössern und verkleinern. Mach die so wie im Hub Vorgegeben.»): Folienleiste links und Seitenleiste rechts haben den Hub-Trenner (`HubSplit`, `.hub-split__resize--end`/`--start`, Lab › Layout): ziehen oder Pfeiltasten. Breite je Person serverseitig (`editor_ui.splits` über `user_api.php set_editor_ui`), Grenzen links 160 px bis 40 % und rechts 260 px bis 50 % der Fensterbreite. Miniaturen und Bühne passen sich über ihre ResizeObserver an. `hub-split.js` kommt vom Hub (`sf_hub_asset`), eigene Kopie nur als Rückfall.
- **Filmstreifen bleibt stehen** (Urs: «Wenn ich da was lösche, dann spult der Filmstreifen immer von anfang bis zu dem Punkt …»): Beim Neuaufbau (Löschen, Verschieben, Einfügen, Duplizieren, Rückgängig) bleibt die Bildlaufposition; die aktive Folie wird nur ohne Animation knapp in den Blick geholt, wenn sie ausserhalb liegt. Gleich in der Rasteransicht und beim Folienwechsel. Nach dem Speichern wird wie bisher nur die eine Miniatur ersetzt.
- Notizbereich: Höhen-Trenner jetzt auch mit Pfeiltasten (auf/ab je 24 px), Zeigerereignisse (Touch/Stift) und Fokus sichtbar.

## [3.9.0] - 2026-11-27

- **Schriften aus dem Hub** (Urs 27.11.: Ordner mit dem FF-Picker im Hub, «für die verschiedenen Programme»): Editor, Presenter, Vorschau, Zuschauer/Player laden `<Hub>/api/fonts.php?css=1`; jede Schriftwahl bietet die Hub-Schriften zuoberst an. Eigene Schriften-Upload-Verwaltung entfernt, Reiter «Schriftarten» verweist auf Hub › Palette › Schriften. Übergang: früher hochgeladene Schriften laufen weiter (auch als Alias ohne Schnitt-Wort). `scripts/schriften-in-hub.php` kopiert sie in den Hub-Ordner. Braucht Hub 2.34.0. Assets 727.

## [3.8.3] - 2026-11-27

- **Einstellungen wieder vollständig** (Urs: «Bei den Einstellungen müsste ich nun aber auch die Einstellungen machen können, die ich zuvor gemacht habe» / «Also Vorlagen definieren. Schriftvorlagen, Dann Schriftarten etc.»): Hub › FolienSchmiede › Modul-Einstellungen und neu der Knopf «Einstellungen» auf der Startseite führen zu einer Reiterleiste (Hub-Stil `hub-list-tabs`): Folienvorlagen (mit Vorlagen-Ordner, jetzt für alle), Textvorlagen, Vorlageelemente, Schriftarten, Farben; für Admins dazu Allgemein (Seitentitel, Logo), Rechtschreibung (LanguageTool), Medien (Pixabay, Iconify, Openclipart). Vorher zeigte Modul-Einstellungen nur admin_settings.php (Vorlagen-Ordner + Admin-Abschnitte), die Vorlagen-Seite war von nirgends verlinkt.
- Felder im Lab-Stil (`hub-admin-field`/`hub-admin-check`), automatisches Speichern mit «speichert …/gespeichert» statt Speichern-Knöpfen (Allgemein, Rechtschreibung, Medien, Textvorlagen, Vorlageelemente, Farben); Logo lädt nach der Wahl im FileForge-Dialog sofort hoch. LanguageTool-Adresse mit HubUrl (Browser `hub-url.js`, Server `src/HubUrl.php`, Kopie aus dem Hub).
- Weg (Altes raus): SMTP-Abschnitt, Testmail (`admin_api.php`), `SmtpMailer`, SMTP-/Registrierungs-Vorgaben in Config/Demo, Rollen-Nachtrag «erster Benutzer wird Admin» in config.php. Die FolienSchmiede verschickt keine Mails mehr.
- Übernahme: `php scripts/migrate-to-ff.php --source=<alt> --settings [--run]` holt Seitentitel, Logo, LanguageTool, Pixabay, Iconify, Openclipart, Markenfarben, Textvorlagen, Vorlageelemente und Logos-Importvorlagen aus der alten Installation, nur wo neu leer oder ab Werk; Sicherung `*.vor-settings-*`; Ausgabe nur Schlüsselnamen.

## [3.8.2] - 2026-11-27

- **Folienschrift im Editor** (Urs: «Schrift stimmt irgend wie auch noch nicht»): Die Übernahme aus der alten FolienSchmiede brachte die hochgeladenen Schriften nicht mit (`custom_fonts` in config.json und Dateien in `public_html/uploads/fonts`; Biel: «Myriad Pro»). Die Editor-Bühne (Canvas) fiel deshalb auf die Serif-Vorgabe des Browsers, Miniaturen/Präsentation (HTML, `font-family:X,sans-serif`) auf eine serifenlose Ersatzschrift. `Migration` holt die Schriften jetzt nach (nur fehlende, Datei nie ersetzt, im Protokoll gezählt); ein erneuter `migrate-to-ff.php … --run` holt nur das Fehlende.
- Editor-Bühne fällt bei fehlender Schrift wie die Präsentation auf `sans-serif` (Konva-Text), der gespeicherte Familienname bleibt.

## [3.8.1] - 2026-11-27

- **Kein Sand-Flackern beim Neuladen** (Urs: «Wenn ich das neu lade, kommt immer zuerst die sandfarbene Oberfläche»): SlideForge merkt sich den vom Hub gemeldeten Fenster-Stil und Akzent (Hell und Dunkel, von cf-theme.js geprüft) je Person (`users.json` `hub_look`) und setzt ihn beim nächsten Laden schon serverseitig ins erste Bild (`<html>`-Attribute und `window.__cfDialogChromePrefs`), wie OfficeForge seit 16.10. Vorher galt bis zur Nachricht des Hubs nur der eine Wert der Rahmenadresse (bei «System» der Dunkel-Wert, ohne Akzent gar keiner) oder die Sitzung. ASSET 724.

## [3.8.0] - 2026-11-27

- **Vorschau auf Gerät (Mitlaufen)** (Urs: «einen Player, der mir immer anzeigt, was ich gerade formatiere»): Ansicht › Vorschau hat neben «Vorschau» nur noch **«Auf Gerät»**; «Fenster» und «Lokal» sind weg. Der Knopf öffnet das Geräte-Raster im Modus Mitlaufen: Das gewählte Anzeige-Gerät zeigt laufend die Folie, die im Editor bearbeitet wird, und übernimmt jede gespeicherte Änderung, ohne die Seite neu zu laden (nur geänderte Folien werden ausgetauscht; Vollbild, Ton und Tipp-Freigabe bleiben). Die Kachel «Web» öffnet dieselbe Mitlauf-Ansicht in einem eigenen Fenster dieses Browsers. Leeren oder eine Präsentation senden beendet das Mitlaufen.
- Weg: Der Editor meldet Folie und Speicherstand in den Live-Zustand (`live.php`, Kanal «editor», Recht Bearbeiten); Gerät/Fenster fragen im bestehenden 400-ms-Rhythmus ab. Kein neuer Takt. Gerät senden prüft wie bisher canView, Bespielen prüft der Hub.
- Angepasste Menübänder: «Fenster»/«Lokal» werden zu «Auf Gerät»; «Folien löschen» (3.7.0) wird einmalig nachgerüstet (Layout-Stand, später Entferntes bleibt weg).

## [3.7.0] - 2026-11-27

- **Rückgängig/Wiederholen für Folien-Aktionen** (Urs: «Ja und kann ich auch mehrere Folien gleichzeitig löschen?»): Präsentation einfügen, Einfügen aus der Zwischenablage, Löschen, Ausschneiden, Duplizieren, Neue Folie und Verschieben legen den Folienstand in den Verlauf (30 Schritte). Strg+Z/Strg+Y und die Menüband-Knöpfe nehmen zuerst die Inhaltsschritte der offenen Folie, dann die Folien-Aktionen. Neuer Schreibweg `restore_slides` (Recht Bearbeiten + CSRF wie die übrigen); Folien, die es noch gibt, behalten ihren heutigen Inhalt. Medien in assets/ bleiben liegen.
- **Mehrere Folien löschen:** Mehrfachauswahl auch in der Folienleiste (Strg/Cmd-Klick einzeln, Umschalt-Klick Bereich, markiert, `aria-selected`). Entf/Rücktaste, ✕ und neuer Menüband-Knopf Einfügen › «Folien löschen» löschen alle markierten nach Rückfrage («3 Folien löschen?»); die letzte Folie bleibt. Ausschneiden, Kopieren und Duplizieren nehmen dieselbe Auswahl.

## [3.6.4] - 2026-11-27

- **FileForge-Dialog statt «Lokale Medien öffnen»** (Urs: «Das öffnet aber immer noch das Lokale Medien öffnen ding und nicht der FF Piker»): Menüband Einfügen › «Präsentation einfügen» wählt die Datei mit HubFiles.choose (pptx/odp/xlsx/chs/zip/html/cfs); api.php holt sie als die Person über AppCall (`FfFile::fetchTemp`) und gibt sie dem bestehenden Einfügen. Ebenso Import (import.php, inkl. Logos-Erkennung über den Dateianfang vom Server), Traktanden-Import und Zusatz-PowerPoint (agenda.php), Folien-Set importieren (templates.php) und Logo (Administration; SVG wird jetzt entschärft). Medien-Seitenleiste: Knopf «Aus Dateien einfügen». Hochladen vom Rechner bietet der Dialog selbst.

## [3.6.3] - 2026-11-27

- **Ctrl+R im Hub bleibt in der Folie** (Urs: «Wenn ich ctrl+r klicke lande ich auf dem Dashboard statt in der Folie»): Startseite, Editor und Presenter melden dem Hub-Rahmen ihren Pfad (`hub-app-ort`, Hub ≥ 2.33.9; Editor/Presenter mit `slide=` und FileForge-Verweis `ref=`). Ist die Arbeitskopie beim Neuladen schon aufgeräumt, öffnet die Startseite die Datei neu aus FileForge (mit Hinweis) statt «kein Zugriff».

## [3.6.2] - 2026-11-27

- **Medien-Seitenleiste füllt die Höhe** (Urs: «Halbwegs repariert»): Die Liste reicht bis unten, Bildlauf nur in der Liste; vorher fester Kasten von ~240 px.

## [3.6.1] - 2026-11-27

- **Farben aus der Hub-Einstellung** (Urs 27.11.: «Farb Design vom SlideForge stimmt übrigens immer noch nicht»): `sf_hub_asset()` nimmt grundsätzlich die Datei des Hubs, die Kopie in `public/assets/` nur als Rückfall. Die veraltete eigene `cf-theme.js` hatte die Akzent-Nachricht des Hubs ignoriert, die Oberfläche blieb Sand. Feste Beigewerte (Schachbrett um die Folie, Scrim, Overlay, Schatten) auf Hub-Töne.
- **Medienliste im Editor** (Urs: «Darstellung stimmt auch noch nicht»): Einträge werden nicht mehr zusammengedrückt, Vorschau 3:2, Name mit Auslassungspunkten, Aktionen in eigener Zeile, Knöpfe im Hub-Stil.

## [3.5.0] - 2026-11-27

- **Medien über den FileForge-Dialog** (Urs 27.11.: «FF Piker verwenden, bei Bilder, Audio Video auch»): Einfügen › Bild/Audio/Video, Ersetzen in den Eigenschaften und Folienhintergrund Bild/Video wählen die Datei mit `HubFiles.choose` (Hochladen vom Rechner im Dialog). `upload.php` holt sie als die Person über AppCall (`FfFile::read`) und legt eine Kopie in `assets/` (steckt in der .cfs). Eigene Datei-Inputs und der direkte Upload-Weg entfernt; Grenzen Grösse/Dateityp bleiben.

## [3.2.0] - 2026-11-27

- **Übernahme des Altbestands** (H6, Entscheid Urs: in «Meine Ablage», ohne Freigaben): `php scripts/migrate-to-ff.php --source=<alt> [--run]`, Probelauf ohne `--run`, Protokoll in `data/migration/`, sichtbar im Task Manager des Hubs.
- **Öffnen aus FileForge ohne Zeitfenster**: der Haken prüft nur das Recht, geladen wird im Browser (`index.php?open=`).
- **Neu aus Vorlage** zeigt die Vorlagen aus dem eingestellten Ordner (FileForge ≥ 1.18.1, Aktion `list`).
- **Vorschaubild** `thumb.png` in jeder .cfs (erste Folie, schematisch).
- **Präsentationen ausserhalb von FileForge** (Logos-/Agenda-Import) speichert «Speichern unter …» als .cfs in FileForge.
- **Entfernt:** WebDAV-Laufwerke, Freigaben in der FolienSchmiede, öffentlicher Zuschauer-Link (`view.php`), Teilen-Dialog. Freigeben geht über FileForge.

## [3.0.0] - 2026-11-27

- **Läuft im Hub** (Urs 27.11.: «slide Forge ganz in den Hub integrieren»): Ordner `public_html/` heisst `public/`; SlideForge liegt im Web-Share des Hubs und ist unter `<Hub>/slideforge/` erreichbar (NPM bildet `/slideforge/` auf `/slideforge/public/` ab). Basispfad über `sf_base()`.
- **Anmeldung nur über den Hub**: `Auth::bootstrapHub()` übernimmt die Sitzung aus `SharedAuth::bootstrapModule`, Benutzer-ID = Personenschlüssel des Hubs (`ct:<id>`/`u:<name>`), Administrator = Gruppe `admins` im Hub. Eigene Sitzung `SFSESSID` mit Pfad `/slideforge/`.
- **Entfernt**: Login, Registrierung, E-Mail-Bestätigung, Einladungen, Abmelden, Passwort und Konto im Profil, Benutzerverwaltung, Theme-/Sprachumschalter, PWA (Manifest, Service Worker), App-Leiste (HubDock, Launcher), eigene Kopien von SharedAuth und HubPersonKey, `demo-reset.php`, Auslieferungsskripte `.deploy/` und `docker/` des eigenen Containers.

## [2.1.33] - 2026-11-14

### Changed

- Präsentationsmodus: Vollbild-Knopf oben links entfernt (Ribbon-Icon und Taste F/F5 bleiben).

## [2.1.32] - 2026-11-14

### Added

- Taste F5 startet das Vollbild (Konsole und Beamer-Fenster, wie in PowerPoint).
- Fernsteuerung am Tablet: Presenter-Stick/Pfeiltasten schalten die Folien.

## [2.1.31] - 2026-11-14

### Fixed

- Präsentation: Pfeiltasten/Presenter schalten auch, wenn das Beamer-Fenster den Fokus hat (Tasten gehen an die Konsole).

## [2.1.30] - 2026-11-14

### Fixed

- Präsentationsmodus: Navigationstasten werden sofort beim Laden gebunden, nicht erst wenn der Folien-Frame bereit ist.

## [2.1.29] - 2026-11-14

### Fixed

- Präsentationsmodus: Navigationstasten (Presenter) im Capture-Durchgang, Seite holt sich beim Start den Fokus.

## [2.1.28] - 2026-11-14

### Fixed

- Präsentationsmodus: Presenter-Tasten (Bild auf/ab, Pfeile) schalten auch, wenn nach einem Klick ein Knopf den Fokus hat; Pfeil auf/ab blättert.

## [2.1.27] - 2026-11-14

### Added

- Präsentationsmodus: Vollbild als Ribbon-Befehl (Icon hinter «Notizen»).

## [2.1.26] - 2026-11-14

### Changed

- Editor und Präsentationsmodus ohne Kopfleiste (mehr Platz auf dem Tablet); Titel steht neben «← Dashboard».
- Präsentationsmodus: Vollbild-Knopf und Taste F.

## [2.1.25] - 2026-11-06

### Fixed

- Hängende Anfragen (504 im Hub-Embed): `Storage` sperrt eine Datei, die der Request schon exklusiv hält, nicht ein zweites Mal (Selbst-Deadlock); Dateisperren warten höchstens 15 Sekunden und melden dann einen Fehler.


## [2.1.24] - 2026-11-05

### Changed

- Hub-Leiste: Personen-Kennung `ct:<Personennummer>` / `u:<name>` über `HubPersonKey` (shared), statt blosser Zahl; keine Datenmigration


## [2.1.23] - 2026-11-05

### Changed

- Stand der Auslieferung 04.11. (ASSET_VERSION 690: Agenda-Import, Präsentation einfügen, Excel-Import); VERSION für den Hub-Installer nachgezogen


## [2.1.22] - 2026-08-09

### Changed

- merge: Morgen-WIP aus Stash wiederherstellen
- fix: Presenter stumm, Ton nur auf Zuschauer-Ansicht
- WIP on main: e55370b chore(release): v2.1.18
- index on main: e55370b chore(release): v2.1.18
- untracked files on main: e55370b chore(release): v2.1.18


## [2.1.21] - 2026-08-09

### Changed

- fix: Ton bei Medien-Steuerung auf der Presenter-Konsole hörbar


## [2.1.19] - 2026-08-09

### Changed

- feat: Media-Leave-Confirm und Seek im Präsentationsmodus


## [2.1.18] - 2026-08-02

### Changed

- fix: User-Menü im Hub-Embed nicht doppelt anzeigen


## [2.1.17] - 2026-08-02

### Changed

- fix: Dashboard-Crash durch \$shared in user_menu behoben


## [2.1.16] - 2026-08-02

### Changed

- fix: Hub-Dock trotz sticky auth_via=local wieder anzeigen


## [2.1.15] - 2026-08-02

### Fixed

- Launcher: Hub-Login zeigt wieder den vollen Programm-Dock (shouldUseHubLogin)
- Embed-Erkennung nur noch via hub_theme (kein generisches iframe-Hide)

## [2.1.14] - 2026-08-02

### Fixed

- Launcher: Hub-Session erkennen; lokales Logo ohne Dock-Label
- Dialog-Bridge: lazy Upgrade, kein Host-Umhängen

## [2.1.13] - 2026-08-02

### Added

- HubFloatDialog (globale Klassen): schwebende Dialoge mit Min/Max, 8-Resize, fitContent
- Launcher: voller Dock bei Hub-Login, ein CF-Logo bei lokalem Login

### Changed

- FolienSchmiede im Hub wie SendeSchmiede (`external` + `hubAuth`); Embed blendet SF-Dock aus

## [2.1.12] - 2026-08-02

### Fixed

- Present-Notizen: Kontrast zur Folienfarbe (helles UI-Theme lesbar)

## [2.1.11] - 2026-08-02

### Added

- Present: Notizen-Einstellungen im Ribbon (offen / geschlossen / übernehmen)

## [2.1.10] - 2026-08-02

### Changed

- Topbar: HubUserMenu-Standard (Anzeigename-Chip; Sprache/Darstellung im Menü)

## [2.1.9] - 2026-08-02

### Changed

- Notizen-Overlay nach unten einklappen (Register bleibt); Deploy-Regel im Repo


## [2.1.8] - 2026-08-02

### Changed

- chore: Regel — nach Umsetzung sofort Release/Deploy
- fix: Notizen-Overlay nach unten einklappen


## [2.1.7] - 2026-08-02

### Changed

- **Notizen-Overlay:** Ausblenden nach unten; horizontales Register bleibt zum Einblenden

## [2.1.6] - 2026-08-02

### Added

- **Notizen-Overlay einklappbar** — Tippen auf die Fläche schiebt Notizen zur Seite; Register rechts blendet sie wieder ein (touch-tauglich)

## [2.1.5] - 2026-07-29

### Changed

- chore: snapshot current module state


## [2.1.4] - 2026-07-28

### Added

- **Present-Ribbon** — konfigurierbar wie im Editor (Anpassen, Tabs/Gruppen, `present_ribbon_layout`)
- Tab **Ansicht:** Steuerungsleiste (Panel-Toggles), Timer/Zeitleiste/Uhr/Laser als Dialoge
- Fortschritt & Navigation als eigene Ribbon-Toggle-Befehle (Editor + Present)

### Changed

- Editor-Anzeige: nur Fortschritt/Navigation; Link und Bildschirm nur noch im Präsentationsmodus
- Present-Tab «Einstellungen» → **Ansicht**; dezente Spalten-Trenner; Abstände in Zuschauer-/Steuerungs-Gruppen

## [2.1.3] - 2026-07-27

### Added

- **Konfigurierbares Ribbon** — Tabs/Gruppen/Befehle anpassen, Peek, Standard-Layout inkl. Ansicht→Vorschau/Fenster/Lokal
- **Theme** Tag / Nacht / System (Prefs + `cf_theme`-Cookie)
- Dashboard Phase 1; einheitliche Dialoge (`.sf-dialog-*`); Zoom-Stepper; Einstellungen als Ribbon-Widgets

### Fixed

- Hub→SlideForge: fehlende Shared-Helpers im Docker-Container (500 nach Login)
- Diverse Ribbon-/Editor-/Raster-Fixes (siehe AENDERUNGEN C)

## [2.1.2] - 2026-07-08

### Changed

- **Raster-Ansicht:** Meta-Zeile pro Kachel — Folien-ID, Übergangs-Icon, Zeit links; Effektname (z. B. „Schieben“) rechtsbündig

## [2.1.1] - 2026-07-08

### Added

- **Raster-Ansicht:** Folien per Drag & Drop sortieren (wie Filmstreifen)
- **Raster-Ansicht:** Steuerung pro Kachel — Präsentieren ein/aus, Duplizieren, Löschen
- **Raster-Ansicht:** Miniatur-Grösse per Schieberegler (Statusleiste), gespeichert pro Benutzer
- **Dokumentation:** `docs/AENDERUNGEN.md` (Bugs & Features A→B→C), `docs/BACKLOG.md`

### Fixed

- Vorlageelemente: Speichern schliesst den Dialog nach erfolgreichem Speichern
- Editor-Ebenen: keine „Ghost-Ebenen“ mehr beim Sortieren (Layer-Reorder korrigiert)
- Editor: leere Zustände im Objekte-/Elemente-Bereich zentriert dargestellt

## [2.1.0] - 2026-07-07

### Added

- **Folien-Sets (Layout-Sets):** Mehrere Layout-Folien pro Set, Set-Editor, Element-Zonen, Platzhalter-Rollen
- **Logos-Import:** HTML-Export aus Logos Sermon Builder → Folien mit Layouts + Sprechernotizen
- **Logos-Zuordnung** pro Set (Folien vs. Notizen vs. nicht verwendet)
- **Vorlageelemente:** Modal zur Textvorlagen-Verknüpfung; Standard-Fallback-Textvorlage
- **Set-Import/Export** als `.chs` (ZIP-Archiv)
- **Standard-Set „Schlicht“** in `seed/layout-sets/schlicht/` für Neuinstallationen
- **Vorlage anwenden** in Präsentationen mit verknüpftem Folien-Set
- **Editor:** Vorlagen-Accordion (Ebenen → Objekte → Vorlagen), Raster-Ansicht im Hauptfenster (Toggle)
- **Editor:** Raster-Ansicht und Masterfolie in linker Menüleiste
- i18n für Set-/Logos-Funktionen in DE, EN, FR, IT, RM

### Changed

- Editor: Folien-Raster ersetzt separates Modal — Ansicht wechselt im Hauptbereich
- Editor: Rechte Sidebar mit einheitlichem Scroll-Verhalten

### Fixed

- Layout-Folien ohne `layoutKey` erscheinen in der Vorlagen-Auswahl (eindeutige `slide.id`)
- Editor: Doppelte Scrollbalken in der rechten Sidebar behoben

## [2.0.3] - 2026-07-06

### Changed

- feat: Plan B — Folien-Raster, Live-Sync, Release-Deploy-Hooks
- chore: Abschluss v2.0.x — Docs sync, Patch-Skript entfernt
- docs: README Mobile-Abschnitt und Release v2.0.2

## [2.0.2] - 2026-07-04

### Added

- **PWA (Progressive Web App):** `manifest.php`, Service Worker (`sw.php`), App-Icons — installierbar auf Android/iOS („Zum Home-Bildschirm“ / „App installieren“), `display: standalone`, Start auf dem Dashboard

## [2.0.1] - 2026-07-04

### Added

- **Feature-Tour (Demo):** Folien „Mobile Fernsteuerung“ mit Screenshot in DE / EN / FR / IT / RM
- Screenshot-Asset `ui-remote.png` und erweitertes Capture-Skript (`capture_feature_tour_screenshots.py`)

### Fixed

- **QR-Code** im Present-Menü: PHP-Warnungen aus phpqrcode zerstörten die PNG-Ausgabe — Konstanten-Guards und sauberer Output-Buffer in `RemoteQr.php`

## [2.0.0] - 2026-07-04

### Added

- **Mobile Fernsteuerung:** Smartphone-Dashboard (Listenansicht), `present_remote.php` für Foliensteuerung über Internet (HTTPS + `live.php`)
- **Remote-Oberfläche:** Tabs Folie, Vorschau, Uhr (analog mit Zahlen), Timer (Studiouhr), Laser; Fortschrittsbalken rechts am Handy (Timer-Sync)
- **QR-Code** im Präsentationsmodus (Menü „Präsentieren“) für schnellen Remote-Zugang — serverseitig via `qr.php` / phpqrcode
- **Laser vom Handy:** Touch-Fläche auf der Remote-Oberfläche steuert den Laserpointer am Desktop-Present
- Mobile Erkennung (`Mobile.php`, `mobile-detect.js`, max-width 767px; `?mobile=1` / `?desktop=1` zum Testen)
- Editor auf Smartphones gesperrt mit klarer Meldung; iPad/Tablet ≥768px = Desktop-UI
- Anzeige „Handy verbunden“ im Präsentationsmodus; Remote-Link kopieren
- `remote_slide.php` für Folien-Vorschau auf der Remote-Oberfläche

### Changed

- `live.php` erweitert: Remote-Schritte, Laser-Sync, Session-Heartbeats, Present-Config-Sync; View-Recht für Remote-Aktionen
- Present-Modus: schnellerer Laser-/Remote-Poll, Menüs schliessen bei Klick ausserhalb

### Fixed

- Mobile-Remote: Panel-Sichtbarkeit (`hidden`-Attribut vs. CSS), Laser-Bewegung (Throttle statt Debounce)
- QR-Code: zuverlässige serverseitige PNG-Generierung (kein fehleranfälliger Client-Generator)

## [1.0.4] - 2026-07-04

### Added

- **WebDAV integration:** per-user cloud/NAS drives in profile (up to 10, encrypted credentials); browse folders in the editor media tab and import images, SVG, audio, and video
- WebDAV lightbox preview for images, SVG, audio, and video before import
- Admin settings split into tabs: General, SMTP, Spellcheck, Media, Users

### Changed

- Media import buttons: **On slide** (primary) before **As background** (Pixabay, WebDAV)
- SVG images preserve aspect ratio when inserted on the canvas
- Demo showcase and feature tour updated for WebDAV

### Fixed

- WebDAV import JSON errors for large media files (size validation and error handling)

## [1.0.3] - 2026-07-04

### Added

- **Openclipart integration:** search and import free SVG cliparts in the editor (no API key)
- **Remove background** on image objects (light backgrounds for SVG and raster images)
- Meaningful layer names for imported media (icons, cliparts, Pixabay)
- Compact SVG/icon preview in the properties panel

### Changed

- Media tab buttons for Pixabay, Iconify, and Openclipart now show icons
- Demo showcase and feature tour updated for new media features

## [1.0.2] - 2026-07-04

### Added

- **Iconify integration:** search SVG icons from 150+ sets in the editor (no API key)
- Icon color picker in the search dialog and on the slide (brand palette supported)
- Server-side SVG tinting for preview, presentation, and export

## [1.0.1] - 2026-07-04

### Added

- Optional spellcheck before entering presentation mode (user setting in editor)
- Feature tour v2: navigation slide, UI screenshots, multilingual tours (DE/EN/FR/IT/RM)
- Improved LanguageTool integration: loading UI, re-check button, markdown list handling, name-variant hints
- Invite management moved to admin user-management tab with optional email delivery

### Changed

- README restructured: feature overview by area, installation guide, AI disclaimer
- Animation and transition pickers use icon grids instead of dropdowns
- Public view links can show navigation controls when enabled on the presentation

## [1.0.0] - 2026-07-03

### Added

- Self-hosted, file-based multi-user editor for [reveal.js](https://revealjs.com) presentations
- Canvas editor (Konva.js): shapes, text, images, video, animations, templates
- Presentation mode with live sync, offline HTML/ZIP export, PDF, PPTX, and ODP export
- PPTX import, user roles, SMTP mail, invite links, DE/EN/FR UI
- Seven default slide templates seeded on first admin registration

[1.0.3]: https://github.com/uwunderli/slideforge/releases/tag/v1.0.3
[1.0.2]: https://github.com/uwunderli/slideforge/releases/tag/v1.0.2
[1.0.1]: https://github.com/uwunderli/slideforge/releases/tag/v1.0.1
[1.0.0]: https://github.com/uwunderli/slideforge/releases/tag/v1.0.0
