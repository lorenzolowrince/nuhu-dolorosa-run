<!DOCTYPE html>
<html lang="<?= currentLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($pageDescription ?? 'Register for the Charity Run Nuhu Dolorosa – Run For Hope, 19 December 2026') ?>">
    <meta name="theme-color" content="#160d2b">
    <title><?= e($pageTitle ?? 'Nuhu Dolorosa Charity Run') ?> | Run For Hope</title>
    <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/images/web/nddk-logo-160.png">

    <!-- Open Graph (link previews on WhatsApp / Facebook) -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($pageTitle ?? 'Nuhu Dolorosa Charity Run') ?> | Run For Hope">
    <meta property="og:description" content="<?= e($pageDescription ?? 'Register for the Charity Run Nuhu Dolorosa – Run For Hope, 19 December 2026') ?>">
    <meta property="og:image" content="<?= APP_URL ?>/assets/images/event-poster.jpeg">

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS (style.css is shared with admin; site.css is the public design layer) -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/site.css?v=3">
    <script>
    /* Hide scroll-reveal elements before first paint (skipped for reduced motion).
       If main.js never runs, the timeout shows everything anyway. */
    (function (d) {
        if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        d.classList.add('reveal-ready');
        window.__revealFallback = setTimeout(function () { d.classList.remove('reveal-ready'); }, 4000);
    })(document.documentElement);
    </script>
</head>
<body class="site">
<a class="skip-link" href="#main"><?= __('ui.skip') ?></a>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top" id="mainNav" aria-label="Main">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= APP_URL ?>/">
            <img src="<?= APP_URL ?>/assets/images/web/nddk-logo-160.png" alt="" width="42" height="42" class="brand-logo-img">
            <span class="brand-text">
                <span class="brand-name">Nuhu Dolorosa</span>
                <span class="brand-sub">Charity Run 2026</span>
            </span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu"
                aria-controls="navMenu" aria-expanded="false" aria-label="<?= __('ui.menu') ?>">
            <span class="nav-burger" aria-hidden="true"><span></span><span></span><span></span></span>
        </button>

        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/#about"><?= __('nav.about') ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/#categories"><?= __('nav.categories') ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/#how"><?= __('ui.how') ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/#contact"><?= __('nav.contact') ?></a></li>
                <li class="nav-item nav-social">
                    <a href="https://www.facebook.com/profile.php?id=61562314834879" target="_blank" rel="noopener" class="nav-icon" aria-label="Facebook">
                        <i class="fab fa-facebook-f" aria-hidden="true"></i>
                    </a>
                    <a href="https://www.instagram.com/nuhudolorosa/" target="_blank" rel="noopener" class="nav-icon" aria-label="Instagram">
                        <i class="fab fa-instagram" aria-hidden="true"></i>
                    </a>
                    <a href="https://wa.me/60198834954" target="_blank" rel="noopener" class="nav-icon" aria-label="WhatsApp">
                        <i class="fab fa-whatsapp" aria-hidden="true"></i>
                    </a>
                </li>
                <li class="nav-item nav-actions">
                    <!-- Language Toggle -->
                    <?php $switchLang = currentLang() === 'en' ? 'ms' : 'en'; ?>
                    <a href="<?= langUrl($switchLang) ?>" class="btn-lang-toggle" lang="<?= $switchLang ?>"
                       title="<?= $switchLang === 'ms' ? 'Tukar ke Bahasa Malaysia' : 'Switch to English' ?>">
                        <?php if (currentLang() === 'en'): ?>
                            <span class="lang-flag" aria-hidden="true">🇲🇾</span> BM
                        <?php else: ?>
                            <span class="lang-flag" aria-hidden="true">🇬🇧</span> EN
                        <?php endif; ?>
                    </a>
                    <a class="btn btn-register" href="<?= APP_URL ?>/register.php">
                        <?= __('nav.register') ?> <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<main id="main">
