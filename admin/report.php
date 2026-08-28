<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/auth.php';

setSecurityHeaders();
Auth::requireCan('reports');

// ── Overview totals ───────────────────────────────────────────────────────────
$overview = Database::fetchAll(
    "SELECT
        COUNT(*)                                                          AS total,
        SUM(registration_status = 'Confirmed')                           AS confirmed,
        SUM(registration_status = 'Pending')                             AS pending,
        SUM(registration_status = 'Cancelled')                           AS cancelled,
        SUM(registration_status = 'Pending'
            AND payment_receipt IS NOT NULL AND payment_receipt != '')   AS pending_receipt,
        SUM(gender = 'Male')                                             AS male,
        SUM(gender = 'Female')                                           AS female
     FROM participants"
)[0] ?? [];

// ── By event category ─────────────────────────────────────────────────────────
$byCategory = Database::fetchAll(
    "SELECT ec.name, ec.distance, ec.fee,
            COUNT(p.id)                                  AS total,
            SUM(p.registration_status = 'Confirmed')     AS confirmed,
            SUM(p.registration_status = 'Pending')       AS pending,
            SUM(p.registration_status = 'Cancelled')     AS cancelled
     FROM event_categories ec
     LEFT JOIN participants p ON p.category_id = ec.id
     WHERE ec.is_active = 1
     GROUP BY ec.id
     ORDER BY ec.fee ASC"
);

// ── Runner category breakdown (per event category) ────────────────────────────
$runnerCats = Database::fetchAll(
    "SELECT ec.distance, p.race_category,
            COUNT(*)                                 AS total,
            SUM(p.registration_status = 'Confirmed') AS confirmed,
            SUM(p.registration_status = 'Pending')   AS pending
     FROM participants p
     JOIN event_categories ec ON ec.id = p.category_id
     WHERE p.registration_status != 'Cancelled'
     GROUP BY ec.distance, p.race_category
     ORDER BY ec.fee ASC,
       FIELD(p.race_category, 'Kanak-Kanak', 'Open', 'Veteran', 'Super Veteran')"
);

// ── Gender per event category ─────────────────────────────────────────────────
$genderBreakdown = Database::fetchAll(
    "SELECT ec.distance, p.gender, COUNT(*) AS total
     FROM participants p
     JOIN event_categories ec ON ec.id = p.category_id
     WHERE p.registration_status != 'Cancelled'
     GROUP BY ec.distance, p.gender
     ORDER BY ec.fee ASC, p.gender"
);

// ── Included Event T-Shirt sizes ──────────────────────────────────────────────
$eventShirtSizes = Database::fetchAll(
    "SELECT event_shirt_size AS size, COUNT(*) AS total
     FROM participants
     WHERE registration_status != 'Cancelled'
       AND event_shirt_size IS NOT NULL AND event_shirt_size != ''
     GROUP BY event_shirt_size
     ORDER BY FIELD(event_shirt_size,'2X','X','S','M','L','XL','2XL','3XL','4XL','5XL','6XL','7XL')"
);

// ── Included Finisher T-Shirt sizes (43KM) ────────────────────────────────────
$finisherShirtSizes = Database::fetchAll(
    "SELECT finisher_shirt_size AS size, COUNT(*) AS total
     FROM participants
     WHERE registration_status != 'Cancelled'
       AND finisher_shirt_size IS NOT NULL AND finisher_shirt_size != ''
     GROUP BY finisher_shirt_size
     ORDER BY FIELD(finisher_shirt_size,'2X','X','S','M','L','XL','2XL','3XL','4XL','5XL','6XL','7XL')"
);

// ── Extra shirt orders ────────────────────────────────────────────────────────
$extraShirts = Database::fetchAll(
    "SELECT so.shirt_type, so.size,
            SUM(so.quantity)   AS total_qty,
            SUM(so.total_price) AS revenue
     FROM shirt_orders so
     JOIN participants p ON p.id = so.participant_id
     WHERE p.registration_status != 'Cancelled'
     GROUP BY so.shirt_type, so.size
     ORDER BY so.shirt_type, FIELD(so.size,'2X','X','S','M','L','XL','2XL','3XL','4XL','5XL','6XL','7XL')"
);

// ── Extra shirt totals ────────────────────────────────────────────────────────
$extraTotals = Database::fetchAll(
    "SELECT so.shirt_type,
            SUM(so.quantity)    AS total_qty,
            COUNT(DISTINCT so.participant_id) AS buyers,
            SUM(so.total_price) AS revenue
     FROM shirt_orders so
     JOIN participants p ON p.id = so.participant_id
     WHERE p.registration_status != 'Cancelled'
     GROUP BY so.shirt_type"
);
$extraTotalsMap = [];
foreach ($extraTotals as $r) {
    $extraTotalsMap[$r['shirt_type']] = $r;
}

// ── Financial summary ─────────────────────────────────────────────────────────
$financial = Database::fetchOne(
    "SELECT
        SUM(CASE WHEN p.registration_status = 'Confirmed' THEN ec.fee ELSE 0 END) AS reg_fee_confirmed,
        SUM(CASE WHEN p.registration_status = 'Pending'   THEN ec.fee ELSE 0 END) AS reg_fee_pending,
        (SELECT COALESCE(SUM(so.total_price),0)
         FROM shirt_orders so
         JOIN participants p2 ON p2.id = so.participant_id
         WHERE p2.registration_status != 'Cancelled')                              AS shirt_revenue
     FROM participants p
     JOIN event_categories ec ON ec.id = p.category_id"
) ?? [];

// ── Helper: pivot runner cats by distance ────────────────────────────────────
$runnerPivot = [];
foreach ($runnerCats as $r) {
    $runnerPivot[$r['distance']][$r['race_category']] = $r;
}
$genderPivot = [];
foreach ($genderBreakdown as $r) {
    $genderPivot[$r['distance']][$r['gender']] = (int)$r['total'];
}

$RUNNER_ORDER = ['Kanak-Kanak', 'Open', 'Veteran', 'Super Veteran'];
$SIZES        = ['2X', 'X', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL', '6XL', '7XL'];

$pageTitle = 'Event Report';
include PUBLIC_PATH . '/admin/includes/header.php';
?>

<?php include PUBLIC_PATH . '/admin/includes/sidebar.php'; ?>

<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h1 class="admin-page-title">Event Report</h1>
        </div>
        <?php if (Auth::can('export')): ?>
        <div class="d-flex gap-2">
            <a href="export.php?type=csv" class="btn btn-sm btn-success">
                <i class="fas fa-file-csv me-1"></i> Export CSV
            </a>
            <a href="export.php?type=shirts" class="btn btn-sm btn-primary">
                <i class="fas fa-tshirt me-1"></i> Shirt Summary
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="admin-content">

        <?php
        $totalActive = (int)($overview['confirmed'] ?? 0) + (int)($overview['pending'] ?? 0);
        $regConfirmed = (float)($financial['reg_fee_confirmed'] ?? 0);
        $regPending   = (float)($financial['reg_fee_pending']   ?? 0);
        $shirtRev     = (float)($financial['shirt_revenue']     ?? 0);
        ?>

        <!-- ═══════════ OVERVIEW CARDS ═══════════ -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="admin-stat-card" style="border-top:3px solid #7C3AED;">
                    <div class="stat-icon" style="background:#ede9fe;color:#7C3AED;"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?= number_format((int)($overview['total'] ?? 0)) ?></div>
                    <div class="stat-label">Total Registrations</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="admin-stat-card" style="border-top:3px solid #16a34a;">
                    <div class="stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value"><?= number_format((int)($overview['confirmed'] ?? 0)) ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="admin-stat-card" style="border-top:3px solid #d97706;">
                    <div class="stat-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-clock"></i></div>
                    <div class="stat-value"><?= number_format((int)($overview['pending'] ?? 0)) ?></div>
                    <div class="stat-label">Pending</div>
                    <?php if ((int)($overview['pending_receipt'] ?? 0) > 0): ?>
                        <div class="small text-warning mt-1">
                            <i class="fas fa-receipt me-1"></i><?= (int)$overview['pending_receipt'] ?> with receipt
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="admin-stat-card" style="border-top:3px solid #16a34a;">
                    <div class="stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-hand-holding-heart"></i></div>
                    <div class="stat-value">RM <?= number_format($regConfirmed + $shirtRev, 2) ?></div>
                    <div class="stat-label">Total Revenue (Confirmed)</div>
                </div>
            </div>
        </div>

        <!-- Gender + Financial -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="admin-table h-100">
                    <div class="p-3 border-bottom fw-700 small text-uppercase text-muted">
                        <i class="fas fa-venus-mars me-2 text-primary"></i>Gender Breakdown
                    </div>
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span><i class="fas fa-mars me-2 text-primary"></i>Male</span>
                            <span class="fw-700"><?= number_format((int)($overview['male'] ?? 0)) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span><i class="fas fa-venus me-2 text-danger"></i>Female</span>
                            <span class="fw-700"><?= number_format((int)($overview['female'] ?? 0)) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2">
                            <span class="text-muted">Total (excl. cancelled)</span>
                            <span class="fw-700"><?= number_format($totalActive) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="admin-table h-100">
                    <div class="p-3 border-bottom fw-700 small text-uppercase text-muted">
                        <i class="fas fa-dollar-sign me-2 text-success"></i>Financial Summary
                    </div>
                    <table class="table table-sm table-borderless mb-0">
                        <thead><tr>
                            <th class="text-muted small">Item</th>
                            <th class="text-muted small text-end">Amount</th>
                        </tr></thead>
                        <tbody>
                            <tr>
                                <td>Registration Fees — Confirmed</td>
                                <td class="text-end fw-600 text-success">RM <?= number_format($regConfirmed, 2) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Registration Fees — Pending (unconfirmed)</td>
                                <td class="text-end text-muted">RM <?= number_format($regPending, 2) ?></td>
                            </tr>
                            <tr>
                                <td>Extra Shirt Sales (excl. cancelled)</td>
                                <td class="text-end fw-600 text-success">RM <?= number_format($shirtRev, 2) ?></td>
                            </tr>
                            <tr style="border-top:2px solid #e5e7eb;">
                                <td class="fw-700">Total Confirmed Revenue</td>
                                <td class="text-end fw-700 text-primary fs-5">RM <?= number_format($regConfirmed + $shirtRev, 2) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ═══════════ BY EVENT CATEGORY ═══════════ -->
        <div class="admin-table mb-4">
            <div class="p-3 border-bottom fw-700 small text-uppercase text-muted">
                <i class="fas fa-route me-2 text-primary"></i>Registrations by Event Category
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Distance</th>
                            <th class="text-center">Fee</th>
                            <th class="text-center">Total</th>
                            <th class="text-center text-success">Confirmed</th>
                            <th class="text-center text-warning">Pending</th>
                            <th class="text-center text-danger">Cancelled</th>
                            <th class="text-center">Male</th>
                            <th class="text-center">Female</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $catTotals = ['total'=>0,'confirmed'=>0,'pending'=>0,'cancelled'=>0];
                        foreach ($byCategory as $cat):
                            $catTotals['total']     += (int)$cat['total'];
                            $catTotals['confirmed'] += (int)$cat['confirmed'];
                            $catTotals['pending']   += (int)$cat['pending'];
                            $catTotals['cancelled'] += (int)$cat['cancelled'];
                            $m = $genderPivot[$cat['distance']]['Male']   ?? 0;
                            $f = $genderPivot[$cat['distance']]['Female'] ?? 0;
                        ?>
                        <tr>
                            <td class="fw-600"><?= e($cat['name']) ?></td>
                            <td><span class="badge bg-primary"><?= e($cat['distance']) ?></span></td>
                            <td class="text-center">RM <?= number_format((float)$cat['fee'], 0) ?></td>
                            <td class="text-center fw-700"><?= number_format((int)$cat['total']) ?></td>
                            <td class="text-center text-success fw-600"><?= number_format((int)$cat['confirmed']) ?></td>
                            <td class="text-center text-warning fw-600"><?= number_format((int)$cat['pending']) ?></td>
                            <td class="text-center text-danger"><?= number_format((int)$cat['cancelled']) ?></td>
                            <td class="text-center text-muted"><?= number_format($m) ?></td>
                            <td class="text-center text-muted"><?= number_format($f) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot style="background:#f9fafb;font-weight:700;">
                        <tr>
                            <td colspan="3">Total</td>
                            <td class="text-center"><?= number_format($catTotals['total']) ?></td>
                            <td class="text-center text-success"><?= number_format($catTotals['confirmed']) ?></td>
                            <td class="text-center text-warning"><?= number_format($catTotals['pending']) ?></td>
                            <td class="text-center text-danger"><?= number_format($catTotals['cancelled']) ?></td>
                            <td class="text-center"><?= number_format((int)($overview['male'] ?? 0)) ?></td>
                            <td class="text-center"><?= number_format((int)($overview['female'] ?? 0)) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- ═══════════ RUNNER CATEGORIES ═══════════ -->
        <div class="admin-table mb-4">
            <div class="p-3 border-bottom fw-700 small text-uppercase text-muted">
                <i class="fas fa-running me-2 text-primary"></i>Runner Category Breakdown
                <span class="text-muted fw-400">(excludes cancelled)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Runner Category</th>
                            <?php foreach ($byCategory as $cat): ?>
                                <th class="text-center" colspan="2">
                                    <?= e($cat['distance']) ?>
                                    <div style="font-size:10px;font-weight:400;color:#9ca3af;"><?= e($cat['name']) ?></div>
                                </th>
                            <?php endforeach; ?>
                            <th class="text-center" colspan="2">All Categories</th>
                        </tr>
                        <tr style="font-size:11px;color:#9ca3af;">
                            <th></th>
                            <?php foreach ($byCategory as $cat): ?>
                                <th class="text-center text-success">Conf.</th>
                                <th class="text-center text-warning">Pend.</th>
                            <?php endforeach; ?>
                            <th class="text-center text-success">Conf.</th>
                            <th class="text-center text-warning">Pend.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rcTotals = [];
                        foreach ($RUNNER_ORDER as $rc):
                            $rowConf = 0; $rowPend = 0;
                        ?>
                        <tr>
                            <td class="fw-600"><?= e($rc) ?></td>
                            <?php foreach ($byCategory as $cat):
                                $d = $cat['distance'];
                                $conf = (int)($runnerPivot[$d][$rc]['confirmed'] ?? 0);
                                $pend = (int)($runnerPivot[$d][$rc]['pending']   ?? 0);
                                $rowConf += $conf; $rowPend += $pend;
                            ?>
                                <td class="text-center text-success fw-600"><?= $conf ?: '—' ?></td>
                                <td class="text-center text-warning"><?= $pend ?: '—' ?></td>
                            <?php endforeach; ?>
                            <td class="text-center text-success fw-700"><?= $rowConf ?: '—' ?></td>
                            <td class="text-center text-warning fw-600"><?= $rowPend ?: '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot style="background:#f9fafb;font-weight:700;">
                        <tr>
                            <td>Total</td>
                            <?php foreach ($byCategory as $cat):
                                $d = $cat['distance'];
                                $tc = array_sum(array_column($runnerPivot[$d] ?? [], 'confirmed'));
                                $tp = array_sum(array_column($runnerPivot[$d] ?? [], 'pending'));
                            ?>
                                <td class="text-center text-success"><?= $tc ?></td>
                                <td class="text-center text-warning"><?= $tp ?></td>
                            <?php endforeach; ?>
                            <td class="text-center text-success"><?= array_sum(array_column($runnerCats, 'confirmed')) ?></td>
                            <td class="text-center text-warning"><?= array_sum(array_column($runnerCats, 'pending')) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- ═══════════ T-SHIRT SUMMARY ═══════════ -->
        <div class="row g-3 mb-4">

            <!-- Included Event T-Shirt -->
            <div class="col-md-4">
                <div class="admin-table h-100">
                    <div class="p-3 border-bottom fw-700 small text-uppercase text-muted">
                        <i class="fas fa-tshirt me-2" style="color:#2563eb;"></i>Event T-Shirt
                        <div class="fw-400 text-success" style="font-size:11px;text-transform:none;margin-top:2px;">
                            Included — all participants (excl. cancelled)
                        </div>
                    </div>
                    <table class="table table-sm mb-0">
                        <thead><tr>
                            <th>Size</th>
                            <th class="text-center">Count</th>
                            <th class="text-center">%</th>
                        </tr></thead>
                        <tbody>
                            <?php
                            $eventTotal = array_sum(array_column($eventShirtSizes, 'total'));
                            foreach ($eventShirtSizes as $s):
                                $pct = $eventTotal > 0 ? round($s['total'] / $eventTotal * 100) : 0;
                            ?>
                            <tr>
                                <td><span class="badge bg-info text-dark"><?= e($s['size']) ?></span></td>
                                <td class="text-center fw-600"><?= number_format((int)$s['total']) ?></td>
                                <td class="text-center text-muted"><?= $pct ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($eventShirtSizes)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">No data</td></tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot style="background:#f9fafb;font-weight:700;">
                            <tr>
                                <td>Total</td>
                                <td class="text-center"><?= number_format($eventTotal) ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Included Finisher T-Shirt -->
            <div class="col-md-4">
                <div class="admin-table h-100">
                    <div class="p-3 border-bottom fw-700 small text-uppercase text-muted">
                        <i class="fas fa-medal me-2" style="color:#d97706;"></i>Finisher T-Shirt
                        <div class="fw-400 text-success" style="font-size:11px;text-transform:none;margin-top:2px;">
                            Included — 43KM participants (excl. cancelled)
                        </div>
                    </div>
                    <table class="table table-sm mb-0">
                        <thead><tr>
                            <th>Size</th>
                            <th class="text-center">Count</th>
                            <th class="text-center">%</th>
                        </tr></thead>
                        <tbody>
                            <?php
                            $finTotal = array_sum(array_column($finisherShirtSizes, 'total'));
                            foreach ($finisherShirtSizes as $s):
                                $pct = $finTotal > 0 ? round($s['total'] / $finTotal * 100) : 0;
                            ?>
                            <tr>
                                <td><span class="badge bg-warning text-dark"><?= e($s['size']) ?></span></td>
                                <td class="text-center fw-600"><?= number_format((int)$s['total']) ?></td>
                                <td class="text-center text-muted"><?= $pct ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($finisherShirtSizes)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">No data yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot style="background:#f9fafb;font-weight:700;">
                            <tr>
                                <td>Total</td>
                                <td class="text-center"><?= number_format($finTotal) ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Extra shirt purchases -->
            <div class="col-md-4">
                <div class="admin-table h-100">
                    <div class="p-3 border-bottom fw-700 small text-uppercase text-muted">
                        <i class="fas fa-shopping-cart me-2 text-primary"></i>Extra Shirt Purchases
                        <div class="fw-400 text-muted" style="font-size:11px;text-transform:none;margin-top:2px;">
                            Additional units purchased (excl. cancelled)
                        </div>
                    </div>
                    <div class="p-3">
                        <?php foreach (['Event', 'Finisher'] as $type):
                            $t = $extraTotalsMap[$type] ?? null;
                            $sizes = array_filter($extraShirts, fn($r) => $r['shirt_type'] === $type);
                            $color = $type === 'Event' ? '#2563eb' : '#d97706';
                            $bgCol = $type === 'Event' ? '#eff6ff' : '#fef3c7';
                        ?>
                        <div class="rounded-3 p-3 mb-3" style="background:<?= $bgCol ?>;border-left:3px solid <?= $color ?>;">
                            <div class="fw-700 mb-2" style="color:<?= $color ?>;font-size:13px;">
                                <i class="fas fa-tshirt me-1"></i> Extra <?= $type ?> T-Shirt
                            </div>
                            <?php if ($t): ?>
                                <div class="small mb-1">
                                    <span class="text-muted">Buyers:</span> <strong><?= number_format((int)$t['buyers']) ?></strong>
                                    &nbsp;|&nbsp;
                                    <span class="text-muted">Units:</span> <strong><?= number_format((int)$t['total_qty']) ?></strong>
                                    &nbsp;|&nbsp;
                                    <span class="text-muted">Revenue:</span> <strong>RM <?= number_format((float)$t['revenue'], 2) ?></strong>
                                </div>
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    <?php foreach ($sizes as $s): ?>
                                        <span class="badge" style="background:<?= $color ?>;font-size:11px;">
                                            <?= e($s['size']) ?> ×<?= (int)$s['total_qty'] ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-muted small">No purchases yet</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>

                        <?php if (!empty($extraTotals)): ?>
                        <div class="border-top pt-2 mt-1">
                            <div class="d-flex justify-content-between small fw-700">
                                <span>Total Shirt Revenue</span>
                                <span class="text-success">RM <?= number_format($shirtRev, 2) ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════ COMPLETE SHIRT ORDER TABLE ═══════════ -->
        <?php if (!empty($extraShirts)): ?>
        <div class="admin-table mb-4">
            <div class="p-3 border-bottom fw-700 small text-uppercase text-muted">
                <i class="fas fa-table me-2 text-primary"></i>Extra Shirt Orders — Size Detail
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Size</th>
                            <th class="text-center">Units Ordered</th>
                            <th class="text-end">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($extraShirts as $s): ?>
                        <tr>
                            <td>
                                <span class="badge" style="background:<?= $s['shirt_type'] === 'Event' ? '#2563eb' : '#d97706' ?>;">
                                    <?= e($s['shirt_type']) ?>
                                </span>
                            </td>
                            <td><?= e($s['size']) ?></td>
                            <td class="text-center fw-600"><?= number_format((int)$s['total_qty']) ?></td>
                            <td class="text-end">RM <?= number_format((float)$s['revenue'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot style="background:#f9fafb;font-weight:700;">
                        <tr>
                            <td colspan="2">Total</td>
                            <td class="text-center">
                                <?= number_format(array_sum(array_column($extraShirts, 'total_qty'))) ?>
                            </td>
                            <td class="text-end text-success">RM <?= number_format($shirtRev, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ═══════════ RECEIPT STATUS ═══════════ -->
        <div class="admin-table mb-4">
            <div class="p-3 border-bottom fw-700 small text-uppercase text-muted">
                <i class="fas fa-receipt me-2 text-warning"></i>Pending Participants — Receipt Status
            </div>
            <div class="p-4">
                <?php
                $pendTotal   = (int)($overview['pending'] ?? 0);
                $pendReceipt = (int)($overview['pending_receipt'] ?? 0);
                $pendNoRec   = $pendTotal - $pendReceipt;
                $recPct      = $pendTotal > 0 ? round($pendReceipt / $pendTotal * 100) : 0;
                ?>
                <div class="row g-3">
                    <div class="col-sm-4 text-center">
                        <div class="fw-700 fs-3 text-warning"><?= number_format($pendTotal) ?></div>
                        <div class="text-muted small">Total Pending</div>
                    </div>
                    <div class="col-sm-4 text-center">
                        <div class="fw-700 fs-3 text-success"><?= number_format($pendReceipt) ?></div>
                        <div class="text-muted small">Receipt Uploaded</div>
                        <a href="registrations.php?status=PendingWithReceipt" class="btn btn-xs btn-outline-success mt-1" style="font-size:11px;padding:2px 8px;">Review →</a>
                    </div>
                    <div class="col-sm-4 text-center">
                        <div class="fw-700 fs-3 text-muted"><?= number_format($pendNoRec) ?></div>
                        <div class="text-muted small">No Receipt Yet</div>
                    </div>
                </div>
                <?php if ($pendTotal > 0): ?>
                <div class="mt-3">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Receipt upload rate</span><span><?= $recPct ?>%</span>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar bg-success" style="width:<?= $recPct ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /admin-content -->
</div><!-- /admin-main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
