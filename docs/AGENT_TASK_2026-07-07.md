# SlideForge – Agent Task (2026-07-07)

Stand: 2026-07-07  
Modus: Agent codet, Owner testet/freigibt

---

## Tagesziele

1. **Fertigstellung der Sets**
2. **Veroeffentlichung des Releases**
3. **Set von Entwicklerumgebung auf Prod-Server kopieren**

---

## Scope fuer heute

### 1) Fertigstellung der Sets

**Zielbild**

- Folien-Sets sind funktional komplett (inkl. Logos-Zuordnung, Import/Export, Standard-Set-Verhalten).
- "Schlicht" ist als Standard-Set sauber eingebunden.

**Checkliste**

- [x] Logos-Elementsteuerung pro Set final pruefen (aktive/inaktive Rollen)
- [x] Sichtbarkeit Logos-Symbol pro aktivem Import-Element pruefen
- [x] Set-Import/Export (`.chs`/`.zip`) Ende-zu-Ende testen
- [x] Standard-Set fuer Neuinstallationen verifizieren (`seed/layout-sets/schlicht`)
- [x] Uebersetzungen DE/EN/FR/IT/RM fuer neue Set-Funktionen pruefen

**Abnahme**

- [x] Testfall "nur H1 + Bibelstellen + Blockzitate" funktioniert
- [x] Testfall "H2-H5 deaktiviert" importiert diese nicht
- [x] Exportiertes Set laesst sich in neuer Umgebung importieren

---



### 2) Veroeffentlichung des Releases

**Zielbild**

- Release ist sauber vorbereitet, versioniert und dokumentiert.

**Checkliste**

- [x] Release-Notizen aktualisieren (`docs/RELEASE_*.md` oder `.github/RELEASE_*.md`)
- [x] Version/Changelog/README konsistent
- [x] Arbeitsbaum vor Release pruefen (`git status`, relevante Diffs) — siehe `docs/RELEASE_PUBLISH_v2.1.0.md`
- [x] Release-Tag-Strategie bestaetigen — **v2.1.0** (Minor), Tag `v2.1.0`, Hook: manueller Release-Commit noetig

**Abnahme**

- [x] Release-Dokumentation ist publizierbar
- [x] Keine offenen Blocker fuer Deploy (Commit/Merge durch Owner)

---



### 3) Set auf Prod kopieren

**Zielbild**

- Das gewuenschte Set ist auf Prod vorhanden, freigegeben und nutzbar.

**Checkliste**

- [x] Ziel-Set eindeutig identifizieren (Titel/ID) — **Schlicht**, Prod-ID `1cb754c60ed55f3b`
- [x] Archiv- oder Seed-Weg waehlen (`.chs` oder `seed/layout-sets/...`) — Seed `schlicht`
- [x] Upload/Import auf Prod durchfuehren — `./.deploy/push-layout-set-seed.sh schlicht`
- [x] `template_shared` und ggf. `default_layout_set` verifizieren — vom Skript gesetzt (siehe `docs/PROD_SET_SCHLICHT_2026-07-07.md`)
- [x] Sichttest in Templates/Import-Maske (Owner)

**Abnahme**

- [x] Set auf Prod vorhanden (aktualisiert)
- [x] Set fuer alle zugaenglich (`template_shared: true`)
- [x] Import mit diesem Set funktioniert (Owner-Test)

---



### 4) Finalisieren

- [x] Aktuelle Version auf Prod und Demo veröffentlichen.
- [x] Git aktualisieren und auf neue Version setzen.

---

## Empfohlene Reihenfolge heute

1. Sets finalisieren (inkl. Test)
2. Release-Doku finalisieren
3. Prod-Uebernahme des Sets
4. Kurzer Abschlussbericht (Was erledigt, was offen, Risiken)

---



## Risiken / offene Punkte

- Unterschied zwischen lokalem "Schlicht" und Seed-Stand
- Prod-Rechte / Deploy-Zugang / ZIP-Unterstuetzung
- Zeit fuer vollständigen End-to-End-Test

---



## Sprach-Check (Pflicht)

- [x] `lang/de.php` aktualisiert
- [x] `lang/en.php` aktualisiert
- [x] `lang/fr.php` aktualisiert
- [x] `lang/it.php` aktualisiert
- [x] `lang/rm.php` aktualisiert
- [x] Keine fehlenden i18n-Keys in neuen UI-Texten

---



## Ergebnisprotokoll (am Ende ausfuellen)



### Erledigt

- [x] Standard-Set `seed/layout-sets/schlicht` neu gebaut und mit `scripts/verify_schlicht_seed.py` verifiziert
- [x] i18n-Keys fuer Set/Logos in EN/FR/IT/RM ergänzt (`scripts/sync_set_i18n.py`)
- [x] Layout-Folien ohne `layoutKey` in Vorlagen-Auswahl sichtbar (Folie-14-Fix)
- [x] Release **v2.1.0** dokumentiert; Roadmap v2.2–v2.4 und v3.1 angepasst
- [x] Release-Veröffentlichung vorbereitet (`docs/RELEASE_PUBLISH_v2.1.0.md`, README, VERSION, CHANGELOG)
- [x] Prod: Folien-Set **Schlicht** via Seed auf `ftp.bkbiel.ch` (`1cb754c60ed55f3b`) — `docs/PROD_SET_SCHLICHT_2026-07-07.md`
- [x] Editor-UI: Vorlagen-Accordion, Raster im Hauptfenster, Sidebar-Scroll-Fixes (`ASSET_VERSION` 356)
- [x] Commit `f54a83b`, Release-Commit `c582670`, Merge auf `main`
- [x] Prod-Deploy (584 Dateien) + Demo-Deploy + Demo-Reset via pre-push-Hook
- [x] Git-Tag `v2.1.0` gepusht, GitHub Release erstellt



### Offen

- [ ] Owner: Sichttest Prod nach Re-Login (Einstellungen/Admin, Vorlagen-Accordion, Raster-Toggle)
- [ ] Optional: Schlicht-Seed erneut auf Prod (`./.deploy/push-layout-set-seed.sh schlicht`) — zuletzt 2026-07-07



### Entscheidungen

- [x] Release-Version **v2.1.0** (Minor) statt v2.0.5/v2.0.4 als finales Label



### Naechster Schritt

- [ ] Owner: Hard-Reload (Ctrl+Shift+R) auf Prod/Demo, Sichttest laut `.github/RELEASE_v2.1.0.md`