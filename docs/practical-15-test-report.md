# Practical 15 Integration Test Report

**Generated:** 2026-10-21  
**Target:** WDF Student Hub  
**Environment:** macOS, PHP 8.5, Node.js 24, Apache 2.4, MySQL (`student_hub`)  
**Application URL tested:** `http://127.0.0.1:8091/`  
**Test database:** local MySQL port `3337` (the test command must match the port configured for the web PHP process)

## Summary

| Check | Status | Evidence |
|---|---|---|
| Application startup / readiness | PASS | Apache served HTML and PHP pages at the test URL |
| Database connectivity | PASS | MySQLi connected and registration/event flows read and wrote data |
| Integration tests | PASS | `STUDENTHUB_DB_PORT=3337 node --test tests/*.test.mjs` — 4 passed, 0 failed, 0 skipped (exit code 0) |
| PHP syntax | PASS | `php -l` passed for login, registration, dashboards, and admin pages |
| Responsive/browser page checks | PASS | Shared CSS loaded on eight pages; no empty links, missing accessible control names, or horizontal document overflow at tested widths |
| Apache virtual host | NOT VERIFIED | Example configuration is supplied; local deployment used the already-running `127.0.0.1:8091` server |
| Manual accessibility checks | PARTIAL | Automated labels/viewport checks passed; keyboard, contrast, zoom, and screen-reader checks remain |
| GitHub submission | NOT PERFORMED | No remote push or commit was requested/authorized |

## Test journeys

1. Public portal routes render; internal navigation targets return a page or the expected authentication redirect.
2. Student registration writes the account and course registration; login reads the stored profile; admin API access is denied to a student; logout removes access.
3. Invalid login, unauthenticated admin dashboard/API access, and student access to the administrator event API are rejected.
4. Temporary administrator creates, reads, updates, and deletes an event through the JSON/Fetch API; invalid CSRF is denied. Unique test fixtures are removed after the test.

## Browser and accessibility evidence

- Pages checked: home, course registration, fees, exam schedule, faculty, feedback, profile, and event calendar.
- Requested viewport sizes: 390 × 900 and 1280 × 900. The browser reports CSS viewport widths of 312 and 1024 respectively due to its display scale; in both cases `document.documentElement.scrollWidth` matched `innerWidth`.
- Event-calendar filters had no programmatic labels on first inspection; accessible names were added and confirmed in the follow-up check.
- No screenshot files are included because the integrated browser session does not export screenshots into the repository. Capture clean screenshots locally for submission; avoid real account data.

## Known gaps

- The virtual-host sample has not been installed and tested on a separate XAMPP Apache instance.
- Keyboard-only navigation, screen-reader announcements, contrast, and 200% zoom need manual validation.
- Course selection, exam schedule, feedback, profile, and fees pages are presentation-only; they do not persist data or process payments.
- GitHub commit/push and live deployment were not performed.
