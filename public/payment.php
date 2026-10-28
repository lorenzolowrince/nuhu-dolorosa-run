<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/csrf.php';
require_once APP_PATH . '/payment/stripe.php';
require_once APP_PATH . '/payment/fpx.php';
require_once APP_PATH . '/email.php';

setSecurityHeaders();

if (empty($_SESSION['payment_pending'])) {
    setFlash('error', 'No pending registration found. Please register first.');
    redirect(APP_URL . '/register.php');
}

$pending        = $_SESSION['payment_pending'];
$participantId  = $pending['participant_id'];
$paymentRef     = $pending['payment_ref'];
$paymentMethod  = $pending['payment_method'];

$participant = Database::fetchOne('SELECT * FROM participants WHERE id = ?', [$participantId]);
$payment     = Database::fetchOne('SELECT * FROM payments WHERE payment_reference = ?', [$paymentRef]);
$category    = Database::fetchOne('SELECT * FROM event_categories WHERE id = ?', [$participant['category_id']]);
$shirt       = Database::fetchOne('SELECT * FROM shirt_orders WHERE participant_id = ?', [$participantId]);

if (!$participant || !$payment || !$category) {
    setFlash('error', 'Registration data not found.');
    redirect(APP_URL . '/register.php');
}

if ($participant['registration_status'] === 'Paid') {
    redirect(APP_URL . '/success.php?ref=' . urlencode($paymentRef));
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrFail();

    // ----- TEST MODE -----
    if (PAYMENT_TEST_MODE) {
        Database::execute(
            "UPDATE payments
             SET status = 'Success', gateway_transaction_id = ?, payment_date = NOW(),
                 gateway_response = ?
             WHERE payment_reference = ?",
            ['TEST-' . strtoupper(bin2hex(random_bytes(4))), json_encode(['mode' => 'test']), $paymentRef]
        );
        Database::execute(
            "UPDATE participants SET registration_status = 'Paid' WHERE id = ?",
            [$participantId]
        );
        Database::execute(
            'UPDATE event_categories SET current_participants = current_participants + 1 WHERE id = ?',
            [$participant['category_id']]
        );
        unset($_SESSION['payment_pending']);
        redirect(APP_URL . '/success.php?gateway=test&ref=' . urlencode($paymentRef));
    }
    // ----- END TEST MODE -----

    try {
        if ($paymentMethod === 'Stripe') {
            $stripe    = new StripePayment();
            $stripeUrl = $stripe->createCheckoutSession($participant, $payment, $category['name']);
            unset($_SESSION['payment_pending']);
            redirect($stripeUrl);
        } elseif ($paymentMethod === 'FPX') {
            $fpx    = new FPXPayment();
            $fpxUrl = $fpx->createBill($participant, $payment, $category['name']);
            unset($_SESSION['payment_pending']);
            redirect($fpxUrl);
        }
    } catch (Throwable $e) {
        error_log('Payment initiation error: ' . $e->getMessage());
        $error = APP_DEBUG
            ? '[DEBUG] ' . get_class($e) . ': ' . $e->getMessage()
            : 'Unable to connect to payment gateway. Please try again or contact support.';
    }
}

$pageTitle = __('pay_page.title');
include PUBLIC_PATH . '/includes/header.php';
?>

<div class="page-hero-sm">
    <div class="container text-center">
        <h1 class="page-hero-title"><i class="fas fa-lock me-2"></i> <?= __('pay_page.title') ?></h1>
        <p class="page-hero-sub"><?= __('pay_page.sub') ?></p>
    </div>
</div>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <?php if (PAYMENT_TEST_MODE): ?>
                    <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
                        <i class="fas fa-flask fs-5"></i>
                        <div><?= __('pay_page.test') ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger mb-4">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= e($error) ?>
                    </div>
                <?php endif; ?>

                <!-- Registration Summary -->
                <div class="summary-card mb-4">
                    <div class="summary-header">
                        <h5 class="mb-0"><i class="fas fa-user-check me-2"></i> <?= __('pay_page.summary') ?></h5>
                        <span class="badge bg-warning text-dark"><?= __('pay_page.pending') ?></span>
                    </div>
                    <div class="summary-body">
                        <div class="ref-display">
                            <span class="ref-label"><?= __('pay_page.ref') ?></span>
                            <span class="ref-value"><?= e($participant['reference_number']) ?></span>
                        </div>

                        <div class="row g-0">
                            <div class="col-md-6">
                                <div class="summary-section">
                                    <h6 class="summary-section-title"><i class="fas fa-user me-1"></i> <?= __('pay_page.section_participant') ?></h6>
                                    <dl class="summary-dl">
                                        <dt><?= __('pay_page.full_name') ?></dt><dd><?= e($participant['full_name']) ?></dd>
                                        <dt><?= __('pay_page.ic') ?></dt><dd><?= e($participant['ic_number']) ?></dd>
                                        <dt><?= __('pay_page.contact') ?></dt><dd><?= e($participant['contact_number']) ?></dd>
                                        <dt><?= __('pay_page.email') ?></dt><dd><?= e($participant['email']) ?></dd>
                                        <dt><?= __('pay_page.gender') ?></dt><dd><?= e($participant['gender']) ?></dd>
                                        <dt><?= __('pay_page.age') ?></dt><dd><?= e($participant['age']) ?> <?= __('pay_page.years') ?></dd>
                                    </dl>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="summary-section">
                                    <h6 class="summary-section-title"><i class="fas fa-trophy me-1"></i> <?= __('pay_page.section_reg') ?></h6>
                                    <dl class="summary-dl">
                                        <dt><?= __('pay_page.category') ?></dt><dd class="text-primary fw-700"><?= e($category['name']) ?></dd>
                                        <dt><?= __('pay_page.distance') ?></dt><dd><?= e($category['distance']) ?></dd>
                                        <dt><?= __('pay_page.reg_fee') ?></dt><dd><?= formatMYR((float)$payment['registration_fee']) ?></dd>
                                        <?php if ($shirt): ?>
                                            <dt><?= __('pay_page.shirt') ?></dt><dd><?= e(shirtSizeLabel($shirt['size'])) ?> x<?= $shirt['quantity'] ?></dd>
                                            <dt><?= __('pay_page.shirt_fee') ?></dt><dd><?= formatMYR((float)$payment['shirt_fee']) ?></dd>
                                        <?php endif; ?>
                                        <dt><?= __('pay_page.pay_method') ?></dt>
                                        <dd>
                                            <?php if ($paymentMethod === 'FPX'): ?>
                                                <i class="fas fa-university me-1 text-primary"></i> <?= __('pay_page.fpx_label') ?>
                                            <?php else: ?>
                                                <i class="fab fa-stripe-s me-1 text-primary"></i> <?= __('pay_page.stripe_label') ?>
                                            <?php endif; ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <div class="total-row">
                            <span><?= __('pay_page.total') ?></span>
                            <span class="total-value"><?= formatMYR((float)$payment['total_amount']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Payment button -->
                <form method="POST" action="">
                    <?= CSRF::field() ?>
                    <div class="text-center">
                        <button type="submit" class="btn btn-pay-now" id="payNowBtn">
                            <?php if ($paymentMethod === 'FPX'): ?>
                                <i class="fas fa-university me-2"></i> <?= __('pay_page.pay_fpx', formatMYR((float)$payment['total_amount'])) ?>
                            <?php else: ?>
                                <i class="fas fa-credit-card me-2"></i> <?= __('pay_page.pay_card', formatMYR((float)$payment['total_amount'])) ?>
                            <?php endif; ?>
                        </button>
                        <p class="text-muted small mt-3">
                            <i class="fas fa-shield-alt me-1 text-success"></i>
                            <?= __('pay_page.redirect') ?>
                        </p>
                        <a href="<?= APP_URL ?>/register.php" class="btn btn-link text-muted">
                            <i class="fas fa-arrow-left me-1"></i> <?= __('pay_page.go_back') ?>
                        </a>
                    </div>
                </form>

                <!-- Security note -->
                <div class="security-note mt-4">
                    <div class="d-flex align-items-start gap-3">
                        <i class="fas fa-lock text-success fs-4 mt-1"></i>
                        <div>
                            <strong><?= __('pay_page.secure_h') ?></strong>
                            <p class="text-muted small mb-0"><?= __('pay_page.secure_p') ?></p>
                        </div>
                    </div>
                </div>

                <!-- DuitNow QR Alternative -->
                <div class="text-center mt-4 pt-4" style="border-top:1px solid #e5e7eb;">
                    <p class="fw-700 mb-1" style="color:#e11d6a;font-size:0.9rem;">
                        <i class="fas fa-qrcode me-1"></i> Prefer to pay manually? Scan the DuitNow QR below:
                    </p>
                    <p class="text-muted small mb-3">Accepted by all participating Banks and e-Wallets in Malaysia.</p>
                    <div class="d-inline-block rounded-4 p-3 bg-white shadow-sm" style="border:2px solid #fce7f3;">
                        <img src="<?= APP_URL ?>/assets/images/duitnow-qr.png"
                             alt="DuitNow QR — RTDKK ST. ANNE PENAMPANG BARU"
                             onclick="openQRLightbox(this.src)"
                             title="Click to enlarge"
                             style="width:220px;height:220px;object-fit:contain;display:block;cursor:zoom-in;">
                        <div class="mt-2 text-center" style="font-size:11px;font-weight:700;color:#374151;letter-spacing:.04em;">
                            RTDKK ST. ANNE PENAMPANG BARU
                        </div>
                        <div class="text-center" style="font-size:10px;color:#9ca3af;">Malaysia National QR · DuitNow</div>
                        <div class="text-center" style="font-size:10px;color:#e11d6a;margin-top:4px;">&#128269; Tap to enlarge</div>
                    </div>
                    <p class="text-muted small mt-3">
                        <i class="fas fa-info-circle me-1 text-primary"></i>
                        After manual payment, upload your receipt via
                        <a href="<?= APP_URL ?>/upload-receipt.php" class="text-primary fw-600">Upload Receipt</a>.
                    </p>
                </div>

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
document.getElementById('payNowBtn').addEventListener('click', function() {
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> <?= __('pay_page.redirecting') ?>';
    this.closest('form').submit();
});
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
