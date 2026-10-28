<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';

setSecurityHeaders();

if (empty($_SESSION['reg_success'])) {
    redirect(APP_URL . '/');
}

$regData     = $_SESSION['reg_success'];
$participant = Database::fetchOne('SELECT * FROM participants WHERE id = ?', [$regData['participant_id']]);

if (!$participant) {
    redirect(APP_URL . '/');
}

$category    = Database::fetchOne('SELECT * FROM event_categories WHERE id = ?', [$participant['category_id']]);
$shirtOrders = Database::fetchAll('SELECT * FROM shirt_orders WHERE participant_id = ?', [$participant['id']]);

$contactPhone = preg_replace('/[^0-9]/', '', getSetting('contact_phone', '0138804954'));
if (substr($contactPhone, 0, 1) === '0') {
    $contactPhone = '60' . substr($contactPhone, 1);
}
$waMsg = urlencode('Hi, I have completed my bank transfer for Nuhu Dolorosa Charity Run. My reference number is: ' . $participant['reference_number']);
$waUrl = 'https://wa.me/' . $contactPhone . '?text=' . $waMsg;

$pageTitle = __('suc.title');
include PUBLIC_PATH . '/includes/header.php';
?>

<div class="success-hero py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 text-center">
                <div class="success-icon-wrap mb-4">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="success-ring"></div>
                </div>
                <h1 class="success-title"><?= __('suc.title') ?></h1>
                <p class="success-sub"><?= __('suc.pending') ?></p>
            </div>
        </div>
    </div>
</div>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <!-- Reference Box -->
                <div class="ref-highlight-box mb-4">
                    <div class="ref-highlight-label"><?= __('suc.ref_label') ?></div>
                    <div class="ref-highlight-value"><?= e($participant['reference_number']) ?></div>
                    <div class="ref-highlight-note"><?= __('suc.ref_note') ?></div>
                </div>

                <!-- Registration Details -->
                <div class="success-details-card mb-4">
                    <h5 class="success-card-header"><i class="fas fa-user me-2"></i> <?= __('suc.details') ?></h5>
                    <div class="row g-3 p-3">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr><th class="text-muted fw-normal" width="45%"><?= __('suc.name') ?></th><td class="fw-600"><?= e($participant['full_name']) ?></td></tr>
                                <tr><th class="text-muted fw-normal"><?= __('suc.ic') ?></th><td><?= e($participant['ic_number']) ?></td></tr>
                                <tr><th class="text-muted fw-normal"><?= __('suc.email') ?></th><td><?= e($participant['email']) ?></td></tr>
                                <tr><th class="text-muted fw-normal"><?= __('suc.contact') ?></th><td><?= e($participant['contact_number']) ?></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr><th class="text-muted fw-normal" width="45%"><?= __('suc.distance') ?></th><td class="fw-700 text-primary fs-5"><?= e($category['distance']) ?></td></tr>
                                <tr><th class="text-muted fw-normal"><?= __('suc.category') ?></th><td><?= e($category['name']) ?></td></tr>
                                <tr><th class="text-muted fw-normal"><?= __('suc.runner_cat') ?></th><td><?= e($participant['race_category']) ?></td></tr>
                                <tr><th class="text-muted fw-normal"><?= __('suc.reg_fee') ?></th><td class="fw-700 text-warning"><?= formatMYR((float)$category['fee']) ?></td></tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Shirt Details -->
                <div class="success-details-card mb-4">
                    <h5 class="success-card-header"><i class="fas fa-tshirt me-2"></i> <?= __('suc.shirts') ?></h5>
                    <div class="p-3">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <th class="text-muted fw-normal" width="50%"><?= __('suc.event_shirt') ?></th>
                                <td><?= e(shirtSizeLabel($participant['event_shirt_size'])) ?></td>
                            </tr>
                            <?php if (!empty($participant['finisher_shirt_size'])): ?>
                            <tr>
                                <th class="text-muted fw-normal"><?= __('suc.finisher_shirt') ?></th>
                                <td><?= e(shirtSizeLabel($participant['finisher_shirt_size'])) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach ($shirtOrders as $order): ?>
                            <tr>
                                <th class="text-muted fw-normal">
                                    <?= $order['shirt_type'] === 'Finisher' ? __('suc.extra_finisher') : __('suc.extra_event') ?>
                                    <span class="text-muted">(<?= __('suc.purchased') ?>)</span>
                                </th>
                                <td><?= e(shirtSizeLabel($order['size'])) ?> &times;<?= $order['quantity'] ?> — <?= formatMYR((float)$order['total_price']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>

                <!-- Payment Notice -->
                <div class="rounded-3 p-4 mb-3" style="background:linear-gradient(135deg,#fef9c3,#fef3c7);border-left:4px solid #d97706;">
                    <h6 class="fw-700 mb-2"><i class="fas fa-university me-2 text-warning"></i><?= __('suc.payment_title') ?></h6>
                    <p class="small text-dark mb-2"><?= __('suc.payment_note') ?></p>
                    <div class="small text-dark">
                        <div class="mb-1"><span class="opacity-75">Bank:</span> <strong><?= __('about.bank.name') ?></strong></div>
                        <div class="mb-1"><span class="opacity-75">Account Name:</span> <strong><?= __('about.bank.acc_name') ?></strong></div>
                        <div><span class="opacity-75">Account No.:</span> <strong class="fs-6 text-primary"><?= __('about.bank.acc_num') ?></strong></div>
                    </div>
                    <div class="mt-2 small text-dark"><strong><?= __('suc.payment_ref') ?></strong> <?= e($participant['reference_number']) ?></div>
                </div>

                <!-- DuitNow QR -->
                <div class="rounded-3 p-4 mb-4 text-center" style="background:#fff5f7;border:1px solid #fce7f3;">
                    <p class="fw-700 mb-1" style="color:#e11d6a;font-size:0.85rem;">
                        <i class="fas fa-qrcode me-1"></i> Or scan DuitNow QR to pay instantly:
                    </p>
                    <p class="text-muted small mb-3">Accepted by all participating Banks and e-Wallets in Malaysia.</p>
                    <div class="d-inline-block rounded-4 p-3 bg-white shadow-sm" style="border:2px solid #fce7f3;">
                        <img src="<?= APP_URL ?>/assets/images/duitnow-qr.png"
                             alt="DuitNow QR — RTDKK ST. ANNE PENAMPANG BARU"
                             onclick="openQRLightbox(this.src)"
                             title="Click to enlarge"
                             style="width:210px;height:210px;object-fit:contain;display:block;cursor:zoom-in;">
                        <div class="mt-2 text-center" style="font-size:11px;font-weight:700;color:#374151;letter-spacing:.04em;">
                            RTDKK ST. ANNE PENAMPANG BARU
                        </div>
                        <div class="text-center" style="font-size:10px;color:#9ca3af;">Malaysia National QR · DuitNow</div>
                        <div class="text-center" style="font-size:10px;color:#e11d6a;margin-top:4px;">&#128269; Tap to enlarge</div>
                    </div>
                    <p class="text-muted small mt-3 mb-0">
                        Use reference <strong class="text-dark"><?= e($participant['reference_number']) ?></strong> as your payment note.
                    </p>
                </div>

                <!-- Upload Receipt & WhatsApp -->
                <div class="rounded-3 p-4 mb-4" style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border-left:4px solid #2563eb;">
                    <h6 class="fw-700 mb-1"><i class="fas fa-upload me-2 text-primary"></i><?= __('suc.upload_title') ?></h6>
                    <p class="small text-dark mb-3"><?= __('suc.upload_desc') ?></p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= APP_URL ?>/upload-receipt.php?ref=<?= urlencode($participant['reference_number']) ?>"
                           class="btn btn-primary btn-sm">
                            <i class="fas fa-receipt me-1"></i><?= __('suc.upload_btn') ?>
                        </a>
                        <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener noreferrer"
                           class="btn btn-success btn-sm">
                            <i class="fab fa-whatsapp me-1"></i><?= __('suc.wa_btn') ?>
                        </a>
                    </div>
                    <div class="mt-2 small text-muted"><i class="fas fa-info-circle me-1"></i><?= __('suc.wa_desc') ?></div>
                </div>

                <!-- Event Reminder -->
                <div class="event-reminder-card mb-4">
                    <h5 class="fw-700 mb-3"><i class="fas fa-calendar-alt me-2 text-primary"></i> <?= __('suc.reminder') ?></h5>
                    <div class="row g-3">
                        <div class="col-sm-4 text-center">
                            <div class="reminder-icon"><i class="fas fa-calendar-day text-warning"></i></div>
                            <div class="reminder-label"><?= __('suc.date') ?></div>
                            <div class="reminder-value"><?= __('hero.event_date') ?></div>
                        </div>
                        <div class="col-sm-4 text-center">
                            <div class="reminder-icon"><i class="fas fa-clock text-warning"></i></div>
                            <div class="reminder-label"><?= __('suc.flagoff') ?></div>
                            <div class="reminder-value">5:30 AM</div>
                        </div>
                        <div class="col-sm-4 text-center">
                            <div class="reminder-icon"><i class="fas fa-map-marker-alt text-warning"></i></div>
                            <div class="reminder-label"><?= __('suc.venue') ?></div>
                            <div class="reminder-value"><?= e(getSetting('event_venue')) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Next Steps -->
                <div class="next-steps-card mb-4">
                    <h5 class="fw-700 mb-3"><i class="fas fa-tasks me-2 text-primary"></i> <?= __('suc.next') ?></h5>
                    <ol class="next-steps-list">
                        <li><?= __('suc.next1', e($participant['email'])) ?></li>
                        <li><?= __('suc.next2') ?></li>
                        <li><?= __('suc.next3') ?></li>
                        <li><?= __('suc.next4') ?></li>
                    </ol>
                </div>

                <div class="text-center mt-4">
                    <a href="<?= APP_URL ?>/" class="btn btn-primary-custom me-3">
                        <i class="fas fa-home me-2"></i> <?= __('suc.home') ?>
                    </a>
                    <button onclick="window.print()" class="btn btn-outline-primary">
                        <i class="fas fa-print me-2"></i> <?= __('suc.print') ?>
                    </button>
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
