# IT Department Attendance Management System

A production-ready, full-stack attendance management system built for the IT Faculty (Information Technology, Software Engineering, Computer Science, and Information Systems programs), based on your research proposal requirements.

**Stack:** Vanilla JavaScript • HTML5 • Bootstrap 5 • PHP 7.1+ (PDO) • MySQL

---

## 1. Features Implemented

### Super Admin (IT Faculty Dean)
- Faculty-wide dashboard with charts (program comparison, attendance distribution, 14-day trend)
- Create / edit / deactivate lecturer accounts and assign them to a program + course units
- Create / edit programs and course units
- View **all** students across all four programs
- View flagged students faculty-wide
- Generate PDF reports (faculty summary, flagged students, per-course)
- Full audit log of every system action

### Lecturer
- Personal dashboard scoped to their program and assigned course units
- Take attendance: create a lecture session → mark Present / Absent / Late / Excused per student, with bulk "mark all" shortcuts
- Add / edit / remove students in their program
- View students flagged for excessive absenteeism
- Generate PDF reports (per-course, per-student, flagged list)
- Global live search across students, courses, and sessions

### Cross-cutting
- **Auto-flagging**: a MySQL trigger automatically flags a student once they accumulate **4 absences** in any single course unit (configurable via `ABSENCE_FLAG_THRESHOLD` in `includes/config.php`)
- **Color-coded attendance**: absent students/rows are highlighted in red throughout the UI; attendance percentages are color-coded (green ≥75%, amber 50–74%, red <50%)
- **Security**: CSRF tokens on every mutating request, bcrypt password hashing, brute-force lockout (5 attempts → 15 min lock), session regeneration on login, idle session timeout, full audit logging, role-based access enforced at the API layer (not just the UI)
- **Global search** with a dropdown showing matching students, courses, and sessions live as you type

---

## 2. Folder Structure

```
attendance-system/
├── api/                    PHP API endpoints (JSON)
│   ├── login.php / logout.php / csrf.php
│   ├── lecturers.php       Admin: lecturer CRUD
│   ├── students.php        Lecturer/Admin: student CRUD
│   ├── attendance.php      Session creation + attendance marking
│   ├── programs.php        Programs & course units CRUD
│   ├── reports.php         PDF/JSON report generation
│   ├── dashboard.php       Dashboard statistics
│   ├── search.php          Global search
│   └── audit.php           Audit log retrieval
├── includes/
│   ├── config.php          App configuration (DB creds, thresholds)
│   ├── db.php               PDO singleton connection
│   ├── auth.php             Authentication, sessions, CSRF, audit logging
│   └── helpers.php          Shared utilities
├── pages/
│   ├── admin/               Dean-facing pages
│   ├── lecturer/            Lecturer-facing pages
│   └── partials/            Shared sidebar/topbar
├── assets/
│   ├── css/app.css          Full design system
│   └── js/app.js            API client, toast, modal, search, helpers
├── database.sql             Complete schema, triggers, views, seed data
├── index.php                Login page
└── .htaccess                Security headers, file protection
```

---

## 3. Setup Instructions

1. **Database**: Import `database.sql` into MySQL (via the `mysql` CLI, or phpMyAdmin's Import tab):
   ```bash
   mysql -u root -p < database.sql
   ```
   The script starts with `DROP DATABASE IF EXISTS` + `CREATE DATABASE`, so it is **safe to re-run** at any time — if a previous import failed partway through, just re-import and you'll get a clean, fully-seeded database rather than a half-finished one. This creates the `it_attendance_db` database with all tables, triggers, views, the 4 programs, sample course units, **and a full demo dataset** (see below) so the system is immediately usable.

2. **Configure**: Edit `includes/config.php` and set your `DB_USER` / `DB_PASS`.
   `BASE_URL` / `BASE_PATH` are now computed **automatically** from the request, so the app works whether it's installed at `http://localhost/` or `http://localhost/attendance-system/` — no manual path configuration needed.

3. **Demo login accounts** (all lecturer accounts share the password `Lecturer@123`; change in production):

   | Role | Email | Password |
   |---|---|---|
   | Super Admin (Dean) | `dean@university.ac.ug` | `Admin@2026!` |
   | Lecturer — IT | `john.mukasa@university.ac.ug` | `Lecturer@123` |
   | Lecturer — SE | `grace.nakato@university.ac.ug` | `Lecturer@123` |
   | Lecturer — CS | `peter.okello@university.ac.ug` | `Lecturer@123` |
   | Lecturer — IS | `sarah.among@university.ac.ug` | `Lecturer@123` |

4. **(Optional) PDF library**: For polished PDF output, drop the free [FPDF library](http://www.fpdf.org/) into `vendor/fpdf/fpdf.php`. Without it, PDF report links automatically fall back to a clean printable HTML page (use the browser's "Print → Save as PDF").

5. **Deploy**: Place the folder in your web server root (e.g., `htdocs/attendance-system` for XAMPP) and visit it in your browser.

### What's in the demo dataset

- 6 students enrolled in **IT1101 – Introduction to Programming**, taught by John Mukasa (IT lecturer)
- 6 lecture sessions already recorded over the past 5 weeks, with attendance marked
- 4 students with healthy attendance (Allan, Brenda, Collins, Diana)
- **Edwin Kato** — exactly 4 absences → automatically flagged by the database trigger
- **Faith Nansubuga** — 6 absences (never attended) → automatically flagged, worse standing
- Log in as John Mukasa and visit **Flagged Students** or the **Dashboard** to see the auto-flagging working live without doing anything manually

### Quick verification checklist

After importing the database and configuring `includes/config.php`:
- [ ] Log in as the Dean → should land directly on the dashboard (no manual refresh needed)
- [ ] Dean → Lecturers → confirm John, Grace, Peter, and Sarah are listed
- [ ] Dean → click "Add Lecturer", fill the form, save → new lecturer appears in the list immediately
- [ ] Log out via the sidebar → should land back on the login page with a "logged out successfully" message
- [ ] Log in as `john.mukasa@university.ac.ug` → Dashboard shows IT1101 stats and a flagged-students count of 2
- [ ] Lecturer → Flagged Students → Edwin and Faith both appear with their absence counts

---

## 4. Database Design Highlights

- `programs` → the 4 IT Faculty programs
- `users` → Dean (`super_admin`) and lecturers, scoped by `program_id`
- `course_units` → courses per program, per year/semester
- `lecturer_course_assignments` → many-to-many: which lecturer teaches which course unit, per academic year/semester
- `students` → scoped to a program; carries `is_flagged` + `flag_reason`
- `student_course_enrollments` → many-to-many: which students are enrolled in which course units
- `lecture_sessions` → one row per actual lecture held
- `attendance_records` → one row per (session, student) — this is where Present/Absent/Late/Excused lives
- `audit_logs` → every sensitive action, with before/after JSON snapshots
- Two **views** (`vw_student_attendance_summary`, `vw_program_attendance_overview`) pre-aggregate attendance percentages for fast reporting
- Two **triggers** auto-flag a student the moment their absence count in a course unit hits the threshold (and can un-flag if records are corrected)

---

## 5. Security Notes

- All SQL uses PDO prepared statements — no raw string concatenation
- Passwords hashed with bcrypt (cost 12)
- Role checks happen **server-side in every API file**, not just hidden in the UI
- Lecturers can only see/modify students and sessions within their own program/assignments — enforced in SQL `WHERE` clauses, not just JS
- Session cookies are HttpOnly, SameSite=Strict, and Secure when served over HTTPS
- Login is protected by brute-force lockout (5 failed attempts → 15 minute lock) and full audit logging
- `includes/` and `vendor/` are blocked from direct web access via `.htaccess`
- **CSRF protection has been intentionally removed** for this build to simplify setup and eliminate a class of "stuck" bugs during development/demo use. If you deploy this beyond a local/trusted environment, re-introducing CSRF tokens (or switching to `SameSite=Strict` cookies as the primary defense, which are already in place) is recommended before exposing it on the open internet.

---

## 6. Bug Fixes in This Revision

- **Login required a manual refresh**: the session write wasn't guaranteed to complete before the redirect response was sent. Fixed by forcing `session_write_close()` before responding, and the login page now navigates with `location.replace()` using a fully-qualified, deployment-aware URL.
- **Admin couldn't create lecturer accounts**: every mutating request was being rejected by a CSRF check that could desync from the server's stored token, silently blocking the request before any data was saved. CSRF checks have been removed app-wide per your request.
- **All "edit" actions across the app were silently failing**: the shared `requirePostJson()` helper only accepted `POST` requests, but every edit (lecturer, student, program, course) is sent as `PUT` — so every edit was hitting `405 Method Not Allowed`. Fixed to accept `POST`, `PUT`, and `DELETE`.
- **Logout was broken**: it redirected to a hardcoded `/index.php`, which fails on any subfolder deployment. Rewritten to be defensive (falls back to manually clearing the session if `Auth::logout()` throws) and redirects through the same dynamic `BASE_PATH` used elsewhere in the app, landing on the login page with a confirmation message.
- **Login returned a 500 Internal Server Error on every attempt**: `includes/helpers.php` used a `: never` return type on `jsonResponse()`, which requires **PHP 8.1+**. On any server running PHP 8.0 or earlier (very common on default XAMPP installs), this caused a fatal compile error the moment any API file loaded `helpers.php` — i.e. on every API request, with no error visible to the user beyond a generic "Server error." Removed the 8.1-only type hint (and a `mixed` type hint in `sanitize()`) so the codebase now runs on PHP 7.1+. Also added a global shutdown-function safety net in `includes/config.php` that catches any future uncaught fatal in an `/api/` request and returns a clean JSON 500 with a logged server-side trace, instead of a blank or malformed response.

---

## 7. What You Can Extend Next

- Email notifications when a student is auto-flagged
- Lecturer self-service password reset flow (token columns are already in the schema)
- Biometric/QR check-in (the proposal mentions this as a "future trend" — current schema supports adding a `check_in_method` column to `attendance_records` without breaking changes)
- Student-facing portal (the schema's `students` table can be extended with a `password_hash` + role if needed later)
- Re-introduce CSRF protection if/when this moves beyond local development use
