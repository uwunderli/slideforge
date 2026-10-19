# Prüfung: feste Zugriffslisten im Code? (Regel «ACLs definieren Menschen»)

**Datum:** 19.10.2026 · **Art:** nur gelesen, **nichts geändert** · **sicherheitsrelevant**
**Anlass:** Urs, 19.10., Wortlaut: «ACL's werden über die User definiert und stehen nicht fest
im Code. Wer was sehen darf, wird von Menschen! gesagt und nicht von der Maschine.»
Regel im Briefing des Koordinators, Abschnitt «ACLs definieren Menschen, nicht der Code».

Durchsucht: `config.php`, `src/`, `public_html/` (ohne `assets/`) nach Rollen- und
Gruppennamen in Zugriffsentscheiden, festen Personen-, Adress-, Host- und Modullisten,
Herkunfts- und IP-Prüfungen.

## Was in Ordnung ist

- **Präsentationen** — der Kern der Anwendung. `Presentation::checkPermission()` kennt genau
  zwei Quellen: Besitzer laut `meta.json` und die ausdrücklichen Freigaben in `acl.json`, die
  ein Mensch in der Oberfläche setzt (`owner` / `edit` / `view`, dazu der öffentliche Link,
  nur Ansehen). Kein Eintrag = **kein Zugriff** (fail-closed), und **keine Admin-Hintertür**.
  Genau so, wie die Regel es will.
- **Rollen** `admin` / `editor` werden von einem Menschen in `admin_settings.php` vergeben, mit
  Schutz gegen das Entfernen des letzten Admins. Der Code setzt nur durch.
- **Vorlagen und Folien-Sätze** prüfen Besitz (`owner_id`) plus Rolle, keine festen Listen.
- **Keine** Herkunfts-, IP- oder CORS-Prüfungen, die Zugriff entscheiden; keine festen
  Benutzer- oder Adresslisten.

## Fund 1 — Module sind sichtbar, wenn niemand etwas gesagt hat (fail-open)

`src/Launcher.php:146-151`:

```php
// Leere requireAnyGroup = für Hub-Nutzer sichtbar; sonst Gruppen-Match
$groups = $module['requireAnyGroup'] ?? null;
if (!is_array($groups) || $groups === []) {
    return true;
}
```

Ein Modul ohne Gruppeneintrag ist damit **für alle angemeldeten Personen sichtbar**. Das ist
genau das von der Regel verbotene «offen, weil niemand etwas gesagt hat». Erschwerend: diese
Stelle entscheidet in der FolienSchmiede tatsächlich, denn `ModuleAccess` (die Klasse des Hubs)
gibt es hier nicht — der Zweig darüber greift nie.

**Vorschlag (braucht ein Wort, nicht hektisch):** umdrehen auf fail-closed — ohne Eintrag
**nicht** sichtbar. Besser noch: die FolienSchmiede entscheidet das gar nicht selbst, sondern
zeigt nur, was der Hub für diese Person freigibt. Der zweite Weg ist der eigentliche; er hängt
an derselben Pendenz wie U50 (Hub-Datenordner lesend einhängen, U67) bzw. an einem
Hub-Endpunkt.
**Was sich sichtbar ändert: heute nichts.** Auf `slides.bkbiel.ch` liest das Modul ohnehin
keine `modules.json` (kein Hub-Datenordner im Container), das Dock zeigt also nur das
Hub-Zeichen. Die Umstellung wirkt erst, wenn der Ordner da ist — und dann richtig herum.

## Fund 2 — die Regel «Gruppe *Admin* darf alles» steht im Code, zweimal

1. `src/Auth.php:316-325`: Beim Anmelden über den Hub wird jedes Tag und jede Gruppe mit
   `strcasecmp(..., 'Admin')` verglichen; passt eines, wird das lokale Konto als `admin`
   angelegt — also **vollwertiger Administrator der FolienSchmiede**.
2. `src/Launcher.php:143`: `hasAnyTag($userTags, ['Admin'])` → sieht alle Module.

**Wer** die Gruppe trägt, sagt der Hub, das ist richtig. **Dass genau der Name «Admin» alles
aufschliesst**, sagt der Code — und das ist eine Zugriffsregel in der Maschine. Wird im Hub
eine Gruppe «Admin» für einen anderen Zweck angelegt, bekommen deren Mitglieder hier
unbemerkt Administratorrechte.

**Vorschlag:** ein Feld in den Verwaltungseinstellungen — «Welche Hub-Gruppe gilt als
Administrator?» — abgelegt in `data/config.json`, gepflegt von einem Menschen; der Code liest
nur. **Wichtig, darum ausdrücklich:** diese Stelle entscheidet über Urs' eigenen Zugang. Ein
leeres Feld als Vorgabe (strikt fail-closed) könnte dazu führen, dass sich nach einer
Neuanmeldung niemand mehr als Administrator anmeldet. Darum der sichere Weg: Feld einführen
und **mit «Admin» vorbelegen**, damit sich im ersten Moment nichts ändert; danach kann es ein
Mensch ändern. Das ist ein Eingriff, der aussperren kann — **also nur mit Urs' direktem
Wort**, nicht über eine Weitergabe.

## Fund 3 — der erste registrierte Benutzer wird automatisch Administrator

`src/Auth.php:59` und `config.php:219`. Hier entscheidet die Maschine, wer alles darf. Es ist
die übliche Erstinbetriebnahme und im `README.md` beschrieben, und ohne sie käme nach einer
leeren Installation niemand hinein.

**Vorschlag:** so lassen, aber bewusst: es greift nur, solange **kein** Benutzer existiert.
Wer es strenger will, ersetzt es durch einen Einladungsschlüssel, den ein Mensch ablegt. Kein
Handlungsbedarf aus meiner Sicht.

## Fund 4 — feste Domainlisten entscheiden, wo eine Anmeldung gilt

`src/SharedAuth.php:441` und `:463` führen `.bkbiel.ch`, `.wunder.li`, `.fworsa.ch`,
`.churchforge.dev` als Liste: davon hängt ab, auf welcher Domain das Sitzungs-Cookie gesetzt
wird und welche Hosts als zusammengehörend gelten. Dasselbe steht in der geteilten Fassung
(`shared/src/SharedAuth.php`, Konstanten `DOMAINS` und `HUBS`). Die Regel nennt Hosts
ausdrücklich mit.

**Vorschlag:** **nicht** von mir anfassen. `src/SharedAuth.php` ist für mich gesperrt (kein
Go), und die geteilte Fassung gehört dem Hub; eine Änderung wirkt auf alle Anwendungen und
kann Anmeldungen kappen. Gehört als Entscheid zum Hub — gemeldet, mehr nicht. Technisch wäre
der Weg eine Liste in der Hub-Konfiguration statt im Quelltext.

## Fund 5 — zwei feste Konten im Quelltext (nur Demo)

`src/Demo.php:14-15` legt zwei Demo-Konten samt Kennwort im Quelltext an (Werte nenne ich hier
nicht). Sie entstehen nur bei `DEMO_MODE`, also auf der Demo, und die ist seit Urs' Entscheid
vom 16.10. eingefroren.

**Vorschlag:** solange die Demo eingefroren ist, nichts tun. Wird sie wieder aufgenommen:
Konten und Kennwörter aus einer Datei neben der Installation lesen, nicht aus dem Repo.

## Zusammengefasst

Der Kern — wer welche Präsentation sehen und ändern darf — ist **sauber von Menschen
definiert**. Die Funde betreffen die Ränder: die Sichtbarkeit von **Modulen** (Fund 1, heute
ohne Wirkung) und die Zuordnung **Hub-Gruppe → Administrator** (Fund 2, aussperrungsrelevant).
Geändert habe ich nichts.
