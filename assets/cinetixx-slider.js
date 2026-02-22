/**
 * Cinetixx Movie Poster Slider – Swiper initialization
 */
document.addEventListener('DOMContentLoaded', function () {
    var config = window.ctxSliderConfig || {};
    var slidesPerView = parseInt(config.slidesPerView, 10) || 4;

    var swiper = new Swiper('.ctx-swiper', {
        slidesPerView: 2,
        spaceBetween: 16,
        grabCursor: true,
        loop: false,
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
});
