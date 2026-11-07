// Floating "Save contact" button on every public card (partials/cards/floating-actions.php).
(() => {
    const button = document.querySelector('[data-fab="vcard"]');
    const source = document.getElementById('ncFabVcard');
    if (!button || !source) return;

    button.addEventListener('click', () => {
        const url = URL.createObjectURL(new Blob([source.textContent], { type: 'text/vcard' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = 'contact.vcf';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    });
})();
