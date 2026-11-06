# CoachFee – Smart Coaching Institute Fee Management System

[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-MariaDB_10.4+-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![CSS3](https://img.shields.io/badge/CSS3-Claymorphism-1572B6?style=flat&logo=css3&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/CSS)
[![Chart.js](https://img.shields.io/badge/Chart.js-4.4-FF6384?style=flat&logo=chartdotjs&logoColor=white)](https://www.chartjs.org/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

> A modern, role-segregated fee management system and parent self-service portal engineered for private coaching institutes, tutoring centers, and educational academies.

---

## 📌 Project Links
- **GitHub Repository:** `[REQUIRES INPUT: Insert your public GitHub repository URL, e.g., https://github.com/your-username/CoachFee]`
- **Local Application URL:** [http://localhost/CoachFee/](http://localhost/CoachFee/)
- **Live Cloud URL:** `[REQUIRES INPUT: Insert live deployment URL if hosted on a cloud domain, e.g., https://coachfee.yourdomain.com]`

---

## 📖 Overview
CoachFee replaces paper ledgers and disconnected spreadsheets with a unified, role-based web application. It automates instalment schedules, prevents duplicate payments via atomic transactions, identifies student dropout risks using an explainable rule-based diagnostic engine, and provides parents with an interactive self-service portal featuring two-step OTP authentication and zero-risk simulated online payments.

---

## ✨ Key Features

### 🏢 Staff Administration Portal
- **Financial Accounts Dashboard:** Real-time KPI summary (Total Revenue Collected, Total Outstanding, Enrolments, High-Risk Count) with dynamic Chart.js monthly collection bars and instalment status donuts.
- **Student Management:** Full student registry with dynamic search, batch tagging, active/dropout status toggles, and soft deletion.
- **Dynamic Fee Structures:** Custom course fees, scholarship discount deductions, and automatic equal instalment divisions with remainder balancing.
- **Payment Processing:** Direct payment recording across Cash, Bank Transfer, Cheque, and Simulated Online channels with transaction isolation and duplicate guards.
- **Printable Receipts:** Branded, print-ready HTML/CSS receipts with cryptographically random verification numbers and print stylesheets.
- **Automated Reminders:** Automatic scan of overdue and upcoming (7-day) instalment milestones with batch reminder generation and demo dispatch logging.
- **Sliding-Scale Refund Calculator:** Transparent tenure-based refund calculations according to a documented sliding-scale policy.
- **AI Dropout Risk Scoring:** Rule-based diagnostic algorithm analyzing payment delays, overdue frequencies, and outstanding ratios to flag students needing academic retention intervention.
- **Batch & Course Operations:** Manage academic batches, target capacities, and date ranges.

### 👨‍👩‍👧 Parent Self-Service Portal
- **Two-Step Authentication:** Email and password login followed by a secure 6-digit OTP verification screen (Demo code: `123456`).
- **Multi-Student Switcher:** Context selector for parents with multiple enrolled siblings.
- **Interactive Fee Progress:** Visual progress meters showing paid amounts versus remaining dues.
- **Instalment Timeline:** Node-based chronological roadmap indicating Paid, Pending, and Overdue milestones.
- **Payment History Ledger:** Comprehensive log of historical payments with instant receipt viewing.
- **Simulated Payment Gateway:** Zero-liability interactive card visualizer with real-time formatting, duplicate guards, and instant receipt generation.

---

## 🖼️ Application Screenshots

### 1. Staff Accounts Dashboard
```
+-----------------------------------------------------------------------------------+
|  [SCREENSHOT PLACEHOLDER: Staff Accounts Dashboard with Chart.js Collection Chart] |
|  Location: http://localhost/CoachFee/src/staff/dashboard.php                      |
+-----------------------------------------------------------------------------------+
```

### 2. Student Registry & Fee Timeline
```
+-----------------------------------------------------------------------------------+
|  [SCREENSHOT PLACEHOLDER: Student Detail View with Milestone Instalment Timeline]  |
|  Location: http://localhost/CoachFee/src/staff/student_detail.php?id=1             |
+-----------------------------------------------------------------------------------+
```

### 3. AI Dropout Risk Analysis
```
+-----------------------------------------------------------------------------------+
|  [SCREENSHOT PLACEHOLDER: AI Risk Cards showing Evidence, Score, and Actions]     |
|  Location: http://localhost/CoachFee/src/staff/risk.php                           |
+-----------------------------------------------------------------------------------+
```

### 4. Parent Self-Service Portal & Payment Gateway
```
+-----------------------------------------------------------------------------------+
|  [SCREENSHOT PLACEHOLDER: Parent Dashboard with Simulated Credit Card Payment]    |
|  Location: http://localhost/CoachFee/src/parent/pay.php                           |
+-----------------------------------------------------------------------------------+
```

---

## 🛠️ Technology Stack & Architecture

- **Backend:** PHP 8.0+ (Native Procedural-MVC, Zero External Runtime Dependencies)
- **Database:** MariaDB 10.4+ / MySQL 8.0 (11 Relational Tables, InnoDB Engine, UTF8MB4)
- **Frontend:** HTML5, CSS3 Custom Claymorphism Design System, Vanilla JavaScript (ES6+)
- **Visuals:** Chart.js 4.4, Lucide Vector Icons
- **Typography:** Google Fonts (Poppins & Inter)

```
Browser (Desktop/Tablet/Mobile)
  │
  ▼
Apache 2.4 (Security Guards: CSRF, RBAC, Session Management)
  │
  ├─► Staff Portal (Dashboard, Students, Payments, Reminders, Refund, Risk)
  └─► Parent Portal (Child Fee Summary, Timeline, History, Simulated Payment)
        │
        ▼
MariaDB Database (ACID Transactions, Foreign Keys, 11 Tables)
```

---

## 🚀 Installation & Local Setup

### Step 1: Install XAMPP
Ensure you have **XAMPP 8.0+** installed from [https://www.apachefriends.org/](https://www.apachefriends.org/).

### Step 2: Start Apache & MySQL
1. Open **XAMPP Control Panel** as Administrator.
2. Click **Start** for **Apache** and **MySQL**. Both indicators must turn green.
   *(If MySQL does not start via GUI, run `C:\xampp\mysql_start.bat`)*.

### Step 3: Run Database One-Click Setup
Open your browser and navigate to:
```
http://localhost/CoachFee/database/setup.php?secret=coachfee_setup_2025
```
This automatically initializes the `coachfee` database, creates all 11 tables, and loads 10 sample students with 40 instalments.

### Step 4: Access CoachFee
Navigate to:
```
http://localhost/CoachFee/
```

---

## 🔑 Demo Accounts & Credentials

### Staff Portal Login
- **URL:** [http://localhost/CoachFee/src/auth/login.php](http://localhost/CoachFee/src/auth/login.php)
- **Admin Email:** `admin@coachfee.dev`
- **Staff Email:** `staff@coachfee.dev`
- **Password:** `Demo@1234`

### Parent Portal Login
- **URL:** [http://localhost/CoachFee/src/auth/parent_login.php](http://localhost/CoachFee/src/auth/parent_login.php)
- **Parent 1 Email:** `parent1@coachfee.dev` *(Linked: Aarav Verma & Neha Joshi)*
- **Parent 2 Email:** `parent2@coachfee.dev` *(Linked: Diya Sharma)*
- **Password:** `Demo@1234`
- **Step-2 OTP Code:** **`123456`** *(Demo Mode)*

---

## ⚠️ Project Limitations & Assumptions
- **Demo Mode Disclaimers:** Online credit card payments and SMS/Email reminders are executed in a safe simulation mode. No live financial charges or cellular SMS costs are incurred.
- **Rule-Based Diagnostic Logic:** The dropout risk analyzer utilizes a mathematically deterministic, rule-based diagnostic algorithm (not a black-box machine-learning model). It serves purely as an advisory tool with mandatory human-in-the-loop oversight.
- **Local Service Management:** In standard Windows XAMPP installations, Apache and MySQL must be actively started prior to loading the application.
- **Currency:** Exclusively denominated in Indian Rupees (INR, ₹).

---

## 📄 License
This project is released under the [MIT License](LICENSE).
