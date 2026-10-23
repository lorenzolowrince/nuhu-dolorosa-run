/* ============================================================
   Nuhu Dolorosa Charity Run – Main JavaScript
   ============================================================ */

(function () {
    'use strict';

    const isSite        = document.body.classList.contains('site');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // --- Navbar scroll effect ---
    const mainNav = document.getElementById('mainNav');
    if (mainNav) {
        const onNavScroll = () => mainNav.classList.toggle('scrolled', window.scrollY > 50);
        window.addEventListener('scroll', onNavScroll, { passive: true });
        onNavScroll();
    }

    // --- Back to top button ---
    const backToTop = document.getElementById('back-to-top');
    if (backToTop) {
        const toggleBackToTop = () => {
            backToTop.classList.toggle('show', window.scrollY > 400);
        };
        window.addEventListener('scroll', toggleBackToTop, { passive: true });
        toggleBackToTop();
        backToTop.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: reducedMotion ? 'auto' : 'smooth' });
        });
    }

    // --- Smooth scroll for in-page anchor links (also handles /#section on the homepage) ---
    document.querySelectorAll('a[href*="#"]').forEach(link => {
        link.addEventListener('click', e => {
            const url = new URL(link.href, location.href);
            if (url.pathname !== location.pathname || !url.hash || url.hash === '#') return;
            const target = document.querySelector(url.hash);
            if (!target) return;
            e.preventDefault();
            const offset = (mainNav ? mainNav.offsetHeight : 0) + 12;
            window.scrollTo({
                top: target.getBoundingClientRect().top + window.scrollY - offset,
                behavior: reducedMotion ? 'auto' : 'smooth'
            });
            history.replaceState(null, '', url.hash);
            // Close the mobile menu after choosing a section
            const menu = document.getElementById('navMenu');
            if (menu && menu.classList.contains('show') && window.bootstrap) {
                bootstrap.Collapse.getOrCreateInstance(menu).hide();
            }
        });
    });

    // --- Animate elements on scroll (Intersection Observer) ---
    if (isSite) {
        // Public site: CSS handles the motion; JS only flags elements as they enter
        const revealEls = document.querySelectorAll('.reveal');
        if ('IntersectionObserver' in window && !reducedMotion) {
            clearTimeout(window.__revealFallback);
            document.documentElement.classList.add('reveal-ready');
            const revealObs = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-in');
                        revealObs.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
            revealEls.forEach(el => revealObs.observe(el));
        }
    } else {
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
    }

    // --- Toast notification ---
    const siteToast = document.getElementById('siteToast');
    let toastTimer = null;
    function showToast(msg) {
        if (!siteToast) return;
        siteToast.innerHTML = '<i class="fas fa-check-circle me-2" aria-hidden="true"></i>';
        siteToast.appendChild(document.createTextNode(msg));
        siteToast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => siteToast.classList.remove('show'), 2600);
    }
    window.showToast = showToast;

    // --- Image viewer (lightbox) ---
    const lightbox = document.getElementById('lightbox');
    if (lightbox) {
        const lbImg  = document.getElementById('lightboxImg');
        const lbCap  = document.getElementById('lightboxCaption');
        const lbNavs = lightbox.querySelectorAll('[data-lightbox-step]');
        let group  = [];
        let index  = 0;
        let opener = null;

        const show = i => {
            index = (i + group.length) % group.length;
            lbImg.src = group[index].src;
            lbImg.alt = group[index].caption;
            lbCap.textContent = group[index].caption;
            lbNavs.forEach(b => { b.hidden = group.length < 2; });
        };

        window.openLightbox = function (src, caption, items, startIndex) {
            opener = document.activeElement;
            group = items && items.length ? items : [{ src: src, caption: caption || '' }];
            show(startIndex || 0);
            if (typeof lightbox.showModal === 'function') lightbox.showModal();
            else lightbox.setAttribute('open', '');
            document.body.style.overflow = 'hidden';
        };
        window.closeLightbox = function () {
            if (typeof lightbox.close === 'function') lightbox.close();
            else lightbox.removeAttribute('open');
        };
        lightbox.addEventListener('close', () => {
            document.body.style.overflow = '';
            lbImg.removeAttribute('src');
            if (opener && opener.focus) opener.focus();
        });
        // Clicking the dark backdrop or the close button closes the viewer
        lightbox.addEventListener('click', e => {
            if (e.target === lightbox || e.target.closest('[data-lightbox-close]')) window.closeLightbox();
        });
        lbNavs.forEach(b => b.addEventListener('click', () => show(index + Number(b.dataset.lightboxStep))));
        lightbox.addEventListener('keydown', e => {
            if (group.length < 2) return;
            if (e.key === 'ArrowRight') show(index + 1);
            if (e.key === 'ArrowLeft')  show(index - 1);
        });

        // Grouped images: every [data-full] button inside a [data-lightbox-group]
        document.querySelectorAll('[data-lightbox-group]').forEach(groupEl => {
            const buttons = Array.from(groupEl.querySelectorAll('[data-full]'));
            const items = buttons.map(b => ({ src: b.dataset.full, caption: b.getAttribute('aria-label') || '' }));
            buttons.forEach((b, i) => b.addEventListener('click', () => window.openLightbox(null, null, items, i)));
        });
    }

    // --- Gallery prev/next buttons ---
    const galleryTrack = document.getElementById('galleryTrack');
    if (galleryTrack) {
        document.querySelectorAll('[data-gallery-step]').forEach(btn => {
            btn.addEventListener('click', () => {
                const item = galleryTrack.querySelector('.gallery-item');
                const step = item ? item.getBoundingClientRect().width + 16 : 320;
                galleryTrack.scrollBy({ left: step * Number(btn.dataset.galleryStep), behavior: reducedMotion ? 'auto' : 'smooth' });
            });
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
        form.addEventListener('submit', function (e) {
            // A page-level check (e.g. registration validation) already stopped this submit
            if (e.defaultPrevented) return;
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
                if (siteToast) {
                    showToast(siteToast.dataset.copied || 'Copied!');
                    return;
                }
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
