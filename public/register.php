<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/csrf.php';
require_once APP_PATH . '/validation.php';

setSecurityHeaders();

if (getSetting('registration_open', '1') !== '1') {
    setFlash('error', __('hero.reg_closed'));
    redirect(APP_URL . '/');
}

$categories  = getActiveCategories();
$shirtPrice  = (float) getSetting('shirt_price', '40');
$errors      = [];
$formData    = [];
$selectedCat = (int)($_GET['category'] ?? 0);

$SHIRT_SIZES = ['2X', 'X', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL', '6XL', '7XL'];
$sizeSurchargeTier1 = (float) getSetting('shirt_extra_size_fee_1', '12');
$sizeSurchargeTier2 = (float) getSetting('shirt_extra_size_fee_2', '14');

$RUNNER_CATEGORIES = [
    'Kanak-Kanak'   => __('reg.cat.kanak'),
    'Open'          => __('reg.cat.open'),
    'Veteran'       => __('reg.cat.veteran'),
    'Super Veteran' => __('reg.cat.super_veteran'),
];

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrFail();

    // Pre-fetch category for conditional validation
    $postCategoryId = (int) ($_POST['category_id'] ?? 0);
    $category = $postCategoryId > 0
        ? Database::fetchOne('SELECT * FROM event_categories WHERE id = ? AND is_active = 1', [$postCategoryId])
        : null;
    $is43km = $category && $category['distance'] === '43KM';

    $v = new Validation($_POST);

    // Personal info
    $v->required('full_name',    'Full Name')->maxLength('full_name', 150)
      ->required('ic_number',    'IC / MyKad or Passport Number')->icOrPassport('ic_number')
      ->required('contact_number', 'Contact Number')->phone('contact_number')
      ->required('email',        'Email Address')->email('email')
      ->required('gender',       'Gender')->in('gender', ['Male', 'Female'], 'Gender')
      ->required('date_of_birth', 'Date of Birth')->date('date_of_birth', 'Y-m-d', 'Date of Birth')
      ->required('address',      'Address')->maxLength('address', 500);

    // Emergency contact
    $v->required('emergency_contact_name',   'Emergency Contact Name')->maxLength('emergency_contact_name', 150)
      ->required('emergency_contact_number', 'Emergency Contact Number')->phone('emergency_contact_number', 'Emergency Contact Number');

    // Race details
    $v->required('category_id',    'Event Category')->numeric('category_id')
      ->required('race_category',  'Runner Category')->in('race_category', array_keys($RUNNER_CATEGORIES), 'Runner Category');

    // Included Event T-Shirt (required for all)
    $v->required('event_shirt_size', 'Event T-Shirt Size')->in('event_shirt_size', $SHIRT_SIZES, 'Event T-Shirt Size');

    // Included Finisher T-Shirt (required for 43KM)
    if ($is43km) {
        $v->required('finisher_shirt_size', 'Finisher T-Shirt Size')->in('finisher_shirt_size', $SHIRT_SIZES, 'Finisher T-Shirt Size');
    }

    // Optional: buy extra Event T-Shirt
    if ($v->get('buy_extra_event') === 'yes') {
        $v->required('extra_event_size', 'Extra Event Shirt Size')->in('extra_event_size', $SHIRT_SIZES, 'Extra Event Shirt Size')
          ->required('extra_event_qty',  'Extra Event Shirt Quantity')->numeric('extra_event_qty')
          ->min('extra_event_qty', 1, 'Quantity')->max('extra_event_qty', 10, 'Quantity');
    }

    // Optional: buy Finisher T-Shirt
    if ($v->get('buy_finisher') === 'yes') {
        $v->required('extra_finisher_size', 'Finisher Shirt Size')->in('extra_finisher_size', $SHIRT_SIZES, 'Finisher Shirt Size')
          ->required('extra_finisher_qty',  'Finisher Shirt Quantity')->numeric('extra_finisher_qty')
          ->min('extra_finisher_qty', 1, 'Quantity')->max('extra_finisher_qty', 10, 'Quantity');
    }

    // Declarations
    $v->checked('health_declaration', 'Health Declaration')
      ->checked('terms_accepted',     'Terms and Conditions');

    // Age calculation
    $dob = $v->get('date_of_birth');
    $age = 0;
    if ($dob) {
        try { $age = (int) date_diff(new DateTime($dob), new DateTime())->y; } catch (\Exception $e) {}
    }

    // Category slot check
    if (!$category) {
        $errors['category_id'] = 'Invalid event category selected.';
    } elseif ($category['max_participants'] > 0 && $category['current_participants'] >= $category['max_participants']) {
        $errors['category_id'] = 'Sorry, this category is fully booked.';
    }

    // Super Veteran only for 43KM
    if ($v->get('race_category') === 'Super Veteran' && $category && $category['distance'] !== '43KM') {
        $errors['race_category'] = 'Super Veteran category is available for the 43KM race only.';
    }

    // One registration per person: same IC / passport (ignoring dashes, spaces, case), unless cancelled
    if (!isset($v->errors()['ic_number']) && $v->get('ic_number') !== '') {
        $existing = Database::fetchOne(
            "SELECT id FROM participants
             WHERE UPPER(REPLACE(REPLACE(ic_number, '-', ''), ' ', '')) = ?
               AND registration_status <> 'Cancelled'
             LIMIT 1",
            [$v->get('ic_number')]
        );
        if ($existing) {
            $errors['ic_number'] = 'This IC / Passport number is already registered. Each person may only register once. If you need help with your registration, please contact the organiser.';
        }
    }

    $errors = array_merge($v->errors(), $errors);

    if (empty($errors)) {
        $refNumber    = generateReference('NDR');
        $finisherSize = $is43km ? $v->get('finisher_shirt_size') : null;

        try {
            $participantId = (int) Database::insert(
                'INSERT INTO participants
                    (reference_number, full_name, ic_number, contact_number, email, gender,
                     age, date_of_birth, address, emergency_contact_name, emergency_contact_number,
                     category_id, race_category, event_shirt_size, finisher_shirt_size,
                     health_declaration, terms_accepted, registration_status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $refNumber,
                    $v->get('full_name'),
                    $v->get('ic_number'),
                    $v->get('contact_number'),
                    $v->get('email'),
                    $v->get('gender'),
                    $age,
                    $v->get('date_of_birth'),
                    $v->get('address'),
                    $v->get('emergency_contact_name'),
                    $v->get('emergency_contact_number'),
                    $postCategoryId,
                    $v->get('race_category'),
                    $v->get('event_shirt_size'),
                    $finisherSize,
                    1, 1,
                    'Pending',
                ]
            );

            // Optional: extra Event T-Shirt purchase
            if ($v->get('buy_extra_event') === 'yes') {
                $qty       = (int) $v->get('extra_event_qty');
                $unitPrice = $shirtPrice + shirtSizeSurcharge($v->get('extra_event_size'));
                Database::insert(
                    'INSERT INTO shirt_orders (participant_id, shirt_type, size, quantity, unit_price, total_price) VALUES (?,?,?,?,?,?)',
                    [$participantId, 'Event', $v->get('extra_event_size'), $qty, $unitPrice, $unitPrice * $qty]
                );
            }

            // Optional: Finisher T-Shirt purchase
            if ($v->get('buy_finisher') === 'yes') {
                $qty       = (int) $v->get('extra_finisher_qty');
                $unitPrice = $shirtPrice + shirtSizeSurcharge($v->get('extra_finisher_size'));
                Database::insert(
                    'INSERT INTO shirt_orders (participant_id, shirt_type, size, quantity, unit_price, total_price) VALUES (?,?,?,?,?,?)',
                    [$participantId, 'Finisher', $v->get('extra_finisher_size'), $qty, $unitPrice, $unitPrice * $qty]
                );
            }

            Database::execute(
                'UPDATE event_categories SET current_participants = current_participants + 1 WHERE id = ?',
                [$postCategoryId]
            );

            $_SESSION['reg_success'] = [
                'participant_id'   => $participantId,
                'reference_number' => $refNumber,
            ];

            // Send registration acknowledgment email
            try {
                require_once APP_PATH . '/email.php';
                $totalFee = (float)$category['fee'];
                $totalFee += shirtSizeSurcharge($v->get('event_shirt_size'));
                if ($is43km) {
                    $totalFee += shirtSizeSurcharge($v->get('finisher_shirt_size'));
                }
                if ($v->get('buy_extra_event') === 'yes') {
                    $qty = max(1, (int)$v->get('extra_event_qty'));
                    $totalFee += ($shirtPrice + shirtSizeSurcharge($v->get('extra_event_size'))) * $qty;
                }
                if ($v->get('buy_finisher') === 'yes') {
                    $qty = max(1, (int)$v->get('extra_finisher_qty'));
                    $totalFee += ($shirtPrice + shirtSizeSurcharge($v->get('extra_finisher_size'))) * $qty;
                }
                EmailService::sendRegistrationPending(
                    ['full_name' => $v->get('full_name'), 'email' => $v->get('email'), 'reference_number' => $refNumber],
                    $category,
                    $totalFee
                );
            } catch (\Throwable $emailEx) {
                error_log('Registration email failed: ' . $emailEx->getMessage());
            }

            CSRF::rotate();
            redirect(APP_URL . '/success.php');

        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), '1062') || str_contains($e->getMessage(), 'Duplicate')) {
                $errors['ic_number'] = 'This IC / Passport number is already registered. Each person may only register once.';
            } else {
                $errors['_db'] = 'A system error occurred. Please try again. (Ref: ' . substr($e->getMessage(), 0, 80) . ')';
            }
        }
    }

    $formData = $v->all();
}

// Parse DOB into parts for dropdown repopulation after validation failure
$_dobVal   = $formData['date_of_birth'] ?? '';
$_dobParts = $_dobVal ? explode('-', $_dobVal) : [];
$_dobYear  = $_dobParts[0] ?? '';
$_dobMonth = $_dobParts[1] ?? '';
$_dobDay   = $_dobParts[2] ?? '';

// Determine whether the initially-rendered category is 43KM
// Covers both ?category=3 (GET) and POST re-renders after validation failure
$initCatId = (int)($formData['category_id'] ?? $selectedCat);
$initIs43  = false;
foreach ($categories as $_cat) {
    if ((int)$_cat['id'] === $initCatId && $_cat['distance'] === '43KM') {
        $initIs43 = true;
        break;
    }
}

$pageTitle = __('reg.page_title');
include PUBLIC_PATH . '/includes/header.php';
?>

<div class="page-hero-sm">
    <div class="container text-center">
        <h1 class="page-hero-title"><i class="fas fa-pencil-alt me-2"></i> <?= __('reg.page_title') ?></h1>
        <p class="page-hero-sub"><?= __('reg.page_sub') ?></p>
    </div>
</div>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">

                <!-- Errors -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong><?= __('reg.errors_title') ?></strong>
                        <ul class="mb-0 mt-1">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="registrationForm" novalidate>
                    <?= CSRF::field() ?>

                    <!-- ======= STEP 1: PERSONAL INFO ======= -->
                    <div class="form-card">
                        <div class="form-card-header">
                            <span class="form-step-num">1</span>
                            <div>
                                <h5 class="mb-0"><?= __('reg.s1.title') ?></h5>
                                <small class="text-muted"><?= __('reg.s1.sub') ?></small>
                            </div>
                        </div>
                        <div class="form-card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required-label"><?= __('reg.full_name') ?> <small class="text-muted">(<?= __('reg.as_per_ic') ?>)</small></label>
                                    <input type="text" name="full_name" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                                           value="<?= e($formData['full_name'] ?? '') ?>" placeholder="e.g. John Peter Kinsik" maxlength="150" required>
                                    <?php if (isset($errors['full_name'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['full_name']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-label"><?= __('reg.ic') ?></label>
                                    <input type="text" name="ic_number" class="form-control <?= isset($errors['ic_number']) ? 'is-invalid' : '' ?>"
                                           value="<?= e($formData['ic_number'] ?? '') ?>" placeholder="e.g. 901231141234 or Passport No." maxlength="20" required>
                                    <div class="form-text"><?= __('reg.ic_hint') ?></div>
                                    <?php if (isset($errors['ic_number'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['ic_number']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-label"><?= __('reg.contact') ?></label>
                                    <input type="tel" name="contact_number" class="form-control <?= isset($errors['contact_number']) ? 'is-invalid' : '' ?>"
                                           value="<?= e($formData['contact_number'] ?? '') ?>" placeholder="e.g. 019-1234567" required>
                                    <?php if (isset($errors['contact_number'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['contact_number']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-label"><?= __('reg.email') ?></label>
                                    <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                           value="<?= e($formData['email'] ?? '') ?>" placeholder="you@example.com" required>
                                    <div class="form-text"><?= __('reg.email_hint') ?></div>
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required-label"><?= __('reg.gender') ?></label>
                                    <select name="gender" class="form-select <?= isset($errors['gender']) ? 'is-invalid' : '' ?>" required>
                                        <option value=""><?= __('reg.gender_select') ?></option>
                                        <option value="Male"   <?= ($formData['gender'] ?? '') === 'Male'   ? 'selected' : '' ?>><?= __('reg.male') ?></option>
                                        <option value="Female" <?= ($formData['gender'] ?? '') === 'Female' ? 'selected' : '' ?>><?= __('reg.female') ?></option>
                                    </select>
                                    <?php if (isset($errors['gender'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['gender']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required-label"><?= __('reg.dob') ?></label>
                                    <div class="d-flex gap-1">
                                        <!-- Day -->
                                        <select id="dob_day" class="form-select form-select-sm <?= isset($errors['date_of_birth']) ? 'is-invalid' : '' ?>" style="width:70px;flex-shrink:0;">
                                            <option value="">DD</option>
                                            <?php for ($d = 1; $d <= 31; $d++):
                                                $dv = sprintf('%02d', $d); ?>
                                                <option value="<?= $dv ?>" <?= $_dobDay === $dv ? 'selected' : '' ?>><?= $d ?></option>
                                            <?php endfor; ?>
                                        </select>
                                        <!-- Month -->
                                        <select id="dob_month" class="form-select form-select-sm <?= isset($errors['date_of_birth']) ? 'is-invalid' : '' ?>" style="flex:1;">
                                            <option value="">Month</option>
                                            <?php foreach (['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $mi => $mn):
                                                $mv = sprintf('%02d', $mi + 1); ?>
                                                <option value="<?= $mv ?>" <?= $_dobMonth === $mv ? 'selected' : '' ?>><?= $mn ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <!-- Year -->
                                        <select id="dob_year" class="form-select form-select-sm <?= isset($errors['date_of_birth']) ? 'is-invalid' : '' ?>" style="width:88px;flex-shrink:0;">
                                            <option value="">Year</option>
                                            <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 100; $y--): ?>
                                                <option value="<?= $y ?>" <?= $_dobYear === (string)$y ? 'selected' : '' ?>><?= $y ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <!-- Hidden field carries the assembled value for server validation -->
                                    <input type="hidden" name="date_of_birth" id="dob_hidden" value="<?= e($formData['date_of_birth'] ?? '') ?>">
                                    <?php if (isset($errors['date_of_birth'])): ?>
                                        <div class="text-danger mt-1" style="font-size:.85rem;"><?= e($errors['date_of_birth']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"><?= __('reg.age') ?></label>
                                    <input type="text" id="ageDisplay" class="form-control" readonly placeholder="<?= __('reg.age_ph') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label required-label"><?= __('reg.address') ?></label>
                                    <textarea name="address" class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>"
                                              rows="3" placeholder="<?= __('reg.address_ph') ?>" maxlength="500" required><?= e($formData['address'] ?? '') ?></textarea>
                                    <?php if (isset($errors['address'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['address']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ======= STEP 2: EMERGENCY CONTACT ======= -->
                    <div class="form-card">
                        <div class="form-card-header">
                            <span class="form-step-num">2</span>
                            <div>
                                <h5 class="mb-0"><?= __('reg.s2.title') ?></h5>
                                <small class="text-muted"><?= __('reg.s2.sub') ?></small>
                            </div>
                        </div>
                        <div class="form-card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required-label"><?= __('reg.emerg_name') ?></label>
                                    <input type="text" name="emergency_contact_name" class="form-control <?= isset($errors['emergency_contact_name']) ? 'is-invalid' : '' ?>"
                                           value="<?= e($formData['emergency_contact_name'] ?? '') ?>" placeholder="<?= __('reg.full_name_ph') ?>" maxlength="150" required>
                                    <?php if (isset($errors['emergency_contact_name'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['emergency_contact_name']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-label"><?= __('reg.emerg_num') ?></label>
                                    <input type="tel" name="emergency_contact_number" class="form-control <?= isset($errors['emergency_contact_number']) ? 'is-invalid' : '' ?>"
                                           value="<?= e($formData['emergency_contact_number'] ?? '') ?>" placeholder="e.g. 011-12345678" required>
                                    <?php if (isset($errors['emergency_contact_number'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['emergency_contact_number']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ======= STEP 3: RACE DETAILS ======= -->
                    <div class="form-card">
                        <div class="form-card-header">
                            <span class="form-step-num">3</span>
                            <div>
                                <h5 class="mb-0"><?= __('reg.s3.title') ?></h5>
                                <small class="text-muted"><?= __('reg.s3.sub') ?></small>
                            </div>
                        </div>
                        <div class="form-card-body">
                            <?php if (isset($errors['category_id'])): ?>
                                <div class="alert alert-danger"><?= e($errors['category_id']) ?></div>
                            <?php endif; ?>

                            <!-- Distance selection -->
                            <label class="form-label required-label mb-2"><?= __('reg.s3.distance_label') ?></label>
                            <div class="row g-3 mb-4">
                                <?php foreach ($categories as $cat):
                                    $isFull     = $cat['max_participants'] > 0 && $cat['current_participants'] >= $cat['max_participants'];
                                    $isSelected = ((int)($formData['category_id'] ?? $selectedCat)) === (int)$cat['id'];
                                ?>
                                    <div class="col-md-4">
                                        <label class="category-radio-label <?= $isFull ? 'disabled' : '' ?>">
                                            <input type="radio" name="category_id" value="<?= $cat['id'] ?>"
                                                   class="category-radio visually-hidden"
                                                   data-fee="<?= $cat['fee'] ?>"
                                                   data-distance="<?= e($cat['distance']) ?>"
                                                   <?= $isSelected ? 'checked' : '' ?>
                                                   <?= $isFull    ? 'disabled' : '' ?>>
                                            <div class="category-radio-card <?= $isSelected ? 'selected' : '' ?> <?= $isFull ? 'full' : '' ?>">
                                                <div class="fw-700 text-primary fs-5"><?= e($cat['distance']) ?></div>
                                                <div class="fw-600"><?= e($cat['name']) ?></div>
                                                <div class="text-warning fw-700">RM <?= number_format((float)$cat['fee'], 0) ?></div>
                                                <?php if ($isFull): ?>
                                                    <span class="badge bg-danger mt-1"><?= __('reg.fully_booked') ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Runner category -->
                            <?php if (isset($errors['race_category'])): ?>
                                <div class="alert alert-danger"><?= e($errors['race_category']) ?></div>
                            <?php endif; ?>
                            <label class="form-label required-label"><?= __('reg.s3.runner_label') ?></label>
                            <div class="row g-2">
                                <?php foreach ($RUNNER_CATEGORIES as $value => $label):
                                    $isSelected = ($formData['race_category'] ?? '') === $value;
                                ?>
                                    <div class="col-sm-6">
                                        <label class="runner-cat-option d-flex align-items-center gap-2 p-3 rounded-3 border cursor-pointer <?= $isSelected ? 'border-primary bg-primary bg-opacity-10' : '' ?>"
                                               style="cursor:pointer;">
                                            <input type="radio" name="race_category" value="<?= e($value) ?>"
                                                   class="form-check-input mt-0 runner-cat-radio"
                                                   <?= $isSelected ? 'checked' : '' ?>>
                                            <span class="fw-600 small"><?= e($label) ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text mt-2"><i class="fas fa-info-circle me-1 text-primary"></i><?= __('reg.cat.note') ?></div>
                        </div>
                    </div>

                    <?php
                    // Finisher T-shirt thumbnails (front/back) that open in the image viewer
                    $finisherPreview = function (string $group): string {
                        $img = APP_URL . '/assets/images';
                        $out = '<div class="shirt-preview" data-lightbox-group="' . $group . '">';
                        foreach (['front' => [195, 'fin.shirt_front'], 'back' => [194, 'fin.shirt_back']] as $side => [$h, $key]) {
                            $label = e(__($key));
                            $out .= '<button type="button" class="shirt-preview-btn" data-full="' . $img . '/finisher-shirt-' . $side . '-v2.jpg" aria-label="' . $label . '">'
                                  . '<img src="' . $img . '/web/finisher-shirt-' . $side . '-v2-160.jpg" alt="' . $label . '" width="160" height="' . $h . '" loading="lazy" decoding="async"></button>';
                        }
                        return $out . '<span class="shirt-preview-text"><i class="fas fa-search-plus me-1" aria-hidden="true"></i>' . __('fin.preview') . '</span></div>';
                    };
                    ?>

                    <!-- ======= STEP 4: T-SHIRT ======= -->
                    <div class="form-card">
                        <div class="form-card-header">
                            <span class="form-step-num">4</span>
                            <div>
                                <h5 class="mb-0"><?= __('reg.s4.title') ?></h5>
                                <small class="text-muted"><?= __('reg.s4.sub') ?></small>
                            </div>
                        </div>
                        <div class="form-card-body">

                            <!-- Included shirts notice -->
                            <div class="rounded-3 p-3 mb-4" style="background:#f0fdf4;border-left:4px solid #16a34a;">
                                <h6 class="fw-700 text-success mb-2"><i class="fas fa-gift me-2"></i><?= __('reg.s4.included_title') ?></h6>
                                <ul class="mb-0 small text-dark">
                                    <li><?= __('reg.s4.included_event') ?></li>
                                    <li id="finisherIncludedNote" style="<?= $initIs43 ? '' : 'display:none' ?>"><?= __('reg.s4.included_finisher') ?></li>
                                </ul>
                            </div>

                            <!-- Extra size surcharge alert -->
                            <div class="d-flex align-items-start gap-2 p-3 mb-4 rounded-3" style="background:#fef2f2;border:2px solid #f87171;box-shadow:0 2px 10px rgba(248,113,113,.25);">
                                <i class="fas fa-exclamation-triangle fa-lg mt-1" style="color:#dc2626;"></i>
                                <div>
                                    <div class="fw-800" style="color:#b91c1c;font-size:.72rem;letter-spacing:.06em;text-transform:uppercase;">
                                        <?= __('reg.s4.extra_size_alert_title') ?>
                                    </div>
                                    <div class="small text-dark mt-1">
                                        <?= __('reg.s4.extra_size_note', number_format($sizeSurchargeTier1, 0), number_format($sizeSurchargeTier2, 0)) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <!-- Included Event T-Shirt size -->
                                <div class="col-md-6">
                                    <label class="form-label required-label"><?= __('reg.event_shirt_size') ?></label>
                                    <select name="event_shirt_size" class="form-select <?= isset($errors['event_shirt_size']) ? 'is-invalid' : '' ?>" required>
                                        <option value=""><?= __('reg.shirt_size_ph') ?></option>
                                        <?php foreach ($SHIRT_SIZES as $sz): ?>
                                            <option value="<?= $sz ?>" <?= ($formData['event_shirt_size'] ?? '') === $sz ? 'selected' : '' ?>><?= shirtSizeLabel($sz) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['event_shirt_size'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['event_shirt_size']) ?></div>
                                    <?php endif; ?>
                                    <div class="size-surcharge-alert" id="eventSizeAlert" style="display:none;"></div>
                                </div>

                                <!-- Included Finisher T-Shirt size (43KM only) -->
                                <div class="col-md-6" id="finisherShirtSizeGroup" style="<?= $initIs43 ? '' : 'display:none' ?>">
                                    <label class="form-label required-label"><?= __('reg.finisher_shirt_size') ?></label>
                                    <select name="finisher_shirt_size" class="form-select <?= isset($errors['finisher_shirt_size']) ? 'is-invalid' : '' ?>" id="finisherShirtSize">
                                        <option value=""><?= __('reg.shirt_size_ph') ?></option>
                                        <?php foreach ($SHIRT_SIZES as $sz): ?>
                                            <option value="<?= $sz ?>" <?= ($formData['finisher_shirt_size'] ?? '') === $sz ? 'selected' : '' ?>><?= shirtSizeLabel($sz) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['finisher_shirt_size'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['finisher_shirt_size']) ?></div>
                                    <?php endif; ?>
                                    <div class="size-surcharge-alert" id="finisherSizeAlert" style="display:none;"></div>
                                    <?= $finisherPreview('finisher-shirt-included') ?>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Optional purchases -->
                            <h6 class="fw-700 mb-3"><i class="fas fa-shopping-cart me-2 text-primary"></i><?= __('reg.s4.extra_title') ?> <span class="badge bg-warning text-dark ms-1"><?= __('reg.s4.extra_badge', number_format($shirtPrice, 0)) ?></span></h6>

                            <!-- Extra Event T-Shirt purchase -->
                            <div class="mb-4">
                                <label class="form-label fw-600"><?= __('reg.buy_extra_event_q') ?></label>
                                <div class="d-flex gap-3 mb-2">
                                    <div class="form-check form-check-custom">
                                        <input class="form-check-input" type="radio" name="buy_extra_event" id="extraEventYes" value="yes"
                                               <?= ($formData['buy_extra_event'] ?? '') === 'yes' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="extraEventYes"><i class="fas fa-tshirt me-1 text-primary"></i> <?= __('reg.shirt_yes') ?></label>
                                    </div>
                                    <div class="form-check form-check-custom">
                                        <input class="form-check-input" type="radio" name="buy_extra_event" id="extraEventNo" value="no"
                                               <?= ($formData['buy_extra_event'] ?? 'no') === 'no' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="extraEventNo"><?= __('reg.shirt_no') ?></label>
                                    </div>
                                </div>
                                <div class="row g-3" id="extraEventFields" style="<?= ($formData['buy_extra_event'] ?? '') === 'yes' ? '' : 'display:none' ?>">
                                    <div class="col-md-5">
                                        <label class="form-label required-label"><?= __('reg.shirt_size') ?></label>
                                        <select name="extra_event_size" class="form-select <?= isset($errors['extra_event_size']) ? 'is-invalid' : '' ?>">
                                            <option value=""><?= __('reg.shirt_size_ph') ?></option>
                                            <?php foreach ($SHIRT_SIZES as $sz): ?>
                                                <option value="<?= $sz ?>" <?= ($formData['extra_event_size'] ?? '') === $sz ? 'selected' : '' ?>><?= shirtSizeLabel($sz) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($errors['extra_event_size'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['extra_event_size']) ?></div>
                                        <?php endif; ?>
                                        <div class="size-surcharge-alert" id="extraEventSizeAlert" style="display:none;"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label required-label"><?= __('reg.shirt_qty') ?></label>
                                        <input type="number" name="extra_event_qty" class="form-control <?= isset($errors['extra_event_qty']) ? 'is-invalid' : '' ?>"
                                               id="extraEventQty" min="1" max="10" value="<?= e($formData['extra_event_qty'] ?? '1') ?>">
                                        <?php if (isset($errors['extra_event_qty'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['extra_event_qty']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Extra Finisher T-Shirt purchase -->
                            <div class="mb-2">
                                <label class="form-label fw-600"><?= __('reg.buy_finisher_q') ?></label>
                                <?= $finisherPreview('finisher-shirt-extra') ?>
                                <div class="d-flex gap-3 mb-2">
                                    <div class="form-check form-check-custom">
                                        <input class="form-check-input" type="radio" name="buy_finisher" id="buyFinisherYes" value="yes"
                                               <?= ($formData['buy_finisher'] ?? '') === 'yes' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="buyFinisherYes"><i class="fas fa-tshirt me-1 text-warning"></i> <?= __('reg.shirt_yes') ?></label>
                                    </div>
                                    <div class="form-check form-check-custom">
                                        <input class="form-check-input" type="radio" name="buy_finisher" id="buyFinisherNo" value="no"
                                               <?= ($formData['buy_finisher'] ?? 'no') === 'no' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="buyFinisherNo"><?= __('reg.shirt_no') ?></label>
                                    </div>
                                </div>
                                <div class="row g-3" id="extraFinisherFields" style="<?= ($formData['buy_finisher'] ?? '') === 'yes' ? '' : 'display:none' ?>">
                                    <div class="col-md-5">
                                        <label class="form-label required-label"><?= __('reg.shirt_size') ?></label>
                                        <select name="extra_finisher_size" class="form-select <?= isset($errors['extra_finisher_size']) ? 'is-invalid' : '' ?>">
                                            <option value=""><?= __('reg.shirt_size_ph') ?></option>
                                            <?php foreach ($SHIRT_SIZES as $sz): ?>
                                                <option value="<?= $sz ?>" <?= ($formData['extra_finisher_size'] ?? '') === $sz ? 'selected' : '' ?>><?= shirtSizeLabel($sz) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($errors['extra_finisher_size'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['extra_finisher_size']) ?></div>
                                        <?php endif; ?>
                                        <div class="size-surcharge-alert" id="extraFinisherSizeAlert" style="display:none;"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label required-label"><?= __('reg.shirt_qty') ?></label>
                                        <input type="number" name="extra_finisher_qty" class="form-control <?= isset($errors['extra_finisher_qty']) ? 'is-invalid' : '' ?>"
                                               id="extraFinisherQty" min="1" max="10" value="<?= e($formData['extra_finisher_qty'] ?? '1') ?>">
                                        <?php if (isset($errors['extra_finisher_qty'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['extra_finisher_qty']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- ======= STEP 5: DECLARATIONS ======= -->
                    <div class="form-card">
                        <div class="form-card-header">
                            <span class="form-step-num">5</span>
                            <div>
                                <h5 class="mb-0"><?= __('reg.s5.title') ?></h5>
                                <small class="text-muted"><?= __('reg.s5.sub') ?></small>
                            </div>
                        </div>
                        <div class="form-card-body">
                            <div class="declaration-box mb-3">
                                <h6 class="fw-700"><i class="fas fa-heartbeat me-2 text-danger"></i> <?= __('reg.health.title') ?></h6>
                                <p class="text-muted small"><?= __('reg.health.text') ?></p>
                                <div class="form-check <?= isset($errors['health_declaration']) ? 'is-invalid' : '' ?>">
                                    <input class="form-check-input" type="checkbox" name="health_declaration" id="healthDecl" value="1"
                                           <?= !empty($formData['health_declaration']) ? 'checked' : '' ?> required>
                                    <label class="form-check-label fw-600" for="healthDecl"><?= __('reg.health.check') ?></label>
                                </div>
                                <?php if (isset($errors['health_declaration'])): ?>
                                    <div class="text-danger small mt-1"><?= e($errors['health_declaration']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="declaration-box">
                                <h6 class="fw-700"><i class="fas fa-file-contract me-2 text-primary"></i> <?= __('reg.terms.title') ?></h6>
                                <p class="text-muted small"><?= __('reg.terms.text') ?></p>
                                <div class="form-check <?= isset($errors['terms_accepted']) ? 'is-invalid' : '' ?>">
                                    <input class="form-check-input" type="checkbox" name="terms_accepted" id="termsAccept" value="1"
                                           <?= !empty($formData['terms_accepted']) ? 'checked' : '' ?> required>
                                    <label class="form-check-label fw-600" for="termsAccept"><?= __('reg.terms.check') ?></label>
                                </div>
                                <?php if (isset($errors['terms_accepted'])): ?>
                                    <div class="text-danger small mt-1"><?= e($errors['terms_accepted']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Fee Summary -->
                    <div class="fee-summary-card mb-4" id="feeSummary">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="fas fa-calculator text-primary"></i>
                            <h6 class="mb-0 fw-700"><?= __('reg.fee_calc') ?></h6>
                        </div>
                        <div id="feeBreakdown">
                            <p class="text-muted small mb-0"><?= __('reg.fee_select') ?></p>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
                            <span class="fw-700 text-dark"><?= __('reg.total') ?></span>
                            <div class="total-amount" id="totalAmount">RM 0.00</div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="text-center mt-4">
                        <div class="fee-final-box mb-4" id="feeFinalBox">
                            <div class="fee-final-label"><?= __('reg.total') ?></div>
                            <div class="fee-final-amount" id="feeFinalAmount">RM 0.00</div>
                        </div>
                        <button type="submit" class="btn btn-submit-register" id="submitBtn">
                            <i class="fas fa-check-circle me-2"></i> <?= __('reg.submit') ?>
                        </button>
                        <p class="text-muted small mt-3">
                            <i class="fas fa-shield-alt me-1 text-success"></i>
                            <?= __('reg.security') ?>
                        </p>
                    </div>
                </form>

            </div>
        </div>
    </div>
</section>

<script>
const SHIRT_PRICE = <?= $shirtPrice ?>;
const categories  = <?= json_encode(array_column($categories, null, 'id')) ?>;
const YEARS_LABEL = <?= json_encode(__('reg.years')) ?>;
const MSG_SELECT  = <?= json_encode(__('reg.fee_select')) ?>;
const SIZE_TIER1_FEE = <?= $sizeSurchargeTier1 ?>;
const SIZE_TIER2_FEE = <?= $sizeSurchargeTier2 ?>;
const SIZE_TIER1 = ['3XL', '4XL', '5XL'];
const SIZE_TIER2 = ['6XL', '7XL'];
function sizeSurcharge(size) {
    if (SIZE_TIER1.includes(size)) return SIZE_TIER1_FEE;
    if (SIZE_TIER2.includes(size)) return SIZE_TIER2_FEE;
    return 0;
}

// Show/hide the inline "extra size" alert under a size <select>
function updateSizeAlert(alertId, size) {
    const el  = document.getElementById(alertId);
    const fee = size ? sizeSurcharge(size) : 0;
    if (!el) return;
    if (fee > 0) {
        el.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i>Extra Size Charge: <strong>${size}</strong> adds +RM${fee.toFixed(2)}`;
        el.style.display = 'block';
    } else {
        el.style.display = 'none';
        el.innerHTML = '';
    }
}

// Auto-select runner category based on age
function autoSelectRunnerCategory(age) {
    let val = age <= 14 ? 'Kanak-Kanak' : age <= 45 ? 'Open' : age <= 54 ? 'Veteran' : 'Super Veteran';
    const radio = document.querySelector(`.runner-cat-radio[value="${val}"]`);
    if (radio && !radio.checked) {
        radio.checked = true;
        document.querySelectorAll('.runner-cat-radio').forEach(x =>
            x.closest('label').classList.remove('border-primary', 'bg-primary', 'bg-opacity-10')
        );
        radio.closest('label').classList.add('border-primary', 'bg-primary', 'bg-opacity-10');
    }
}

// DOB dropdowns → assemble hidden field + age calculate
function assembleDOB() {
    const day   = document.getElementById('dob_day').value;
    const month = document.getElementById('dob_month').value;
    const year  = document.getElementById('dob_year').value;
    if (day && month && year) {
        const val = year + '-' + month + '-' + day;
        document.getElementById('dob_hidden').value = val;
        const dob = new Date(val);
        const age = Math.floor((new Date() - dob) / (365.25 * 24 * 3600 * 1000));
        document.getElementById('ageDisplay').value = isNaN(age) ? '' : age + ' ' + YEARS_LABEL;
        if (!isNaN(age) && age > 0) autoSelectRunnerCategory(age);
        updateFee();
    } else {
        document.getElementById('dob_hidden').value = '';
    }
}
['dob_day', 'dob_month', 'dob_year'].forEach(function(id) {
    document.getElementById(id).addEventListener('change', assembleDOB);
});

// Category selection highlights & finisher shirt toggle
document.querySelectorAll('.category-radio').forEach(r => {
    r.addEventListener('change', function () {
        document.querySelectorAll('.category-radio-card').forEach(c => c.classList.remove('selected'));
        if (this.checked) this.closest('.category-radio-label').querySelector('.category-radio-card').classList.add('selected');
        const dist = this.getAttribute('data-distance');
        const is43 = dist === '43KM';
        document.getElementById('finisherShirtSizeGroup').style.display = is43 ? '' : 'none';
        document.getElementById('finisherIncludedNote').style.display   = is43 ? '' : 'none';
        if (!is43) document.getElementById('finisherShirtSize').value = '';
        updateFee();
    });
});

// Runner category highlight
document.querySelectorAll('.runner-cat-radio').forEach(r => {
    r.addEventListener('change', function () {
        document.querySelectorAll('.runner-cat-radio').forEach(x => {
            x.closest('label').classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
        });
        if (this.checked) this.closest('label').classList.add('border-primary', 'bg-primary', 'bg-opacity-10');
    });
});

// Toggle extra event shirt fields
document.querySelectorAll('[name="buy_extra_event"]').forEach(r =>
    r.addEventListener('change', function () {
        document.getElementById('extraEventFields').style.display = this.value === 'yes' ? '' : 'none';
        updateFee();
    })
);

// Toggle extra finisher shirt fields
document.querySelectorAll('[name="buy_finisher"]').forEach(r =>
    r.addEventListener('change', function () {
        document.getElementById('extraFinisherFields').style.display = this.value === 'yes' ? '' : 'none';
        updateFee();
    })
);

// Qty changes update fee
document.getElementById('extraEventQty').addEventListener('input', updateFee);
document.getElementById('extraFinisherQty').addEventListener('input', updateFee);

// Size changes update fee (oversized shirts carry a surcharge)
document.querySelector('[name="event_shirt_size"]').addEventListener('change', updateFee);
document.getElementById('finisherShirtSize').addEventListener('change', updateFee);
document.querySelector('[name="extra_event_size"]').addEventListener('change', updateFee);
document.querySelector('[name="extra_finisher_size"]').addEventListener('change', updateFee);

function updateFee() {
    const catInput = document.querySelector('[name="category_id"]:checked');
    const catId    = catInput ? catInput.value : null;
    const catFee   = catInput ? parseFloat(catInput.getAttribute('data-fee') || 0) : 0;
    const catName  = catId && categories[catId] ? categories[catId].name : '';
    const is43     = catInput ? catInput.getAttribute('data-distance') === '43KM' : false;

    const eventSize    = document.querySelector('[name="event_shirt_size"]').value;
    const finisherSize = document.getElementById('finisherShirtSize').value;

    const wantsExtraEvent    = document.querySelector('[name="buy_extra_event"]:checked')?.value === 'yes';
    const extraEventQty      = parseInt(document.getElementById('extraEventQty')?.value || 1) || 1;
    const extraEventSize     = document.querySelector('[name="extra_event_size"]').value;
    const wantsExtraFinisher = document.querySelector('[name="buy_finisher"]:checked')?.value === 'yes';
    const extraFinisherQty   = parseInt(document.getElementById('extraFinisherQty')?.value || 1) || 1;
    const extraFinisherSize  = document.querySelector('[name="extra_finisher_size"]').value;

    const eventSizeFee    = eventSize ? sizeSurcharge(eventSize) : 0;
    const finisherSizeFee = (is43 && finisherSize) ? sizeSurcharge(finisherSize) : 0;
    const extraEventFee    = wantsExtraEvent    ? (SHIRT_PRICE + sizeSurcharge(extraEventSize))    * extraEventQty    : 0;
    const extraFinisherFee = wantsExtraFinisher ? (SHIRT_PRICE + sizeSurcharge(extraFinisherSize)) * extraFinisherQty : 0;
    const total            = catFee + eventSizeFee + finisherSizeFee + extraEventFee + extraFinisherFee;

    updateSizeAlert('eventSizeAlert', eventSize);
    updateSizeAlert('finisherSizeAlert', is43 ? finisherSize : '');
    updateSizeAlert('extraEventSizeAlert', wantsExtraEvent ? extraEventSize : '');
    updateSizeAlert('extraFinisherSizeAlert', wantsExtraFinisher ? extraFinisherSize : '');

    let html = '';
    if (catFee > 0) {
        const row = (label, amount) =>
            `<div class="d-flex justify-content-between small mb-1"><span>${label}</span><span class="fw-600">RM ${amount.toFixed(2)}</span></div>`;
        html += row(`${catName} – Registration`, catFee);
        html += `<div class="d-flex justify-content-between small mb-1 text-success"><span><i class="fas fa-gift me-1"></i>Event T-Shirt (included)</span><span class="fw-600">RM 0.00</span></div>`;
        if (eventSizeFee > 0)    html += row(`Event T-Shirt Extra Size (${eventSize})`, eventSizeFee);
        if (finisherSizeFee > 0) html += row(`Finisher T-Shirt Extra Size (${finisherSize})`, finisherSizeFee);
        if (extraEventFee > 0)    html += row(`Extra Event T-Shirt ×${extraEventQty}`, extraEventFee);
        if (extraFinisherFee > 0) html += row(`Extra Finisher T-Shirt ×${extraFinisherQty}`, extraFinisherFee);
    } else {
        html = `<p class="text-muted small mb-0">${MSG_SELECT}</p>`;
    }

    document.getElementById('feeBreakdown').innerHTML     = html;
    document.getElementById('totalAmount').textContent    = `RM ${total.toFixed(2)}`;
    document.getElementById('feeFinalAmount').textContent = `RM ${total.toFixed(2)}`;
}

updateFee();
</script>

<!-- Mobile: keep the running total in view while filling the form -->
<div class="reg-total-bar" id="regTotalBar" aria-hidden="true">
    <div>
        <small><?= __('reg.total') ?></small>
        <strong id="regTotalBarAmount">RM 0.00</strong>
    </div>
    <a href="#feeFinalBox" class="btn" tabindex="-1"><?= __('reg.submit') ?></a>
</div>

<script>
(function () {
    const form       = document.getElementById('registrationForm');
    const MSG_REQ    = <?= json_encode(__('ui.field_required')) ?>;
    const MSG_FIX    = <?= json_encode(__('ui.fix_fields')) ?>;

    // Tie each visible label to its field so screen readers announce it (adds ids where missing)
    let autoId = 0;
    form.querySelectorAll('label.form-label').forEach(label => {
        if (label.htmlFor) return;
        const field = label.parentElement.querySelector('input:not([type=hidden]):not([type=radio]), select, textarea');
        if (!field) return;
        if (!field.id) field.id = 'fld-' + (++autoId);
        label.htmlFor = field.id;
    });

    // --- Client-side check before submitting (the server still validates everything) ---
    const isShown = el => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
    const groupOf = el => el.closest('.col-md-6, .col-md-5, .col-md-4, .col-md-3, .col-12, .declaration-box') || el.parentElement;

    function clearError(holder) {
        holder.querySelectorAll('.field-error[data-live]').forEach(n => n.remove());
        holder.querySelectorAll('.is-invalid[data-live]').forEach(n => { n.classList.remove('is-invalid'); n.removeAttribute('data-live'); n.removeAttribute('aria-invalid'); });
        holder.classList.remove('group-invalid');
    }
    function flag(field, holder, msg) {
        holder = holder || groupOf(field);
        if (holder.querySelector('.field-error[data-live]')) return;
        if (field && field.classList) {
            field.classList.add('is-invalid');
            field.setAttribute('data-live', '');
            field.setAttribute('aria-invalid', 'true');
        }
        const note = document.createElement('div');
        note.className = 'field-error';
        note.setAttribute('data-live', '');
        note.textContent = msg || MSG_REQ;
        if (field && field.id) {
            note.id = field.id + '-err';
            field.setAttribute('aria-describedby', note.id);
        }
        holder.appendChild(note);
        return holder;
    }

    function validate() {
        form.querySelectorAll('.field-error[data-live]').forEach(n => n.remove());
        form.querySelectorAll('[data-live]').forEach(n => { n.classList.remove('is-invalid'); n.removeAttribute('data-live'); n.removeAttribute('aria-invalid'); });
        form.querySelectorAll('.group-invalid').forEach(n => n.classList.remove('group-invalid'));
        const bad = [];

        // Plain required fields that are currently visible
        form.querySelectorAll('input[required]:not([type=checkbox]):not([type=radio]), select[required], textarea[required]').forEach(f => {
            if (!isShown(f)) return;
            if (!f.value.trim()) bad.push(flag(f));
            else if (f.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.value.trim())) bad.push(flag(f, null, f.validationMessage || MSG_REQ));
        });

        // Date of birth (three dropdowns)
        const dobDay = document.getElementById('dob_day');
        if (!document.getElementById('dob_hidden').value) {
            ['dob_day', 'dob_month', 'dob_year'].forEach(id => {
                const s = document.getElementById(id);
                if (!s.value) { s.classList.add('is-invalid'); s.setAttribute('data-live', ''); }
            });
            bad.push(flag(dobDay, dobDay.closest('.col-md-4')));
        }

        // Distance and runner category (radio groups)
        if (!form.querySelector('[name="category_id"]:checked')) {
            const row = form.querySelector('.category-radio').closest('.row');
            row.classList.add('group-invalid');
            bad.push(flag(null, row));
        }
        if (!form.querySelector('[name="race_category"]:checked')) {
            const row = form.querySelector('.runner-cat-radio').closest('.row');
            row.classList.add('group-invalid');
            bad.push(flag(null, row));
        }

        // Size fields that only matter when their section is shown
        const finisher = document.getElementById('finisherShirtSize');
        if (isShown(finisher) && !finisher.value) bad.push(flag(finisher));
        [['buy_extra_event', 'extra_event_size'], ['buy_finisher', 'extra_finisher_size']].forEach(([toggle, sizeName]) => {
            const yes = form.querySelector(`[name="${toggle}"]:checked`);
            const size = form.querySelector(`[name="${sizeName}"]`);
            if (yes && yes.value === 'yes' && !size.value) bad.push(flag(size));
        });

        // Declarations
        ['healthDecl', 'termsAccept'].forEach(id => {
            const c = document.getElementById(id);
            if (!c.checked) bad.push(flag(c, c.closest('.declaration-box')));
        });

        return bad.filter(Boolean);
    }

    form.addEventListener('submit', e => {
        const bad = validate();
        if (!bad.length) return;
        e.preventDefault();
        if (window.showToast) window.showToast(MSG_FIX);
        const first = bad[0];
        const nav = document.getElementById('mainNav');
        window.scrollTo({ top: first.getBoundingClientRect().top + window.scrollY - (nav ? nav.offsetHeight : 0) - 24 });
        const focusable = first.querySelector('input:not([type=hidden]), select, textarea');
        if (focusable) focusable.focus({ preventScroll: true });
    });

    // Clear a field's message as soon as it is fixed
    form.addEventListener('input',  e => { const h = e.target.closest('.row, .col-md-6, .col-md-5, .col-md-4, .col-md-3, .col-12, .declaration-box'); if (h && h.querySelector('[data-live]')) clearError(groupOf(e.target)); });
    form.addEventListener('change', e => {
        if (e.target.matches('.category-radio, .runner-cat-radio')) { const r = e.target.closest('.row'); clearError(r); }
        else if (e.target.closest('#dob_day, #dob_month, #dob_year')) clearError(e.target.closest('.col-md-4'));
        else clearError(groupOf(e.target));
    });

    // --- Mobile running total ---
    const bar     = document.getElementById('regTotalBar');
    const barAmt  = document.getElementById('regTotalBarAmount');
    const finalEl = document.getElementById('feeFinalAmount');
    const sync = () => { barAmt.textContent = finalEl.textContent; };
    sync();
    new MutationObserver(sync).observe(finalEl, { childList: true, characterData: true, subtree: true });
    document.body.classList.add('has-total-bar');
    if ('IntersectionObserver' in window) {
        let formVisible = false, finalVisible = false;
        const update = () => bar.classList.toggle('show', formVisible && !finalVisible);
        new IntersectionObserver(es => { formVisible = es[0].isIntersecting; update(); }).observe(form);
        new IntersectionObserver(es => { finalVisible = es[0].isIntersecting; update(); }).observe(document.getElementById('feeFinalBox'));
    }
})();
</script>

<?php include PUBLIC_PATH . '/includes/footer.php'; ?>
