// Dashboard home: share the public card URL, or copy it, with a manual-copy fallback (PRD SH-01).
(() => {
    const page = document.getElementById('dashboardHome');
    if (!page) return;

    const url = page.dataset.publicUrl;
    const status = page.querySelector('[data-copy-status]');
    const manual = page.querySelector('.dash-manual-copy');

    const showManualCopy = () => {
        if (!manual) return;
        manual.hidden = false;
        const input = manual.querySelector('input');
        input.focus();
        input.select();
    };

    const copy = async () => {
        try {
            if (!navigator.clipboard) throw new Error('Clipboard unavailable');
            await navigator.clipboard.writeText(url);
            if (status) status.textContent = status.dataset.copiedText || 'Tautan disalin.';
        } catch {
            if (status) status.textContent = '';
            showManualCopy();
        }
    };

    page.addEventListener('click', async (event) => {
        const action = event.target.closest('[data-action]')?.dataset.action;
        if (action === 'copy') {
            await copy();
        } else if (action === 'share') {
            if (!navigator.share) {
                await copy();
                return;
            }
            try {
                await navigator.share({ title: document.title, url });
            } catch (error) {
                // Cancelling the share sheet is not an error.
                if (error.name !== 'AbortError') await copy();
            }
        }
    });
})();
