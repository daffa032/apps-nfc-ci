// Statistik and Dashboard: refresh the monthly aggregate while the page is visible (PRD AN-02).
// A failed request keeps the last numbers and says when they were fetched; it never shows 0.
(() => {
    const root = document.querySelector('[data-analytics]');
    if (!root) return;

    document.querySelector('[data-auto-submit]')?.addEventListener('change', (event) => event.target.form.submit());

    const asOf = root.querySelector('[data-as-of]');
    // Texts and month names in the page language, printed by the view (data-*).
    const text = (key, fallback) => root.dataset[key] || fallback;
    const months = text('months', 'Jan,Feb,Mar,Apr,Mei,Jun,Jul,Agu,Sep,Okt,Nov,Des').split(',');
    const formatTime = (iso) => {
        const date = new Date(iso);
        const parts = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Jakarta', day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false })
            .formatToParts(date).reduce((all, part) => ({ ...all, [part.type]: part.value }), {});
        return `${parts.day} ${months[Number(parts.month) - 1]} ${parts.year}, ${parts.hour}.${parts.minute} WIB`;
    };
    const number = (value) => new Intl.NumberFormat(document.documentElement.lang === 'en' ? 'en-GB' : 'id-ID').format(value);
    let lastAsOf = null;
    let busy = false;

    const render = (data) => {
        Object.entries(data.totals).forEach(([key, value]) => {
            const node = root.querySelector(`[data-total="${key}"]`);
            if (node && node.textContent !== number(value)) {
                node.textContent = number(value);
                window.nfcFlash?.(node);
            }
        });
        const max = Math.max(1, ...data.days.map((day) => day.views));
        data.days.forEach((day) => {
            const bar = root.querySelector(`[data-chart] [data-date="${day.date}"]`);
            if (bar) {
                bar.querySelector('[data-value]').textContent = day.views;
                const level = day.views === 0 ? 0 : Math.max(1, Math.round((day.views / max) * 20));
                bar.querySelector('.dash-chart-bar').className = `dash-chart-bar dash-bar-${level}`;
            }
            const row = root.querySelector(`[data-table] [data-date="${day.date}"]`);
            row?.querySelectorAll('[data-field]').forEach((cell) => {
                cell.textContent = day[cell.dataset.field];
            });
        });
        lastAsOf = data.as_of;
        if (asOf) asOf.textContent = text('updated', 'Diperbarui {0}').replace('{0}', formatTime(data.as_of));
    };

    const refresh = async () => {
        if (busy || document.visibilityState !== 'visible') return;
        busy = true;
        try {
            const response = await fetch(root.dataset.endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) throw new Error(String(response.status));
            render(await response.json());
        } catch {
            if (asOf) asOf.textContent = lastAsOf
                ? text('failedAt', 'Gagal memperbarui. Menampilkan data {0}.').replace('{0}', formatTime(lastAsOf))
                : text('failed', 'Gagal memperbarui. Menampilkan data terakhir yang dimuat.');
        } finally {
            busy = false;
        }
    };

    setInterval(refresh, 60000);
    document.addEventListener('visibilitychange', refresh);
    window.addEventListener('focus', refresh);
})();
