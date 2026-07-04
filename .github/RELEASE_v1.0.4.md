## SlideForge v1.0.4

Feature release: WebDAV media import, admin settings tabs, and editor UX polish.

### Highlights

- **WebDAV** — connect your own cloud or NAS (Nextcloud, Synology, …) in **Profile**; browse and import images, SVG, audio, and video from the editor **Media** tab ([demo showcase](https://slideforge.service7.ch/view.php?token=slideforge-showcase))
- **Admin settings** reorganized into tabs: General · SMTP · Spellcheck · Media · Users
- **Media UX** — consistent button order (On slide first), lightbox preview for WebDAV, SVG aspect ratio on insert

### Upgrade

Replace code files on your server (keep `data/` and `public_html/uploads/`). No database migration — file-based storage only.

Set `APP_SECRET` in `config.php` if not already defined (required for encrypted WebDAV passwords).

See [CHANGELOG.md](https://github.com/uwunderli/slideforge/blob/main/CHANGELOG.md) for the full list.

### License

MIT — Copyright (c) 2026 Urs Wunderli
