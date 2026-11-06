<?php
// CoachFee – Shared HTML head + sidebar template
// Usage: include with $pageTitle and $activePage set
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';
require_login();
$user = current_user();
$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $user['name']), 0, 2)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? 'Dashboard') ?> – CoachFee</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/CoachFee/assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body>
<div class="app-shell">
<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-icon"><i data-lucide="graduation-cap"></i></div>
        <span class="sidebar-logo-text">Coach<span>Fee</span></span>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>
        <a href="/CoachFee/src/staff/dashboard.php" class="nav-item <?= ($activePage==='dashboard') ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i> Dashboard
        </a>
        <a href="/CoachFee/src/staff/students.php" class="nav-item <?= ($activePage==='students') ? 'active' : '' ?>">
            <i data-lucide="users"></i> Students
        </a>
        <a href="/CoachFee/src/staff/batches.php" class="nav-item <?= ($activePage==='batches') ? 'active' : '' ?>">
            <i data-lucide="book-open"></i> Batches
        </a>

        <div class="nav-section-label">Fee Management</div>
        <a href="/CoachFee/src/staff/payments.php" class="nav-item <?= ($activePage==='payments') ? 'active' : '' ?>">
            <i data-lucide="credit-card"></i> Payments
        </a>
        <a href="/CoachFee/src/staff/reminders.php" class="nav-item <?= ($activePage==='reminders') ? 'active' : '' ?>">
            <i data-lucide="bell"></i> Reminders
        </a>
        <a href="/CoachFee/src/staff/refund.php" class="nav-item <?= ($activePage==='refund') ? 'active' : '' ?>">
            <i data-lucide="rotate-ccw"></i> Refund Calculator
        </a>

        <div class="nav-section-label">Intelligence</div>
        <a href="/CoachFee/src/staff/risk.php" class="nav-item <?= ($activePage==='risk') ? 'active' : '' ?>">
            <i data-lucide="shield-alert"></i> Risk Analysis
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar"><?= h($initials) ?></div>
            <div>
                <div class="sidebar-user-name"><?= h($user['name']) ?></div>
                <div class="sidebar-user-role"><?= ucfirst($user['role']) ?></div>
            </div>
        </div>
        <a href="/CoachFee/src/auth/logout.php" class="nav-item" style="margin-top:8px;color:rgba(255,255,255,.5);">
            <i data-lucide="log-out"></i> Sign Out
        </a>
    </div>
</aside>
<!-- SIDEBAR OVERLAY (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- MAIN CONTENT -->
<div class="main-content">
    <!-- TOP HEADER -->
    <header class="top-header">
        <div class="header-left">
            <button class="hamburger" onclick="toggleSidebar()" aria-label="Toggle menu">
                <i data-lucide="menu"></i>
            </button>
            <div>
                <div class="page-title"><?= h($pageTitle ?? 'Dashboard') ?></div>
            </div>
        </div>
        <div class="header-right">
            <a href="/CoachFee/src/staff/reminders.php" class="header-btn" title="Reminders">
                <i data-lucide="bell"></i>
                <?php
                $overdueCount = db()->query("SELECT COUNT(*) FROM reminders WHERE status='pending'")->fetchColumn();
                if ($overdueCount > 0): ?>
                <span class="badge-dot"></span>
                <?php endif; ?>
            </a>
            <span style="font-size:.82rem;color:var(--text-muted);">
                <?= date('d M Y') ?>
            </span>
        </div>
    </header>
<?php
// Flash messages
$flashSuccess = get_flash('success');
$flashError   = get_flash('error');
$flashInfo    = get_flash('info');
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
<?php if ($flashInfo): ?>
<div style="padding:0 var(--space-8);margin-top:var(--space-4);">
    <div class="alert alert-info"><i data-lucide="info"></i> <?= h($flashInfo) ?></div>
</div>
<?php endif; ?>
