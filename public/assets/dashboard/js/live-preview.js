// Personal Info live preview (PRD PV-01). The iframe shows the saved card in the current design
// (same-origin, never counted as a visit). Unsaved text and chosen photos/brand files are applied
// to it in the browser only, so the public card keeps the stored data until the form is saved.
(() => {
    const panel = document.querySelector('[data-live-preview]');
    const frame = panel?.querySelector('[data-preview-frame]');
    if (!panel || !frame) return;

    const form = document.getElementById('userForm');
    const badge = panel.querySelector('[data-preview-unsaved]');
    const fullDialog = document.getElementById('fullPreview');
    const draft = { text: {}, images: {}, hidden: {} };
    const objectUrls = {};

    const setText = (doc, field, value) => {
        doc.querySelectorAll(`[data-preview-field="${field}"]`).forEach((node) => {
            node.textContent = value;
            if (node.tagName !== 'H1' && node.tagName !== 'H2' && node.tagName !== 'SPAN') node.hidden = value.trim() === '';
        });
    };

    const setImage = (doc, slot, url) => {
        if (slot === 'profile_image') {
            const avatar = doc.querySelector('.nc-avatar-button img, img.nc-avatar, img.profile-pic');
            if (avatar) avatar.src = url;
            const monogram = doc.querySelector('div.nc-avatar');
            if (!avatar && monogram) {
                const img = doc.createElement('img');
                img.className = 'nc-avatar';
                img.alt = '';
                img.src = url;
                monogram.replaceWith(img);
            }
            return;
        }
        if (slot === 'brand_logo') {
            const box = doc.querySelector('.tpl-pro-brand');
            if (!box) return;
            const holder = Object.assign(doc.createElement('span'), { className: 'nc-pro-brand-logo' });
            holder.append(Object.assign(doc.createElement('img'), { src: url, alt: '' }));
            box.replaceChildren(holder);
            return;
        }
        if (slot === 'header_background') {
            const hero = doc.querySelector('.nc-pro-template header[class$="-hero"]');
            if (!hero) return;
            let cover = hero.querySelector('.nc-pro-cover');
            if (!cover) {
                cover = Object.assign(doc.createElement('img'), { className: 'nc-pro-cover', alt: '' });
                hero.prepend(cover);
            }
            cover.src = url;
        }
    };

    const apply = (target) => {
        const doc = target?.contentDocument;
        if (!doc?.body) return;
        Object.entries(draft.text).forEach(([field, value]) => setText(doc, field, value));
        Object.entries(draft.images).forEach(([slot, url]) => setImage(doc, slot, url));
        // Show/hide switches of the brand slots (the file stays stored; only the card hides it).
        Object.entries(draft.hidden).forEach(([slot, hidden]) => {
            const node = slot === 'brand_logo' ? doc.querySelector('.tpl-pro-brand') : doc.querySelector('.nc-pro-cover');
            if (node) node.hidden = hidden;
        });
    };

    const markDirty = () => {
        badge.hidden = [draft.text, draft.images, draft.hidden].every((part) => Object.keys(part).length === 0);
    };

    form?.addEventListener('input', (event) => {
        const field = event.target.name;
        if (!['nama', 'company_name', 'job_title'].includes(field)) return;
        draft.text[field] = event.target.value;
        markDirty();
        apply(frame);
    });

    document.addEventListener('change', (event) => {
        const input = event.target;
        const switchSlot = input.dataset?.brandVisible;
        if (switchSlot) {
            if (input.checked === input.defaultChecked) delete draft.hidden[switchSlot];
            else draft.hidden[switchSlot] = !input.checked;
            markDirty();
            frame.contentWindow?.location.reload();
            return;
        }
        const slot = input.id === 'inputProfilePicture' ? 'profile_image' : input.dataset?.brandInput;
        if (!slot || !input.files) return;
        const file = input.files[0];
        if (objectUrls[slot]) URL.revokeObjectURL(objectUrls[slot]);
        // Only images the browser can decode are shown; the server still checks every upload.
        if (file && /^image\/(jpeg|png|webp)$/.test(file.type)) {
            objectUrls[slot] = URL.createObjectURL(file);
            draft.images[slot] = objectUrls[slot];
        } else {
            delete draft.images[slot];
            delete objectUrls[slot];
        }
        markDirty();
        frame.contentWindow?.location.reload();
    });

    frame.addEventListener('load', () => apply(frame));

    panel.querySelector('[data-preview-full]')?.addEventListener('click', () => {
        if (!fullDialog) return;
        const big = fullDialog.querySelector('iframe');
        big.addEventListener('load', () => apply(big), { once: true });
        big.src = frame.src;
        fullDialog.showModal();
    });
    fullDialog?.querySelector('[data-dialog-close]')?.addEventListener('click', () => fullDialog.close());
    fullDialog?.addEventListener('close', () => {
        fullDialog.querySelector('iframe').src = 'about:blank';
    });
})();
