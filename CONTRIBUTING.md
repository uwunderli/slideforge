# Contributing to SlideForge

Thank you for your interest in improving SlideForge.

## Getting started

1. Clone the repository and point your web server’s document root at `public_html/`.
2. Ensure `data/` and `public_html/uploads/` are writable by the PHP process.
3. Register the first user — that account becomes administrator automatically.

See [README.md](README.md) for deployment details (Docker or classic nginx + PHP).

## Pull requests

- Keep changes focused; one logical change per PR when possible.
- Match existing code style (plain PHP, no Composer, minimal dependencies).
- Test manually in the editor, preview, presentation mode, and export if your change touches those areas.
- Do not commit secrets, user data, or files under `data/` or `public_html/uploads/`.

## Reporting issues

Use GitHub Issues for bugs and feature requests. Include PHP version, browser, and steps to reproduce when reporting bugs.

## Security

See [SECURITY.md](SECURITY.md) for reporting vulnerabilities.
