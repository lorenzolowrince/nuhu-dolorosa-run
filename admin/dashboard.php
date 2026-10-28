<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/auth.php';

setSecurityHeaders();
Auth::requireCan('dashboard');

$admin = Auth::user();

// --- Statistics ---
$totalRegistrations = (int)(Database::fetchOne('SELECT COUNT(*) AS c FROM participants')['c'] ?? 0);
$totalConfirmed     = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM participants WHERE registration_status = 'Confirmed'")['c'] ?? 0);
$totalPending       = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM participants WHERE registration_status = 'Pending'")['c'] ?? 0);
$totalCancelled     = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM participants WHERE registration_status = 'Cancelled'")['c'] ?? 0);

// Revenue = sum of reg fees for confirmed participants
$totalRevenue = (float)(Database::fetchOne(
    "SELECT COALESCE(SUM(ec.fee), 0) AS s
     FROM participants p
     JOIN event_categories ec ON ec.id = p.category_id
     WHERE p.registration_status = 'Confirmed'"
)['s'] ?? 0);

// Pending revenue = what's still outstanding
$pendingRevenue = (float)(Database::fetchOne(
    "SELECT COALESCE(SUM(ec.fee), 0) AS s
     FROM participants p
     JOIN event_categories ec ON ec.id = p.category_id
     WHERE p.registration_status = 'Pending'"
)['s'] ?? 0);

// Receipt uploads (safe — column may not exist on older DB)
$receiptCount        = 0;
$pendingNoReceipt    = $totalPending;
$pendingWithReceipt  = 0;
try {
    $receiptCount     = (int)(Database::fetchOne(
        "SELECT COUNT(*) AS c FROM participants WHERE payment_receipt IS NOT NULL AND payment_receipt != ''"
    )['c'] ?? 0);
    $pendingNoReceipt = (int)(Database::fetchOne(
        "SELECT COUNT(*) AS c FROM participants WHERE registration_status = 'Pending' AND (payment_receipt IS NULL OR payment_receipt = '')"
    )['c'] ?? 0);
    $pendingWithReceipt = (int)(Database::fetchOne(
        "SELECT COUNT(*) AS c FROM participants WHERE registration_status = 'Pending' AND payment_receipt IS NOT NULL AND payment_receipt != ''"
    )['c'] ?? 0);
} catch (\Throwable $e) { /* column not yet added */ }

// Category breakdown
$catBreakdown = Database::fetchAll(
    'SELECT ec.id, ec.name, ec.distance, ec.fee, ec.max_participants,
            COUNT(p.id) AS cnt,
            SUM(CASE WHEN p.registration_status = "Confirmed" THEN 1 ELSE 0 END) AS confirmed_cnt,
            SUM(CASE WHEN p.registration_status = "Pending"   THEN 1 ELSE 0 END) AS pending_cnt
     FROM event_categories ec
     LEFT JOIN participants p ON p.category_id = ec.id
     WHERE ec.is_active = 1
     GROUP BY ec.id, ec.name, ec.distance, ec.fee, ec.max_participants
     ORDER BY ec.fee ASC'
);

// Optional shirt purchases (shirt_type column added by migration_v2)
$shirtSummary = [];
try {
    $shirtSummary = Database::fetchAll(
        'SELECT so.shirt_type, so.size, SUM(so.quantity) AS total_qty
         FROM shirt_orders so
         JOIN participants p ON p.id = so.participant_id
         GROUP BY so.shirt_type, so.size
         ORDER BY so.shirt_type, FIELD(so.size, "2X","X","S","M","L","XL","2XL","3XL","4XL","5XL","6XL","7XL")'
    );
} catch (\Throwable $e) { /* shirt_type column not yet added */ }

// Included event shirt sizes (event_shirt_size column added by migration_v2)
$eventShirtSummary = [];
try {
    $eventShirtSummary = Database::fetchAll(
        'SELECT event_shirt_size AS size, COUNT(*) AS total_qty
         FROM participants
         WHERE event_shirt_size IS NOT NULL
         GROUP BY event_shirt_size
         ORDER BY FIELD(event_shirt_size, "2X","X","S","M","L","XL","2XL","3XL","4XL","5XL","6XL","7XL")'
    );
} catch (\Throwable $e) { /* event_shirt_size column not yet added */ }

// Recent registrations
$recentRegistrations = Database::fetchAll(
    'SELECT p.*, ec.name AS category_name, ec.distance, ec.fee AS reg_fee
     FROM participants p
     JOIN event_categories ec ON ec.id = p.category_id
     ORDER BY p.created_at DESC LIMIT 10'
);

$pageTitle = 'Dashboard';
include PUBLIC_PATH . '/admin/includes/header.php';
?>

<?php include PUBLIC_PATH . '/admin/includes/sidebar.php'; ?>

<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="admin-page-title">Dashboard</h1>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small"><i class="fas fa-calendar me-1"></i><?= date('d M Y') ?></span>
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                    <i class="fas fa-user text-white small"></i>
                </div>
                <span class="fw-600 d-none d-md-inline"><?= e($admin['full_name']) ?></span>
            </div>
        </div>
    </div>

    <div class="admin-content">
        <?= renderFlash() ?>

        <!-- Pending with Receipt action widget -->
        <?php if ($pendingWithReceipt > 0): ?>
        <div class="alert mb-4 d-flex align-items-center gap-3" style="background:#fefce8;border:1px solid #fde047;border-left:4px solid #d97706;">
            <div style="width:44px;height:44px;background:#fef9c3;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas fa-receipt" style="color:#d97706;font-size:1.1rem;"></i>
            </div>
            <div class="flex-fill">
                <div class="fw-700"><?= $pendingWithReceipt ?> Pending registration<?= $pendingWithReceipt > 1 ? 's' : '' ?> with receipt uploaded</div>
                <div class="small text-muted">These participants have uploaded proof of payment and are waiting for confirmation.</div>
            </div>
            <a href="registrations.php?status=PendingWithReceipt" class="btn btn-sm btn-warning flex-shrink-0">
                <i class="fas fa-check-circle me-1"></i> Review &amp; Confirm
            </a>
        </div>
        <?php endif; ?>

        <!-- KPI Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card-admin">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Total Registrations</div>
                            <div class="fs-1 fw-800" style="font-family:'Montserrat',sans-serif"><?= $totalRegistrations ?></div>
                        </div>
                        <div class="icon" style="background:#ede9fe;color:#7C3AED;">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-muted small">
                        <span class="text-success me-2"><i class="fas fa-check-circle me-1"></i><?= $totalConfirmed ?> confirmed</span>
                        <span class="text-warning"><i class="fas fa-clock me-1"></i><?= $totalPending ?> pending</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card-admin">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Confirmed Revenue</div>
                            <div class="fs-2 fw-800" style="font-family:'Montserrat',sans-serif;color:#16a34a"><?= formatMYR($totalRevenue) ?></div>
                        </div>
                        <div class="icon" style="background:#dcfce7;color:#16a34a;">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-muted small">From <?= $totalConfirmed ?> confirmed registrations</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card-admin">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Pending Transfer</div>
                            <div class="fs-1 fw-800" style="font-family:'Montserrat',sans-serif;color:#d97706"><?= $totalPending ?></div>
                        </div>
                        <div class="icon" style="background:#fef9c3;color:#d97706;">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-warning small"><?= formatMYR($pendingRevenue) ?> outstanding</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card-admin">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Receipts Uploaded</div>
                            <div class="fs-1 fw-800" style="font-family:'Montserrat',sans-serif;color:#2563eb"><?= $receiptCount ?></div>
                        </div>
                        <div class="icon" style="background:#dbeafe;color:#2563eb;">
                            <i class="fas fa-receipt"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-muted small"><?= $pendingNoReceipt ?> pending without receipt</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Category Breakdown -->
            <div class="col-lg-7">
                <div class="admin-table">
                    <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                        <h6 class="fw-700 mb-0"><i class="fas fa-running me-2 text-primary"></i>Registration by Category</h6>
                        <a href="registrations.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Distance</th>
                                <th>Total</th>
                                <th>Confirmed</th>
                                <th>Pending</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($catBreakdown as $cat): ?>
                                <tr>
                                    <td class="fw-600"><?= e($cat['name']) ?></td>
                                    <td><span class="badge bg-primary"><?= e($cat['distance']) ?></span></td>
                                    <td><?= $cat['cnt'] ?><?php if ($cat['max_participants'] > 0): ?><small class="text-muted"> / <?= $cat['max_participants'] ?></small><?php endif; ?></td>
                                    <td><span class="badge-paid"><?= $cat['confirmed_cnt'] ?></span></td>
                                    <td><span class="badge-pending"><?= $cat['pending_cnt'] ?></span></td>
                                    <td style="min-width:100px;">
                                        <?php $pct = $cat['cnt'] > 0 ? round(($cat['confirmed_cnt'] / $cat['cnt']) * 100) : 0; ?>
                                        <div class="progress" style="height:6px;">
                                            <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?= $pct ?>% confirmed</small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- T-Shirt Summary -->
            <div class="col-lg-5">
                <div class="admin-table mb-4">
                    <div class="p-3 border-bottom">
                        <h6 class="fw-700 mb-0"><i class="fas fa-tshirt me-2 text-primary"></i>Event T-Shirt (Included)</h6>
                    </div>
                    <?php if ($eventShirtSummary): ?>
                        <table class="table">
                            <thead><tr><th>Size</th><th>Count</th></tr></thead>
                            <tbody>
                                <?php foreach ($eventShirtSummary as $row): ?>
                                    <tr>
                                        <td><span class="badge bg-info text-dark"><?= e(shirtSizeLabel($row['size'])) ?></span></td>
                                        <td class="fw-700"><?= $row['total_qty'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="text-center p-4 text-muted small">No registrations yet</div>
                    <?php endif; ?>
                </div>

                <?php if ($shirtSummary): ?>
                <div class="admin-table">
                    <div class="p-3 border-bottom">
                        <h6 class="fw-700 mb-0"><i class="fas fa-shopping-cart me-2 text-warning"></i>Optional Shirt Purchases</h6>
                    </div>
                    <table class="table">
                        <thead><tr><th>Type</th><th>Size</th><th>Qty</th></tr></thead>
                        <tbody>
                            <?php foreach ($shirtSummary as $row): ?>
                                <tr>
                                    <td><span class="badge bg-dark"><?= e($row['shirt_type']) ?></span></td>
                                    <td><span class="badge bg-secondary"><?= e(shirtSizeLabel($row['size'])) ?></span></td>
                                    <td class="fw-700"><?= $row['total_qty'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Registrations -->
        <div class="admin-table">
            <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                <h6 class="fw-700 mb-0"><i class="fas fa-list me-2 text-primary"></i>Recent Registrations</h6>
                <div class="d-flex gap-2">
                    <?php if (Auth::can('export')): ?>
                    <a href="export.php?type=csv" class="btn btn-sm btn-success">
                        <i class="fas fa-download me-1"></i> Export CSV
                    </a>
                    <?php endif; ?>
                    <a href="registrations.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Runner Cat.</th>
                            <th>Reg. Fee</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRegistrations as $reg):
                            $statusMap = ['Confirmed' => 'badge-paid', 'Pending' => 'badge-pending', 'Cancelled' => 'badge-failed'];
                        ?>
                            <tr>
                                <td><code class="text-primary small"><?= e($reg['reference_number']) ?></code></td>
                                <td>
                                    <a href="participant-view.php?id=<?= $reg['id'] ?>" class="fw-600 text-decoration-none"><?= e($reg['full_name']) ?></a>
                                    <div class="text-muted small"><?= e($reg['email']) ?></div>
                                </td>
                                <td>
                                    <div><?= e($reg['category_name']) ?></div>
                                    <span class="badge bg-primary"><?= e($reg['distance']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= e($reg['race_category'] ?? '—') ?></span>
                                </td>
                                <td class="fw-600"><?= formatMYR((float)$reg['reg_fee']) ?></td>
                                <td>
                                    <span class="<?= $statusMap[$reg['registration_status']] ?? 'badge-pending' ?>">
                                        <?= e($reg['registration_status']) ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= date('d M Y H:i', strtotime($reg['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentRegistrations)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No registrations yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
