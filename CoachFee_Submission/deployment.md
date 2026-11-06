# CoachFee – Smart Coaching Institute Fee Management System
## Official Deployment & Operational Guide

---

## 1. Project Introduction
**CoachFee** is an automated, web-based fee management and parent portal solution tailored for coaching institutes, private academies, and tutoring centres. It features a complete administrative back-office for student enrolment, batch scheduling, instalment structuring, payment recording, and intelligent risk detection, coupled with a self-service parent portal featuring two-step OTP verification and a zero-risk simulated payment gateway.

---

## 2. Prerequisites & System Requirements
To run CoachFee locally or in production, your system must meet the following minimum specifications:

| Requirement | Minimum Supported Specification | Recommended Specification |
|---|---|---|
| **Operating System** | Windows 10/11, macOS 11+, or Linux (Ubuntu 20.04+) | Windows 11 / Ubuntu 22.04 LTS |
| **Web Server** | Apache 2.4+ | Apache 2.4 with `mod_rewrite` enabled |
| **PHP Runtime** | PHP 8.0+ | PHP 8.2 with `pdo_mysql`, `session`, `filter` |
| **Database Server** | MySQL 5.7+ or MariaDB 10.4+ | MariaDB 10.4+ with InnoDB engine |
| **Web Browser** | Google Chrome 90+, Mozilla Firefox 88+, MS Edge 90+ | Latest Evergreen Browser |
| **Network** | Active Internet connection (for Google Fonts & CDNs) | Broadband connection |

---

## 3. Required Software
Download and install:
1. **XAMPP for Windows** (Version 8.0 or higher with PHP 8.0+ and MariaDB):
   - Official Download: [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. **Modern Web Browser** (Chrome, Firefox, or Edge).
3. *(Optional)* **Git** for source revision management: [https://git-scm.com/](https://git-scm.com/)

---

## 4. Project Installation
The CoachFee application is already deployed in your local web root directory:
```
C:\xampp\htdocs\CoachFee\
```

If copying to a new workstation:
1. Extract the project folder directly into your web server's document root:
   - **Windows (XAMPP):** `C:\xampp\htdocs\CoachFee`
   - **Linux (LAMP):** `/var/www/html/CoachFee`
   - **macOS (MAMP):** `/Applications/MAMP/htdocs/CoachFee`
2. Ensure file read permissions are granted to the web server user (`daemon` / `www-data`).

---

## 5. XAMPP Setup & Configuration
1. Install XAMPP into its default directory (recommended: `C:\xampp`).
2. Verify that port `80` (or `8080`) and port `3306` are not blocked by competing services (such as Skype, IIS, or an existing standalone MySQL server).

---

## 6. How to Run: Apache and MySQL Startup Guide
*(Step-by-step resolution for: "I start the panel, then how to run?")*

### Step 1: Open XAMPP Control Panel
1. Click the Windows **Start Menu**, search for **XAMPP Control Panel**, right-click it, and select **"Run as administrator"**.
2. Alternatively, navigate to `C:\xampp\xampp-control.exe` and launch it.

### Step 2: Start Apache and MySQL Services
1. In the XAMPP Control Panel window, locate the **Apache** row and click the **Start** button.
   - When active, the text "Apache" will turn **Green** and display port numbers (e.g., `80, 443`).
2. Locate the **MySQL** row and click the **Start** button.
   - When active, the text "MySQL" will turn **Green** and display port number `3306`.
   - *Alternative for MySQL:* If MySQL fails to start from the GUI panel, double-click `C:\xampp\mysql_start.bat` to launch the MariaDB standalone engine in a background console.

---

## 7. Database Creation
CoachFee provides an automated one-click installation script that handles database initialization, schema migration, and seed insertion.

### Method A: Automated Browser-Based Setup (Recommended)
1. Ensure Apache and MySQL are running in XAMPP.
2. Open your web browser and navigate to:
   ```
   http://localhost/CoachFee/database/setup.php?secret=coachfee_setup_2025
   ```
3. The script will automatically:
   - Create the `coachfee` database with `utf8mb4_unicode_ci` collation.
   - Execute all table creation DDL statements.
   - Populate all 10 students, 5 batches, users, instalments, payments, and risk records.
4. You will see green checkmarks confirming:
   ```
   ✅ Connected to MySQL successfully
   ✅ Database coachfee created
   ✅ Schema created (11 tables/indexes)
   ✅ Sample data inserted (26 statements)
   ✅ Verification - users: 7 · students: 10 · batches: 5 ...
   ```
5. **Security Step:** Once setup finishes, delete or rename `C:\xampp\htdocs\CoachFee\database\setup.php` to prevent unauthorized re-initialization.

---

## 8. Manual SQL Import Instructions (Alternative)
If you prefer importing via command line or phpMyAdmin:

### Via phpMyAdmin GUI:
1. Open your browser and go to `http://localhost/phpmyadmin/`.
2. Click **New** in the left sidebar, name the database `coachfee`, set Collation to `utf8mb4_unicode_ci`, and click **Create**.
3. Select the `coachfee` database.
4. Click the **Import** tab at the top.
5. Click **Choose File**, select `C:\xampp\htdocs\CoachFee\database\schema.sql`, and click **Import**.
6. Repeat the Import step with `C:\xampp\htdocs\CoachFee\database\seed.sql`.

### Via MySQL Command Line:
```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS coachfee CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
C:\xampp\mysql\bin\mysql.exe -u root coachfee -e "source C:/xampp/htdocs/CoachFee/database/schema.sql"
C:\xampp\mysql\bin\mysql.exe -u root coachfee -e "source C:/xampp/htdocs/CoachFee/database/seed.sql"
```

---

## 9. Database Configuration
All database and environment parameters are stored in:
```
C:\xampp\htdocs\CoachFee\config\config.php
```

Default local settings:
```php
define('DB_HOST',    '127.0.0.1');  // Using 127.0.0.1 forces IPv4 connection
define('DB_PORT',    '3306');
define('DB_NAME',    'coachfee');
define('DB_USER',    'root');
define('DB_PASS',    '');           // Default XAMPP has no password
```

---

## 10. Environment Variable Names (For Production)
When deploying to cloud platforms (Docker, AWS, Heroku, DigitalOcean), configure these environment variable names (never commit raw credentials):

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=coachfee
DB_USER=your_db_username
DB_PASS=your_db_password
APP_ENV=production
APP_SECRET=your_32_character_random_hex_key
USE_DEMO_OTP=false
```

---

## 11. Local Application URLs
Once Apache is running and the database is configured, access CoachFee via your browser:

- **Root Redirection:** `http://localhost/CoachFee/`
- **Staff Portal Login:** `http://localhost/CoachFee/src/auth/login.php`
- **Parent Portal Login:** `http://localhost/CoachFee/src/auth/parent_login.php`
- **Logout Endpoint:** `http://localhost/CoachFee/src/auth/logout.php`

---

## 12. Deployment Instructions (Production Server)
1. **Transfer Files:** Upload the CoachFee folder to your server's document root using SFTP, rsync, or Git clone.
2. **Configure Virtual Host:** Configure an Apache VirtualHost or Nginx server block pointing to the root directory.
3. **Database Setup:** Run `schema.sql` and optionally `seed.sql` on your production MySQL instance.
4. **Update `config.php`:** Enter your production database user, password, and host.
5. **Session Cookie Security:** In `config/session.php`, toggle `'secure' => true` when running on an HTTPS domain.
6. **Remove Installer:** Delete `database/setup.php` from the production server.

---

## 13. Live Application URL
- **Local Runtime Environment:** `http://localhost/CoachFee/`
- **Cloud Live Deployment:** `[REQUIRES INPUT: Enter your public hosting URL here if hosted externally, e.g., https://coachfee.yourdomain.com. If delivering for local institute review, state 'Configured for on-premise local intranet deployment at http://localhost/CoachFee/']`

---

## 14. Staff Demo Login Credentials

| Role | Email Address | Password | Portal URL |
|---|---|---|---|
| **Lead Administrator** | `admin@coachfee.dev` | `Demo@1234` | `http://localhost/CoachFee/src/auth/login.php` |
| **Accounts Staff** | `staff@coachfee.dev` | `Demo@1234` | `http://localhost/CoachFee/src/auth/login.php` |

*Permissions: Full administrative access to Dashboard, Students, Batches, Payments, Receipts, Reminders, Refund Calculator, and AI Risk Analysis.*

---

## 15. Parent Demo Login Credentials

| Role | Email Address | Password | Step-2 OTP Code | Linked Student(s) |
|---|---|---|---|---|
| **Parent 1** | `parent1@coachfee.dev` | `Demo@1234` | `123456` | Aarav Verma, Neha Joshi |
| **Parent 2** | `parent2@coachfee.dev` | `Demo@1234` | `123456` | Diya Sharma |
| **Parent 3** | `parent3@coachfee.dev` | `Demo@1234` | `123456` | Rohan Patel |
| **Parent 4** | `parent4@coachfee.dev` | `Demo@1234` | `123456` | Ananya Iyer |
| **Parent 5** | `parent5@coachfee.dev` | `Demo@1234` | `123456` | Kabir Nair |

*Parent Login Flow: Enter Email & Password -> Click Continue -> Enter 6-digit OTP (123456) -> Access Dashboard.*

---

## 16. Sample Data Information
The pre-seeded database contains realistic demonstration records:
- **10 Students** enrolled across distinct competitive courses (JEE Advanced, NEET Medical, Foundation 10th).
- **5 Academic Batches** with defined fee tiers, capacities, and date ranges.
- **40 Instalment Records** categorized into Paid, Pending, Overdue, and Partial states.
- **18 Historical Payments** across Cash, Cheque, Bank Transfer, and Simulated Online channels.
- **6 Reminders** logged in pending/sent states.
- **10 Computed Risk Records** demonstrating High, Medium, and Low risk thresholds with factor evidence.

---

## 17. Troubleshooting Common Errors

### Problem 1: "SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it"
- **Cause:** MariaDB/MySQL is not running on port 3306.
- **Resolution:** Open XAMPP Control Panel, ensure the MySQL module shows Green. If it crashes immediately, double-click `C:\xampp\mysql_start.bat` to run the standalone process. Ensure `DB_HOST` in `config/config.php` is set to `'127.0.0.1'`.

### Problem 2: "HTTP 404 Not Found" when opening `http://localhost/CoachFee/`
- **Cause:** The project folder is placed in the wrong path or renamed.
- **Resolution:** Confirm the project files reside at `C:\xampp\htdocs\CoachFee\`. The file `C:\xampp\htdocs\CoachFee\index.php` must exist.

### Problem 3: "Invalid or expired OTP" during Parent Login
- **Cause:** Incorrect OTP entered or session variable cleared.
- **Resolution:** In demo mode (`USE_DEMO_OTP = true`), the OTP is always **`123456`**. If locked, click "Back to login" to restart the flow.

### Problem 4: Blank White Screen (PHP Error Suppression)
- **Cause:** Uncaught PHP exception while `display_errors` is Off in `php.ini`.
- **Resolution:** Open `C:\xampp\php\php.ini`, set `display_errors = On`, and restart Apache from XAMPP Control Panel.

---

## 18. Known Limitations
1. **Local MySQL Service:** In standard non-service XAMPP installations, MySQL must be started each session before opening the application.
2. **Offline Simulation Mode:** SMS/Email reminder delivery and credit card processing are fully simulated in demo mode to avoid live telecommunication and gateway costs.
3. **Single Currency:** The application standardizes on Indian Rupees (INR, ₹).
