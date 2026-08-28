<!DOCTYPE html>
<html lang="<?= currentLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($pageDescription ?? 'Register for the Charity Run Nuhu Dolorosa – Run For Hope, 19 December 2026') ?>">
    <title><?= e($pageTitle ?? 'Nuhu Dolorosa Charity Run') ?> | Run For Hope</title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top" id="mainNav">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= APP_URL ?>/">
            <img src="<?= APP_URL ?>/assets/images/nddk-logo.png" alt="NDDK Logo" class="brand-logo-img">
            <div>
                <div class="brand-name">Nuhu Dolorosa</div>
                <div class="brand-sub">Charity Run 2026</div>
            </div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto gap-1 align-items-lg-center">
                <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/#about"><?= __('nav.about') ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/#categories"><?= __('nav.categories') ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/#contact"><?= __('nav.contact') ?></a></li>
                <!-- Social Icons -->
                <li class="nav-item d-flex align-items-center gap-1 ms-1">
                    <a href="https://www.facebook.com/profile.php?id=61562314834879" target="_blank" rel="noopener" class="nav-link py-0 px-1" title="Facebook" style="font-size:1rem;opacity:.85;">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://www.instagram.com/nuhudolorosa/" target="_blank" rel="noopener" class="nav-link py-0 px-1" title="Instagram" style="font-size:1rem;opacity:.85;">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="https://wa.me/60198834954" target="_blank" rel="noopener" class="nav-link py-0 px-1" title="WhatsApp" style="font-size:1rem;opacity:.85;">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                </li>
                <li class="nav-item ms-1">
                    <!-- Language Toggle -->
                    <?php $switchLang = currentLang() === 'en' ? 'ms' : 'en'; ?>
                    <a href="<?= langUrl($switchLang) ?>" class="btn-lang-toggle" title="<?= $switchLang === 'ms' ? 'Tukar ke Bahasa Malaysia' : 'Switch to English' ?>">
                        <?php if (currentLang() === 'en'): ?>
                            <span class="lang-flag">🇲🇾</span> BM
                        <?php else: ?>
                            <span class="lang-flag">🇬🇧</span> EN
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item ms-1">
                    <a href="<?= APP_URL ?>/admin/login.php" class="nav-link py-0 px-2" style="font-size:.8rem;opacity:.7;" title="Admin">
                        <i class="fas fa-lock me-1"></i>Admin
                    </a>
                </li>
                <li class="nav-item ms-1">
                    <a class="btn btn-register" href="<?= APP_URL ?>/register.php">
                        <i class="fas fa-pencil-alt me-1"></i> <?= __('nav.register') ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
