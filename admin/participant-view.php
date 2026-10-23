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
$id    = (int) ($_GET['id'] ?? 0);

if (!$id) {
    setFlash('error', 'Invalid participant.');
    redirect(APP_URL . '/admin/registrations.php');
}

$participant = Database::fetchOne(
    'SELECT p.*, ec.name AS category_name, ec.distance, ec.fee AS category_fee
     FROM participants p
     JOIN event_categories ec ON ec.id = p.category_id
     WHERE p.id = ?',
    [$id]
);

if (!$participant) {
    setFlash('error', 'Participant not found.');
    redirect(APP_URL . '/admin/registrations.php');
}

$shirtOrders  = Database::fetchAll('SELECT * FROM shirt_orders WHERE participant_id = ? ORDER BY id', [$id]);
$allCategories = Database::fetchAll('SELECT * FROM event_categories WHERE is_active = 1 ORDER BY fee ASC');

$SHIRT_SIZES = ['2X', 'X', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL', '6XL', '7XL'];
$RUNNER_CATS = ['Kanak-Kanak', 'Open', 'Veteran', 'Super Veteran'];

// ── Delete participant ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_participant') {
    Auth::requireCan('registrations', 'delete');
    CSRF::verifyOrFail();
    $deletedShirts = shirtOrdersAuditMap($id);
    Database::execute('DELETE FROM shirt_orders WHERE participant_id = ?', [$id]);
    Database::execute('DELETE FROM payments    WHERE participant_id = ?', [$id]);
    Database::execute('DELETE FROM participants WHERE id = ?', [$id]);
    auditLog('delete_participant', 'participants', $id,
             participantAuditSnapshot($participant) + $deletedShirts,
             null, (int)$admin['id']);
    setFlash('success', 'Participant "' . e($participant['full_name']) . '" has been permanently deleted.');
    redirect(APP_URL . '/admin/registrations.php');
}

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::can('registrations', 'edit')) {
    CSRF::verifyOrFail();
    $action = $_POST['action'] ?? '';

    // ── Update status only ───────────────────────────────────────────────────
    if ($action === 'update_status') {
        $newStatus = $_POST['registration_status'] ?? '';
        $allowed   = ['Pending', 'Confirmed', 'Cancelled'];
        if (in_array($newStatus, $allowed, true)) {
            $old = $participant['registration_status'];
            Database::execute(
                'UPDATE participants SET registration_status = ? WHERE id = ?',
                [$newStatus, $id]
            );
            if ($old !== $newStatus) {
                auditLog('update_status', 'participants', $id,
                         ['status' => $old], ['status' => $newStatus], (int) $admin['id']);
            }

            if ($newStatus === 'Confirmed' && $old !== 'Confirmed') {
                try {
                    require_once APP_PATH . '/email.php';
                    EmailService::sendRegistrationConfirmed($participant);
                } catch (\Throwable $emailEx) {
                    error_log('Confirmation email failed: ' . $emailEx->getMessage());
                }
            }

            setFlash('success', 'Status updated to ' . $newStatus . '.');
            redirect(APP_URL . '/admin/participant-view.php?id=' . $id);
        }
    }

    // ── Quick-edit race details (inline collapse form) ───────────────────────
    if ($action === 'update_race_details') {
        $raceCat      = sanitize($_POST['race_category']       ?? '');
        $eventSize    = sanitize($_POST['event_shirt_size']    ?? '');
        $finisherSize = sanitize($_POST['finisher_shirt_size'] ?? '') ?: null;
        $is43km       = $participant['distance'] === '43KM';

        $editErrors = [];
        if (!in_array($raceCat, $RUNNER_CATS, true))   $editErrors[] = 'Invalid runner category.';
        if (!in_array($eventSize, $SHIRT_SIZES, true)) $editErrors[] = 'Invalid event shirt size.';

        if (empty($editErrors)) {
            $oldVals = [
                'race_category'    => $participant['race_category'],
                'event_shirt_size' => $participant['event_shirt_size'],
            ];
            if ($is43km) $oldVals['finisher_shirt_size'] = $participant['finisher_shirt_size'];
            $oldVals += shirtOrdersAuditMap($id);

            Database::execute(
                'UPDATE participants SET race_category = ?, event_shirt_size = ?, finisher_shirt_size = ? WHERE id = ?',
                [$raceCat, $eventSize, ($is43km ? $finisherSize : null), $id]
            );
            $newVals = ['race_category' => $raceCat, 'event_shirt_size' => $eventSize];
            if ($is43km) $newVals['finisher_shirt_size'] = $finisherSize;

            // Update optional shirt order sizes and quantities
            $soIds   = array_map('intval', $_POST['shirt_id']   ?? []);
            $soSizes = $_POST['shirt_size'] ?? [];
            $soQtys  = $_POST['shirt_qty']  ?? [];
            foreach ($soIds as $i => $soId) {
                if (!$soId) continue;
                $soSize = in_array($soSizes[$i] ?? '', $SHIRT_SIZES, true) ? $soSizes[$i] : null;
                $soQty  = max(1, (int)($soQtys[$i] ?? 1));
                if ($soSize) {
                    Database::execute(
                        'UPDATE shirt_orders SET size = ?, quantity = ? WHERE id = ? AND participant_id = ?',
                        [$soSize, $soQty, $soId, $id]
                    );
                }
            }

            [$logOld, $logNew] = auditDiff($oldVals, $newVals + shirtOrdersAuditMap($id));
            if ($logNew) {
                auditLog('update_race_details', 'participants', $id, $logOld, $logNew, (int) $admin['id']);
            }

            setFlash('success', 'Race details updated.');
            redirect(APP_URL . '/admin/participant-view.php?id=' . $id);
        } else {
            setFlash('error', implode(' ', $editErrors));
            redirect(APP_URL . '/admin/participant-view.php?id=' . $id);
        }
    }

    // ── Full participant edit ────────────────────────────────────────────────
    if ($action === 'update_participant') {
        $newFullName  = sanitize($_POST['full_name']                ?? '');
        $newIC        = sanitize($_POST['ic_number']               ?? '');
        $newContact   = sanitize($_POST['contact_number']          ?? '');
        $newEmail     = sanitize($_POST['email']                   ?? '');
        $newGender    = sanitize($_POST['gender']                  ?? '');
        $newDob       = sanitize($_POST['date_of_birth']           ?? '');
        $newAddress   = sanitize($_POST['address']                 ?? '');
        $newEcName    = sanitize($_POST['emergency_contact_name']   ?? '');
        $newEcNumber  = sanitize($_POST['emergency_contact_number'] ?? '');
        $newCatId     = (int) ($_POST['category_id']              ?? 0);
        $newRaceCat   = sanitize($_POST['race_category']           ?? '');
        $newEventSize = sanitize($_POST['event_shirt_size']        ?? '');
        $newFinSize   = sanitize($_POST['finisher_shirt_size']     ?? '') ?: null;

        $editErrors = [];
        if (empty($newFullName))  $editErrors[] = 'Full name is required.';
        if (empty($newIC))        $editErrors[] = 'IC number is required.';
        if (empty($newContact))   $editErrors[] = 'Contact number is required.';
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) $editErrors[] = 'Invalid email address.';
        if (!in_array($newGender, ['Male', 'Female'], true)) $editErrors[] = 'Invalid gender.';
        if (empty($newDob) || !strtotime($newDob)) $editErrors[] = 'Invalid date of birth.';
        if (empty($newAddress))   $editErrors[] = 'Address is required.';
        if (!in_array($newRaceCat, $RUNNER_CATS, true)) $editErrors[] = 'Invalid runner category.';
        if (!in_array($newEventSize, $SHIRT_SIZES, true)) $editErrors[] = 'Invalid event shirt size.';

        $newCat = null;
        if ($newCatId) {
            $newCat = Database::fetchOne('SELECT * FROM event_categories WHERE id = ?', [$newCatId]);
        }
        if (!$newCat) $editErrors[] = 'Invalid event category.';

        // Validate receipt file if one was submitted
        $newReceiptFilename = null;
        if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] !== UPLOAD_ERR_NO_FILE) {
            $rf = $_FILES['payment_receipt'];
            if ($rf['error'] !== UPLOAD_ERR_OK) {
                $editErrors[] = 'Receipt upload failed (error code ' . $rf['error'] . ').';
            } elseif ($rf['size'] > 5 * 1024 * 1024) {
                $editErrors[] = 'Receipt file exceeds 5 MB limit.';
            } else {
                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $rf['tmp_name']);
                finfo_close($finfo);
                $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                if (!in_array($mimeType, $allowed, true)) {
                    $editErrors[] = 'Invalid receipt file type. Allowed: JPG, PNG, GIF, PDF.';
                } else {
                    $ext = strtolower(pathinfo($rf['name'], PATHINFO_EXTENSION));
                    $newReceiptFilename = 'receipt_' . $id . '_' . time() . '.' . $ext;
                }
            }
        }

        if (empty($editErrors)) {
            $newAge  = (int) (new \DateTime())->diff(new \DateTime($newDob))->y;
            $is43km  = ($newCat['distance'] === '43KM');

            // Build old/new value maps for diffing
            $oldMap = [
                'full_name'                => $participant['full_name'],
                'ic_number'               => $participant['ic_number'],
                'contact_number'          => $participant['contact_number'],
                'email'                   => $participant['email'],
                'gender'                  => $participant['gender'],
                'date_of_birth'           => $participant['date_of_birth'],
                'age'                     => (string) $participant['age'],
                'address'                 => $participant['address'],
                'emergency_contact_name'  => $participant['emergency_contact_name'],
                'emergency_contact_number'=> $participant['emergency_contact_number'],
                'category'                => (string) $participant['category_name'],
                'race_category'           => $participant['race_category'],
                'event_shirt_size'        => $participant['event_shirt_size'],
                'finisher_shirt_size'     => (string) ($participant['finisher_shirt_size'] ?? ''),
            ];
            $newMap = [
                'full_name'                => $newFullName,
                'ic_number'               => $newIC,
                'contact_number'          => $newContact,
                'email'                   => $newEmail,
                'gender'                  => $newGender,
                'date_of_birth'           => $newDob,
                'age'                     => (string) $newAge,
                'address'                 => $newAddress,
                'emergency_contact_name'  => $newEcName,
                'emergency_contact_number'=> $newEcNumber,
                'category'                => (string) $newCat['name'],
                'race_category'           => $newRaceCat,
                'event_shirt_size'        => $newEventSize,
                'finisher_shirt_size'     => (string) ($is43km ? $newFinSize : ''),
            ];
            $oldMap += shirtOrdersAuditMap($id);

            Database::execute(
                'UPDATE participants SET
                   full_name = ?, ic_number = ?, contact_number = ?, email = ?,
                   gender = ?, date_of_birth = ?, age = ?, address = ?,
                   emergency_contact_name = ?, emergency_contact_number = ?,
                   category_id = ?, race_category = ?,
                   event_shirt_size = ?, finisher_shirt_size = ?
                 WHERE id = ?',
                [
                    $newFullName, $newIC, $newContact, $newEmail,
                    $newGender, $newDob, $newAge, $newAddress,
                    $newEcName, $newEcNumber,
                    $newCatId, $newRaceCat,
                    $newEventSize, ($is43km ? $newFinSize : null),
                    $id,
                ]
            );

            // Update optional shirt order sizes and quantities
            $soIds   = array_map('intval', $_POST['shirt_id']   ?? []);
            $soSizes = $_POST['shirt_size'] ?? [];
            $soQtys  = $_POST['shirt_qty']  ?? [];
            foreach ($soIds as $i => $soId) {
                if (!$soId) continue;
                $soSize = in_array($soSizes[$i] ?? '', $SHIRT_SIZES, true) ? $soSizes[$i] : null;
                $soQty  = max(1, (int)($soQtys[$i] ?? 1));
                if ($soSize) {
                    Database::execute(
                        'UPDATE shirt_orders SET size = ?, quantity = ? WHERE id = ? AND participant_id = ?',
                        [$soSize, $soQty, $soId, $id]
                    );
                }
            }

            [$logOld, $logNew] = auditDiff($oldMap, $newMap + shirtOrdersAuditMap($id));
            if ($logNew) {
                auditLog('update_participant', 'participants', $id, $logOld, $logNew, (int) $admin['id']);
            }

            // Save receipt changes
            $receiptDir = PUBLIC_PATH . '/assets/uploads/receipts/';
            if ($newReceiptFilename !== null) {
                if (move_uploaded_file($_FILES['payment_receipt']['tmp_name'], $receiptDir . $newReceiptFilename)) {
                    if (!empty($participant['payment_receipt'])) {
                        $oldPath = $receiptDir . $participant['payment_receipt'];
                        if (is_file($oldPath)) @unlink($oldPath);
                    }
                    Database::execute('UPDATE participants SET payment_receipt = ? WHERE id = ?', [$newReceiptFilename, $id]);
                    auditLog('update_receipt', 'participants', $id,
                             ['payment_receipt' => $participant['payment_receipt'] ?? ''],
                             ['payment_receipt' => $newReceiptFilename], (int)$admin['id']);
                }
            } elseif (!empty($_POST['clear_receipt']) && !empty($participant['payment_receipt'])) {
                $oldPath = $receiptDir . $participant['payment_receipt'];
                if (is_file($oldPath)) @unlink($oldPath);
                Database::execute('UPDATE participants SET payment_receipt = NULL WHERE id = ?', [$id]);
                auditLog('update_receipt', 'participants', $id,
                         ['payment_receipt' => $participant['payment_receipt']],
                         ['payment_receipt' => ''], (int)$admin['id']);
            }

            setFlash('success', 'Participant information updated successfully.');
            redirect(APP_URL . '/admin/participant-view.php?id=' . $id);
        } else {
            setFlash('error', implode(' ', $editErrors));
            redirect(APP_URL . '/admin/participant-view.php?id=' . $id);
        }
    }
}

$statusMap = ['Confirmed' => 'badge-paid', 'Pending' => 'badge-pending', 'Cancelled' => 'badge-failed'];

$pageTitle = 'Participant — ' . $participant['full_name'];
include PUBLIC_PATH . '/admin/includes/header.php';
?>

<?php include PUBLIC_PATH . '/admin/includes/sidebar.php'; ?>

<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <a href="registrations.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
            <h1 class="admin-page-title">Participant Detail</h1>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="<?= $statusMap[$participant['registration_status']] ?? 'badge-pending' ?> fs-6">
                <?= e($participant['registration_status']) ?>
            </span>
            <?php if (Auth::can('registrations', 'edit')): ?>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editParticipantModal">
                <i class="fas fa-pen me-1"></i> Edit All Details
            </button>
            <?php endif; ?>
            <?php if (Auth::can('registrations', 'delete')): ?>
            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteParticipantModal">
                <i class="fas fa-trash-alt me-1"></i> Delete
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-content">
        <?= renderFlash() ?>

        <div class="row g-4">
            <!-- Left: Participant Info -->
            <div class="col-lg-8">

                <!-- Personal Details -->
                <div class="admin-table mb-4">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-700 mb-0"><i class="fas fa-user me-2 text-primary"></i>Personal Information</h6>
                        <code class="text-primary"><?= e($participant['reference_number']) ?></code>
                    </div>
                    <div class="p-4">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="text-muted small">Full Name</div>
                                <div class="fw-600"><?= e($participant['full_name']) ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">IC Number</div>
                                <div class="fw-600"><?= e($participant['ic_number']) ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Email</div>
                                <div><?= e($participant['email']) ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Contact Number</div>
                                <div><?= e($participant['contact_number']) ?></div>
                            </div>
                            <div class="col-sm-3">
                                <div class="text-muted small">Gender</div>
                                <div><?= e($participant['gender']) ?></div>
                            </div>
                            <div class="col-sm-3">
                                <div class="text-muted small">Age</div>
                                <div><?= e($participant['age']) ?> years</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Date of Birth</div>
                                <div><?= date('d M Y', strtotime($participant['date_of_birth'])) ?></div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted small">Address</div>
                                <div><?= nl2br(e($participant['address'])) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="admin-table mb-4">
                    <div class="p-3 border-bottom">
                        <h6 class="fw-700 mb-0"><i class="fas fa-phone-alt me-2 text-danger"></i>Emergency Contact</h6>
                    </div>
                    <div class="p-4">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="text-muted small">Name</div>
                                <div class="fw-600"><?= e($participant['emergency_contact_name']) ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Phone</div>
                                <div><?= e($participant['emergency_contact_number']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Race Details -->
                <div class="admin-table mb-4">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-700 mb-0"><i class="fas fa-trophy me-2 text-warning"></i>Race Details</h6>
                        <?php if (Auth::can('registrations', 'edit')): ?>
                        <button class="btn btn-sm btn-outline-secondary py-0" type="button"
                                data-bs-toggle="collapse" data-bs-target="#editRaceForm">
                            <i class="fas fa-pen me-1"></i> Quick Edit
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="p-4">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="text-muted small">Category</div>
                                <div class="fw-600"><?= e($participant['category_name']) ?> <span class="badge bg-primary ms-1"><?= e($participant['distance']) ?></span></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Runner Category</div>
                                <div><span class="badge bg-secondary"><?= e($participant['race_category'] ?? '—') ?></span></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Registration Fee</div>
                                <div class="fw-600"><?= formatMYR((float) $participant['category_fee']) ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Event T-Shirt (Included)</div>
                                <div><span class="badge bg-info text-dark"><?= e($participant['event_shirt_size'] ?? '—') ?></span></div>
                            </div>
                            <?php if ($participant['finisher_shirt_size']): ?>
                            <div class="col-sm-6">
                                <div class="text-muted small">Finisher T-Shirt (Included)</div>
                                <div><span class="badge bg-warning text-dark"><?= e($participant['finisher_shirt_size']) ?></span></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($shirtOrders)): ?>
                            <div class="col-12">
                                <div class="text-muted small mb-2">Optional Shirt Purchases</div>
                                <?php foreach ($shirtOrders as $so): ?>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge bg-dark"><?= e($so['shirt_type']) ?></span>
                                        <span class="badge bg-secondary"><?= e($so['size']) ?></span>
                                        <span class="text-muted small">× <?= $so['quantity'] ?> = <?= formatMYR((float) $so['total_price']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (Auth::can('registrations', 'edit')): ?>
                    <div class="collapse" id="editRaceForm">
                        <div class="p-3 border-top bg-light">
                            <form method="POST">
                                <?= CSRF::field() ?>
                                <input type="hidden" name="action" value="update_race_details">

                                <!-- Included shirt sizes & runner category -->
                                <div class="row g-2 mb-3">
                                    <div class="col-sm-4">
                                        <label class="form-label small fw-600 mb-1">Runner Category</label>
                                        <select name="race_category" class="form-select form-select-sm" required>
                                            <?php foreach ($RUNNER_CATS as $rc): ?>
                                                <option value="<?= $rc ?>" <?= ($participant['race_category'] ?? '') === $rc ? 'selected' : '' ?>><?= $rc ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label small fw-600 mb-1">Included Event T-Shirt</label>
                                        <select name="event_shirt_size" class="form-select form-select-sm" required>
                                            <?php foreach ($SHIRT_SIZES as $sz): ?>
                                                <option value="<?= $sz ?>" <?= ($participant['event_shirt_size'] ?? '') === $sz ? 'selected' : '' ?>><?= $sz ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php if ($participant['distance'] === '43KM'): ?>
                                    <div class="col-sm-4">
                                        <label class="form-label small fw-600 mb-1">Included Finisher T-Shirt</label>
                                        <select name="finisher_shirt_size" class="form-select form-select-sm">
                                            <option value="">— None —</option>
                                            <?php foreach ($SHIRT_SIZES as $sz): ?>
                                                <option value="<?= $sz ?>" <?= ($participant['finisher_shirt_size'] ?? '') === $sz ? 'selected' : '' ?>><?= $sz ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Optional shirt purchases -->
                                <?php if (!empty($shirtOrders)): ?>
                                <div class="small fw-700 text-muted mb-2" style="border-top:1px solid #dee2e6;padding-top:10px;">
                                    <i class="fas fa-shopping-cart me-1 text-info"></i> Optional T-Shirt Purchases
                                </div>
                                <?php foreach ($shirtOrders as $so): ?>
                                <input type="hidden" name="shirt_id[]" value="<?= $so['id'] ?>">
                                <div class="row g-2 mb-2">
                                    <div class="col-sm-4">
                                        <label class="form-label small fw-600 mb-1"><?= e($so['shirt_type']) ?></label>
                                        <input type="text" class="form-control form-control-sm bg-white" value="<?= e($so['shirt_type']) ?>" readonly>
                                    </div>
                                    <div class="col-sm-3">
                                        <label class="form-label small fw-600 mb-1">Size</label>
                                        <select name="shirt_size[]" class="form-select form-select-sm">
                                            <?php foreach ($SHIRT_SIZES as $sz): ?>
                                                <option value="<?= $sz ?>" <?= $so['size'] === $sz ? 'selected' : '' ?>><?= $sz ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-2">
                                        <label class="form-label small fw-600 mb-1">Qty</label>
                                        <input type="number" name="shirt_qty[]" class="form-control form-control-sm"
                                               value="<?= (int)$so['quantity'] ?>" min="1" max="10">
                                    </div>
                                    <div class="col-sm-3 d-flex align-items-end pb-1">
                                        <span class="text-muted small">Paid: <?= formatMYR((float)$so['total_price']) ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>

                                <button type="submit" class="btn btn-primary btn-sm mt-1">
                                    <i class="fas fa-save me-1"></i> Save
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Payment Receipt -->
                <div class="admin-table mb-4">
                    <div class="p-3 border-bottom">
                        <h6 class="fw-700 mb-0"><i class="fas fa-receipt me-2 text-success"></i>Payment Receipt</h6>
                    </div>
                    <div class="p-4">
                        <?php if (!empty($participant['payment_receipt'])): ?>
                            <div class="d-flex align-items-center gap-3">
                                <i class="fas fa-file-image fa-2x text-success"></i>
                                <div>
                                    <div class="fw-600 small"><?= e($participant['payment_receipt']) ?></div>
                                    <a href="<?= APP_URL ?>/assets/uploads/receipts/<?= urlencode($participant['payment_receipt']) ?>"
                                       target="_blank" class="btn btn-sm btn-outline-success mt-1">
                                        <i class="fas fa-eye me-1"></i> View Receipt
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0"><i class="fas fa-exclamation-circle me-1"></i> No receipt uploaded yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Status & Actions -->
            <div class="col-lg-4">
                <!-- Current Status -->
                <div class="admin-table mb-4">
                    <div class="p-3 border-bottom">
                        <h6 class="fw-700 mb-0"><i class="fas fa-info-circle me-2 text-primary"></i>Status</h6>
                    </div>
                    <div class="p-4 text-center">
                        <div class="mb-2 <?= $statusMap[$participant['registration_status']] ?? 'badge-pending' ?>" style="font-size:16px;padding:8px 20px;">
                            <?= e($participant['registration_status']) ?>
                        </div>
                        <div class="text-muted small mt-2">
                            Registered: <?= date('d M Y H:i', strtotime($participant['created_at'])) ?>
                        </div>
                        <?php if ($participant['health_declaration'] && $participant['terms_accepted']): ?>
                        <div class="mt-2">
                            <span class="badge bg-success me-1"><i class="fas fa-check me-1"></i>Health Declared</span>
                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Terms Accepted</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (Auth::can('registrations', 'edit')): ?>
                    <div class="p-3 border-top">
                        <form method="POST">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="action" value="update_status">
                            <label class="form-label small fw-600">Update Status</label>
                            <select name="registration_status" class="form-select form-select-sm mb-2">
                                <?php foreach (['Pending', 'Confirmed', 'Cancelled'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $participant['registration_status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="fas fa-save me-1"></i> Update Status
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="admin-table">
                    <div class="p-3 border-bottom">
                        <h6 class="fw-700 mb-0"><i class="fas fa-link me-2 text-muted"></i>Quick Actions</h6>
                    </div>
                    <div class="p-3 d-grid gap-2">
                        <a href="mailto:<?= e($participant['email']) ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-envelope me-1"></i> Email Participant
                        </a>
                        <a href="https://wa.me/60<?= ltrim(preg_replace('/[^0-9]/', '', $participant['contact_number']), '0') ?>"
                           target="_blank" class="btn btn-outline-success btn-sm">
                            <i class="fab fa-whatsapp me-1"></i> WhatsApp
                        </a>
                        <a href="registrations.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back to List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (Auth::can('registrations', 'edit')): ?>
<!-- ── Edit All Details Modal ───────────────────────────────────────────────── -->
<div class="modal fade" id="editParticipantModal" tabindex="-1" aria-labelledby="editParticipantModalLabel">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" id="editParticipantForm"
                  style="display:flex;flex-direction:column;flex:1 1 auto;min-height:0;overflow:hidden;">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="update_participant">

                <div class="modal-header" style="background:linear-gradient(135deg,#6B21A8 0%,#7C3AED 100%);color:#fff;border-bottom:3px solid #A855F7;text-shadow:0 1px 2px rgba(0,0,0,.25);">
                    <h5 class="modal-title fw-700" id="editParticipantModalLabel">
                        <i class="fas fa-pen me-2"></i>Edit Participant Information
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <!-- Personal Information -->
                    <div class="mb-1 pb-1 border-bottom">
                        <span class="text-uppercase fw-700 small" style="letter-spacing:.08em;color:#6b7280;">
                            <i class="fas fa-user me-1 text-primary"></i> Personal Information
                        </span>
                    </div>
                    <div class="row g-3 mb-4 mt-1">
                        <div class="col-sm-6">
                            <label class="form-label fw-600 small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control form-control-sm"
                                   value="<?= e($participant['full_name']) ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-600 small">IC Number <span class="text-danger">*</span></label>
                            <input type="text" name="ic_number" class="form-control form-control-sm"
                                   value="<?= e($participant['ic_number']) ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-600 small">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control form-control-sm"
                                   value="<?= e($participant['email']) ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-600 small">Contact Number <span class="text-danger">*</span></label>
                            <input type="text" name="contact_number" class="form-control form-control-sm"
                                   value="<?= e($participant['contact_number']) ?>" required>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-600 small">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select form-select-sm" required>
                                <option value="Male"   <?= $participant['gender'] === 'Male'   ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= $participant['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-600 small">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" name="date_of_birth" class="form-control form-control-sm"
                                   value="<?= e($participant['date_of_birth']) ?>" required>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-600 small">Age (auto-calculated)</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="<?= e($participant['age']) ?> years" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-600 small">Address <span class="text-danger">*</span></label>
                            <textarea name="address" class="form-control form-control-sm" rows="2" required><?= e($participant['address']) ?></textarea>
                        </div>
                    </div>

                    <!-- Emergency Contact -->
                    <div class="mb-1 pb-1 border-bottom">
                        <span class="text-uppercase fw-700 small" style="letter-spacing:.08em;color:#6b7280;">
                            <i class="fas fa-phone-alt me-1 text-danger"></i> Emergency Contact
                        </span>
                    </div>
                    <div class="row g-3 mb-4 mt-1">
                        <div class="col-sm-6">
                            <label class="form-label fw-600 small">Contact Name</label>
                            <input type="text" name="emergency_contact_name" class="form-control form-control-sm"
                                   value="<?= e($participant['emergency_contact_name']) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-600 small">Contact Number</label>
                            <input type="text" name="emergency_contact_number" class="form-control form-control-sm"
                                   value="<?= e($participant['emergency_contact_number']) ?>">
                        </div>
                    </div>

                    <!-- Race & Shirt Details -->
                    <div class="mb-1 pb-1 border-bottom">
                        <span class="text-uppercase fw-700 small" style="letter-spacing:.08em;color:#6b7280;">
                            <i class="fas fa-trophy me-1 text-warning"></i> Race &amp; Shirt Details
                        </span>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-sm-6">
                            <label class="form-label fw-600 small">Event Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select form-select-sm" id="editCategoryId" required>
                                <?php foreach ($allCategories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"
                                            data-distance="<?= e($cat['distance']) ?>"
                                            <?= (int) $participant['category_id'] === (int) $cat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?> (<?= e($cat['distance']) ?> — <?= formatMYR((float) $cat['fee']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-600 small">Runner Category <span class="text-danger">*</span></label>
                            <select name="race_category" class="form-select form-select-sm" required>
                                <?php foreach ($RUNNER_CATS as $rc): ?>
                                    <option value="<?= $rc ?>" <?= ($participant['race_category'] ?? '') === $rc ? 'selected' : '' ?>><?= $rc ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-600 small">Event T-Shirt Size <span class="text-danger">*</span></label>
                            <select name="event_shirt_size" class="form-select form-select-sm" required>
                                <?php foreach ($SHIRT_SIZES as $sz): ?>
                                    <option value="<?= $sz ?>" <?= ($participant['event_shirt_size'] ?? '') === $sz ? 'selected' : '' ?>><?= $sz ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6" id="finisherSizeWrap" style="<?= $participant['distance'] !== '43KM' ? 'display:none;' : '' ?>">
                            <label class="form-label fw-600 small">Finisher T-Shirt Size <span class="text-muted">(43KM only)</span></label>
                            <select name="finisher_shirt_size" class="form-select form-select-sm">
                                <option value="">— None —</option>
                                <?php foreach ($SHIRT_SIZES as $sz): ?>
                                    <option value="<?= $sz ?>" <?= ($participant['finisher_shirt_size'] ?? '') === $sz ? 'selected' : '' ?>><?= $sz ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Optional T-Shirt Purchases -->
                    <?php if (!empty($shirtOrders)): ?>
                    <div class="mb-1 pb-1 border-bottom mt-4">
                        <span class="text-uppercase fw-700 small" style="letter-spacing:.08em;color:#6b7280;">
                            <i class="fas fa-shopping-cart me-1 text-info"></i> Optional T-Shirt Purchases
                        </span>
                    </div>
                    <div class="mt-3">
                        <?php foreach ($shirtOrders as $so): ?>
                        <input type="hidden" name="shirt_id[]" value="<?= $so['id'] ?>">
                        <div class="row g-3 mb-3 p-2 rounded" style="background:#f8faff;border:1px solid #e5e7eb;">
                            <div class="col-sm-4">
                                <label class="form-label fw-600 small">Shirt Type</label>
                                <input type="text" class="form-control form-control-sm bg-light"
                                       value="<?= e($so['shirt_type']) ?>" readonly>
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label fw-600 small">Size <span class="text-danger">*</span></label>
                                <select name="shirt_size[]" class="form-select form-select-sm">
                                    <?php foreach ($SHIRT_SIZES as $sz): ?>
                                        <option value="<?= $sz ?>" <?= $so['size'] === $sz ? 'selected' : '' ?>><?= $sz ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-2">
                                <label class="form-label fw-600 small">Qty <span class="text-danger">*</span></label>
                                <input type="number" name="shirt_qty[]" class="form-control form-control-sm"
                                       value="<?= (int)$so['quantity'] ?>" min="1" max="10" required>
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label fw-600 small">Amount Paid</label>
                                <input type="text" class="form-control form-control-sm bg-light"
                                       value="<?= formatMYR((float)$so['total_price']) ?>" readonly>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Payment Receipt -->
                    <div class="mb-1 pb-1 border-bottom mt-4">
                        <span class="text-uppercase fw-700 small" style="letter-spacing:.08em;color:#6b7280;">
                            <i class="fas fa-receipt me-1 text-success"></i> Payment Receipt
                        </span>
                    </div>
                    <div class="row g-3 mt-1 mb-2">
                        <div class="col-12">
                            <?php if (!empty($participant['payment_receipt'])): ?>
                            <div class="d-flex align-items-center gap-3 mb-2 p-2 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                                <i class="fas fa-file-image text-success"></i>
                                <div class="flex-grow-1 small fw-600 text-truncate"><?= e($participant['payment_receipt']) ?></div>
                                <a href="<?= APP_URL ?>/assets/uploads/receipts/<?= urlencode($participant['payment_receipt']) ?>"
                                   target="_blank" class="btn btn-sm btn-outline-success py-0 px-2">
                                    <i class="fas fa-eye me-1"></i> View
                                </a>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="clear_receipt" id="clearReceiptCheck" value="1">
                                <label class="form-check-label small text-danger" for="clearReceiptCheck">
                                    Remove existing receipt
                                </label>
                            </div>
                            <?php else: ?>
                            <p class="text-muted small mb-2"><i class="fas fa-exclamation-circle me-1"></i> No receipt uploaded yet.</p>
                            <?php endif; ?>
                            <label class="form-label fw-600 small"><?= !empty($participant['payment_receipt']) ? 'Replace Receipt' : 'Upload Receipt' ?></label>
                            <input type="file" name="payment_receipt" class="form-control form-control-sm"
                                   accept="image/jpeg,image/png,image/gif,application/pdf">
                            <div class="form-text">Accepted: JPG, PNG, GIF, PDF &mdash; max 5 MB.</div>
                        </div>
                    </div>

                    <div class="alert alert-warning border-0 mt-4 mb-0 p-2" style="font-size:12px;background:#fef9c3;">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        All amendments are logged in the audit trail with your admin ID and the before/after values.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-700">
                        <i class="fas fa-save me-2"></i> Save All Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<?php if (Auth::can('registrations', 'delete')): ?>
<div class="modal fade" id="deleteParticipantModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white border-0" style="background:#dc2626;">
                <h5 class="modal-title fw-700">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Participant
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4">
                <p class="mb-3">You are about to permanently delete:</p>
                <div class="p-3 rounded mb-3" style="background:#fef2f2;border:1px solid #fecaca;">
                    <div class="fw-700 fs-6"><?= e($participant['full_name']) ?></div>
                    <div class="text-muted small"><?= e($participant['reference_number']) ?> &nbsp;&middot;&nbsp; <?= e($participant['category_name']) ?> (<?= e($participant['distance']) ?>)</div>
                    <div class="text-muted small"><?= e($participant['email']) ?> &nbsp;&middot;&nbsp; IC: <?= e($participant['ic_number']) ?></div>
                </div>
                <div class="alert alert-danger py-2 mb-0 small">
                    <i class="fas fa-ban me-1"></i>
                    This action <strong>cannot be undone</strong>. The participant record and all associated shirt orders will be permanently removed.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <form method="POST" style="display:inline;">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="delete_participant">
                    <button type="submit" class="btn btn-danger fw-700">
                        <i class="fas fa-trash-alt me-1"></i> Yes, Delete Permanently
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Show/hide finisher shirt size based on selected category distance
document.getElementById('editCategoryId')?.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    const wrap = document.getElementById('finisherSizeWrap');
    if (wrap) wrap.style.display = (opt.dataset.distance === '43KM') ? '' : 'none';
});
</script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
