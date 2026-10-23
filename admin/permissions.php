<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/auth.php';
require_once APP_PATH . '/csrf.php';
require_once APP_PATH . '/permissions.php';

setSecurityHeaders();
Auth::requireCan('permissions', 'view');

$admin = Auth::user();

// Auto-migrate: create role_permissions table if it does not yet exist
try {
    Database::execute("
        CREATE TABLE IF NOT EXISTS role_permissions (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            role       ENUM('admin','viewer','data_entry') NOT NULL,
            module     VARCHAR(50)  NOT NULL,
            can_view   TINYINT(1)   NOT NULL DEFAULT 0,
            can_edit   TINYINT(1)   NOT NULL DEFAULT 0,
            can_delete TINYINT(1)   NOT NULL DEFAULT 0,
            updated_by INT UNSIGNED NULL,
            updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_role_module (role, module)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (\Throwable $e) {
    // Table already exists — ignore
}
// Expand ENUM on existing installations that predate the data_entry role
try {
    Database::execute("
        ALTER TABLE role_permissions
        MODIFY COLUMN role ENUM('admin','viewer','data_entry') NOT NULL
    ");
} catch (\Throwable $e) {
    // Ignore — column already up to date or table does not exist yet
}

// Seed with hardcoded defaults if the table is empty
$rowCount = (int) (Database::fetchOne('SELECT COUNT(*) AS c FROM role_permissions')['c'] ?? 0);
if ($rowCount === 0) {
    foreach (Permissions::DEFAULTS as $role => $modules) {
        foreach ($modules as $module => $acts) {
            Database::execute(
                'INSERT IGNORE INTO role_permissions (role, module, can_view, can_edit, can_delete)
                 VALUES (?,?,?,?,?)',
                [$role, $module, $acts['view'], $acts['edit'], $acts['delete']]
            );
        }
    }
    Auth::refreshPermissions();
}

// Current matrix as "Role › Module › action" => Allowed/Denied, for the audit log
function permissionsAuditMap(): array
{
    $map = [];
    foreach (Database::fetchAll('SELECT role, module, can_view, can_edit, can_delete FROM role_permissions ORDER BY role, module') as $r) {
        $mod = Permissions::MODULES[$r['module']] ?? null;
        if (!$mod) continue;
        foreach ($mod['actions'] as $act) {
            $map[ucwords(str_replace('_', ' ', $r['role'])) . ' › ' . $mod['label'] . ' › ' . $act] = $r['can_' . $act] ? 'Allowed' : 'Denied';
        }
    }
    return $map;
}

// ---- Handle POST: reset to defaults ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_defaults') {
    Auth::requireCan('permissions', 'edit');
    CSRF::verifyOrFail();

    $oldPerms = permissionsAuditMap();
    Database::execute('DELETE FROM role_permissions');
    foreach (Permissions::DEFAULTS as $role => $modules) {
        foreach ($modules as $module => $acts) {
            Database::execute(
                'INSERT INTO role_permissions (role, module, can_view, can_edit, can_delete, updated_by)
                 VALUES (?,?,?,?,?,?)',
                [$role, $module, $acts['view'], $acts['edit'], $acts['delete'], (int) $admin['id']]
            );
        }
    }
    self_bump_version((int) $admin['id']);
    [$logOld, $logNew] = auditDiff($oldPerms, permissionsAuditMap());
    auditLog('reset_permissions', 'role_permissions', null, $logOld ?: null, $logNew ?: null, (int) $admin['id']);
    Auth::refreshPermissions();
    setFlash('success', 'Permissions reset to default values.');
    redirect(APP_URL . '/admin/permissions.php');
}

// ---- Handle POST: save matrix ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    Auth::requireCan('permissions', 'edit');
    CSRF::verifyOrFail();

    $adminId  = (int) $admin['id'];
    $oldPerms = permissionsAuditMap();

    foreach (['admin', 'viewer', 'data_entry'] as $role) {
        foreach (Permissions::MODULES as $key => $modDef) {
            $hasView   = in_array('view',   $modDef['actions'], true);
            $hasEdit   = in_array('edit',   $modDef['actions'], true);
            $hasDelete = in_array('delete', $modDef['actions'], true);

            $view   = $hasView   ? (isset($_POST['perm'][$role][$key]['view'])   ? 1 : 0) : 0;
            $edit   = $hasEdit   ? (isset($_POST['perm'][$role][$key]['edit'])   ? 1 : 0) : 0;
            $delete = $hasDelete ? (isset($_POST['perm'][$role][$key]['delete']) ? 1 : 0) : 0;

            Database::execute(
                'INSERT INTO role_permissions (role, module, can_view, can_edit, can_delete, updated_by)
                 VALUES (?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                   can_view   = VALUES(can_view),
                   can_edit   = VALUES(can_edit),
                   can_delete = VALUES(can_delete),
                   updated_by = VALUES(updated_by)',
                [$role, $key, $view, $edit, $delete, $adminId]
            );
        }
    }

    self_bump_version($adminId);
    [$logOld, $logNew] = auditDiff($oldPerms, permissionsAuditMap());
    if ($logNew) {
        auditLog('update_permissions', 'role_permissions', null, $logOld, $logNew, $adminId);
    }
    Auth::refreshPermissions();

    setFlash('success', 'Role permissions saved. All active sessions will pick up the changes on their next page load.');
    redirect(APP_URL . '/admin/permissions.php');
}

// Helper: bump permissions_version in system_settings so other sessions know to reload
function self_bump_version(int $adminId): void
{
    Database::execute(
        "INSERT INTO system_settings (setting_key, setting_value)
         VALUES ('permissions_version', ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
        [(string) time()]
    );
}

// ---- Load current permissions from DB ----
$dbRows = Database::fetchAll(
    'SELECT role, module, can_view, can_edit, can_delete FROM role_permissions ORDER BY role, module'
);
$perms = [];
foreach ($dbRows as $row) {
    $perms[$row['role']][$row['module']] = [
        'view'   => (bool) $row['can_view'],
        'edit'   => (bool) $row['can_edit'],
        'delete' => (bool) $row['can_delete'],
    ];
}
// Fill in missing combinations with hardcoded defaults
foreach (['admin', 'viewer', 'data_entry'] as $role) {
    foreach (array_keys(Permissions::MODULES) as $key) {
        if (!isset($perms[$role][$key])) {
            $perms[$role][$key] = Permissions::DEFAULTS[$role][$key]
                                  ?? ['view' => 0, 'edit' => 0, 'delete' => 0];
        }
    }
}

$lastUpdated = Database::fetchOne('SELECT MAX(updated_at) AS ts FROM role_permissions');

$pageTitle = 'Role Permissions';
include PUBLIC_PATH . '/admin/includes/header.php';
?>

<?php include PUBLIC_PATH . '/admin/includes/sidebar.php'; ?>

<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="admin-page-title">Role Permissions Matrix</h1>
        </div>
        <?php if ($lastUpdated && $lastUpdated['ts']): ?>
        <span class="text-muted small">
            <i class="fas fa-clock me-1"></i>
            Last saved: <?= date('d M Y, H:i', strtotime($lastUpdated['ts'])) ?>
        </span>
        <?php endif; ?>
    </div>

    <div class="admin-content">
        <?= renderFlash() ?>

        <div class="alert border-0 mb-4" style="background:#eff6ff;border-left:4px solid #2563eb !important;border-left-style:solid;">
            <div class="d-flex gap-3 align-items-start">
                <i class="fas fa-info-circle text-primary mt-1"></i>
                <div>
                    <strong>Super Admin</strong> always has full, unrestricted access to every module
                    and cannot be configured here. Changes to this matrix affect the
                    <strong>Admin</strong>, <strong>Data Entry</strong>, and <strong>Viewer</strong> roles only.<br>
                    <span class="text-muted small">
                        All active sessions reload their permissions automatically on the next page request.
                    </span>
                </div>
            </div>
        </div>

        <!-- Save form wraps the whole matrix -->
        <form method="POST" id="permForm">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="save">

            <div class="admin-table mb-4">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0" id="permMatrix">
                        <thead>
                            <!-- Role group headers -->
                            <tr>
                                <th rowspan="2" class="align-middle perm-module-col" style="background:#f9fafb;">
                                    Module
                                </th>
                                <th colspan="3" class="text-center perm-head-sa">
                                    <i class="fas fa-lock me-1"></i> Super Admin
                                </th>
                                <th colspan="3" class="text-center perm-head-admin">
                                    <i class="fas fa-user-shield me-1"></i> Admin Role
                                </th>
                                <th colspan="3" class="text-center perm-head-viewer">
                                    <i class="fas fa-eye me-1"></i> Viewer Role
                                </th>
                                <th colspan="3" class="text-center perm-head-de">
                                    <i class="fas fa-keyboard me-1"></i> Data Entry Role
                                </th>
                            </tr>
                            <!-- Action sub-headers -->
                            <tr>
                                <th class="text-center perm-head-sa perm-act-col">View</th>
                                <th class="text-center perm-head-sa perm-act-col">Edit</th>
                                <th class="text-center perm-head-sa perm-act-col">Delete</th>
                                <th class="text-center perm-head-admin perm-act-col">View</th>
                                <th class="text-center perm-head-admin perm-act-col">Edit</th>
                                <th class="text-center perm-head-admin perm-act-col">Delete</th>
                                <th class="text-center perm-head-viewer perm-act-col">View</th>
                                <th class="text-center perm-head-viewer perm-act-col">Edit</th>
                                <th class="text-center perm-head-viewer perm-act-col">Delete</th>
                                <th class="text-center perm-head-de perm-act-col">View</th>
                                <th class="text-center perm-head-de perm-act-col">Edit</th>
                                <th class="text-center perm-head-de perm-act-col">Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $prevSection = null;
                            foreach (Permissions::MODULES as $key => $mod):
                                if ($mod['section'] !== $prevSection):
                                    $prevSection = $mod['section'];
                            ?>
                            <tr class="perm-section-row">
                                <td colspan="13">
                                    <span class="perm-section-label"><?= e($mod['section']) ?></span>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr class="perm-data-row">
                                <!-- Module label -->
                                <td class="perm-module-cell">
                                    <i class="fas fa-<?= e($mod['icon']) ?> perm-module-icon"></i>
                                    <?= e($mod['label']) ?>
                                </td>

                                <?php
                                // --- Super Admin: always checked, all disabled ---
                                foreach (['view', 'edit', 'delete'] as $act):
                                    $applies = in_array($act, $mod['actions'], true);
                                ?>
                                <td class="text-center perm-cell-sa">
                                    <?php if ($applies): ?>
                                        <input type="checkbox" class="form-check-input perm-chk" checked disabled>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>

                                <?php
                                // --- Admin, Viewer, Data Entry: editable checkboxes ---
                                foreach (['admin', 'viewer', 'data_entry'] as $role):
                                    $cellCls = match($role) {
                                        'admin'      => 'perm-cell-admin',
                                        'viewer'     => 'perm-cell-viewer',
                                        'data_entry' => 'perm-cell-de',
                                        default      => 'perm-cell-viewer',
                                    };
                                    foreach (['view', 'edit', 'delete'] as $act):
                                        $applies = in_array($act, $mod['actions'], true);
                                        $checked = $applies && ($perms[$role][$key][$act] ?? false);
                                ?>
                                <td class="text-center <?= $cellCls ?>">
                                    <?php if ($applies): ?>
                                        <input type="checkbox"
                                               name="perm[<?= $role ?>][<?= $key ?>][<?= $act ?>]"
                                               class="form-check-input perm-chk"
                                               <?= $checked ? 'checked' : '' ?>>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (Auth::can('permissions', 'edit')): ?>
            <div class="d-flex flex-wrap gap-3 align-items-center">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save me-2"></i> Save Permissions
                </button>

                <!-- Reset form (separate POST to avoid mixing hidden fields) -->
                <form method="POST" class="d-inline"
                      onsubmit="return confirm('Reset ALL permissions to factory defaults? This cannot be undone.')">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="reset_defaults">
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="fas fa-undo me-2"></i> Reset to Defaults
                    </button>
                </form>

                <span class="text-muted small">
                    <i class="fas fa-info-circle me-1"></i>
                    Unchecking <em>View</em> for a module prevents that role from accessing the page at all.
                </span>
            </div>
            <?php else: ?>
            <p class="text-muted small"><i class="fas fa-lock me-1"></i> You have read-only access to this matrix.</p>
            <?php endif; ?>
        </form>

        <!-- Role legend -->
        <div class="row g-3 mt-4">
            <div class="col-md-3">
                <div class="card border-0 h-100" style="background:#f5f3ff;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span style="width:12px;height:12px;border-radius:50%;background:#7c3aed;display:inline-block;"></span>
                            <strong style="color:#6d28d9;">Super Admin</strong>
                        </div>
                        <p class="small text-muted mb-0">Full unconditional access to every module. Not configurable.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 h-100" style="background:#eff6ff;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span style="width:12px;height:12px;border-radius:50%;background:#2563eb;display:inline-block;"></span>
                            <strong style="color:#1d4ed8;">Admin</strong>
                        </div>
                        <p class="small text-muted mb-0">Operational staff. Can view, edit and optionally delete based on the matrix above.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 h-100" style="background:#ecfdf5;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span style="width:12px;height:12px;border-radius:50%;background:#059669;display:inline-block;"></span>
                            <strong style="color:#065f46;">Data Entry</strong>
                        </div>
                        <p class="small text-muted mb-0">Operational data entry staff. Can add and update participant records based on the matrix above.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 h-100" style="background:#f9fafb;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span style="width:12px;height:12px;border-radius:50%;background:#6b7280;display:inline-block;"></span>
                            <strong style="color:#374151;">Viewer</strong>
                        </div>
                        <p class="small text-muted mb-0">Read-only observer. Typically restricted to viewing registrations and dashboard.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Permissions matrix table */
#permMatrix th, #permMatrix td { vertical-align: middle; font-size: 13px; }
.perm-module-col   { min-width: 200px; }
.perm-act-col      { width: 62px; min-width: 62px; }
.perm-head-sa      { background: #f5f3ff; color: #6d28d9; font-weight: 700; }
.perm-head-admin   { background: #eff6ff; color: #1d4ed8; font-weight: 700; }
.perm-head-viewer  { background: #f9fafb; color: #374151; font-weight: 700; }
.perm-head-de      { background: #ecfdf5; color: #065f46; font-weight: 700; }
.perm-cell-sa      { background: #faf9ff; }
.perm-cell-admin   { background: #f8fbff; }
.perm-cell-viewer  { background: #fafafa; }
.perm-cell-de      { background: #f0fdf4; }
.perm-section-row td { background: #f1f5f9; padding: 6px 16px; }
.perm-section-label  { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; color: #64748b; }
.perm-data-row:hover td { background: #fafcff; }
.perm-module-cell    { font-weight: 500; padding-left: 20px !important; }
.perm-module-icon    { width: 16px; text-align: center; margin-right: 8px; color: var(--primary); }
.perm-chk            { width: 1.1em; height: 1.1em; cursor: pointer; }
input.perm-chk:disabled { opacity: .4; cursor: not-allowed; }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
