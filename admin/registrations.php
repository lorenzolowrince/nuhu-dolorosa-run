<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/auth.php';
require_once APP_PATH . '/csrf.php';

setSecurityHeaders();
Auth::requireCan('registrations');

$admin = Auth::user();

// Bulk confirm POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_confirm') {
    Auth::requireCan('registrations', 'edit');
    CSRF::verifyOrFail();
    $ids = array_values(array_filter(array_map('intval', $_POST['ids'] ?? [])));
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        Database::execute(
            "UPDATE participants SET registration_status = 'Confirmed' WHERE id IN ({$placeholders}) AND registration_status = 'Pending'",
            $ids
        );
        setFlash('success', count($ids) . ' participant(s) confirmed successfully.');
    }
    $qs = http_build_query([
        'status'   => sanitize($_POST['redirect_status']   ?? ''),
        'search'   => sanitize($_POST['redirect_search']   ?? ''),
        'category' => sanitize($_POST['redirect_category'] ?? ''),
    ]);
    redirect(APP_URL . '/admin/registrations.php?' . $qs);
}

// Delete participant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_participant') {
    Auth::requireCan('registrations', 'delete');
    CSRF::verifyOrFail();
    $delId = (int)($_POST['participant_id'] ?? 0);
    if ($delId) {
        $delP = Database::fetchOne(
            'SELECT p.*, ec.name AS category_name, ec.distance
             FROM participants p JOIN event_categories ec ON ec.id = p.category_id
             WHERE p.id = ?',
            [$delId]
        );
        if ($delP) {
            $deletedShirts = shirtOrdersAuditMap($delId);
            Database::execute('DELETE FROM shirt_orders WHERE participant_id = ?', [$delId]);
            Database::execute('DELETE FROM payments    WHERE participant_id = ?', [$delId]);
            Database::execute('DELETE FROM participants WHERE id = ?', [$delId]);
            auditLog('delete_participant', 'participants', $delId,
                     participantAuditSnapshot($delP) + $deletedShirts,
                     null, (int)$admin['id']);
            setFlash('success', 'Participant "' . e($delP['full_name']) . '" has been permanently deleted.');
        }
    }
    redirect(APP_URL . '/admin/registrations.php');
}

// Search & filter
$search   = sanitize($_GET['search']   ?? '');
$status   = sanitize($_GET['status']   ?? '');
$category = sanitize($_GET['category'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 25;

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(p.full_name LIKE ? OR p.ic_number LIKE ? OR p.email LIKE ? OR p.contact_number LIKE ? OR p.reference_number LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like, $like, $like]);
}
if ($status === 'PendingWithReceipt') {
    $where[] = "p.registration_status = 'Pending' AND p.payment_receipt IS NOT NULL AND p.payment_receipt != ''";
} elseif ($status !== '') {
    $where[]  = 'p.registration_status = ?';
    $params[] = $status;
}
if ($category !== '') {
    $where[]  = 'p.category_id = ?';
    $params[] = $category;
}

$whereClause = implode(' AND ', $where);

$totalRows = (int)(Database::fetchOne(
    "SELECT COUNT(*) AS c FROM participants p WHERE {$whereClause}",
    $params
)['c'] ?? 0);

$pager = paginate($totalRows, $perPage, $page);

$registrations = Database::fetchAll(
    "SELECT p.*, ec.name AS category_name, ec.distance
     FROM participants p
     JOIN event_categories ec ON ec.id = p.category_id
     WHERE {$whereClause}
     ORDER BY p.created_at DESC
     LIMIT {$pager['per_page']} OFFSET {$pager['offset']}",
    $params
);

$categories = getActiveCategories();

$pageTitle = 'Registrations';
include PUBLIC_PATH . '/admin/includes/header.php';
?>

<?php include PUBLIC_PATH . '/admin/includes/sidebar.php'; ?>

<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h1 class="admin-page-title">Registrations</h1>
        </div>
        <div class="d-flex gap-2">
            <?php if (Auth::can('registrations', 'edit')): ?>
            <a href="participant-add.php" class="btn btn-sm btn-primary">
                <i class="fas fa-user-plus me-1"></i> Add Manual Entry
            </a>
            <a href="export.php?type=csv&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&category=<?= urlencode($category) ?>"
               class="btn btn-sm btn-success"><i class="fas fa-download me-1"></i> Export CSV</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-content">
        <?= renderFlash() ?>

        <!-- Search & Filter -->
        <div class="admin-search-bar mb-4">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-600">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Name, IC, email, phone, reference..."
                           value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-600">Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-600">Registration Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <?php foreach (['Pending', 'Confirmed', 'Cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                        <option value="PendingWithReceipt" <?= $status === 'PendingWithReceipt' ? 'selected' : '' ?>>
                            Pending + Receipt Uploaded
                        </option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                    <a href="registrations.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>

        <form method="POST" id="bulkConfirmForm">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="bulk_confirm">
        <input type="hidden" name="redirect_status"   value="<?= e($status) ?>">
        <input type="hidden" name="redirect_search"   value="<?= e($search) ?>">
        <input type="hidden" name="redirect_category" value="<?= e($category) ?>">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted small">
                Showing <?= number_format($pager['offset'] + 1) ?>–<?= number_format(min($pager['offset'] + $perPage, $totalRows)) ?>
                of <?= number_format($totalRows) ?> registrations
            </span>
            <?php if (Auth::can('registrations', 'edit')): ?>
            <button type="submit" class="btn btn-success btn-sm" id="bulkConfirmBtn" disabled>
                <i class="fas fa-check-circle me-1"></i> Confirm Selected (<span id="selectedCount">0</span>)
            </button>
            <?php endif; ?>
        </div>

        <!-- Table -->
        <div class="admin-table">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <?php if (Auth::can('registrations', 'edit')): ?>
                            <th style="width:36px;">
                                <input type="checkbox" id="selectAll" class="form-check-input mt-0" title="Select all pending">
                            </th>
                            <?php endif; ?>
                            <th>#</th>
                            <th>Reference</th>
                            <th>Participant</th>
                            <th>Category</th>
                            <th>Runner Cat.</th>
                            <th>Event Shirt</th>
                            <th>Finisher Shirt</th>
                            <th>Receipt</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $i => $reg): ?>
                            <tr>
                                <?php if (Auth::can('registrations', 'edit')): ?>
                                <td>
                                    <?php if ($reg['registration_status'] === 'Pending'): ?>
                                    <input type="checkbox" name="ids[]" value="<?= $reg['id'] ?>"
                                           class="form-check-input row-check mt-0">
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td class="text-muted"><?= $pager['offset'] + $i + 1 ?></td>
                                <td>
                                    <code class="text-primary small"><?= e($reg['reference_number']) ?></code>
                                    <button class="btn btn-link btn-sm p-0 ms-1" data-copy="<?= e($reg['reference_number']) ?>"
                                            data-bs-toggle="tooltip" title="Copy">
                                        <i class="fas fa-copy text-muted"></i>
                                    </button>
                                </td>
                                <td>
                                    <div class="fw-600"><?= e($reg['full_name']) ?></div>
                                    <div class="text-muted small"><?= e($reg['ic_number']) ?></div>
                                    <div class="text-muted small"><?= e($reg['email']) ?></div>
                                    <div class="text-muted small"><?= e($reg['contact_number']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-600"><?= e($reg['category_name']) ?></div>
                                    <span class="badge bg-primary"><?= e($reg['distance']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= e($reg['race_category'] ?? '—') ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark"><?= e(shirtSizeLabel($reg['event_shirt_size'] ?? '—')) ?></span>
                                </td>
                                <td>
                                    <?php if ($reg['finisher_shirt_size']): ?>
                                        <span class="badge bg-warning text-dark"><?= e(shirtSizeLabel($reg['finisher_shirt_size'])) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($reg['payment_receipt'])): ?>
                                        <a href="<?= APP_URL ?>/assets/uploads/receipts/<?= urlencode($reg['payment_receipt']) ?>"
                                           target="_blank" class="btn btn-sm btn-outline-success py-0" title="View Receipt">
                                            <i class="fas fa-file-image"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $map = ['Confirmed' => 'badge-paid', 'Pending' => 'badge-pending', 'Cancelled' => 'badge-failed'];
                                    echo '<span class="' . ($map[$reg['registration_status']] ?? 'badge-pending') . '">' . e($reg['registration_status']) . '</span>';
                                    ?>
                                </td>
                                <td class="text-muted small">
                                    <?= date('d M Y', strtotime($reg['created_at'])) ?><br>
                                    <?= date('H:i', strtotime($reg['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="participant-view.php?id=<?= $reg['id'] ?>"
                                           class="btn btn-sm btn-outline-primary" title="View / Edit">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (Auth::can('registrations', 'delete')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                                                onclick="delParticipant(<?= $reg['id'] ?>, <?= e(json_encode($reg['full_name'])) ?>)">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($registrations)): ?>
                            <tr><td colspan="12" class="text-center text-muted py-5">No registrations found</td></tr>
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
                            <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&category=<?= urlencode($category) ?>">
                                <?= $p ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>

        </form><!-- end bulk confirm form -->
    </div>
</div>

<!-- Hidden single-use delete form (lives outside the bulk form to avoid nesting) -->
<?php if (Auth::can('registrations', 'delete')): ?>
<form id="delParticipantForm" method="POST" style="display:none;">
    <?= CSRF::field() ?>
    <input type="hidden" name="action" value="delete_participant">
    <input type="hidden" name="participant_id" id="delParticipantId">
</form>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script>
function delParticipant(id, name) {
    if (confirm('Delete participant "' + name + '"?\n\nThis cannot be undone.')) {
        document.getElementById('delParticipantId').value = id;
        document.getElementById('delParticipantForm').submit();
    }
}
</script>
<script>
(function () {
    const selectAll   = document.getElementById('selectAll');
    const btn         = document.getElementById('bulkConfirmBtn');
    const countSpan   = document.getElementById('selectedCount');

    function updateBtn() {
        const checked = document.querySelectorAll('.row-check:checked').length;
        if (countSpan) countSpan.textContent = checked;
        if (btn) btn.disabled = checked === 0;
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
            updateBtn();
        });
    }

    document.querySelectorAll('.row-check').forEach(cb =>
        cb.addEventListener('change', function () {
            if (selectAll && !this.checked) selectAll.checked = false;
            updateBtn();
        })
    );
})();
</script>
</body>
</html>
