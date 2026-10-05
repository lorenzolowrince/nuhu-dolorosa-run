<!-- Footer -->
<footer class="site-footer">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <img src="<?= APP_URL ?>/assets/images/nddk-logo.png" alt="NDDK Logo" class="brand-logo-img">
                    <div>
                        <div class="text-white fw-700 fs-6">Nuhu Dolorosa</div>
                        <div class="text-warning fw-600">Run For Hope</div>
                    </div>
                </div>
                <p class="text-light opacity-75">
                    <?= __('footer.tagline') ?>
                </p>
            </div>
            <div class="col-lg-2 col-md-4">
                <h6 class="text-warning fw-700 mb-3 text-uppercase"><?= __('footer.links') ?></h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="<?= APP_URL ?>/#about"><?= __('footer.about') ?></a></li>
                    <li><a href="<?= APP_URL ?>/#categories"><?= __('footer.categories') ?></a></li>
                    <li><a href="<?= APP_URL ?>/#contact"><?= __('nav.contact') ?></a></li>
                    <li><a href="<?= APP_URL ?>/register.php"><?= __('footer.register') ?></a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-4">
                <h6 class="text-warning fw-700 mb-3 text-uppercase"><?= __('footer.event_info') ?></h6>
                <ul class="list-unstyled footer-links">
                    <li><i class="fas fa-calendar-alt me-2 text-warning"></i><?= __('hero.event_date') ?></li>
                    <li><i class="fas fa-clock me-2 text-warning"></i><?= __('footer.flagoff') ?></li>
                    <li>
                        <i class="fas fa-map-marker-alt me-2 text-warning"></i>
                        <a href="https://www.google.com/maps/search/St+Anne+Church+Penampang+Baru+Kimanis+Papar+Sabah" target="_blank" rel="noopener" style="color:rgba(255,255,255,.7)">
                            <?= e(getSetting('event_venue')) ?>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-4">
                <h6 class="text-warning fw-700 mb-3 text-uppercase"><?= __('footer.contact') ?></h6>
                <ul class="list-unstyled footer-links">
                    <li><i class="fas fa-envelope me-2 text-warning"></i><?= e(getSetting('contact_email')) ?></li>
                    <li><i class="fas fa-phone me-2 text-warning"></i><?= e(getSetting('contact_name', 'Jessel')) ?>: <?= e(getSetting('contact_phone')) ?></li>
                    <li><i class="fas fa-phone me-2 text-warning"></i><?= e(getSetting('contact_name2', 'Prisca')) ?>: <?= e(getSetting('contact_phone2', '017-2574954')) ?></li>
                </ul>
                <div class="mt-3 d-flex gap-2">
                    <a href="https://www.facebook.com/profile.php?id=61562314834879" target="_blank" class="social-btn"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://www.instagram.com/nuhudolorosa/" target="_blank" class="social-btn"><i class="fab fa-instagram"></i></a>
                    <a href="https://wa.me/60198834954" target="_blank" class="social-btn"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
        </div>
        <hr class="border-secondary mt-4">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="text-light opacity-50 mb-0 small">
                    &copy; <?= date('Y') ?> <?= e(getSetting('event_name')) ?>. <?= __('footer.rights') ?>
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <p class="text-light opacity-50 mb-0 small">
                    <?= __('footer.secured_by') ?> <i class="fas fa-shield-alt text-warning"></i> SSL
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- Floating back-to-top button -->
<button id="back-to-top" class="back-to-top" type="button"
        title="<?= __('footer.back_to_top') ?>"
        aria-label="<?= __('footer.back_to_top') ?>">
    <i class="fas fa-arrow-up"></i>
</button>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?= APP_URL ?>/assets/js/main.js"></script>

<!-- ============================================================ -->
<!-- BACKGROUND MUSIC -->
<!-- ============================================================ -->
<audio id="bg-music"
       src="<?= APP_URL ?>/assets/audio/theme.mp3"
       loop
       preload="auto"
       autoplay
       muted
       style="display:none;"></audio>

<!-- Floating music toggle button -->
<button id="music-fab" class="music-fab fab-off"
        onclick="toggleMusic()"
        title="Play music"
        aria-label="Toggle background music">
    <i id="music-fab-icon" class="music-fab-icon fas fa-volume-mute"></i>
</button>

<!-- Music autoplay blocked toast -->
<div id="music-toast" onclick="toggleMusic();dismissToast();">
    <i class="fas fa-music"></i> Tap anywhere to play music
</div>

<script>
(function () {
    var audio   = document.getElementById('bg-music');
    var fab     = document.getElementById('music-fab');
    var fabIcon = document.getElementById('music-fab-icon');
    var userOff = sessionStorage.getItem('music-off') === '1';
    var started       = false;
    var waitingUnmute = false;

    function setUI(playing) {
        fabIcon.className = 'music-fab-icon fas ' + (playing ? 'fa-music' : 'fa-volume-mute');
        fab.classList.toggle('fab-playing', playing);
        fab.classList.toggle('fab-off',    !playing);
        fab.classList.remove('fab-hint');
        fab.title = playing ? 'Pause music' : 'Play music';
    }

    function showHint(msg) {
        fab.classList.add('fab-hint');
        fab.classList.remove('fab-playing', 'fab-off');
        fab.title = msg || 'Tap to start music';
    }

    /* ── Step 1: try autoplay on page load ── */
    /* ── Toast helpers ── */
    var toastTimer = null;
    function showToast() {
        if (sessionStorage.getItem('music-toast-shown')) return;
        var t = document.getElementById('music-toast');
        if (!t) return;
        t.classList.add('music-toast-show');
        toastTimer = setTimeout(dismissToast, 5000);
        sessionStorage.setItem('music-toast-shown', '1');
    }
    window.dismissToast = function () {
        clearTimeout(toastTimer);
        var t = document.getElementById('music-toast');
        if (t) t.classList.remove('music-toast-show');
    };

    function tryAutoplay() {
        if (userOff) { setUI(false); return; }
        audio.volume = 0.6;

        /* If the HTML autoplay+muted already started it, just unmute */
        if (!audio.paused) {
            started       = true;
            waitingUnmute = true;
            showHint('Scroll or tap to unmute');
            return;
        }

        /* Try unmuted first (works on sites with high engagement score) */
        audio.muted = false;
        audio.play()
            .then(function () {
                started = true;
                setUI(true);
            })
            .catch(function () {
                /* Fall back to muted — scroll / tap will unmute */
                audio.muted = true;
                audio.play()
                    .then(function () {
                        started       = true;
                        waitingUnmute = true;
                        showHint('Scroll or tap to unmute');
                    })
                    .catch(function () {
                        /* Strict policy (e.g. iOS Safari) — need a real tap */
                        showHint('Tap to play music');
                        showToast();
                    });
            });
    }

    /* ── Scroll — unmutes already-running muted audio ── */
    function onScroll() {
        if (waitingUnmute && !userOff) {
            audio.muted   = false;
            waitingUnmute = false;
            setUI(true);
            removeAllListeners();
        }
    }

    /* ── Click / tap / key — can both START and unmute ── */
    function onGesture(e) {
        /* Ignore clicks on the FAB itself — toggleMusic() handles those */
        var fab = document.getElementById('music-fab');
        if (fab && fab.contains(e.target)) return;

        if (userOff) { removeAllListeners(); return; }

        if (!started) {
            audio.muted  = false;
            audio.volume = 0.6;
            audio.play()
                .then(function () {
                    started       = true;
                    waitingUnmute = false;
                    setUI(true);
                    dismissToast();
                })
                .catch(function () {});
        } else if (waitingUnmute) {
            audio.muted   = false;
            waitingUnmute = false;
            setUI(true);
            dismissToast();
        }
        removeAllListeners();
    }

    function removeAllListeners() {
        document.removeEventListener('click',      onGesture);
        document.removeEventListener('keydown',    onGesture);
        document.removeEventListener('touchstart', onGesture);
        document.removeEventListener('touchend',   onGesture);
        document.removeEventListener('scroll',     onScroll);
    }

    document.addEventListener('scroll',     onScroll,  { passive: true });
    document.addEventListener('click',      onGesture);
    document.addEventListener('keydown',    onGesture);
    document.addEventListener('touchstart', onGesture, { passive: true });
    document.addEventListener('touchend',   onGesture, { passive: true });

    /* ── Toggle button ── */
    window.toggleMusic = function () {
        waitingUnmute = false;
        removeAllListeners();
        if (audio.paused || audio.muted) {
            audio.muted = false;
            if (audio.paused) { audio.play().catch(function () {}); }
            userOff = false;
            sessionStorage.removeItem('music-off');
            setUI(true);
        } else {
            audio.muted = true;
            userOff = true;
            sessionStorage.setItem('music-off', '1');
            setUI(false);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryAutoplay);
    } else {
        tryAutoplay();
    }
})();
</script>

</body>
</html>
