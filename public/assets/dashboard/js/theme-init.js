// Applies the saved light/dark theme before first paint (loaded in <head>, not deferred).
// No saved choice follows the system setting. Storage may be blocked; the page then stays light/system.
(() => {
    let saved = null;
    try {
        saved = localStorage.getItem('nfc:theme');
    } catch (e) {
        saved = null;
    }
    const dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
})();
