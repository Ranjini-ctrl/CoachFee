<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';
require_login('/CoachFee/src/auth/parent_login.php');
if ($_SESSION['role'] !== 'parent') {
    header('Location: /CoachFee/src/staff/dashboard.php');
    exit;
}
$user = current_user();
$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $user['name']), 0, 2)));
// Get linked students
$stmt = db()->prepare('
    SELECT s.* FROM students s
    JOIN parent_students ps ON ps.student_id = s.id
    WHERE ps.parent_id = ?
');
$stmt->execute([$user['id']]);
$linkedStudents = $stmt->fetchAll();
$currentStudentId = $_SESSION['selected_student_id'] ?? ($linkedStudents[0]['id'] ?? null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? 'Parent Portal') ?> – CoachFee</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/CoachFee/assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body>
<div class="app-shell">
<!-- PARENT SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-icon"><i data-lucide="graduation-cap"></i></div>
        <span class="sidebar-logo-text">Coach<span>Fee</span></span>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Parent Portal</div>
        <a href="/CoachFee/src/parent/dashboard.php" class="nav-item <?= ($activePage==='p-dashboard') ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i> Dashboard
        </a>
        <a href="/CoachFee/src/parent/fee_summary.php" class="nav-item <?= ($activePage==='p-fee') ? 'active' : '' ?>">
            <i data-lucide="indian-rupee"></i> Fee Summary
        </a>
        <a href="/CoachFee/src/parent/schedule.php" class="nav-item <?= ($activePage==='p-schedule') ? 'active' : '' ?>">
            <i data-lucide="calendar"></i> Instalment Schedule
        </a>
        <a href="/CoachFee/src/parent/history.php" class="nav-item <?= ($activePage==='p-history') ? 'active' : '' ?>">
            <i data-lucide="clock"></i> Payment History
        </a>
        <a href="/CoachFee/src/parent/pay.php" class="nav-item <?= ($activePage==='p-pay') ? 'active' : '' ?>">
            <i data-lucide="credit-card"></i> Make Payment
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar"><?= h($initials) ?></div>
            <div>
                <div class="sidebar-user-name"><?= h($user['name']) ?></div>
                <div class="sidebar-user-role">Parent</div>
            </div>
        </div>
        <a href="/CoachFee/src/auth/logout.php" class="nav-item" style="margin-top:8px;color:rgba(255,255,255,.5);">
            <i data-lucide="log-out"></i> Sign Out
        </a>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="main-content">
    <header class="top-header">
        <div class="header-left">
            <button class="hamburger" onclick="toggleSidebar()" aria-label="Toggle menu">
                <i data-lucide="menu"></i>
            </button>
            <div class="page-title"><?= h($pageTitle ?? 'Parent Portal') ?></div>
        </div>
        <div class="header-right">
            <?php if (count($linkedStudents) > 1): ?>
            <form method="POST" action="/CoachFee/src/parent/switch_student.php" style="display:flex;align-items:center;gap:8px;">
                <?= csrf_field() ?>
                <select name="student_id" class="form-control" style="padding:6px 10px;font-size:.82rem;" onchange="this.form.submit()">
                    <?php foreach ($linkedStudents as $ls): ?>
                    <option value="<?= $ls['id'] ?>" <?= $ls['id'] == $currentStudentId ? 'selected' : '' ?>>
                        <?= h($ls['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
            <span style="font-size:.82rem;color:var(--text-muted);"><?= date('d M Y') ?></span>
        </div>
    </header>
<?php
$flashSuccess = get_flash('success');
$flashError   = get_flash('error');
?>
<?php if ($flashSuccess): ?>
<div style="padding:0 var(--space-8);margin-top:var(--space-4);">
    <div class="alert alert-success"><i data-lucide="check-circle"></i> <?= h($flashSuccess) ?></div>
</div>
<?php endif; ?>
<?php if ($flashError): ?>
<div style="padding:0 var(--space-8);margin-top:var(--space-4);">
    <div class="alert alert-danger"><i data-lucide="alert-circle"></i> <?= h($flashError) ?></div>
</div>
<?php endif; ?>
