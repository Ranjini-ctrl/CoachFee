<?php
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';

if (is_logged_in() && $_SESSION['role'] === 'parent') {
    header('Location: /CoachFee/src/parent/dashboard.php');
    exit;
}

$step  = $_SESSION['parent_login_step'] ?? 'email';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $error = 'Invalid request.'; }
    elseif ($step === 'email') {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND role = \'parent\' AND is_active = 1 LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
                // Generate/store OTP
                $otp = USE_DEMO_OTP ? DEMO_OTP_CODE : str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
                $expires = date('Y-m-d H:i:s', time() + 300);
                db()->prepare('UPDATE users SET otp = ?, otp_expires_at = ?, otp_verified = 0 WHERE id = ?')
                    ->execute([$otp, $expires, $user['id']]);
                $_SESSION['parent_login_pending_id'] = $user['id'];
                $_SESSION['parent_login_step'] = 'otp';
                header('Location: /CoachFee/src/auth/parent_login.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        }
    } elseif ($step === 'otp') {
        $otp = trim($_POST['otp'] ?? '');
        $uid = $_SESSION['parent_login_pending_id'] ?? 0;

        $user = null;
        if (USE_DEMO_OTP && $otp === DEMO_OTP_CODE) {
            // Demo mode: accept the fixed OTP without expiry check
            $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND role = \'parent\' AND is_active = 1 LIMIT 1');
            $stmt->execute([$uid]);
            $user = $stmt->fetch();
        } else {
            // Real mode: check OTP + expiry
            $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND otp = ? AND otp_expires_at > NOW() LIMIT 1');
            $stmt->execute([$uid, $otp]);
            $user = $stmt->fetch();
        }

        if ($user) {
            db()->prepare('UPDATE users SET otp = NULL, otp_expires_at = NULL, otp_verified = 1 WHERE id = ?')->execute([$uid]);
            unset($_SESSION['parent_login_step'], $_SESSION['parent_login_pending_id']);
            login_user($user);
            header('Location: /CoachFee/src/parent/dashboard.php');
            exit;
        } else {
            $error = 'Invalid or expired OTP. Please try again.';
        }
    }
}

// Handle reset link before HTML output
if (isset($_GET['reset'])) {
    unset($_SESSION['parent_login_step'], $_SESSION['parent_login_pending_id']);
    header('Location: /CoachFee/src/auth/parent_login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Login – CoachFee</title>
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
        <?php if ($step === 'email'): ?>
        <h2 class="login-title">Parent Portal</h2>
        <p class="login-subtitle">Sign in to view your child's fee details</p>
        <?php if ($error): ?><div class="alert alert-danger"><i data-lucide="alert-circle"></i> <?= h($error) ?></div><?php endif; ?>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="parent@email.com" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-accent w-full"><i data-lucide="arrow-right"></i> Continue</button>
        </form>
        <div class="login-divider"><hr><span>Demo Credentials</span><hr></div>
        <div style="background:var(--bg);border-radius:var(--radius-md);padding:var(--space-4);font-size:.8rem;">
            <div style="display:flex;justify-content:space-between;padding:4px 0;"><span style="color:var(--text-muted);">Email</span><code>parent1@coachfee.dev</code></div>
            <div style="display:flex;justify-content:space-between;padding:4px 0;border-top:1px solid var(--border-light);"><span style="color:var(--text-muted);">Password</span><code>Demo@1234</code></div>
            <div style="display:flex;justify-content:space-between;padding:4px 0;border-top:1px solid var(--border-light);"><span style="color:var(--text-muted);">OTP</span><code>123456 (Demo)</code></div>
        </div>
        <div style="text-align:center;margin-top:var(--space-5);">
            <a href="/CoachFee/src/auth/login.php" style="font-size:.82rem;color:var(--text-muted);">Staff Portal &rarr;</a>
        </div>
        <?php else: ?>
        <h2 class="login-title">OTP Verification</h2>
        <p class="login-subtitle">Enter the 6-digit code sent to your phone</p>
        <?php if ($error): ?><div class="alert alert-danger"><i data-lucide="alert-circle"></i> <?= h($error) ?></div><?php endif; ?>
        <div class="alert alert-info"><i data-lucide="info"></i> <strong>Demo mode:</strong> OTP is always <strong>123456</strong></div>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>One-Time Password</label>
                <input type="text" name="otp" class="form-control" placeholder="123456" maxlength="6"
                       style="font-size:1.5rem;letter-spacing:.3em;text-align:center;" autofocus>
            </div>
            <button type="submit" class="btn btn-accent w-full"><i data-lucide="shield-check"></i> Verify & Sign In</button>
        </form>
        <div style="text-align:center;margin-top:var(--space-4);">
            <a href="/CoachFee/src/auth/parent_login.php?reset=1" style="font-size:.82rem;color:var(--text-muted);">Back to login</a>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
