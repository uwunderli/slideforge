# SlideForge – Release v2.0.0 (Briefing)

> **Handoff:** *„Lies `docs/RELEASE_v2.0.0.md` und setze die Release um — ausser „Nicht in dieser Release“.“*

Stand: Juli 2026 · Basis: **v1.0.4** (main)

---

## Ziel

SlideForge wird **vom Handy steuerbar**: Nach Login auf dem Smartphone sieht der Nutzer eine **eigene, stark reduzierte Oberfläche** — kein Editor, sondern Dashboard zur Präsentationsauswahl und ein **mobiler Präsentations-/Fernsteuerungsmodus** für Folienwechsel. Das Desktop-Präsentationsfenster (Beamer/ Laptop) bleibt die Hauptanzeige; das Handy ist die **Fernbedienung**.

---

## Features (priorisiert)

1. **[MUSS] Mobile Erkennung & reduzierte UI** — nur auf **Smartphones** (nicht Tablet/iPad); eigenes Layout, kein Desktop-Editor
2. **[MUSS] Tablet = Desktop-UI** — iPad & grössere Tablets erhalten normale Oberfläche (inkl. Editor)
3. **[MUSS] Mobile Dashboard** — Listenansicht der Präsentationen; Auswahl → **Präsentieren** / Remote
4. **[MUSS] Editor auf Smartphones gesperrt** — kein Canvas auf Handys; klare Meldung
5. **[MUSS] Mobile Present-Remote** — Folien vor/zurück; Steuerung über **Internet** (HTTPS + Live-Sync)
6. **[MUSS] Desktop Present + Mobile Remote** — Beamer/Laptop = Hauptfenster; Handy = Fernbedienung
7. **[MUSS] Laserpointer vom Handy** — Touch auf Remote-Oberfläche steuert Laser auf Desktop-Present (wie bestehender Laser, über Live-Sync)
8. **[SOLL] Timer-Zeitleiste ein-/ausblendbar** — Toggle auf Mobile-Remote → sichtbar auf Desktop-Present
9. **[SOLL] Weitere Present-Elemente optional** — Notizen, Uhr, Fortschrittsbalken toggelbar (Architektur vorbereiten)
10. **[NICE] QR-Code** auf Desktop-Present zur Remote-URL
11. **[NICE] Haptic / Vibration** bei Folienwechsel

---

## Feature-Details

### Feature: Mobile UI (eigene Oberfläche)

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | Global nach Login — Erkennung per CSS/JS (`matchMedia`, Touch, Viewport) und/oder `User-Agent` + User-Override |
| **Verhalten** | Auf **Smartphone** (schmaler Viewport, typ. max-width 767px + Touch, kein Tablet-UA): CSS-Klasse `sf-mobile`, reduzierte Navigation (**Dashboard**, **Present/Remote**, **Profil/Logout**). **Tablet/iPad (≥768px oder explizit Tablet): normale Desktop-UI** inkl. Editor. |
| **Grenzen** | Mobile-UI nur Phone; iPad Pro etc. = Desktop |
| **Akzeptanz** | iPhone → reduziertes UI; iPad → Desktop wie am Laptop |
| **Technik** | `matchMedia('(max-width: 767px)')` + optional UA-Heuristik; `?mobile=1` / `?desktop=1` zum Testen; Cookie/User-Setting optional |

---

### Feature: Mobile Dashboard

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | `index.php` (Dashboard) — Mobile-Variante |
| **Verhalten** | **Liste** der Präsentationen (Karten vereinfacht: Titel, ggf. Thumbnail). Tap → Aktionen: **Präsentieren** (Primary), **Ansehen** optional. Keine Import/Export/Archiv-Komplexität in v2.0.0 MUSS — oder eingeklappt unter „Mehr“. Sortierung: zuletzt bearbeitet. |
| **Grenzen** | Nur Präsentationen mit Recht **Present** oder **Edit**; archivierte optional ausblenden |
| **Akzeptanz** | Präsentation antippen → Mobile-Present-Steuerung öffnet sich |
| **Technik** | Bestehende Dashboard-API/Daten (`Auth`, Präsentationsliste); Mobile-spezifisches Markup |

---

### Feature: Editor auf Handy nicht verfügbar

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | `editor.php`, Links vom Dashboard |
| **Verhalten** | Aufruf `editor.php` auf **Smartphone** (Mobile-UI): Redirect mit Hinweis *„Editor nur am Desktop“*. Auf **Tablet/iPad**: Editor normal nutzbar. |
| **Akzeptanz** | Direkt-URL `editor.php` auf Handy → kein Editor, klarer Hinweis |
| **Technik** | Serverseitiger Check in `editor.php` + Client-Redirect als Fallback |

---

### Feature: Mobile Präsentationssteuerung (Fernbedienung)

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | Neues `present_remote.php` oder Mobile-Ansicht von `present.php` |
| **Verhalten** | Grosse Buttons **Zurück** / **Weiter**, Foliennummer, Verbindungsstatus. Steuert Desktop-Present über **Live-Sync via Internet** (HTTPS zum selben SlideForge-Server — **kein lokales WLAN nötig**). Desktop muss Present offen haben; Handy kann von unterwegs (LTE/5G) verbinden. |
| **Akzeptanz** | Handy unterwegs + Laptop im Büro (beide online) → Folienwechsel funktioniert |
| **Technik** | `live.php` polling über HTTPS; Session-Token pro Präsentation; CSRF/Auth; Latenz ~Polling-Intervall (z. B. 500ms–1s) |

---

### Feature: Desktop Present (Hauptfenster)

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | `present.php` (unverändert Hauptanzeige) |
| **Verhalten** | Desktop/Beamer bleibt **Hauptfenster** mit grosser Folie, Filmstreifen, Notizen (am Desktop). Lauscht auf Remote-Navigation von Mobile. Optional: Anzeige „Mobile verbunden“ (SOLL). |
| **Akzeptanz** | Present auf Laptop; Handy steuert zuverlässig |

---

### Feature: Timer-Zeitleiste ein-/ausblenden

| Feld | Inhalt |
|------|--------|
| **Priorität** | SOLL |
| **Wo** | Mobile Present-Remote **und/oder** Desktop `present.php` |
| **Verhalten** | Toggle **Timerbalken / Zeitleiste** sichtbar oder ausgeblendet. Einstellung pro Session oder aus User-/Present-Config übernehmen. Auf Mobile: klarer Schalter (Icon). Auf Desktop: bestehende Timer-UI respektiert Toggle. |
| **Akzeptanz** | Toggle auf Handy → Zeitleiste auf Beamer erscheint/verschwindet (wenn an Desktop angebunden) |
| **Technik** | Present-Config JSON; Sync über `live.php` wie andere Present-Einstellungen |

---

### Feature: Laserpointer vom Handy

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | `present_remote.php` — Touch-Fläche „Laser-Modus“ |
| **Verhalten** | Nutzer aktiviert Laser auf dem Handy; **Wischen/Tippen** auf Touch-Fläche sendet Position (normalisiert 0–1) über `live.php` an Desktop-Present. Desktop zeigt Laser wie bei Maus-Laser (`present-laser.js`). Farbe/Grösse optional von Desktop-Config übernehmen oder auf Handy wählbar (SOLL). |
| **Grenzen** | Nur wenn Present-Fenster verbunden; gleiche Berechtigung wie Navigation |
| **Akzeptanz** | Finger auf Handy bewegen → Laser auf Beamer folgt |
| **Technik** | Erweiterung bestehender Laser-Pipeline (`present-laser.js`, `present-laser-audience.js`); Remote sendet `{x, y, active}`; Throttle ~30fps |

---

### Feature: Weitere Present-Elemente (erweiterbar)

| Feld | Inhalt |
|------|--------|
| **Priorität** | SOLL / NICE |
| **Wo** | Mobile Remote — Einstellungen-Drawer |
| **Verhalten** | Architektur für Toggles: **Notizen** (Mobile), **Uhr**, **Fortschrittsbalken** — neben Laser und Timer. v2.0.0 MUSS: Navigation + Laser; SOLL: Timer-Toggle. |
| **Akzeptanz** | Code-Struktur für weitere Toggles vorhanden |

---

## Nicht in dieser Release

- Marketing / Bekanntmachen
- Mobile **Editor** (bewusst ausgeschlossen)
- Native iOS/Android-App
- Present ohne Desktop (Handy als einziger Bildschirm)
- Öffentliche Remote-Links ohne Login
- Vollständiges Admin-Panel auf Mobile

---

## Technische Rahmenbedingungen

- Bestehendes **Live-Sync** (`live.php`) — **Internet-tauglich** (HTTPS, kein LAN-Zwang)
- Remote + Present kommunizieren über denselben öffentlichen SlideForge-Host
- Touch-optimierte Controls (min. 44×44px Tap-Targets)
- Mehrsprachigkeit: DE / EN / FR / IT / RM
- Kein Composer; PHP 8.2+
- Deploy Prod / Demo wie üblich
- **HTTPS** vorausgesetzt (Login auf Mobile)

---

## Dokumentation & Demo

- [ ] README — Abschnitt „Mobile Fernsteuerung“
- [ ] CHANGELOG.md → `[2.0.0]`
- [ ] `.github/RELEASE_v2.0.0.md`
- [ ] Demo: kurzer Hinweis auf Login-Seite / Help — „Present vom Handy steuern“
- [ ] Feature-Tour: optional eine Folie

---

## Release-Checkliste (am Ende)

- [ ] Code + i18n
- [ ] Manuell: Smartphone + Desktop — Present, Remote, **Laser**, Timer-Toggle; **Remote übers Internet** (nicht nur gleiches WLAN)
- [ ] PR → main · Tag `v2.0.0` · Deploy

---

## Getroffene Entscheidungen

| # | Frage | Entscheidung | Begründung |
|---|--------|--------------|------------|
| 1 | Handy-Rolle | **Fernbedienung**, nicht Hauptdisplay | User: Present-Hauptfenster am Desktop; Handy für Steuerung. |
| 2 | Editor auf Mobile | **Nicht verfügbar** | Explizit gewünscht; Konva ungeeignet für Phone. |
| 3 | Dashboard Mobile | **Listen-Auswahl** zum Präsentieren | User-Vorgabe. |
| 4 | Timer-Zeitleiste | **Ein-/ausblendbar** (SOLL) | User-Wunsch. |
| 5 | Tablet (iPad) | **Desktop-UI** | User: eher Desktop; Editor auf Tablet erlaubt. |
| 6 | Remote-Netzwerk | **Über Internet** (HTTPS) | Kein WLAN-Zwang; Polling zum Server. |
| 7 | Laser vom Handy | **Ja, in v2.0.0 (MUSS)** | Touch → Laser auf Desktop-Present. |

---

## Ursprüngliche Idee (Rohtext)

> Slides sollten mit dem Handy gesteuert werden können. Nach Login am Handy: eigene stark reduzierte Oberfläche.
>
> **Dashboard:** Listenauswahl mit Folien zum Präsentieren  
> **Editor:** für Handys nicht verwendbar  
> **Präsentationsmodus:** Hauptfenster + Foliensteuerung; weitere Elemente evtl. später; vor allem **Timerbalken ein-/ausblendbar**
