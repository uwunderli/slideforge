# Release v2.1.10

**Tag:** `v2.1.10`  
**Basis:** v2.1.9 · `ASSET_VERSION` 589

## Highlights

- Topbar an **HubUserMenu-Standard**: Anzeigename + Avatar-Chip
- Sprache und Darstellung nur noch im Benutzermenü (keine separaten Flag-/Theme-Icons)
- Vendored: `hub-user-menu.css` / `hub-user-menu.js`, Prefs via `prefs.php`

## Test plan

- [ ] Header: Name vor Avatar; Menü öffnet mit Sprache/Darstellung
- [ ] Sprache wechseln → Reload in neuer Sprache
- [ ] Darstellung Hell/Dunkel/System → sofort + Cookie
- [ ] Present + Editor + Dashboard: gleiches Menü
- [ ] Hard-Reload (ASSET 589)
