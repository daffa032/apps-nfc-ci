# Deployment dan operasional

Catatan operasional yang sebelumnya berada di README. Berisi hal-hal yang perlu diperiksa saat menyiapkan lingkungan, menjalankan migration, dan melakukan deploy ke hosting.

## Kebutuhan lingkungan

- PHP 8.3 dengan ekstensi `intl`, `mbstring`, `mysqli`, `curl`, `fileinfo`, dan `gd`.
- MySQL 8 atau MariaDB 10.5+.
- Composer untuk dependensi pengembangan. Framework CI4 sudah disertakan di `system/`.

## Konfigurasi `.env`

`app.baseURL` harus sama persis dengan alamat yang dibuka di browser, termasuk skema dan subfolder. Di cPanel gunakan URL publik aplikasi, misalnya `https://domain-anda.example/nfc/`.

Ketidaksesuaian alamat membuat cookie CSRF tidak ikut terkirim pada POST dan menghasilkan HTTP 403 saat login. Aplikasi memakai nama cookie khusus `nfc_ci_csrf` dan `nfc_ci_session`; setelah mengubah cookie atau alamat, muat ulang halaman login sebelum mengirim form.

Isi juga:

- `billing.whatsapp`, `billing.email`, dan opsional `billing.transferInstructions` agar halaman "Paket & pembayaran" menampilkan kontak perpanjangan. Tanpa nilai itu halaman hanya meminta pengguna menghubungi admin.
- `analytics.secret` dengan string acak panjang, dipakai sebagai kunci HMAC statistik.
- `email.*` sebelum menguji pengiriman reset password.

Jangan menaruh nomor rekening, kontak asli, atau kredensial di repository. Kredensial SMTP yang pernah tertulis dalam source harus dirotasi.

## Migration

Jalankan `php spark migrate --all` setelah schema dasar tersedia. Backup database lebih dulu; semua migration dapat di-rollback dengan `php spark migrate:rollback`.

Yang dilakukan migration, berurutan:

- `table_user.reset_expired_at` dari DATE menjadi DATETIME.
- `account_status` (default `enabled`) dan `session_version` (default 1) untuk penangguhan akun dan pencabutan sesi.
- Tabel `plans`, `subscriptions`, `subscription_grants`, lalu mengimpor masa aktif dari `expired_date`.
- `table_data_personal.gallery_columns` (default 1) untuk tata letak galeri.
- `audit_events` untuk Activity Logs.
- Kolom paket/status/urutan pada `templates`, beserta empat layout kartu baru dalam keadaan non-aktif.
- `plan_price_versions` (riwayat harga paket).
- `card_daily_metrics` dan `card_daily_visitors` (statistik Pro).
- `card_aliases` (URL username lama).

Migration juga menghapus token reset lama yang tersimpan dalam format mentah.

Kode membaca hak akses dari tabel `subscriptions`. Jalankan migration bersamaan dengan deploy kode — tanpa tabel itu, dashboard dan kartu publik gagal dengan HTTP 500.

### Bahasa kartu

Penyimpanan bahasa kartu membutuhkan kolom `table_data_personal.card_locale` dari migration `2026-10-30-090000_AddCardLocaleToProfiles.php`.

Adanya file migration belum berarti schema database sudah diperbarui. Periksa dengan `php spark migrate:status -g default` pada database yang dipakai server. Setelah backup tersedia, jalankan `php spark migrate -n App -g default` — perintah ini menjalankan seluruh migration `App` yang tertunda, jadi periksa daftarnya dahulu.

Pastikan status `AddCardLocaleToProfiles` sudah memiliki tanggal dan batch, lalu uji simpan bahasa lewat sesi pemilik. Tes repository memakai database uji terpisah yang menjalankan migration sendiri; itu bukan bukti schema aplikasi sudah diperbarui.

## Cron

Pasang cron harian `php spark analytics:purge` untuk retensi statistik: hash visitor 30 hari, agregat 12 bulan.

Perintah CLI lain yang tersedia: `payments:reconcile` (rekonsiliasi status pembayaran), `billing:notify` (pengingat perpanjangan dan kirim kuitansi), dan `geoip:update` (unduh basis data GeoLite2).

## Aset statis dan document root

Arahkan document root web server ke `public/`.

- CSS login di `public/assets/login/`, dashboard memakai Bootstrap 5 dan `assets/dashboard/css/app.css`, kartu publik memakai `assets/cards/css/base.css` serta `dark.css`.
- Dependensi dashboard (Bootstrap, Font Awesome, Simple DataTables) disimpan di `public/assets/dashboard/vendor/` agar tidak bergantung pada CDN.
- Upload berada di `public/uploads` secara fisik dan diakses sebagai `/uploads/...` dari web.

Folder `assets/` di root masih dipertahankan untuk kompatibilitas deployment lama. Jangan menjadikan root repository sebagai document root produksi, karena di sana terdapat `.env` dan berkas internal. Deploy perubahan URL dan document root secara bersamaan.

## Paket deploy terakhir

Prosedur rinci, SQL upgrade, dan verifikasinya ada di [`deploy/2026-11-02/INSTALL.md`](../deploy/2026-11-02/INSTALL.md).
