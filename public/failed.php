<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/payment/stripe.php';
require_once APP_PATH . '/payment/fpx.php';

setSecurityHeaders();

$gateway    = sanitize($_GET['gateway'] ?? '');
$paymentRef = sanitize($_GET['ref']     ?? '');

$payment     = null;
$participant = null;

if ($paymentRef) {
    $payment = Database::fetchOne('SELECT * FROM payments WHERE payment_reference = ?', [$paymentRef]);
    if ($payment) {
        $participant = Database::fetchOne('SELECT * FROM participants WHERE id = ?', [$payment['participant_id']]);

        if ($payment['status'] === 'Pending') {
            if ($gateway === 'stripe') {
                (new StripePayment())->markPaymentFailed($paymentRef, 'cancelled');
            } else {
                (new FPXPayment())->markPaymentFailed($paymentRef, ['reason' => 'cancelled']);
            }
        }
    }
}

$pageTitle = __('fail.title');
include PUBLIC_PATH . '/includes/header.php';
?>

<section class="failed-hero py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 text-center">
                <div class="failed-icon-wrap mb-4">
                    <div class="failed-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
                <h1 class="failed-title"><?= __('fail.title') ?></h1>
                <p class="failed-sub"><?= __('fail.sub') ?></p>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">

                <?php if ($participant): ?>
                <div class="failed-info-card mb-4">
                    <h5 class="fw-700 mb-3"><i class="fas fa-info-circle me-2 text-warning"></i> <?= __('fail.your_reg') ?></h5>
                    <table class="table table-sm table-borderless">
                        <tr><th class="text-muted fw-normal" width="40%"><?= __('fail.ref') ?></th><td class="fw-700"><?= e($participant['reference_number']) ?></td></tr>
                        <tr><th class="text-muted fw-normal"><?= __('fail.name') ?></th><td><?= e($participant['full_name']) ?></td></tr>
                        <tr><th class="text-muted fw-normal"><?= __('fail.amount') ?></th><td><?= formatMYR((float)$payment['total_amount']) ?></td></tr>
                        <tr><th class="text-muted fw-normal"><?= __('fail.gateway') ?></th><td><?= e(ucfirst($gateway)) ?></td></tr>
                        <tr><th class="text-muted fw-normal"><?= __('fail.status') ?></th><td><span class="badge bg-danger"><?= __('fail.status_val') ?></span></td></tr>
                    </table>
                </div>
                <?php endif; ?>

                <div class="failed-options-card mb-4">
                    <h5 class="fw-700 mb-3"><i class="fas fa-redo me-2 text-primary"></i> <?= __('fail.options') ?></h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="option-card">
                                <i class="fas fa-user-plus text-primary fs-3 mb-2"></i>
                                <h6 class="fw-700"><?= __('fail.reg_again') ?></h6>
                                <p class="text-muted small"><?= __('fail.reg_again_p') ?></p>
                                <a href="<?= APP_URL ?>/register.php" class="btn btn-primary-custom btn-sm">
                                    <i class="fas fa-pencil-alt me-1"></i> <?= __('fail.reg_again') ?>
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="option-card">
                                <i class="fas fa-headset text-primary fs-3 mb-2"></i>
                                <h6 class="fw-700"><?= __('fail.support') ?></h6>
                                <p class="text-muted small"><?= __('fail.support_p') ?></p>
                                <a href="mailto:<?= e(getSetting('contact_email')) ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-envelope me-1"></i> <?= __('fail.email_us') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Common Reasons -->
                <div class="alert alert-light border">
                    <h6 class="fw-700"><i class="fas fa-question-circle me-2"></i> <?= __('fail.reasons_h') ?></h6>
                    <ul class="mb-0 small text-muted">
                        <li><?= __('fail.r1') ?></li>
                        <li><?= __('fail.r2') ?></li>
                        <li><?= __('fail.r3') ?></li>
                        <li><?= __('fail.r4') ?></li>
                        <li><?= __('fail.r5') ?></li>
                    </ul>
                </div>

                <div class="text-center mt-4">
                    <a href="<?= APP_URL ?>/" class="btn btn-outline-secondary">
                        <i class="fas fa-home me-2"></i> <?= __('fail.home') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include PUBLIC_PATH . '/includes/footer.php'; ?>
