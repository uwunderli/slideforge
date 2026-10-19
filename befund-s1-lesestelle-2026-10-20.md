# S-1 Phase 1: Lesestelle für das verschlüsselte CT-Token eingebaut

**Datum:** 20.10.2026 · **Einordnung: sicherheitsrelevant** (Anmeldepfad) · **Stand: gebaut und
geprüft, nicht ausgeliefert.**
**Auftrag:** Koordinator, 20.10., Sicherheitsmassnahme S-1. Vorgabe
`_berichte/hub/s1-lesestelle-forks-2026-10-20.md`, sha256
`b0f400f04c2833b6d17977cadcb73dd142a8f60e1be702d245e84f0ee3c9f467` — **Prüfwert gegengerechnet,
stimmt.** Die beiden Code-Blöcke habe ich **nicht abgetippt**, sondern programmatisch aus der
Vorgabedatei herausgeschnitten und eingesetzt; damit sind sie byte-gleich.

## Was eingebaut ist

In `src/SharedAuth.php`:

1. In `decode()`, nach Signatur- und `exp`-Prüfung, vor `return $json;`: `ct_login_token_enc`
   wird entschlüsselt und für alle Leser als `ct_login_token` bereitgestellt; die alte
   Klartext-Form gilt weiter; misslingt das Entschlüsseln, gibt es **kein Token, aber keine
   Abmeldung**. `go_access_token` wird nicht mehr weitergereicht.
2. Drei neue Funktionen: `feldSchluessel()` (HKDF-SHA256 aus dem **rohen** gemeinsamen
   Geheimnis, Zweck `cf-session-feld-v1`), `feldVerschluesseln()` und `feldEntschluesseln()`
   (AES-256-GCM, Nonce 12, Tag 16, AAD `cf-session|<sub>`).

**Voraussetzungen geprüft:** `secret()` liefert in unserer Fassung das rohe Geheimnis,
`b64url()`/`b64urlDecode()` sind **zeichengleich** mit `gemeinde/shared`. Ein Unterschied bleibt
(vorbestehend, nicht von S-1): die geteilte Fassung akzeptiert zusätzlich die
Umgebungsvariable `HUB_AUTH_SECRET`, unsere nur `CHURCHFORGE_AUTH_SECRET`. Auf einem Host, der
nur die erste setzt, könnte unsere Fassung ohnehin nichts entschlüsseln — sie könnte dort schon
heute keine Sitzung prüfen.

## Prüfung: 16 Fälle, alle grün

`php _pruefungen/s1-sharedauth-feld.php` (Vorlage `hub/scripts/test-sharedauth-feld.php`).

**Der wichtigste Fall läuft in zwei Prozessen:** verschlüsselt wird mit der **geteilten**
Fassung (`gemeinde/shared/src/SharedAuth.php`), entschlüsselt mit **unserer**, mit demselben
Geheimnis. Beide Klassen heissen `SharedAuth`, in einem Prozess geht das nicht — und nur so ist
belegt, dass die Stelle wirklich byte-gleich rechnet und nicht nur gleich aussieht.

Weiter geprüft: Klartext-Form gilt weiter; `_enc` wird nicht weitergereicht; **anderes `sub`
(AAD) → kein Token, Sitzung bleibt gültig**; fünf kaputte Feldformen → kein Token, keine
Abmeldung; sind Klartext und `_enc` beide da, bleibt der Klartext stehen; altes
`go_access_token` im Keks wird nicht weitergereicht; unsere Fassung schreibt es nirgends; das
Token steht nicht im Klartext im Keks; zweimal verschlüsselt ergibt zwei verschiedene Felder;
mit falschem Geheimnis gilt der Keks gar nicht.

**Kein Leser im Modul ausserhalb von SharedAuth:** `go_access_token` und `ct_login_token`
kommen im ganzen Modul nur in `src/SharedAuth.php` vor. Das `unset()` kann hier also nichts
brechen.

## Zwei Dinge, die zum Auftrag nicht passen

1. **Der md5 in der Weitergabe ist nicht meine Datei.** Angegeben war `0ce3015626`
   (wunder.li). Mein Arbeitsbaum hatte vorher `ec00e634d563` (sha256 `d7d6d2e9c931…`,
   491 Zeilen) — **identisch mit der laufenden Datei auf bkbiel**, geprüft durch Herunterholen
   und Vergleichen. Es gibt also mindestens zwei Modul-Fassungen; die auf wunder.li ausgelieferte
   ist ein anderer Stand. Was ich hier baue, passt auf den bkbiel-Stand; für die Demo wäre es
   eine eigene Lieferung, und die ist eingefroren.
2. **Auf bkbiel wirkt unsere Fassung vermutlich gar nicht.** `shared/bootstrap.php` lädt
   `shared/src/SharedAuth.php` fest mit `require_once` (Zeile 12). Ist der Mount da — am
   11.10. im Container als uid 101 gemessen: er ist da —, dann ist `SharedAuth` schon
   geladen, bevor unser Autoloader zum Zug kommt, und **die geteilte Fassung gewinnt**.
   Unsere Kopie greift nur im Alleinbetrieb ohne Mount. Für bkbiel zählt also Phase 1 in
   `shared`, nicht diese Lieferung. Bitte von bkb-srv10 bestätigen lassen, bevor jemand aus
   dieser Lieferung eine Wirkung auf Prod ableitet; mein Stand ist eine Messung vom 11.10.

Beides ändert nichts daran, dass die Stelle hier drin sein muss, bevor der Hub Phase 2
ausliefert — sonst schreibt eine Übergabe aus dem Alleinbetrieb ein Cookie ohne Token über das
des Hubs (Review M2 der Vorgabe).

## Auslieferung

- **Vorher-Digest, geprüft:** `src/SharedAuth.php` auf Prod =
  `d7d6d2e9c931dc070efdddddc5d4b738ff67d455fc7f20f79ad05161b5256df7`, gleich dem Arbeitsbaum
  vor der Änderung.
- **Nachher (Arbeitsbaum):** `2dfb74ed35496dfebd95e1478c93e7067fe2bf5c720797a953ac3d8ff2abab45`.
- **Der Unterschied ist rein additiv:** 52 Zeilen dazu, **keine** entfernt oder geändert
  (`diff` gegen die laufende Datei, nur `+`-Zeilen).
- Kein `ASSET_VERSION`-Sprung (keine statische Datei).
- Takt-Probe nach der Lieferung: `login.php` muss **302** zur Hub-Anmeldung liefern (ein
  Sprung), `register.php` **200** ohne Meldungszeilen, und im Container-Protokoll dürfen keine
  `secret.missing`-Zeilen auftauchen.

> **Rückbau-Satz:** `src/SharedAuth.php` aus `.sicherung/s1-lesestelle-<stempel>/` zurücklegen
> (Prüfwert `d7d6d2e9c931…`) — die Datei ist der ganze Umfang, es wandern keine Daten mit, und
> es gibt keinen Zustand, der danach noch von der Änderung abhängt.

**Mein Urteil zur Aussperr-Frage:** Die Änderung kann eine Sitzung nicht entziehen. Sie läuft
erst **nach** erfolgreicher Signatur- und Ablaufprüfung, fängt jeden Fehler ab und setzt im
schlechtesten Fall ein leeres Token; `feldSchluessel()` kann an dieser Stelle nicht mehr an
einem fehlenden Geheimnis scheitern, weil die Signaturprüfung es schon gebraucht hat. Ein
Aussperren sehe ich darum nicht — anders als beim Schreiben (Phase 2), das ausdrücklich **nicht**
Teil dieser Lieferung ist.
