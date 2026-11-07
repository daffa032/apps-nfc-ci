// Personal Info and first-profile form: photo preview, gallery rows, social links, busy submit.
(() => {
    // Result toast from shared/js/feedback.js; falls back to a plain alert if the layout lacks it.
    const notify = (type, message) => (window.nfcToast ? window.nfcToast(type, message) : window.alert(message));

    const page = document.getElementById('profilePage');
    if (!page) return;

    // Translated texts printed by the page (data-strings); Indonesian fallbacks keep it working without them.
    let text = {};
    try {
        text = JSON.parse(page.dataset.strings || '{}');
    } catch {
        text = {};
    }
    const t = (key, fallback) => text[key] || fallback;

    // Prevent double submits; the page reloads with the server result.
    page.addEventListener('submit', (event) => {
        const button = event.target.querySelector('button[type="submit"][data-busy-label]');
        if (!button) return;
        button.textContent = button.dataset.busyLabel;
        button.disabled = true;
    });

    async function postForm(url, data) {
        const body = new URLSearchParams({ ...data, [page.dataset.csrfName]: page.dataset.csrfHash });
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                Accept: 'application/json',
            },
            body,
        });
        const result = await response.json().catch(() => null);
        if (!response.ok || !result) {
            throw new Error(result?.message || t('requestFailed', 'Permintaan gagal. Muat ulang halaman lalu coba lagi.'));
        }
        return result;
    }

    function showPreview(file, image, placeholder) {
        if (!file || !image) return;
        const reader = new FileReader();
        reader.onload = () => {
            image.src = reader.result;
            image.hidden = false;
            if (placeholder) placeholder.hidden = true;
        };
        reader.readAsDataURL(file);
    }

    let newRows = 0;
    document.getElementById('addRow')?.addEventListener('click', () => {
        newRows += 1;
        const id = `newImage${newRows}`;
        const esc = (value) => String(value).replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);
        document.getElementById('newImageRows').insertAdjacentHTML('beforeend', `
            <div class="dash-gallery-item image-row">
                <img alt="${esc(t('newPhotoPreview', 'Pratinjau foto baru'))}" hidden>
                <div class="dash-field">
                    <label for="${id}">${esc(t('newPhotoN', 'Foto baru {0}').replace('{0}', newRows))}</label>
                    <input type="file" id="${id}" name="images[]" class="form-control" accept="image/jpeg,image/png,image/webp" required>
                </div>
                <div class="dash-field">
                    <label for="${id}Caption">${esc(t('caption', 'Keterangan'))}</label>
                    <input type="text" id="${id}Caption" name="captions[]" class="form-control" maxlength="255" required>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm remove-row">${esc(t('cancel', 'Batal'))}</button>
            </div>
        `);
        document.getElementById('uploadSubmit').hidden = false;
        document.getElementById(id).focus();
    });

    page.addEventListener('change', (event) => {
        const input = event.target;
        if (input.id === 'inputProfilePicture') {
            showPreview(input.files?.[0], document.getElementById('imagePreviewForm'), document.getElementById('photoPlaceholder'));
        } else if (input.matches('input[type="file"][name="images[]"]')) {
            showPreview(input.files?.[0], input.closest('.image-row')?.querySelector('img'));
        }
    });

    page.addEventListener('click', async (event) => {
        const galleryImage = event.target.closest('[data-action="preview-gallery"]');
        if (galleryImage) {
            document.getElementById('modalImage').src = galleryImage.src;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('imageModal')).show();
            return;
        }

        const button = event.target.closest('button');
        if (!button) return;

        if (button.classList.contains('remove-row')) {
            const row = button.closest('.image-row');
            const id = button.dataset.id;
            if (!id) {
                row.remove();
                const submit = document.getElementById('uploadSubmit');
                if (submit && !document.querySelector('#newImageRows .image-row')) submit.hidden = true;
                return;
            }
            if (!window.confirm(t('confirmDeletePhoto', 'Hapus foto ini dari galeri?'))) return;
            button.disabled = true;
            try {
                const result = await postForm(page.dataset.deleteImageUrl, { id });
                if (!result.success) throw new Error(result.message || t('photoDeleteFailed', 'Foto gagal dihapus.'));
                row.remove();
                notify('success', result.message || t('photoDeleted', 'Foto dihapus dari galeri.'));
            } catch (error) {
                notify('error', error.message);
                button.disabled = false;
            }
            return;
        }

        const action = button.dataset.action;
        if (action === 'save-caption') {
            const item = button.closest('.dash-gallery-item');
            const status = item.querySelector('[data-caption-status]');
            button.disabled = true;
            try {
                const result = await postForm(page.dataset.captionUrl, {
                    id: button.dataset.id,
                    caption: item.querySelector('input[type="text"]').value,
                });
                if (!result.success) throw new Error(result.message || t('captionFailed', 'Keterangan gagal disimpan.'));
                status.textContent = t('captionSaved', 'Keterangan tersimpan.');
                notify('success', result.message || t('captionSaved', 'Keterangan tersimpan.'));
            } catch (error) {
                status.textContent = '';
                notify('error', error.message);
            } finally {
                button.disabled = false;
            }
            return;
        }
        if (action !== 'save-social' && action !== 'delete-social') return;
        const channel = button.dataset.channel;
        if (action === 'delete-social' && !window.confirm(t('confirmDeleteLink', 'Hapus tautan ini dari kartu?'))) return;
        button.disabled = true;
        try {
            const result = action === 'save-social'
                ? await postForm(page.dataset.saveSocialUrl, {
                    type: channel,
                    link: document.getElementById(`${channel}-link`).value,
                })
                : await postForm(page.dataset.deleteSocialUrl, { socialMediaType: channel });
            if (result.status !== 'success') throw new Error(result.message || t('linkSaveFailed', 'Tautan gagal disimpan.'));
            window.location.reload();
        } catch (error) {
            notify('error', error.message);
            button.disabled = false;
        }
    });

    // Brand slots: show the chosen (or cropped) file's name and image in the slot's frame preview.
    const brandForm = document.querySelector('[data-brand-form]');
    const brandUrls = {};
    brandForm?.addEventListener('change', (event) => {
        const input = event.target.closest?.('[data-brand-input]');
        if (!input) return;
        const slot = input.closest('[data-brand-slot]');
        const file = input.files?.[0];
        slot.querySelector('[data-brand-filename]').textContent = file ? file.name : brandForm.dataset.noFile;
        const thumb = slot.querySelector('[data-brand-thumb]');
        if (!thumb.dataset.saved) thumb.dataset.saved = thumb.getAttribute('src') || '';
        if (brandUrls[input.name]) URL.revokeObjectURL(brandUrls[input.name]);
        delete brandUrls[input.name];
        if (file && /^image\/(jpeg|png|webp)$/.test(file.type)) {
            brandUrls[input.name] = URL.createObjectURL(file);
            thumb.src = brandUrls[input.name];
        } else if (thumb.dataset.saved) {
            thumb.src = thumb.dataset.saved;
        }
        // A newly chosen image is meant to be seen, so its switch turns back on.
        const visible = slot.querySelector('[data-brand-visible]');
        if (file && visible && !visible.checked) {
            visible.checked = true;
            visible.dispatchEvent(new Event('change', { bubbles: true }));
        }
        const hasImage = Boolean(file || thumb.dataset.saved);
        thumb.hidden = !hasImage;
        slot.querySelector('[data-brand-empty]').hidden = hasImage;
    });
})();
