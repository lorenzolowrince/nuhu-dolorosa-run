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
$mapUrl           = 'https://www.google.com/maps/search/St+Anne+Church+Penampang+Baru+Kimanis+Papar+Sabah';
$img              = APP_URL . '/assets/images';

include PUBLIC_PATH . '/includes/header.php';
?>

<!-- ============================================================ -->
<!-- HERO SECTION -->
<!-- ============================================================ -->
<section class="hero" id="hero" aria-labelledby="hero-title">
    <div class="hero-bg" aria-hidden="true"></div>
    <canvas id="hero-canvas" aria-hidden="true"></canvas>

    <div class="container hero-inner">
        <div class="row align-items-center gy-5">
            <div class="col-lg-7">
                <div class="hero-badge">
                    <i class="fas fa-heart" aria-hidden="true"></i> <?= __('hero.badge') ?>
                </div>
                <h1 class="hero-title" id="hero-title">
                    Run<br>
                    <span class="hero-title-accent">For Hope</span>
                </h1>
                <p class="hero-event-name">
                    Nuhu Dolorosa Ultra Trail 3.0 Charity Run
                    <span class="hero-distances">7KM · 15KM · 43KM</span>
                </p>
                <ul class="hero-meta">
                    <li class="meta-chip"><i class="fas fa-calendar-alt" aria-hidden="true"></i> <?= __('hero.event_date') ?></li>
                    <li class="meta-chip"><i class="fas fa-clock" aria-hidden="true"></i> <?= __('hero.flagoff') ?></li>
                    <li>
                        <a href="<?= $mapUrl ?>" target="_blank" rel="noopener" class="meta-chip meta-chip-link">
                            <i class="fas fa-map-marker-alt" aria-hidden="true"></i> <?= e($eventVenue) ?>
                        </a>
                    </li>
                </ul>
                <div class="hero-actions">
                    <?php if ($registrationOpen): ?>
                        <a href="<?= APP_URL ?>/register.php" class="btn btn-hero-primary">
                            <?= __('hero.register') ?> <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i>
                        </a>
                    <?php else: ?>
                        <button class="btn btn-hero-secondary" disabled>
                            <i class="fas fa-lock me-2" aria-hidden="true"></i> <?= __('hero.reg_closed') ?>
                        </button>
                    <?php endif; ?>
                    <a href="#categories" class="btn btn-hero-outline">
                        <?= __('hero.categories') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <button type="button" class="hero-photo-btn"
            onclick="openLightbox('<?= $img ?>/hero-bg.jpeg', this.dataset.caption)"
            data-caption="Groundbreaking Ceremony — St Anne Church, Penampang Baru">
        <i class="fas fa-search-plus" aria-hidden="true"></i> <?= __('ui.view_photo') ?>
    </button>

    <!-- Crocker-range ridgeline with the trail drawn across it -->
    <svg class="hero-ridge" viewBox="0 0 1440 140" preserveAspectRatio="none" aria-hidden="true">
        <path class="ridge-back" d="M0,92 L120,70 L210,84 L330,46 L430,74 L560,38 L660,66 L790,30 L900,62 L1010,44 L1120,72 L1240,40 L1340,64 L1440,52 L1440,140 L0,140 Z"/>
        <path class="ridge-front" d="M0,118 L140,96 L260,110 L380,84 L520,104 L640,80 L770,100 L900,76 L1040,98 L1180,82 L1300,102 L1440,90 L1440,140 L0,140 Z"/>
        <path class="ridge-trail" d="M0,112 C180,96 260,110 380,90 S620,82 770,96 S1040,76 1180,86 S1360,96 1440,88"/>
    </svg>
</section>

<!-- ============================================================ -->
<!-- STATS -->
<!-- ============================================================ -->
<section class="stats-band" aria-label="Event at a glance">
    <div class="container">
        <!-- Countdown, styled as a race bib — sits below the photo so it never covers it -->
        <div class="race-bib">
            <div class="race-bib-info">
                <img src="<?= $img ?>/web/nddk-logo-160.png" alt="" width="48" height="48">
                <div>
                    <div class="race-bib-event">Ultra Trail 3.0</div>
                    <div class="race-bib-meta">19 · 12 · 2026 · Kimanis, Papar, Sabah</div>
                </div>
            </div>
            <div class="countdown-box" id="countdown" role="timer" aria-live="off">
                <div class="countdown-unit">
                    <span class="count-num" id="days">00</span>
                    <span class="count-label"><?= __('countdown.days') ?></span>
                </div>
                <div class="countdown-unit">
                    <span class="count-num" id="hours">00</span>
                    <span class="count-label"><?= __('countdown.hours') ?></span>
                </div>
                <div class="countdown-unit">
                    <span class="count-num" id="minutes">00</span>
                    <span class="count-label"><?= __('countdown.minutes') ?></span>
                </div>
                <div class="countdown-unit">
                    <span class="count-num" id="seconds">00</span>
                    <span class="count-label"><?= __('countdown.seconds') ?></span>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <?php
            $stats = [
                ['icon' => 'fas fa-flag-checkered',     'value' => __('stats.edition_val'), 'label' => __('stats.edition')],
                ['icon' => 'fas fa-route',              'value' => '3',      'label' => __('stats.categories')],
                ['icon' => 'fas fa-trophy',             'value' => 'RM 85+', 'label' => __('stats.starting')],
                ['icon' => 'fas fa-hand-holding-heart', 'value' => '100%',   'label' => __('stats.charity')],
            ];
            foreach ($stats as $s): ?>
                <div class="stat-tile reveal">
                    <i class="<?= $s['icon'] ?>" aria-hidden="true"></i>
                    <div>
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
<section class="section about-section" id="about">
    <div class="container">
        <div class="row gy-5 gx-lg-5">
            <div class="col-lg-5">
                <div class="about-card reveal">
                    <img src="<?= $img ?>/web/nddk-logo-320.png" alt="NDDK Logo" width="132" height="132" class="about-logo">
                    <h3 class="about-card-title">Run For Hope</h3>
                    <p class="about-card-meta"><?= __('hero.event_date') ?> · Kimanis, Papar, Sabah</p>
                    <div class="about-distances">
                        <div><span>7KM</span><small><?= __('about.fun_run') ?></small></div>
                        <div><span>15KM</span><small><?= __('about.trail') ?></small></div>
                        <div><span>43KM</span><small><?= __('about.ultra') ?></small></div>
                    </div>
                </div>

                <!-- VIP / Distinguished Guests -->
                <div class="note-card note-violet reveal">
                    <div class="note-icon"><i class="fas fa-star" aria-hidden="true"></i></div>
                    <div>
                        <h3 class="note-title"><?= __('about.vip.title') ?></h3>
                        <p class="mb-0"><?= __('about.vip.p') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="section-label"><?= __('about.label') ?></div>
                <h2 class="section-title"><?= __('about.title') ?></h2>
                <div class="prose">
                    <p class="lead-p"><?= __('about.p1') ?></p>
                    <p><?= __('about.p2') ?></p>
                    <p><?= __('about.p3') ?></p>
                </div>

                <!-- Donation bank account box -->
                <div class="note-card note-gold reveal">
                    <div class="note-icon"><i class="fas fa-university" aria-hidden="true"></i></div>
                    <div class="flex-grow-1">
                        <h3 class="note-title"><?= __('about.bank.title') ?></h3>
                        <dl class="bank-dl">
                            <dt>Bank</dt><dd><?= __('about.bank.name') ?></dd>
                            <dt>Account Name</dt><dd><?= __('about.bank.acc_name') ?></dd>
                            <dt>Account Number</dt>
                            <dd>
                                <span class="acc-num"><?= __('about.bank.acc_num') ?></span>
                                <button type="button" class="btn-copy" data-copy="<?= e(strip_tags(__('about.bank.acc_num'))) ?>">
                                    <i class="far fa-copy me-1" aria-hidden="true"></i><?= __('ui.copy') ?>
                                </button>
                            </dd>
                        </dl>
                    </div>
                </div>

                <p class="prose"><?= __('about.p4') ?></p>

                <ul class="feature-grid">
                    <?php
                    $features = [
                        'about.feat.medal', 'about.feat.shirt',
                        'about.feat.medical', 'about.feat.refresh',
                        'about.feat.goody', 'about.feat.prizes',
                    ];
                    foreach ($features as $feat): ?>
                        <li class="feature-item"><i class="fas fa-check" aria-hidden="true"></i><span><?= __($feat) ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($registrationOpen): ?>
                    <a href="<?= APP_URL ?>/register.php" class="btn btn-primary-custom">
                        <?= __('about.register') ?> <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- CATEGORIES SECTION -->
<!-- ============================================================ -->
<section class="section categories-section" id="categories">
    <div class="container">
        <div class="section-head text-center">
            <div class="section-label section-label-dark"><?= __('cat.label') ?></div>
            <h2 class="section-title text-white"><?= __('cat.title') ?></h2>
            <p class="section-sub"><?= __('cat.subtitle') ?></p>
        </div>
        <div class="row g-4 justify-content-center align-items-stretch">
            <?php
            $icons    = ['fas fa-walking', 'fas fa-running', 'fas fa-bolt'];
            $featured = [false, true, false];
            foreach ($categories as $i => $cat):
                $isFeatured = $featured[$i] ?? false;
            ?>
                <div class="col-lg-4 col-md-6">
                    <article class="category-card reveal <?= $isFeatured ? 'featured' : '' ?>">
                        <?php if ($isFeatured): ?>
                            <div class="category-badge-top"><?= __('cat.popular') ?></div>
                        <?php endif; ?>
                        <div class="category-head">
                            <div class="category-km"><?= e($cat['distance']) ?></div>
                            <div class="category-icon"><i class="<?= $icons[$i] ?? 'fas fa-running' ?>" aria-hidden="true"></i></div>
                        </div>
                        <h3 class="category-name"><?= e($cat['name']) ?></h3>
                        <p class="category-desc"><?= e($cat['description']) ?></p>
                        <div class="category-price">
                            <span class="price-currency">RM</span>
                            <span class="price-amount"><?= number_format((float)$cat['fee'], 0) ?></span>
                        </div>
                        <ul class="category-perks">
                            <li><i class="fas fa-check" aria-hidden="true"></i> <?= __('cat.perk.medal') ?></li>
                            <li><i class="fas fa-check" aria-hidden="true"></i> <?= __('cat.perk.pack') ?></li>
                            <li><i class="fas fa-check" aria-hidden="true"></i> <?= __('cat.perk.cert') ?></li>
                            <?php if ($cat['distance'] !== '7KM'): ?>
                                <li><i class="fas fa-check" aria-hidden="true"></i> <?= __('cat.perk.trail') ?></li>
                            <?php endif; ?>
                            <?php if ($cat['distance'] === '43KM'): ?>
                                <li><i class="fas fa-check" aria-hidden="true"></i> <?= __('cat.perk.ultra') ?></li>
                            <?php endif; ?>
                            <li><i class="fas fa-check" aria-hidden="true"></i> <?= __('cat.perk.shirt') ?></li>
                            <?php if ($cat['distance'] === '43KM'): ?>
                                <li><i class="fas fa-check" aria-hidden="true"></i> <?= __('cat.perk.finisher_shirt') ?></li>
                                <li><i class="fas fa-check" aria-hidden="true"></i> <?= __('cat.perk.prizes_20') ?></li>
                            <?php elseif ($cat['distance'] === '15KM'): ?>
                                <li><i class="fas fa-check" aria-hidden="true"></i> <?= __('cat.perk.prizes_10') ?></li>
                            <?php elseif ($cat['distance'] === '7KM'): ?>
                                <li><i class="fas fa-check" aria-hidden="true"></i> <?= __('cat.perk.prizes_5') ?></li>
                            <?php endif; ?>
                        </ul>
                        <div class="category-foot">
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
                                <div class="slot-bar">
                                    <div class="d-flex justify-content-between">
                                        <small><?= __('cat.slots', $remaining) ?></small>
                                        <small><?= __('cat.filled', $pct) ?></small>
                                    </div>
                                    <div class="slot-track" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= e($cat['distance']) ?>">
                                        <div class="slot-fill" style="width:<?= $pct ?>%"></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Shirt add-on note -->
        <p class="shirt-note">
            <i class="fas fa-tshirt" aria-hidden="true"></i>
            <span><?= __('cat.shirt_note', e(getSetting('shirt_price', '35'))) ?></span>
        </p>
    </div>
</section>

<!-- ============================================================ -->
<!-- FINISHER REWARDS (medal & finisher T-shirt) -->
<!-- ============================================================ -->
<section class="section rewards-section" id="rewards">
    <div class="container">
        <div class="section-head text-center">
            <div class="section-label"><?= __('fin.label') ?></div>
            <h2 class="section-title"><?= __('fin.title') ?></h2>
            <p class="section-sub section-sub-muted"><?= __('fin.sub') ?></p>
        </div>

        <div class="row g-4 align-items-stretch">
            <!-- Medal -->
            <div class="col-lg-6">
                <article class="panel reward-card reveal">
                    <div class="reward-head">
                        <div>
                            <h3 class="panel-title"><?= __('fin.medal_title') ?></h3>
                            <p class="panel-kicker mb-0"><?= __('fin.medal_kicker') ?></p>
                        </div>
                        <span class="reward-icon"><i class="fas fa-medal" aria-hidden="true"></i></span>
                    </div>
                    <div class="reward-photos" data-lightbox-group="medal">
                        <button type="button" class="reward-photo" data-full="<?= $img ?>/medal-finisher.jpg" aria-label="<?= e(strip_tags(__('fin.medal_a'))) ?>">
                            <img src="<?= $img ?>/web/medal-finisher-700.jpg" alt="<?= e(strip_tags(__('fin.medal_a'))) ?>" width="700" height="792" loading="lazy" decoding="async">
                            <span class="reward-tag"><?= __('fin.front') ?></span>
                        </button>
                        <button type="button" class="reward-photo" data-full="<?= $img ?>/medal-ultra-trail.jpg" aria-label="<?= e(strip_tags(__('fin.medal_b'))) ?>">
                            <img src="<?= $img ?>/web/medal-ultra-trail-700.jpg" alt="<?= e(strip_tags(__('fin.medal_b'))) ?>" width="700" height="815" loading="lazy" decoding="async">
                            <span class="reward-tag"><?= __('fin.back') ?></span>
                        </button>
                    </div>
                    <dl class="spec-list">
                        <div><dt><?= __('fin.spec_diameter') ?></dt><dd>80 mm</dd></div>
                        <div><dt><?= __('fin.spec_hanger') ?></dt><dd>35 × 8 mm</dd></div>
                        <div><dt><?= __('fin.spec_slot') ?></dt><dd>30 mm</dd></div>
                    </dl>
                    <button type="button" class="btn-copy reward-size-btn"
                            onclick="openLightbox('<?= $img ?>/medal-size.jpg', this.dataset.caption)"
                            data-caption="Medal size — 80 mm diameter, 35 × 8 mm hanger, 30 mm ribbon slot">
                        <i class="fas fa-ruler-combined me-1" aria-hidden="true"></i><?= __('fin.size_btn') ?>
                    </button>
                </article>
            </div>

            <!-- Finisher T-shirt -->
            <div class="col-lg-6">
                <article class="panel reward-card reward-card-dark reveal">
                    <div class="reward-head">
                        <div>
                            <h3 class="panel-title"><?= __('fin.shirt_title') ?></h3>
                            <p class="panel-kicker mb-0"><?= __('fin.shirt_kicker') ?></p>
                        </div>
                        <span class="reward-icon"><i class="fas fa-tshirt" aria-hidden="true"></i></span>
                    </div>
                    <div class="reward-photos" data-lightbox-group="finisher-shirt">
                        <button type="button" class="reward-photo" data-full="<?= $img ?>/finisher-shirt-front.jpg" aria-label="<?= e(__('fin.shirt_front')) ?>">
                            <img src="<?= $img ?>/web/finisher-shirt-front-700.jpg" alt="<?= e(__('fin.shirt_front')) ?>" width="700" height="882" loading="lazy" decoding="async">
                            <span class="reward-tag"><?= __('fin.front') ?></span>
                        </button>
                        <button type="button" class="reward-photo" data-full="<?= $img ?>/finisher-shirt-back.jpg" aria-label="<?= e(__('fin.shirt_back')) ?>">
                            <img src="<?= $img ?>/web/finisher-shirt-back-700.jpg" alt="<?= e(__('fin.shirt_back')) ?>" width="700" height="749" loading="lazy" decoding="async">
                            <span class="reward-tag"><?= __('fin.back') ?></span>
                        </button>
                    </div>
                    <ul class="reward-points">
                        <li><i class="fas fa-gift" aria-hidden="true"></i><span><?= __('fin.shirt_incl') ?></span></li>
                        <li><i class="fas fa-shopping-bag" aria-hidden="true"></i><span><?= __('fin.shirt_buy') ?> — <strong>RM <?= number_format((float)getSetting('shirt_price', '40'), 0) ?></strong></span></li>
                    </ul>
                    <?php if ($registrationOpen): ?>
                    <a href="<?= APP_URL ?>/register.php" class="btn btn-hero-primary align-self-start">
                        <?= __('about.register') ?> <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i>
                    </a>
                    <?php endif; ?>
                </article>
            </div>
        </div>
        <p class="zoom-hint"><i class="fas fa-search-plus me-1" aria-hidden="true"></i><?= __('ui.enlarge') ?></p>
    </div>
</section>

<!-- ============================================================ -->
<!-- SHIRT SHOWCASE -->
<!-- ============================================================ -->
<?php
$shirt1 = PUBLIC_PATH . '/assets/images/shirt-sample-1.jpg';
$shirt2 = PUBLIC_PATH . '/assets/images/shirt-sample-2.jpg';
$shirt1Url = $img . '/shirt-sample-1.jpg';
$shirt2Url = $img . '/shirt-sample-2.jpg';
$imagesReady = file_exists($shirt1) && file_exists($shirt2);
$shirtPrice  = number_format((float)getSetting('shirt_price', '40'), 0);
?>
<section class="section shirt-section" id="shirt">
    <div class="container">
        <div class="section-head text-center">
            <div class="section-label"><?= __('shirt.label') ?></div>
            <h2 class="section-title"><?= __('shirt.title') ?></h2>
            <p class="section-sub section-sub-muted"><?= __('shirt.subtitle') ?></p>
        </div>

        <div class="row g-4 align-items-stretch">
            <div class="col-lg-7">
                <?php if ($imagesReady): ?>
                <div class="shirt-photos" data-lightbox-group="shirts">
                    <button type="button" class="shirt-photo shirt-photo-wide" data-full="<?= $shirt1Url ?>" aria-label="T-Shirt Sample 1">
                        <img src="<?= $img ?>/web/shirt-sample-1-1000.jpg" alt="T-Shirt Sample 1" loading="lazy" decoding="async">
                    </button>
                    <button type="button" class="shirt-photo" data-full="<?= $shirt2Url ?>" aria-label="T-Shirt Sample 2">
                        <img src="<?= $img ?>/web/shirt-sample-2-800.jpg" alt="T-Shirt Sample 2" loading="lazy" decoding="async">
                    </button>
                </div>
                <p class="zoom-hint"><i class="fas fa-search-plus me-1" aria-hidden="true"></i> <?= __('shirt.zoom') ?></p>
                <?php else: ?>
                <div class="shirt-placeholder">
                    <i class="fas fa-tshirt" aria-hidden="true"></i>
                    <p><?= __('shirt.coming_soon') ?></p>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-5">
                <div class="panel shirt-panel reveal">
                    <h3 class="panel-title">Nuhu Dolorosa Ultra Trail 3.0</h3>
                    <p class="panel-kicker"><?= __('shirt.running_shirts') ?></p>

                    <ul class="icon-list">
                        <?php
                        $feats = [
                            ['fas fa-palette',  __('shirt.feat.design')],
                            ['fas fa-tshirt',   __('shirt.feat.sizes')],
                            ['fas fa-wind',     __('shirt.feat.fabric')],
                            ['fas fa-star',     __('shirt.feat.print')],
                            ['fas fa-box-open', __('shirt.feat.collect')],
                        ];
                        foreach ($feats as [$ic, $tx]): ?>
                            <li><span class="icon-dot"><i class="<?= $ic ?>" aria-hidden="true"></i></span><span><?= $tx ?></span></li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="price-row price-row-green">
                        <div>
                            <div class="price-row-title"><i class="fas fa-gift me-1" aria-hidden="true"></i> <?= __('cat.perk.shirt') ?></div>
                            <div class="price-row-note"><?= __('shirt.incl.note') ?></div>
                        </div>
                        <span class="pill pill-green"><?= __('shirt.incl.badge') ?></span>
                    </div>
                    <div class="price-row price-row-violet">
                        <div>
                            <div class="price-row-title"><i class="fas fa-tshirt me-1" aria-hidden="true"></i> <?= __('shirt.extra.label') ?></div>
                            <div class="price-row-note"><?= __('shirt.extra.note') ?></div>
                        </div>
                        <div class="price-row-amount">RM <?= $shirtPrice ?></div>
                    </div>
                    <div class="price-row price-row-gold">
                        <div>
                            <div class="price-row-title"><i class="fas fa-medal me-1" aria-hidden="true"></i> <?= __('cat.perk.finisher_shirt') ?></div>
                            <div class="price-row-note"><?= __('shirt.finisher.note') ?></div>
                        </div>
                        <div class="price-row-amount">RM <?= $shirtPrice ?></div>
                    </div>

                    <p class="info-note"><i class="fas fa-info-circle me-2" aria-hidden="true"></i><?= __('shirt.note') ?></p>

                    <?php if ($registrationOpen): ?>
                    <a href="<?= APP_URL ?>/register.php" class="btn btn-primary-custom w-100 justify-content-center">
                        <?= __('about.register') ?> <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- SIZE CHART -->
<!-- ============================================================ -->
<section class="section size-section" id="size-chart">
    <div class="container">
        <div class="section-head text-center">
            <div class="section-label"><?= __('ui.size_label') ?></div>
            <h2 class="section-title"><?= __('ui.size_title') ?></h2>
            <p class="section-sub section-sub-muted"><?= __('ui.size_sub') ?></p>
        </div>
        <div class="text-center">
            <button type="button" class="size-chart-btn" onclick="openLightbox('<?= $img ?>/size-chart.jpg', this.querySelector('img').alt)">
                <img src="<?= $img ?>/web/size-chart-1400.jpg"
                     alt="T-Shirt Size Chart — Adult and Kids Short/Long Sleeve"
                     width="1400" height="1423" loading="lazy" decoding="async">
            </button>
            <p class="zoom-hint"><i class="fas fa-search-plus me-1" aria-hidden="true"></i><?= __('ui.enlarge') ?></p>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- GALLERY -->
<!-- ============================================================ -->
<?php
$gallerySlides = [
    ['event-poster.jpeg', 'event-poster-900.jpg', 'Nuhu Dolorosa Ultra Trail 3.0 — Official Event Poster'],
    ['gallery-1.jpeg',    'gallery-1-900.jpg',    'Nuhu Dolorosa Ultra Trail 3.0 — Runners in Action'],
    ['gallery-2.jpeg',    'gallery-2-900.jpg',    'Shalom Kopivosian — Trail Recce Team'],
    ['gallery-3.jpeg',    'gallery-3-900.jpg',    'Shalom Kopivosian — Forest Trail Recce'],
    ['gallery-4.jpeg',    'gallery-4-900.jpg',    'Larian Pengharapan — Poster Rasmi (BM)'],
    ['gallery-5.jpeg',    'gallery-5-900.jpg',    'Nuhu Dolorosa Ultra Trail 3.0 — Sunset Runners'],
    ['gallery-6.jpeg',    'gallery-6-900.jpg',    'Larian Amal Pengharapan — Poster Air Terjun'],
    ['gallery-7.jpeg',    'gallery-7-900.jpg',    'Nuhu Dolorosa Ultra Trail 3.0 — Trail Runners'],
    ['gallery-8.jpeg',    'gallery-8-900.jpg',    'Nuhu Dolorosa Ultra Trail 3.0 — Rocky Hill Climbers'],
];
?>
<section class="section gallery-section" id="gallery" aria-labelledby="gallery-title">
    <div class="container">
        <div class="section-head section-head-split">
            <div>
                <div class="section-label section-label-dark"><?= __('ui.gallery_label') ?></div>
                <h2 class="section-title text-white" id="gallery-title"><?= __('ui.gallery_title') ?></h2>
                <p class="section-sub"><?= __('ui.gallery_sub') ?></p>
            </div>
            <div class="gallery-controls">
                <button type="button" class="round-btn" data-gallery-step="-1" aria-label="<?= __('ui.prev') ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i></button>
                <button type="button" class="round-btn" data-gallery-step="1" aria-label="<?= __('ui.next') ?>"><i class="fas fa-arrow-right" aria-hidden="true"></i></button>
            </div>
        </div>
    </div>
    <ul class="gallery-track" id="galleryTrack" data-lightbox-group="gallery">
        <?php foreach ($gallerySlides as $i => [$full, $web, $alt]): ?>
            <li class="gallery-item">
                <button type="button" class="gallery-btn" data-full="<?= $img . '/' . $full ?>" aria-label="<?= e($alt) ?>">
                    <img src="<?= $img ?>/web/<?= $web ?>" alt="<?= e($alt) ?>" loading="<?= $i < 2 ? 'eager' : 'lazy' ?>" decoding="async">
                    <span class="gallery-cap"><?= e($alt) ?></span>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="container gallery-hint"><i class="fas fa-search-plus me-1" aria-hidden="true"></i><?= __('ui.enlarge') ?></p>
</section>

<!-- ============================================================ -->
<!-- HOW IT WORKS -->
<!-- ============================================================ -->
<section class="section how-section" id="how">
    <div class="container">
        <div class="section-head text-center">
            <div class="section-label"><?= __('how.label') ?></div>
            <h2 class="section-title"><?= __('how.title') ?></h2>
        </div>
        <ol class="steps">
            <?php
            $steps = [
                ['icon' => 'fas fa-user-plus',   'title' => __('how.s1.title'), 'desc' => __('how.s1.desc')],
                ['icon' => 'fas fa-route',       'title' => __('how.s2.title'), 'desc' => __('how.s2.desc')],
                ['icon' => 'fas fa-tshirt',      'title' => __('how.s3.title'), 'desc' => __('how.s3.desc')],
                ['icon' => 'fas fa-envelope',    'title' => __('how.s4.title'), 'desc' => __('how.s4.desc')],
            ];
            foreach ($steps as $n => $step):
            ?>
                <li class="step reveal">
                    <div class="step-marker"><span><?= $n + 1 ?></span></div>
                    <div class="step-icon"><i class="<?= $step['icon'] ?>" aria-hidden="true"></i></div>
                    <h3 class="step-title"><?= $step['title'] ?></h3>
                    <p class="step-desc"><?= $step['desc'] ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<!-- ============================================================ -->
<!-- PAYMENT / BANK TRANSFER SECTION -->
<!-- ============================================================ -->
<section class="section payment-section" id="payment">
    <div class="container">
        <div class="section-head text-center">
            <div class="section-label"><?= __('pay.label') ?></div>
            <h2 class="section-title"><?= __('pay.title') ?></h2>
            <p class="section-sub section-sub-muted"><?= __('pay.desc') ?></p>
        </div>
        <div class="row align-items-stretch justify-content-center g-4">

            <!-- Bank Transfer Card -->
            <div class="col-lg-5 col-md-7">
                <div class="bank-card reveal">
                    <div class="bank-card-head">
                        <i class="fas fa-university" aria-hidden="true"></i>
                        <span><?= __('about.bank.name') ?></span>
                    </div>
                    <div class="bank-card-row">
                        <span><?= __('pay.acc_name') ?></span>
                        <strong><?= __('about.bank.acc_name') ?></strong>
                    </div>
                    <div class="bank-card-row">
                        <span><?= __('pay.acc_num') ?></span>
                        <strong class="bank-card-num"><?= __('about.bank.acc_num') ?></strong>
                    </div>
                    <button type="button" class="btn-copy btn-copy-light" data-copy="<?= e(strip_tags(__('about.bank.acc_num'))) ?>">
                        <i class="far fa-copy me-1" aria-hidden="true"></i><?= __('ui.copy') ?> <?= __('pay.acc_num') ?>
                    </button>
                </div>
                <p class="info-note mt-3"><i class="fas fa-info-circle me-2" aria-hidden="true"></i><?= __('pay.note') ?></p>
            </div>

            <!-- DuitNow QR -->
            <div class="col-lg-3 col-md-5">
                <div class="qr-card reveal">
                    <div class="qr-title"><i class="fas fa-qrcode me-1" aria-hidden="true"></i> <?= __('ui.qr_title') ?></div>
                    <button type="button" class="qr-btn" onclick="openLightbox('<?= $img ?>/duitnow-qr.png', this.querySelector('img').alt)">
                        <img src="<?= $img ?>/web/duitnow-qr-480.png"
                             alt="DuitNow QR — RTDKK ST. ANNE PENAMPANG BARU"
                             width="200" height="200" loading="lazy">
                    </button>
                    <div class="qr-name">RTDKK ST. ANNE PENAMPANG BARU</div>
                    <div class="qr-sub"><?= __('ui.qr_sub') ?></div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- CONTACT SECTION -->
<!-- ============================================================ -->
<section class="section contact-section" id="contact">
    <div class="container">
        <div class="section-head text-center">
            <div class="section-label"><?= __('contact.label') ?></div>
            <h2 class="section-title"><?= __('contact.title') ?></h2>
        </div>
        <div class="row g-4 justify-content-center">
            <?php
            $contacts = [
                ['fas fa-phone-alt',      __('contact.call'),    e(getSetting('contact_name', 'Jessel Kamijan')),
                 'tel:' . preg_replace('/[^+0-9]/', '', getSetting('contact_phone')), e(getSetting('contact_phone')), false],
                ['fas fa-phone-alt',      __('contact.call2'),   e(getSetting('contact_name2', 'Prisca Kinsik')),
                 'tel:' . preg_replace('/[^+0-9]/', '', getSetting('contact_phone2', '0172574954')), e(getSetting('contact_phone2', '017-2574954')), false],
                ['fas fa-envelope',       __('contact.email_h'), __('contact.email_sub'),
                 'mailto:' . e(getSetting('contact_email')), e(getSetting('contact_email')), false],
                ['fas fa-map-marker-alt', __('contact.venue_h'), e(getSetting('event_venue')),
                 $mapUrl, __('contact.map'), true],
            ];
            foreach ($contacts as [$icon, $title, $sub, $href, $linkText, $external]): ?>
                <div class="col-lg-3 col-sm-6">
                    <a class="contact-card reveal" href="<?= $href ?>" <?= $external ? 'target="_blank" rel="noopener"' : '' ?>>
                        <span class="contact-icon"><i class="<?= $icon ?>" aria-hidden="true"></i></span>
                        <span class="contact-title"><?= $title ?></span>
                        <span class="contact-sub"><?= $sub ?></span>
                        <span class="contact-link"><?= $linkText ?> <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA Banner -->
<?php if ($registrationOpen): ?>
<section class="cta-section">
    <div class="container">
        <div class="cta-panel">
            <div>
                <h2 class="cta-title"><?= __('cta.title') ?></h2>
                <p class="cta-sub"><?= __('cta.sub') ?></p>
            </div>
            <a href="<?= APP_URL ?>/register.php" class="btn btn-cta">
                <i class="fas fa-running me-2" aria-hidden="true"></i> <?= __('cta.btn') ?>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<script>
/* ── Drifting light particles over the hero photo (skipped for reduced motion) ── */
(function () {
    var canvas  = document.getElementById('hero-canvas');
    var section = document.getElementById('hero');
    if (!canvas || !section || !canvas.getContext) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var ctx = canvas.getContext('2d');
    var particles = [];
    var RAF = null;
    var visible = true;
    var dpr = Math.min(window.devicePixelRatio || 1, 2);

    function resize() {
        canvas.width  = section.offsetWidth * dpr;
        canvas.height = section.offsetHeight * dpr;
        canvas.style.width  = section.offsetWidth + 'px';
        canvas.style.height = section.offsetHeight + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }
    window.addEventListener('resize', resize);
    resize();

    /* Gold, white and soft violet — the colours of a dawn start */
    var colours = ['rgba(245,176,20,', 'rgba(255,255,255,', 'rgba(196,148,255,'];
    var W = function () { return section.offsetWidth; };
    var H = function () { return section.offsetHeight; };

    function Particle() { this.reset(true); }
    Particle.prototype.reset = function (init) {
        this.x     = Math.random() * W();
        this.y     = init ? Math.random() * H() : H() + 10;
        this.r     = Math.random() * 2.4 + 0.8;
        this.speed = Math.random() * 0.35 + 0.12;
        this.drift = (Math.random() - 0.5) * 0.25;
        this.col   = colours[Math.floor(Math.random() * colours.length)];
        this.alpha = Math.random() * 0.4 + 0.1;
        this.pulse = Math.random() * Math.PI * 2;
    };
    Particle.prototype.update = function () {
        this.y     -= this.speed;
        this.x     += this.drift;
        this.pulse += 0.02;
        var a = Math.max(0, this.alpha + Math.sin(this.pulse) * 0.1);
        var grd = ctx.createRadialGradient(this.x, this.y, 0, this.x, this.y, this.r * 3);
        grd.addColorStop(0,   this.col + Math.min(a, 0.7) + ')');
        grd.addColorStop(1,   this.col + '0)');
        ctx.fillStyle = grd;
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.r * 3, 0, Math.PI * 2);
        ctx.fill();
        if (this.y < -10) this.reset(false);
    };

    var count = window.innerWidth < 768 ? 35 : 60;
    for (var i = 0; i < count; i++) { particles.push(new Particle()); }

    function loop() {
        ctx.clearRect(0, 0, W(), H());
        for (var i = 0; i < particles.length; i++) { particles[i].update(); }
        RAF = requestAnimationFrame(loop);
    }
    function start() { if (!RAF && visible && !document.hidden) loop(); }
    function stop()  { if (RAF) { cancelAnimationFrame(RAF); RAF = null; } }

    /* Pause when the tab is hidden or the hero is scrolled away, to save battery */
    document.addEventListener('visibilitychange', function () { document.hidden ? stop() : start(); });
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(function (entries) {
            visible = entries[0].isIntersecting;
            visible ? start() : stop();
        }).observe(section);
    }
    start();
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
        document.getElementById('countdown').innerHTML = '<p class="raceday-msg">' + raceDayMsg + '</p>';
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
