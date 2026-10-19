# Vorbereitet, nicht gebaut: wer ist Administrator? (Fund 2)

> **Nachtrag 19.10., und er ändert die Empfehlung.** Nachdem der Hub mitgeteilt hat, dass
> `acl.php` je Person ein Feld **`isAdmin`** liefert, gibt es einen besseren Weg als ein
> eigenes Feld in unserer Verwaltung: **die Hub-Antwort fragen.** Dann steht die Entscheidung
> dort, wo Menschen sie ohnehin pflegen, und es gibt keinen zweiten Ort, der auseinanderlaufen
> kann. Der Koordinator hat das am 19.10. im Grundsatz gutgeheissen — ausliefern **nur mit
> Urs' direktem Wort**, weil es aussperren kann.
>
> Unten stehen darum **zwei Wege**. **Weg B ist mein Vorschlag.** Weg A bleibt beschrieben,
> weil er ohne Hub funktioniert und als Rückfall taugt.

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

## Weg B (Vorschlag): der Hub sagt, wer Administrator ist

Die Antwort von `hub/public/api/acl.php` enthält je Person `isAdmin` — mit einem ausdrücklichen
Hinweis im Quelltext: «Urs, 15.10.: Module sollen nicht jede angemeldete Person für Admin
halten. Fehlt das Feld (älterer Hub), heisst das für ein Modul **kein Admin**.»

1. Die FolienSchmiede fragt die Hub-Antwort ohnehin ab, sobald der Dock-Endpunkt steht (siehe
   `plan-dock-vom-hub-2026-10-19.md`). `isAdmin` reist in derselben Antwort mit — **kein
   zusätzlicher Aufruf, kein zusätzliches Geheimnis.**
2. `src/Auth.php` setzt die Rolle beim Hub-Login aus diesem Feld statt aus dem Gruppennamen.
3. `src/Launcher.php` braucht die Admin-Abfrage gar nicht mehr: die Liste kommt gefiltert vom
   Hub, der Admin-Fall ist dort schon berücksichtigt.
4. Im Code bleibt **kein** Gruppenname mehr stehen. Die Regel ist damit nicht nur erfüllt,
   sondern es gibt auch keine zweite Liste, die jemand pflegen müsste.

**Die drei Regeln, die dabei zählen — sonst wird genau das daraus, was Urs nicht will:**

- **Nie aus einem Fehler heraus herabsetzen.** Antwort fehlt, Zeitlimit, `404`, Hub nicht
  erreichbar → die gespeicherte Rolle in `data/users.json` bleibt, wie sie ist. Nur eine
  **gültige** Antwort darf die Rolle ändern.
- **Herabsetzen ja, aber nur auf eine gültige Antwort.** Verliert jemand im Hub die
  Administratorrechte, soll die FolienSchmiede folgen — sonst wäre die Hub-Oberfläche nur noch
  die halbe Wahrheit.
- **Mindestens ein Administrator bleibt.** Dieselbe Sperre, die `admin_settings.php` schon
  kennt (`Auth::countAdmins() <= 1`), gilt auch hier: der letzte Administrator wird nicht
  automatisch herabgesetzt, sondern es gibt einen Hinweis in der Verwaltung.

**Rückbau:** die drei Dateien aus der Sicherung zurücklegen. Eine lokale Rolle lässt sich
unabhängig davon jederzeit in `admin_settings.php` von Hand setzen — das bleibt der Notausgang.

## Weg A (Rückfall): ein eigenes Feld in der Verwaltung

Sinnvoll, wenn die FolienSchmiede ohne Hub betrieben wird oder der Dock-Endpunkt nicht kommt.

## Was Weg A ändern würde

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

Ein Satz genügt, direkt in meinem Fenster. Zur Auswahl:

- **Weg B (Vorschlag):** «Der Hub soll sagen, wer bei den Folien Administrator ist.» — dann
  baue ich es zusammen mit dem Dock-Endpunkt, mit den drei Regeln oben.
- **Weg A:** «Mach das Feld für die Admin-Gruppe, vorbelegt mit Admin.» — dann gibt es die
  Einstellung bei uns, unabhängig vom Hub.
- **Oder gar nichts:** dann bleibt es, wie es ist — die Gruppe mit dem Namen «Admin» schliesst
  weiterhin alles auf.

Ohne sein Wort ist nichts angefasst. Beide Wege sind sicherheitsrelevant und bekommen vor der
Lieferung einen unabhängigen Review.
