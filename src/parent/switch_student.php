<?php
/**
 * CoachFee – Switch selected student (parent with multiple children)
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';

if (!is_logged_in() || $_SESSION['role'] !== 'parent') {
    header('Location: /CoachFee/src/auth/parent_login.php'); exit;
}
if (!verify_csrf()) {
    header('Location: /CoachFee/src/parent/dashboard.php'); exit;
}

$studentId = sanitize_int($_POST['student_id'] ?? 0);
$pdo = db();

// Verify this student is linked to this parent
$check = $pdo->prepare("SELECT 1 FROM parent_students WHERE parent_id=? AND student_id=?");
$check->execute([$_SESSION['user_id'], $studentId]);
if ($check->fetch()) {
    $_SESSION['selected_student_id'] = $studentId;
}

header('Location: /CoachFee/src/parent/dashboard.php'); exit;
