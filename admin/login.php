<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/auth.php';
require_once APP_PATH . '/csrf.php';

setSecurityHeaders();

// Already logged in
if (Auth::check()) {
    redirect(APP_URL . '/admin/dashboard.php');
}

$error = '';
$ip    = getClientIP();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrFail();

    if (Auth::isLockedOut($ip)) {
        $error = 'Too many failed attempts. Please wait ' . ceil(Auth::remainingLockoutSeconds($ip) / 60) . ' minutes before trying again.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } elseif (!Auth::attempt($username, $password)) {
            $remaining = Auth::MAX_ATTEMPTS - Auth::failedAttemptsCount($ip) - 1;
            $error = 'Invalid credentials.' . ($remaining > 0 ? " {$remaining} attempts remaining." : ' Account temporarily locked.');
        } else {
            CSRF::rotate();
            redirect(APP_URL . '/admin/dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Nuhu Dolorosa Run</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Montserrat:wght@700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="login-page">
<div class="login-card">
    <div class="text-center mb-3">
        <img src="<?= APP_URL ?>/assets/images/nddk-logo.png" alt="NDDK Logo" style="width:80px;height:80px;object-fit:contain;">
    </div>
    <h2 class="text-center fw-800 mb-1" style="font-family:'Montserrat',sans-serif">Admin Login</h2>
    <p class="text-center text-muted small mb-4">Nuhu Dolorosa Charity Run</p>

    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-start gap-2 mb-4">
            <i class="fas fa-exclamation-triangle mt-1 flex-shrink-0"></i>
            <div><?= e($error) ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= CSRF::field() ?>
        <div class="mb-3">
            <label class="form-label fw-600">Username or Email</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-user"></i></span>
                <input type="text" name="username" class="form-control"
                       placeholder="Enter username"
                       value="<?= isset($_POST['username']) ? e(sanitize($_POST['username'])) : '' ?>"
                       autocomplete="username" required autofocus>
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label fw-600">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" name="password" id="passwordField" class="form-control"
                       placeholder="Enter password" autocomplete="current-password" required>
                <button class="btn btn-outline-secondary" type="button" id="togglePwd" tabindex="-1">
                    <i class="fas fa-eye" id="toggleIcon"></i>
                </button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2 fw-700" style="background:linear-gradient(135deg,#6B21A8,#7C3AED);border:none;border-radius:8px;">
            <i class="fas fa-sign-in-alt me-2"></i> Sign In
        </button>
    </form>

    <div class="mt-4 text-center">
        <a href="<?= APP_URL ?>/" class="text-muted small">
            <i class="fas fa-arrow-left me-1"></i> Back to website
        </a>
    </div>
    <div class="mt-3 p-2 bg-light rounded text-center">
        <small class="text-muted">
            <i class="fas fa-shield-alt me-1"></i>
            Protected by rate limiting. <?= Auth::MAX_ATTEMPTS ?> attempts allowed per <?= Auth::LOCKOUT_MINUTES ?> minutes.
        </small>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('togglePwd').addEventListener('click', function() {
    const field = document.getElementById('passwordField');
    const icon  = document.getElementById('toggleIcon');
    if (field.type === 'password') { field.type = 'text'; icon.className = 'fas fa-eye-slash'; }
    else { field.type = 'password'; icon.className = 'fas fa-eye'; }
});
</script>
</body>
</html>
