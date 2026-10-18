# Kleines Paket (vorbereitet, nicht ausgeliefert): Benutzermenü auf die Hub-Fassung

**Datum:** 18.10.2026 · **Stand: zurückgestellt, nichts gelöscht.**
Entscheid Koordinator vom 18.10., nach dieser Messung: **Kopie behalten** — das Menü soll auch
bei einem Hub-Ausfall aufgehen (ausschalten statt löschen). Stattdessen gehen die zwei
Alt-Wahlausdrücke als Meldung an den Hub, damit die Fassungen später zusammenfallen:
`_berichte/slideforge/meldung-an-hub-benutzermenue-altklassen-2026-10-18.md`.
Der Rest dieses Befunds ist die Messung, die zu diesem Entscheid geführt hat. Entstanden als
Nebenbefund von U50.

## Worum es geht

`public_html/includes/header.php:146` lädt das Benutzermenü über
`sf_hub_asset('js/hub-user-menu.js')`. Diese Funktion (`config.php:350`) **bevorzugt die
mitgeführte Kopie**: liegt `public_html/assets/js/<pfad>` vor, nimmt sie die und fragt den Hub
nicht. Es liegt eine Kopie vor, vom 2.8.2026. Das Modul fährt also eine eigene Fassung, und
Korrekturen am Hub kommen hier nie an.

## Was der Vergleich zeigt — anders als erwartet

Die Kopie ist **nicht veraltet**, sie ist dieselbe Fassung **plus zwei Alt-Wahlausdrücke**:

| | sha256 | Bytes |
|---|---|---|
| Modul `public_html/assets/js/hub-user-menu.js` | `53407b52369310f9418ef8bd6b76774f1007b813e9319f2700a57eb379ceee61` | 3139 |
| Hub, live von `hub.bkbiel.ch` geholt (= Hub-Repo, Byte für Byte) | `cec3ecd5110095067d66901ac6e31b3085bd546b5816c5a841bc470644ccf6b6` | 3098 |

Der ganze Unterschied sind zwei Zeilen: die Kopie sucht zusätzlich `.user-menu-dropdown` und
`.user-menu-trigger`, die Hub-Fassung nur `[data-hub-user-menu-dropdown], .hub-user-menu__dropdown`
bzw. `[data-hub-user-menu-trigger], .hub-user-chip`.

**Unser Markup trägt beides** (`public_html/includes/user_menu.php:33-52`): der Knopf hat
`class="hub-user-chip user-menu-trigger"` **und** `data-hub-user-menu-trigger`, die Klappe
`class="hub-user-menu__dropdown user-menu-dropdown"` **und** `data-hub-user-menu-dropdown`.
Die Hub-Fassung findet also beides. Es gibt nur diese eine Markup-Stelle; die Alt-Klassen
bleiben für die Stile (`slideforge-user-menu-bridge.css`, `mobile.css`, `style.css`) nötig und
werden **nicht** angefasst.

## Gegenprobe (gemessen, nicht geschätzt)

Prüfseite mit genau unserem Markup, einmal mit der Modul-Kopie, einmal mit der live vom Hub
geholten Datei; Chromium headless mit frischem Profil, Klick auf den Knopf im Skript, Ergebnis
in den `<title>` geschrieben:

| | API | gebunden | nach 1. Klick offen | `aria-expanded` | nach 2. Klick offen |
|---|---|---|---|---|---|
| vorher (Modul-Kopie) | da | ja | **ja** | `true` | nein |
| nachher (Hub-Fassung) | da | ja | **ja** | `true` | nein |

Gleiches Verhalten, Zeile für Zeile.

## Das Paket

Eine Datei, ein Schritt:

```
rm public_html/assets/js/hub-user-menu.js          # im Arbeitsbaum
ssh web-bkb10 sudo rm /data/slideforge/public_html/assets/js/hub-user-menu.js   # auf Prod
```

Der zweite Schritt ist nötig, weil `deploy.sh sync-code` **ohne `--delete`** überträgt: eine
Löschung im Arbeitsbaum erreicht Prod nicht von selbst. Danach liefert `hub_asset()` die Datei
von `hub.bkbiel.ch` aus, mit der Versionszählung des Hubs.

**Sicherung vorher:** die Datei nach `.sicherung/benutzermenue-<stempel>/` kopieren
(Prüfwert `53407b52369310f9…`).

> **Rückbau-Satz:** Die gesicherte `hub-user-menu.js` nach
> `public_html/assets/js/hub-user-menu.js` zurückkopieren (Prüfwert `53407b52369310f9…`) —
> dann bevorzugt `sf_hub_asset()` wieder die Modul-Kopie, wie vorher.

## Abwägung

**Dafür:** Hub-Korrekturen am Benutzermenü erreichen das Modul künftig von selbst; eine
Fassung weniger, die auseinanderlaufen kann (vgl. `befund-hub-kit-umstellung.md`).
**Dagegen:** Das Menü hängt dann an der Erreichbarkeit des Hubs. Fällt der Hub aus, ist die
Seite weiter benutzbar, aber die Klappe geht nicht auf (ohne Skript kein Öffnen) — bei der
mitgeführten Kopie ginge sie. Das ist derselbe Abtausch wie bei `cf-fine.css`; dort haben wir
dafür einen Rückfall in `style.css` gelegt. Ein gleichwertiger Rückfall wäre hier ein
Dutzend Zeilen eigenes Öffnen/Schliessen — **nicht** Teil dieses Pakets.
**Mein Vorschlag war:** so ausliefern, wenn der Hub-Ausfall als Fall gilt, in dem ein nicht
öffnendes Benutzermenü hinnehmbar ist — sonst die Kopie behalten und stattdessen die zwei
Alt-Wahlausdrücke an den Hub melden.
**Entschieden wurde das Zweite** (Koordinator, 18.10.): Kopie behalten, Meldung an den Hub.
Damit ist dieses Paket zurückgestellt; gelöscht wurde nichts. Wird die Hub-Fassung später um
die zwei Wahlausdrücke ergänzt, fallen beide Fassungen zusammen und die Kopie ist dann ein
reiner Rückfall ohne Abweichung.
