// Payments page live view (PRD AD-01): the list updates by itself, no "check with Midtrans" click.
// - Poll: GET payments/live?since=<marker>. When nothing changed the server answers in a few bytes
//   without running the list query; only a changed marker brings new HTML for the regions.
//   Every 5 s while things change, slowing to 30 s after a few quiet minutes (adaptive).
// - Sync (fallback when webhooks cannot reach the server): POST payments/sync at most once a minute
//   per browser; the server also allows only one run per minute for all admins together.
// Both pause while the tab is hidden or the switch is off, and resume at once when shown again.
(() => {
    const bar = document.querySelector('[data-payments-live]');
    if (!bar || typeof fetch !== 'function') return;

    const status = bar.querySelector('[data-live-status]');
    const toggle = bar.querySelector('[data-live-toggle]');
    const regions = {};
    document.querySelectorAll('[data-live-region]').forEach((node) => { regions[node.dataset.liveRegion] = node; });
    const text = (key, fallback) => bar.dataset[key] || fallback;
    const time = (date) => date.toLocaleTimeString(document.documentElement.lang || 'id', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

    const FAST = 5000;
    const SLOW = 30000;
    const QUIET_BEFORE_SLOW = 12; // unchanged polls (~1 min at 5 s) before slowing down
    const SYNC_EVERY = 60000;
    let marker = '';
    let csrf = bar.dataset.csrfHash;
    let busy = false;
    let lastSync = 0;
    let quiet = 0;
    let failures = 0;
    let timer = null;

    const enabled = () => toggle.checked && document.visibilityState === 'visible';

    const setStatus = (message, state) => {
        status.textContent = message;
        bar.dataset.state = state;
    };

    const flash = (node) => {
        node.classList.remove('dash-live-flash');
        void node.offsetWidth; // restart the highlight animation
        node.classList.add('dash-live-flash');
    };

    // One sync per minute across this admin's open tabs (localStorage), so two tabs do not both POST.
    const claimSync = () => {
        const now = Date.now();
        try {
            const last = Number(localStorage.getItem('nfcPaymentsSync') || 0);
            if (now - last < SYNC_EVERY) return false;
            localStorage.setItem('nfcPaymentsSync', String(now));
        } catch {
            if (now - lastSync < SYNC_EVERY) return false;
        }
        lastSync = now;

        return true;
    };

    const sync = async () => {
        if (!claimSync()) return;
        try {
            await fetch(bar.dataset.syncUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', [bar.dataset.csrfHeader]: csrf },
            });
        } catch {
            // The view refresh still runs; the next minute retries the sync.
        }
    };

    const refresh = async () => {
        if (busy || !enabled()) return;
        busy = true;
        try {
            await sync();
            const url = new URL(bar.dataset.liveUrl, window.location.href);
            if (marker) url.searchParams.set('since', marker);
            const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(String(response.status));
            const data = await response.json();
            if (data.csrf) csrf = data.csrf;
            const first = marker === '';
            marker = data.marker;
            if (data.changed && !first) {
                Object.entries(data.html || {}).forEach(([key, html]) => {
                    const node = regions[key];
                    if (node && node.innerHTML.trim() !== html.trim()) {
                        node.innerHTML = html;
                        flash(node);
                        node.querySelectorAll('.dash-number').forEach((number) => window.nfcFlash?.(number));
                    }
                });
                quiet = 0;
                setStatus(text('textNew', 'Ada perubahan, daftar diperbarui {0}').replace('{0}', time(new Date())), 'changed');
            } else {
                quiet += 1;
                setStatus(text('textUpdated', 'Live · diperbarui {0}').replace('{0}', time(new Date())), 'live');
            }
            failures = 0;
        } catch {
            failures += 1;
            setStatus(text('textFailed', 'Gagal memperbarui, mencoba lagi…'), 'failed');
        } finally {
            busy = false;
            schedule();
        }
    };

    const schedule = () => {
        clearTimeout(timer);
        if (!enabled()) {
            setStatus(text('textPaused', 'Live dijeda'), 'paused');
            return;
        }
        // Failures back off (10 s, 20 s, … 60 s); a quiet page slows to SLOW; changes keep it FAST.
        const delay = failures > 0 ? Math.min(60000, FAST * 2 ** Math.min(failures, 4)) : (quiet >= QUIET_BEFORE_SLOW ? SLOW : FAST);
        timer = setTimeout(refresh, delay);
    };

    toggle.addEventListener('change', () => {
        quiet = 0;
        toggle.checked ? refresh() : schedule();
    });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            quiet = 0;
            refresh();
        } else {
            schedule();
        }
    });
    refresh();
})();
