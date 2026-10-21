# CHARUSAT Student Hub

Student Hub is a semester-long Web Development Frameworks project for **ITUE203**, Faculty of Technology and Engineering, CHARUSAT (Semester 3, 2026–27). It brings together the HTML, CSS, JavaScript, PHP, and MySQL work from the practical list into a student portal with public information pages, student accounts, and administrator tools.

The course practical list is `Practical_List_ITUE203_WDF_2026_27.pdf`. This README describes the code currently in this repository; it does not claim that every suggested extension or separate submission artifact (such as wireframes or a GitHub link) is included.

## Project at a glance

- **Frontend:** HTML5, CSS3, responsive layouts, and browser JavaScript.
- **Backend:** PHP 8.1+ using MySQLi and prepared statements.
- **Database:** MySQL/MariaDB through XAMPP and phpMyAdmin.
- **Integration tests:** Node.js 20+ built-in test runner, with PHP CLI fixture helpers.
- **Local web server:** Apache (XAMPP).
- **No package install is required:** the test suite uses Node.js built-ins and the application has no Composer dependencies.

## What the portal includes

### Public and student pages

The `HTML/` directory contains the shared Student Hub experience:

| Page | Route | Purpose |
|---|---|---|
| Home | `home.html` | Portal landing page and student services |
| Registration | `register.php` | Validated student account and course registration |
| Login / logout | `login.php`, `logout.php` | Student and administrator authentication |
| Student dashboard | `dashboard.php` | Signed-in student's profile, course, and service summary |
| Course registration | `course_registration.html` | Course-selection demonstration page |
| Event calendar | `event_calendar.html` | Searchable, filterable event listings |
| Faculty | `about_faculty.html` | Faculty information |
| Exam schedule | `exam_schedule.html` | Exam-schedule demonstration page |
| Feedback | `feedback.html` | Feedback form demonstration page |
| Profile | `profile.html` | Student profile demonstration page |
| Fees | `fees_payment.html` | Fee information/payment demonstration page |

The shared stylesheet is `HTML/style.css`; `HTML/script.js` supplies shared browser interactions and event-calendar behavior.

### Administrator pages and APIs

- `admin_dashboard.php` — role-protected dashboard with student/admin/event/registration counts, recent registrations, and administrator activity.
- `admin_students.php` — student create/read/update, search, course/status filters, pagination, CSV export, soft delete, and restore.
- `admin_events.php` — administrator event manager.
- `admin_events_api.php` — authenticated JSON endpoint for event listing and asynchronous create/update/delete; `admin_events.js` connects it to the event-manager page.
- `events.php` — public JSON endpoint that returns events from MySQL for the event calendar.

Event posters are optional JPEG, PNG, or WebP files up to 3 MB. The server validates the uploaded image, gives it a random filename, and stores it under `HTML/uploads/event-posters/`. The upload directory's `.htaccess` prevents PHP execution there.

### Practical coverage from the ITUE203 list

The course PDF lists these 15 practicals. The table describes how their topics are represented in the current project, and calls out where the final implementation evolved from an earlier exercise.

| Practical | Topic | Current project evidence |
|---|---|---|
| 1 | Project initiation, requirements, sitemap, wireframe, GitHub setup | Student Hub scope, portal pages, and project structure are present. Standalone sitemap/wireframe deliverables are not included in this repository. |
| 2 | Semantic HTML and accessibility-ready pages | Linked HTML/PHP pages use document structure, headings, labelled forms, and accessible names for event controls. |
| 3 | Responsive UI with CSS, Grid, and Flexbox | A shared responsive theme is in `HTML/style.css`. |
| 4 | JavaScript DOM manipulation and interactions | `HTML/script.js` adds interactive navigation, theme preference, notices, modal, slider, FAQ, and event behavior. |
| 5 | Registration form and client-side validation | The registration form includes browser-side validation and feedback; PHP also validates submissions on the server. |
| 6 | JSON data, Fetch, search, and filtering | The event calendar loads JSON from the local `events.php` endpoint and supports search, category/status filters, sorting, and pagination. The final event data comes from MySQL rather than a third-party JSON service. `events.json` and `registrations.json` remain as project data artifacts. |
| 7 | PHP form processing and CSV/JSON storage | PHP processes and validates forms. The final registration workflow stores account data in MySQL; administrator student export produces CSV. The JSON files are retained project artifacts, not the live registration store. |
| 8 | Database schema and PHP connectivity | `database/schema.sql` defines the relational schema; `HTML/db.php` connects with MySQLi. |
| 9 | Secure registration and password hashing | Registration checks duplicate username/email/mobile values, hashes passwords, verifies CSRF, and saves the student, course registration, and audit event transactionally. |
| 10 | Login/logout, roles, sessions, timeout, remember-me | Authentication uses password verification, session ID regeneration, role-based redirects, a 30-minute idle timeout, and optional hashed remember-me tokens. |
| 11 | Student management CRUD | The admin student page supports CRUD, search, filters, pagination, CSV export, soft delete, and restore. |
| 12 | Event CRUD and poster validation | Administrators manage events and validated poster uploads; event changes are audited. |
| 13 | PHP JSON API and Fetch CRUD | The admin event interface uses Fetch and JSON for create/read/update/delete without a full-page reload. |
| 14 | Role-based admin dashboard and audit log | The dashboard is restricted to administrators, shows live database counts, and uses role-specific navigation and audit history. |
| 15 | Integration, tests, documentation, and local deployment | This README, the integration suite, SQL schema/migrations, and XAMPP virtual-host example support local setup and verification. GitHub push and deployment to another host are not performed by these files. |

**Implementation note:** The practical list mentions PDO as an extension option for Practical 8; this project uses **MySQLi** consistently.

## Repository layout

```text
WDF/
├── HTML/                         # Public pages, PHP application, CSS, JavaScript
│   ├── uploads/event-posters/    # Validated event posters (.htaccess protected)
│   ├── admin_*.php               # Administrator dashboard and management pages
│   ├── *_api.php                 # PHP JSON API endpoints
│   ├── auth.php, db.php          # Authentication and database helpers
│   └── style.css, script.js      # Shared styling and browser behavior
├── database/
│   ├── schema.sql                # Complete schema and initial courses/events
│   ├── migrate_practical_*.sql  # Incremental upgrades from earlier schemas
│   └── *_seed.sql                # Optional demonstration data
├── deployment/
│   └── httpd-vhosts.conf.example # XAMPP Apache virtual-host example
└── tests/                        # Node.js integration tests and PHP fixtures
```

## Run locally with XAMPP

### Requirements

- XAMPP with Apache and MySQL/MariaDB.
- PHP **8.1 or newer**, with MySQLi, `mysqlnd` (for `mysqli_stmt::get_result()`), and `fileinfo` enabled.
- Node.js **20 or newer** only if you want to run the integration tests.
- Apache `mod_rewrite` is not required by the application; `AllowOverride All` is used by the supplied virtual-host example to honor the upload-directory `.htaccess`.

### 1. Start Apache and MySQL

Start both services in the XAMPP Control Panel. This repository's web root is the `HTML/` folder.

For the default XAMPP document root, open:

```text
http://localhost/WDF/HTML/home.html
```

If Apache uses a non-default port, include it in the URL, for example `http://localhost:8080/WDF/HTML/home.html`.

### 2. Create the database

For a **new installation**, open `http://localhost/phpmyadmin`, choose **Import**, and import `database/schema.sql`. It creates the `student_hub` database, tables, constraints, initial courses, and sample events.

For an **existing earlier practical database**, back it up first. Apply only the migration scripts that have not already been applied, in order:

```text
database/migrate_practical_9.sql
database/migrate_practical_10.sql
database/migrate_practical_11.sql
database/migrate_practical_12.sql
database/migrate_practical_14.sql
```

The migration scripts upgrade earlier schemas; they are not a replacement for the complete fresh-install schema. Do not import them into a fresh `schema.sql` database or re-run an `ALTER TABLE` migration. If you are unsure which version the existing database uses, export a backup and inspect its tables before applying anything.

Optional demo data:

- `database/seed.sql` adds one sample student and a course registration.
- `database/practical_11_seed.sql` adds the Practical 11 demonstration student records and registrations.

Review seed scripts before importing them. They create demo data and are intended for local practice, not production accounts.

### 3. Configure the PHP database connection

`HTML/db.php` reads these environment variables:

| Variable | Default |
|---|---|
| `STUDENTHUB_DB_HOST` | `127.0.0.1` |
| `STUDENTHUB_DB_PORT` | `3306` |
| `STUDENTHUB_DB_NAME` | `student_hub` |
| `STUDENTHUB_DB_USER` | `root` |
| `STUDENTHUB_DB_PASSWORD` | Empty |

Set any overrides in the **Apache/PHP environment** and restart Apache. For example, if XAMPP MySQL listens on port `3337`, configure `STUDENTHUB_DB_PORT=3337` for Apache PHP as well as for any CLI test command. Import the schema into that same MySQL instance. The root/empty-password defaults are for a fresh local XAMPP setup only; use a dedicated database account and a strong secret outside local development. Never commit credentials.

### 4. Optional Apache virtual host

The example `deployment/httpd-vhosts.conf.example` points to the path used in the author's XAMPP installation. Update both `DocumentRoot` and the matching `<Directory>` path for your machine; the document root must point to this project's `HTML/` directory.

1. Add the edited virtual-host block to XAMPP's `etc/extra/httpd-vhosts.conf`.
2. In `etc/httpd.conf`, enable the `conf/extra/httpd-vhosts.conf` include if it is commented out.
3. Add `127.0.0.1 studenthub.local` to the system hosts file (`/etc/hosts` on macOS/Linux; the Windows hosts file on Windows).
4. Restart Apache and open `http://studenthub.local/home.html`. If Apache uses a non-default port, configure the virtual host accordingly and include the port in the URL.

Keep `HTML/uploads/event-posters/` writable by Apache for event poster uploads and preserve its `.htaccess` protection.

## Create an administrator account

1. Open `register.php` in the local site and register an account.
2. In phpMyAdmin, select the `student_hub` database and promote that local test account:

```sql
UPDATE students
SET role = 'admin'
WHERE username = 'your_username'
  AND deleted_at IS NULL;
```

3. Sign in at `login.php`; administrator accounts are redirected to `admin_dashboard.php`.

Use a unique local account for practice. Do not promote an account or use real student information on a publicly accessible installation.

## Run the integration tests

The suite uses Node's built-in test runner and invokes PHP CLI for narrowly scoped database fixtures. Start Apache and MySQL, import the schema, and point `STUDENTHUB_BASE_URL` to the base URL of the running `HTML/` app (include a trailing slash):

```sh
STUDENTHUB_BASE_URL=http://localhost/WDF/HTML/ \
STUDENTHUB_DB_PORT=3306 \
node --test tests/*.test.mjs
```

With the example virtual host:

```sh
STUDENTHUB_BASE_URL=http://studenthub.local/ \
STUDENTHUB_DB_PORT=3306 \
node --test tests/*.test.mjs
```

Set any other `STUDENTHUB_DB_*` values and `PHP_BINARY` in the shell environment when the PHP CLI does not use the defaults. The test process passes these variables to the PHP fixture helper. Use the same database connection settings as the running app. Tests create unique temporary student/admin accounts and events, then clean up their records; running them against a disposable local database is recommended.

The suite covers public-page rendering and local links, registration and login, role-protected access and logout, and administrator event API CRUD and CSRF behavior. The default test URL in `tests/helpers.mjs` is `http://127.0.0.1:8091/`; set `STUDENTHUB_BASE_URL` when your local server uses another address or path.

Useful checks:

```sh
# Run every integration test
STUDENTHUB_BASE_URL=http://127.0.0.1:8091/ node --test tests/*.test.mjs

# Check PHP syntax (run from the project root)
find HTML tests -name '*.php' -print0 | xargs -0 -n1 php -l

# Check the test JavaScript syntax
node --check tests/helpers.mjs
node --check tests/public-pages.test.mjs
node --check tests/auth-registration.test.mjs
node --check tests/admin-event-crud.test.mjs
```

Adjust `STUDENTHUB_DB_PORT` in the test command if your MySQL server uses a different port. For example, use `STUDENTHUB_DB_PORT=3337` when that is the port configured for the application.

## Database overview

`database/schema.sql` creates these tables:

- `students` — student/admin accounts, password hashes, role, and soft-delete state.
- `remember_tokens` — hashed remember-me tokens and expiry.
- `courses` and `registrations` — course catalogue and student/year registrations.
- `events` — event details, poster path, status, and update timestamp.
- `registration_audit`, `event_audit`, and `audit_logs` — registration, event, and administrator activity records.

To inspect a fresh database in phpMyAdmin:

```sql
USE student_hub;
SHOW TABLES;
SELECT course_code, course_name FROM courses ORDER BY course_code;
SELECT event_id, title, category, event_date, status
FROM events ORDER BY event_date;
```

## Security and data limitations

The implemented account and administrator flows use password hashing/verification, prepared statements, server-side validation, CSRF tokens, role checks, session ID regeneration, and HTML escaping. Session cookies use `HttpOnly` and `SameSite=Lax`; `Secure` is enabled when HTTPS is active. Event poster validation includes file size and detected image type.

This is a learning project, not a production student-information or payment system:

- Course registration, exams, feedback, profile, and fees pages are demonstration UI and do not save data. The fees page does **not** process payments.
- A locally configured XAMPP virtual host is not automatically an HTTPS production deployment. Use HTTPS, least-privilege database credentials, protected backups, and reviewed file permissions before any real deployment.
- Automated tests do not replace manual keyboard, screen-reader, contrast, and zoom checks.
- Do not store real student or financial data in a public or shared development environment.

## Practical 15 submission checklist

For a course submission, review the practical's requested deliverables separately: repository/GitHub link, SQL dump, README, test evidence, screenshots, and project demonstration. This repository provides the application code, schema/migrations, local virtual-host example, and integration tests; create and verify any required screenshots and repository submission for your own course hand-in. Review `git status` and inspect staged files before committing. Do not commit passwords, local environment settings, or personal data.
