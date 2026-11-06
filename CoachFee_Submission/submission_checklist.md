# CoachFee – Final Submission & Quality Assurance Checklist

This checklist confirms that the **CoachFee – Smart Coaching Institute Fee Management System** meets every technical, security, architectural, and documentation requirement prior to final evaluation and client handover.

---

## 1. Documentation & Architecture Verification

- [x] **Technical Design Document (TDD) Completed:** The comprehensive 25-section Word document (`Technical_Design_Document.docx`) is generated with complete technical depth, architecture diagrams, table schemas, and professional styling.
- [ ] **GitHub Repository Public Status:** `[REQUIRES USER ACTION]` Ensure your GitHub repository is toggled to **Public** so evaluators can inspect the codebase without permission blocks.
- [ ] **GitHub URL in TDD:** `[REQUIRES USER ACTION]` Insert your actual GitHub repository URL in Section 2 of the TDD.
- [x] **Live URL Documented:** Documented as local on-premise installation (`http://localhost/CoachFee/`) with cloud hosting placeholder if externally hosted.
- [x] **Database & ER Schema Documented:** Full 11-table schema, primary/foreign key relationships, column types, and relational diagrams included in both TDD and `schema.sql`.
- [x] **System Architecture Documented:** Procedural-MVC architecture, request flow, and security gateway diagrams explicitly illustrated.
- [x] **AI / Automation Implementation Accurately Disclosed:** The dropout risk scoring engine is strictly and transparently documented as a **rule-based, explainable scoring algorithm** rather than an opaque black-box machine-learning model.
- [x] **Labelled Assumptions Catalogue:** All system assumptions (sliding-scale refund policy, simulated payment gateway, fixed demo OTP, single currency) are explicitly catalogued and labelled.

---

## 2. Deployment & Operational Readiness

- [x] **deployment.md Executable & Accurate:** Step-by-step instructions for XAMPP startup, database creation, one-click browser setup, credentials, and troubleshooting documented with exact project paths.
- [x] **Beginner-Friendly Startup Guide:** Clear resolution provided for starting Apache and MySQL from XAMPP Control Panel.
- [x] **Demo Accounts Tested & Operational:**
  - Staff Account: `admin@coachfee.dev` / `Demo@1234` (Tested & verified)
  - Secondary Staff: `staff@coachfee.dev` / `Demo@1234` (Tested & verified)
  - Parent Account: `parent1@coachfee.dev` / `Demo@1234` + OTP `123456` (Tested & verified)
- [x] **Realistic Sample Data Populated:** Pre-seeded with 10 students, 5 batches, 40 instalments, 18 payments, 6 reminders, and 10 risk evaluations.

---

## 3. Security & Data Privacy Audit

- [x] **Zero Raw Secrets in Codebase:** No private production API keys, production database passwords, or third-party secret tokens are embedded in git-tracked files.
- [x] **Zero Real Personal Identifiable Information (PII):** All student names, emails, addresses, and phone numbers in `seed.sql` are 100% synthetic fictional sample data.
- [x] **SQL Injection Defense:** 100% of database queries use PDO prepared statements with bound parameters.
- [x] **Cross-Site Request Forgery (CSRF) Protection:** Cryptographic CSRF tokens validated on all state-changing POST endpoints.
- [x] **Password Storage Standards:** All passwords hashed using `password_hash()` with `PASSWORD_BCRYPT` (Cost factor: 12).
- [x] **Role-Based Access Control (RBAC):** Strict server-side role verification prevents parents from accessing staff pages and restricts parents to linked children only.
- [x] **Financial Transaction Integrity:** Payment recording and instalment balance updates are wrapped in atomic database transactions (`beginTransaction` / `commit`).
- [x] **Duplicate Payment Prevention:** Server-side validation prevents re-paying already settled instalments.

---

## 4. Submission Package & Delivery Verification

- [x] **Dedicated Submission Directory:** Created at `C:\xampp\htdocs\CoachFee\CoachFee_Submission\`.
- [x] **Technical_Design_Document.docx Exists:** Generated, verified, and opens cleanly (135 paragraphs, 15 tables).
- [x] **deployment.md Exists:** Generated with complete installation and troubleshooting guidelines.
- [x] **README.md Exists:** Formatted with feature matrix, architecture flow, and screenshot placeholders.
- [x] **CoachFee_Submission.zip Archive Generated:** Packaged inside `CoachFee_Submission/`.
- [x] **Strict ZIP Contents Verification:** Contains **EXACTLY TWO (2)** files:
  1. `Technical_Design_Document.docx`
  2. `deployment.md`
  *(Contains NO source code, NO database dumps, NO README, and NO extraneous files).*
- [x] **Archive Size Limit Adherence:** Current archive size is **0.06 MB**, well below the 25 MB ceiling.

---

## 5. Summary Checklist Status
| Criteria | Requirement | Status |
|---|---|---|
| Documentation Completeness | All 25 TDD sections covered | ✅ Complete |
| Installation Guide | Step-by-step XAMPP & DB setup | ✅ Complete |
| Functional Verification | All 20 smoke tests passed | ✅ Complete |
| Packaging Compliance | ZIP contains only 2 files, < 25 MB | ✅ Complete |
| User Action Required | Public GitHub URL & optional Cloud URL | ⚠️ Requires Input (2 items) |
