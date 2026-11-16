# Delivery outcomes

- [ ] **Publish the supplied portfolio to the requested address**
  - Deploy the uploaded PHP portfolio package to `https://mohamedprof-wjriznkh.manus.space/` and confirm the public root returns HTTP 200 with the portfolio page.
  - Leave any other website unchanged.

- [ ] **Recheck production mobile LCP at the serving layer**
  - Investigate the live root document's slow response/cache behavior; Lighthouse 12.8.2 mobile measured an 83/100 performance score and 3.4 s LCP, with the root document TTFB identified as the main delay.
  - Rerun the mobile audit after origin/proxy remediation. Do not treat lab TBT as INP or local test-server results as production field Core Web Vitals.

- [ ] **Expose the protected admin dashboard at /admin**
  - Make `https://mohamedprof-wjriznkh.manus.space/admin` open the existing password-protected owner dashboard rather than returning 404.
  - Keep login, logout, pagination, and inbox actions on `/admin` when visitors enter through that route; retain `/dashboard.php` and `/admin.php` as compatibility URLs.
  - Preserve CSRF protection, private downloads, `noindex`, and `no-store` behavior.

- [x] **Add dashboard statistics and contact filters**
  - Show total messages, unread messages, messages received today, and results matching current filters.
  - Filter by partial email and inclusive start/end dates using validated inputs and prepared SQL; keep filters across pagination, refresh, and inbox actions.
  - Verify both date boundaries, literal wildcard escaping, invalid-date handling, hostile search text, filtered pagination, `/admin` routing, and `/dashboard.php` compatibility with an isolated PHP integration suite.

- [x] **Mobile-first portfolio and Core Web Vitals safeguards**
  - Keep the public portfolio responsive from small mobile widths upward, with readable text, wrapping content, and touch targets of at least 44 CSS pixels for icon controls.
  - Use a compact rounded-square mobile menu button with a clear three-line icon; expose its open/closed state accessibly and close the menu on Escape, outside click, navigation, and desktop-width changes.
  - Render above-the-fold hero text and the portrait immediately; do not hide the likely LCP content behind an entrance animation.
  - Avoid third-party font requests on the critical path; keep the hero image sized, responsive, preloaded, and high priority.
  - Keep the decorative network canvas static on mobile and cap desktop redraws at 30 frames per second; continue to respect reduced-motion preferences.
  - Do not let the floating WhatsApp action cover the contact form while the visitor is using it.
  - Permit safe browser revalidation for the public HTML and use a finite cache lifetime for the mutable portrait asset.

- [x] **Safe, accessible contact submission**
  - Accept name, email, and message through POST; never place visitor-entered values in the URL.
  - Provide a usable server-rendered confirmation or error response for native/no-JavaScript form submissions, and a JSON response for the enhanced JavaScript form.
  - Validate scalar field values, email syntax, and server-side length limits; use Unicode-aware character counting and filename truncation.
  - Prevent duplicate submissions while a request is in progress, enforce the five-file/five-MiB limits in the browser and server, and show actionable errors.
  - After saving a message, confirm receipt and identify the email address to which the owner will reply.
  - Make the project-details checkbox reveal a file-upload input and describe attached files accurately.

- [x] **Durable uploads and concurrent-safe abuse limits**
  - Validate uploaded file count, size, MIME type, and filename on the server; persist accepted files durably with their message.
  - Serialize per-IP rate checks and inserts so concurrent requests cannot race past the five-message/ten-minute limit.
  - Keep email notifications best-effort; the database remains the source of truth.

- [x] **Complete, protected owner inbox**
  - Require the configured admin password before exposing visitor messages or download links.
  - Protect message read/delete actions and logout with CSRF validation; reject invalid authenticated POSTs.
  - Display true total and unread counts, and provide pagination so older than the newest 500 messages remain accessible.
  - Keep user-controlled output escaped and uploaded-file downloads private.
  - Make the dashboard wrap safely and retain usable action targets on mobile.

- [x] **Reliable database setup and readiness**
  - Keep `schema.sql` safe to apply to a fresh database and make automatic schema DDL opt-in rather than executing it in the normal request path.
  - Install the PHP `mbstring` extension in the supplied container so multibyte input limits work consistently.
  - Return a healthy status only when the database is available and both required tables exist.
  - Document the fresh-install and upgrade path and keep runtime credentials out of the public package.
