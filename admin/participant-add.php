<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/auth.php';
require_once APP_PATH . '/csrf.php';

setSecurityHeaders();
Auth::requireCan('participant_add');

$admin       = Auth::user();
$categories  = getActiveCategories();
$shirtPrice  = (float)getSetting('shirt_price', '40.00');
$SHIRT_SIZES = ['2X', 'X', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL', '6XL', '7XL'];
$sizeSurchargeTier1 = (float) getSetting('shirt_extra_size_fee_1', '12');
$sizeSurchargeTier2 = (float) getSetting('shirt_extra_size_fee_2', '14');
$RUNNER_CATS = ['Kanak-Kanak', 'Open', 'Veteran', 'Super Veteran'];
$errors      = [];
$old         = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrFail();

    $old = $_POST;

    $fields = [
        'full_name'                => 'Full Name',
        'ic_number'                => 'IC Number',
        'contact_number'           => 'Contact Number',
        'email'                    => 'Email',
        'gender'                   => 'Gender',
        'date_of_birth'            => 'Date of Birth',
        'address'                  => 'Address',
        'emergency_contact_name'   => 'Emergency Contact Name',
        'emergency_contact_number' => 'Emergency Contact Number',
        'category_id'              => 'Category',
    ];
    foreach ($fields as $key => $label) {
        if (empty(trim($_POST[$key] ?? ''))) {
            $errors[] = $label . ' is required.';
        }
    }
    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }
    if (!in_array($_POST['gender'] ?? '', ['Male', 'Female'], true)) {
        $errors[] = 'Invalid gender.';
    }

    $categoryId    = (int)($_POST['category_id'] ?? 0);
    $raceCat       = sanitize($_POST['race_category'] ?? '');
    $eventSize     = sanitize($_POST['event_shirt_size'] ?? '');
    $finisherSize  = sanitize($_POST['finisher_shirt_size'] ?? '');
    $category      = null;
    foreach ($categories as $cat) {
        if ((int)$cat['id'] === $categoryId) { $category = $cat; break; }
    }
    if (!$category) {
        $errors[] = 'Invalid category selected.';
    }
    if (!in_array($raceCat, $RUNNER_CATS, true)) {
        $errors[] = 'Invalid runner category.';
    }
    if (!in_array($eventSize, $SHIRT_SIZES, true)) {
        $errors[] = 'Invalid Event T-Shirt size.';
    }
    $is43km = $category && $category['distance'] === '43KM';
    if ($is43km && !in_array($finisherSize, $SHIRT_SIZES, true)) {
        $errors[] = 'Finisher T-Shirt size is required for 43KM.';
    }

    // Optional shirt purchases
    $buyExtraEvent    = !empty($_POST['want_extra_event']);
    $extraEventSize   = sanitize($_POST['extra_event_size'] ?? '');
    $extraEventQty    = max(1, (int)($_POST['extra_event_qty'] ?? 1));
    $buyFinisher      = !empty($_POST['want_finisher']);
    $finisherBuySize  = sanitize($_POST['finisher_buy_size'] ?? '');
    $finisherBuyQty   = max(1, (int)($_POST['finisher_buy_qty'] ?? 1));

    if ($buyExtraEvent && !in_array($extraEventSize, $SHIRT_SIZES, true)) {
        $errors[] = 'Invalid Extra Event Shirt size.';
    }
    if ($buyFinisher && !in_array($finisherBuySize, $SHIRT_SIZES, true)) {
        $errors[] = 'Invalid Finisher Shirt purchase size.';
    }

    if (empty($errors)) {
        $dob = $_POST['date_of_birth'];
        $age = (int)date_diff(date_create($dob), date_create('today'))->y;

        $refNumber = generateReference('NDR');

        $extraEventFee   = $buyExtraEvent ? ($shirtPrice + shirtSizeSurcharge($extraEventSize)) * $extraEventQty : 0.00;
        $finisherFee     = $buyFinisher   ? ($shirtPrice + shirtSizeSurcharge($finisherBuySize)) * $finisherBuyQty : 0.00;

        Database::execute(
            'INSERT INTO participants
             (reference_number, full_name, ic_number, contact_number, email,
              gender, age, date_of_birth, address,
              emergency_contact_name, emergency_contact_number,
              category_id, race_category, event_shirt_size, finisher_shirt_size,
              health_declaration, terms_accepted, registration_status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,1,"Confirmed")',
            [
                $refNumber,
                sanitize($_POST['full_name']),
                sanitize($_POST['ic_number']),
                sanitize($_POST['contact_number']),
                sanitize($_POST['email']),
                $_POST['gender'],
                $age,
                $dob,
                sanitize($_POST['address']),
                sanitize($_POST['emergency_contact_name']),
                sanitize($_POST['emergency_contact_number']),
                $categoryId,
                $raceCat,
                $eventSize,
                $is43km ? $finisherSize : null,
            ]
        );

        $participantId = (int)Database::lastInsertId();

        if ($buyExtraEvent) {
            Database::execute(
                'INSERT INTO shirt_orders (participant_id, shirt_type, size, quantity, unit_price, total_price) VALUES (?,?,?,?,?,?)',
                [$participantId, 'Event', $extraEventSize, $extraEventQty, $shirtPrice + shirtSizeSurcharge($extraEventSize), $extraEventFee]
            );
        }
        if ($buyFinisher) {
            Database::execute(
                'INSERT INTO shirt_orders (participant_id, shirt_type, size, quantity, unit_price, total_price) VALUES (?,?,?,?,?,?)',
                [$participantId, 'Finisher', $finisherBuySize, $finisherBuyQty, $shirtPrice + shirtSizeSurcharge($finisherBuySize), $finisherFee]
            );
        }

        Database::execute(
            'UPDATE event_categories SET current_participants = current_participants + 1 WHERE id = ?',
            [$categoryId]
        );

        $newP = Database::fetchOne(
            'SELECT p.*, ec.name AS category_name, ec.distance
             FROM participants p JOIN event_categories ec ON ec.id = p.category_id
             WHERE p.id = ?',
            [$participantId]
        ) ?? ['reference_number' => $refNumber];
        auditLog('manual_registration', 'participants', $participantId, null,
                 participantAuditSnapshot($newP) + shirtOrdersAuditMap($participantId),
                 (int)$admin['id']);

        setFlash('success', "Manual registration added. Reference: {$refNumber}");
        redirect(APP_URL . '/admin/participant-view.php?id=' . $participantId);
    }
}

$pageTitle = 'Add Manual Registration';
include PUBLIC_PATH . '/admin/includes/header.php';
?>

<?php include PUBLIC_PATH . '/admin/includes/sidebar.php'; ?>

<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <a href="registrations.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
            <h1 class="admin-page-title">Add Manual / Offline Registration</h1>
        </div>
    </div>

    <div class="admin-content">
        <?= renderFlash() ?>

        <?php if ($errors): ?>
        <div class="alert alert-danger mb-4">
            <strong><i class="fas fa-exclamation-triangle me-2"></i>Please fix the following:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle me-2"></i>
            This form registers a participant as <strong>Confirmed</strong> immediately.
            Use it for walk-in, offline, or manually confirmed registrations.
        </div>

        <form method="POST" action="">
            <?= CSRF::field() ?>

            <div class="row g-4">
                <!-- Personal Info -->
                <div class="col-lg-8">
                    <div class="admin-table mb-4">
                        <div class="p-3 border-bottom">
                            <h6 class="fw-700 mb-0"><i class="fas fa-user me-2 text-primary"></i>Personal Information</h6>
                        </div>
                        <div class="p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-600">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" class="form-control"
                                           value="<?= e($old['full_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-600">IC Number <span class="text-danger">*</span></label>
                                    <input type="text" name="ic_number" class="form-control" placeholder="e.g. 900101011234"
                                           value="<?= e($old['ic_number'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-600">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control"
                                           value="<?= e($old['email'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-600">Contact Number <span class="text-danger">*</span></label>
                                    <input type="text" name="contact_number" class="form-control" placeholder="e.g. 0123456789"
                                           value="<?= e($old['contact_number'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-600">Gender <span class="text-danger">*</span></label>
                                    <select name="gender" class="form-select" required>
                                        <option value="">Select</option>
                                        <option value="Male"   <?= ($old['gender'] ?? '') === 'Male'   ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= ($old['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-600">Date of Birth <span class="text-danger">*</span></label>
                                    <input type="date" name="date_of_birth" class="form-control"
                                           value="<?= e($old['date_of_birth'] ?? '') ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-600">Address <span class="text-danger">*</span></label>
                                    <textarea name="address" class="form-control" rows="2" required><?= e($old['address'] ?? '') ?></textarea>
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
                                <div class="col-md-6">
                                    <label class="form-label fw-600">Name <span class="text-danger">*</span></label>
                                    <input type="text" name="emergency_contact_name" class="form-control"
                                           value="<?= e($old['emergency_contact_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-600">Phone <span class="text-danger">*</span></label>
                                    <input type="text" name="emergency_contact_number" class="form-control"
                                           value="<?= e($old['emergency_contact_number'] ?? '') ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Category, Runner Cat, Shirts -->
                <div class="col-lg-4">
                    <div class="admin-table mb-4">
                        <div class="p-3 border-bottom">
                            <h6 class="fw-700 mb-0"><i class="fas fa-trophy me-2 text-warning"></i>Race Category</h6>
                        </div>
                        <div class="p-4">
                            <label class="form-label fw-600">Distance <span class="text-danger">*</span></label>
                            <?php foreach ($categories as $cat): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="category_id"
                                       id="cat_<?= $cat['id'] ?>" value="<?= $cat['id'] ?>"
                                       data-distance="<?= e($cat['distance']) ?>"
                                       data-fee="<?= (float)$cat['fee'] ?>"
                                       <?= ($old['category_id'] ?? '') == $cat['id'] ? 'checked' : '' ?>
                                       required onchange="onCatChange(); updateTotal()">
                                <label class="form-check-label" for="cat_<?= $cat['id'] ?>">
                                    <strong><?= e($cat['name']) ?></strong>
                                    <span class="badge bg-primary ms-1"><?= e($cat['distance']) ?></span>
                                    <span class="text-success ms-1"><?= formatMYR((float)$cat['fee']) ?></span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="admin-table mb-4">
                        <div class="p-3 border-bottom">
                            <h6 class="fw-700 mb-0"><i class="fas fa-running me-2 text-primary"></i>Runner Category</h6>
                        </div>
                        <div class="p-4">
                            <label class="form-label fw-600">Category <span class="text-danger">*</span></label>
                            <select name="race_category" class="form-select form-select-sm" required>
                                <option value="">Select</option>
                                <?php foreach ($RUNNER_CATS as $rc): ?>
                                    <option value="<?= $rc ?>" <?= ($old['race_category'] ?? '') === $rc ? 'selected' : '' ?>><?= $rc ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="admin-table mb-4">
                        <div class="p-3 border-bottom">
                            <h6 class="fw-700 mb-0"><i class="fas fa-tshirt me-2 text-primary"></i>T-Shirt Sizes</h6>
                        </div>
                        <div class="p-4">
                            <div class="small text-muted mb-2">
                                <i class="fas fa-info-circle me-1 text-warning"></i>
                                Extra Size: 3XL&ndash;5XL +RM<?= number_format($sizeSurchargeTier1, 0) ?>, 6XL&ndash;7XL +RM<?= number_format($sizeSurchargeTier2, 0) ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-600">Event T-Shirt (Included) <span class="text-danger">*</span></label>
                                <select name="event_shirt_size" class="form-select form-select-sm" required onchange="updateTotal()">
                                    <option value="">Select size</option>
                                    <?php foreach ($SHIRT_SIZES as $sz): ?>
                                        <option value="<?= $sz ?>" <?= ($old['event_shirt_size'] ?? '') === $sz ? 'selected' : '' ?>><?= shirtSizeLabel($sz) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="finisherSizeRow" style="display:none;">
                                <label class="form-label small fw-600">Finisher T-Shirt (Included — 43KM) <span class="text-danger">*</span></label>
                                <select name="finisher_shirt_size" class="form-select form-select-sm" id="finisherSizeSelect" onchange="updateTotal()">
                                    <option value="">Select size</option>
                                    <?php foreach ($SHIRT_SIZES as $sz): ?>
                                        <option value="<?= $sz ?>" <?= ($old['finisher_shirt_size'] ?? '') === $sz ? 'selected' : '' ?>><?= shirtSizeLabel($sz) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <hr class="my-3">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="want_extra_event" id="wantExtraEvent"
                                       <?= !empty($old['want_extra_event']) ? 'checked' : '' ?>
                                       onchange="toggleExtraEvent(); updateTotal()">
                                <label class="form-check-label small fw-600" for="wantExtraEvent">
                                    Extra Event Shirt (<?= formatMYR($shirtPrice) ?> each)
                                </label>
                            </div>
                            <div id="extraEventFields" style="display:<?= !empty($old['want_extra_event']) ? 'flex' : 'none' ?>;gap:.5rem;" class="mb-2">
                                <select name="extra_event_size" class="form-select form-select-sm" style="width:auto;" onchange="updateTotal()">
                                    <?php foreach ($SHIRT_SIZES as $sz): ?>
                                        <option value="<?= $sz ?>" <?= ($old['extra_event_size'] ?? '') === $sz ? 'selected' : '' ?>><?= shirtSizeLabel($sz) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="extra_event_qty" class="form-control form-control-sm" style="width:70px;"
                                       min="1" max="10" value="<?= (int)($old['extra_event_qty'] ?? 1) ?>" onchange="updateTotal()">
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="want_finisher" id="wantFinisher"
                                       <?= !empty($old['want_finisher']) ? 'checked' : '' ?>
                                       onchange="toggleFinisher(); updateTotal()">
                                <label class="form-check-label small fw-600" for="wantFinisher">
                                    Finisher Shirt Purchase (<?= formatMYR($shirtPrice) ?> each)
                                </label>
                            </div>
                            <div id="finisherBuyFields" style="display:<?= !empty($old['want_finisher']) ? 'flex' : 'none' ?>;gap:.5rem;">
                                <select name="finisher_buy_size" class="form-select form-select-sm" style="width:auto;" onchange="updateTotal()">
                                    <?php foreach ($SHIRT_SIZES as $sz): ?>
                                        <option value="<?= $sz ?>" <?= ($old['finisher_buy_size'] ?? '') === $sz ? 'selected' : '' ?>><?= shirtSizeLabel($sz) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="finisher_buy_qty" class="form-control form-control-sm" style="width:70px;"
                                       min="1" max="10" value="<?= (int)($old['finisher_buy_qty'] ?? 1) ?>" onchange="updateTotal()">
                            </div>
                        </div>
                    </div>

                    <div class="admin-table mb-4">
                        <div class="p-3 border-bottom">
                            <h6 class="fw-700 mb-0"><i class="fas fa-calculator me-2 text-success"></i>Fee Summary</h6>
                        </div>
                        <div class="p-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Registration Fee</span>
                                <span id="regFeeDisplay" class="small">—</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2" id="sizeFeeRow" style="display:none;">
                                <span class="text-muted small">Extra Size Surcharge</span>
                                <span id="sizeFeeDisplay" class="small">—</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2" id="extraEventFeeRow" style="display:none!important;">
                                <span class="text-muted small">Extra Event Shirt</span>
                                <span id="extraEventFeeDisplay" class="small">—</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2" id="finisherBuyFeeRow" style="display:none!important;">
                                <span class="text-muted small">Finisher Shirt Purchase</span>
                                <span id="finisherBuyFeeDisplay" class="small">—</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fw-700">
                                <span>Total</span>
                                <span id="totalDisplay" class="text-success">—</span>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Status: Confirmed</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-700">
                        <i class="fas fa-user-check me-2"></i> Register &amp; Confirm
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const shirtPrice = <?= $shirtPrice ?>;
const catFees    = {
    <?php foreach ($categories as $cat): ?>
    <?= $cat['id'] ?>: <?= (float)$cat['fee'] ?>,
    <?php endforeach; ?>
};
const SIZE_TIER1_FEE = <?= $sizeSurchargeTier1 ?>;
const SIZE_TIER2_FEE = <?= $sizeSurchargeTier2 ?>;
const SIZE_TIER1 = ['3XL', '4XL', '5XL'];
const SIZE_TIER2 = ['6XL', '7XL'];
function sizeSurcharge(size) {
    if (SIZE_TIER1.includes(size)) return SIZE_TIER1_FEE;
    if (SIZE_TIER2.includes(size)) return SIZE_TIER2_FEE;
    return 0;
}

function fmt(n) { return 'RM ' + n.toFixed(2); }

function onCatChange() {
    const r = document.querySelector('input[name="category_id"]:checked');
    const is43 = r && r.getAttribute('data-distance') === '43KM';
    document.getElementById('finisherSizeRow').style.display = is43 ? '' : 'none';
    if (!is43) document.getElementById('finisherSizeSelect').value = '';
}

function toggleExtraEvent() {
    const on = document.getElementById('wantExtraEvent').checked;
    document.getElementById('extraEventFields').style.display     = on ? 'flex' : 'none';
    document.getElementById('extraEventFeeRow').style.display     = on ? 'flex' : 'none';
}

function toggleFinisher() {
    const on = document.getElementById('wantFinisher').checked;
    document.getElementById('finisherBuyFields').style.display    = on ? 'flex' : 'none';
    document.getElementById('finisherBuyFeeRow').style.display    = on ? 'flex' : 'none';
}

function updateTotal() {
    const catRadio = document.querySelector('input[name="category_id"]:checked');
    const regFee   = catRadio ? (catFees[catRadio.value] || 0) : 0;
    const is43     = catRadio && catRadio.getAttribute('data-distance') === '43KM';

    const eventSize    = document.querySelector('select[name="event_shirt_size"]')?.value || '';
    const finisherSize = document.getElementById('finisherSizeSelect')?.value || '';
    const sizeFee      = sizeSurcharge(eventSize) + (is43 ? sizeSurcharge(finisherSize) : 0);

    const wantsExtraEvent    = document.getElementById('wantExtraEvent').checked;
    const extraEventQty      = parseInt(document.querySelector('input[name="extra_event_qty"]')?.value || 1);
    const extraEventSize     = document.querySelector('select[name="extra_event_size"]')?.value || '';
    const wantsFinisher      = document.getElementById('wantFinisher').checked;
    const finisherQty        = parseInt(document.querySelector('input[name="finisher_buy_qty"]')?.value || 1);
    const finisherBuySize    = document.querySelector('select[name="finisher_buy_size"]')?.value || '';
    const extraEventFee      = wantsExtraEvent ? (shirtPrice + sizeSurcharge(extraEventSize)) * extraEventQty : 0;
    const finisherBuyFee     = wantsFinisher   ? (shirtPrice + sizeSurcharge(finisherBuySize)) * finisherQty  : 0;
    const total              = regFee + sizeFee + extraEventFee + finisherBuyFee;

    document.getElementById('regFeeDisplay').textContent        = regFee         ? fmt(regFee)         : '—';
    document.getElementById('sizeFeeDisplay').textContent       = sizeFee        ? fmt(sizeFee)        : '—';
    document.getElementById('extraEventFeeDisplay').textContent = extraEventFee  ? fmt(extraEventFee)  : '—';
    document.getElementById('finisherBuyFeeDisplay').textContent = finisherBuyFee ? fmt(finisherBuyFee) : '—';
    document.getElementById('totalDisplay').textContent         = total          ? fmt(total)          : '—';
    document.getElementById('sizeFeeRow').style.display         = sizeFee > 0    ? 'flex' : 'none';
    document.getElementById('extraEventFeeRow').style.display   = wantsExtraEvent ? 'flex' : 'none';
    document.getElementById('finisherBuyFeeRow').style.display  = wantsFinisher   ? 'flex' : 'none';
}

onCatChange();
toggleExtraEvent();
toggleFinisher();
updateTotal();
</script>
</body>
</html>
