// Public card: tap the profile photo, a gallery photo or the brand logo to see it larger (PRD CARD-01).
// The dialog shows the image inside the clicked button, which the server printed from the card's own
// files; this script only copies it and opens/closes the dialog. Escape is native.
(() => {
    const dialog = document.getElementById('ncPhotoDialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    const image = dialog.querySelector('[data-photo-image]');
    const caption = dialog.querySelector('[data-photo-caption]');
    let opener = null;

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="photo"]');
        const source = button?.querySelector('img');
        if (!source) return;
        opener = button;
        image.src = source.currentSrc || source.src;
        image.alt = source.alt;
        const text = button.closest('figure')?.querySelector('figcaption')?.textContent.trim() || '';
        caption.textContent = text;
        caption.hidden = text === '';
        dialog.dataset.kind = button.dataset.photoKind || 'photo';
        dialog.setAttribute('aria-label', source.alt || dialog.getAttribute('aria-label'));
        dialog.showModal();
        dialog.querySelector('[data-photo-close]').focus();
    });
    dialog.querySelector('[data-photo-close]').addEventListener('click', () => dialog.close());
    // A click on the backdrop (outside the image) closes it too.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('close', () => opener?.focus());
})();
