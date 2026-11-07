/* Prototype only: no account, payment, analytics, NFC, or outbound-message requests. */
const root = document.documentElement;
const themeButton = document.querySelector('#theme-toggle');
const themeIcon = themeButton.querySelector('use');
const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
const updateThemeButton = () => {
    const dark = root.dataset.theme ? root.dataset.theme === 'dark' : systemTheme.matches;
    themeButton.setAttribute('aria-pressed', String(dark));
    themeButton.setAttribute('aria-label', dark ? 'Gunakan tema terang' : 'Gunakan tema gelap');
    themeIcon.setAttribute('href', dark ? '#i-sun' : '#i-moon');
};
try {
    const savedTheme = localStorage.getItem('nfc-landing-prototype-theme');
    if (savedTheme === 'light' || savedTheme === 'dark') root.dataset.theme = savedTheme;
} catch { /* The page works without browser storage. */ }
updateThemeButton();
systemTheme.addEventListener('change', updateThemeButton);
themeButton.addEventListener('click', () => {
    const dark = root.dataset.theme ? root.dataset.theme === 'dark' : systemTheme.matches;
    root.dataset.theme = dark ? 'light' : 'dark';
    try { localStorage.setItem('nfc-landing-prototype-theme', root.dataset.theme); } catch { /* Optional preference. */ }
    updateThemeButton();
});

const menuButton = document.querySelector('#menu-toggle');
const navigation = document.querySelector('#navigasi');
const closeMenu = () => {
    navigation.classList.remove('is-open');
    menuButton.setAttribute('aria-expanded', 'false');
    menuButton.setAttribute('aria-label', 'Buka navigasi');
};
menuButton.addEventListener('click', () => {
    const open = navigation.classList.toggle('is-open');
    menuButton.setAttribute('aria-expanded', String(open));
    menuButton.setAttribute('aria-label', open ? 'Tutup navigasi' : 'Buka navigasi');
});
navigation.addEventListener('click', (event) => {
    if (event.target.closest('a')) closeMenu();
});
document.addEventListener('click', (event) => {
    if (!event.target.closest('.land-header')) closeMenu();
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && navigation.classList.contains('is-open')) {
        closeMenu();
        menuButton.focus();
    }
});
window.matchMedia('(min-width: 801px)').addEventListener('change', (event) => {
    if (event.matches) closeMenu();
});

const previewDialog = document.querySelector('#preview-dialog');
const consultDialog = document.querySelector('#consult-dialog');
const loginDialog = document.querySelector('#login-dialog');
const templateNames = { classic: 'Klasik', aurora: 'Aurora', rona: 'Rona', ledger: 'Ledger' };
let currentTemplate = 'classic';
let previewOpener = null;
let consultOpener = null;
const openPreview = (design, opener) => {
    if (!Object.hasOwn(templateNames, design)) return;
    currentTemplate = design;
    previewOpener = opener;
    document.querySelector('#preview-title').textContent = templateNames[design];
    document.querySelector('#preview-card').dataset.design = design;
    document.querySelector('#sample-feedback').textContent = '';
    document.querySelector('#sample-save').textContent = 'Coba simpan kontak';
    previewDialog.showModal();
};
document.querySelectorAll('[data-preview]').forEach((button) => {
    button.addEventListener('click', () => openPreview(button.dataset.preview, button));
});
document.querySelectorAll('[data-filter]').forEach((button) => {
    button.addEventListener('click', () => {
        const filter = button.dataset.filter;
        document.querySelectorAll('[data-filter]').forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
        document.querySelectorAll('.land-template').forEach((item) => { item.hidden = filter !== 'all' && item.dataset.plan !== filter; });
    });
});
document.querySelectorAll('.land-dialog').forEach((dialog) => {
    dialog.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (event) => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
    });
});
previewDialog.addEventListener('close', () => previewOpener?.focus());
consultDialog.addEventListener('close', () => consultOpener?.focus());
document.querySelector('#sample-save').addEventListener('click', () => {
    document.querySelector('#sample-feedback').textContent = 'Simulasi selesai. Pada kartu asli, tombol ini menawarkan file kontak (vCard). Tidak ada kontak yang diunduh atau tersimpan dari contoh ini.';
});

const channels = {
    nfc: { description: 'Dekatkan media NFC yang sudah diatur ke ponsel penerima yang mendukung.', label: 'Simulasikan tap NFC' },
    qr: { description: 'Penerima memindai QR untuk membuka URL kartu. Halaman berbagi QR tersedia pada paket Pro.', label: 'Lihat hasil membuka QR' },
    link: { description: 'Kirim tautan kartu lewat chat, email, atau bio. Penerima membukanya melalui browser.', label: 'Lihat hasil membuka tautan' },
};
const demoOpen = document.querySelector('#demo-open');
document.querySelectorAll('[data-channel]').forEach((button) => {
    button.addEventListener('click', () => {
        const channel = channels[button.dataset.channel];
        if (!channel) return;
        document.querySelectorAll('[data-channel]').forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
        document.querySelector('#demo-description').textContent = channel.description;
        demoOpen.firstChild.textContent = `${channel.label} `;
    });
});
demoOpen.addEventListener('click', () => openPreview('classic', demoOpen));

const planSelect = document.querySelector('#consult-plan');
const messageField = document.querySelector('#consult-message');
const copyStatus = document.querySelector('#copy-status');
const updateMessage = () => {
    const subject = planSelect.value === 'Belum menentukan' ? 'pilihan kartu digital yang sesuai kebutuhan saya' : planSelect.value === 'NFC fisik' ? 'ketersediaan dan biaya media NFC fisik' : `paket ${planSelect.value}`;
    messageField.value = `Halo, saya ingin berkonsultasi tentang ${subject}. Mohon informasi fitur, harga, masa aktif, dan langkah pemesanannya. Terima kasih.`;
    copyStatus.textContent = 'Pesan hanya disiapkan di perangkat ini. Tidak dikirim atau disimpan ke server.';
};
const openConsult = (plan, opener) => {
    consultOpener = opener;
    const selected = [...planSelect.options].some((option) => option.value === plan) ? plan : 'Belum menentukan';
    planSelect.value = selected;
    updateMessage();
    consultDialog.showModal();
};
planSelect.addEventListener('change', updateMessage);
document.querySelectorAll('[data-consult]').forEach((button) => button.addEventListener('click', () => openConsult(button.dataset.consult, button)));
document.querySelector('#preview-consult').addEventListener('click', () => {
    const opener = previewOpener;
    previewDialog.close();
    openConsult(currentTemplate === 'classic' ? 'Basic' : 'Pro', opener);
});
document.querySelector('#copy-message').addEventListener('click', async () => {
    try {
        await navigator.clipboard.writeText(messageField.value);
        copyStatus.textContent = 'Pesan disalin. Belum dikirim ke siapa pun.';
    } catch {
        messageField.focus();
        messageField.select();
        copyStatus.textContent = 'Salin otomatis tidak tersedia. Pesan sudah dipilih; gunakan Salin dari perangkat Anda.';
    }
});
document.querySelectorAll('[data-login]').forEach((button) => button.addEventListener('click', () => loginDialog.showModal()));
