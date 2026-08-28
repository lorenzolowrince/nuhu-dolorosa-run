/* ============================================================
   Nuhu Dolorosa Charity Run – Main JavaScript
   ============================================================ */

(function () {
    'use strict';

    // --- Navbar scroll effect ---
    const mainNav = document.getElementById('mainNav');
    if (mainNav) {
        window.addEventListener('scroll', () => {
            mainNav.classList.toggle('scrolled', window.scrollY > 50);
        });
    }

    // --- Smooth scroll for anchor links ---
    document.querySelectorAll('a[href^="#"]').forEach(link => {
        link.addEventListener('click', e => {
            const target = document.querySelector(link.getAttribute('href'));
            if (target) {
                e.preventDefault();
                const offset = 80;
                window.scrollTo({
                    top: target.getBoundingClientRect().top + window.scrollY - offset,
                    behavior: 'smooth'
                });
            }
        });
    });

    // --- Animate elements on scroll (Intersection Observer) ---
    const animateEls = document.querySelectorAll(
        '.stat-card, .category-card, .step-card, .contact-card, .form-card, .stat-card-admin'
    );
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        animateEls.forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity .5s ease, transform .5s ease';
            observer.observe(el);
        });
    }

    // --- Flash message auto-dismiss ---
    const flashes = document.querySelectorAll('.alert.fade');
    flashes.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 6000);
    });

    // --- Form submit protection (prevent double submit) ---
    const forms = document.querySelectorAll('form:not([data-no-protect])');
    forms.forEach(form => {
        form.addEventListener('submit', function () {
            const submitBtns = form.querySelectorAll('[type="submit"]:not([data-no-protect])');
            submitBtns.forEach(btn => {
                if (!btn.dataset.processing) {
                    btn.dataset.processing = '1';
                    const orig = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing…';
                    // Safety re-enable after 15s in case of error
                    setTimeout(() => {
                        btn.disabled = false;
                        btn.innerHTML = orig;
                        delete btn.dataset.processing;
                    }, 15000);
                }
            });
        });
    });

    // --- Admin sidebar mobile toggle ---
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.admin-sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
        // Close on backdrop click
        document.addEventListener('click', e => {
            if (sidebar.classList.contains('open') &&
                !sidebar.contains(e.target) &&
                e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        });
    }

    // --- Data table search filter (admin tables) ---
    const tableSearch = document.getElementById('tableSearch');
    if (tableSearch) {
        tableSearch.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('.admin-table tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // --- Tooltip init (Bootstrap) ---
    const tooltipEls = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipEls.forEach(el => new bootstrap.Tooltip(el));

    // --- Copy to clipboard ---
    document.querySelectorAll('[data-copy]').forEach(btn => {
        btn.addEventListener('click', function () {
            const text = this.getAttribute('data-copy');
            navigator.clipboard.writeText(text).then(() => {
                const orig = this.innerHTML;
                this.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
                setTimeout(() => { this.innerHTML = orig; }, 2000);
            });
        });
    });

    // --- Number counter animation (stats) ---
    function animateCounter(el) {
        const target = parseInt(el.getAttribute('data-count'), 10);
        if (isNaN(target)) return;
        let start = 0;
        const dur = 1200;
        const step = Math.max(1, Math.floor(target / (dur / 16)));
        const timer = setInterval(() => {
            start += step;
            if (start >= target) {
                el.textContent = target.toLocaleString();
                clearInterval(timer);
            } else {
                el.textContent = start.toLocaleString();
            }
        }, 16);
    }

    const counterEls = document.querySelectorAll('[data-count]');
    if (counterEls.length) {
        const counterObs = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    counterObs.unobserve(entry.target);
                }
            });
        }, { threshold: .5 });
        counterEls.forEach(el => counterObs.observe(el));
    }

})();
