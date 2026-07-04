## SlideForge v1.0.1

Maintenance release: documentation, feature tour, spellcheck UX, and admin invite flow.

### Highlights

- **README** restructured — features by Dashboard / Editor / Presentation / Admin, installation guide (Docker, nginx, shared hosting), AI disclaimer
- **Feature tour v2** — navigation slide, UI screenshots, tours in DE / EN / FR / IT / RM ([live demo](https://slideforge.service7.ch/view.php?token=slideforge-tour))
- **Spellcheck** — optional check before presentation mode; improved LanguageTool UX (loading state, re-check, markdown handling)
- **Admin** — invite links with optional email moved to user management tab
- **Editor** — animation and transition icon pickers; public view can show navigation controls

### Upgrade

Replace code files on your server (keep `data/` and `public_html/uploads/`). No database migration — file-based storage only.

See [CHANGELOG.md](https://github.com/uwunderli/slideforge/blob/main/CHANGELOG.md) for the full list.

### License

MIT — Copyright (c) 2026 Urs Wunderli
