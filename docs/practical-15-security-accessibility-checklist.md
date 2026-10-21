# Practical 15 Security and Accessibility Checklist

Use this checklist before presenting or deploying Student Hub. Checked implementation items were verified from the current source and local integration tests; manual/browser and production-environment items still need the operator's sign-off.

## Security

- [x] Passwords are stored with `password_hash()` and checked with `password_verify()`.
- [x] Registration and event CRUD use prepared MySQL statements.
- [x] Login, registration, logout, and event mutations validate CSRF tokens.
- [x] Student and administrator routes enforce authenticated roles.
- [x] Session cookies use HttpOnly, SameSite, strict session mode, and Secure when HTTPS is active.
- [x] Event posters are size/type checked, get randomized names, and the upload directory blocks PHP execution.
- [x] Dynamic page/API content is escaped or inserted as text; API responses are not cached.
- [x] Integration tests verify invalid login, unauthenticated API denial, student-to-admin API denial, and invalid event CSRF denial.
- [ ] Configure a non-default database account/password for deployment; never run production with a MySQL root account.
- [ ] Enable HTTPS and confirm Secure cookies on the deployed host.
- [ ] Review Apache/PHP error logs and database backups/restore before production use.
- [ ] Confirm the production upload directory has least-privilege filesystem permissions and backups.
- [ ] Do not enable or advertise payment processing: the current fees page is a nonfunctional demonstration and collects no payment.

## Accessibility and responsive behavior

- [x] Pages declare a viewport and use shared responsive styling.
- [x] Form fields in the checked registration, feedback, profile, fees, event, and admin forms have labels or accessible names.
- [x] Event-calendar search/filter controls and event participation controls have accessible names.
- [x] Browser checks found no document-level horizontal overflow at the tested mobile and desktop viewport sizes.
- [ ] Manually verify keyboard-only navigation, visible focus, and menu operation on a desktop browser.
- [ ] Manually verify text and interactive-element contrast in light and dark modes.
- [ ] Test with a screen reader and confirm dynamic event loading, errors, and status messages are announced.
- [ ] Verify text zoom to 200% and confirm no content or control is obscured.

## Feature limitations to disclose

Course selection, exam schedule, feedback, student profile, and fee pages are presentation-only screens; their controls do not save data. The fees page does not process payments. Do not collect real student or financial information until those flows are implemented and reviewed.
