<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/csrf.php';

setSecurityHeaders();

$ref         = strtoupper(trim(sanitize($_GET['ref'] ?? '')));
$success     = false;
$errors      = [];
$participant = null;

// Pre-load participant if ref given in URL
if ($ref !== '') {
    $participant = Database::fetchOne(
        'SELECT p.*, ec.name AS category_name, ec.distance, ec.fee
         FROM participants p
         JOIN event_categories ec ON ec.id = p.category_id
         WHERE p.reference_number = ?',
        [$ref]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrFail();

    $postRef     = strtoupper(trim(sanitize($_POST['reference_number'] ?? '')));
    $participant = Database::fetchOne(
        'SELECT p.*, ec.name AS category_name, ec.distance, ec.fee
         FROM participants p
         JOIN event_categories ec ON ec.id = p.category_id
         WHERE p.reference_number = ?',
        [$postRef]
    );

    if (!$participant) {
        $errors['reference_number'] = __('upload.not_found');
    } elseif ($participant['registration_status'] === 'Cancelled') {
        $errors['reference_number'] = __('upload.cancelled_msg');
    }

    if (empty($errors)) {
        $receiptFile = $_FILES['payment_receipt'] ?? null;
        if (!$receiptFile || $receiptFile['error'] === UPLOAD_ERR_NO_FILE) {
            $errors['payment_receipt'] = 'Please select a file to upload.';
        } elseif ($receiptFile['error'] !== UPLOAD_ERR_OK) {
            $errors['payment_receipt'] = 'Upload failed. Please try again.';
        } else {
            $allowedMime = ['image/jpeg', 'image/png', 'application/pdf'];
            $maxBytes    = 5 * 1024 * 1024;
            if (!in_array($receiptFile['type'], $allowedMime, true)) {
                $errors['payment_receipt'] = 'Only JPG, PNG or PDF files are accepted.';
            } elseif ($receiptFile['size'] > $maxBytes) {
                $errors['payment_receipt'] = 'File must be under 5MB.';
            }
        }
    }

    if (empty($errors) && $participant) {
        $ext       = strtolower(pathinfo($_FILES['payment_receipt']['name'], PATHINFO_EXTENSION));
        $safeExt   = in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true) ? $ext : 'bin';
        $filename  = $postRef . '_' . time() . '.' . $safeExt;
        $uploadDir = PUBLIC_PATH . '/assets/uploads/receipts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        if (move_uploaded_file($_FILES['payment_receipt']['tmp_name'], $uploadDir . $filename)) {
            Database::execute(
                'UPDATE participants SET payment_receipt = ? WHERE reference_number = ?',
                [$filename, $postRef]
            );
            $participant['payment_receipt'] = $filename;
            $success = true;
        } else {
            $errors['payment_receipt'] = 'Failed to save file. Please try again.';
        }
    }
}

$statusMap = ['Confirmed' => 'success', 'Pending' => 'warning', 'Cancelled' => 'danger'];

$pageTitle = __('upload.page_title');
include PUBLIC_PATH . '/includes/header.php';
?>

<div class="page-hero-sm">
    <div class="container text-center">
        <h1 class="page-hero-title"><i class="fas fa-receipt me-2"></i><?= __('upload.page_title') ?></h1>
        <p class="page-hero-sub"><?= __('upload.page_sub') ?></p>
    </div>
</div>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">

                <?php if ($success): ?>
                    <!-- SUCCESS STATE -->
                    <div class="text-center mb-4">
                        <div style="width:72px;height:72px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                        <h3 class="fw-700"><?= __('upload.success_title') ?></h3>
                        <p class="text-muted"><?= __('upload.success_msg') ?></p>
                    </div>

                    <div class="form-card">
                        <div class="form-card-body">
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="text-muted small"><?= __('suc.name') ?></div>
                                    <div class="fw-600"><?= e($participant['full_name']) ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted small"><?= __('suc.ref_label') ?></div>
                                    <div class="fw-700 text-primary"><?= e($participant['reference_number']) ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted small"><?= __('suc.category') ?></div>
                                    <div><?= e($participant['category_name']) ?> <span class="badge bg-primary"><?= e($participant['distance']) ?></span></div>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted small"><?= __('upload.check_status') ?></div>
                                    <div><span class="badge bg-<?= $statusMap[$participant['registration_status']] ?? 'secondary' ?>"><?= e($participant['registration_status']) ?></span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <a href="<?= APP_URL ?>/" class="btn btn-primary-custom">
                            <i class="fas fa-home me-2"></i><?= __('upload.back') ?>
                        </a>
                    </div>

                <?php elseif ($participant && $participant['registration_status'] === 'Confirmed'): ?>
                    <!-- ALREADY CONFIRMED -->
                    <div class="text-center mb-4">
                        <div style="width:72px;height:72px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                            <i class="fas fa-medal fa-2x text-success"></i>
                        </div>
                        <h3 class="fw-700 text-success"><?= __('upload.confirmed_msg') ?></h3>
                    </div>

                    <div class="form-card">
                        <div class="form-card-body">
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="text-muted small"><?= __('suc.name') ?></div>
                                    <div class="fw-600"><?= e($participant['full_name']) ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted small"><?= __('suc.ref_label') ?></div>
                                    <div class="fw-700 text-primary"><?= e($participant['reference_number']) ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted small"><?= __('suc.category') ?></div>
                                    <div><?= e($participant['category_name']) ?> <span class="badge bg-primary"><?= e($participant['distance']) ?></span></div>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted small"><?= __('suc.reg_fee') ?></div>
                                    <div class="fw-700 text-success"><?= formatMYR((float)$participant['fee']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <a href="<?= APP_URL ?>/" class="btn btn-primary-custom">
                            <i class="fas fa-home me-2"></i><?= __('upload.back') ?>
                        </a>
                    </div>

                <?php else: ?>
                    <!-- UPLOAD FORM -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <ul class="mb-0 mt-1 ps-3">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= e($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($participant && !empty($participant['payment_receipt'])): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i><?= __('upload.replace_note') ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-card">
                        <div class="form-card-header">
                            <span class="form-step-num"><i class="fas fa-receipt"></i></span>
                            <div>
                                <h5 class="mb-0"><?= __('upload.page_title') ?></h5>
                                <small class="text-muted"><?= __('upload.page_sub') ?></small>
                            </div>
                        </div>
                        <div class="form-card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <?= CSRF::field() ?>

                                <div class="mb-4">
                                    <label class="form-label required-label"><?= __('upload.ref_label') ?></label>
                                    <input type="text" name="reference_number"
                                           class="form-control <?= isset($errors['reference_number']) ? 'is-invalid' : '' ?>"
                                           value="<?= e($participant['reference_number'] ?? strtoupper($_POST['reference_number'] ?? '')) ?>"
                                           placeholder="<?= __('upload.ref_ph') ?>"
                                           maxlength="20" required style="text-transform:uppercase;font-family:monospace;font-size:1.1rem;">
                                    <?php if (isset($errors['reference_number'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['reference_number']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($participant && $participant['registration_status'] !== 'Cancelled'): ?>
                                        <div class="form-text text-success">
                                            <i class="fas fa-check-circle me-1"></i>
                                            <?= e($participant['full_name']) ?> — <?= e($participant['category_name']) ?>
                                            <span class="badge bg-<?= $statusMap[$participant['registration_status']] ?? 'secondary' ?> ms-1"><?= e($participant['registration_status']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label required-label"><?= __('upload.file_label') ?></label>
                                    <input type="file" name="payment_receipt" id="paymentReceipt"
                                           class="form-control <?= isset($errors['payment_receipt']) ? 'is-invalid' : '' ?>"
                                           accept=".jpg,.jpeg,.png,.pdf" required>
                                    <div class="form-text"><?= __('upload.file_hint') ?></div>
                                    <?php if (isset($errors['payment_receipt'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['payment_receipt']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- Bank details reminder -->
                                <div class="rounded-3 p-3 mb-3" style="background:#eff6ff;border-left:4px solid #3b82f6;">
                                    <div class="small text-dark">
                                        <div class="fw-700 mb-1"><i class="fas fa-university me-1 text-primary"></i><?= __('suc.payment_title') ?></div>
                                        <div><span class="opacity-75">Bank:</span> <strong><?= __('about.bank.name') ?></strong></div>
                                        <div><span class="opacity-75">Account Name:</span> <strong><?= __('about.bank.acc_name') ?></strong></div>
                                        <div><span class="opacity-75">Account No.:</span> <strong class="text-primary"><?= __('about.bank.acc_num') ?></strong></div>
                                    </div>
                                </div>

                                <!-- DuitNow QR -->
                                <div class="rounded-3 p-3 mb-4 text-center" style="background:#fff5f7;border:1px solid #fce7f3;">
                                    <div class="fw-700 mb-2" style="color:#e11d6a;font-size:0.8rem;">
                                        <i class="fas fa-qrcode me-1"></i> Or scan DuitNow QR:
                                    </div>
                                    <div class="d-inline-block rounded-3 p-2 bg-white shadow-sm" style="border:1.5px solid #fce7f3;">
                                        <img src="<?= APP_URL ?>/assets/images/duitnow-qr.png"
                                             alt="DuitNow QR — RTDKK ST. ANNE PENAMPANG BARU"
                                             onclick="openQRLightbox(this.src)"
                                             title="Click to enlarge"
                                             style="width:170px;height:170px;object-fit:contain;display:block;cursor:zoom-in;">
                                        <div class="mt-1 text-center" style="font-size:9px;font-weight:700;color:#374151;letter-spacing:.04em;">
                                            RTDKK ST. ANNE PENAMPANG BARU
                                        </div>
                                        <div class="text-center" style="font-size:9px;color:#9ca3af;">Malaysia National QR · DuitNow</div>
                                        <div class="text-center" style="font-size:9px;color:#e11d6a;margin-top:3px;">&#128269; Tap to enlarge</div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-submit-register w-100">
                                    <i class="fas fa-upload me-2"></i><?= __('upload.submit') ?>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="text-center mt-3">
                        <a href="<?= APP_URL ?>/" class="text-muted small">
                            <i class="fas fa-arrow-left me-1"></i><?= __('upload.back') ?>
                        </a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</section>

<div id="qr-lightbox" onclick="closeQRLightbox()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:9999;cursor:zoom-out;align-items:center;justify-content:center;">
    <img id="qr-lightbox-img" src="" alt="DuitNow QR"
         style="max-width:92vw;max-height:90vh;object-fit:contain;border-radius:12px;box-shadow:0 0 60px rgba(225,29,106,.4);">
    <button onclick="closeQRLightbox()" title="Close"
            style="position:absolute;top:16px;right:20px;background:none;border:none;color:#fff;font-size:2rem;line-height:1;cursor:pointer;">&#x2715;</button>
</div>

<script>
function openQRLightbox(src) {
    document.getElementById('qr-lightbox-img').src = src;
    var lb = document.getElementById('qr-lightbox');
    lb.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeQRLightbox() {
    document.getElementById('qr-lightbox').style.display = 'none';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeQRLightbox(); });
</script>

<?php include PUBLIC_PATH . '/includes/footer.php'; ?>
