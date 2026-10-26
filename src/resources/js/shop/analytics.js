/**
 * Yandex.Metrica, loaded only once the visitor has accepted analytics
 * cookies. The counter id comes from the page (data-metrika on <body>).
 */
export function loadMetrika() {
    const id = Number(document.body.dataset.metrika);

    if (!id || window.ym) {
        return;
    }

    // Yandex's own loader: queue calls until tag.js arrives.
    window.ym = function (...args) {
        (window.ym.a = window.ym.a || []).push(args);
    };
    window.ym.l = Date.now();

    const script = document.createElement('script');
    script.async = true;
    script.src = 'https://mc.yandex.ru/metrika/tag.js';
    document.head.append(script);

    window.ym(id, 'init', {
        clickmap: true,
        trackLinks: true,
        accurateTrackBounce: true,
        webvisor: true,
        ecommerce: 'dataLayer',
    });
}
