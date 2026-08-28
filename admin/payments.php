<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/auth.php';

setSecurityHeaders();
Auth::requireCan('payments');

$search = sanitize($_GET['search'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(p.full_name LIKE ? OR p.reference_number LIKE ? OR p.email LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like]);
}
if ($status !== '') {
    $where[]  = 'p.registration_status = ?';
    $params[] = $status;
}

$whereClause = implode(' AND ', $where);

// Summary stats
$stats = Database::fetchOne(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN p.registration_status = 'Confirmed' THEN 1 ELSE 0 END) AS confirmed,
        SUM(CASE WHEN p.registration_status = 'Pending'   THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN p.registration_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
        SUM(CASE WHEN p.registration_status = 'Confirmed' THEN ec.fee ELSE 0 END) AS confirmed_amount,
        SUM(CASE WHEN p.registration_status = 'Pending'   THEN ec.fee ELSE 0 END) AS pending_amount
     FROM participants p
     JOIN event_categories ec ON ec.id = p.category_id",
    []
);

// Receipt count — only available after migration_v3 adds the column
$receiptCount = 0;
try {
    $receiptCount = (int)(Database::fetchOne(
        "SELECT COUNT(*) AS c FROM participants WHERE payment_receipt IS NOT NULL AND payment_receipt != ''",
        []
    )['c'] ?? 0);
} catch (\Throwable $e) { /* column not yet added — silently skip */ }

$totalRows = (int)(Database::fetchOne(
    "SELECT COUNT(*) AS c FROM participants p JOIN event_categories ec ON ec.id = p.category_id WHERE {$whereClause}",
    $params
)['c'] ?? 0);

$pager = paginate($totalRows, $perPage, $page);

$registrations = Database::fetchAll(
    "SELECT p.*, ec.name AS category_name, ec.distance, ec.fee AS reg_fee
     FROM participants p
     JOIN event_categories ec ON ec.id = p.category_id
     WHERE {$whereClause}
     ORDER BY p.created_at DESC
     LIMIT {$pager['per_page']} OFFSET {$pager['offset']}",
    $params
);

$pageTitle = 'Bank Transfer Tracker';
include PUBLIC_PATH . '/admin/includes/header.php';
?>

<?php include PUBLIC_PATH . '/admin/includes/sidebar.php'; ?>

<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h1 class="admin-page-title">Bank Transfer Tracker</h1>
        </div>
    </div>

    <div class="admin-content">
        <?= renderFlash() ?>

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card-admin">
                    <div class="text-muted small mb-1">Confirmed (Payment Received)</div>
                    <div class="fs-3 fw-800" style="font-family:'Montserrat',sans-serif;color:#16a34a"><?= formatMYR((float)($stats['confirmed_amount'] ?? 0)) ?></div>
                    <div class="mt-1 text-muted small"><?= number_format((int)($stats['confirmed'] ?? 0)) ?> registrations confirmed</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card-admin">
                    <div class="text-muted small mb-1">Pending (Awaiting Transfer)</div>
                    <div class="fs-3 fw-800" style="font-family:'Montserrat',sans-serif;color:#d97706"><?= formatMYR((float)($stats['pending_amount'] ?? 0)) ?></div>
                    <div class="mt-1 text-muted small"><?= number_format((int)($stats['pending'] ?? 0)) ?> pending confirmations</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card-admin">
                    <div class="text-muted small mb-1">Receipts Uploaded</div>
                    <div class="fs-3 fw-800" style="font-family:'Montserrat',sans-serif;color:#2563eb"><?= number_format($receiptCount) ?></div>
                    <div class="mt-1 text-muted small">of <?= number_format((int)($stats['total'] ?? 0)) ?> total registrations</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card-admin">
                    <div class="text-muted small mb-1">Cancelled</div>
                    <div class="fs-3 fw-800" style="font-family:'Montserrat',sans-serif;color:#dc2626"><?= number_format((int)($stats['cancelled'] ?? 0)) ?></div>
                    <div class="mt-1 text-muted small">registrations cancelled</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="admin-search-bar mb-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-600">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Name, reference number, email..."
                           value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-600">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <?php foreach (['Pending', 'Confirmed', 'Cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="fas fa-search me-1"></i> Filter</button>
                    <a href="payments.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a>
                </div>
            </form>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted small">
                Showing <?= number_format($pager['offset'] + 1) ?>–<?= number_format(min($pager['offset'] + $perPage, $totalRows)) ?>
                of <?= number_format($totalRows) ?> registrations
            </span>
        </div>

        <!-- Table -->
        <div class="admin-table">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Participant</th>
                            <th>Category</th>
                            <th>Reg. Fee</th>
                            <th>Receipt</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $reg):
                            $statusMap = ['Confirmed' => 'badge-paid', 'Pending' => 'badge-pending', 'Cancelled' => 'badge-failed'];
                        ?>
                            <tr>
                                <td><code class="text-primary small"><?= e($reg['reference_number']) ?></code></td>
                                <td>
                                    <div class="fw-600"><?= e($reg['full_name']) ?></div>
                                    <div class="text-muted small"><?= e($reg['email']) ?></div>
                                    <div class="text-muted small"><?= e($reg['contact_number']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-600"><?= e($reg['category_name']) ?></div>
                                    <span class="badge bg-primary"><?= e($reg['distance']) ?></span>
                                </td>
                                <td class="fw-700"><?= formatMYR((float)$reg['reg_fee']) ?></td>
                                <td class="text-center">
                                    <?php $receipt = $reg['payment_receipt'] ?? null; ?>
                                    <?php if (!empty($receipt)): ?>
                                        <a href="<?= APP_URL ?>/assets/uploads/receipts/<?= urlencode($receipt) ?>"
                                           target="_blank" class="btn btn-sm btn-outline-success py-0" title="View Receipt">
                                            <i class="fas fa-file-image me-1"></i> View
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">No Receipt</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?= $statusMap[$reg['registration_status']] ?? 'badge-pending' ?>">
                                        <?= e($reg['registration_status']) ?>
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <?= date('d M Y', strtotime($reg['created_at'])) ?><br>
                                    <?= date('H:i', strtotime($reg['created_at'])) ?>
                                </td>
                                <td>
                                    <a href="participant-view.php?id=<?= $reg['id'] ?>"
                                       class="btn btn-sm btn-outline-primary" title="View & Update">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($registrations)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-5">No registrations found</td></tr>
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
                            <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">
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
