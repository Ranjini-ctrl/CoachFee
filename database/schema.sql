-- ============================================================
-- CoachFee – Smart Coaching Institute Fee Management System
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS coachfee CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE coachfee;

-- -------------------------------------------------------
-- users (staff + parents)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    email           VARCHAR(180) NOT NULL UNIQUE,
    phone           VARCHAR(20),
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('staff','parent') NOT NULL DEFAULT 'staff',
    otp             VARCHAR(10) DEFAULT NULL,
    otp_expires_at  DATETIME DEFAULT NULL,
    otp_verified    TINYINT(1) NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- batches / courses
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS batches (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    course_name     VARCHAR(180) NOT NULL,
    description     TEXT,
    total_fee       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    start_date      DATE,
    end_date        DATE,
    max_students    INT UNSIGNED DEFAULT 50,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- students
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      VARCHAR(20) NOT NULL UNIQUE,  -- e.g. CF-2024-001
    name            VARCHAR(120) NOT NULL,
    email           VARCHAR(180),
    phone           VARCHAR(20),
    dob             DATE,
    address         TEXT,
    batch_id        INT UNSIGNED,
    enroll_date     DATE NOT NULL,
    status          ENUM('active','inactive','dropout','completed') NOT NULL DEFAULT 'active',
    photo           VARCHAR(255) DEFAULT NULL,
    notes           TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL,
    INDEX idx_batch (batch_id),
    INDEX idx_status (status),
    FULLTEXT idx_ft_name (name, email)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- parent_students (many-to-many)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS parent_students (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id   INT UNSIGNED NOT NULL,
    student_id  INT UNSIGNED NOT NULL,
    relation    VARCHAR(50) DEFAULT 'Parent',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_link (parent_id, student_id),
    FOREIGN KEY (parent_id)  REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- fee_structures  (one per student–batch combination)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS fee_structures (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      INT UNSIGNED NOT NULL,
    batch_id        INT UNSIGNED,
    total_fee       DECIMAL(10,2) NOT NULL,
    discount        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    net_fee         DECIMAL(10,2) NOT NULL,   -- total_fee - discount
    notes           TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id)   REFERENCES batches(id)  ON DELETE SET NULL,
    INDEX idx_student (student_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- instalments
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS instalments (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fee_structure_id    INT UNSIGNED NOT NULL,
    instalment_no       INT NOT NULL DEFAULT 1,
    amount              DECIMAL(10,2) NOT NULL,
    due_date            DATE NOT NULL,
    status              ENUM('pending','paid','overdue','partial') NOT NULL DEFAULT 'pending',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fee_structure_id) REFERENCES fee_structures(id) ON DELETE CASCADE,
    INDEX idx_fee_structure (fee_structure_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- payments
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instalment_id   INT UNSIGNED NOT NULL,
    student_id      INT UNSIGNED NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    payment_date    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    method          ENUM('cash','online_sim','bank_transfer','cheque') NOT NULL DEFAULT 'cash',
    txn_reference   VARCHAR(80) UNIQUE,
    receipt_number  VARCHAR(40) UNIQUE,
    notes           TEXT,
    recorded_by     INT UNSIGNED,   -- staff user id
    is_simulated    TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instalment_id) REFERENCES instalments(id) ON DELETE RESTRICT,
    FOREIGN KEY (student_id)    REFERENCES students(id)    ON DELETE RESTRICT,
    FOREIGN KEY (recorded_by)   REFERENCES users(id)       ON DELETE SET NULL,
    INDEX idx_student   (student_id),
    INDEX idx_instalment(instalment_id),
    INDEX idx_date      (payment_date)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- reminders
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS reminders (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      INT UNSIGNED NOT NULL,
    instalment_id   INT UNSIGNED,
    type            ENUM('upcoming','overdue','custom') NOT NULL DEFAULT 'upcoming',
    channel         ENUM('demo','email','sms') NOT NULL DEFAULT 'demo',
    message         TEXT,
    status          ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    scheduled_for   DATETIME,
    sent_at         DATETIME,
    created_by      INT UNSIGNED,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)    REFERENCES students(id)    ON DELETE CASCADE,
    FOREIGN KEY (instalment_id) REFERENCES instalments(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)    REFERENCES users(id)       ON DELETE SET NULL,
    INDEX idx_student (student_id),
    INDEX idx_status  (status)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- refund_records
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS refund_records (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      INT UNSIGNED NOT NULL,
    original_fee    DECIMAL(10,2) NOT NULL,
    paid_amount     DECIMAL(10,2) NOT NULL,
    deduction       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    refund_amount   DECIMAL(10,2) NOT NULL,
    policy_label    VARCHAR(120),
    policy_snapshot TEXT,       -- JSON snapshot of policy at time of calc
    reason          TEXT,
    status          ENUM('calculated','approved','processed','rejected') NOT NULL DEFAULT 'calculated',
    processed_by    INT UNSIGNED,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)    REFERENCES students(id) ON DELETE RESTRICT,
    FOREIGN KEY (processed_by)  REFERENCES users(id)    ON DELETE SET NULL,
    INDEX idx_student (student_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- risk_analysis
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS risk_analysis (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      INT UNSIGNED NOT NULL UNIQUE,
    risk_level      ENUM('low','medium','high','insufficient_data') NOT NULL DEFAULT 'insufficient_data',
    risk_score      DECIMAL(5,2) DEFAULT NULL,
    reasons         JSON,           -- array of reason strings
    evidence        JSON,           -- payment data snapshot used
    recommendation  TEXT,
    computed_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_risk_level (risk_level)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- audit_log  (optional but good practice)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED,
    action      VARCHAR(100) NOT NULL,
    entity      VARCHAR(60),
    entity_id   INT UNSIGNED,
    details     JSON,
    ip_address  VARCHAR(45),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user   (user_id),
    INDEX idx_entity (entity, entity_id)
) ENGINE=InnoDB;
