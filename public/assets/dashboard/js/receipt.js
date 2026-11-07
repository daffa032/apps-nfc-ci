// Payment receipt: the print button opens the browser's print dialog (save as PDF there).
document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
