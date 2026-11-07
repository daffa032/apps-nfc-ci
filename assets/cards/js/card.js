// Public card: share with copy fallback (PRD SH-01). Save contact is in floating-actions.js.
(() => {
    const card = document.getElementById('digitalCard');
    if (!card) return;

    // A missing photo should not leave alt text or an empty frame on the card.
    card.querySelectorAll('img[data-fallback]').forEach((img) => {
        const hide = () => {
            if (img.dataset.fallback === 'avatar') {
                // The photo sits in its enlarge button; a broken photo leaves the monogram and no button.
                const holder = img.closest('.nc-avatar-button') || img;
                holder.nextElementSibling?.removeAttribute('hidden');
                holder.remove();
            } else {
                img.closest('figure')?.remove();
                const gallery = card.querySelector('.nc-gallery');
                if (gallery && !gallery.querySelector('figure')) gallery.remove();
            }
        };
        if (img.complete && img.naturalWidth === 0) hide();
        else img.addEventListener('error', hide, { once: true });
    });
    // Translated messages printed by digital_card/frame-end.php.
    let strings = {};
    try {
        strings = JSON.parse(document.getElementById('ncStrings')?.textContent || '{}');
    } catch {
        strings = {};
    }
    const status = card.querySelector('.nc-status');
    const fallback = card.querySelector('.nc-share-fallback');

    const copy = async () => {
        const url = card.dataset.shareUrl;
        try {
            if (!navigator.clipboard) throw new Error('Clipboard unavailable');
            await navigator.clipboard.writeText(url);
            status.textContent = strings.linkCopied || 'Tautan disalin.';
        } catch {
            fallback.hidden = false;
            const input = fallback.querySelector('input');
            input.focus();
            input.select();
        }
    };

    card.addEventListener('click', async (event) => {
        const action = event.target.closest('[data-action]')?.dataset.action;
        if (action === 'share') {
            if (!navigator.share) {
                await copy();
                return;
            }
            try {
                await navigator.share({ title: card.dataset.shareTitle || document.title, url: card.dataset.shareUrl });
            } catch (error) {
                // Cancelling the share sheet is not an error.
                if (error.name !== 'AbortError') await copy();
            }
        }
    });
})();
