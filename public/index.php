<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';

setSecurityHeaders();

$pageTitle        = 'Home';
$pageDescription  = 'Register for the Nuhu Dolorosa Ultra Trail 3.0 Charity Run – Run For Hope, 19 December 2026. Join 7KM, 15KM & 43KM trail run.';
$categories       = getActiveCategories();
$registrationOpen = getSetting('registration_open', '1') === '1';
$eventDate        = getSetting('event_date', '2026-12-19');
$eventVenue       = getSetting('event_venue');

include PUBLIC_PATH . '/includes/header.php';
?>

<!-- ============================================================ -->
<!-- HERO SECTION -->
<!-- ============================================================ -->
<section class="hero-section" id="hero">
    <div class="hero-overlay"></div>
    <canvas id="hero-canvas"
            onclick="openLightbox('<?= APP_URL ?>/assets/images/hero-bg.jpeg')"
            title="Click to view the Groundbreaking Ceremony photo"></canvas>
    <div class="hero-photo-hint">
        <i class="fas fa-search-plus me-1"></i> View ceremony photo
    </div>
    <div class="hero-particles" id="particles"></div>
    <div class="container position-relative">
        <div class="row align-items-center min-vh-hero">
            <div class="col-lg-8">
                <div class="hero-badge mb-3">
                    <i class="fas fa-heart me-1"></i> <?= __('hero.badge') ?>
                </div>
                <h1 class="hero-title">
                    Run<br>
                    <span class="text-warning">For Hope</span>
                </h1>
                <p class="hero-event-name">Nuhu Dolorosa Ultra Trail 3.0 Charity Run<br><span style="font-size:0.55em;opacity:0.85;letter-spacing:.02em;">(7KM, 15KM &amp; 43KM)</span></p>
                <div class="hero-meta d-flex flex-wrap gap-3 mb-4">
                    <span class="meta-chip"><i class="fas fa-calendar-alt me-1"></i> <?= __('hero.event_date') ?></span>
                    <a href="https://www.google.com/maps/search/St+Anne+Church+Penampang+Baru+Kimanis+Papar+Sabah" target="_blank" rel="noopener" class="meta-chip" style="text-decoration:none;">
                        <i class="fas fa-map-marker-alt me-1"></i> <?= e($eventVenue) ?>
                    </a>
                    <span class="meta-chip"><i class="fas fa-clock me-1"></i> <?= __('hero.flagoff') ?></span>
                </div>

                <!-- Countdown Timer -->
                <div class="countdown-box mb-4" id="countdown">
                    <div class="countdown-unit">
                        <span class="count-num" id="days">00</span>
                        <span class="count-label"><?= __('countdown.days') ?></span>
                    </div>
                    <div class="countdown-sep">:</div>
                    <div class="countdown-unit">
                        <span class="count-num" id="hours">00</span>
                        <span class="count-label"><?= __('countdown.hours') ?></span>
                    </div>
                    <div class="countdown-sep">:</div>
                    <div class="countdown-unit">
                        <span class="count-num" id="minutes">00</span>
                        <span class="count-label"><?= __('countdown.minutes') ?></span>
                    </div>
                    <div class="countdown-sep">:</div>
                    <div class="countdown-unit">
                        <span class="count-num" id="seconds">00</span>
                        <span class="count-label"><?= __('countdown.seconds') ?></span>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-3">
                    <?php if ($registrationOpen): ?>
                        <a href="<?= APP_URL ?>/register.php" class="btn btn-hero-primary">
                            <i class="fas fa-pencil-alt me-2"></i> <?= __('hero.register') ?>
                        </a>
                    <?php else: ?>
                        <button class="btn btn-hero-secondary" disabled>
                            <i class="fas fa-lock me-2"></i> <?= __('hero.reg_closed') ?>
                        </button>
                    <?php endif; ?>
                    <a href="#categories" class="btn btn-hero-outline">
                        <i class="fas fa-trophy me-2"></i> <?= __('hero.categories') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="hero-wave">
        <svg viewBox="0 0 1440 80" preserveAspectRatio="none"><path d="M0,0 C360,80 1080,80 1440,0 L1440,80 L0,80 Z" fill="#fff"/></svg>
    </div>
</section>

<!-- Hero graphic strip — slideshow -->
<div class="hero-graphic-strip">
    <div id="heroGraphicCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4500">
        <div class="carousel-inner">

            <!-- Slide 1: NDDK Logo -->
            <div class="carousel-item active">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-auto text-center">
                            <div class="hero-graphic">
                                <div class="hero-circle-outer">
                                    <div class="hero-circle-inner">
                                        <img src="<?= APP_URL ?>/assets/images/nddk-logo.png"
                                             alt="NDDK Logo"
                                             class="nddk-logo-click"
                                             onclick="openLightbox('<?= APP_URL ?>/assets/images/nddk-logo.png')"
                                             title="Click to enlarge">
                                    </div>
                                </div>
                            </div>
                            <div class="nddk-logo-hint">
                                <i class="fas fa-search-plus me-1"></i> Click to enlarge
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $gallerySlides = [
                ['event-poster.jpeg', 'Nuhu Dolorosa Ultra Trail 3.0 — Official Event Poster'],
                ['gallery-1.jpeg',    'Nuhu Dolorosa Ultra Trail 3.0 — Runners in Action'],
                ['gallery-2.jpeg',    'Shalom Kopivosian — Trail Recce Team'],
                ['gallery-3.jpeg',    'Shalom Kopivosian — Forest Trail Recce'],
                ['gallery-4.jpeg',    'Larian Pengharapan — Poster Rasmi (BM)'],
                ['gallery-5.jpeg',    'Nuhu Dolorosa Ultra Trail 3.0 — Sunset Runners'],
                ['gallery-6.jpeg',    'Larian Amal Pengharapan — Poster Air Terjun'],
                ['gallery-7.jpeg',    'Nuhu Dolorosa Ultra Trail 3.0 — Trail Runners'],
                ['gallery-8.jpeg',    'Nuhu Dolorosa Ultra Trail 3.0 — Rocky Hill Climbers'],
            ];
            foreach ($gallerySlides as [$file, $alt]):
                $src = APP_URL . '/assets/images/' . $file;
            ?>
            <div class="carousel-item">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-auto text-center">
                            <img src="<?= $src ?>"
                                 alt="<?= htmlspecialchars($alt) ?>"
                                 class="hero-strip-poster"
                                 onclick="openLightbox('<?= $src ?>')"
                                 title="Click to enlarge">
                            <div class="nddk-logo-hint mt-3">
                                <i class="fas fa-search-plus me-1"></i> Click to enlarge
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

        </div><!-- /.carousel-inner -->

        <!-- Prev / Next controls -->
        <button class="carousel-control-prev hero-strip-ctrl" type="button"
                data-bs-target="#heroGraphicCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next hero-strip-ctrl" type="button"
                data-bs-target="#heroGraphicCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
            <span class="visually-hidden">Next</span>
        </button>

        <!-- Dot indicators (10 slides total) -->
        <div class="carousel-indicators hero-strip-dots">
            <?php for ($i = 0; $i < 10; $i++): ?>
            <button type="button"
                    data-bs-target="#heroGraphicCarousel"
                    data-bs-slide-to="<?= $i ?>"
                    <?= $i === 0 ? 'class="active" aria-current="true"' : '' ?>
                    aria-label="Slide <?= $i + 1 ?>"></button>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- STATS SECTION -->
<!-- ============================================================ -->
<section class="stats-section py-4">
    <div class="container">
        <div class="row g-3">
            <?php
            $stats = [
                ['icon' => 'fas fa-users',              'value' => '900+',   'label' => __('stats.participants')],
                ['icon' => 'fas fa-route',              'value' => '3',      'label' => __('stats.categories')],
                ['icon' => 'fas fa-trophy',             'value' => 'RM 85+', 'label' => __('stats.starting')],
                ['icon' => 'fas fa-hand-holding-heart', 'value' => '100%',   'label' => __('stats.charity')],
            ];
            foreach ($stats as $s): ?>
                <div class="col-6 col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon"><i class="<?= $s['icon'] ?>"></i></div>
                        <div class="stat-value"><?= $s['value'] ?></div>
                        <div class="stat-label"><?= $s['label'] ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- ABOUT SECTION -->
<!-- ============================================================ -->
<section class="about-section py-5" id="about">
    <div class="container">
        <div class="row align-items-center gy-5">
            <div class="col-lg-5">
                <div class="about-img-wrap">
                    <div class="about-img-bg"></div>
                    <div class="about-img-card">
                        <div class="text-center p-4">
                            <img src="<?= APP_URL ?>/assets/images/nddk-logo.png" alt="NDDK Logo" style="width:120px;height:120px;object-fit:contain;" class="mb-3">
                            <h3 class="fw-800 text-dark">Run For Hope</h3>
                            <p class="text-muted"><?= __('hero.event_date') ?> | Kimanis, Papar, Sabah</p>
                            <div class="d-flex justify-content-center gap-3 mt-3">
                                <div class="text-center">
                                    <div class="fw-700 text-primary fs-4">7KM</div>
                                    <div class="small text-muted"><?= __('about.fun_run') ?></div>
                                </div>
                                <div class="vr"></div>
                                <div class="text-center">
                                    <div class="fw-700 text-primary fs-4">15KM</div>
                                    <div class="small text-muted"><?= __('about.trail') ?></div>
                                </div>
                                <div class="vr"></div>
                                <div class="text-center">
                                    <div class="fw-700 text-primary fs-4">43KM</div>
                                    <div class="small text-muted"><?= __('about.ultra') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="section-label"><?= __('about.label') ?></div>
                <h2 class="section-title"><?= __('about.title') ?></h2>
                <p class="text-muted fs-6 mb-4" style="line-height:1.85;">
                    <?= __('about.p1') ?>
                </p>
                <p class="text-muted fs-6 mb-4" style="line-height:1.85;">
                    <?= __('about.p2') ?>
                </p>
                <p class="text-muted fs-6 mb-4" style="line-height:1.85;">
                    <?= __('about.p3') ?>
                </p>

                <!-- Donation bank account box -->
                <div class="rounded-3 p-3 mb-4" style="background:linear-gradient(135deg,#fef9c3,#fef3c7);border-left:4px solid #d97706;">
                    <h6 class="fw-700 mb-2 text-dark"><i class="fas fa-university me-2 text-warning"></i><?= __('about.bank.title') ?></h6>
                    <div class="small text-dark">
                        <div class="mb-1"><span class="opacity-75">Bank:</span> <strong><?= __('about.bank.name') ?></strong></div>
                        <div class="mb-1"><span class="opacity-75">Account Name:</span> <strong><?= __('about.bank.acc_name') ?></strong></div>
                        <div><span class="opacity-75">Account Number:</span> <strong class="fs-6 text-primary"><?= __('about.bank.acc_num') ?></strong></div>
                    </div>
                </div>

                <p class="text-muted fs-6 mb-4" style="line-height:1.85;">
                    <?= __('about.p4') ?>
                </p>

                <!-- VIP / Distinguished Guests -->
                <div class="rounded-3 p-3 mb-4 d-flex align-items-start gap-3" style="background:#f5f0ff;border-left:4px solid #7C3AED;">
                    <div class="flex-shrink-0 rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;background:#ede9fe;">
                        <i class="fas fa-star text-primary"></i>
                    </div>
                    <div>
                        <h6 class="fw-700 mb-1 text-primary"><?= __('about.vip.title') ?></h6>
                        <p class="text-muted small mb-0"><?= __('about.vip.p') ?></p>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <?php
                    $features = [
                        'about.feat.medal', 'about.feat.shirt',
                        'about.feat.medical', 'about.feat.refresh',
                        'about.feat.goody', 'about.feat.prizes',
                    ];
                    foreach ($features as $feat): ?>
                        <div class="col-sm-6">
                            <div class="feature-item">
                                <i class="fas fa-check-circle text-primary me-2"></i>
                                <span><?= __($feat) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($registrationOpen): ?>
                    <a href="<?= APP_URL ?>/register.php" class="btn btn-primary-custom">
                        <i class="fas fa-pencil-alt me-2"></i> <?= __('about.register') ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- CATEGORIES SECTION -->
<!-- ============================================================ -->
<section class="categories-section py-5" id="categories">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-label"><?= __('cat.label') ?></div>
            <h2 class="section-title"><?= __('cat.title') ?></h2>
            <p class="text-light opacity-75"><?= __('cat.subtitle') ?></p>
        </div>
        <div class="row g-4 justify-content-center">
            <?php
            $icons    = ['fas fa-walking', 'fas fa-running', 'fas fa-bolt'];
            $featured = [false, true, false];
            foreach ($categories as $i => $cat):
                $isFeatured = $featured[$i] ?? false;
            ?>
                <div class="col-lg-4 col-md-6">
                    <div class="category-card <?= $isFeatured ? 'featured' : '' ?>">
                        <?php if ($isFeatured): ?>
                            <div class="category-badge-top"><?= __('cat.popular') ?></div>
                        <?php endif; ?>
                        <div class="category-icon">
                            <i class="<?= $icons[$i] ?? 'fas fa-running' ?>"></i>
                        </div>
                        <div class="category-km"><?= e($cat['distance']) ?></div>
                        <h3 class="category-name"><?= e($cat['name']) ?></h3>
                        <p class="category-desc"><?= e($cat['description']) ?></p>
                        <div class="category-price">
                            <span class="price-currency">RM</span>
                            <span class="price-amount"><?= number_format((float)$cat['fee'], 0) ?></span>
                        </div>
                        <ul class="category-perks">
                            <li><i class="fas fa-check"></i> <?= __('cat.perk.medal') ?></li>
                            <li><i class="fas fa-check"></i> <?= __('cat.perk.pack') ?></li>
                            <li><i class="fas fa-check"></i> <?= __('cat.perk.cert') ?></li>
                            <?php if ($cat['distance'] !== '7KM'): ?>
                                <li><i class="fas fa-check"></i> <?= __('cat.perk.trail') ?></li>
                            <?php endif; ?>
                            <?php if ($cat['distance'] === '43KM'): ?>
                                <li><i class="fas fa-check"></i> <?= __('cat.perk.ultra') ?></li>
                            <?php endif; ?>
                            <li><i class="fas fa-check"></i> <?= __('cat.perk.shirt') ?></li>
                            <?php if ($cat['distance'] === '43KM'): ?>
                                <li><i class="fas fa-check"></i> <?= __('cat.perk.finisher_shirt') ?></li>
                                <li><i class="fas fa-check"></i> <?= __('cat.perk.prizes_20') ?></li>
                            <?php elseif ($cat['distance'] === '15KM'): ?>
                                <li><i class="fas fa-check"></i> <?= __('cat.perk.prizes_10') ?></li>
                            <?php elseif ($cat['distance'] === '7KM'): ?>
                                <li><i class="fas fa-check"></i> <?= __('cat.perk.prizes_5') ?></li>
                            <?php endif; ?>
                        </ul>
                        <?php if ($registrationOpen): ?>
                            <a href="<?= APP_URL ?>/register.php?category=<?= $cat['id'] ?>" class="btn btn-category-register">
                                <?= __('cat.register_btn', number_format((float)$cat['fee'], 0)) ?>
                            </a>
                        <?php else: ?>
                            <button class="btn btn-category-register disabled" disabled><?= __('cat.closed') ?></button>
                        <?php endif; ?>
                        <?php if ($cat['max_participants'] > 0): ?>
                            <?php
                            $remaining = $cat['max_participants'] - $cat['current_participants'];
                            $pct = round(($cat['current_participants'] / $cat['max_participants']) * 100);
                            ?>
                            <div class="slot-bar mt-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted"><?= __('cat.slots', $remaining) ?></small>
                                    <small class="text-muted"><?= __('cat.filled', $pct) ?></small>
                                </div>
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar bg-warning" style="width:<?= $pct ?>%"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Shirt add-on note -->
        <div class="text-center mt-4">
            <div class="shirt-note">
                <i class="fas fa-tshirt me-2 text-warning"></i>
                <?= __('cat.shirt_note', e(getSetting('shirt_price', '35'))) ?>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- SHIRT SHOWCASE -->
<!-- ============================================================ -->
<?php
$shirt1 = PUBLIC_PATH . '/assets/images/shirt-sample-1.jpg';
$shirt2 = PUBLIC_PATH . '/assets/images/shirt-sample-2.jpg';
$shirt1Url = APP_URL . '/assets/images/shirt-sample-1.jpg';
$shirt2Url = APP_URL . '/assets/images/shirt-sample-2.jpg';
$imagesReady = file_exists($shirt1) && file_exists($shirt2);
?>
<section id="shirt" style="background:#f0f4ff;padding:60px 0;">
    <div class="container">

        <!-- Heading -->
        <div class="text-center mb-5">
            <span style="display:inline-block;background:#7C3AED;color:#fff;font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:4px 16px;border-radius:20px;margin-bottom:12px;">
                <?= __('shirt.label') ?>
            </span>
            <h2 style="font-family:'Montserrat',sans-serif;font-weight:800;font-size:2rem;color:#1e1b4b;margin-bottom:8px;">
                <?= __('shirt.title') ?>
            </h2>
            <p style="color:#6b7280;max-width:520px;margin:0 auto;">
                <?= __('shirt.subtitle') ?>
            </p>
        </div>

        <div class="row align-items-center g-4">

            <!-- Carousel / Images -->
            <div class="col-lg-7">
                <?php if ($imagesReady): ?>
                <div id="shirtCarousel" class="carousel slide carousel-fade"
                     data-bs-ride="carousel" data-bs-interval="4000"
                     style="border-radius:16px;overflow:hidden;box-shadow:0 12px 40px rgba(124,58,237,.18);">
                    <div class="carousel-inner">
                        <div class="carousel-item active">
                            <img src="<?= $shirt1Url ?>" class="d-block w-100"
                                 alt="T-Shirt Sample 1"
                                 style="max-height:500px;object-fit:contain;background:#e8eaf6;cursor:zoom-in;"
                                 onclick="openLightbox('<?= $shirt1Url ?>')">
                        </div>
                        <div class="carousel-item">
                            <img src="<?= $shirt2Url ?>" class="d-block w-100"
                                 alt="T-Shirt Sample 2"
                                 style="max-height:500px;object-fit:contain;background:#e8eaf6;cursor:zoom-in;"
                                 onclick="openLightbox('<?= $shirt2Url ?>')">
                        </div>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#shirtCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#shirtCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon"></span>
                    </button>
                    <div class="carousel-indicators" style="bottom:-28px;">
                        <button type="button" data-bs-target="#shirtCarousel" data-bs-slide-to="0" class="active" style="background:#7C3AED;"></button>
                        <button type="button" data-bs-target="#shirtCarousel" data-bs-slide-to="1" style="background:#7C3AED;"></button>
                    </div>
                </div>
                <p style="text-align:center;color:#9ca3af;font-size:12px;margin-top:36px;">
                    <i class="fas fa-search-plus me-1"></i> <?= __('shirt.zoom') ?>
                </p>
                <?php else: ?>
                <div style="background:#e8eaf6;border-radius:16px;padding:60px;text-align:center;">
                    <i class="fas fa-tshirt" style="font-size:4rem;color:#c4b5fd;"></i>
                    <p style="color:#6b7280;margin-top:16px;"><?= __('shirt.coming_soon') ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Info Panel -->
            <div class="col-lg-5">
                <div style="background:#fff;border-radius:16px;padding:32px;box-shadow:0 4px 20px rgba(0,0,0,.06);">

                    <h3 style="font-family:'Montserrat',sans-serif;font-weight:800;color:#1e1b4b;margin-bottom:4px;font-size:1.4rem;">
                        Nuhu Dolorosa Ultra Trail 3.0
                    </h3>
                    <p style="color:#7C3AED;font-weight:600;margin-bottom:16px;"><?= __('shirt.running_shirts') ?></p>

                    <?php
                    $feats = [
                        ['fas fa-palette',  __('shirt.feat.design')],
                        ['fas fa-tshirt',   __('shirt.feat.sizes')],
                        ['fas fa-wind',     __('shirt.feat.fabric')],
                        ['fas fa-star',     __('shirt.feat.print')],
                        ['fas fa-box-open', __('shirt.feat.collect')],
                    ];
                    foreach ($feats as [$ic, $tx]): ?>
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                        <div style="width:32px;height:32px;background:#ede9fe;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="<?= $ic ?>" style="color:#7C3AED;font-size:13px;"></i>
                        </div>
                        <span style="color:#374151;font-size:14px;"><?= $tx ?></span>
                    </div>
                    <?php endforeach; ?>

                    <!-- Included shirt -->
                    <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:12px;padding:14px 16px;margin:16px 0;">
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <div>
                                <div style="font-weight:700;color:#15803d;font-size:14px;"><i class="fas fa-gift me-1"></i> <?= __('cat.perk.shirt') ?></div>
                                <div style="color:#6b7280;font-size:12px;margin-top:2px;"><?= __('shirt.incl.note') ?></div>
                            </div>
                            <span style="background:#16a34a;color:#fff;font-weight:700;font-size:11px;padding:4px 12px;border-radius:20px;"><?= __('shirt.incl.badge') ?></span>
                        </div>
                    </div>

                    <!-- Purchasable shirts -->
                    <div style="background:#ede9fe;border-radius:12px;padding:14px 16px;margin-bottom:12px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <div>
                                <div style="font-weight:700;color:#7C3AED;font-size:14px;"><i class="fas fa-tshirt me-1"></i> <?= __('shirt.extra.label') ?></div>
                                <div style="color:#6b7280;font-size:12px;margin-top:2px;"><?= __('shirt.extra.note') ?></div>
                            </div>
                            <div style="font-family:'Montserrat',sans-serif;font-weight:800;font-size:1.4rem;color:#7C3AED;">
                                RM <?= number_format((float)getSetting('shirt_price', '40'), 0) ?>
                            </div>
                        </div>
                    </div>
                    <div style="background:#fef3c7;border:1px solid #fbbf24;border-radius:12px;padding:14px 16px;margin-bottom:16px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <div>
                                <div style="font-weight:700;color:#92400e;font-size:14px;"><i class="fas fa-medal me-1"></i> <?= __('cat.perk.finisher_shirt') ?></div>
                                <div style="color:#6b7280;font-size:12px;margin-top:2px;"><?= __('shirt.finisher.note') ?></div>
                            </div>
                            <div style="font-family:'Montserrat',sans-serif;font-weight:800;font-size:1.4rem;color:#b45309;">
                                RM <?= number_format((float)getSetting('shirt_price', '40'), 0) ?>
                            </div>
                        </div>
                    </div>

                    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;font-size:13px;color:#1d4ed8;margin-bottom:20px;">
                        <i class="fas fa-info-circle me-2"></i><?= __('shirt.note') ?>
                    </div>

                    <?php if ($registrationOpen): ?>
                    <a href="<?= APP_URL ?>/register.php"
                       style="display:block;text-align:center;background:linear-gradient(135deg,#6B21A8,#7C3AED);color:#fff;font-weight:700;padding:14px;border-radius:10px;text-decoration:none;font-size:15px;">
                        <i class="fas fa-pencil-alt me-2"></i> <?= __('about.register') ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Lightbox -->
<div id="lightbox" onclick="closeLightbox()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.9);z-index:9999;cursor:zoom-out;align-items:center;justify-content:center;">
    <img id="lightboxImg" src="" alt=""
         style="max-width:92vw;max-height:90vh;object-fit:contain;border-radius:8px;">
    <button onclick="closeLightbox()" title="Close"
            style="position:absolute;top:16px;right:20px;background:none;border:none;color:#fff;font-size:2rem;line-height:1;cursor:pointer;">&#x2715;</button>
</div>
<script>
function openLightbox(src){
    document.getElementById('lightboxImg').src = src;
    var lb = document.getElementById('lightbox');
    lb.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeLightbox(){
    document.getElementById('lightbox').style.display = 'none';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeLightbox(); });
</script>

<!-- ============================================================ -->
<!-- SIZE CHART -->
<!-- ============================================================ -->
<section id="size-chart" style="background:#fff;padding:60px 0;">
    <div class="container">
        <div class="text-center mb-4">
            <div class="section-label">APPAREL</div>
            <h2 class="section-title">T-Shirt Size Chart</h2>
            <p class="text-muted">All measurements are in inches &bull; Tolerance &plusmn;&thinsp;0.5 inch</p>
        </div>
        <div class="text-center">
            <img src="<?= APP_URL ?>/assets/images/size-chart.jpg"
                 alt="T-Shirt Size Chart — Adult and Kids Short/Long Sleeve"
                 onclick="openLightbox(this.src)"
                 style="max-width:100%;width:720px;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.12);cursor:zoom-in;">
            <p class="text-muted mt-2" style="font-size:.82rem;">
                <i class="fas fa-search-plus me-1"></i>Tap / click image to enlarge
            </p>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- HOW IT WORKS -->
<!-- ============================================================ -->
<section class="how-section py-5">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-label"><?= __('how.label') ?></div>
            <h2 class="section-title"><?= __('how.title') ?></h2>
        </div>
        <div class="row g-4">
            <?php
            $steps = [
                ['num' => '1', 'icon' => 'fas fa-user-plus',   'title' => __('how.s1.title'), 'desc' => __('how.s1.desc')],
                ['num' => '2', 'icon' => 'fas fa-tshirt',      'title' => __('how.s2.title'), 'desc' => __('how.s2.desc')],
                ['num' => '3', 'icon' => 'fas fa-credit-card', 'title' => __('how.s3.title'), 'desc' => __('how.s3.desc')],
                ['num' => '4', 'icon' => 'fas fa-envelope',    'title' => __('how.s4.title'), 'desc' => __('how.s4.desc')],
            ];
            foreach ($steps as $step):
            ?>
                <div class="col-md-3 col-sm-6">
                    <div class="step-card text-center">
                        <div class="step-num"><?= $step['num'] ?></div>
                        <div class="step-icon"><i class="<?= $step['icon'] ?>"></i></div>
                        <h5 class="step-title"><?= $step['title'] ?></h5>
                        <p class="step-desc text-muted small"><?= $step['desc'] ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- PAYMENT / BANK TRANSFER SECTION -->
<!-- ============================================================ -->
<section class="payment-section py-5">
    <div class="container">
        <div class="text-center mb-4">
            <div class="section-label"><?= __('pay.label') ?></div>
            <h2 class="section-title"><?= __('pay.title') ?></h2>
            <p class="text-muted"><?= __('pay.desc') ?></p>
        </div>
        <div class="row align-items-center justify-content-center g-4">

            <!-- Bank Transfer Card -->
            <div class="col-lg-5 col-md-7 text-center">
                <div class="rounded-4 p-4 mx-auto" style="max-width:420px;background:linear-gradient(135deg,#1e3a5f,#2563eb);color:#fff;">
                    <div class="mb-3">
                        <i class="fas fa-university fs-2 mb-2" style="opacity:0.8;"></i>
                        <div class="fw-700 fs-5"><?= __('about.bank.name') ?></div>
                    </div>
                    <div class="d-flex justify-content-between border-bottom border-white border-opacity-25 py-2">
                        <span style="opacity:0.75;"><?= __('pay.acc_name') ?></span>
                        <strong><?= __('about.bank.acc_name') ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 mt-1">
                        <span style="opacity:0.75;"><?= __('pay.acc_num') ?></span>
                        <strong class="fs-5 text-warning"><?= __('about.bank.acc_num') ?></strong>
                    </div>
                </div>
                <p class="text-muted small mt-3"><i class="fas fa-info-circle me-1 text-primary"></i> <?= __('pay.note') ?></p>
            </div>

            <!-- DuitNow QR -->
            <div class="col-lg-3 col-md-5 text-center">
                <div class="d-inline-block position-relative">
                    <div class="fw-700 mb-3" style="color:#e11d6a;font-size:0.85rem;letter-spacing:.05em;text-transform:uppercase;">
                        <i class="fas fa-qrcode me-1"></i> Scan to Pay — DuitNow QR
                    </div>
                    <div class="rounded-4 p-3 bg-white shadow" style="display:inline-block;border:2px solid #fce7f3;">
                        <img src="<?= APP_URL ?>/assets/images/duitnow-qr.png"
                             alt="DuitNow QR — RTDKK ST. ANNE PENAMPANG BARU"
                             onclick="openLightbox('<?= APP_URL ?>/assets/images/duitnow-qr.png')"
                             title="Click to enlarge"
                             style="width:200px;height:200px;object-fit:contain;display:block;cursor:zoom-in;">
                        <div class="mt-2" style="font-size:10px;font-weight:700;color:#374151;letter-spacing:.04em;text-align:center;">
                            RTDKK ST. ANNE PENAMPANG BARU
                        </div>
                        <div style="font-size:9px;color:#9ca3af;text-align:center;">Malaysia National QR · All Banks &amp; e-Wallets</div>
                        <div style="font-size:9px;color:#e11d6a;text-align:center;margin-top:4px;">&#128269; Tap to enlarge</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- CONTACT SECTION -->
<!-- ============================================================ -->
<section class="contact-section py-5" id="contact">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-label"><?= __('contact.label') ?></div>
            <h2 class="section-title"><?= __('contact.title') ?></h2>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-md-3 col-sm-6">
                <div class="contact-card text-center">
                    <div class="contact-icon"><i class="fas fa-phone-alt"></i></div>
                    <h5 class="fw-700"><?= __('contact.call') ?></h5>
                    <p class="text-muted small mb-1"><?= e(getSetting('contact_name', 'Jessel Kamijan')) ?></p>
                    <a href="tel:<?= preg_replace('/[^+0-9]/', '', getSetting('contact_phone')) ?>" class="contact-link">
                        <?= e(getSetting('contact_phone')) ?>
                    </a>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="contact-card text-center">
                    <div class="contact-icon"><i class="fas fa-phone-alt"></i></div>
                    <h5 class="fw-700"><?= __('contact.call2') ?></h5>
                    <p class="text-muted small mb-1"><?= e(getSetting('contact_name2', 'Prisca Kinsik')) ?></p>
                    <a href="tel:<?= preg_replace('/[^+0-9]/', '', getSetting('contact_phone2', '0172574954')) ?>" class="contact-link">
                        <?= e(getSetting('contact_phone2', '017-2574954')) ?>
                    </a>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="contact-card text-center">
                    <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                    <h5 class="fw-700"><?= __('contact.email_h') ?></h5>
                    <p class="text-muted small mb-1"><?= __('contact.email_sub') ?></p>
                    <a href="mailto:<?= e(getSetting('contact_email')) ?>" class="contact-link">
                        <?= e(getSetting('contact_email')) ?>
                    </a>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="contact-card text-center">
                    <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <h5 class="fw-700"><?= __('contact.venue_h') ?></h5>
                    <p class="text-muted small mb-1"><?= e(getSetting('event_venue')) ?></p>
                    <a href="https://www.google.com/maps/search/St+Anne+Church+Penampang+Baru+Kimanis+Papar+Sabah" target="_blank" rel="noopener" class="contact-link">
                        <?= __('contact.map') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Banner -->
<?php if ($registrationOpen): ?>
<section class="cta-section py-5">
    <div class="container text-center">
        <h2 class="cta-title mb-3"><?= __('cta.title') ?></h2>
        <p class="cta-sub mb-4"><?= __('cta.sub') ?></p>
        <a href="<?= APP_URL ?>/register.php" class="btn btn-cta">
            <i class="fas fa-running me-2"></i> <?= __('cta.btn') ?>
        </a>
    </div>
</section>
<?php endif; ?>

<script>
/* ── Bokeh particle system for hero background ── */
(function () {
    var canvas = document.getElementById('hero-canvas');
    var section = document.getElementById('hero');
    if (!canvas || !section) return;
    var ctx = canvas.getContext('2d');
    var particles = [];
    var RAF;

    function resize() {
        canvas.width  = section.offsetWidth;
        canvas.height = section.offsetHeight;
    }
    window.addEventListener('resize', resize);
    resize();

    /* Particle colours: gold, white, soft-purple */
    var colours = [
        'rgba(245,158,11,',   /* gold   */
        'rgba(255,255,255,',  /* white  */
        'rgba(168,85,247,',   /* purple */
        'rgba(99,172,255,',   /* blue   */
    ];

    function Particle() {
        this.reset(true);
    }
    Particle.prototype.reset = function (init) {
        this.x     = Math.random() * canvas.width;
        this.y     = init ? Math.random() * canvas.height : canvas.height + 10;
        this.r     = Math.random() * 3 + 1;          /* radius 1–4 px   */
        this.speed = Math.random() * 0.4 + 0.15;     /* px per frame    */
        this.drift = (Math.random() - 0.5) * 0.3;    /* gentle sideways */
        this.col   = colours[Math.floor(Math.random() * colours.length)];
        this.alpha = Math.random() * 0.45 + 0.1;
        this.pulse = Math.random() * Math.PI * 2;     /* phase offset    */
    };
    Particle.prototype.update = function () {
        this.y     -= this.speed;
        this.x     += this.drift;
        this.pulse += 0.025;
        /* gently pulsing opacity */
        var a = this.alpha + Math.sin(this.pulse) * 0.12;
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.r, 0, Math.PI * 2);
        /* soft glow */
        var grd = ctx.createRadialGradient(this.x, this.y, 0, this.x, this.y, this.r * 3);
        grd.addColorStop(0,   this.col + Math.min(a, 0.7) + ')');
        grd.addColorStop(0.4, this.col + Math.min(a * 0.5, 0.35) + ')');
        grd.addColorStop(1,   this.col + '0)');
        ctx.fillStyle = grd;
        ctx.fill();
        if (this.y < -10) this.reset(false);
    };

    /* Spawn 80 particles */
    for (var i = 0; i < 80; i++) { particles.push(new Particle()); }

    function loop() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        for (var i = 0; i < particles.length; i++) { particles[i].update(); }
        RAF = requestAnimationFrame(loop);
    }

    /* Pause animation when tab hidden to save CPU */
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { cancelAnimationFrame(RAF); }
        else { loop(); }
    });

    loop();
})();
</script>

<script>
// Countdown Timer — race day message is translated via PHP data attribute
const eventTimestamp  = <?= json_encode(strtotime('2026-12-19 05:30:00') * 1000) ?>;
const raceDayMsg      = <?= json_encode(__('hero.raceday')) ?>;
function updateCountdown() {
    const now  = new Date().getTime();
    const diff = eventTimestamp - now;
    if (diff <= 0) {
        document.getElementById('countdown').innerHTML = '<p class="text-warning fs-4 fw-700">' + raceDayMsg + '</p>';
        return;
    }
    const d = Math.floor(diff / (1000 * 60 * 60 * 24));
    const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const s = Math.floor((diff % (1000 * 60)) / 1000);
    document.getElementById('days').textContent    = String(d).padStart(2, '0');
    document.getElementById('hours').textContent   = String(h).padStart(2, '0');
    document.getElementById('minutes').textContent = String(m).padStart(2, '0');
    document.getElementById('seconds').textContent = String(s).padStart(2, '0');
}
updateCountdown();
setInterval(updateCountdown, 1000);
</script>

<?php include PUBLIC_PATH . '/includes/footer.php'; ?>
