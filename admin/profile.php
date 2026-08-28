<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/auth.php';
require_once APP_PATH . '/csrf.php';

setSecurityHeaders();
Auth::requireAuth(APP_URL . '/admin/login.php');

$admin  = Auth::user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrFail();
    $action = $_POST['action'] ?? '';

    // --- Update profile info ---
    if ($action === 'update_profile') {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $username = sanitize($_POST['username']  ?? '');
        $email    = sanitize($_POST['email']     ?? '');

        if (empty($fullName) || empty($username) || empty($email)) {
            $errors[] = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        } else {
            $conflict = Database::fetchOne(
                'SELECT id FROM admin_users WHERE (username = ? OR email = ?) AND id != ?',
                [$username, $email, (int)$admin['id']]
            );
            if ($conflict) {
                $errors[] = 'Username or email is already taken by another account.';
            } else {
                $current = Database::fetchOne(
                    'SELECT full_name, username, email FROM admin_users WHERE id = ?',
                    [(int)$admin['id']]
                );
                $oldMap  = ['full_name' => $current['full_name'], 'username' => $current['username'], 'email' => $current['email']];
                $newMap  = ['full_name' => $fullName, 'username' => $username, 'email' => $email];
                $changed = array_filter($newMap, fn($v, $k) => $v !== $oldMap[$k], ARRAY_FILTER_USE_BOTH);

                if ($changed) {
                    Database::execute(
                        'UPDATE admin_users SET full_name = ?, username = ?, email = ? WHERE id = ?',
                        [$fullName, $username, $email, (int)$admin['id']]
                    );
                    auditLog('update_own_profile', 'admin_users', (int)$admin['id'],
                             array_intersect_key($oldMap, $changed),
                             array_intersect_key($newMap, $changed),
                             (int)$admin['id']);
                    Auth::refreshSession();
                    $admin = Auth::user();
                    setFlash('success', 'Profile updated successfully.');
                } else {
                    setFlash('info', 'No changes were made.');
                }
                redirect(APP_URL . '/admin/profile.php');
            }
        }
    }

    // --- Change password ---
    if ($action === 'change_password') {
        $currentPwd = $_POST['current_password'] ?? '';
        $newPwd     = $_POST['new_password']     ?? '';
        $newPwd2    = $_POST['new_password2']    ?? '';

        $user = Database::fetchOne('SELECT * FROM admin_users WHERE id = ?', [(int)$admin['id']]);

        if (empty($currentPwd) || empty($newPwd) || empty($newPwd2)) {
            $errors[] = 'All password fields are required.';
        } elseif (!password_verify($currentPwd, $user['password'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($newPwd) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($newPwd !== $newPwd2) {
            $errors[] = 'New passwords do not match.';
        } else {
            Database::execute(
                'UPDATE admin_users SET password = ? WHERE id = ?',
                [password_hash($newPwd, PASSWORD_BCRYPT, ['cost' => 12]), (int)$admin['id']]
            );
            auditLog('change_own_password', 'admin_users', (int)$admin['id'], null, null, (int)$admin['id']);
            setFlash('success', 'Password changed successfully.');
            redirect(APP_URL . '/admin/profile.php');
        }
    }
}

$roleLabels = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'viewer' => 'Viewer', 'data_entry' => 'Data Entry'];
$roleColors = ['super_admin' => '#7C3AED', 'admin' => '#2563eb', 'viewer' => '#6b7280', 'data_entry' => '#059669'];

$pageTitle = 'My Profile';
include PUBLIC_PATH . '/admin/includes/header.php';
?>

<?php include PUBLIC_PATH . '/admin/includes/sidebar.php'; ?>

<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h1 class="admin-page-title">My Profile</h1>
        </div>
    </div>

    <div class="admin-content">
        <?= renderFlash() ?>

        <?php if ($errors): ?>
        <div class="alert alert-danger mb-4">
            <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <!-- Profile avatar banner -->
        <div class="admin-table mb-4">
            <div class="p-4 d-flex align-items-center gap-4">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:80px;height:80px;background:#fff;border:3px solid #7C3AED;box-shadow:0 0 0 5px #ede9fe;">
                    <img src="<?= APP_URL ?>/assets/images/nddk-logo.png" alt="Profile"
                         style="width:54px;height:54px;object-fit:contain;">
                </div>
                <div>
                    <h5 class="fw-700 mb-1"><?= e($admin['full_name']) ?></h5>
                    <div class="text-muted small mb-2">@<?= e($admin['username']) ?> &nbsp;&middot;&nbsp; <?= e($admin['email']) ?></div>
                    <span class="badge" style="background:<?= $roleColors[$admin['role']] ?? '#6b7280' ?>;">
                        <i class="fas fa-shield-alt me-1"></i><?= e($roleLabels[$admin['role']] ?? ucwords(str_replace('_',' ',$admin['role']))) ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Profile Information -->
            <div class="col-lg-6">
                <div class="admin-table">
                    <div class="p-3 border-bottom">
                        <h6 class="fw-700 mb-0"><i class="fas fa-user-circle me-2 text-primary"></i>Profile Information</h6>
                    </div>
                    <div class="p-4">
                        <form method="POST">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="action" value="update_profile">
                            <div class="mb-3">
                                <label class="form-label fw-600">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control"
                                       value="<?= e($admin['full_name']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-600">Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control"
                                       value="<?= e($admin['username']) ?>" autocomplete="username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-600">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control"
                                       value="<?= e($admin['email']) ?>" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-600">Role</label>
                                <input type="text" class="form-control bg-light"
                                       value="<?= e($roleLabels[$admin['role']] ?? ucwords(str_replace('_', ' ', $admin['role']))) ?>"
                                       disabled>
                                <div class="form-text">Role can only be changed by a Super Admin.</div>
                            </div>
                            <button type="submit" class="btn btn-primary fw-700">
                                <i class="fas fa-save me-1"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Change Password -->
            <div class="col-lg-6">
                <div class="admin-table">
                    <div class="p-3 border-bottom">
                        <h6 class="fw-700 mb-0"><i class="fas fa-lock me-2 text-warning"></i>Change Password</h6>
                    </div>
                    <div class="p-4">
                        <form method="POST">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label class="form-label fw-600">Current Password <span class="text-danger">*</span></label>
                                <input type="password" name="current_password" class="form-control"
                                       autocomplete="current-password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-600">New Password <span class="text-danger">*</span></label>
                                <input type="password" name="new_password" class="form-control"
                                       minlength="8" autocomplete="new-password" required>
                                <div class="form-text">Minimum 8 characters.</div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-600">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" name="new_password2" class="form-control"
                                       autocomplete="new-password" required>
                            </div>
                            <button type="submit" class="btn btn-warning fw-700">
                                <i class="fas fa-key me-1"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
