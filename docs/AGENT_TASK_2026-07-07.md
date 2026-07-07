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
- [ ] Logos-Elementsteuerung pro Set final pruefen (aktive/inaktive Rollen)
- [ ] Sichtbarkeit Logos-Symbol pro aktivem Import-Element pruefen
- [ ] Set-Import/Export (`.chs`/`.zip`) Ende-zu-Ende testen
- [ ] Standard-Set fuer Neuinstallationen verifizieren (`seed/layout-sets/schlicht`)
- [ ] Uebersetzungen DE/EN/FR/IT/RM fuer neue Set-Funktionen pruefen

**Abnahme**
- [ ] Testfall "nur H1 + Bibelstellen + Blockzitate" funktioniert
- [ ] Testfall "H2-H5 deaktiviert" importiert diese nicht
- [ ] Exportiertes Set laesst sich in neuer Umgebung importieren

---

### 2) Veroeffentlichung des Releases

**Zielbild**
- Release ist sauber vorbereitet, versioniert und dokumentiert.

**Checkliste**
- [ ] Release-Notizen aktualisieren (`docs/RELEASE_*.md` oder `.github/RELEASE_*.md`)
- [ ] Version/Changelog/README konsistent
- [ ] Arbeitsbaum vor Release pruefen (`git status`, relevante Diffs)
- [ ] Release-Tag-Strategie bestaetigen (z. B. `vX.Y.Z`)

**Abnahme**
- [ ] Release-Dokumentation ist publizierbar
- [ ] Keine offenen Blocker fuer Deploy

---

### 3) Set auf Prod kopieren

**Zielbild**
- Das gewuenschte Set ist auf Prod vorhanden, freigegeben und nutzbar.

**Checkliste**
- [ ] Ziel-Set eindeutig identifizieren (Titel/ID)
- [ ] Archiv- oder Seed-Weg waehlen (`.chs` oder `seed/layout-sets/...`)
- [ ] Upload/Import auf Prod durchfuehren
- [ ] `template_shared` und ggf. `default_layout_set` verifizieren
- [ ] Sichttest in Templates/Import-Maske

**Abnahme**
- [ ] Set auf Prod sichtbar
- [ ] Set fuer alle zugaenglich
- [ ] Import mit diesem Set funktioniert

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

- [ ] `lang/de.php` aktualisiert
- [ ] `lang/en.php` aktualisiert
- [ ] `lang/fr.php` aktualisiert
- [ ] `lang/it.php` aktualisiert
- [ ] `lang/rm.php` aktualisiert
- [ ] Keine fehlenden i18n-Keys in neuen UI-Texten

---

## Ergebnisprotokoll (am Ende ausfuellen)

### Erledigt
- [ ]

### Offen
- [ ]

### Entscheidungen
- [ ]

### Naechster Schritt
- [ ]

