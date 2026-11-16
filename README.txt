PORTFOLIO + CONTACT DATABASE (PHP 8.3+, MySQL/MariaDB)

WHAT IS INCLUDED
- A responsive portfolio page with a mobile-first layout, system-font rendering, reduced-motion support, a lightweight decorative canvas, and 320px/640px responsive portrait sources.
- A contact form that validates name, email, and message, stores submissions, and confirms the email address the visitor provided.
- Optional project-file uploads: up to 5 files, 5 MiB each. Server-side MIME checks remain authoritative; accepted files are stored with the message in message_files.
- A protected owner dashboard at /admin, with /dashboard.php and /admin.php retained as compatibility URLs. It shows total, unread, today's, and matching-message counts; supports partial email and inclusive date-range filters; preserves filters across pagination and inbox actions; and keeps CSRF-protected actions and private downloads.
- A database-aware health endpoint at /health.php. It returns 503 until the database and required tables are available.

DATABASE SETUP
1. Create a MySQL/MariaDB database and a runtime user with only the database privileges the app needs.
2. For a fresh install, apply schema.sql once before the service is marked ready.
3. For an upgrade from a release that created its schema automatically, the schema is already present. If an older messages table is missing project_details or message_files, apply the matching changes from schema.sql or temporarily set AUTO_MIGRATE=1 and make one database-backed request, then turn AUTO_MIGRATE off again.
4. AUTO_MIGRATE is off by default. This avoids CREATE/ALTER TABLE operations in normal contact, download, dashboard, and health requests.

MANAGED DEPLOYMENT
The included Dockerfile runs PHP 8.3 + Apache, installs pdo_mysql and mbstring, enables gzip/expiration modules, listens on PORT, and sets the upload limits for five 5 MiB files. Configure these protected runtime values in the hosting environment:
- DATABASE_URL: mysql://USER:PASSWORD@HOST:PORT/DATABASE
- ADMIN_HASH: output of php -r "echo password_hash('YourStrongPassword', PASSWORD_DEFAULT);"
- ADMIN_PASSWORD: optional exact owner password stored only in the secret manager
- NOTIFY_EMAIL: optional address for best-effort mail notifications
- AUTO_MIGRATE: optional; keep unset/0 in normal operation, or set to 1 only for a one-time schema bootstrap/upgrade

Set /health.php as the health path. It verifies database connectivity and both required tables; it does not expose database error details to visitors. The database must be provisioned and schema.sql applied before a healthy status is expected.

MANUAL PHP HOSTING
1. Create a MySQL/MariaDB database and apply schema.sql once using the host’s database tool.
2. Set DB_HOST, DB_NAME, DB_USER, DB_PASS, NOTIFY_EMAIL, and ADMIN_HASH in the host environment (or config.php where appropriate).
3. Upload all project files to the web root, keeping .htaccess and schema.sql.
4. Enable HTTPS and test /health.php; it should return HTTP 200 with {"ok":true} once the database is ready.
5. Submit a test message and review it at /admin. Keep the dashboard password private.

SECURITY AND OPERATIONS
- The public portfolio does not require JavaScript to avoid exposing form data in a URL: native form submissions use POST and receive a small HTML confirmation/error page. The enhanced form uses fetch and a JSON response.
- Dashboard login requires ADMIN_PASSWORD or ADMIN_HASH. Sessions use HttpOnly cookies and Secure/SameSite settings on HTTPS. Do not share the password.
- Read/delete/logout actions use CSRF validation. Uploaded files are downloadable only from an authenticated dashboard session.
- Per-IP submission limiting is serialized with a MySQL named lock so concurrent requests cannot race past the limit.
- Email notification is best-effort; the database remains the source of truth.
