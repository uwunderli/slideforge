# Release v2.0.2 — PWA

**Tag:** `v2.0.2`  
**Basis:** v2.0.1  
**Shipped:** 2026-07-04 · `ASSET_VERSION` 269

## Highlights

- **Progressive Web App:** Manifest, Service Worker, Icons — SlideForge als App auf dem Home-Bildschirm (Mobile Dashboard + Fernsteuerung)
- **Standalone-Modus** ohne Browser-Leiste auf unterstützten Geräten

## Test plan

- [ ] Android Chrome: „App installieren“ oder „Zum Startbildschirm“
- [ ] iPhone Safari: „Zum Home-Bildschirm“
- [ ] Start → Dashboard; Remote weiterhin über QR/Link

## Deploy

Prod: `./.deploy/deploy.sh sync-code`  
Demo: `./.deploy/deploy-demo.sh sync-demo`
