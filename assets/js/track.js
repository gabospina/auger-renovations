/**
 * Auger Renovations — client-side event tracking
 * Sends events to api/track.php (relative to HTML file location)
 */
(function () {
    'use strict';

    var ENDPOINT = 'api/track.php';

    function send(data) {
        try {
            fetch(ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
                keepalive: true
            }).catch(function () {});
        } catch (e) {
            /* silent — tracking must never break the site */
        }
    }

    function pageName() {
        return window.location.pathname.split('/').pop() || 'index.html';
    }

    // 1. Track page view on load
    send({
        type: 'page_view',
        page: pageName(),
        referrer: document.referrer || 'direct'
    });

    // 2. Track WhatsApp / phone / directions clicks (delegated)
    document.addEventListener('click', function (e) {
        var el = e.target.closest ? e.target.closest('a') : null;
        if (!el) return;

        var href = el.getAttribute('href') || '';

        if (href.indexOf('wa.me') !== -1 || el.classList.contains('whatsapp-float-ar')) {
            send({ type: 'whatsapp_click', page: pageName() });
        } else if (href.indexOf('tel:') === 0) {
            send({ type: 'phone_click', page: pageName() });
        } else if (href.indexOf('google.com/maps') !== -1 || el.classList.contains('get-directions')) {
            send({ type: 'directions_click', page: pageName() });
        }
    }, true);
})();