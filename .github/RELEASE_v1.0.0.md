## SlideForge v1.0.0 — First public release

SlideForge is a self-hosted, file-based multi-user editor for [reveal.js](https://revealjs.com) presentations. **No database**, no Composer — runs on any nginx + PHP host.

### Highlights

- Canvas editor (Konva.js): shapes, text, images, video, object animations
- Presentation mode with live sync between presenter and audience view
- Offline HTML/ZIP export, PDF, PPTX, and ODP export; PPTX import
- Multi-user with roles, sharing, public view links, invite registration
- Templates, spell check, Pixabay integration, DE/EN/FR UI
- Seven slide templates seeded when the first admin registers

### Quick install

1. Download **Source code (zip)** below or clone the repository.
2. Upload the project to your server (keep `config.php`, `src/`, and `data/` **outside** the web root; nginx `root` → `public_html/`).
3. Make `data/` and `public_html/uploads/` writable by the PHP process (`chmod 770` or equivalent).
4. Open `https://your-host/register.php` — the **first registered user becomes administrator**.

See [README.md](https://github.com/uwunderli/slideforge/blob/main/README.md) for Docker (`tangramor/nginx-php8-fpm`) and classic nginx deployment details.

### License

MIT — Copyright (c) 2026 Urs Wunderli
