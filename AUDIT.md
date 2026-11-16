# Mobile-first, Core Web Vitals, and logic audit

## Results

The public page was audited with Lighthouse 12.8.2 on a local PHP test server using mobile emulation and simulated throttling. The performance score was **100/100**. The key lab readings were:

| Metric | Result |
| --- | ---: |
| First Contentful Paint | 1.1 s |
| Largest Contentful Paint | 1.1 s |
| Cumulative Layout Shift | 0.00 |
| Total Blocking Time | 60 ms |
| Responsive image audit | Pass |
| Render-blocking resource audit | Pass |

Lighthouse lab data does not provide a representative field INP measurement. The 60 ms Total Blocking Time is only a lab responsiveness proxy; it is not INP. Real-user Core Web Vitals (CrUX/RUM) cannot be confirmed from this source ZIP without the deployed origin and real traffic. Treat the numbers above as a local build check, not a production guarantee.

## Changes made

The hero’s text and portrait no longer fade in, so the likely LCP content can paint immediately. Google Fonts were removed from the critical path in favor of system font stacks. The portrait is supplied at 320px and 640px with matching responsive preload hints; the 320px WebP is about 5.8 KiB versus 17 KiB for the original. Its layout box is explicitly square. Desktop canvas animation is capped at 30 fps, while mobile renders a single static frame. The WhatsApp floating action is hidden on mobile and while the contact section is in view on larger screens, avoiding content obstruction. The mobile menu is a 44px rounded-square control with a larger three-line icon, a clear open/close animation, synchronized accessible labels/state, and Escape/outside-click/link/breakpoint dismissal. The layout was inspected at 320px and 390px; Chromium reported no horizontal document overflow at 320px.

The contact form now uses POST even without JavaScript, so names, email addresses, message text, and selected files are not put in the URL. Native submissions receive a server-rendered HTML result; enhanced submissions continue to use JSON. Client-side checks match the five-file/five-MiB server limits, duplicate submits are prevented, malformed array values are rejected without PHP conversion warnings, and the project-details prompt matches the actual upload behavior. Success copy is corrected and includes the reply email.

Per-IP rate checks and inserts are serialized with a MySQL named lock. The admin inbox uses accurate all-message, unread, and today's counts; adds a matching-result count and unread count for active filters; paginates every message in pages of 50; retains the filter state across pagination and inbox actions; rejects invalid CSRF POSTs; and protects logout with CSRF. Character counting and filename truncation are Unicode-aware; the provided PHP container installs mbstring. Automatic schema DDL is opt-in through `AUTO_MIGRATE=1`, keeping it out of normal requests. The health endpoint returns 503 until the database and required tables are available.

## Validation performed

All PHP files passed `php -l`; the inline JavaScript passed `node --check`. The public page returned HTTP 200. Contact integration tests against a sandbox-local MariaDB verified a Unicode submission, stored text attachment, JSON confirmation, native HTML confirmation, and persistent records. After one accepted request, ten concurrent submissions from the same IP resulted in four more HTTP 200 responses and six HTTP 429 responses, leaving exactly five accepted for that IP. The authenticated dashboard correctly showed 65 total messages as pages 1–50 and 51–65, an invalid CSRF action returned 403, and valid CSRF logout redirected successfully. Health returned 200 with the test schema present and 503 without a usable database. A no-JavaScript oversized request was also rejected as HTTP 413 by PHP’s configured request limit.

The updated dashboard passed a separate PHP 8.3/SQLite HTTP integration suite using seeded messages: it verified global totals, unread/today statistics, partial email and inclusive start/end dates, literal wildcard escaping, prepared-query handling of hostile input, invalid-date feedback, filtered pagination, filter persistence in action URLs, clean `/admin` routing, and retained `/dashboard.php` behavior.

The local Lighthouse run used PHP’s built-in development server, which does not exercise Apache `.htaccess` compression. The supplied Dockerfile now enables Apache `mod_deflate` and `mod_expires`; verify gzip and cache headers again on the deployed Apache host.

## Live production follow-up (2026-11-16)

A fresh Lighthouse 12.8.2 mobile run against `https://mohamedprof-wjriznkh.manus.space/` scored **83/100**: FCP 2.7 s, LCP 3.4 s, CLS 0, and TBT 230 ms. Lighthouse identified the hero portrait as the LCP element, but its LCP breakdown attributed most delay to the root document's TTFB; the server-response-time audit measured about 1.53 s for that request. The image itself was discovered promptly and loaded in about 0.66 s. The live root response currently carries `Cache-Control: no-cache, no-store, must-revalidate`, while the portrait assets have a seven-day cache lifetime.

The live page also had no horizontal overflow at 320 CSS pixels. INP remains unmeasured: a Lighthouse lab run cannot substitute for real-user field data. The elevated production LCP appears to be dominated by the hosting/proxy response path rather than page rendering or image discovery, so it cannot be responsibly fixed by changing frontend source alone. Recheck the origin/cache configuration and rerun Lighthouse after the serving project is accessible; use CrUX/RUM for field Core Web Vitals.
