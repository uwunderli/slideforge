# Vorbereitet, nicht gebaut: «Welche Hub-Gruppe gilt als Administrator?» (Fund 2)

**Datum:** 19.10.2026 · **Stand: nur beschrieben.** Im Quelltext ist **nichts** geändert.
**sicherheitsrelevant** · **Braucht Urs' Wort direkt in meinem Fenster**, nicht weitergegeben:
der Eingriff kann aussperren.

## Was heute gilt

Zwei Stellen entscheiden anhand eines **im Code festgeschriebenen Gruppennamens**:

| Stelle | Wirkung heute |
|---|---|
| `src/Auth.php:316-325` | Trägt eine Person im Hub ein Tag oder eine Gruppe mit dem Namen `Admin` (gross/klein egal), wird ihr lokales Konto beim ersten Hub-Login als **`admin`** angelegt — vollwertiger Administrator der FolienSchmiede. |
| `src/Launcher.php:143` | Dieselbe Gruppe sieht **alle** Module im Dock, unabhängig von `requireAnyGroup`. |

**Wer** die Gruppe trägt, sagt der Hub — das ist richtig. **Dass genau dieser Name alles
aufschliesst**, sagt die Maschine. Legt im Hub jemand eine Gruppe «Admin» für einen anderen
Zweck an, bekommen deren Mitglieder hier unbemerkt Administratorrechte.

## Was das Paket ändern würde

1. **Neues Feld in der Verwaltung** (`public_html/admin_settings.php`, bei den übrigen
   site-weiten Einstellungen): «Welche Hub-Gruppe gilt als Administrator?» — ein Textfeld,
   mehrere Namen mit Komma erlaubt.
2. **Ablage** in `data/config.json` unter `hub_admin_groups`, über die bestehende Klasse
   `Config` (`Config::get()` / `Config::update()`, wie `registration_enabled`). Gepflegt von
   einem Menschen in der Oberfläche; der Code liest nur.
3. **Vorbelegung `['Admin']`** — damit sich im ersten Moment **nichts** ändert. Genau das ist
   der Unterschied zwischen «Regel erfüllt» und «Urs ist draussen».
4. **Zwei Leser statt zwei festen Namen:** `Auth.php` und `Launcher.php` fragen künftig
   `Config::hubAdminGroups()`. Eine neue Methode in `src/Config.php`, die leere und doppelte
   Einträge wegwirft.
5. **Leeres Feld = niemand wird automatisch Administrator.** Bestehende lokale
   Administratorkonten bleiben davon unberührt — sie stehen in `data/users.json` und werden
   nicht neu bewertet.

## Das Risiko, ungeschminkt

Diese Stelle entscheidet über **Urs' eigenen Zugang**. Drei Dinge können schiefgehen:

1. **Feld leer ausgeliefert** (strikt fail-closed): Wer sein lokales Konto noch nicht hat,
   wird beim nächsten Hub-Login als `editor` angelegt und kommt nicht in die Verwaltung.
   Darum die Vorbelegung `Admin`.
2. **Tippfehler im Feld**: steht dort «Admins» statt «Admin», bekommt niemand Neues mehr
   Administratorrechte. Bestehende Konten behalten ihre Rolle, es wäre also kein Aussperren,
   aber ein stiller Rückschritt. Gegenmittel: nach dem Speichern zeigt die Seite die aktuell
   erkannten Gruppen an.
3. **`data/config.json` nicht schreibbar**: dann bleibt die Vorbelegung — also der heutige
   Zustand. Unkritisch (die Rechte sind seit dem 18.10. korrigiert).

Rückbau ist in beide Richtungen einfach: entweder den Wert in der Oberfläche zurückstellen
oder die drei Dateien aus der Sicherung zurücklegen.

## Umfang und Ablauf, wenn das Wort kommt

- Geändert würden: `src/Config.php` (neue Methode), `src/Auth.php` (eine Stelle),
  `src/Launcher.php` (eine Stelle), `public_html/admin_settings.php` (Feld + Speichern),
  `lang/*.php` (fünf Sprachen, Beschriftung).
- Prüfstand: Hub-Login mit Gruppe «Admin» → Rolle `admin`; mit umbenannter Gruppe → `editor`;
  leeres Feld → niemand neu `admin`, bestehende Konten unverändert; Dock-Sichtbarkeit
  entsprechend. Dazu die 28 bestehenden Prüffälle.
- **Unabhängiger Review**, weil sicherheitsrelevant (Regel Urs 18.10.).
- Vorher-Digest = erwartet, Sicherung, Nachher-Manifest, Takt-Probe mit Cache-Buster,
  Rückbau-Satz. Nur `slides.bkbiel.ch`; die Demo bleibt eingefroren.
- Zu beachten: `src/Launcher.php` hängt derzeit an der U50-Lieferung (wartet auf das lesende
  Einhängen des Hub-Datenordners). Entweder zusammen ausliefern oder diese eine Stelle
  getrennt — sage ich beim Vorlegen dazu.

## Was Urs sagen muss

Ein Satz genügt, direkt in meinem Fenster — zum Beispiel: «Mach das Feld für die Admin-Gruppe,
vorbelegt mit Admin.» Ohne sein Wort bleibt alles, wie es ist; ich habe nichts angefasst.
