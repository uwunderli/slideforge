# Besitzer und Rechte der Präsentationsordner korrigiert (U51)

**Datum:** 18.10.2026 · **Host:** `slides.bkbiel.ch` (Prod, `web-bkb10:/data/slideforge`)
**Go:** Urs, 18.10.2026, Wortlaut «U51 → Ja Rechte korrigieren», weitergegeben durch den
Koordinator. Umfang laut Weitergabe: nur die genannten Präsentationsordner (6 mit falschem
Besitzer, 12 mit `0777`) und `data/jobs`; nichts löschen, keine Alarm-Bereiche.

## Was vorher war — und was ich zusätzlich belegt habe

Der Befund aus der Nacht (`_berichte/slideforge/nacht-2026-10-18.md`) hat sich **genau so**
bestätigt. Vor dem Eingriff gemessen:

| Zustand | Anzahl Ordner |
|---|---|
| `101:101`, `0750` — von der App selbst angelegt | 2 |
| `99:100`, `0777` — funktionieren, weil jeder schreiben darf | 12 |
| `99:100`, `0755` — **für die App nicht schreibbar** | 6 |
| `data/jobs`: `99:100`, `0777` | 1 |

**Neu und wichtig:** In der Nacht war «PHP läuft als 101» aus dem Code geschlossen. Jetzt ist
es gemessen. Zwei unabhängige Belege:

1. In `presentations/c0dcb7f9a094d997` wurde `live.json` am **18.10. um 08:41 von uid 101**
   geschrieben, `slides.json` am 17.10. um 19:54 — die App schreibt also als 101.
2. Eine Schreibprobe mit `posix_setuid(101)` ergab vor dem Eingriff für die sechs Ordner
   `Ordner=nein slides.json=nein`, für die beiden app-eigenen `ja`.

Alle `admin`-Dateien (uid 99) stammen aus einer Kopie am **10.10. um 08:28** — darum gehören
die älteren Präsentationen dem falschen Benutzer. In den `0777`-Ordnern sind auch die Dateien
weltschreibbar, deshalb funktionierten sie.

Die Zugänge, über die ich den Eingriff gemacht habe: `ssh web-bkb10` (Benutzer `admin`, uid 99)
mit `sudo` auf root in diesem Container. `/data/slideforge/data` ist dieselbe Ablage, die die
App unter `/var/www/html/data` sieht.

## Sicherung vor dem Eingriff

`/data/slideforge/.sicherung/rechte-2026-10-18/` (Ordner `0700`, beide Dateien `0600`, root):

| Datei | sha256 | Inhalt |
|---|---|---|
| `vorher-rechte.txt` | `17b566faa16e2dc26925866aebbe6b603681fd73eed02e2a8ba34d79a6998c19` | 136 Pfade mit `uid:gid mode` |
| `rueckbau.sh` | `419e34f978e266607f28680880e103f2dd841fc26db7f37bfc3276e4393c4ec8` | 272 Zeilen, je `chown`/`chmod` pro Pfad |

## Was ich geändert habe

Für jeden der 18 Ordner einzeln (nicht als Sammelbefehl, damit jeder Schritt im Protokoll
steht):

```
sudo chown -R 101:101 <ordner>
sudo chmod -R ug=rwX,o=  <ordner>          # Ordner 0770, Dateien 0660
sudo find <ordner> -type d -exec chmod 2770 {} +
```

und für `data/jobs` selbst `chown 101:101` + `chmod 2770`. Die beiden Unterordner von
`data/jobs` gehörten schon dem Dienst und blieben unberührt, ebenso die zwei app-eigenen
Präsentationsordner.

## Zahlen und Proben nachher

| Messung | vorher | nachher |
|---|---|---|
| Präsentationsordner `101:101 2770` | 0 | **18** |
| Präsentationsordner `99:100` | 18 | **0** |
| `data/jobs` | `99:100 0777` | **`101:101 2770`** |
| Pfade mit Besitzer 99 im Umfang (Ordner + Dateien) | 136 von 136 | **0** |
| Schreibrecht als uid 101 (20 Ordner) | 14 von 20 | **20 von 20** |

**Inhalt unverändert:** Sammel-sha256 über alle `slides.json` vorher und nachher identisch —
`b52c2c3b8547c7990476904047fccc3b91db16d41b058fefd12d630b23fa2a37`. Es wurde nichts
geschrieben, verschoben oder gelöscht; nur Besitzer und Rechte.

**Takt-Probe:** `login.php` antwortet mit `302` auf die Hub-Anmeldung (0.07 s),
`assets/css/style.css` mit `200`, beide mit Cache-Buster. Das Schreibrecht ist als uid 101 für
alle 20 Ordner und deren `slides.json` belegt. **Was ich nicht selbst prüfen konnte:** eine
echte Präsentation im Editor öffnen und speichern — dafür fehlt mir eine Anmeldung, und die
soll mir auch fehlen. Das ist Urs' Abnahmeschritt; die Rechtelage ist gemessen offen.

## Rückbau

> **Rückbau-Satz:** `ssh web-bkb10 "sudo sh /data/slideforge/.sicherung/rechte-2026-10-18/rueckbau.sh"`
> setzt Besitzer und Rechte aller 136 Pfade wieder auf den Stand vom 18.10.2026, 14:04 UTC
> (vor dem Eingriff) — also 18 Ordner auf `99:100` mit `0777` bzw. `0755` und `data/jobs` auf
> `99:100 0777`. Inhalte sind nicht betroffen, weil keine geändert wurden.

## Was bewusst offen geblieben ist

- **`data/presentations` selbst** ist weiter `99:100` mit `0777`, ebenso die `.gitkeep` darin.
  Das war nicht im Umfang. Solange der Ordner weltschreibbar ist, kann die App neue
  Präsentationen anlegen; eine Korrektur auf `101:101 2770` wäre der folgerichtige nächste
  Schritt, braucht aber ein eigenes Go.
- **Setgid auf den Ordnern** ist gesetzt (`2770`), obwohl die beiden app-eigenen Ordner ohne
  Setgid auskommen (`0750`). Es schadet nicht und hält die Gruppe stabil, falls einmal ein
  zweiter Dienst dazukommt.
- **Wer ausser der App schrieb, schreibt nicht mehr.** `admin` (uid 99) hat in diesen Ordnern
  jetzt kein Schreibrecht. Für die App ist das richtig; ein Werkzeug, das als `admin` in
  Nutzerdaten schreibt, gibt es nach meiner Kenntnis nicht (Medien liefert `asset.php` aus,
  also PHP als 101; die Auslieferung lässt `data/` aus).
