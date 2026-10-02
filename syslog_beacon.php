<script>
(function () {
    let lastBeacon = 0;

    document.addEventListener('click', function (e) {
        const now = Date.now();
        if (now - lastBeacon < 350) return;

        const el = e.target.closest('button, a, input[type="submit"], input[type="button"], [role="button"]');
        if (!el) return;

        lastBeacon = now;

        const payload = {
            type: 'click',
            text: (el.innerText || el.value || el.getAttribute('aria-label') || '').trim().slice(0, 160),
            href: el.href || '',
            url: window.location.pathname + window.location.search
        };

        try {
            const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
            navigator.sendBeacon(window.location.origin + '/syslog_beacon.php', blob);
        } catch (err) {
            console.warn('Beacon failed', err);
        }
    });
})();
</script>