// Language dropdown (partials/shared/lang-switch.php): close on Escape and outside clicks.
(() => {
    const menus = document.querySelectorAll('details.nfc-lang');
    if (!menus.length) return;

    document.addEventListener('click', (event) => {
        menus.forEach((menu) => {
            if (menu.open && !menu.contains(event.target)) menu.open = false;
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        menus.forEach((menu) => {
            if (!menu.open) return;
            menu.open = false;
            menu.querySelector('summary').focus();
        });
    });
})();
