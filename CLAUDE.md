# Instruksi Claude untuk aplikasi NFC

Baca [README.md](README.md), [guides/ARCHITECTURE.md](guides/ARCHITECTURE.md), dan [guides/DEVELOPMENT.md](guides/DEVELOPMENT.md) sebelum mengubah aplikasi. Untuk fitur paket, pembayaran, statistik, atau perapian UI, baca juga [PRD](docs/ui-ux-2026/PRD.md), [TECHNICAL](docs/ui-ux-2026/TECHNICAL.md), dan [QA](docs/ui-ux-2026/QA.md).

Ini aplikasi CodeIgniter 4 dengan framework lokal di `system/`, bukan Laravel. Proyek Laravel `apps-mutugading` adalah referensi pemisahan tanggung jawab saja. Jangan memakai Eloquent, Artisan, service provider Laravel, Blade, atau paket Laravel di repository ini.

Dokumen lama di `docs/` bukan sumber kebenaran; pengecualiannya `docs/ui-ux-2026/` (rancangan aktif) dan `docs/LOGIC-REVIEW.md` (temuan beserta status per 10 Oktober 2026), yang tetap harus dicocokkan dengan kode. Verifikasi perilaku dari route, controller, model, view, konfigurasi, dan schema database yang sah. Bila schema belum tersedia, tulis asumsi dengan jelas.

Prioritaskan role per request, otorisasi target, CSRF, metode mutasi, reset password, validasi upload, dan konsistensi path. Refactor satu alur vertikal bersama tes perilaku. Jangan membuat repository/interface untuk setiap model tanpa kebutuhan nyata.

Pola kode mengikuti PRD bagian 9–10 dan ARCHITECTURE:

- Rantai Route → Filter → Controller → Service → Repository → CI4 Model. Repository hanya untuk query ber-scope pemilik/role, transaksi lintas tabel, lock, agregat/pencarian admin, atau append-only. Contohnya `AdminRepository` dan `SocialLinkRepository`. Repository tidak membaca session/request dan tidak mengembalikan query builder.
- Helper (`app/Helpers/`) berisi fungsi format murni tanpa database/session. Trait (`app/Traits/`) hanya untuk perilaku berulang yang bukan aturan bisnis. Hak paket, harga, dan status pembayaran tetap di service.
- View baru memakai layout CI4 (`extend`/`section`), partial, dan View Cell. Partial tidak menjalankan query, `session()`, atau pemeriksaan role. Halaman lama dipindah dari `dashboard-home.php` per alur, bukan sekaligus, dengan tampilan yang tetap sama.
- Jangan membuat helper, trait, Cell, atau repository "untuk nanti"; buat bersama alur yang membutuhkannya beserta tes. Setiap alur menjalankan baris terkait di gate arsitektur QA (ARC-01–06, RU-01–06).
- `apps-mutugading` hanya referensi pembagian tanggung jawab. Pola interface + `Eloquent…Repository`, provider binding, dan trait model Laravel tidak disalin.
- ID dokumen: `LOG-xx` adalah temuan di LOGIC-REVIEW; `AUD-xx` adalah kebutuhan audit log di PRD.
- Halaman login, lupa sandi, dan reset sandi tidak diubah tampilannya (keputusan pemilik). Jangan menerapkan layout baru, prototype login, atau feedback bersama ke `app/Views/login/**`. Perbaikan backend wajib menjaga render tetap identik.

Aset baru diletakkan di `public/assets/` menurut halaman yang memakainya. Ikuti [guides/ASSETS.md](guides/ASSETS.md). Jangan menambah PHP, dump SQL, atau file demo ke folder aset publik. CSS login dan dashboard tetap terpisah; hanya komponen desain yang benar-benar digunakan bersama yang layak menjadi CSS bersama di `public/assets/shared/css/` (token `--nfc-` dan feedback). Jangan menambah `style=""` atau `<style>` di view.

Jangan menyalin `.env`, kredensial, dump produksi, atau data pribadi ke contoh/dokumentasi. Jangan mengubah `system/` kecuali tugas eksplisit meminta perubahan framework.
