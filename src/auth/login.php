<?php
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';

if (is_logged_in()) {
    header('Location: ' . ($_SESSION['role'] === 'parent' ? '/CoachFee/src/parent/dashboard.php' : '/CoachFee/src/staff/dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $error = 'Invalid request. Please try again.'; }
    else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            $error = 'Please enter both email and password.';
        } else {
            $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND role = \'staff\' AND is_active = 1 LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                login_user($user);
                set_flash('success', 'Welcome back, ' . $user['name'] . '!');
                header('Location: /CoachFee/src/staff/dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login – CoachFee</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/CoachFee/assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <div class="login-logo-icon"><i data-lucide="graduation-cap"></i></div>
            <span class="login-logo-text">Coach<span>Fee</span></span>
        </div>
        <h2 class="login-title">Staff Portal</h2>
        <p class="login-subtitle">Sign in to manage your institute</p>

        <?php if ($error): ?>
        <div class="alert alert-danger"><i data-lucide="alert-circle"></i> <?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email Address</label>
                <div style="position:relative;">
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="you@institute.com"
                           value="<?= h($_POST['email'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary w-full" style="margin-top:8px;">
                <i data-lucide="log-in"></i> Sign In
            </button>
        </form>

        <div class="login-divider"><hr><span>Demo Credentials</span><hr></div>
        <div style="background:var(--bg);border-radius:var(--radius-md);padding:var(--space-4);font-size:.8rem;">
            <div style="display:flex;justify-content:space-between;padding:4px 0;">
                <span style="color:var(--text-muted);">Email</span>
                <code style="font-weight:600;">admin@coachfee.dev</code>
            </div>
            <div style="display:flex;justify-content:space-between;padding:4px 0;border-top:1px solid var(--border-light);">
                <span style="color:var(--text-muted);">Password</span>
                <code style="font-weight:600;">Demo@1234</code>
            </div>
        </div>
        <div style="text-align:center;margin-top:var(--space-5);">
            <a href="/CoachFee/src/auth/parent_login.php" style="font-size:.82rem;color:var(--text-muted);">Parent Portal &rarr;</a>
        </div>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
