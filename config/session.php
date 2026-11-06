<?php
/**
 * CoachFee – Session & Auth Helper
 */

require_once __DIR__ . '/config.php';

// Start session once
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => false,       // set true in HTTPS production
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    // Regenerate ID periodically to reduce fixation risk
    if (!isset($_SESSION['_last_regen'])) {
        session_regenerate_id(true);
        $_SESSION['_last_regen'] = time();
    } elseif (time() - $_SESSION['_last_regen'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_last_regen'] = time();
    }
}

// CSRF token
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals(csrf_token(), $token);
}

// Authentication
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role'  => $_SESSION['role'],
    ];
}

function require_login(string $redirect = '/CoachFee/src/auth/login.php'): void {
    if (!is_logged_in()) {
        header('Location: ' . $redirect);
        exit;
    }
}

function require_role(string $role, string $redirect = '/CoachFee/src/auth/login.php'): void {
    require_login($redirect);
    if ($_SESSION['role'] !== $role) {
        // Redirect to appropriate dashboard
        if ($_SESSION['role'] === 'parent') {
            header('Location: /CoachFee/src/parent/dashboard.php');
        } else {
            header('Location: /CoachFee/src/staff/dashboard.php');
        }
        exit;
    }
}

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['_last_regen']= time();
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

// Flash messages
function set_flash(string $key, string $message): void {
    $_SESSION['flash'][$key] = $message;
}

function get_flash(string $key): ?string {
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

// Sanitisation helpers
function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitize_int($value): int {
    return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
}

function sanitize_decimal($value): float {
    return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

// Format currency
function fmt_currency(float $amount): string {
    return '₹' . number_format($amount, 2);
}

// Format date
function fmt_date(?string $date, string $format = 'd M Y'): string {
    if (!$date) return '—';
    return date($format, strtotime($date));
}

// Generate unique receipt number
function generate_receipt_number(): string {
    return 'RCP-' . strtoupper(substr(md5(uniqid('', true)), 0, 10));
}

// Generate unique transaction reference
function generate_txn_ref(): string {
    return 'SIM-' . strtoupper(bin2hex(random_bytes(6)));
}
