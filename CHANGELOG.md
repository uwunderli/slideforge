# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - 2026-07-04

### Added

- **Iconify integration:** search SVG icons from 150+ sets in the editor (no API key)
- Icon color picker in the search dialog and on the slide (brand palette supported)
- Server-side SVG tinting for preview, presentation, and export

## [1.0.1] - 2026-07-04

### Added

- Optional spellcheck before entering presentation mode (user setting in editor)
- Feature tour v2: navigation slide, UI screenshots, multilingual tours (DE/EN/FR/IT/RM)
- Improved LanguageTool integration: loading UI, re-check button, markdown list handling, name-variant hints
- Invite management moved to admin user-management tab with optional email delivery

### Changed

- README restructured: feature overview by area, installation guide, AI disclaimer
- Animation and transition pickers use icon grids instead of dropdowns
- Public view links can show navigation controls when enabled on the presentation

## [1.0.0] - 2026-07-03

### Added

- Self-hosted, file-based multi-user editor for [reveal.js](https://revealjs.com) presentations
- Canvas editor (Konva.js): shapes, text, images, video, animations, templates
- Presentation mode with live sync, offline HTML/ZIP export, PDF, PPTX, and ODP export
- PPTX import, user roles, SMTP mail, invite links, DE/EN/FR UI
- Seven default slide templates seeded on first admin registration

[1.0.2]: https://github.com/uwunderli/slideforge/releases/tag/v1.0.2
[1.0.1]: https://github.com/uwunderli/slideforge/releases/tag/v1.0.1
[1.0.0]: https://github.com/uwunderli/slideforge/releases/tag/v1.0.0
