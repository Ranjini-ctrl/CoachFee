<?php
/**
 * CoachFee – Entry Point
 * Redirects to appropriate portal based on login state
 */
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/session.php';

if (is_logged_in()) {
    if ($_SESSION['role'] === 'parent') {
        header('Location: /CoachFee/src/parent/dashboard.php');
    } else {
        header('Location: /CoachFee/src/staff/dashboard.php');
    }
} else {
    header('Location: /CoachFee/src/auth/login.php');
}
exit;
