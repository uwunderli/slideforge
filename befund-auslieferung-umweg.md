# Was der fehlende Mount kostet

Laufendes Protokoll. Zweck: dem `Koordinator` bei Bedarf belegen, was die Auslieferung
über eine zweite Sitzung konkret kostet, statt nur zu sagen, dass der Mount fehlt.

**Ursache:** SlideForge entwickelt auf srv09 (`/mnt/storage`), Produktion liegt auf
192.168.10.10 unter `/srv/daten/appdata/slideforge/www`. Mein SSH-Zugang dorthin
(Port 2222) bindet nur `/srv/daten/www` ein — einen toten Verzeichnisbaum. Nötig wäre am
openssh-Jail zusätzlich `-v /srv/daten/appdata/slideforge/www:/data/slideforge`.
**Erledigt am 2026-09-26.** `deb-srv9master` hat den Jail-Container neu erstellt und
`/srv/daten/appdata/slideforge/www` als `/data/slideforge` eingebunden, für `admin`
schreibbar. Selbst geprüft, nicht geglaubt: `ASSET_VERSION` im Ordner stimmt mit dem live
ausgelieferten Wert überein, eine Schreibprobe war binnen Sekunden unter
`https://slides.bkbiel.ch/assets/css/import-dialog.css` sichtbar, und das Zurücksetzen
stellte die Datei byteidentisch wieder her. Ab jetzt liefert SlideForge selbst aus.

## Vorfälle

**2026-09-26 — Renderer-Korrektur (Deckkraft animierter Objekte, Strichstärke offener Formen)**
Auslieferung über `bkb-srv10`. Zusätzlich nötig: Prod-Fassung anfordern, diffen,
Zieldatei bauen, Hash übergeben, Vollzug abwarten. Mehraufwand geschätzt 40 Minuten
gegenüber einem direkten Schreibvorgang.

**2026-09-26 — Fehlalarm aus dem toten Ordner**
Ich habe `web-bkb10:/data/www/slideforge` gemessen, dort die alte Fassung gefunden und
`bkb-srv10` einen nicht ausgeführten Deploy vorgeworfen. Der Vorwurf war falsch; der
`Koordinator` hat ihn zunächst bestätigt und lag ebenfalls falsch. Aufklärung kostete drei
Sitzungen mehrere Nachrichten. **Direkte Ursache ist derselbe fehlende Mount:** Der einzige
Pfad, den ich sehe, ist der, der nicht ausgeliefert wird.

**2026-09-26 — Logos-Import (12 Dateien) und Nachlieferung der Dialog-Gestaltung**
Zwei getrennte Auslieferungen über `bkb-srv10`. Die zweite blieb liegen, bis Urs selbst
nachfragte „Hast du das wirklich hoch geladen?" — ich hatte ihn zum Neuladen aufgefordert,
bevor der Vollzug bestätigt war. **Kosten für Urs: eine unnötige Rückfrage und eine
Fehlinformation von mir.** Lehre daraus, unabhängig vom Mount: Vollzug erst melden, wenn
von aussen gemessen (`curl .../sw.php` plus Prüfsumme der ausgelieferten Datei).

## Summe

An **einem** Tag, für **drei** Auslieferungen einer einzigen Erweiterung:
rund **40 Minuten Mehraufwand je Auslieferung** (Prod-Fassung anfordern, diffen, Paket
bauen, Hash übergeben, Vollzug abwarten), **drei** beteiligte Sitzungen statt einer,
**ein zu Unrecht erhobener Vorwurf** gegen `bkb-srv10`, den zwei Sitzungen
richtigstellen mussten, und **eine Rückfrage von Urs** („Hast du das wirklich hoch
geladen?"), weil eine Auslieferung unbemerkt liegen blieb.

**Die Trennung gehört dazu:** Zeit und fremde Aufmerksamkeit kostete der Umweg. Die
Falschmeldung an Urs war **mein** Fehler — ich hatte Vollzug gemeldet, bevor ich gemessen
hatte. Der Umweg hat ihn begünstigt, aber nicht verursacht.

**Lehre:** Eine solche Lücke sofort schliessen, nicht umgehen. Der Umweg funktionierte
jedes Mal — und kostete trotzdem mehr als das Schliessen gekostet hätte.

## Offener Punkt: der tote Ordner

`/srv/daten/www/slideforge` (im Jail als `/data/www/slideforge`) ist die
Verwechslungsquelle, die den Fehlalarm ausgelöst hat: 67 MB, `ASSET_VERSION 629` vom
2026-09-22, in keinen laufenden Container eingebunden.

Geprüft am 2026-09-26: 18 Präsentationen, **keine** davon fehlt in Produktion (dort 19),
und **keine** enthält dort mehr Dateien als in Produktion. Es liegt also nichts
Einmaliges darin.

**Empfehlung:** zuerst **umbenennen** (etwa auf `slideforge.tot-2026-09-26`), nicht
löschen. Das beseitigt die Verwechslungsgefahr sofort und ist umkehrbar. Löschen ist
nicht umkehrbar und braucht darum Urs direkt — und erst, wenn die Umbenennung eine
Weile ohne Folgen überstanden hat.
