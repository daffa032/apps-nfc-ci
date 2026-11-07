// Master Templates: live preview of an uploaded HTML template before it is saved (PRD MA-02).
// The server sanitises the HTML and renders it with sample data; nothing is stored until submit.
(() => {
    const form = document.querySelector('[data-template-form]');
    if (!form) return;

    const fileInput = form.querySelector('[name=template_file]');
    const frame = form.querySelector('.dash-template-frame');
    const status = form.querySelector('[data-preview-status]');
    const submit = form.querySelector('[data-submit-label]');
    // Status texts in the page language (data-text-* on the status line).
    const say = (key, fallback) => status.dataset[key] || fallback;

    const updateSubmitLabel = () => {
        const active = form.querySelector('[name=is_active]:checked')?.value === '1';
        submit.textContent = active ? (submit.dataset.labelActive || 'Tambah sebagai Aktif') : (submit.dataset.labelInactive || 'Tambah sebagai Nonaktif');
    };
    form.querySelectorAll('[name=is_active]').forEach((radio) => radio.addEventListener('change', updateSubmitLabel));
    updateSubmitLabel();

    fileInput.addEventListener('change', async () => {
        const file = fileInput.files?.[0];
        frame.hidden = true;
        if (!file) {
            status.textContent = say('textPick', 'Pilih berkas untuk melihat pratinjau.');
            return;
        }
        if (!/\.html$/i.test(file.name) || file.size > 100 * 1024) {
            status.textContent = say('textRules', 'Pilih berkas .html maksimal 100 KB.');
            return;
        }
        status.textContent = say('textChecking', 'Memeriksa template…');
        const body = new FormData();
        body.append('html', await file.text());
        body.append(form.querySelector('input[type=hidden][name^=csrf]')?.name ?? 'csrf_test_name', form.querySelector('input[type=hidden][name^=csrf]')?.value ?? '');
        try {
            const response = await fetch(form.dataset.previewUrl, { method: 'POST', body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (response.status === 422) {
                status.textContent = (await response.json()).message;
                return;
            }
            if (!response.ok) throw new Error(String(response.status));
            // Rendered by the server from sanitised HTML; the sandbox blocks scripts and forms in the frame.
            frame.srcdoc = await response.text();
            frame.hidden = false;
            status.textContent = say('textValid', 'Template valid. Pratinjau memakai data contoh.');
        } catch {
            status.textContent = say('textFailed', 'Pratinjau gagal dimuat. Template tetap diperiksa saat disimpan.');
        }
    });
})();

// Slot limits: −/+ steppers, and the usage bar and "slots left" follow the typed limit before saving.
(() => {
    const form = document.querySelector('[data-slot-form]');
    if (!form) return;
    const note = form.querySelector('[data-slot-dirty]');

    const refresh = (card) => {
        const input = card.querySelector('input[type=number]');
        const used = Number(card.dataset.used) || 0;
        const limit = Number(input.value) || 0;
        const left = Math.max(0, limit - used);
        const bar = card.querySelector('[data-slot-bar]');
        const label = card.querySelector('[data-slot-left]');
        card.querySelector('[data-slot-limit]').textContent = String(limit);
        bar.max = Math.max(1, limit);
        bar.classList.toggle('is-full', left === 0);
        label.classList.toggle('is-full', left === 0);
        label.textContent = left === 0 ? label.dataset.textFull : label.dataset.textLeft.replace('{0}', String(left));
    };

    const markDirty = () => {
        note.hidden = [...form.querySelectorAll('input[type=number]')].every((input) => input.value === input.defaultValue);
    };

    form.querySelectorAll('[data-slot-card]').forEach((card) => {
        const input = card.querySelector('input[type=number]');
        card.querySelectorAll('[data-step]').forEach((button) => button.addEventListener('click', () => {
            const next = (Number(input.value) || 0) + Number(button.dataset.step);
            input.value = String(Math.min(Number(input.max), Math.max(Number(input.min), next)));
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }));
        input.addEventListener('input', () => {
            refresh(card);
            markDirty();
        });
    });
})();

// Rename dialogs (one per template row): open from the row button, close with Cancel/Esc/backdrop,
// focus back on the row button. A failed rename reopens its dialog with the error.
(() => {
    document.querySelectorAll('[data-rename-open]').forEach((button) => {
        const dialog = document.getElementById(button.dataset.renameOpen);
        if (!dialog || typeof dialog.showModal !== 'function') return;
        const input = dialog.querySelector('input[name=nama_template]');
        const open = () => {
            dialog.showModal();
            input?.focus();
            input?.select();
        };
        button.addEventListener('click', open);
        dialog.querySelectorAll('[data-rename-close]').forEach((close) => close.addEventListener('click', () => dialog.close()));
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
        dialog.addEventListener('close', () => button.focus());
        if (dialog.hasAttribute('data-rename-reopen')) open();
    });
})();
