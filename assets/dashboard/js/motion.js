// Dashboard motion (docs/ui-ux-2026/MOTION.md 4.3, 8–10). Purely visual and optional:
// pages work the same when this file is missing, and nothing runs under prefers-reduced-motion.
// Every class added here is removed when its animation ends, so no transform stays behind
// (a leftover transform would turn <main> into the containing block of fixed elements).
(() => {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

    const play = (node, className, index = 0) => {
        if (index) node.style.setProperty('--nfc-i', String(index));
        node.classList.add(className);
        // Children's animations (e.g. chart bars) bubble here too, so only our own end cleans up.
        const done = (event) => {
            if (event.target !== node) return;
            node.removeEventListener('animationend', done);
            node.removeEventListener('animationcancel', done);
            node.classList.remove(className);
            node.style.removeProperty('--nfc-i');
        };
        node.addEventListener('animationend', done);
        node.addEventListener('animationcancel', done);
    };

    // Lets other scripts highlight a value they just updated: window.nfcFlash?.(node).
    window.nfcFlash = (node) => {
        if (!node || reduced.matches) return;
        node.classList.remove('nfc-flash');
        void node.offsetWidth; // restart when the same value changes twice in a row
        play(node, 'nfc-flash');
    };

    // Closing animation for <dialog class="dash-dialog"> (stage 3). The real close() still runs, just
    // after a short exit, so the close event, returnValue and focus return work as before. While the
    // exit plays the dialog is still open: a second close() is ignored, showModal() cancels the exit
    // and keeps the dialog open (no close event, so the page's new content stays), and a native
    // close (e.g. a forced Escape) ends the exit at once.
    if (window.HTMLDialogElement && !HTMLDialogElement.prototype.nfcAnimatedClose) {
        const proto = HTMLDialogElement.prototype;
        const nativeClose = proto.close;
        const nativeShowModal = proto.showModal;
        const exits = new WeakMap();

        // Ends a running exit; closes the dialog for real unless the exit was cancelled by a reopen.
        const finish = (dialog, close = true) => {
            const exit = exits.get(dialog);
            if (!exit) return false;
            exits.delete(dialog);
            clearTimeout(exit.timer);
            dialog.removeEventListener('animationend', exit.done);
            dialog.classList.remove('nfc-closing');
            if (close && dialog.open) nativeClose.apply(dialog, exit.args);
            return true;
        };

        proto.nfcAnimatedClose = true;
        proto.close = function (...args) {
            if (!this.open || !this.classList.contains('dash-dialog') || reduced.matches) {
                if (exits.has(this)) finish(this);
                return nativeClose.apply(this, args);
            }
            if (exits.has(this)) return undefined;
            const done = (event) => {
                if (event.target === this) finish(this);
            };
            exits.set(this, { args, done, timer: setTimeout(() => finish(this), 300) });
            this.classList.add('nfc-closing');
            this.addEventListener('animationend', done);
            return undefined;
        };
        proto.showModal = function (...args) {
            // Reopened while closing: it never closed, so stay open and replay the opening animation.
            if (finish(this, false) && this.open) {
                this.style.animation = 'none';
                void this.offsetWidth;
                this.style.removeProperty('animation');
                return undefined;
            }
            return nativeShowModal.apply(this, args);
        };

        // Escape: let page listeners see the cancel as usual, then close through the animation.
        document.addEventListener('cancel', (event) => {
            const dialog = event.target;
            if (!(dialog instanceof HTMLDialogElement) || !dialog.classList.contains('dash-dialog') || reduced.matches) return;
            event.preventDefault();
            dialog.close();
        }, true);
        // A close that bypassed close() (the browser can force Escape) clears the exit state.
        document.addEventListener('close', (event) => {
            if (event.target instanceof HTMLDialogElement) finish(event.target, false);
        }, true);
    }

    if (reduced.matches) return;

    // Sidebar: a pill slides from the link that was active on the previous page to the new one
    // (Coffra's panel pill). Only the pill moves; links keep their own highlight underneath.
    const menu = document.querySelector('#sidenavAccordion .sb-sidenav-menu');
    const current = menu?.querySelector('.nav-link[aria-current="page"]');
    if (menu && current) {
        const pill = document.createElement('span');
        pill.className = 'nfc-nav-pill';
        pill.setAttribute('aria-hidden', 'true');
        menu.prepend(pill);
        const box = (link) => {
            const m = menu.getBoundingClientRect();
            const r = link.getBoundingClientRect();
            return { top: r.top - m.top + menu.scrollTop, left: r.left - m.left, width: r.width, height: r.height };
        };
        const place = (b) => {
            pill.style.setProperty('--nfc-pill-y', `${b.top}px`);
            pill.style.setProperty('--nfc-pill-x', `${b.left}px`);
            pill.style.setProperty('--nfc-pill-w', `${b.width}px`);
            pill.style.setProperty('--nfc-pill-h', `${b.height}px`);
        };
        let from = null;
        try {
            from = JSON.parse(sessionStorage.getItem('nfc:nav-pill') || 'null');
        } catch (e) {
            from = null;
        }
        const to = box(current);
        place(from && Number.isFinite(from.top) ? from : to);
        menu.classList.add('has-nav-pill');
        requestAnimationFrame(() => requestAnimationFrame(() => {
            pill.classList.add('is-moving');
            place(to);
        }));
        // Remember where the clicked link sits, so the next page starts the pill there.
        menu.addEventListener('click', (event) => {
            const link = event.target.closest('a.nav-link[href]:not([data-bs-toggle])');
            if (!link) return;
            try {
                sessionStorage.setItem('nfc:nav-pill', JSON.stringify(box(current)));
            } catch (e) {
                // Storage blocked: the pill simply appears in place on the next page.
            }
        });
    }

    // Page title: letters rise in one after another (Coffra splits the heading the same way).
    // Screen readers get the plain heading through aria-label; the letters are aria-hidden.
    document.querySelectorAll('main.dash-home .dash-page-head h1').forEach((title) => {
        const text = title.textContent.trim();
        if (!text || title.children.length || text.length > 80) return;
        title.setAttribute('aria-label', text);
        let i = 0;
        title.replaceChildren(...text.split(' ').flatMap((word, w) => {
            const span = document.createElement('span');
            span.className = 'nfc-word';
            span.setAttribute('aria-hidden', 'true');
            [...word].forEach((char) => {
                const letter = document.createElement('span');
                letter.className = 'nfc-char';
                letter.textContent = char;
                letter.style.setProperty('--nfc-i', String(i++));
                span.append(letter);
            });
            return w ? [document.createTextNode(' '), span] : [span];
        }));
        title.classList.add('nfc-title-in');
        // Put the plain text back once the letters have landed, so copy/paste and wrapping are normal.
        setTimeout(() => {
            title.textContent = text;
            title.removeAttribute('aria-label');
            title.classList.remove('nfc-title-in');
            if (!title.classList.length) title.removeAttribute('class');
        }, 900 + i * 22);
    });

    // Numbers count up from 0 to the value printed by the server (Coffra: 1.2 s power3.out).
    // The final text is the server's own text, so formatting such as "Rp1.250.000" stays exact.
    const countUp = (node) => {
        const text = node.textContent;
        const match = text.match(/\d[\d.,]*/);
        if (!match) return;
        const digits = match[0].replace(/[.,](?=\d{3}(\D|$))/g, '');
        const target = Number(digits.replace(',', '.'));
        if (!Number.isFinite(target) || target <= 0 || !Number.isInteger(target)) return;
        const sep = /\d\.\d{3}/.test(match[0]) ? '.' : (/\d,\d{3}/.test(match[0]) ? ',' : '');
        const format = (value) => String(Math.round(value)).replace(/\B(?=(\d{3})+(?!\d))/g, sep);
        const start = performance.now();
        const duration = 1200;
        const tick = (now) => {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - (1 - t) ** 3;
            node.textContent = text.replace(match[0], format(target * eased));
            if (t < 1) requestAnimationFrame(tick);
            else node.textContent = text;
        };
        node.textContent = text.replace(match[0], format(0));
        requestAnimationFrame(tick);
    };
    document.querySelectorAll('main.dash-home .dash-number').forEach(countUp);

    // Page entry: the first blocks of the page rise in one after another, then cards inside grids.
    const main = document.querySelector('main.dash-home');
    if (main) {
        [...main.children]
            .filter((node) => !['DIALOG', 'SCRIPT', 'TEMPLATE'].includes(node.tagName))
            .slice(0, 8)
            .forEach((node, index) => play(node, 'nfc-enter', index));

        main.querySelectorAll('.dash-theme-grid, .dash-stats, .dash-slot-grid, .dash-plan-offers').forEach((grid) => {
            [...grid.children].slice(0, 12).forEach((node, index) => play(node, 'nfc-enter', index + 2));
        });
    }

    // Statistik: bars grow one after another (the grow animation itself is in theme.css).
    document.querySelectorAll('[data-chart] .dash-chart-bar').forEach((bar, index) => {
        bar.style.setProperty('--nfc-i', String(index));
    });

    // The first invalid field (focused by shared/js/feedback.js) shakes once.
    const invalid = document.querySelector('main [aria-invalid="true"]');
    if (invalid) play(invalid, 'nfc-shake');
})();
