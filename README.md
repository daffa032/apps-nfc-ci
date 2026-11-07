# NFC Digital Card (CodeIgniter 4)

Aplikasi kartu digital dengan login, pengelolaan akun, profil, galeri, template, dan halaman kartu publik. Perapian dilakukan bertahap; manajemen User/Admin, reset password, penyimpanan profil, kartu publik, dan tautan sosial sudah memiliki service dan repository sesuai kebutuhan.

## Kebutuhan dan setup lokal

- PHP yang sesuai dengan CI4 vendored, dengan `intl`, `mbstring`, dan `mysqli`. Lingkungan lokal ini sudah teruji pada PHP 8.3 di container `nfc-ci`; PHP CLI host 8.5 gagal saat memuat `CodeIgniter\I18n\Time`.
- MySQL/MariaDB dan schema aplikasi yang sesuai.
- Composer untuk dependensi pengembangan dan PHPUnit. Framework CI4 sudah ada di `system/`.

```bash
cp .env.example .env
# Isi kredensial database lokal di .env, lalu siapkan schema/data uji.
php spark serve
```

Isi `billing.whatsapp`, `billing.email`, dan (opsional) `billing.transferInstructions` di `.env` agar halaman "Paket & pembayaran" menampilkan kontak perpanjangan; tanpa nilai itu halaman meminta pengguna menghubungi admin. Jangan menaruh nomor rekening atau kontak asli di repository.

`app.baseURL` di `.env` harus sama dengan alamat yang benar-benar dibuka di browser, termasuk skema (`http`/`https`) dan subfolder. Di cPanel, gunakan URL publik aplikasi, misalnya `https://domain-anda.example/nfc/`; untuk lokal gunakan alamat server lokal. Ketidaksesuaian alamat dapat membuat cookie CSRF tidak ikut pada POST dan menghasilkan HTTP 403 saat login. Cookie CSRF dan session aplikasi ini memakai nama khusus `nfc_ci_csrf` dan `nfc_ci_session`; setelah deployment perubahan cookie atau alamat, muat ulang halaman login sebelum mengirim form.

Tabel yang dipakai antara lain `table_user`, `table_data_personal`, dan `sosial_media`. Setelah schema dasar tersedia, jalankan `php spark migrate --all`. Migration mengubah `table_user.reset_expired_at` dari DATE menjadi DATETIME, menambah `account_status` (default `enabled`) serta `session_version` (default 1) untuk penangguhan akun dan pencabutan sesi, lalu membuat tabel `plans`, `subscriptions`, dan `subscription_grants` dan mengimpor masa aktif dari `expired_date`, menambah `table_data_personal.gallery_columns` (default 1) untuk tata letak galeri kartu, lalu membuat `audit_events` (Activity Logs), kolom paket/status/urutan pada `templates` beserta empat layout kartu baru (non-aktif), `plan_price_versions` (riwayat harga paket), `card_daily_metrics`/`card_daily_visitors` (statistik Pro), dan `card_aliases` (URL username lama). Isi `analytics.secret` di `.env` privat dengan string acak panjang dan pasang cron harian `php spark analytics:purge` untuk retensi statistik (hash 30 hari, agregat 12 bulan). Kode membaca hak akses dari `subscriptions`; jalankan migration bersamaan dengan deploy kode, karena tanpa tabel itu dashboard dan kartu publik gagal (HTTP 500). Backup database sebelum migrate. Keduanya dapat di-rollback dengan `php spark migrate:rollback`. Migration menghapus token reset lama yang disimpan dalam format mentah. Siapkan backup database dan gunakan data uji terpisah dari produksi. Konfigurasikan `email.*` di `.env` sebelum menguji pengiriman reset password. Kredensial SMTP yang pernah tertulis dalam source harus dirotasi.

**Deploy bahasa kartu:** penyimpanan bahasa kartu membutuhkan kolom `table_data_personal.card_locale` dari migration `2026-10-30-090000_AddCardLocaleToProfiles.php`. Adanya file migration belum berarti schema database aplikasi sudah diperbarui. Periksa `php spark migrate:status -g default` pada database yang dipakai server. Setelah backup dan izin perubahan schema tersedia, jalankan `php spark migrate -n App -g default`; perintah ini menjalankan seluruh migration `App` yang tertunda, jadi periksa daftarnya dahulu. Pada container lokal, awali perintah dengan `docker exec nfc-ci`. Pastikan status `AddCardLocaleToProfiles` sudah memiliki tanggal/batch, lalu uji simpan bahasa lewat sesi pemilik. Tes repository memakai database uji terpisah yang menjalankan migration sendiri, bukan bukti schema aplikasi telah diperbarui.

**Aset statis:** file yang dipakai view telah tersedia di `public/assets/`. CSS login tetap di `assets/login/`, dashboard memakai Bootstrap 5 dan `assets/dashboard/css/app.css`, sedangkan kartu publik memakai `assets/cards/css/base.css` serta `dark.css`. Dependensi dashboard Bootstrap, Font Awesome, dan Simple DataTables disimpan di `public/assets/dashboard/vendor/` agar tidak bergantung pada CDN. Arahkan document root web server ke `public/`. Folder `assets/` di root masih dipertahankan sementara untuk kompatibilitas deployment lama dan berisi file demo serta dump SQL; jangan jadikan root repository sebagai document root produksi karena terdapat `.env` dan berkas internal di sana. Upload dan URL gambarnya sekarang memakai `public/uploads` secara fisik dan `/uploads/...` dari web. Deploy perubahan URL dan document root secara bersamaan di cPanel.

## Struktur

| Lokasi | Isi |
| --- | --- |
| `app/Config/Routes.php` | Route login, dashboard, akun, dan kartu publik |
| `app/Controllers/` | Endpoint HTTP; sebagian controller lama masih memuat logika bisnis yang sedang dipindahkan ke service |
| `app/Services/` | Aturan bisnis, otorisasi objek, transaksi, dan koordinasi file/email (`Auth/`, `Users/`, `Cards/`, `Uploads/`) |
| `app/Repositories/` | Query domain dengan scope kepemilikan/role dan transaksi; tidak dibuat untuk setiap tabel |
| `app/Models/` | Metadata tabel dan query sederhana melalui CI4 Model |
| `app/Filters/` | Autentikasi dan pemeriksaan akses |
| `app/Views/` | Tampilan login, dashboard, dan kartu |
| `app/Helpers/`, `app/Traits/` | Helper format murni (`asset_url`, `flash_feedback`, `remaining_days_label`) dan trait controller (`ResolvesCurrentUser`, `RespondsWithFeedback`). View Cell (`app/Cells/`) belum dibuat. Lihat [PRD bagian 10](docs/ui-ux-2026/PRD.md#10-komponen-reusable-layout-partial-css-helper-trait) |
| `app/Views/layouts/`, `app/Views/partials/` | Layout `dashboard` dan partial `dashboard/`, `feedback/`, `shared/`, `cards/`. Semua halaman dashboard memakainya; layout lama `dashboard-home.php` sudah dihapus. View login tidak diubah |
| `public/assets/` | Aset login dan dashboard yang benar-benar dipakai view |
| `assets/` | Aset legacy untuk deployment lama; bukan lokasi penambahan aset baru |
| `public/` | Front controller dan sebagian upload |
| `writable/` | Session, cache, dan log |

## Panduan

- [Arsitektur dan urutan refactor](guides/ARCHITECTURE.md)
- [Standar pengembangan](guides/DEVELOPMENT.md)
- [Penataan aset dan CSS](guides/ASSETS.md)
- [Template kartu: upload, CSS, dan desain baru](guides/TEMPLATES.md)
- [Prioritas perbaikan kode](guides/NEXT_STEPS.md)
- [Rancangan UI/UX, PRD, dan rancangan teknis 2026](docs/ui-ux-2026/README.md), termasuk pola repository dan komponen reusable
- [Instruksi Claude](CLAUDE.md)

Jalankan `php -l path/ke/file.php` untuk pemeriksaan sintaks. Untuk perintah CLI yang memuat framework pada lingkungan lokal ini, gunakan PHP 8.3 di container, misalnya `docker exec nfc-ci php spark routes`. Seluruh 11 view dashboard sudah diuji render dengan PHP 8.3 dan data contoh. Setelah dependensi pengembangan tersedia, jalankan `composer test`. Per 11 Oktober 2026 ada 95 tes unit (User/Admin, login, profil/galeri, kartu publik dan alias, langganan, template per paket, master data, audit, upload WebP, statistik). Tes request dan browser dijalankan manual terhadap DB uji terpisah; hasilnya di [QA](docs/ui-ux-2026/QA.md#hasil-pemeriksaan-aplikasi-11-oktober-2026).
