/**
 * Cinetixx Movie Poster Slider – Swiper initialization & interaction fixes
 */
document.addEventListener('DOMContentLoaded', function () {
    var config = window.ctxSliderConfig || {};
    var slidesPerView = parseInt(config.slidesPerView, 10) || 4;
    var loop = !!config.loop;

    /* ── Swiper ───────────────────────────────────────────────────── */
    var swiper = new Swiper('.ctx-swiper', {
        slidesPerView: 2,
        spaceBetween: 16,
        grabCursor: true,
        loop: loop,
        /*
         * Click / drag fixes:
         * - threshold 10: ignore pointer movements under 10 px so a
         *   slightly shaky tap is not mistaken for a swipe.
         * - preventClicks off: Swiper's built-in click-prevention is
         *   too aggressive (any 1 px movement blocks clicks). We
         *   replace it with a custom drag guard below.
         * - touchStartPreventDefault off: let the browser handle
         *   default touch behaviour (scroll, link activation).
         */
        threshold: 10,
        preventClicks: false,
        preventClicksPropagation: false,
        touchStartPreventDefault: false,
        navigation: {
            prevEl: '.ctx-prev',
            nextEl: '.ctx-next',
        },
        breakpoints: {
            // ≥480px
            480: {
                slidesPerView: 2,
                spaceBetween: 16,
            },
            // ≥640px
            640: {
                slidesPerView: 3,
                spaceBetween: 20,
            },
            // ≥900px
            900: {
                slidesPerView: Math.min(slidesPerView, 4),
                spaceBetween: 24,
            },
            // ≥1100px
            1100: {
                slidesPerView: slidesPerView,
                spaceBetween: 28,
            },
        },
    });

    /* ── Drag guard & touch-overlay toggle ─────────────────────── */
    var startX = 0;
    var startY = 0;
    var lastPointerType = 'mouse';
    var DRAG_THRESHOLD = 10; // px

    var swiperEl = document.querySelector('.ctx-swiper');
    if (!swiperEl) return;

    /* Track where every interaction starts and whether it was
       a mouse or finger (important for hybrid / touchscreen laptops). */
    swiperEl.addEventListener('pointerdown', function (e) {
        startX = e.clientX;
        startY = e.clientY;
        lastPointerType = e.pointerType; // 'mouse' | 'touch' | 'pen'
    });

    /*
     * Single capture-phase handler on the Swiper container that:
     * 1. Blocks navigation when the pointer moved more than DRAG_THRESHOLD
     *    (i.e. the user was swiping, not clicking).
     * 2. On touch devices, implements a two-tap pattern: first tap
     *    reveals the overlay, second tap follows the link.
     */
    swiperEl.addEventListener('click', function (e) {
        var dx = Math.abs(e.clientX - startX);
        var dy = Math.abs(e.clientY - startY);

        /* ── Was it a drag / swipe? ────────────────────────────── */
        if (dx > DRAG_THRESHOLD || dy > DRAG_THRESHOLD) {
            e.preventDefault();
            return;
        }

        /* ── Regular click / tap ───────────────────────────────── */
        var link = e.target.closest('.ctx-poster-link');
        if (!link) return;

        /* Clicks on individual showtime links go through directly */
        if (e.target.closest('.ctx-time-link')) return;

        /*
         * Touch interaction: first tap opens the overlay,
         * second tap follows the link.
         * We only do this for pointerType === 'touch' so that
         * mouse users on touchscreen laptops are not affected.
         */
        if (lastPointerType === 'touch' && !link.classList.contains('ctx-touch-active')) {
            e.preventDefault();
            swiperEl.querySelectorAll('.ctx-poster-link.ctx-touch-active').forEach(function (el) {
                el.classList.remove('ctx-touch-active');
            });
            link.classList.add('ctx-touch-active');
        }
    }, true); /* capture phase – runs before the <a> default action */

    /* Dismiss the touch overlay when tapping outside or navigating */
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.ctx-poster-link') && !e.target.closest('.ctx-slider-nav')) {
            document.querySelectorAll('.ctx-poster-link.ctx-touch-active').forEach(function (el) {
                el.classList.remove('ctx-touch-active');
            });
        }
    });

    swiper.on('slideChange', function () {
        document.querySelectorAll('.ctx-poster-link.ctx-touch-active').forEach(function (el) {
            el.classList.remove('ctx-touch-active');
        });
    });
});
