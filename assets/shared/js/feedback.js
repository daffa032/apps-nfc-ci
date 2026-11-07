// Toasts in the bottom-right corner for action results (partials/feedback/flash.php).
// Success hides after 6 s (role=status); errors stay until closed (role=alert), per the PRD.
(() => {
    const region = document.getElementById('nfcToastRegion');
    const template = document.getElementById('nfcToastTemplate');
    if (!region || !template) return;

    const kinds = {
        success: { title: region.dataset.titleSuccess || 'Berhasil', icon: 'fa-check-circle', role: 'status', live: 'polite' },
        error: { title: region.dataset.titleError || 'Gagal', icon: 'fa-exclamation-circle', role: 'alert', live: 'assertive' },
    };

    // Message is always set as text, never HTML.
    window.nfcToast = (type, message) => {
        const kind = kinds[type] || kinds.error;
        const toast = template.content.firstElementChild.cloneNode(true);
        toast.classList.add(`nfc-toast-${type === 'success' ? 'success' : 'error'}`);
        toast.setAttribute('role', kind.role);
        toast.setAttribute('aria-live', kind.live);
        toast.querySelector('.nfc-toast-icon').classList.add(kind.icon);
        toast.querySelector('.nfc-toast-title').textContent = kind.title;
        toast.querySelector('.nfc-toast-message').textContent = String(message || '');
        region.append(toast);

        if (typeof bootstrap === 'undefined') {
            toast.classList.add('show');
            toast.querySelector('.btn-close').addEventListener('click', () => toast.remove());
            return;
        }
        toast.addEventListener('hidden.bs.toast', () => toast.remove(), { once: true });
        bootstrap.Toast.getOrCreateInstance(toast, { autohide: type === 'success', delay: 6000 }).show();
    };

    if (region.dataset.messageType) {
        window.nfcToast(region.dataset.messageType, region.dataset.message);
    }
})();

// First invalid field gets focus right away; the toast does not block the form (FB-02).
(() => {
    document.querySelector('[aria-invalid="true"]')?.focus();
})();
