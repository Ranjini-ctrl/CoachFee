# CoachFee – Deployment Guide

## Prerequisites

| Requirement | Version |
|---|---|
| XAMPP | 8.0+ (PHP 8.0+, Apache, MySQL 5.7+) |
| Browser | Chrome, Firefox, Edge (modern) |
| Internet | Required for Google Fonts & CDN assets |

---

## ⚠️ IMPORTANT – Start XAMPP First

Before anything else, open **XAMPP Control Panel** and start:
- ✅ **Apache**
- ✅ **MySQL**

Both must show green status before proceeding.

---

## Step 1 – Files Are Already Installed

The project is already placed at:
```
C:\xampp\htdocs\CoachFee\
```

No file extraction needed.

---

## Step 2 – Database Setup (One-Click)

Open your browser and go to:
```
http://localhost/CoachFee/database/setup.php?secret=coachfee_setup_2025
```

This will:
- Create the `coachfee` MySQL database
- Run all table schemas
- Insert realistic sample data

You should see all green checkmarks. If you see errors, check that MySQL is running in XAMPP.

> **After setup**, delete the setup file for security:
> Delete `C:\xampp\htdocs\CoachFee\database\setup.php`

---

## Step 3 – Run the Application

Open your browser and visit:
```
http://localhost/CoachFee/
```

You'll be redirected to the login page.

---

## Demo Credentials

### Staff Login
| Field | Value |
|---|---|
| URL | `http://localhost/CoachFee/src/auth/login.php` |
| Email | `admin@coachfee.dev` |
| Password | `Demo@1234` |

### Staff (Secondary Account)
| Email | `staff@coachfee.dev` |
|---|---|
| Password | `Demo@1234` |

### Parent Login
| Field | Value |
|---|---|
| URL | `http://localhost/CoachFee/src/auth/parent_login.php` |
| Email | `parent1@coachfee.dev` |
| Password | `Demo@1234` |
| OTP | `123456` (Demo mode — no SMS sent) |

**Other parent accounts:** `parent2@coachfee.dev` through `parent5@coachfee.dev` (same password + OTP)

---

## Configuration

All configuration is in `config/config.php`:

```php
// Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');         // Change for production
define('DB_NAME', 'coachfee');

// OTP (demo mode — no real SMS)
define('USE_DEMO_OTP', true);
define('DEMO_OTP_CODE', '123456');

// Refund policy (configurable)
define('REFUND_POLICY', serialize([
    ['days_max' =>  7, 'percent' => 100, ...],
    ...
]));
```

### Environment Variable Names (Production)
```
DB_HOST=localhost
DB_NAME=coachfee
DB_USER=your_db_user
DB_PASS=your_db_password
APP_ENV=production
```

---

## Application Structure

```
CoachFee/
├── index.php                    ← Entry point
├── config/
│   ├── config.php               ← All settings
│   ├── database.php             ← PDO connection
│   └── session.php              ← Auth helpers
├── src/
│   ├── auth/
│   │   ├── login.php            ← Staff login
│   │   ├── parent_login.php     ← Parent login + OTP
│   │   └── logout.php
│   ├── staff/
│   │   ├── dashboard.php        ← Main dashboard
│   │   ├── students.php         ← Student list
│   │   ├── student_form.php     ← Add/Edit student
│   │   ├── student_detail.php   ← Student fee detail
│   │   ├── fee_structure.php    ← Fee/instalment setup
│   │   ├── payments.php         ← Payment history
│   │   ├── record_payment.php   ← Record a payment
│   │   ├── receipt.php          ← Payment receipt
│   │   ├── reminders.php        ← Fee reminders
│   │   ├── refund.php           ← Refund calculator
│   │   ├── risk.php             ← AI risk analysis
│   │   └── batches.php          ← Batch management
│   └── parent/
│       ├── dashboard.php        ← Parent home
│       ├── fee_summary.php      ← Fee overview
│       ├── schedule.php         ← Instalment timeline
│       ├── history.php          ← Payment history
│       ├── pay.php              ← Simulated payment
│       └── switch_student.php   ← Switch child
├── assets/
│   ├── css/style.css            ← Full design system
│   └── js/app.js                ← UI utilities
├── templates/
│   ├── header.php               ← Staff layout header
│   ├── parent_header.php        ← Parent layout header
│   └── footer.php               ← Shared footer
└── database/
    ├── schema.sql               ← Full DB schema
    ├── seed.sql                 ← Sample data
    └── setup.php                ← One-click installer
```

---

## Troubleshooting

| Problem | Fix |
|---|---|
| "Can't connect to MySQL" | Start MySQL in XAMPP Control Panel |
| Blank page | Enable PHP error display in `php.ini`: `display_errors = On` |
| Session errors | Ensure `session.save_path` is writable |
| Fonts not loading | Check internet connection (Google Fonts via CDN) |
| 404 on pages | Ensure Apache is running and URL is correct |

---

## Production Deployment Notes

1. Change `DB_PASS` to a strong password
2. Set `APP_ENV=production` in `config/config.php`
3. Set `secure=true` on session cookie (requires HTTPS)
4. Remove `database/setup.php`
5. Set up real SMS/email provider and disable `USE_DEMO_OTP`
6. Enable HTTPS (SSL certificate)
7. Set strong `open_basedir` restrictions in `php.ini`
