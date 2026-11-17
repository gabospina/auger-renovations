/**
 * Auger Renovations — Floating share button
 * - Injects a share button on public pages (not on /admin/)
 * - Uses native Web Share API on mobile, falls back to copy link on desktop
 * - Tracks share events to analytics
 */
(function () {
    'use strict';

    // Skip admin pages
    if (window.location.pathname.indexOf('/admin/') !== -1) return;
    // Skip QR redirect (it redirects away anyway)
    if (window.location.pathname.indexOf('qr-redirect') !== -1) return;

    var currentUrl  = window.location.href;
    var pageTitle   = document.title || 'Auger Renovations';
    var pageName    = window.location.pathname.split('/').pop() || 'index.html';

    // Detect API base path (if page is at root)
    var endpoint = (window.location.pathname.indexOf('/admin/') !== -1)
        ? '../api/track.php'
        : 'api/track.php';

    function trackShare(method) {
        try {
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type: 'share_' + method, page: pageName }),
                keepalive: true
            }).catch(function () {});
        } catch (e) { /* silent */ }
    }

    function buildShareButton() {
        // Style
        var style = document.createElement('style');
        style.textContent = [
            '.share-page-float {',
            '  position: fixed;',
            '  bottom: 24px;',
            '  left: 24px;',
            '  z-index: 8999;',
            '}',
            '.share-page-btn {',
            '  display: inline-flex;',
            '  align-items: center;',
            '  gap: 8px;',
            '  background: #24292e;',
            '  color: #fff;',
            '  padding: 12px 18px;',
            '  border-radius: 999px;',
            '  border: none;',
            '  cursor: pointer;',
            '  font-family: -apple-system, Arial, sans-serif;',
            '  font-size: 0.92rem;',
            '  font-weight: 600;',
            '  box-shadow: 0 4px 16px rgba(0,0,0,0.25);',
            '  transition: transform 0.15s, box-shadow 0.15s;',
            '}',
            '.share-page-btn:hover {',
            '  transform: translateY(-2px);',
            '  box-shadow: 0 6px 20px rgba(0,0,0,0.3);',
            '}',
            '.share-page-btn svg { width: 18px; height: 18px; }',
            '@media (max-width: 640px) {',
            '  .share-page-float { bottom: 16px; left: 16px; }',
            '  .share-page-btn { padding: 10px 14px; font-size: 0.85rem; }',
            '  .share-page-btn .share-label { display: none; }',
            '}'
        ].join('\n');
        document.head.appendChild(style);

        // Button
        var container = document.createElement('div');
        container.className = 'share-page-float';
        container.innerHTML = [
            '<button type="button" class="share-page-btn" aria-label="Partager cette page" onclick="__shareCurrentPage()">',
            '  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">',
            '    <circle cx="18" cy="5" r="3"></circle>',
            '    <circle cx="6" cy="12" r="3"></circle>',
            '    <circle cx="18" cy="19" r="3"></circle>',
            '    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>',
            '    <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>',
            '  </svg>',
            '  <span class="share-label">Partager</span>',
            '</button>'
        ].join('\n');
        document.body.appendChild(container);
    }

    function copyFallback() {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(currentUrl).then(function () {
                showToast('✓ Lien copié dans le presse-papiers');
            }).catch(function () {
                prompt('Copiez ce lien :', currentUrl);
            });
        } else {
            prompt('Copiez ce lien :', currentUrl);
        }
    }

    function showToast(text) {
        var toast = document.createElement('div');
        toast.textContent = text;
        toast.style.cssText = 'position:fixed;bottom:90px;left:24px;background:#10b981;color:#fff;padding:12px 20px;border-radius:8px;font-family:inherit;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,0.2);z-index:9000;animation:shareToastIn 0.2s ease;';
        var anim = document.createElement('style');
        anim.textContent = '@keyframes shareToastIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}';
        document.head.appendChild(anim);
        document.body.appendChild(toast);
        setTimeout(function () { toast.remove(); }, 2500);
    }

    window.__shareCurrentPage = function () {
        // Try native share API first (mobile + modern desktop)
        if (navigator.share) {
            navigator.share({
                title: pageTitle,
                text: pageTitle,
                url: currentUrl
            }).then(function () {
                trackShare('native');
            }).catch(function (err) {
                // User cancelled or share failed — try copy fallback
                if (err && err.name === 'AbortError') return; // user cancelled — no tracking
                copyFallback();
                trackShare('copy');
            });
        } else {
            // Desktop fallback — copy link
            copyFallback();
            trackShare('copy');
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', buildShareButton);
    } else {
        buildShareButton();
    }
})();