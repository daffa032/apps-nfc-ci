// Brand logo cropper (Personal Info, PRD BR-01): after choosing a logo the owner pans and zooms it inside
// the logo frame of the current design (circle or 2:1 box, from TemplateCatalog::logoFrame via
// data-crop-shape/ratio); the cropped PNG replaces the file in the same input. The server still validates and
// re-encodes every upload, so this only shapes the image, it does not bypass any check.
(() => {
    const dialog = document.getElementById('logoCropper');
    const input = document.querySelector('[data-brand-input="brand_logo"]');
    if (!dialog || !input || typeof DataTransfer === 'undefined') return;

    const canvas = dialog.querySelector('[data-crop-canvas]');
    const zoom = dialog.querySelector('[data-crop-zoom]');
    const circle = dialog.dataset.cropShape === 'circle';
    const ratio = Number(dialog.dataset.cropRatio) || 1;
    const STAGE_WIDTH = 480;
    const OUTPUT_HEIGHT = 240;

    let image = null;
    let sourceName = 'logo';
    let fit = 1;
    let x = 0;
    let y = 0;
    let applying = false;
    let drag = null;

    const scale = () => fit * Number(zoom.value);

    const draw = (target, factor) => {
        const c = target.getContext('2d');
        c.clearRect(0, 0, target.width, target.height);
        if (!image) return;
        const s = scale() * factor;
        c.imageSmoothingQuality = 'high';
        c.drawImage(image, x * factor - (image.width * s) / 2, y * factor - (image.height * s) / 2, image.width * s, image.height * s);
        if (circle) {
            // Keep only the circle; the corners become transparent so the logo fits a round frame.
            c.globalCompositeOperation = 'destination-in';
            c.beginPath();
            c.arc(target.width / 2, target.height / 2, Math.min(target.width, target.height) / 2, 0, Math.PI * 2);
            c.fill();
            c.globalCompositeOperation = 'source-over';
        }
    };

    const reset = () => {
        canvas.width = STAGE_WIDTH;
        canvas.height = Math.round(STAGE_WIDTH / ratio);
        // A circle starts filled (cover); a box starts with the whole logo visible (contain).
        const fits = image ? [canvas.width / image.width, canvas.height / image.height] : [1];
        fit = circle ? Math.max(...fits) : Math.min(...fits);
        zoom.value = '1';
        x = canvas.width / 2;
        y = canvas.height / 2;
        draw(canvas, 1);
    };

    const close = () => {
        if (dialog.open) dialog.close();
        if (image?.close) image.close();
        image = null;
    };

    // Hands the input's current file to the other listeners (live preview) once the crop is decided.
    const forward = () => {
        applying = true;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        applying = false;
    };

    input.addEventListener('change', async (event) => {
        if (applying) return;
        const file = input.files?.[0];
        if (!file || !/^image\/(jpeg|png|webp)$/.test(file.type)) return;
        event.stopPropagation();
        try {
            image = await createImageBitmap(file);
        } catch {
            forward();
            return;
        }
        sourceName = file.name.replace(/\.[^.]+$/, '') || 'logo';
        reset();
        dialog.showModal();
    });

    zoom.addEventListener('input', () => draw(canvas, 1));

    // Pointer position in canvas pixels (the canvas is scaled down by CSS on small screens).
    const point = (event) => {
        const box = canvas.getBoundingClientRect();
        return { px: ((event.clientX - box.left) * canvas.width) / box.width, py: ((event.clientY - box.top) * canvas.height) / box.height };
    };

    canvas.addEventListener('pointerdown', (event) => {
        const { px, py } = point(event);
        drag = { px, py, x, y };
        canvas.setPointerCapture(event.pointerId);
    });
    canvas.addEventListener('pointermove', (event) => {
        if (!drag) return;
        const { px, py } = point(event);
        x = drag.x + px - drag.px;
        y = drag.y + py - drag.py;
        draw(canvas, 1);
    });
    const endDrag = () => {
        drag = null;
    };
    canvas.addEventListener('pointerup', endDrag);
    canvas.addEventListener('pointercancel', endDrag);

    canvas.addEventListener('wheel', (event) => {
        event.preventDefault();
        const next = Number(zoom.value) * (event.deltaY < 0 ? 1.08 : 1 / 1.08);
        zoom.value = String(Math.min(Number(zoom.max), Math.max(Number(zoom.min), next)));
        draw(canvas, 1);
    }, { passive: false });

    canvas.addEventListener('keydown', (event) => {
        const step = event.shiftKey ? 20 : 5;
        const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
        if (!moves[event.key]) return;
        event.preventDefault();
        x += moves[event.key][0];
        y += moves[event.key][1];
        draw(canvas, 1);
    });

    dialog.querySelector('[data-crop-reset]')?.addEventListener('click', reset);

    dialog.querySelector('[data-crop-apply]')?.addEventListener('click', () => {
        const output = document.createElement('canvas');
        output.height = OUTPUT_HEIGHT;
        output.width = Math.round(OUTPUT_HEIGHT * ratio);
        draw(output, output.width / canvas.width);
        output.toBlob((blob) => {
            if (blob) {
                const files = new DataTransfer();
                files.items.add(new File([blob], `${sourceName}.png`, { type: 'image/png' }));
                input.files = files.files;
            }
            close();
            forward();
        }, 'image/png');
    });

    // Cancel (button or Escape) drops the chosen file so nothing uncropped is uploaded by surprise.
    dialog.addEventListener('cancel', () => {
        input.value = '';
    });
    dialog.querySelector('[data-crop-cancel]')?.addEventListener('click', () => {
        input.value = '';
        close();
        forward();
    });
    dialog.addEventListener('close', () => {
        if (image) {
            close();
            forward();
        }
    });
})();
