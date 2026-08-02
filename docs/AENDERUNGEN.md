# SlideForge – Änderungen (Bugs & Features)

Eine Warteschlange für **Bugs** und **Features**, die (noch) keinem Release zugeordnet sind.

Workflow:

1. **A) Neu** — Roh-Einträge mit **Typ** (`Bug` / `Feature`), Beschreibung und gewünschter Änderung.
2. **B) Geplant** — gemeinsam aus A übernehmen, priorisieren, ggf. schärfen.
3. **C) Umgesetzt** — nach Umsetzung hierher verschieben (Was, wo, wann).

**Typ**


| Wert        | Bedeutung                               |
| ----------- | --------------------------------------- |
| **Bug**     | Fehler / falsches Verhalten korrigieren |
| **Feature** | neue oder erweiterte Funktionalität     |


**Prio (Vorschlag)**


| Prio   | Bedeutung                          |
| ------ | ---------------------------------- |
| **1**  | Blocker / kaputte Kernfunktion     |
| **2**  | Schneller sichtbarer Fix           |
| **3**  | Klarer UX-Gewinn, begrenzter Scope |
| **4**  | Produktnutzen, mittlerer Aufwand   |
| **5+** | Gross / braucht Briefing           |


**Handoff an Cursor:** *„Lies* `docs/AENDERUNGEN.md` *und arbeite Abschnitt B ab.“*  
Nach jedem Punkt: Eintrag von B nach C, bei UI-Fixes ggf. `ASSET_VERSION` bumpen.

Verwandt: [BACKLOG.md](BACKLOG.md) · [RELEASES.md](RELEASES.md) · [README.md](README.md)

---

## A) Neu

| #   | Typ     | Thema                         | Beschreibung                                                                 | Gewünschte Änderung / Hinweis                                                                 |
| --- | ------- | ----------------------------- | ---------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| —   | Feature | Text-Animationen Animate.css  | Zusätzliche Texteffekte über Reveal-Fragments hinaus (bounceIn, zoomIn, …). | Kuratierte Auswahl (~8–12 Entrance) nur für Text; Bridge Fragment↔Animate.css. **Pendent** — Aufwand ca. 1–2 Tage, nicht jetzt. |

*Ausgangslage*

---

## B) Geplant

*Bereit zur Umsetzung (Prio niedrig zuerst).*

| #   | Typ     | Prio  | Thema                            | Beschreibung                                          | Änderung                                                                                                                                                                                               | Notizen                                          |
| --- | ------- | ----- | -------------------------------- | ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------ |
| 7   | Feature | **4** | Logos Importer neu strukturieren | H1-Abschnitte, Layout-Scoring, Import-Vorschau.         | War für **v2.1.3** vorgesehen; **pausiert** bis Vorlage «Schlicht II» geprüft — [LOGOS_IMPORTER.md](LOGOS_IMPORTER.md)                                                                                    | Listen/H2-Gruppierung noch testen                |
| 14b | Feature | **8** | Dashboard Phase 2                | Geteilte Folien in Bereiche, Bereich teilen.              | Siehe [DASHBOARD_PERSONALIZE.md](DASHBOARD_PERSONALIZE.md)                                                                                                             | Phase 1 erledigt (C #14)                         |

**Erledigt aus B (nicht mehr aktiv):**

| #   | Thema | Wohin |
| --- | ----- | ----- |
| 8   | Ribbon Menü | → C (umgesetzt in **v2.1.3**, siehe [RIBBON_MENU.md](RIBBON_MENU.md) / [RELEASES.md](RELEASES.md)) |

---

## C) Umgesetzt


| #   | Typ     | Thema                        | Umsetzung                                                                                                                         | Datum / Kontext     |
| --- | ------- | ---------------------------- | --------------------------------------------------------------------------------------------------------------------------------- | ------------------- |
| —   | Feature | Ribbon-Default = aktuelles Menü | Ansicht+Vorschau/Fenster/Lokal im Standard; Theme Tag/Nacht/System in Prefs+Cookie. ASSET 553. | 2026-07-27          |
| —   | UX      | Notizen nach unten einklappen | Horizontal; Tippen → nach unten, Register unten bleibt zum Einblenden. ASSET 588. | 2026-08-02          |
| —   | UX      | Notizen-Overlay einklappbar   | Rechts Register; Tippen auf Fläche → raus, Register → rein. Touch-tauglich. ASSET 587. | 2026-08-02          |
| —   | UX      | Fortschritt/Nav als Befehle   | Einzelne Ribbon-Commands (konfigurierbar); Link-Widget getrennt. ASSET 584. | 2026-07-28          |
| —   | UX      | Present-Tab «Ansicht»         | Ribbon-Tab Einstellungen → Ansicht (`ribbon.tab_view`). | 2026-07-28          |
| —   | UX      | Anzeige als Icon-Toggles      | Fortschritt/Navigation als Ribbon-Buttons (active) statt Switch — Editor + Present. ASSET 583. | 2026-07-28          |
| —   | UX      | Editor-Anzeige schlank        | Nur Fortschritt/Navigation; Link+Bildschirm nur noch im Präsentationsmodus. ASSET 582. | 2026-07-28          |
| —   | Design  | Icon «Ribbon anpassen» einheitlich | Neues Icon (Leiste+Kacheln+Stift) in Editor-Sidebar & Present-Ribbon. ASSET 581. | 2026-07-28          |
| —   | Design  | Present-Spalten-Trenner dezent | 1px Linie statt voller Border-Balken; Akzent nur Hover/Resize. ASSET 580. | 2026-07-28          |
| —   | Fix     | Present-Layout rechts         | «Live» aus schmaler Zeitleisten-Spalte (min-width 140px) in Topbar; Timebar-Min. 100px. ASSET 579. | 2026-07-28          |
| —   | UX      | Uhr-Einstellungen Dialog      | Reihenfolge der Uhren als Standard-Dialog statt Floating-Panel. ASSET 576. | 2026-07-28          |
| —   | UX      | Laser: Toggle + Dialog        | Ein/Aus in Steuerungsleiste; Farbe/Grösse/Schweif als Standard-Dialog. ASSET 575. | 2026-07-28          |
| —   | UX      | Ghost in Steuerungsleiste     | Hauptfenster → Ghost-Icon Toggle in Steuerung; Transparenz bleibt bei Folien steuern. ASSET 574. | 2026-07-28          |
| —   | UX      | Panel-Resize weniger fummelig | Grössere Trefferflächen, sichtbarer Griff, Pointer-Capture, korrekter ns/col-Cursor. ASSET 572. | 2026-07-28          |
| —   | UX      | Medien-Panel sortierbar       | Drag-Handle + gleiche Sortier-Logik wie übrige Seitenpanels. ASSET 571. | 2026-07-28          |
| —   | UX      | Medien-Panel im Ribbon        | «Medien» Toggle in Steuerungsleiste; Sichtbarkeit persistiert, erscheint bei Audio/Video. ASSET 570. | 2026-07-28          |
| —   | UX      | Zeitleiste: Icon + Dialog     | Toggle-Icon in Steuerungsleiste (nach Timer); Timer/Zeitleisten-Einstellungen als Standard-Dialog. ASSET 569. | 2026-07-28          |
| —   | UX      | Steuerungsleiste aufgedröselt | Panel-Icons (Nächste/Uhr/Timer/Folien) eigene Gruppe; Klick = aktiv + sichtbar rechts. ASSET 568. | 2026-07-28          |
| —   | Feature | Present-Ribbon Anpassen       | Wie Editor: Dialog, Rechtsklick, Settings-Befehl; `present_ribbon_layout` / `present_ribbon.php`. ASSET 567. | 2026-07-28          |
| —   | UX      | Steuerung neben QR            | Status «Steuerung»/Handy im Ribbon links vom QR; Topbar bereinigt. ASSET 564. | 2026-07-28          |
| —   | Design  | Präsentieren-Btn wie Kopieren | Ribbon-Lokal: kompakter Pill-Button; Widget schmaler (5 Spalten). ASSET 561. | 2026-07-28          |
| —   | UX      | Present: QR wieder eigene Gruppe | QR-Gruppe «Mobile Fernsteuerung» getrennt; Lokal unter «Präsentieren». | 2026-07-28          |
| —   | UX      | Present: eine Gruppe Präsentieren | QR + Lokal kurz zusammen; Topbar-Remote-Link entfernt (bleibt im QR-Dialog). | 2026-07-28          |
| —   | Bug     | Present-QR-Dialog tot         | Modal braucht `.open`; Ribbon zeigt echten QR-Thumb. ASSET 560. | 2026-07-28          |
| —   | Design  | Zuschauer-Spalten gleichbreit   | Present-Ribbon: 2 Spalten 1fr/1fr statt fälschlich 3-Spalten-Owner-Grid. ASSET 559. | 2026-07-28          |
| —   | Design  | Zurück-Icon = Editor          | Present-Ribbon «Zurück»: Folie+Stift statt Pfeil (Pendant zu Present Monitor+Play). ASSET 558. | 2026-07-28          |
| —   | UX      | Present-QR im Ribbon klein    | QR als 2×2-Ribbon-Icon; Klick öffnet Dialog mit grossem Code + Link kopieren. ASSET 557. | 2026-07-28          |
| —   | Feature | Präsentationsmodus-Ribbon     | Present-Topbar-Menüs → Ribbon (Präsentieren/Einstellungen); Widgets Lokal/Zuschauer; Floating-Settings. ASSET 554. Customize später. | 2026-07-28          |
| 8   | Feature | Ribbon Menü                   | Konfigurierbares Ribbon (Tabs/Gruppen/Anpassen/Peek). [RIBBON_MENU.md](RIBBON_MENU.md) · **v2.1.3** | 2026-07-12 … 07-27  |
| —   | Design  | Runde Klammern typografisch   | `( )` als elliptischer Bézier-Bogen statt sin²-Wölbung. ASSET 552. | 2026-07-27          |
| —   | Design  | Dialoge = Ribbon anpassen     | Einheitliches Chrome (`.sf-dialog-*`); Cliparts/Pixabay/Share/…; Rule `sf-dialogs`. ASSET 551. | 2026-07-27          |
| —   | Feature | Zahlenfeld Mausrad global     | input[type=number]: Scrollrad ±step; Standard via number-input-wheel.js. ASSET 550. | 2026-07-27          |
| —   | Design  | Geschweifte Klammer besser    | Typografische Accolade (Bézier); Standard curly-left. ASSET 549. | 2026-07-27          |
| —   | Design  | Zoom-Widget neu               | Stepper (− % +) + Einpassen-Button im Ribbon. ASSET 548. | 2026-07-27          |
| —   | Feature | Masterfolie: disable statt hide | Präsentation-Befehle ausgegraut + Tooltip; Icon toggelt zurück. ASSET 547. | 2026-07-27          |
| —   | Feature | Ribbon-Standard = Nutzerlayout | Dein wiederhergestelltes Layout als Default (Präsentation, Entwurf, Start…). | 2026-07-27          |
| —   | Bug     | Ribbon-Icons nach Vorlage weg | Kontextfilter persistierte nicht mehr; Präsentation-Defaults restauriert. ASSET 546. | 2026-07-27          |
| —   | Design  | Auf Auswahl nur im Raster     | Ribbon-Befehl «Auf Auswahl» nur bei Rasteransicht sichtbar. ASSET 545. | 2026-07-27          |
| —   | Bug     | Raster Shift-Klick markiert Text | Keine Browser-Textselektion in Miniaturen bei Mehrfachauswahl. ASSET 544. | 2026-07-27          |
| —   | Feature | Raster → Entwurf-Ribbon       | Übergang/Timing/Anwenden nur noch im Tab Entwurf; Raster nur Auswahl. ASSET 543. | 2026-07-27          |
| —   | Design  | Texte prüfen quadratisch      | 2×2-Zelle, Button füllt Zelle ohne Seitenabstand. ASSET 542. | 2026-07-27          |
| —   | Design  | Ribbon-Trenner einheitlich    | Alle Trenner 1px border mit --ribbon-divider. ASSET 541. | 2026-07-27          |
| —   | Design  | Kopieren ausgegraut           | Disabled-Style wenn Link aus. ASSET 540. | 2026-07-27          |
| —   | Design  | Anzeige Label Navigation      | «Pfeile» → «Navigation». ASSET 539. | 2026-07-27          |
| —   | Feature | Einstellungen Auto-Save       | Speichern-Button entfernt; Meta-Felder speichern wie der Rest. ASSET 538. | 2026-07-27          |
| —   | Design  | Texte prüfen wie Teilen-Icon  | Hoher Ribbon-Button (Icon oben, Label unten); aktiv/passiv. ASSET 537. | 2026-07-27          |
| —   | Design  | B×H Tooltip Folienmasse       | «Folien Breite» / «Folie Höhe». ASSET 536. | 2026-07-27          |
| —   | Design  | Texte prüfen Icon aktiv/passiv | Spellcheck-Symbol; checked=aktiv, unchecked=passiv. ASSET 535. | 2026-07-27          |
| —   | Design  | B×H Feldbreite wie Rand/Dauer | Size-Widget 3 Spalten (wie Rand/Dauer). ASSET 534. | 2026-07-27          |
| —   | Design  | Folien-Set vertikal mittig    | Label+Select nicht mehr auf volle Zellenhöhe gestreckt. ASSET 533. | 2026-07-27          |
| —   | Design  | Einstellungen Icons vor Feld  | B/H/Rand/Dauer: Icon links statt Label oben, kompakter. ASSET 532. | 2026-07-27          |
| —   | Bug     | Kopieren nur bei Link an      | Copy-Button sofort disabled wenn Link aus; PHP-Initialzustand. ASSET 531. | 2026-07-27          |
| —   | Design  | Dateiname zentriert/breiter   | 2 Zeilen hoch + 6 Spalten; Feld vertikal mittig. ASSET 530. | 2026-07-27          |
| —   | Design  | Anzeige Spalten gleichbreit   | 3 Spalten 1fr; Link/Kopieren breiter, Bildschirm schmaler. ASSET 529. | 2026-07-27          |
| —   | Design  | Anzeige Kopieren links        | Kopieren linksbündig wie Link-Toggle. ASSET 528. | 2026-07-27          |
| —   | Feature | Einstellungen als Elemente    | Dateiname, B×H, Randabstand, Dauer, Texte prüfen getrennt; B×H klein/gross. ASSET 527. | 2026-07-27          |
| —   | Design  | Anzeige Abstände + Trenner    | 3 Spalten kompakt; Trenner wie Bildschirm auch zwischen Zeile 1/2. ASSET 526. | 2026-07-27          |
| —   | Design  | Anzeige Toggles 2×2           | Fortschritt/Link · Pfeile/Kopieren als Switch-Toggles. ASSET 525. | 2026-07-27          |
| —   | Feature | Einstellungen in Ribbon       | Folien/Präsentation als Widgets statt Floating-Panels. ASSET 524. | 2026-07-27          |
| —   | Design  | Anzeige-Widget Layout         | Optionen 1-Spalte + Bildschirm rechts, overflow clipped; Label «Pfeile». ASSET 523. | 2026-07-27          |
| —   | Design  | Anzeige-Widget Layout         | 2-Spalten-Grid (Optionen | Bildschirm), kein Überlappen. ASSET 522. | 2026-07-27          |
| —   | Bug     | Navigation = Anzeige doppelt  | settings_navigation entfernt (gleicher show_progress/controls wie Anzeige-Widget). ASSET 521. | 2026-07-27          |
| —   | Bug     | Preview-Migration wirkungslos | `foreach (... ?? [] as &$x)` schrieb nur in Kopie; Vorschau blieb in Anzeige. | 2026-07-27          |
| —   | Feature | Anzeige / Ansicht Ribbon      | Vorschau/Fenster/Lokal → Ansicht; Optionen als Anzeige-Widget. ASSET 520. | 2026-07-27          |
| —   | Feature | Export als Dialog             | Ribbon «Export» öffnet Modal (HTML/ZIP/PPTX/ODP/PDF); Download per iframe. ASSET 519. | 2026-07-27          |
| —   | Design  | Dialoge = Ribbon-Panels       | Modal/SFDialog/Share wie present-config-panel; Rule `sf-dialogs`. ASSET 518. | 2026-07-27          |
| —   | Bug     | Optionen-Panel unsichtbar     | Settings-Host z-index 0 hinter Stage; Floating-Panels jetzt über Ribbon. ASSET 517. | 2026-07-27          |
| —   | Feature | Teilen als Dialog             | Ribbon «Teilen» öffnet Modal im Editor statt presentation_share.php. ASSET 516. | 2026-07-27          |
| —   | Bug     | Teilen verschwindet           | Ribbon-API speicherte ohne isOwner und strich «Teilen»; Migration stellt es wieder her. | 2026-07-27          |
| —   | Design  | Präsentationsmodus-Icon       | Feineres Monitor+Play, Label wieder, Soft-Hyphen; 2×2 ohne Label max. 40px. ASSET 515. | 2026-07-27          |
| —   | Feature | Präsentation-Ribbon Icons     | Anzeige/Zusammenarbeit als Einzelicons; Einstellungen ohne Rahmenkästen; neue SVGs. ASSET 514. | 2026-07-27          |
| —   | Bug     | Auf-alle-Icon zu grob         | Dünnere Striche, klarere Folie→Stapel-Silhouette. ASSET 513. | 2026-07-27          |
| —   | Feature | Entwurf-Gruppe Einstellungen  | SoftMaker: «Auf alle anwenden» in eigener Gruppe «Einstellungen». | 2026-07-27          |
| —   | Bug     | Farbe/Marken vertikal fluchtend | Color-Input `appearance:none`, beide Controls exakt 28px Höhe. ASSET 512. | 2026-07-27          |
| —   | Bug     | Farbe + Markenfarben Linie    | Farbfeld und Marken-Dropdown eine Zeile, gleiche Höhe; Farbe 5 Spalten. ASSET 511. | 2026-07-27          |
| —   | Feature | Zeilentrenner Enter-Icon      | Customize-Liste/Katalog: Return/Enter-Pfeil statt Doppelpfeil. ASSET 510. | 2026-07-27          |
| —   | Bug     | Farbe nach Zeilentrenner      | Offener Zeilenstapel nimmt auch 2-zeilige Widgets (Farbe) in die neue Zeile. ASSET 509. | 2026-07-27          |
| —   | Feature | Hintergrund-Zeilenlayout      | Default: Vorschau\|Keine+Verlauf / Farbe\|Bild+Video; Zeilentrenner = `<br>`. ASSET 508. | 2026-07-27          |
| —   | Bug     | HG-Vorschau Zentrierung/Text  | Vorschau zentriert in der Zelle; bei «Keine» Text «Kein Hintergrund». ASSET 507. | 2026-07-27          |
| —   | Feature | Ribbon Zeilentrenner          | Layout-Marker: danach zeilenweises Packen bis zweizeiligem Trenner/Gruppenende. ASSET 506. | 2026-07-27          |
| —   | Feature | HG-Vorschau auch bei Farbe    | Ribbon-Vorschau zeigt Farbe/Verlauf/Keine, nicht nur Bild/Video. ASSET 505. | 2026-07-27          |
| —   | Bug     | Übergang einzeilig gequetscht | `clampSpan` kollabierte Breite auf 1 Spalte; Default 10×2, Row-Layout teilt Zellenbreite. ASSET 504. | 2026-07-27          |
| —   | Bug     | Video-HG bestehende Medien    | Dialog lädt Videos robust (kein Race); klarere Video-Thumbs. ASSET 503. | 2026-07-27          |
| —   | Feature | HG-Medien aus Präsentation    | Bild/Video-Dialog zeigt vorhandene Assets zur Auswahl. ASSET 502. | 2026-07-27          |
| —   | Feature | Entwurf einzeln layoutbar     | Hintergrund/Zeitsteuerung als Icons; Übergang 1-/2-zeilig tilbar. ASSET 501. | 2026-07-27          |
| —   | Bug     | Entwurf-Buttons gequetscht    | Grid-Zellen-Regeln nur auf direkte Button-Kinder; bg-type-btn im Ribbon fix; Höhenangleichung. ASSET 500. | 2026-07-27          |
| —   | Bug     | Entwurf-Widgets zu eng        | Design-Gruppen nach Inhalt statt Zellenraster; Spans 10/10/5. ASSET 498. | 2026-07-27          |
| —   | Bug     | Entwurf-Widgets Layout        | Inhalt statt verschachtelter ribbon-group in Zelle; Hüllen entfernt. ASSET 497. | 2026-07-27          |
| —   | Bug     | Entwurf-Ribbon leer           | Widget-Hüllen ohne Inhalt akkumulierten; takeWidget/extractWidgetBody gefixt. ASSET 496. | 2026-07-27          |
| —   | UX      | Ribbon-Anpassen Tab           | Dialog öffnet auf dem aktuell aktiven Ribbon-Tab (z. B. Einfügen). ASSET 495. | 2026-07-27          |
| —   | Bug     | Ribbon-Kachelgrösse           | Gross (2×2) bei 1-Zeilen-Gruppe: Gruppe auto auf 2 Zeilen. ASSET 494. | 2026-07-27          |
| —   | Design  | Einfügen-Ribbon Icons         | Formen/Textfeld: SVG wie Start statt Unicode-Glyphen. ASSET 493. | 2026-07-27          |
| —   | Feature | Textvorlage-Fallback sortieren | Standard-Fallback verschiebbar (nicht löschbar); Reihenfolge wird gespeichert. | 2026-07-27          |
| —   | Design  | Sidebar-Titel einheitlich     | Kanonische `#propsPanelWrap`-Titel + Cursor-Rule `props-sidebar-titles`. ASSET 492. | 2026-07-27          |
| —   | Design  | Sidebar-Abschnittstitel       | Fläche/Kontur wie Position: Satzschreibweise, 0.85rem, Textfarbe. ASSET 491. | 2026-07-27          |
| —   | Feature | Format-Subtabs                | Text / Vorlagen / Format wie Position; Tab-Leiste nur wenn nötig. ASSET 490. | 2026-07-27          |
| —   | Bug     | Format-Farbpaletten           | `[hidden]` durch Sidebar-Flex überschrieben; Marker/Textfarbe nur auf Knopf. ASSET 489. | 2026-07-27          |
| —   | Bug     | Format-Toggle-Buttons         | B/I/U… wieder in Zeile (Wrap), nicht als Spalte. ASSET 488. | 2026-07-27          |
| —   | Bug     | Format-Text/Vorlagen Layout   | `.ribbon-group-content`-Scroll/Row in Sidebar überschrieben; vertikal. ASSET 487. | 2026-07-27          |
| —   | Bug     | Format-Text horizontal        | Ribbon-Flex überschrieb Sidebar: Text/Buttons/Hilfe wieder vertikal. ASSET 486. | 2026-07-27          |
| —   | Bug     | Ribbon Schriftfarbe schmal    | 4 Spalten; Marken-Kurzlabel; Opacity-Slider mit Thumb + %-Anzeige. ASSET 485. | 2026-07-27          |
| —   | Feature | Format-Hinweise einklappbar   | Markieren-/Markdown-Hilfe als Disclosure; Zustand in localStorage. ASSET 483. | 2026-07-27          |
| —   | Bug     | Format-Label «Text»/«Vorlagen» | Abschnittstitel wie Fläche/Kontur (Uppercase); Doppel-Label entfernt. ASSET 482. | 2026-07-27          |
| —   | Bug     | Ribbon Absatz zu breit        | Widget-Span 5 statt 8; Layout ohne Leerspalte. ASSET 480. | 2026-07-27          |
| —   | Bug     | Ribbon Symbolgrösse           | Beim Anpassen Ribbon ausgeklappt; Icon-Grösse wieder sichtbar/setzbar. ASSET 479. | 2026-07-27          |
| —   | Bug     | Ribbon Tabs / Peek            | Tab-Leiste ohne Scrollbalken; Doppelklick minimiert, Klick = Overlay-Peek. ASSET 478. | 2026-07-27          |
| —   | Bug     | Ribbon Gruppe auflösen        | Label «Auflösen»; grosse Symbole mit 2-Zeilen-Clamp; 2×1-Hybrid vermieden. ASSET 477. | 2026-07-27          |
| —   | Bug     | Ribbon Schriftfarbe           | Markenfarben wieder als Dropdown (überdeckt Transparenz); Opacity-Slider volle Breite. ASSET 476. | 2026-07-27          |
| —   | Feature | Ribbon Vorlagen-Galerie       | SoftMaker-Stil: sichtbare Kacheln wachsen mit Ribbon-Breite; ▾ öffnet volle Liste. ASSET 475. | 2026-07-27          |
| —   | Bug     | Ribbon Schriftfarbe/Marken    | Markenfarben als Swatches; Farbfeld ohne Clipping. ASSET 473. | 2026-07-27          |
| —   | Bug     | Ribbon Zeichen abgeschnitten  | Ribbon-Zellen 36px; Schrift/Grösse-Felder ohne Rahmen-Clipping. ASSET 471. | 2026-07-27          |
| —   | Feature | Kontur Markenfarben           | Kontur wie Fläche: Markenfarben als Swatch-Icons statt Dropdown. ASSET 470. | 2026-07-27          |
| —   | Feature | Position wie Canva            | Canva-Layout/Icons (Anordnen, Ausrichten, Erweitert); H-Mitte zentriert. ASSET 469. | 2026-07-27          |
| —   | Feature | Fläche 3 Modi                 | Keine / Farbe / Verlauf; Markenfarben als Swatches; Verlauf ohne Festfarbe. ASSET 468. | 2026-07-27          |
| —   | Feature | Verlauf Markenfarben          | Farbe 1/2 im Verlauf-Editor mit Marken-Farben-Dropdown. ASSET 467. | 2026-07-27          |
| —   | Bug     | Effekte Kopieren/Einfügen     | Animation kopieren/einfügen unter das Effekt-Raster. ASSET 466. | 2026-07-27          |
| —   | Bug     | Formen in Format-Sidebar      | Formvarianten als 2-Spalten-Raster statt enger Einzeile mit Truncation. ASSET 465. | 2026-07-27          |
| —   | Feature | Verlauf in Format-Sidebar     | Objekt-Verlauf (Farben, Winkel, Vorschau) inline unter Fläche; Modal entfernt. Live-Anwenden. ASSET 464. | 2026-07-27          |
| —   | Bug     | Effekte-Tab horizontal        | Sidebar-Effekte vertikal: `flex-wrap:nowrap`, kein `max-height`-Spaltenwrap; Transfer-Buttons + Anim-Raster gestapelt. ASSET 463. | 2026-07-27          |
| —   | Feature | Rechte Sidebar SoftMaker-Tabs | Icon-Tabs: Vorlagen, Format, Position (Subtabs), Effekte, Textkorrekturen, Medien. ASSET 458. | 2026-07-27          |
| —   | Bug     | Ribbon Zeichen-Icons / Anpassen | Zeichen-Formatbuttons ohne Rahmen; Schrift/Grösse/Schriftfarbe ohne Überlauf hinter Icons; Anpassungsdialog resize + localStorage. ASSET 457. | 2026-07-27          |
| —   | Feature | Hell/Dunkel Darstellung      | Hub-Pref `dark\|light\|system` (`cf_theme`), herbstliches Light (Weinrot-Rahmen) in Hub/SF/CF/Stream/AF-Admin. Dark unverändert. | 2026-07-27          |
| 1   | Bug     | Vorlageelemente Zuordnungen  | Speichern schliesst Dialog (`closeModal`).                                                                                        | 2026-07-08 · v2.1.1 |
| 2   | Bug     | Editor / Ebenen              | Keine Ghost-Ebenen (`restackContentNodes`).                                                                                       | 2026-07-08 · v2.1.1 |
| 3   | Bug     | Darstellungsfehler im Editor | Leere Zustände zentriert.                                                                                                         | 2026-07-08 · v2.1.1 |
| 4   | Feature | Raster — Sortieren           | Drag & Drop (`bindGridReorder`).                                                                                                  | 2026-07-08 · v2.1.1 |
| 9   | Feature | Raster — Steuerung           | Präsentieren/Duplizieren/Löschen pro Kachel.                                                                                      | 2026-07-08 · v2.1.1 |
| 10  | Feature | Raster — Miniatur-Grösse     | Schieberegler, pro User gespeichert.                                                                                              | 2026-07-08 · v2.1.1 |
| 11  | Feature | Raster — Effekt pro Kachel   | Meta: ID, Übergangs-Icon, Zeit links; Effektname rechtsbündig.                                                                    | 2026-07-08 · v2.1.2 |
| 12  | Feature | Design — nur Dark Mode       | Helles Theme + Umschalter entfernt; `data-theme="dark"` fix.                                                                      | 2026-07-09 · v2.1.3 |
| 13  | Feature | Rasteransicht in Vorlagen    | Raster-Toggle + Grid-View im Layout-Set-Editor.                                                                                   | 2026-07-09 · v2.1.3 |
| 5   | Feature | Folie als Vorlage in Set     | `importPresentationSlide()`; Filmstreifen/Grid → Set (→); zugewiesene `setRole`-Felder bleiben erhalten, Text wird zu «Feldname». | 2026-07-09 · v2.1.3 |
| 6   | Feature | Editor / Medien — Audio-DB   | Recherche + Vorschlag — [AUDIO_DB.md](AUDIO_DB.md)                                                                                | 2026-07-09 · v2.1.3 |
| 15  | Feature | Medien — Auf Englisch suchen | `MediaTranslate`, `media_translate.php`; Button + Hinweis in Pixabay/Iconify/OpenClipart                                          | 2026-07-09 · v2.1.3 |
| 16  | Bug     | Modal — Schliessen per Klick | `SFModalBackdrop.bindDismiss`: nur bei Mousedown+Mouseup auf Backdrop (nicht bei Textmarkierung nach draussen)                     | 2026-07-09 · v2.1.3 |
| 14  | Feature | Dashboard personalisieren (Phase 1) | Eigene Bereiche, DnD, Kachel/Liste pro Bereich; `Dashboard.php`, `dashboard.php` — [DASHBOARD_PERSONALIZE.md](DASHBOARD_PERSONALIZE.md) | 2026-07-09 · v2.1.3 |


