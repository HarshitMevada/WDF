# WDF Student Hub

## MySQL setup with XAMPP and phpMyAdmin

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open phpMyAdmin at `http://localhost/phpmyadmin`.
3. Select the **Import** tab, choose `database/schema.sql`, and click **Import**. The script creates the `student_hub` database, its tables and constraints, and the initial course and event rows.
   - If your database predates Practical 9, import `database/migrate_practical_9.sql`, `database/migrate_practical_10.sql`, `database/migrate_practical_11.sql`, `database/migrate_practical_12.sql`, and `database/migrate_practical_14.sql`, in that order, once each.
   - If you already completed Practical 11, import only `database/migrate_practical_12.sql` once. It adds event posters, registration status, and event audit logging while retaining existing events.
   - If you already completed Practicals 12 and 13, import only `database/migrate_practical_14.sql` once to add the admin activity log.
   - Import `database/practical_11_seed.sql` to add 20 demo student accounts and course registrations.
4. Optionally import `database/seed.sql` to add a sample student and registration for dashboard testing.
5. Serve the app through Apache and open `http://localhost/WDF/HTML/`.

The application connects through MySQLi in `HTML/db.php`. Its defaults match a standard local XAMPP MySQL installation:

- Host: `127.0.0.1`
- Default port: `3306`
- Database: `student_hub`
- Username: `root`
- Password: empty

To use different credentials or a different MySQL port, set `STUDENTHUB_DB_HOST`, `STUDENTHUB_DB_PORT`, `STUDENTHUB_DB_NAME`, `STUDENTHUB_DB_USER`, and `STUDENTHUB_DB_PASSWORD` in the Apache/PHP environment, then restart Apache. Import SQL into the same MySQL instance/port used by the PHP app.

## Verify the imported database

Select the `student_hub` database in phpMyAdmin and run:

```sql
SHOW TABLES;
SELECT course_code, course_name FROM courses ORDER BY course_code;
SELECT event_id, title, category, event_date, status, poster_path FROM events ORDER BY event_date;
SELECT s.full_name, s.email, r.course_code, r.study_year
FROM students AS s
JOIN registrations AS r ON r.student_id = s.student_id
ORDER BY r.registration_id DESC;
```

`HTML/register.php` validates input on the server, checks duplicate usernames/emails/mobile numbers, verifies a CSRF token, hashes passwords with `password_hash()`, and inserts the student, course registration, and audit entry in one transaction using prepared MySQLi statements. `HTML/dashboard.php` reads the latest registration, and `HTML/events.php` returns database events to the event calendar.

## Practical 10: Login and roles

Open `HTML/login.php` and sign in with the username or email and password used during registration. Student dashboards require login; logging out or being idle for 30 minutes ends the session. The optional **Remember me** checkbox uses a random cookie token whose hash is stored in MySQL and expires after 30 days.

New accounts have the `student` role. To make an account an administrator, first register it, then run this query in phpMyAdmin:

```sql
UPDATE students SET role = 'admin' WHERE username = 'your_username';
```

Administrators are redirected to `HTML/admin_dashboard.php`; students are redirected to `HTML/dashboard.php`. The `last_login_at` column records the latest successful password login.

## Practical 11: Student management

From the admin dashboard, open **Manage Students** to add, view, edit, and search student records. Search covers name, email, username, and course; course/status filters and pagination are included. Deleting a student is confirmed and soft-deletes the account, preserving registrations and audit history; use the Deleted filter to restore it. The Export CSV link respects the current filters.

The Practical 11 seed accounts use usernames `seed_student_01` through `seed_student_20` and the shared password `StudentDemo1!`. They are demonstration accounts only; change or remove them before using real student data.

## Practical 12: Event management

After importing the Practical 12 migration, sign in with an administrator account and choose **Manage Events** on the admin dashboard (or open `HTML/admin_events.php`). Administrators can create, edit, search, filter, and delete events. Event changes are recorded in `event_audit`.

Posters are optional JPEG, PNG, or WebP images up to 3 MB. The server checks image content, assigns a random filename, and stores the file in `HTML/uploads/event-posters/`; the directory blocks PHP execution. Make sure PHP's `fileinfo` extension is enabled and the upload directory is writable by Apache. The public calendar at `HTML/event_calendar.html` displays posters and supports category and Open/Closed status filters; closed events have participation disabled.

## Practical 13: JSON API and asynchronous event CRUD

The admin event page uses Fetch requests to `HTML/admin_events_api.php`; event create, edit, and delete operations update the list without a page reload. `GET` returns the events as JSON and accepts `q`, `category`, and `status` filters. `POST` accepts a CSRF-protected multipart form with the `action` value `create`, `update`, or `delete`; it returns a JSON message and saved event data. The admin search waits 300 ms after typing before requesting filtered results.

The API uses appropriate HTTP status codes for success, invalid input, missing events, unauthorized access, and server/database errors. The admin page displays loading, success, and error feedback and preserves input on failed submissions. Validate it using the browser Network tab while searching and performing CRUD; the demo data in `events` is used by both the admin interface and the public calendar.

## Practical 14: Role-based admin dashboard and audit log

After completing the previous migrations, import `database/migrate_practical_14.sql` once to create the `audit_logs` table. The admin-only dashboard loads active student and admin counts, event count, course registration count, latest registrations, and recent admin activity from MySQL. Navigation links are selected from the authenticated user's role. Admin sign-ins, sign-outs, student create/update/delete/restore actions, and event create/update/delete actions are recorded in `audit_logs`; event changes also remain in the Practical 12 `event_audit` table.

## Practical 15: Final integration and local deployment

### Apache virtual host (XAMPP)

1. Copy `deployment/httpd-vhosts.conf.example` into XAMPP's `etc/extra/httpd-vhosts.conf`. Update both `DocumentRoot` and `<Directory>` to the absolute path of this project's `HTML` directory.
2. In XAMPP's `etc/httpd.conf`, ensure the `conf/extra/httpd-vhosts.conf` include is enabled.
3. Add `127.0.0.1 studenthub.local` to `/etc/hosts` (macOS/Linux) or the Windows hosts file.
4. Restart Apache and open `http://studenthub.local/`. If Apache listens on a non-default port, update the virtual-host port and include it in the URL (for example, `http://studenthub.local:8091/`).
5. Start MySQL and import `database/schema.sql` into phpMyAdmin if the database has not been initialized. For an existing Practical 13 database, import `database/migrate_practical_14.sql` once. Configure `STUDENTHUB_DB_*` variables in Apache if your MySQL connection differs from the defaults in `HTML/db.php`; do not commit database passwords.

The virtual-host example points at the current XAMPP installation path; change it for other installations. Keep the event poster upload directory writable by Apache and preserve its `.htaccess` protection. Serve over HTTPS before using real student data.

### Integration tests

Node.js 20+ and PHP with MySQLi are required. Start Apache and MySQL, ensure the database is populated, and run:

```sh
STUDENTHUB_BASE_URL=http://studenthub.local/ node --test tests/*.test.mjs
```

The suite verifies public-page rendering and local navigation, registration-to-login and protected-page behavior, student/admin authorization, and Fetch-based event create/read/update/delete. It creates uniquely named test accounts and events and removes them afterward. Set the same `STUDENTHUB_DB_HOST`, `STUDENTHUB_DB_PORT`, `STUDENTHUB_DB_NAME`, and `STUDENTHUB_DB_USER` values for the test command that Apache/PHP uses; set `STUDENTHUB_DB_PASSWORD` when required. For example, if the web PHP process uses MySQL port `3337`, run `STUDENTHUB_DB_PORT=3337` with the test command so fixture setup and teardown use that same database.

`docs/practical-15-test-report.md` records the executed checks and environment-specific gaps. `docs/practical-15-security-accessibility-checklist.md` separates verified implementation controls from manual checks needed before a real deployment.

### Scope and submission notes

- Student registration, role-protected login/dashboard, student administration, event CRUD/API, and audit logging use the MySQL-backed PHP application.
- Course selection, exam schedule, feedback, student profile, and fee pages are currently front-end demonstration pages; they do not persist changes or process payments. Do not enter real payment or personal data into these placeholder screens.
- The repository contains the SQL schema and migration scripts. Run `git status` and review staged files before committing or pushing to your own GitHub repository; credentials, local uploads, and generated personal data should not be submitted.
- Capture screenshots of the home page, student/admin dashboards, event calendar, and responsive mobile layout for the lab submission. Do not include real account details or personal data.
