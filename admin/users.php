<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/auth.php';
require_once APP_PATH . '/csrf.php';

setSecurityHeaders();
Auth::requireCan('users');

$admin  = Auth::user();
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrFail();
    $action = $_POST['action'] ?? '';

    // --- Add new user ---
    if ($action === 'add') {
        $username  = sanitize($_POST['username']  ?? '');
        $fullName  = sanitize($_POST['full_name'] ?? '');
        $email     = sanitize($_POST['email']     ?? '');
        $role      = $_POST['role']               ?? 'viewer';
        $password  = $_POST['password']           ?? '';
        $password2 = $_POST['password2']          ?? '';

        if (empty($username) || empty($fullName) || empty($email) || empty($password)) {
            $errors[] = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif ($password !== $password2) {
            $errors[] = 'Passwords do not match.';
        } elseif (!in_array($role, ['super_admin', 'admin', 'viewer', 'data_entry'], true)) {
            $errors[] = 'Invalid role.';
        } else {
            $exists = Database::fetchOne(
                'SELECT id FROM admin_users WHERE username = ? OR email = ?',
                [$username, $email]
            );
            if ($exists) {
                $errors[] = 'Username or email already exists.';
            } else {
                Database::execute(
                    'INSERT INTO admin_users (username, email, password, full_name, role, is_active)
                     VALUES (?,?,?,?,?,1)',
                    [$username, $email, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $fullName, $role]
                );
                auditLog('create_admin_user', 'admin_users', (int)Database::getInstance()->lastInsertId(),
                         null, ['username' => $username, 'role' => $role], (int)$admin['id']);
                setFlash('success', "User '{$username}' created successfully.");
                redirect(APP_URL . '/admin/users.php');
            }
        }
    }

    // --- Toggle active ---
    if ($action === 'toggle_active') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        if ($targetId && $targetId !== (int)$admin['id']) {
            $user = Database::fetchOne('SELECT * FROM admin_users WHERE id = ?', [$targetId]);
            if ($user) {
                $newActive = $user['is_active'] ? 0 : 1;
                Database::execute('UPDATE admin_users SET is_active = ? WHERE id = ?', [$newActive, $targetId]);
                auditLog('toggle_user_active', 'admin_users', $targetId,
                         ['is_active' => $user['is_active']], ['is_active' => $newActive], (int)$admin['id']);
                setFlash('success', "User '{$user['username']}' " . ($newActive ? 'activated' : 'deactivated') . '.');
            }
        } else {
            setFlash('error', 'You cannot deactivate your own account.');
        }
        redirect(APP_URL . '/admin/users.php');
    }

    // --- Change role ---
    if ($action === 'change_role') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $newRole  = $_POST['role'] ?? '';
        if ($targetId && $targetId !== (int)$admin['id'] && in_array($newRole, ['super_admin','admin','viewer','data_entry'], true)) {
            $user = Database::fetchOne('SELECT * FROM admin_users WHERE id = ?', [$targetId]);
            if ($user) {
                Database::execute('UPDATE admin_users SET role = ? WHERE id = ?', [$newRole, $targetId]);
                auditLog('change_role', 'admin_users', $targetId,
                         ['role' => $user['role']], ['role' => $newRole], (int)$admin['id']);
                setFlash('success', "Role updated for '{$user['username']}'.");
            }
        } else {
            setFlash('error', 'Cannot change your own role.');
        }
        redirect(APP_URL . '/admin/users.php');
    }

    // --- Reset password ---
    if ($action === 'reset_password') {
        $targetId  = (int)($_POST['user_id']  ?? 0);
        $password  = $_POST['new_password']   ?? '';
        $password2 = $_POST['new_password2']  ?? '';
        if (!$targetId) {
            $errors[] = 'Invalid user.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif ($password !== $password2) {
            $errors[] = 'Passwords do not match.';
        } else {
            Database::execute(
                'UPDATE admin_users SET password = ? WHERE id = ?',
                [password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $targetId]
            );
            auditLog('reset_password', 'admin_users', $targetId, null, null, (int)$admin['id']);
            setFlash('success', 'Password reset successfully.');
            redirect(APP_URL . '/admin/users.php');
        }
    }

    // --- Edit user profile info ---
    if ($action === 'edit_user') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $fullName = sanitize($_POST['full_name'] ?? '');
        $username = sanitize($_POST['username']  ?? '');
        $email    = sanitize($_POST['email']     ?? '');
        if (!$targetId) {
            $errors[] = 'Invalid user.';
        } elseif (empty($fullName) || empty($username) || empty($email)) {
            $errors[] = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        } else {
            $target = Database::fetchOne('SELECT * FROM admin_users WHERE id = ?', [$targetId]);
            if (!$target) {
                $errors[] = 'User not found.';
            } else {
                $conflict = Database::fetchOne(
                    'SELECT id FROM admin_users WHERE (username = ? OR email = ?) AND id != ?',
                    [$username, $email, $targetId]
                );
                if ($conflict) {
                    $errors[] = 'Username or email is already in use by another user.';
                } else {
                    $oldMap = ['full_name' => $target['full_name'], 'username' => $target['username'], 'email' => $target['email']];
                    $newMap = ['full_name' => $fullName, 'username' => $username, 'email' => $email];
                    $changed = array_filter($newMap, fn($v, $k) => $v !== $oldMap[$k], ARRAY_FILTER_USE_BOTH);
                    if ($changed) {
                        Database::execute(
                            'UPDATE admin_users SET full_name = ?, username = ?, email = ? WHERE id = ?',
                            [$fullName, $username, $email, $targetId]
                        );
                        auditLog('edit_admin_user', 'admin_users', $targetId,
                                 array_intersect_key($oldMap, $changed),
                                 array_intersect_key($newMap, $changed),
                                 (int)$admin['id']);
                        setFlash('success', "User '{$username}' updated.");
                    } else {
                        setFlash('info', 'No changes were made.');
                    }
                    redirect(APP_URL . '/admin/users.php');
                }
            }
        }
    }

    // --- Delete user ---
    if ($action === 'delete') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        if ($targetId && $targetId !== (int)$admin['id']) {
            $user = Database::fetchOne('SELECT * FROM admin_users WHERE id = ?', [$targetId]);
            if ($user) {
                Database::execute('DELETE FROM admin_users WHERE id = ?', [$targetId]);
                auditLog('delete_admin_user', 'admin_users', $targetId,
                         ['username' => $user['username']], null, (int)$admin['id']);
                setFlash('success', "User '{$user['username']}' deleted.");
            }
        } else {
            setFlash('error', 'You cannot delete your own account.');
        }
        redirect(APP_URL . '/admin/users.php');
    }
}

$users = Database::fetchAll('SELECT * FROM admin_users ORDER BY role ASC, full_name ASC');

$roleLabels = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'viewer' => 'Viewer', 'data_entry' => 'Data Entry'];
$roleBadge  = ['super_admin' => 'bg-purple', 'admin' => 'bg-primary', 'viewer' => 'bg-secondary', 'data_entry' => 'bg-success'];

$pageTitle = 'Manage Users';
include PUBLIC_PATH . '/admin/includes/header.php';
?>

<?php include PUBLIC_PATH . '/admin/includes/sidebar.php'; ?>

<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h1 class="admin-page-title">Manage Admin Users</h1>
        </div>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-user-plus me-1"></i> Add User
        </button>
    </div>

    <div class="admin-content">
        <?= renderFlash() ?>

        <?php if ($errors): ?>
        <div class="alert alert-danger mb-4">
            <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Roles:</strong>
            <strong>Super Admin</strong> — full access including settings &amp; user management &nbsp;|&nbsp;
            <strong>Admin</strong> — manage participants, export, view payments &nbsp;|&nbsp;
            <strong>Viewer</strong> — read-only access to registrations and payments
        </div>

        <div class="admin-table">
            <div class="p-3 border-bottom">
                <h6 class="fw-700 mb-0"><i class="fas fa-users-cog me-2 text-primary"></i>Admin Users (<?= count($users) ?>)</h6>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr class="<?= !$u['is_active'] ? 'opacity-50' : '' ?>">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                         style="width:36px;height:36px;background:#fff;border:2px solid #7C3AED;">
                                        <img src="<?= APP_URL ?>/assets/images/nddk-logo.png" alt="<?= e($u['full_name']) ?>"
                                             style="width:24px;height:24px;object-fit:contain;">
                                    </div>
                                    <div>
                                        <div class="fw-600"><?= e($u['full_name']) ?></div>
                                        <div class="text-muted small">@<?= e($u['username']) ?> &nbsp;·&nbsp; <?= e($u['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
                                <form method="POST" class="d-flex align-items-center gap-1">
                                    <?= CSRF::field() ?>
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <select name="role" class="form-select form-select-sm" style="width:auto;"
                                            onchange="this.form.submit()">
                                        <option value="super_admin" <?= $u['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                        <option value="admin"       <?= $u['role'] === 'admin'       ? 'selected' : '' ?>>Admin</option>
                                        <option value="data_entry"  <?= $u['role'] === 'data_entry'  ? 'selected' : '' ?>>Data Entry</option>
                                        <option value="viewer"      <?= $u['role'] === 'viewer'      ? 'selected' : '' ?>>Viewer</option>
                                    </select>
                                </form>
                                <?php else: ?>
                                    <span class="badge" style="background:#7C3AED;">Super Admin (You)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small">
                                <?= $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : 'Never' ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <!-- Reset Password -->
                                    <button class="btn btn-sm btn-outline-warning" title="Reset Password"
                                            data-bs-toggle="modal" data-bs-target="#resetPwdModal"
                                            data-uid="<?= $u['id'] ?>" data-name="<?= e($u['full_name']) ?>">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
                                    <!-- Edit User -->
                                    <button class="btn btn-sm btn-outline-primary" title="Edit User"
                                            data-bs-toggle="modal" data-bs-target="#editUserModal"
                                            data-uid="<?= $u['id'] ?>"
                                            data-fullname="<?= e($u['full_name']) ?>"
                                            data-username="<?= e($u['username']) ?>"
                                            data-email="<?= e($u['email']) ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <!-- Toggle Active -->
                                    <form method="POST" class="d-inline">
                                        <?= CSRF::field() ?>
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                                                title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                            <i class="fas fa-<?= $u['is_active'] ? 'ban' : 'check' ?>"></i>
                                        </button>
                                    </form>
                                    <!-- Delete -->
                                    <form method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete user <?= e(addslashes($u['full_name'])) ?>? This cannot be undone.');">
                                        <?= CSRF::field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title fw-700"><i class="fas fa-user-plus me-2 text-primary"></i>Add Admin User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-600">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" autocomplete="off" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="data_entry">Data Entry — Operational data entry &amp; updates</option>
                            <option value="viewer">Viewer — Read only</option>
                            <option value="admin">Admin — Manage participants</option>
                            <option value="super_admin">Super Admin — Full access</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" autocomplete="new-password"
                               minlength="8" required>
                        <div class="form-text">Minimum 8 characters.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="password2" class="form-control" autocomplete="new-password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-700">
                        <i class="fas fa-user-plus me-1"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPwdModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="resetUserId">
                <div class="modal-header">
                    <h5 class="modal-title fw-700"><i class="fas fa-key me-2 text-warning"></i>Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Resetting password for: <strong id="resetUserName"></strong></p>
                    <div class="mb-3">
                        <label class="form-label fw-600">New Password</label>
                        <input type="password" name="new_password" class="form-control" minlength="8" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Confirm Password</label>
                        <input type="password" name="new_password2" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-700">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-header">
                    <h5 class="modal-title fw-700"><i class="fas fa-edit me-2 text-primary"></i>Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-600">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" id="editUserFullName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="editUserUsername" class="form-control" autocomplete="off" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="editUserEmail" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-700">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('resetPwdModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('resetUserId').value  = btn.dataset.uid;
    document.getElementById('resetUserName').textContent = btn.dataset.name;
});
document.getElementById('editUserModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('editUserId').value       = btn.dataset.uid;
    document.getElementById('editUserFullName').value = btn.dataset.fullname;
    document.getElementById('editUserUsername').value = btn.dataset.username;
    document.getElementById('editUserEmail').value    = btn.dataset.email;
});
</script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
