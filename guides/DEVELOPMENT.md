# Panduan pengembangan

Berlaku untuk kode baru dan kode lama yang sedang disentuh. Perubahan gaya pada kode lama dilakukan bersama perubahan perilaku yang teruji.

## Penamaan

| Jenis | Konvensi | Contoh |
| --- | --- | --- |
| Class | PascalCase sesuai namespace `App\...` | `UpdateProfileService` |
| Controller | Domain + `Controller` | `UserController` |
| Model | Entitas + `Model` | `UserModel` |
| Service | Aksi/proses + `Service` | `ResetPasswordService` |
| Repository | Entitas + `Repository` | `UserRepository` |
| Interface jika perlu | Kontrak + `Interface` | `UserRepositoryInterface` |
| Filter | Tujuan + `Filter` | `RoleFilter` |
| Method dan variabel | camelCase, nama jelas | `findActiveByUsername`, `$userId` |
| Method repository | Kata kerja + scope | `findOwnedOrder`, `findManageableUser`, `lockForApply` |
| Trait | Kemampuan, diawali kata kerja | `ResolvesCurrentUser`, `SearchesBuilder` |
| Helper | File `{topik}_helper.php`, fungsi snake_case | `date_helper.php` → `format_wib()` |
| View Cell | Komponen + `Cell` di `app/Cells/` | `PageHeaderCell` |
| View | snake_case dalam folder fitur | `users/edit.php` |
| Layout dan partial | `layouts/{area}.php`, `partials/{kelompok}/{nama}.php` (kebab-case bila lebih dari satu kata) | `layouts/dashboard.php`, `partials/feedback/field-error.php` |
| Class CSS | Prefiks area, kebab-case | `dash-plan-card`, `nfc-toast` |
| Token CSS | `--nfc-{jenis}-{nama}` | `--nfc-color-primary`, `--nfc-radius-md` |
| Nama route | `domain.aksi` | `users.update` |

Gunakan `declare(strict_types=1);`, tipe parameter/return yang akurat, dan format PSR-12 pada file baru. CI4 Model biasanya mengembalikan array; jangan mengklaim entity object tanpa mengonfigurasinya.

Prefix dipakai hanya jika ada ambiguitas. Pertahankan nama kolom lama seperti `id_user` pada mapping database, tetapi gunakan `$userId` pada API PHP baru. Nama class tidak perlu prefix `Nfc` karena namespace dan folder sudah memberi konteks. Alias filter singkat seperti `auth` dan `role` cukup.

## Aturan logika

- Route eksplisit metode HTTP dan tidak dipilih berdasarkan isi session saat konfigurasi.
- Controller memvalidasi format input; service tetap memeriksa hak pelaku dan kondisi objek agar tidak bergantung pada form.
- Query dengan `OR` dan scope role/owner harus dikelompokkan secara eksplisit.
- Gunakan query builder atau parameter binding untuk input pengguna.
- Operasi beberapa tabel menggunakan transaksi; periksa hasil simpan sebelum mengirim respons sukses.
- Password menggunakan `password_hash()`/`password_verify()`; token reset disimpan sebagai hash, memakai timestamp penuh, dan hanya dapat dipakai sekali.
- Upload memeriksa tipe, ukuran, dan nama yang dibuat server. Tulis/hapus berkas melalui satu path konfigurasi.
- Escape output sesuai konteks HTML, atribut, URL, atau JavaScript.
- Tes fitur harus menguji hasil dan batas akses, terutama guest, User, Admin, Super Admin, dan target milik pihak lain.

## Repository, helper, trait, dan view

Rincian ada di [ARCHITECTURE](ARCHITECTURE.md#aturan-repository), [ASSETS](ASSETS.md#aturan-css-reusable), dan PRD bagian 9–10 (dokumen internal). Saat menulis kode:

- **Repository** hanya untuk query ber-scope pemilik/role, transaksi lintas tabel, lock, agregat/pencarian admin, atau append-only. Constructor menerima model/koneksi opsional dengan `??=`. Tidak membaca session/request dan tidak mengembalikan query builder. CRUD satu tabel tanpa scope cukup memakai CI4 Model di service.
- **Interface** di `app/Contracts/` hanya bila ada dua implementasi nyata atau batas eksternal yang perlu dipalsukan dalam tes.
- **Helper** berisi fungsi murni untuk format. Waktu acuan dikirim sebagai argumen (`$now`), zona waktu `Asia/Jakarta` ditulis eksplisit. Tidak memanggil database, session, atau request.
- **Trait** dibuat bila perilaku yang sama dipakai di sedikitnya dua class dan bukan aturan bisnis. Hak paket, harga, dan status pembayaran tetap di service.
- **View** pada halaman baru atau yang direfactor memakai `$this->extend('layouts/…')`. Head, sidebar, footer, dan pesan flash berasal dari layout/partial, bukan disalin per halaman. Partial dan View Cell menerima data lewat argumen, tanpa query, `session()`, atau pemeriksaan role.
- **CSS** baru masuk ke file area di `public/assets/`; tidak menambah `style=""` atau `<style>` di view. Warna dan ukuran membaca token `--nfc-`. URL aset memakai `asset_url()` setelah helper tersedia, sebelumnya `base_url('assets/…')`.
- Jangan membuat repository, trait, helper, atau Cell lebih dulu "untuk nanti". Buat saat alur yang sedang dikerjakan membutuhkannya, beserta tesnya.

## Alur kerja satu perubahan

1. Baca route, controller, model, view, dan schema terkait; catat perilaku yang perlu dipertahankan.
2. Tambahkan tes untuk keberhasilan dan penolakan dengan database uji terpisah.
3. Pindahkan satu use case lengkap; jangan membuat lapisan yang hanya meneruskan panggilan tanpa aturan. Bila halamannya ikut disentuh, pindahkan ke layout baru, ganti blok flash dengan partial `feedback/`, dan pindahkan inline style halaman itu ke CSS area.
4. Jalankan `php -l` untuk file PHP yang berubah dan `composer test` jika dependensi tersedia. Service dan helper diuji di `tests/unit` dengan repository palsu; repository diuji di `tests/database` dengan fixture sintetis.
5. Periksa form, route, redirect, dan aset, termasuk screenshot sebelum/sesudah bila layout berubah. Jalankan baris terkait di gate arsitektur QA (dokumen internal). Catat langkah rollback untuk perubahan schema/URL.

## GeoLite2 untuk bahasa awal (cPanel)

Pengunjung baru yang belum memilih bahasa mendapat bahasa dari negaranya (PRD LANG-03): Indonesia → IND, negara lain → ENG. Hosting cPanel tidak mengirim header negara tepercaya, jadi aplikasi membaca berkas lokal **GeoLite2 Country** dengan `App\Libraries\MaxMindReader` (tanpa paket Composer). Bila berkas tidak ada atau rusak, halaman tetap terbuka dan bahasa ditebak dari browser. IP hanya dipakai untuk pencarian ini, tidak disimpan atau dicatat.

1. Buat akun gratis di MaxMind (GeoLite2), lalu buat *license key* di halaman akun.
2. Tambahkan ke `.env` privat di server (jangan disalin ke repository, contoh, atau tiket):
   ```
   locale.geoipAccountId = 'ID_AKUN'
   locale.geoipLicenseKey = 'LICENSE_KEY'
   ```
3. Jalankan sekali dari Terminal cPanel (atau SSH) di folder aplikasi: `php spark geoip:update`. Perintah mengunduh arsip resmi, mencocokkan SHA-256, mencoba satu pencarian, lalu memasang `writable/geoip/GeoLite2-Country.mmdb`. Bila gagal, database lama tetap dipakai.
4. Di **cPanel → Cron Jobs**, tambahkan pembaruan mingguan (MaxMind memperbarui GeoLite2 dua kali seminggu dan lisensinya mewajibkan data dijaga tetap baru), misalnya Rabu 03.17:
   ```
   17 3 * * 3 cd /home/USER/PATH-APLIKASI && /usr/local/bin/php spark geoip:update >/dev/null 2>&1
   ```
   Sesuaikan path PHP dengan versi PHP yang dipakai situs (lihat *MultiPHP Manager*). Cron statistik harian juga dipasang di sini: `php spark analytics:purge`.
5. Pastikan `writable/` tidak dapat diakses lewat web (`writable/.htaccess` sudah menolak semua akses) dan berkas `.mmdb` tidak ikut repository.

Alternatif: bila situs nanti berada di belakang Cloudflare, isi `Config\Locale::$countryHeader = 'CF-IPCountry'`; header ini dipakai lebih dulu dan berkas GeoLite2 tidak diperlukan.

## Midtrans (pembayaran online)

Alur: pemilik memilih paket di **Paket & pembayaran** → aplikasi membuat tagihan (snapshot harga) dan membuka halaman Snap Midtrans → status lunas hanya berasal dari **notifikasi bertanda tangan** atau **pemeriksaan status ke server Midtrans**, bukan dari halaman kembali → masa aktif ditambahkan tepat satu kali (perpanjangan dimulai setelah periode terakhir berakhir; ganti paket berlaku periode berikutnya). Tidak ada tombol "tandai lunas"; pemberian manual tetap lewat User table dan tidak dihitung pendapatan.

1. **Harga paket** diisi Super Admin di *Master Data → Harga Paket* (IDR, durasi bulan, aktif). Tanpa harga, tombol bayar tidak muncul.
2. **`.env` privat** (jangan disalin ke repository/dokumen/chat):
   ```
   midtrans.isProduction = false          # true hanya dengan kunci produksi
   midtrans.serverKey = '...'             # rahasia
   midtrans.clientKey = '...'
   ```
   Sandbox dan produksi memakai kunci berbeda; kunci produksi hanya di `.env` server produksi.
3. **Dashboard Midtrans → Settings → Payment** (lakukan di mode Sandbox dan Production masing-masing):
   - Payment Notification URL: `https://DOMAIN/payments/midtrans/notification`
   - Finish / Unfinish / Error Redirect URL: `https://DOMAIN/dashboard/billing`
4. **Cron cPanel** untuk pemulihan (notifikasi tetap sinyal utama):
   ```
   */10 * * * * cd /home/USER/PATH-APLIKASI && /usr/local/bin/php spark payments:reconcile >/dev/null 2>&1
   17 * * * * cd /home/USER/PATH-APLIKASI && /usr/local/bin/php spark billing:notify >/dev/null 2>&1
   ```
   `payments:reconcile` juga menulis peringatan `Payments needing attention` ke log bila ada tagihan yang perlu ditinjau, macet melewati batas bayar, atau lunas tetapi belum diterapkan; daftar yang sama tampil di atas halaman **Pembayaran** admin. Order yang cek statusnya terus gagal ditunda bertahap (5 menit × 2^gagal, maksimal 6 jam). `billing:notify` mengirim email kwitansi setelah lunas dan pengingat perpanjangan H-7 serta H-1 (sekali per periode); command ini tidak mengirim apa pun sampai `email.fromEmail` dan `email.SMTPHost` diisi di `.env`.
   Tagihan yang perlu tinjauan diselesaikan Super Admin di detail pembayaran: **Berikan masa aktif** (lunas, jumlah cocok, akun sudah aktif), **Cabut masa aktif** (refund/chargeback), atau **Tandai sudah ditinjau**; setiap tindakan wajib beralasan dan tercatat di Activity Logs. Pemilik yang checkout paket lain saat masih punya tagihan terbuka: tagihan lama dibatalkan di Midtrans dulu; bila tidak bisa, checkout ditolak agar tidak ada dua tagihan yang sama-sama bisa dibayar.
5. Uji lokal: notifikasi butuh HTTPS publik. Jalankan tunnel ke server uji, misalnya `cloudflared tunnel --url http://127.0.0.1:8099`, lalu isi Notification URL sandbox dengan alamat `https://…trycloudflare.com/payments/midtrans/notification` (alamat berubah setiap tunnel dijalankan). Sesuaikan port dengan server yang benar; aplikasi lokal container `nfc-ci` menggunakan `8080`, bukan `8099`. Bayar dengan kartu uji sandbox `4811 1111 1111 1114`, CVV `123`, kedaluwarsa masa depan, OTP `112233`, atau simulator VA/QRIS di `simulator.sandbox.midtrans.com`. Jangan pernah mengirim uang sungguhan ke referensi sandbox.

### Email gagal notifikasi Midtrans

Email Midtrans `Blocked: URL … resolves to private IP address` adalah kegagalan menjangkau URL notifikasi, bukan bukti transaksi gagal dibayar. Jangan samakan `status_code: 200` di JSON transaksi dengan HTTP 200 dari aplikasi penerima. Pada pemeriksaan 2 November 2026, hostname quick tunnel lama dari laporan pemilik juga sudah `ENOTFOUND`; ini keadaan saat pemeriksaan, bukan bukti IP yang di-resolve Midtrans pada waktu email dikirim.

- Periksa **Sandbox → Settings → Payment → Payment Notification URL**, bukan hanya Finish Redirect URL. Aplikasi tidak mengirim override Notification URL pada checkout; alamat notifikasi diatur di dashboard merchant.
- Untuk uji lokal, tunnel harus tetap berjalan dan Notification URL harus memakai hostname tunnel yang sedang aktif beserta path `/payments/midtrans/notification`. Untuk penggunaan tetap, pakai domain HTTPS publik yang stabil (hosting atau named tunnel dengan DNS publik). Jangan pakai `localhost`, IP privat, host VPN-only, atau endpoint yang meminta login/Cloudflare Access/browser challenge. Jangan mematikan proteksi private-IP untuk mengakali penolakan.
- JSON rusak ke endpoint lokal harus menghasilkan 400; GET biasa dapat 404/405 karena route hanya POST. Keduanya bukan uji webhook sukses. POST yang signature-nya tidak sah harus tetap 403. Hanya notifikasi autentik yang berhasil disimpan mendapat 200; jangan mengubah handler agar selalu 200, mematikan verifikasi signature, atau memperluas pengecualian CSRF.
- Untuk transaksi lama, buka detail pembayaran sebagai Admin/Super Admin lalu **Periksa ke Midtrans**. Status diambil lewat API server dan diterapkan melalui alur idempoten yang sama; tidak perlu membuat tagihan atau membayar lagi hanya karena notifikasi gagal. Rekonsiliasi cron adalah pemulihan tambahan, bukan pengganti perbaikan URL. Status aktual transaksi harus diperiksa, bukan disimpulkan dari email saja.
- Setelah endpoint publik aktif, lakukan uji notifikasi Sandbox yang sah dan cocokkan hasil tersimpan/HTTP 200 serta tidak ada penambahan masa aktif ganda saat notifikasi berulang. Tidak perlu membuka data pembayaran/kunci gateway di log atau tiket.

Rujukan resmi: [HTTP(S) Notification / Webhooks](https://docs.midtrans.com/docs/https-notification-webhooks).
