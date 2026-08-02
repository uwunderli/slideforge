# SlideForge – Release-Planung

Übersicht aller geplanten Releases. Nach **v1.0.4** folgt die **v2.x**-Linie; der slides.com-Editor wird **v3.1.0**.

| Prio | Release | Fokus | Status | Briefing |
|------|---------|--------|--------|----------|
| **1** | **v2.0.0** | **Mobile Fernsteuerung** — reduzierte UI, Present-Remote | ✔️ shipped | [RELEASE_v2.0.0.md](RELEASE_v2.0.0.md) |
| — | **v2.0.1** | QR-Fix, Demo Feature-Tour Fernsteuerung | ✔️ shipped | [GitHub Release](https://github.com/uwunderli/slideforge/releases/tag/v2.0.1) |
| — | **v2.0.2** | **PWA** — Home-Bildschirm, Standalone | ✔️ shipped | [RELEASE_v2.0.2.md](../.github/RELEASE_v2.0.2.md) |
| — | **v2.0.3** | Folien-Raster, Live-Sync, Release-Deploy-Hooks | ✔️ shipped | CHANGELOG |
| **2** | **v2.1.0** | **Folien-Sets & Logos-Import** — Layout-Sets, `.chs`, Schlicht-Seed | ✔️ shipped | [RELEASE_v2.1.0.md](RELEASE_v2.1.0.md) · [GitHub](../.github/RELEASE_v2.1.0.md) |
| — | **v2.1.1** | Editor-Fixes & Raster-Ansicht (Sortieren, Steuerung, Miniatur-Grösse) | ✔️ shipped | CHANGELOG |
| — | **v2.1.2** | Raster-Kachel: Übergang/Zeit/Effektname Layout | ✔️ shipped | CHANGELOG |
| — | **v2.1.3** | **Editor-/Ribbon-Feinschliff** (Dialoge, Widgets, Raster→Entwurf, …) + SharedAuth-Docker-Fix | ✔️ shipped | [RELEASE_v2.1.3.md](../.github/RELEASE_v2.1.3.md) · [RIBBON_MENU.md](RIBBON_MENU.md) |
| — | **v2.1.4** | **Present-Ribbon** — Anpassen, Ansicht/Steuerung, Fortschritt/Navigation-Befehle | ✔️ shipped | [RELEASE_v2.1.4.md](../.github/RELEASE_v2.1.4.md) · [RIBBON_MENU.md](RIBBON_MENU.md) |
| — | **v2.1.5** | Snapshot Modulstand | ✔️ shipped | CHANGELOG |
| — | **v2.1.6** | **Notizen-Overlay einklappbar** (Register rechts, touch-tauglich) | ✔️ shipped | [RELEASE_v2.1.6.md](../.github/RELEASE_v2.1.6.md) |
| — | **v2.1.7** | Notizen nach unten einklappen; Register unten | ✔️ shipped | [RELEASE_v2.1.7.md](../.github/RELEASE_v2.1.7.md) |
| — | **v2.1.8** | Notizen-unten + Deploy-Regel im Repo | ✔️ shipped | [RELEASE_v2.1.8.md](../.github/RELEASE_v2.1.8.md) |
| — | **v2.1.9** | Notizen nach unten (stabilisiert) | ✔️ shipped | [RELEASE_v2.1.9.md](../.github/RELEASE_v2.1.9.md) |
| — | **v2.1.10** | HubUserMenu-Standard in der Topbar | ✔️ shipped | [RELEASE_v2.1.10.md](../.github/RELEASE_v2.1.10.md) |
| — | **v2.1.11** | Present Notizen-Einstellungen | ✔️ shipped | [RELEASE_v2.1.11.md](../.github/RELEASE_v2.1.11.md) |
| — | **v2.1.16** | Hub-Launcher: sticky `auth_via=local` Fix | ✔️ shipped | [RELEASE_v2.1.16.md](../.github/RELEASE_v2.1.16.md) |
| — | **v2.1.15** | Hub-Launcher Dock Fix | ✔️ shipped | [RELEASE_v2.1.15.md](../.github/RELEASE_v2.1.15.md) |
| — | **v2.1.14** | Launcher/Dialog-Nachzug | ✔️ shipped | [RELEASE_v2.1.14.md](../.github/RELEASE_v2.1.14.md) |
| — | **v2.1.13** | Hub-Launcher + globale Dialog-Klassen | ✔️ shipped | [RELEASE_v2.1.13.md](../.github/RELEASE_v2.1.13.md) |
| — | **v2.1.12** | Notizen-Kontrast zur Folie | ✔️ shipped | [RELEASE_v2.1.12.md](../.github/RELEASE_v2.1.12.md) |
| **3** | **v2.2.0** | **church.tools** — Live-Kalender, Gruppen, Platzhalter-Templates | ✅ Briefing fertig · **nächster Major nach 2.1.13** | [RELEASE_v2.2.0.md](RELEASE_v2.2.0.md) |
| **4** | **v2.3.0** | Integrierter **Medien-Editor** (Trim, Bild, SVG) | ✅ Briefing fertig | [RELEASE_v2.3.0.md](RELEASE_v2.3.0.md) |
| **5** | **v2.4.0** | reveal.js **Auto-Animate** — Objekte zwischen Folien morphen | ✅ Briefing fertig | [RELEASE_v2.4.0.md](RELEASE_v2.4.0.md) |
| **6** | **v3.1.0** | **Editor v2** — slides.com-artiger visueller Editor (Major) | ✅ Briefing fertig | [RELEASE_v3.1.0.md](RELEASE_v3.1.0.md) |

**Legende:** ✅ Briefing fertig · 📝 Entwurf · 🔧 in Arbeit / unreleased · 🚀 Release-Umsetzung · ✔️ shipped · ⏸ pausiert

---

## Aktuelle Situation (2026-08-02)

| Lage | Stand |
|------|--------|
| **Letzter Tag** | **v2.1.16** auf GitHub / `main` |
| **Arbeitsstand** | Hub-Launcher + hub-float-dialog **shipped**; nächster Major **v2.2.0** church.tools |
| **Ribbon** | Editor + Present konfigurierbar ([RIBBON_MENU.md](RIBBON_MENU.md)) |
| **Logos-Importer neu** | Briefing fertig, **pausiert** bis Vorlage «Schlicht II» geprüft ([LOGOS_IMPORTER.md](LOGOS_IMPORTER.md), AENDERUNGEN B #7) |
| **Dashboard** | Phase 1 ✔️; Phase 2 (geteilte Folien / Bereich teilen) noch offen (B #14b) |
| **Animate.css Texteffekte** | Idee **pendent** (AENDERUNGEN A) |

**Nächster Schritt:** **v2.2.0** church.tools.

---

## Versionslinien

| Linie | Bedeutung |
|-------|-----------|
| **v1.x** | Konva-Editor, WebDAV, Medien-Quellen — **endet mit v1.0.4** |
| **v2.x** | Mobile, Folien-Sets/Logos, Ribbon-Feinschliff, church.tools, Medien-Editor, Auto-Animate |
| **v3.1** | Neuer visueller Editor (slides.com), Konva bleibt als „Klassischer Editor“ |

---

## Roadmap (Reihenfolge = Priorität)

```
v1.0.4   ✔️ shipped
v2.0.0   ✔️ shipped — Mobile Fernsteuerung
v2.0.1   ✔️ shipped — QR-Fix, Demo Feature-Tour
v2.0.2   ✔️ shipped — PWA
v2.0.3   ✔️ shipped — Folien-Raster, Live-Sync
v2.1.0   ✔️ shipped — Folien-Sets & Logos-Import
v2.1.1   ✔️ shipped — Editor-Fixes, Raster-Sort/Steuerung/Miniatur-Grösse
v2.1.2   ✔️ shipped — Raster-Kachel Meta-Layout
v2.1.3   ✔️ shipped — Ribbon/Editor-Feinschliff
v2.1.4   ✔️ shipped — Present-Ribbon (Anpassen, Ansicht)
v2.2.0   → church.tools          (nächster Major)
v2.3.0   → Medien-Editor
v2.4.0   → Auto-Animate
v3.1.0   → Visueller Editor (slides.com)
```

**Nebenstränge (kein eigener Release-Slot):**

| Thema | Ort | Status |
|-------|-----|--------|
| Dashboard Phase 2 | [AENDERUNGEN.md](AENDERUNGEN.md) B #14b · [DASHBOARD_PERSONALIZE.md](DASHBOARD_PERSONALIZE.md) | geplant |
| Text-Animationen Animate.css | [AENDERUNGEN.md](AENDERUNGEN.md) A | pendent |
| Audio-DB | [AUDIO_DB.md](AUDIO_DB.md) | Recherche; nicht 2.1.3 |

**Backlog (ohne Release):** [BACKLOG.md](BACKLOG.md)  
**Änderungen Bugs & Features (A → B → C):** [AENDERUNGEN.md](AENDERUNGEN.md)  
**Doku-Übersicht:** [README.md](README.md)

---

## Handoff an Cursor

```
Lies docs/RELEASES.md und setze v2.1.3 um (Working Tree → Release).
```

Nach Publish von 2.1.3:

```
Lies docs/RELEASES.md und setze v2.2.0 um.
```
