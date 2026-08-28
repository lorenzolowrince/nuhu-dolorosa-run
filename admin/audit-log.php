<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/auth.php';

setSecurityHeaders();
Auth::requireCan('audit_log');

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$filterAction = sanitize($_GET['action_filter'] ?? '');
$filterAdmin  = (int)($_GET['admin_filter'] ?? 0);
$filterDate   = sanitize($_GET['date_filter'] ?? '');

$where  = ['1=1'];
$params = [];

if ($filterAction !== '') {
    $where[]  = 'al.action = ?';
    $params[] = $filterAction;
}
if ($filterAdmin > 0) {
    $where[]  = 'al.admin_id = ?';
    $params[] = $filterAdmin;
}
if ($filterDate !== '') {
    $where[]  = 'DATE(al.created_at) = ?';
    $params[] = $filterDate;
}

$whereClause = implode(' AND ', $where);

$totalRows = (int)(Database::fetchOne(
    "SELECT COUNT(*) AS c FROM audit_logs al WHERE {$whereClause}",
    $params
)['c'] ?? 0);

$pager = paginate($totalRows, $perPage, $page);

$logs = Database::fetchAll(
    "SELECT al.*, au.full_name AS admin_name, au.username AS admin_username
     FROM audit_logs al
     LEFT JOIN admin_users au ON au.id = al.admin_id
     WHERE {$whereClause}
     ORDER BY al.created_at DESC
     LIMIT {$pager['per_page']} OFFSET {$pager['offset']}",
    $params
);

$distinctActions = Database::fetchAll("SELECT DISTINCT action FROM audit_logs ORDER BY action");
$adminUsers      = Database::fetchAll("SELECT id, full_name, username FROM admin_users ORDER BY full_name");

$pageTitle = 'Audit Log';
include PUBLIC_PATH . '/admin/includes/header.php';
?>

<?php include PUBLIC_PATH . '/admin/includes/sidebar.php'; ?>

<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h1 class="admin-page-title">Audit Log</h1>
        </div>
    </div>

    <div class="admin-content">
        <?= renderFlash() ?>

        <!-- Filters -->
        <div class="admin-search-bar mb-4">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-600">Action</label>
                    <select name="action_filter" class="form-select form-select-sm">
                        <option value="">All Actions</option>
                        <?php foreach ($distinctActions as $a): ?>
                            <option value="<?= e($a['action']) ?>" <?= $filterAction === $a['action'] ? 'selected' : '' ?>>
                                <?= e($a['action']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-600">Admin User</label>
                    <select name="admin_filter" class="form-select form-select-sm">
                        <option value="">All Users</option>
                        <?php foreach ($adminUsers as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $filterAdmin === (int)$u['id'] ? 'selected' : '' ?>>
                                <?= e($u['full_name']) ?> (<?= e($u['username']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-600">Date</label>
                    <input type="date" name="date_filter" class="form-control form-control-sm"
                           value="<?= e($filterDate) ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                    <a href="audit-log.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted small">
                <?php if ($totalRows > 0): ?>
                    Showing <?= number_format($pager['offset'] + 1) ?>–<?= number_format(min($pager['offset'] + $perPage, $totalRows)) ?>
                    of <?= number_format($totalRows) ?> entries
                <?php else: ?>
                    No entries found
                <?php endif; ?>
            </span>
        </div>

        <div class="admin-table">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date / Time</th>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Table</th>
                            <th>Record ID</th>
                            <th>Changes</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="text-muted small text-nowrap">
                                    <?= date('d M Y', strtotime($log['created_at'])) ?><br>
                                    <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                </td>
                                <td>
                                    <?php if ($log['admin_name']): ?>
                                        <div class="fw-600 small"><?= e($log['admin_name']) ?></div>
                                        <div class="text-muted" style="font-size:11px;"><?= e($log['admin_username']) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code class="small text-primary"><?= e($log['action']) ?></code>
                                </td>
                                <td class="text-muted small"><?= e($log['table_name'] ?? '—') ?></td>
                                <td class="text-muted small"><?= $log['record_id'] ? '#' . $log['record_id'] : '—' ?></td>
                                <td style="max-width:320px;">
                                    <?php if ($log['old_values'] || $log['new_values']): ?>
                                        <button class="btn btn-link btn-sm p-0 text-primary"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#changes-<?= $log['id'] ?>">
                                            <i class="fas fa-code me-1"></i>View
                                        </button>
                                        <div class="collapse mt-1" id="changes-<?= $log['id'] ?>">
                                            <?php if ($log['old_values']): ?>
                                                <div class="small text-muted mb-1"><strong>Before:</strong></div>
                                                <pre class="small bg-light p-2 rounded mb-1" style="font-size:11px;max-height:120px;overflow:auto;"><?= e(json_encode(json_decode($log['old_values']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                            <?php endif; ?>
                                            <?php if ($log['new_values']): ?>
                                                <div class="small text-muted mb-1"><strong>After:</strong></div>
                                                <pre class="small bg-light p-2 rounded" style="font-size:11px;max-height:120px;overflow:auto;"><?= e(json_encode(json_decode($log['new_values']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= e($log['ip_address'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-5">No audit log entries found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($pager['total_pages'] > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($p = 1; $p <= $pager['total_pages']; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $p ?>&action_filter=<?= urlencode($filterAction) ?>&admin_filter=<?= $filterAdmin ?>&date_filter=<?= urlencode($filterDate) ?>">
                                <?= $p ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
