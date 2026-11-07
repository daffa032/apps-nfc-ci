// Light/dark switch in the dashboard header (partials/dashboard/topnav.php, button[data-theme-toggle]).
// The new theme is revealed with a circle from the button (View Transition API) when the browser
// supports it and the user has not asked for reduced motion; otherwise it switches at once.
(() => {
    const root = document.documentElement;
    const button = document.querySelector('[data-theme-toggle]');
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

    const stored = () => {
        try {
            return localStorage.getItem('nfc:theme');
        } catch (e) {
            return null;
        }
    };

    const sync = () => {
        if (!button) return;
        const dark = root.dataset.theme === 'dark';
        button.setAttribute('aria-pressed', dark ? 'true' : 'false');
        button.title = dark ? button.dataset.labelLight : button.dataset.labelDark;
    };

    const apply = (theme) => {
        root.dataset.theme = theme;
        sync();
    };

    // Follow the system only while the user has not picked a theme.
    system.addEventListener('change', (event) => {
        if (!stored()) apply(event.matches ? 'dark' : 'light');
    });

    sync();
    if (!button) return;

    button.addEventListener('click', () => {
        const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
        try {
            localStorage.setItem('nfc:theme', next);
        } catch (e) {
            // Private mode: the choice lasts for this page only.
        }

        if (!document.startViewTransition || reduced.matches) {
            apply(next);
            return;
        }

        const box = button.getBoundingClientRect();
        const x = box.left + box.width / 2;
        const y = box.top + box.height / 2;
        const radius = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y));
        document.startViewTransition(() => apply(next)).ready.then(() => {
            root.animate(
                { clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${radius}px at ${x}px ${y}px)`] },
                { duration: 450, easing: 'cubic-bezier(.65, 0, .35, 1)', pseudoElement: '::view-transition-new(root)' }
            );
        }).catch(() => {});
    });
})();
