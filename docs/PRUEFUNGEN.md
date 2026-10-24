# Was geprüft wird — statt eines Prüfsatzes

Urs, 21.10.2026: «Ich habe dir den Prüfsatz komplett entzogen. Er liegt jetzt ausserhalb des Jails.
Du schreibst nun in eine md-Datei, was geprüft wird. Mehr gibts nicht! Erst wenn zu viele Fehler
auftauchen, machen wir uns wieder Gedanken über das Prüfen.»

Diese Datei tritt an seine Stelle: Vor jeder Auslieferung steht hier in einer Zeile, **was** geprüft wurde
und **wie**. Nach dem Muster von `officeforge/docs/PRUEFUNGEN.md`.

## So wird in der FolienSchmiede ausgeliefert

**Achtung, hier ist es anders als bei OfficeForge:** `scripts/lint.sh` und `scripts/release.sh --rueckbau`
gibt es in diesem Modul **nicht** (Stand 24.10.2026). Statt dessen:

1. **Lint:** `php -l` über die geänderten Dateien — es gibt kein Sammelskript.
2. **Vorher messen:** Prüfsumme der **laufenden** Datei holen
   (`ssh web-bkb10 sha256sum /data/slideforge/<pfad>`) und mit dem Arbeitsbaum vergleichen.
   **Nie gegen HEAD diffen** — auf Prod lief jahrelang nicht festgeschriebene Arbeit (seit 20.10. ist HEAD
   nachgezogen, die Regel bleibt).
3. **Sichern:** die laufende Fassung nach `/data/slideforge/.sicherung/<name>-<stempel>/` kopieren
   (`cp -p`), Prüfsumme gegenprüfen.
4. **Ausliefern:** `scp -p` der einzelnen Dateien (oder `.deploy/deploy.sh sync-code` für den ganzen Baum;
   die Positivliste lässt `data/` und eigene Ordner aus und löscht **nichts**, `rsync` läuft ohne `--delete`).
5. **Nachher messen:** Prüfsumme auf Prod = Arbeitsbaum, Besitzer und Rechte unverändert.
6. **Takt-Probe**, jede Abfrage mit Cache-Buster (`?cb=$(date +%s)`; openresty hält Statisches ~11 h):
   `login.php` → 302 zur Hub-Anmeldung, eine Seite mit 200 und **0** PHP-Meldungszeilen, `sw.php` liefert
   `slideforge-<ASSET_VERSION>`.
7. **Rückbau:** die gesicherten Dateien zurückkopieren — es gibt kein `--rueckbau`-Skript. Darum gehört zu
   jeder Lieferung ein Rückbau-Satz in den Befund.
8. **Statische Dateien geändert?** Dann `ASSET_VERSION` in `config.php` anheben, sonst sehen die Browser
   die alte Fassung. Reine PHP-/Markup-Änderungen brauchen das nicht.

Der `pre-push`-Hook liefert seit 18.10.2026 **nichts** mehr aus und setzt kein Tag (`.githooks/pre-push`,
alte Fassung daneben als `.aus-2026-10-18`). Ein Push ist also keine Auslieferung — und der Versionssprung
in `VERSION`/`CHANGELOG.md` kommt von Hand.

## Was beim Bauen gilt statt Prüfungen

Die sechs Fehlermuster und ihre Regeln: `../hub/docs/FEHLERMUSTER.md`. Sie sind beim Schreiben einzuhalten,
nicht nachträglich zu suchen.

Dazu zwei Dinge, die in diesem Modul regelmässig Zeit gekostet haben:
- **Eine Messung, die nichts findet, belegt nichts** — vorher belegen, dass sie anschlagen *kann*
  (Gegenprobe mit einem Fall, der anschlagen muss).
- **Chromium misst den eigenen Zwischenspeicher**, wenn man ihm kein frisches Profil gibt
  (`--user-data-dir="$(mktemp -d)" --incognito`), und legt Speicherabzüge im Arbeitsverzeichnis ab, wenn
  `ulimit -c 0` fehlt.

## Prüf-Protokoll je Auslieferung

Neueste zuoberst. Eine Zeile je Stand: Commit · was geändert wurde · was geprüft wurde · was offen bleibt.

| Stand | Änderung | Geprüft | Offen |
|---|---|---|---|
| `016a7a9` · ausgeliefert 24.10. 19:54 UTC | Logos-Import: Häkchen «Gliederung zusammengefasst auf Folien» (Vorgabe aus) — mehrere Unterpunkte samt Stichpunkten je Folie, übergeordnete Überschrift als Titel; neu `LayoutSet::roleTextCapacity()` (Platz aus Platzhalter und Schriftgrad); Stellenangabe verlor die Buchnummer («2. Chr» → «Chr») | `php -l` über die fünf geänderten Dateien grün; Messung am echten Export `manasse-2026-10-24.html`: elf Punkte → **neun** Folien, 28 Folien ohne Häkchen unverändert, **0 leere Folien**, 0 Warnungen, keine Doppelung in den Notizen; Gegenproben: ausdrückliche Dialogwahl («in die Notizen», Gliederungstiefe 2) schaltet die Zusammenfassung **nicht** an, Listen-Nummer bleibt mit Listenzeichen und fällt ohne weg, `roleTextCapacity()` liefert für vier Rollen vier verschiedene Werte. **Nach der Lieferung:** neun Dateien auf Prod mit den Prüfwerten des Arbeitsbaums, Rechte unverändert (99:100 644); Takt-Probe `login.php` 302 in einem Sprung zur Hub-Anmeldung, `import.php` 302, `register.php` 200/3347 B mit 0 Meldungszeilen, `sw.php` `slideforge-679` (keine Versionsanhebung nötig, keine statische Datei geändert); Sicherung `.sicherung/logos-zusammenfassung-20261024/` | `textMaxCharacters: 'layout'` wird im Importer weiterhin zu «keine Grenze» — die Einstellung verspricht etwas und tut nichts (Befund §5.1, nicht angefasst); Folienzahl hängt an der Schriftgrösse des Inhaltsfelds im Set (65 px → 9 Folien, 48 px → 5), Entscheid liegt bei Urs |
