<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/auth.php';
require_once APP_PATH . '/csrf.php';

setSecurityHeaders();
Auth::requireCan('settings');

$admin = Auth::user();

// ---- Handle Settings Update ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    CSRF::verifyOrFail();

    if ($_POST['action'] === 'update_settings') {
        $allowedKeys = [
            'event_name', 'event_theme', 'event_date', 'event_start_time',
            'event_venue', 'contact_name', 'contact_email', 'contact_phone',
            'shirt_price', 'shirt_extra_size_fee_1', 'shirt_extra_size_fee_2', 'registration_open',
        ];

        $oldSettings = [];
        foreach ($allowedKeys as $key) {
            $oldSettings[$key] = getSetting($key);
        }
        $newSettings = [];

        foreach ($allowedKeys as $key) {
            if (isset($_POST[$key])) {
                $newSettings[$key] = sanitize($_POST[$key]);
                $value = sanitize($_POST[$key]);
                Database::execute(
                    'INSERT INTO system_settings (setting_key, setting_value)
                     VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                    [$key, $value]
                );
            }
        }

        [$logOld, $logNew] = auditDiff(array_intersect_key($oldSettings, $newSettings), $newSettings);
        if ($logNew) {
            auditLog('update_settings', 'system_settings', null, $logOld, $logNew, (int)$admin['id']);
        }
        CSRF::rotate();
        setFlash('success', 'Settings updated successfully.');
        redirect(APP_URL . '/admin/settings.php');
    }

    if ($_POST['action'] === 'update_category') {
        $catId  = (int)($_POST['category_id'] ?? 0);
        $fee    = (float)($_POST['fee'] ?? 0);
        $name   = sanitize($_POST['name'] ?? '');
        $desc   = sanitize($_POST['description'] ?? '');
        $active = isset($_POST['is_active']) ? 1 : 0;
        $maxPax = max(0, (int)($_POST['max_participants'] ?? 0));

        if ($catId > 0 && $fee >= 0 && $name !== '') {
            $oldCat = Database::fetchOne(
                'SELECT name, description, fee, is_active, max_participants FROM event_categories WHERE id = ?',
                [$catId]
            ) ?? [];
            Database::execute(
                'UPDATE event_categories SET name = ?, description = ?, fee = ?, is_active = ?, max_participants = ? WHERE id = ?',
                [$name, $desc, $fee, $active, $maxPax, $catId]
            );
            if (isset($oldCat['fee'])) $oldCat['fee'] = number_format((float) $oldCat['fee'], 2, '.', '');
            [$logOld, $logNew] = auditDiff($oldCat, [
                'name' => $name, 'description' => $desc, 'fee' => number_format($fee, 2, '.', ''),
                'is_active' => $active, 'max_participants' => $maxPax,
            ]);
            if ($logNew) {
                auditLog('update_category', 'event_categories', $catId, $logOld, $logNew, (int)$admin['id']);
            }
            setFlash('success', 'Category updated successfully.');
        } else {
            setFlash('error', 'Invalid category data.');
        }

        CSRF::rotate();
        redirect(APP_URL . '/admin/settings.php');
    }

    if ($_POST['action'] === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $user = Database::fetchOne('SELECT * FROM admin_users WHERE id = ?', [$admin['id']]);

        if (!password_verify($current, $user['password'])) {
            setFlash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            setFlash('error', 'New password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            setFlash('error', 'New passwords do not match.');
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
            Database::execute('UPDATE admin_users SET password = ? WHERE id = ?', [$hash, $admin['id']]);
            auditLog('change_password', 'admin_users', (int)$admin['id'], null, null, (int)$admin['id']);
            setFlash('success', 'Password changed successfully.');
        }

        CSRF::rotate();
        redirect(APP_URL . '/admin/settings.php');
    }
}

$categories = Database::fetchAll('SELECT * FROM event_categories ORDER BY fee ASC');
$settings   = Database::fetchAll('SELECT * FROM system_settings ORDER BY setting_key ASC');
$settingsMap = array_column($settings, 'setting_value', 'setting_key');

$pageTitle = 'Settings';
include PUBLIC_PATH . '/admin/includes/header.php';
?>

<?php include PUBLIC_PATH . '/admin/includes/sidebar.php'; ?>

<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h1 class="admin-page-title">Settings</h1>
        </div>
    </div>

    <div class="admin-content">
        <?= renderFlash() ?>

        <div class="row g-4">
            <!-- Event Settings -->
            <div class="col-lg-8">
                <div class="admin-table mb-4">
                    <div class="p-3 border-bottom">
                        <h6 class="fw-700 mb-0"><i class="fas fa-calendar-alt me-2 text-primary"></i>Event Settings</h6>
                    </div>
                    <div class="p-4">
                        <form method="POST">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="action" value="update_settings">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-600">Event Name</label>
                                    <input type="text" name="event_name" class="form-control"
                                           value="<?= e($settingsMap['event_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-600">Theme / Tagline</label>
                                    <input type="text" name="event_theme" class="form-control"
                                           value="<?= e($settingsMap['event_theme'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-600">Event Date</label>
                                    <input type="date" name="event_date" class="form-control"
                                           value="<?= e($settingsMap['event_date'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-600">Start Time</label>
                                    <input type="text" name="event_start_time" class="form-control"
                                           value="<?= e($settingsMap['event_start_time'] ?? '') ?>"
                                           placeholder="e.g. 5:30 AM">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-600">Registration Open?</label>
                                    <select name="registration_open" class="form-select">
                                        <option value="1" <?= ($settingsMap['registration_open'] ?? '1') === '1' ? 'selected' : '' ?>>Open</option>
                                        <option value="0" <?= ($settingsMap['registration_open'] ?? '1') === '0' ? 'selected' : '' ?>>Closed</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-600">Venue</label>
                                    <input type="text" name="event_venue" class="form-control"
                                           value="<?= e($settingsMap['event_venue'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-600">Contact Name</label>
                                    <input type="text" name="contact_name" class="form-control"
                                           value="<?= e($settingsMap['contact_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-600">Contact Email</label>
                                    <input type="email" name="contact_email" class="form-control"
                                           value="<?= e($settingsMap['contact_email'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-600">Contact Phone</label>
                                    <input type="text" name="contact_phone" class="form-control"
                                           value="<?= e($settingsMap['contact_phone'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-600">Shirt Price (RM)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">RM</span>
                                        <input type="number" name="shirt_price" class="form-control"
                                               value="<?= e($settingsMap['shirt_price'] ?? '35') ?>"
                                               min="0" step="0.01">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-600">Extra Size 3XL&ndash;5XL (+RM)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">RM</span>
                                        <input type="number" name="shirt_extra_size_fee_1" class="form-control"
                                               value="<?= e($settingsMap['shirt_extra_size_fee_1'] ?? '12') ?>"
                                               min="0" step="0.01">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-600">Extra Size 6XL&ndash;7XL (+RM)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">RM</span>
                                        <input type="number" name="shirt_extra_size_fee_2" class="form-control"
                                               value="<?= e($settingsMap['shirt_extra_size_fee_2'] ?? '14') ?>"
                                               min="0" step="0.01">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Save Settings
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Category Pricing -->
                <div class="admin-table">
                    <div class="p-3 border-bottom">
                        <h6 class="fw-700 mb-0"><i class="fas fa-trophy me-2 text-primary"></i>Event Categories &amp; Pricing</h6>
                    </div>
                    <?php foreach ($categories as $cat): ?>
                        <div class="border-bottom p-4">
                            <form method="POST">
                                <?= CSRF::field() ?>
                                <input type="hidden" name="action" value="update_category">
                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label fw-600">Category Name</label>
                                        <input type="text" name="name" class="form-control" value="<?= e($cat['name']) ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-600">Fee (RM)</label>
                                        <input type="number" name="fee" class="form-control" value="<?= $cat['fee'] ?>" min="0" step="0.01">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-600">Max Pax</label>
                                        <input type="number" name="max_participants" class="form-control" value="<?= $cat['max_participants'] ?>" min="0">
                                        <div class="form-text">0 = unlimited</div>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-center gap-2 pt-3">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="active_<?= $cat['id'] ?>"
                                               value="1" <?= $cat['is_active'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="active_<?= $cat['id'] ?>">Active</label>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                                            <i class="fas fa-save me-1"></i> Update
                                        </button>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-600">Description</label>
                                        <input type="text" name="description" class="form-control" value="<?= e($cat['description']) ?>">
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Password & Info -->
            <div class="col-lg-4">
                <div class="admin-table mb-4">
                    <div class="p-3 border-bottom">
                        <h6 class="fw-700 mb-0"><i class="fas fa-key me-2 text-warning"></i>Change Password</h6>
                    </div>
                    <div class="p-4">
                        <form method="POST">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label class="form-label fw-600">Current Password</label>
                                <input type="password" name="current_password" class="form-control" autocomplete="current-password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-600">New Password</label>
                                <input type="password" name="new_password" class="form-control" autocomplete="new-password" minlength="8" required>
                                <div class="form-text">Minimum 8 characters</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-600">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" autocomplete="new-password" required>
                            </div>
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="fas fa-key me-2"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- System Info -->
                <div class="admin-table">
                    <div class="p-3 border-bottom">
                        <h6 class="fw-700 mb-0"><i class="fas fa-info-circle me-2 text-primary"></i>System Info</h6>
                    </div>
                    <div class="p-4">
                        <table class="table table-sm table-borderless">
                            <tr><td class="text-muted">PHP Version</td><td><?= PHP_VERSION ?></td></tr>
                            <tr><td class="text-muted">App URL</td><td><small><?= e(APP_URL) ?></small></td></tr>
                            <tr><td class="text-muted">Environment</td><td><?= e(APP_ENV) ?></td></tr>
                        </table>
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
