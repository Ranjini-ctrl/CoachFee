-- ============================================================
-- CoachFee – Seed Data
-- Realistic fictional sample data for demonstration
-- No real personal information is included.
-- ============================================================

USE coachfee;

-- -------------------------------------------------------
-- Staff and parent users
-- Passwords are hashed with bcrypt (password: Demo@1234)
-- -------------------------------------------------------
INSERT INTO users (name, email, phone, password_hash, role, otp_verified, is_active) VALUES
('Arjun Sharma',    'admin@coachfee.dev',    '9876543210', '$2y$12$dPqI4NlYzuTwBlltUBovW.1B.4BGlwHA1PiHhFqT/aUp3UMmcyvXO', 'staff',  1, 1),
('Priya Mehta',     'staff@coachfee.dev',    '9876543211', '$2y$12$dPqI4NlYzuTwBlltUBovW.1B.4BGlwHA1PiHhFqT/aUp3UMmcyvXO', 'staff',  1, 1),
('Rahul Verma',     'parent1@coachfee.dev',  '9123456789', '$2y$12$dPqI4NlYzuTwBlltUBovW.1B.4BGlwHA1PiHhFqT/aUp3UMmcyvXO', 'parent', 1, 1),
('Sunita Kapoor',   'parent2@coachfee.dev',  '9123456790', '$2y$12$dPqI4NlYzuTwBlltUBovW.1B.4BGlwHA1PiHhFqT/aUp3UMmcyvXO', 'parent', 1, 1),
('Deepak Nair',     'parent3@coachfee.dev',  '9123456791', '$2y$12$dPqI4NlYzuTwBlltUBovW.1B.4BGlwHA1PiHhFqT/aUp3UMmcyvXO', 'parent', 1, 1),
('Meena Pillai',    'parent4@coachfee.dev',  '9123456792', '$2y$12$dPqI4NlYzuTwBlltUBovW.1B.4BGlwHA1PiHhFqT/aUp3UMmcyvXO', 'parent', 1, 1),
('Vikram Singh',    'parent5@coachfee.dev',  '9123456793', '$2y$12$dPqI4NlYzuTwBlltUBovW.1B.4BGlwHA1PiHhFqT/aUp3UMmcyvXO', 'parent', 1, 1);

-- -------------------------------------------------------
-- Batches / Courses
-- -------------------------------------------------------
INSERT INTO batches (name, course_name, description, total_fee, start_date, end_date, max_students) VALUES
('JEE-2025-A', 'JEE Main & Advanced', 'Full-year JEE preparation batch – Morning', 48000.00, '2025-04-01', '2026-03-31', 60),
('JEE-2025-B', 'JEE Main & Advanced', 'Full-year JEE preparation batch – Evening', 48000.00, '2025-04-01', '2026-03-31', 60),
('NEET-2025',  'NEET UG Preparation', 'Comprehensive NEET biology+physics+chemistry', 52000.00, '2025-05-01', '2026-04-30', 50),
('CBSE-X-2025','CBSE Class X Booster','Board exam crash course for Class X', 18000.00, '2025-06-01', '2026-02-28', 80),
('CBSE-XII-2025','CBSE Class XII Science','Full-year Class XII science stream', 24000.00, '2025-04-15', '2026-03-15', 70);

-- -------------------------------------------------------
-- Students (10 students)
-- -------------------------------------------------------
INSERT INTO students (student_id, name, email, phone, dob, batch_id, enroll_date, status, notes) VALUES
('CF-2025-001', 'Aarav Verma',    'aarav.verma@example.com',   '9000000001', '2007-03-14', 1, '2025-04-01', 'active',   'Scholarship student'),
('CF-2025-002', 'Priya Kapoor',   'priya.kapoor@example.com',  '9000000002', '2007-07-22', 1, '2025-04-01', 'active',   NULL),
('CF-2025-003', 'Rohan Nair',     'rohan.nair@example.com',    '9000000003', '2008-01-05', 2, '2025-04-05', 'active',   NULL),
('CF-2025-004', 'Anjali Singh',   'anjali.singh@example.com',  '9000000004', '2007-11-18', 3, '2025-05-01', 'active',   'Topper candidate'),
('CF-2025-005', 'Kiran Pillai',   'kiran.pillai@example.com',  '9000000005', '2007-09-30', 3, '2025-05-02', 'active',   NULL),
('CF-2025-006', 'Dev Sharma',     'dev.sharma@example.com',    '9000000006', '2008-04-10', 4, '2025-06-01', 'active',   NULL),
('CF-2025-007', 'Neha Patel',     'neha.patel@example.com',    '9000000007', '2007-12-25', 4, '2025-06-01', 'active',   NULL),
('CF-2025-008', 'Aryan Gupta',    'aryan.gupta@example.com',   '9000000008', '2007-05-16', 5, '2025-04-15', 'active',   NULL),
('CF-2025-009', 'Sanya Mehta',    'sanya.mehta@example.com',   '9000000009', '2008-02-28', 2, '2025-04-10', 'active',   NULL),
('CF-2025-010', 'Rahul Joshi',    'rahul.joshi@example.com',   '9000000010', '2007-08-03', 1, '2025-04-01', 'inactive', 'Payment issues');

-- -------------------------------------------------------
-- Parent-student links
-- -------------------------------------------------------
INSERT INTO parent_students (parent_id, student_id, relation) VALUES
(3, 1, 'Father'),  -- Rahul Verma → Aarav Verma
(4, 2, 'Mother'),  -- Sunita Kapoor → Priya Kapoor
(5, 3, 'Father'),  -- Deepak Nair → Rohan Nair
(6, 4, 'Mother'),  -- Meena Pillai → Anjali Singh
(7, 5, 'Father'),  -- Vikram Singh → Kiran Pillai
(3, 6, 'Father');  -- Rahul Verma also parent of Dev Sharma (sibling scenario)

-- -------------------------------------------------------
-- Fee structures
-- -------------------------------------------------------
INSERT INTO fee_structures (student_id, batch_id, total_fee, discount, net_fee, notes) VALUES
(1, 1, 48000.00, 4800.00, 43200.00, '10% scholarship discount'),
(2, 1, 48000.00,    0.00, 48000.00, NULL),
(3, 2, 48000.00,    0.00, 48000.00, NULL),
(4, 3, 52000.00, 2000.00, 50000.00, 'Early-bird discount'),
(5, 3, 52000.00,    0.00, 52000.00, NULL),
(6, 4, 18000.00,    0.00, 18000.00, NULL),
(7, 4, 18000.00,    0.00, 18000.00, NULL),
(8, 5, 24000.00,    0.00, 24000.00, NULL),
(9, 2, 48000.00,    0.00, 48000.00, NULL),
(10,1, 48000.00,    0.00, 48000.00, NULL);

-- -------------------------------------------------------
-- Instalments (4 per student = quarterly)
-- -------------------------------------------------------

-- Student 1 (Aarav – net 43200) – 3 paid, 1 upcoming
INSERT INTO instalments (fee_structure_id, instalment_no, amount, due_date, status) VALUES
(1, 1, 10800.00, '2025-04-15', 'paid'),
(1, 2, 10800.00, '2025-07-15', 'paid'),
(1, 3, 10800.00, '2025-10-15', 'paid'),
(1, 4, 10800.00, '2026-01-15', 'pending');

-- Student 2 (Priya – 48000) – 2 paid, 1 overdue, 1 pending
INSERT INTO instalments (fee_structure_id, instalment_no, amount, due_date, status) VALUES
(2, 1, 12000.00, '2025-04-15', 'paid'),
(2, 2, 12000.00, '2025-07-15', 'paid'),
(2, 3, 12000.00, '2025-10-15', 'overdue'),
(2, 4, 12000.00, '2026-01-15', 'pending');

-- Student 3 (Rohan – 48000) – 1 paid, 2 overdue, 1 pending
INSERT INTO instalments (fee_structure_id, instalment_no, amount, due_date, status) VALUES
(3, 1, 12000.00, '2025-05-05', 'paid'),
(3, 2, 12000.00, '2025-08-05', 'overdue'),
(3, 3, 12000.00, '2025-11-05', 'overdue'),
(3, 4, 12000.00, '2026-02-05', 'pending');

-- Student 4 (Anjali – 50000) – 2 paid, 2 pending
INSERT INTO instalments (fee_structure_id, instalment_no, amount, due_date, status) VALUES
(4, 1, 12500.00, '2025-05-15', 'paid'),
(4, 2, 12500.00, '2025-08-15', 'paid'),
(4, 3, 12500.00, '2025-11-15', 'pending'),
(4, 4, 12500.00, '2026-02-15', 'pending');

-- Student 5 (Kiran – 52000) – 2 paid, 1 overdue, 1 pending
INSERT INTO instalments (fee_structure_id, instalment_no, amount, due_date, status) VALUES
(5, 1, 13000.00, '2025-06-01', 'paid'),
(5, 2, 13000.00, '2025-09-01', 'paid'),
(5, 3, 13000.00, '2025-12-01', 'overdue'),
(5, 4, 13000.00, '2026-03-01', 'pending');

-- Student 6 (Dev – 18000) – 1 paid, 1 overdue, 2 pending
INSERT INTO instalments (fee_structure_id, instalment_no, amount, due_date, status) VALUES
(6, 1, 4500.00, '2025-07-01', 'paid'),
(6, 2, 4500.00, '2025-09-01', 'overdue'),
(6, 3, 4500.00, '2025-11-01', 'overdue'),
(6, 4, 4500.00, '2026-01-01', 'pending');

-- Student 7 (Neha – 18000) – 2 paid, 2 pending
INSERT INTO instalments (fee_structure_id, instalment_no, amount, due_date, status) VALUES
(7, 1, 4500.00, '2025-07-01', 'paid'),
(7, 2, 4500.00, '2025-09-01', 'paid'),
(7, 3, 4500.00, '2025-11-01', 'pending'),
(7, 4, 4500.00, '2026-01-01', 'pending');

-- Student 8 (Aryan – 24000) – 2 paid, 2 pending
INSERT INTO instalments (fee_structure_id, instalment_no, amount, due_date, status) VALUES
(8, 1, 6000.00, '2025-05-15', 'paid'),
(8, 2, 6000.00, '2025-08-15', 'paid'),
(8, 3, 6000.00, '2025-11-15', 'pending'),
(8, 4, 6000.00, '2026-02-15', 'pending');

-- Student 9 (Sanya – 48000) – 3 paid, 1 pending
INSERT INTO instalments (fee_structure_id, instalment_no, amount, due_date, status) VALUES
(9, 1, 12000.00, '2025-05-10', 'paid'),
(9, 2, 12000.00, '2025-08-10', 'paid'),
(9, 3, 12000.00, '2025-11-10', 'paid'),
(9, 4, 12000.00, '2026-02-10', 'pending');

-- Student 10 (Rahul – 48000) – 0 paid, all overdue (high risk)
INSERT INTO instalments (fee_structure_id, instalment_no, amount, due_date, status) VALUES
(10,1, 12000.00, '2025-04-15', 'overdue'),
(10,2, 12000.00, '2025-07-15', 'overdue'),
(10,3, 12000.00, '2025-10-15', 'overdue'),
(10,4, 12000.00, '2026-01-15', 'pending');

-- -------------------------------------------------------
-- Payments (matching paid instalments)
-- -------------------------------------------------------
-- Student 1
INSERT INTO payments (instalment_id, student_id, amount, payment_date, method, txn_reference, receipt_number, is_simulated, recorded_by) VALUES
(1,  1, 10800.00, '2025-04-14 10:30:00', 'cash',       'TXN-2504-0001', 'RCP-2504-0001', 0, 1),
(2,  1, 10800.00, '2025-07-13 11:00:00', 'bank_transfer','TXN-2507-0001','RCP-2507-0001', 0, 1),
(3,  1, 10800.00, '2025-10-14 09:45:00', 'online_sim', 'TXN-2510-0001', 'RCP-2510-0001', 1, 1);

-- Student 2
INSERT INTO payments (instalment_id, student_id, amount, payment_date, method, txn_reference, receipt_number, is_simulated, recorded_by) VALUES
(5,  2, 12000.00, '2025-04-15 14:00:00', 'cash',       'TXN-2504-0002', 'RCP-2504-0002', 0, 1),
(6,  2, 12000.00, '2025-07-20 15:30:00', 'online_sim', 'TXN-2507-0002', 'RCP-2507-0002', 1, 2);

-- Student 3
INSERT INTO payments (instalment_id, student_id, amount, payment_date, method, txn_reference, receipt_number, is_simulated, recorded_by) VALUES
(9,  3, 12000.00, '2025-05-04 10:00:00', 'cash',       'TXN-2505-0001', 'RCP-2505-0001', 0, 1);

-- Student 4
INSERT INTO payments (instalment_id, student_id, amount, payment_date, method, txn_reference, receipt_number, is_simulated, recorded_by) VALUES
(13, 4, 12500.00, '2025-05-14 11:30:00', 'bank_transfer','TXN-2505-0002','RCP-2505-0002', 0, 1),
(14, 4, 12500.00, '2025-08-14 10:00:00', 'online_sim', 'TXN-2508-0001', 'RCP-2508-0001', 1, 1);

-- Student 5
INSERT INTO payments (instalment_id, student_id, amount, payment_date, method, txn_reference, receipt_number, is_simulated, recorded_by) VALUES
(17, 5, 13000.00, '2025-05-31 09:00:00', 'cash',       'TXN-2506-0001', 'RCP-2506-0001', 0, 2),
(18, 5, 13000.00, '2025-09-02 10:30:00', 'online_sim', 'TXN-2509-0001', 'RCP-2509-0001', 1, 2);

-- Student 6
INSERT INTO payments (instalment_id, student_id, amount, payment_date, method, txn_reference, receipt_number, is_simulated, recorded_by) VALUES
(21, 6, 4500.00, '2025-06-30 14:00:00', 'cash',        'TXN-2507-0003', 'RCP-2507-0003', 0, 1);

-- Student 7
INSERT INTO payments (instalment_id, student_id, amount, payment_date, method, txn_reference, receipt_number, is_simulated, recorded_by) VALUES
(25, 7, 4500.00, '2025-07-01 10:00:00', 'cash',        'TXN-2507-0004', 'RCP-2507-0004', 0, 1),
(26, 7, 4500.00, '2025-09-02 11:00:00', 'bank_transfer','TXN-2509-0002','RCP-2509-0002', 0, 1);

-- Student 8
INSERT INTO payments (instalment_id, student_id, amount, payment_date, method, txn_reference, receipt_number, is_simulated, recorded_by) VALUES
(29, 8, 6000.00, '2025-05-14 10:00:00', 'cash',        'TXN-2505-0003', 'RCP-2505-0003', 0, 1),
(30, 8, 6000.00, '2025-08-15 15:00:00', 'online_sim',  'TXN-2508-0002', 'RCP-2508-0002', 1, 1);

-- Student 9
INSERT INTO payments (instalment_id, student_id, amount, payment_date, method, txn_reference, receipt_number, is_simulated, recorded_by) VALUES
(33, 9, 12000.00, '2025-05-10 09:00:00', 'cash',       'TXN-2505-0004', 'RCP-2505-0004', 0, 2),
(34, 9, 12000.00, '2025-08-10 10:30:00', 'online_sim', 'TXN-2508-0003', 'RCP-2508-0003', 1, 2),
(35, 9, 12000.00, '2025-11-10 11:00:00', 'bank_transfer','TXN-2511-0001','RCP-2511-0001', 0, 1);

-- -------------------------------------------------------
-- Reminders
-- -------------------------------------------------------
INSERT INTO reminders (student_id, instalment_id, type, channel, message, status, sent_at, created_by) VALUES
(2,  7,  'overdue',  'demo', 'Dear Priya Kapoor, your instalment of ₹12,000 was due on 15-Oct-2025. Please pay immediately.', 'sent', '2025-10-18 09:00:00', 1),
(3,  10, 'overdue',  'demo', 'Dear Rohan Nair, your instalment of ₹12,000 was due on 05-Aug-2025. Please pay at the earliest.', 'sent', '2025-08-10 09:00:00', 1),
(3,  11, 'overdue',  'demo', 'Dear Rohan Nair, your instalment of ₹12,000 was due on 05-Nov-2025.', 'sent', '2025-11-08 09:00:00', 1),
(5,  19, 'overdue',  'demo', 'Dear Kiran Pillai, your instalment of ₹13,000 was due on 01-Dec-2025.', 'pending', NULL, 1),
(10, 37, 'overdue',  'demo', 'Dear Rahul Joshi, your instalment of ₹12,000 is severely overdue.', 'pending', NULL, 1),
(1,  4,  'upcoming', 'demo', 'Dear Aarav Verma, your instalment of ₹10,800 is due on 15-Jan-2026.', 'pending', NULL, 1);

-- -------------------------------------------------------
-- Initial risk analysis (precomputed)
-- -------------------------------------------------------
INSERT INTO risk_analysis (student_id, risk_level, risk_score, reasons, evidence, recommendation, computed_at) VALUES
(1, 'low',  15.00,
 JSON_ARRAY('3 of 4 instalments paid on time', 'No overdue instalments'),
 JSON_OBJECT('total_instalments', 4, 'paid', 3, 'overdue', 0, 'avg_delay_days', 1),
 'No action required. Student is performing well financially.',
 NOW()),

(2, 'medium', 55.00,
 JSON_ARRAY('1 overdue instalment', 'Payment was 5 days late in instalment 2'),
 JSON_OBJECT('total_instalments', 4, 'paid', 2, 'overdue', 1, 'avg_delay_days', 5),
 'Send reminder and follow up with parent.',
 NOW()),

(3, 'high', 82.00,
 JSON_ARRAY('2 overdue instalments', 'Only 1 of 4 instalments paid', 'Large outstanding balance of ₹36,000'),
 JSON_OBJECT('total_instalments', 4, 'paid', 1, 'overdue', 2, 'avg_delay_days', 45),
 'Urgent: Call parent immediately. Discuss payment plan or escalate.',
 NOW()),

(4, 'low', 20.00,
 JSON_ARRAY('2 of 4 instalments paid', 'No overdue instalments', 'Next instalment not yet due'),
 JSON_OBJECT('total_instalments', 4, 'paid', 2, 'overdue', 0, 'avg_delay_days', 1),
 'Monitor. No action required.',
 NOW()),

(5, 'medium', 50.00,
 JSON_ARRAY('1 overdue instalment', 'Outstanding balance ₹26,000'),
 JSON_OBJECT('total_instalments', 4, 'paid', 2, 'overdue', 1, 'avg_delay_days', 7),
 'Send polite reminder. Schedule a call with parent.',
 NOW()),

(6, 'high', 78.00,
 JSON_ARRAY('2 overdue instalments', 'Only 1 of 4 paid', 'Outstanding ₹13,500'),
 JSON_OBJECT('total_instalments', 4, 'paid', 1, 'overdue', 2, 'avg_delay_days', 30),
 'Urgent follow-up required. Discuss payment arrangement.',
 NOW()),

(7, 'low', 10.00,
 JSON_ARRAY('2 of 4 instalments paid on schedule', 'No overdue payments'),
 JSON_OBJECT('total_instalments', 4, 'paid', 2, 'overdue', 0, 'avg_delay_days', 1),
 'No action required.',
 NOW()),

(8, 'low', 18.00,
 JSON_ARRAY('2 of 4 instalments paid', 'No overdue instalments'),
 JSON_OBJECT('total_instalments', 4, 'paid', 2, 'overdue', 0, 'avg_delay_days', 1),
 'No action required. Next instalment due Nov 2025.',
 NOW()),

(9, 'low', 12.00,
 JSON_ARRAY('3 of 4 instalments paid on time', 'No overdue instalments'),
 JSON_OBJECT('total_instalments', 4, 'paid', 3, 'overdue', 0, 'avg_delay_days', 1),
 'Excellent payment record. No action required.',
 NOW()),

(10, 'high', 95.00,
 JSON_ARRAY('0 of 4 instalments paid', 'All 3 past instalments are overdue', 'Outstanding balance ₹48,000', 'Student marked inactive'),
 JSON_OBJECT('total_instalments', 4, 'paid', 0, 'overdue', 3, 'avg_delay_days', 90),
 'CRITICAL: Student has made no payments. Immediate intervention required. Consider formal notice.',
 NOW());
