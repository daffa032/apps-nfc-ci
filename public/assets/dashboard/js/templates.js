// Tampilan kartu: preview dialog (PRD TH-01). Opening a preview never saves anything; the dialog
// offers "Pakai tampilan ini" only when the plan allows it, otherwise a link to the Pro plan.
(() => {
    const dialog = document.getElementById('themePreview');
    if (!dialog) return;

    const frame = dialog.querySelector('iframe');
    const title = dialog.querySelector('#themePreviewTitle');
    const form = dialog.querySelector('[data-preview-form]');
    const upgrade = dialog.querySelector('[data-preview-upgrade]');
    let opener = null;

    const close = () => {
        dialog.close();
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-preview-url]');
        if (!button) return;
        opener = button;
        // Translated title from the page, with {name} replaced as text.
        title.textContent = (dialog.dataset.titleTemplate || 'Pratinjau · {name}').replace('{name}', button.dataset.previewName);
        frame.src = button.dataset.previewUrl;
        const selected = button.dataset.previewSelected === '1';
        const allowed = button.dataset.previewAllowed === '1';
        form.hidden = !allowed;
        form.querySelector('[name=id_template]').value = button.dataset.previewId;
        upgrade.hidden = allowed || selected;
        dialog.showModal();
    });

    dialog.querySelector('[data-dialog-close]').addEventListener('click', close);
    // Click on the backdrop closes the dialog.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) close();
    });
    dialog.addEventListener('close', () => {
        frame.src = 'about:blank';
        opener?.focus();
    });
})();
