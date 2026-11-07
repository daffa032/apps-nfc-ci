// Public card: count WhatsApp/phone/email clicks for Pro statistics (PRD section 6).
// The page prints the endpoint and a daily token only for Pro cards; without them this does nothing.
(() => {
    const url = document.querySelector('meta[name="nc-event-url"]')?.content;
    const token = document.querySelector('meta[name="nc-event-token"]')?.content;
    if (!url || !token || !navigator.sendBeacon) return;

    const typeOf = (href) => {
        if (href.startsWith('https://wa.me/')) return 'whatsapp';
        if (href.startsWith('tel:')) return 'phone';
        if (href.startsWith('mailto:')) return 'email';
        return null;
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        const type = link && typeOf(link.getAttribute('href'));
        if (!type) return;
        const body = new FormData();
        body.append('type', type);
        body.append('token', token);
        navigator.sendBeacon(url, body);
    });
})();
