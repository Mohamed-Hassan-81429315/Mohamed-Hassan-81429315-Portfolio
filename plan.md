# Portfolio Contact Persistence and Public Deployment Plan

## Implementation approach

Preserve the supplied single-page portfolio and its existing visual language. Serve `index.html` and the PHP endpoints from the supplied PHP 8.3 + Apache container. The contact form posts to `contact.php`, which validates requests, supports native HTML and enhanced JSON responses, applies the per-IP submission limit, and stores accepted messages and optional files in a MySQL-compatible database.

Use the project's managed MySQL database and read the runtime `DATABASE_URL` from the environment. Development and production share this database, so avoid test submissions or destructive changes. Apply `schema.sql` once for a fresh database; keep `AUTO_MIGRATE` off for normal requests. Protect the owner inbox at `/dashboard.php` with the `ADMIN_PASSWORD` held in the platform secret manager, and retain `admin.php` as a compatibility URL. Keep database and dashboard credentials out of the repository and public package.

The container listens on the supplied `PORT` (3000 by default), installs `pdo_mysql` and `mbstring`, and serves the public `/health.php` readiness endpoint. Health is successful only after the database and both required tables are available. Publish the project as a container using the supplied `Dockerfile` and `/health.php` health path; no separate static build is needed because Apache serves both the page and PHP endpoints. Preserve the route manifest and publish the package to the requested new portfolio address while leaving the existing portfolio site unchanged.

## Project structure

- `index.html`: responsive public portfolio, assets, and contact form.
- `contact.php`: POST validation, rate limiting, durable message/file persistence, JSON response, and native HTML fallback.
- `config.php`: environment-aware database connection and opt-in-only schema migration.
- `dashboard.php` and `admin.php`: protected owner inbox and compatibility URL.
- `download.php`: authenticated private attachment downloads.
- `health.php`: unauthenticated database and schema readiness endpoint.
- `schema.sql`: one-time fresh-install table definitions.
- `Dockerfile` and `docker-entrypoint.sh`: PHP 8.3 Apache container and `PORT` listener setup.
- `.htaccess`: Apache hardening and static asset cache/compression rules.
- `assets/`: supplied portfolio portraits.
- `manus-routes.json`: public page route manifest.
- `README.txt`, `AUDIT.md`, and `TODO.md`: deployment notes, audit evidence, and delivery outcomes.
- `app.config.ts`: project branding metadata using the supplied portfolio portrait asset.

## Design

### Considered styles

- **Existing neon editorial portfolio** (0.06): keep the supplied dark navy canvas, cyan accent, restrained cards, and technical feel.
- **Warm studio case-study** (0.04): introduce cream backgrounds and softer presentation.
- **Monochrome terminal resume** (0.08): reduce the interface to black, white, and utility typography.

### Chosen direction: existing neon editorial portfolio

- **Design Movement:** contemporary cyber-editorial portfolio: technical, spacious, and human rather than a generic dashboard.
- **Core Principles:** preserve the supplied identity; make the contact action obvious; communicate state immediately; keep owner functionality separate and protected.
- **Color Philosophy:** the deep navy base creates focus and trust, while the turquoise accent signals live technical craft and provides strong interaction contrast.
- **Layout Paradigm:** long-form narrative scrolling with asymmetric visual anchors, section transitions, and a focused contact close rather than a dense centered grid.
- **Signature Elements:** cyan edge glows, a soft radial atmosphere behind the contact section, and compact pill-shaped calls to action.
- **Interaction Philosophy:** form submission should feel direct and calm; validation appears beside the form, success confirms the stored action, and failures offer a clear fallback.
- **Animation:** keep the likely LCP hero text and portrait visible immediately; keep the decorative canvas static on mobile and cap desktop redraws at 30 frames per second; respect reduced-motion preferences.
- **Typography System:** retain the supplied system-font stack and existing hierarchy; do not reintroduce third-party font requests on the critical path.
- **Brand Essence:** an AI engineer who turns business requests into shipped LLM features; practical, technically fluent, responsive.
- **Brand Voice:** concise and personal. Example lines: “Send a message and I will reply quickly.” “Tell me what you are building; I will help turn it into a working feature.”
- **Wordmark & Logo:** retain the supplied wordmark treatment and existing portfolio identity; no new site logo is needed.
- **Signature Brand Color:** the existing turquoise/cyan accent is the ownable signal carried into the confirmation state.
