<?php
/**
 * CoachFee – Database Configuration
 * Keep this file outside the public web root in production.
 */

define('DB_HOST',    '127.0.0.1');
define('DB_PORT',    '3306');
define('DB_NAME',    'coachfee');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',    'CoachFee');
define('APP_VERSION', '1.0.0');
define('APP_ENV',     'development'); // 'production' in live

// Session settings
define('SESSION_NAME',    'cf_session');
define('SESSION_LIFETIME', 7200); // 2 hours

// Demo OTP (always used because no SMS provider is configured)
define('USE_DEMO_OTP', true);
define('DEMO_OTP_CODE', '123456');

// Refund policy (ASSUMPTION – clearly labelled)
// Policy: sliding scale based on days since enrolment
// 0-7 days:   100% refund
// 8-30 days:  75% refund
// 31-60 days: 50% refund
// 61-90 days: 25% refund
// >90 days:   No refund
define('REFUND_POLICY_LABEL', 'Standard Sliding-Scale Policy (Assumption v1.0)');
define('REFUND_POLICY', serialize([
    ['days_max' =>  7, 'percent' => 100, 'label' => 'Within 7 days'],
    ['days_max' => 30, 'percent' =>  75, 'label' => '8–30 days'],
    ['days_max' => 60, 'percent' =>  50, 'label' => '31–60 days'],
    ['days_max' => 90, 'percent' =>  25, 'label' => '61–90 days'],
    ['days_max' => PHP_INT_MAX, 'percent' => 0, 'label' => 'More than 90 days'],
]));

// Risk analysis thresholds (rule-based, NOT machine learning)
define('RISK_THRESHOLD_HIGH',   70);
define('RISK_THRESHOLD_MEDIUM', 40);
define('RISK_MIN_INSTALMENTS',   2); // need at least 2 to compute
