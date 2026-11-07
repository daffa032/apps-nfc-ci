// Pro share page (PRD QR-02/03): copy/share the canonical card URL and save PNG images drawn from
// the server's QR matrix (data-qr). Success is only reported once the browser has accepted the action.
(() => {
    const root = document.getElementById('ncShare');
    if (!root) return;

    const status = root.querySelector('.nc-share-status');
    const fallback = root.querySelector('.nc-share-fallback');
    const url = root.dataset.cardUrl;
    const rows = root.dataset.qr.split(',');
    const say = (text) => { status.textContent = text; };

    // A missing photo file falls back to the monogram (no broken image, and none in the PNG).
    const photo = root.querySelector('[data-photo-fallback]');
    const dropPhoto = () => {
        photo.nextElementSibling?.removeAttribute('hidden');
        photo.remove();
        root.dataset.photo = '';
    };
    if (photo) {
        if (photo.complete && photo.naturalWidth === 0) dropPhoto();
        else photo.addEventListener('error', dropPhoto, { once: true });
    }

    const copy = async () => {
        try {
            if (!navigator.clipboard) throw new Error('Clipboard unavailable');
            await navigator.clipboard.writeText(url);
            say(root.dataset.textCopied);
        } catch {
            say('');
            fallback.hidden = false;
            const input = fallback.querySelector('input');
            input.focus();
            input.select();
        }
    };

    const drawQr = (ctx, x, y, size) => {
        const quiet = 4;
        const cell = size / (rows.length + quiet * 2);
        ctx.fillStyle = '#fff';
        ctx.fillRect(x, y, size, size);
        ctx.fillStyle = '#000';
        rows.forEach((row, r) => {
            for (let c = 0; c < row.length; c += 1) {
                if (row[c] === '1') ctx.fillRect(Math.floor(x + (c + quiet) * cell), Math.floor(y + (r + quiet) * cell), Math.ceil(cell), Math.ceil(cell));
            }
        });
    };

    const loadPhoto = () => new Promise((resolve) => {
        if (!root.dataset.photo) return resolve(null);
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => resolve(null);
        img.src = root.dataset.photo;
    });

    const wrap = (ctx, text, maxWidth) => {
        const words = text.split(/\s+/);
        const lines = [];
        let line = '';
        words.forEach((word) => {
            const next = line ? `${line} ${word}` : word;
            if (ctx.measureText(next).width > maxWidth && line) {
                lines.push(line);
                line = word;
            } else {
                line = next;
            }
        });
        if (line) lines.push(line);
        return lines.slice(0, 2);
    };

    const cardCanvas = async () => {
        const width = 1080;
        const height = 1500;
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, width, height);
        gradient.addColorStop(0, '#e9f0f6');
        gradient.addColorStop(1, '#f7f4ee');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, width, height);
        ctx.fillStyle = '#fff';
        ctx.beginPath();
        ctx.roundRect(60, 60, width - 120, height - 120, 48);
        ctx.fill();

        const photo = await loadPhoto();
        const cx = width / 2;
        ctx.save();
        ctx.beginPath();
        ctx.arc(cx, 220, 110, 0, Math.PI * 2);
        ctx.closePath();
        ctx.clip();
        if (photo) {
            const side = Math.min(photo.naturalWidth, photo.naturalHeight);
            ctx.drawImage(photo, (photo.naturalWidth - side) / 2, (photo.naturalHeight - side) / 2, side, side, cx - 110, 110, 220, 220);
        } else {
            ctx.fillStyle = '#dce6ee';
            ctx.fillRect(cx - 110, 110, 220, 220);
            ctx.fillStyle = '#143656';
            ctx.font = '700 80px system-ui, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(root.dataset.initials, cx, 220);
        }
        ctx.restore();

        ctx.textAlign = 'center';
        ctx.textBaseline = 'alphabetic';
        ctx.fillStyle = '#1d2b36';
        ctx.font = '700 64px system-ui, sans-serif';
        let y = 420;
        wrap(ctx, root.dataset.name || '', width - 220).forEach((line) => { ctx.fillText(line, cx, y); y += 72; });
        ctx.fillStyle = '#55636e';
        ctx.font = '600 40px system-ui, sans-serif';
        [root.dataset.title, root.dataset.company].filter(Boolean).forEach((text) => {
            wrap(ctx, text, width - 220).forEach((line) => { ctx.fillText(line, cx, y); y += 52; });
        });

        const qrSize = 620;
        drawQr(ctx, cx - qrSize / 2, Math.max(y + 20, 640), qrSize);
        ctx.fillStyle = '#55636e';
        ctx.font = '500 34px system-ui, sans-serif';
        ctx.fillText(root.dataset.textScan, cx, height - 110);
        return canvas;
    };

    const qrCanvas = () => {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 1200;
        drawQr(canvas.getContext('2d'), 0, 0, 1200);
        return canvas;
    };

    const save = async (canvas, name) => {
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        if (!blob) throw new Error('No image');
        const file = new File([blob], name, { type: 'image/png' });
        // Phones can hand the image straight to the share sheet (e.g. "Save image").
        if (navigator.canShare?.({ files: [file] })) {
            try {
                await navigator.share({ files: [file] });
                say(root.dataset.textReady);
                return;
            } catch (error) {
                if (error.name === 'AbortError') return;
            }
        }
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = name;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
        say(root.dataset.textReady);
    };

    root.addEventListener('click', async (event) => {
        const action = event.target.closest('[data-action]')?.dataset.action;
        if (!action) return;
        try {
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
            } else if (action === 'save-card') {
                await save(await cardCanvas(), `${root.dataset.file}-kartu.png`);
            } else if (action === 'save-qr') {
                await save(qrCanvas(), `${root.dataset.file}-qr.png`);
            }
        } catch {
            say(root.dataset.textFailed);
        }
    });
})();
