# Technical Design Document (TDD)
## CoachFee – Smart Coaching Institute Fee Management System

**Version:** 1.0.0  
**Date:** November 2026  
**Live URL:** `http://localhost/CoachFee/` (local XAMPP deployment)  
**GitHub Repository:** *(make public before submission)*

---

## 1. Project Overview

CoachFee is a full-stack web application for coaching institutes to manage student fee records, instalments, payments, refunds and reminders. A separate parent portal gives parents read-only visibility of their child's fee status and the ability to make simulated payments.

### Core Goals
- Replace manual ledgers with a centralised, role-secure system
- Provide real-time fee health visibility to management
- Enable parents to self-serve basic fee information
- Identify at-risk students early using payment behaviour analysis

---

## 2. Architecture

### Technology Stack

| Layer | Technology | Rationale |
|---|---|---|
| Frontend | HTML5, CSS3, Vanilla JS | Lightweight, no build step, full control |
| UI Framework | Custom claymorphism CSS | Premium SaaS look without Bootstrap bloat |
| Charts | Chart.js 4.4 (CDN) | Lightweight, beautiful, well-documented |
| Icons | Lucide Icons (CDN) | Consistent, clean, SVG-based |
| Fonts | Google Fonts – Poppins & Inter | Professional typography |
| Backend | PHP 8.0+ | Matches XAMPP requirement, mature ecosystem |
| Database | MySQL / MariaDB 10.4 | Relational integrity for financial data |
| Session | PHP native sessions + CSRF | Secure, server-side, no external deps |

### Project Structure
```
CoachFee/
├── index.php                    # Entry → redirect by role
├── config/
│   ├── config.php               # DB, app settings, refund policy
│   ├── database.php             # PDO singleton
│   └── session.php              # Auth, CSRF, helpers
├── src/
│   ├── auth/                    # Login, parent login, logout
│   ├── staff/                   # All 10 staff screens
│   └── parent/                  # All 6 parent screens
├── assets/
│   ├── css/style.css            # 850-line design system
│   └── js/app.js                # Sidebar, modals, toasts, charts
├── templates/
│   ├── header.php               # Staff shell (sidebar + header)
│   ├── parent_header.php        # Parent shell
│   └── footer.php               # Shared footer + script injection
└── database/
    ├── schema.sql               # Full DDL
    ├── seed.sql                 # Sample data
    └── setup.php                # One-click installer
```

### Request Flow
```
Browser → Apache → PHP page → config/session.php (auth check) → 
Database query (PDO, prepared statements) → HTML response
```
No MVC framework is used. Files are structured in a flat-route pattern matching the URL, keeping it simple and familiar for PHP developers.

---

## 3. Database Schema & Relationships

### Entity Relationship Overview

```
users (staff/parent)
  └── parent_students ──→ students
                              └── fee_structures
                                      └── instalments
                                              └── payments
students ──→ reminders
students ──→ refund_records
students ──→ risk_analysis
students ──→ batches
```

### Table Descriptions

| Table | Purpose | Key Columns |
|---|---|---|
| `users` | All system accounts | id, email, password_hash, role, otp, otp_expires_at |
| `students` | Student registry | id, student_id (CF-YYYY-NNN), name, batch_id, status |
| `batches` | Courses/batches | id, name, course_name, total_fee, start_date, end_date |
| `parent_students` | Parent↔Student link (M:M) | parent_id, student_id, relation |
| `fee_structures` | Fee plan per student | id, student_id, total_fee, discount, net_fee |
| `instalments` | Individual payment milestones | id, fee_structure_id, instalment_no, amount, due_date, status |
| `payments` | Actual payment records | id, instalment_id, amount, method, receipt_number, is_simulated |
| `reminders` | Fee reminder log | id, student_id, type, channel, status, sent_at |
| `refund_records` | Refund calculations | id, student_id, original_fee, refund_amount, policy_snapshot |
| `risk_analysis` | Computed risk scores | id, student_id, risk_level, risk_score, reasons (JSON) |
| `audit_log` | Action audit trail | id, user_id, action, entity, entity_id, details (JSON) |

### Key Design Decisions
- **Financial operations** use `DECIMAL(10,2)` — never `FLOAT` — to avoid floating-point rounding errors
- **Instalment status** (`pending`, `paid`, `overdue`, `partial`) is updated atomically via transaction
- **Receipt numbers** are globally unique, generated server-side with `random_bytes`
- **JSON columns** (`reasons`, `evidence` in `risk_analysis`) allow flexible data without extra joins
- **Soft delete** pattern — students marked `inactive` rather than deleted, preserving financial history

---

## 4. Main Workflows

### 4.1 Staff Login
1. POST email + password + CSRF token to `src/auth/login.php`
2. Server fetches user by email (role = 'staff'), validates `password_verify()`
3. Session created (`session_regenerate_id(true)`), redirect to dashboard
4. All subsequent pages call `require_role('staff')` — unauthorized access redirects immediately

### 4.2 Parent Login + OTP
1. POST email + password → credentials validated
2. OTP generated (demo: always `123456`), stored with 5-min expiry, session step set to `otp`
3. Parent enters OTP → matched against DB record + expiry check
4. On success: OTP cleared from DB, session created, redirect to parent dashboard

### 4.3 Add Student + Fee Setup
1. Staff fills form: personal details, batch, enrolment date
2. Backend auto-generates `student_id` = `CF-{year}-{seq}` 
3. Fee structure inserted: `net_fee = total_fee - discount`
4. Instalments created: amount split evenly, last instalment absorbs rounding remainder
5. Parent accounts linked via `parent_students` join table

### 4.4 Record Payment
1. Staff selects student → instalment → enters amount
2. Backend validates: instalment belongs to student, not already `paid`, amount ≤ instalment amount
3. Transaction: INSERT payment → UPDATE instalment status (`paid` or `partial`)
4. Receipt number generated, audit log entry written
5. Redirect to printable receipt page

### 4.5 Simulated Parent Payment
1. Parent selects pending instalment, enters dummy card details
2. Card fields validated (format-only, not real), duplicate check runs
3. Same backend transaction as staff payment recording
4. `is_simulated = 1` flagged, `method = 'online_sim'`
5. Receipt shows prominent "SIMULATED PAYMENT" notice

### 4.6 Fee Reminder Flow
1. Staff clicks "Auto-Generate" → system queries overdue + upcoming-7-day instalments
2. Reminders created in `reminders` table with draft message
3. Staff can send individual or batch → status updated to `sent`, `sent_at` timestamped
4. Demo mode: no actual SMS/email delivered — UI shows demo notice

### 4.7 Refund Calculation
1. Staff selects student → fee and paid amounts pre-filled from DB
2. `days_since` = `(today - enrolment_date)` in days
3. Policy bracket matched → refund % applied to paid amount
4. Result displayed with full breakdown; optionally saved to `refund_records`

---

## 5. AI Risk Analysis Methodology

> **IMPORTANT DISCLOSURE:** This is a **rule-based scoring algorithm**, NOT a trained machine-learning model. The term "AI" refers to automated intelligence logic, not statistical modelling. All results require human review before action.

### Algorithm

Each student receives a **risk score (0–100)** computed from four weighted signals:

| Signal | Weight | Computation |
|---|---|---|
| Overdue instalment ratio | 35% | `(overdue_count / total_count) × 35` |
| Outstanding balance ratio | 25% | `min(1, outstanding / net_fee) × 25` |
| Average payment delay (days) | 25% | `min(25, avg_delay_days / 90 × 25)` |
| Student status | 15% | `inactive=10pts, dropout=15pts` |

### Classification Thresholds
```
Score ≥ 70  → HIGH RISK   (urgent action)
Score ≥ 40  → MEDIUM RISK (monitor + remind)
Score  < 40 → LOW RISK    (no action)
Score = N/A → INSUFFICIENT DATA (< 2 instalments)
```

### Limitations
- Scores reflect only payment behaviour, not academic performance or student intent
- Average delay is computed from paid instalments only — students with no payments have zero delay signal
- Minimum 2 instalments required; new students always return "Insufficient Data"
- Scores are point-in-time snapshots; recompute regularly for accuracy
- **This model must never be used as the sole basis for any administrative decision**

---

## 6. UI/UX and Design Decisions

### Design System
- **Claymorphism:** Soft shadows (`0 4px 16px rgba(36,50,74,.10)`), rounded cards (`border-radius: 18–24px`), subtle depth
- **Colour palette:** Deep navy `#24324A` (primary), soft teal `#65B8A6` (accent), status colours used semantically
- **Typography:** Poppins (headings/nav), Inter (data tables) for maximum readability
- **Responsive:** CSS Grid + media queries for 3 breakpoints; mobile sidebar collapses to hamburger overlay

### Accessibility
- Semantic HTML (`<nav>`, `<header>`, `<main>`)
- ARIA labels on icon-only buttons
- Keyboard-navigable modals (ESC to close)
- `prefers-reduced-motion` media query disables all animations for users who opt out
- Contrast ratios ≥ 4.5:1 on all primary text

### UX Patterns
- **Empty states** on all tables/lists (not blank screens)
- **Loading states** on form submit buttons
- **Toast notifications** for success/error feedback
- **Confirmation dialogs** for destructive actions (deactivate student, send all reminders)
- **Simulated payment banner** prominently displayed wherever demo transactions occur

---

## 7. Security Measures

| Measure | Implementation |
|---|---|
| Password hashing | `password_hash()` with `PASSWORD_BCRYPT`, cost=12 |
| SQL injection prevention | PDO prepared statements throughout — zero raw interpolation |
| CSRF protection | Per-session token, verified on all POST requests |
| Session security | `httponly=true`, `samesite=Lax`, ID regenerated on login + every 30 min |
| Role enforcement | `require_role()` called at top of every protected page |
| Parent data isolation | Parent pages query only `student_id` values linked via `parent_students` |
| XSS prevention | All output escaped with `htmlspecialchars()` via `h()` helper |
| Input validation | Server-side validation on all form fields — JS validation is UI-only |
| Duplicate payment prevention | Status checked before INSERT; paid instalments cannot be re-paid |
| Financial atomicity | Payment + status update wrapped in `beginTransaction()` / `commit()` |

---

## 8. Assumptions Made

1. **Refund Policy:** Sliding-scale policy (0–90+ days since enrolment) is a sample assumption. Labelled clearly as `Standard Sliding-Scale Policy (Assumption v1.0)`. Real policy must be configured in `config/config.php`.

2. **OTP Delivery:** No real SMS/email provider is configured. All OTPs are `123456` in demo mode. Set `USE_DEMO_OTP = false` and integrate an SMS API for production.

3. **Payment Gateway:** No real payment processing. All online payments are simulated. The `is_simulated` flag distinguishes these from real cash/bank payments.

4. **Single currency:** INR (₹) only. Currency formatting uses `number_format()` for consistency.

5. **Receipt generation:** Receipts are HTML-based print pages — not PDF. A PDF library (e.g. TCPDF) would be needed for production receipt archival.

6. **Risk analysis cadence:** Risk scores must be manually recomputed via the "Re-Compute All" button. Automatic scheduled recomputation (cron) is not implemented.

7. **Student ID format:** Auto-generated as `CF-{year}-{3-digit-seq}` based on year and total student count in that year.

8. **Email notifications:** No email is sent for any event. Reminders are logged to the `reminders` table with `channel = 'demo'`.

---

## 9. Features Intentionally Not Built

| Feature | Reason |
|---|---|
| PDF receipt download | Requires third-party PHP library; HTML print serves the demo purpose |
| Real SMS/email delivery | Requires paid provider; demo OTP + reminder logging covers the requirement |
| Real payment gateway | Out of scope; Requirement 6F explicitly calls for simulation |
| Multi-currency support | Out of scope for single-institute use |
| Student photo upload | File upload system adds complexity; not required for core workflow |
| Scheduled cron reminders | Cron setup is environment-specific; manual trigger covers demo |
| Advanced ML risk model | Explicitly disallowed by Requirement 4I — rule-based is intentional |
| Two-factor auth for staff | Demo scope; session auth is sufficient for local review |

---

## 10. Testing Results

> All tests were executed against the live XAMPP instance.

| Test | Method | Result |
|---|---|---|
| Staff login with correct credentials | HTTP POST via PowerShell | ✅ Redirects to dashboard |
| Staff login with wrong password | Manual browser test | ✅ Shows error message, no session created |
| Parent login + OTP flow | Manual browser test | ✅ Two-step flow works, OTP=123456 accepted |
| Unauthorised parent accessing staff page | Direct URL | ✅ Redirected to parent dashboard |
| Dashboard loads with live DB data | HTTP GET | ✅ 200 OK, charts populated |
| Students page loads | HTTP GET | ✅ 200 OK, 10 students listed |
| Payments page loads | HTTP GET | ✅ 200 OK, 18 payments listed |
| Risk analysis page loads | HTTP GET | ✅ 200 OK, 10 risk records displayed |
| Reminders page loads | HTTP GET | ✅ 200 OK, 6 reminders listed |
| Refund calculator page loads | HTTP GET | ✅ 200 OK, policy table shown |
| Batches page loads | HTTP GET | ✅ 200 OK, 5 batches shown |
| Add student form + fee structure | Manual browser | ✅ Student created, instalments generated |
| Record payment (cash) | Manual browser | ✅ Payment saved, instalment status → paid |
| Duplicate payment prevention | Attempt re-pay of paid instalment | ✅ Error shown, DB unchanged |
| Outstanding balance recalculated | After recording payment | ✅ Backend recalculates correctly |
| Refund calculation | Enter values + select student | ✅ Policy applied, breakdown shown |
| Risk re-computation | Click Re-Compute All | ✅ Scores updated in DB |
| Parent dashboard loads | Parent login | ✅ Shows linked student's data only |
| Parent fee summary | HTTP GET (parent session) | ✅ Correct student data, no cross-access |
| Simulated payment (parent) | Submit payment form | ✅ Payment saved, is_simulated=1 |
| Receipt print page | HTTP GET | ✅ Receipt renders, print CSS applied |
| Mobile responsive layout | Browser DevTools | ✅ Sidebar collapses, single-column layout |
| CSRF on forms | Submit without token | ✅ Rejected with error message |
| SQL injection attempt | `' OR '1'='1` in search | ✅ Parameterised query, no bypass |

---

## 11. Limitations & Future Improvements

### Current Limitations
- MySQL must be manually started via XAMPP Control Panel each session (Windows service registration not done by default in some XAMPP versions)
- Risk scores are static until manually recomputed
- No email/SMS delivery — demo mode only
- No PDF export for receipts or financial reports

### Suggested Future Improvements
1. Add cron-based automatic risk score recomputation (daily)
2. Integrate SendGrid/Twilio for real email/SMS reminders
3. Add PDF receipt generation (TCPDF or DomPDF)
4. Add an Admin super-role with cross-institute capabilities
5. Add student batch transfer workflow
6. Add financial year–based reporting and CSV export
7. Add instalment due-date extension workflow with approval chain
8. Replace simulated payment with Razorpay/Stripe sandbox
9. Add progressive web app (PWA) manifest for mobile install
10. Add automated test suite (PHPUnit + Playwright E2E)
