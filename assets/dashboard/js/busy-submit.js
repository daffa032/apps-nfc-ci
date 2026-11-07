// Disable a form's submit button while the request is in flight, to prevent double submits (PRD FB-01).
document.addEventListener('submit', (event) => {
    if (event.defaultPrevented) return;
    const button = event.target.querySelector('button[type="submit"][data-busy-label]');
    if (!button) return;
    button.textContent = button.dataset.busyLabel;
    button.disabled = true;
});
