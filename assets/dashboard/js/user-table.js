// User and admin tables: pass the row's user ID into the activation dialogs and confirm destructive actions.
(() => {
    const bindUserId = (modalId, inputId) => {
        document.getElementById(modalId)?.addEventListener('show.bs.modal', (event) => {
            document.getElementById(inputId).value = event.relatedTarget?.dataset.id || '';
        });
    };
    bindUserId('activateModal', 'activateUserId');
    bindUserId('addActivateModal', 'addActivateUserId');

    document.addEventListener('submit', (event) => {
        const message = event.target.dataset?.confirm;
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
})();
