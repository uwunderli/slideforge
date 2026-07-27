# Audio-DB für Medien-Editor — Recherche (AENDERUNGEN #6)

**Status:** Vorschlag (Recherche abgeschlossen) · **AENDERUNGEN:** [C #6](AENDERUNGEN.md) · Prio **5**

## Ziel

Offene Audiodatenbank neben Pixabay/Icons für den Editor/Medien-Bereich — lizenzklar, API-tauglich, Self-Hosting nicht nötig.

## Kandidaten (Kurz)

| Quelle | Lizenz | API | Eignung |
|--------|--------|-----|---------|
| [Freesound.org](https://freesound.org) | CC0 / CC-BY / Sampling+ | Ja (OAuth) | Gross, gut für SFX; Attribution je nach Lizenz |
| [Pixabay Audio](https://pixabay.com/music/) | Pixabay License | Wie Bilder-API | Einheitlich mit bestehender Pixabay-Integration |
| [Openverse](https://openverse.org) | CC-Varianten | Ja | Aggregator; Filter nötig |
| [Mixkit](https://mixkit.co/free-sound-effects/) | Mixkit License | Kein off. API | Eher manuell / Scraping ungeeignet |
| [BBC Sound Effects](https://sound-effects.bbcrewind.co.uk/) | RemArc (nicht kommerziell) | Download | Nur nicht-kommerziell |

## Empfehlung

1. **Kurzfristig:** Pixabay Audio über bestehende Pixabay-Anbindung prüfen (ein API-Key, gleiche UX).
2. **Mittelfristig:** Freesound als zweite Quelle (SFX, Atmo) — CC-Filter in UI, Attribution automatisch.

## UI (Entwurf)

- Medien-Tab: Unterreiter **Bilder | Icons | Audio**
- Suche + Vorschau-Welle + «Auf Folie» / «In Medienbibliothek»
- Metadaten: Lizenz, Autor, Dauer

## Nächste Schritte

- [ ] Pixabay Audio API testen (Key, Rate Limits)
- [ ] Lizenz-Texte DE/EN für UI
- [ ] Entscheid Owner: nur Pixabay vs. Freesound zusätzlich
- [ ] Dann in Release einplanen (nicht v2.1.3)
