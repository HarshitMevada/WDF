# WDF Student Hub

## MySQL setup with XAMPP and phpMyAdmin

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open phpMyAdmin at `http://localhost/phpmyadmin`.
3. Select the **Import** tab, choose `database/schema.sql`, and click **Import**. The script creates the `student_hub` database, its tables and constraints, and the initial course and event rows.
   - If your database predates Practical 9, import `database/migrate_practical_9.sql`, `database/migrate_practical_10.sql`, `database/migrate_practical_11.sql`, and `database/migrate_practical_12.sql`, in that order, once each.
   - If you already completed Practical 11, import only `database/migrate_practical_12.sql` once. It adds event posters, registration status, and event audit logging while retaining existing events.
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
