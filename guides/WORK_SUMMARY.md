# Rangkuman pekerjaan — aplikasi NFC (CodeIgniter 4)

Diperbarui 12 Oktober 2026. Dokumen ini merangkum pekerjaan yang sudah selesai, pengujian yang tercatat, dan pekerjaan tersisa. Statusnya dicocokkan dengan kode pada tanggal tersebut. Bila berbeda dengan dokumen lain, kode yang menjadi acuan. Rencana kebutuhan ada di PRD (dokumen internal), dan urutan fase ada di [NEXT_STEPS](NEXT_STEPS.md).

Legenda status: **Selesai** = sudah ada di kode dan diuji lokal · **Sebagian** = berjalan dengan batasan yang dicatat · **Belum** = belum dikerjakan.

## 1. Lingkungan dan arsitektur

- CodeIgniter 4.5.3 dengan framework lokal di `system/` (tidak diubah), PHP 8.3 di container Docker `nfc-ci`, dan MySQL `qrcode_system`. Aplikasi lokal berjalan di `http://localhost:8080`.
- Rantai kode: Route → Filter → Controller → Service → Repository → CI4 Model. Repository hanya dibuat untuk query ber-scope pemilik/role, transaksi lintas tabel, lock, agregat admin, atau data append-only.
- Trait `ResolvesCurrentUser` dan `RespondsWithFeedback`; helper murni `asset_url`, `date`, `feedback`, dan lainnya; layout `layouts/dashboard` beserta partial `partials/dashboard/*` dan `partials/feedback/*`.
- Aset baru ada di `public/assets/{shared,dashboard,cards}/`. Folder `assets/` lama masih menjadi cermin selama document root belum dipindah ke `public/`.
- Tampilan login, lupa sandi, dan reset sandi **tidak diubah** (keputusan pemilik).

## 2. Dokumentasi

| Dokumen | Isi pembaruan |
| --- | --- |
| README, CLAUDE.md, guides/ARCHITECTURE, DEVELOPMENT, ASSETS, NEXT_STEPS | Pola repository/layout/partial/helper/trait CI4, aturan aset, fase kerja |
| docs/ui-ux-2026/PRD, TECHNICAL, QA, AUDIT-DESIGN | Kebutuhan dan gate QA (ARC-01–06, RU-01–06), audit log `AUD-xx` |
| docs/LOGIC-REVIEW.md | Status temuan `LOG-xx` |

PRD 1.2 (12 Oktober 2026) menambah bagian 12: sembilan template Pro, aset merek, pratinjau langsung, halaman share QR, dan dua bahasa. Semua kebutuhan bagian 12 **belum** diimplementasikan.

## 3. Fitur yang sudah dikerjakan

### Akun, paket, dan akses — Selesai
- Periode subscription (`subscriptions`, `subscription_grants`) menjadi sumber status aktif. Intervalnya UTC `[starts_at, ends_at)` dan tampil dalam WIB. `expired_date` hanya disinkronkan untuk rollback.
- Admin/Super Admin selalu aktif dengan hak setara Pro.
- Akun kedaluwarsa hanya mendapat akses dashboard dan billing yang terbatas. Kartu publik yang tidak aktif ditolak.
- Status `suspended` punya halaman khusus. `session_version` mencabut sesi setelah suspend, ganti sandi, atau ganti role. Super Admin terakhir dilindungi.
- Grant/revoke manual oleh Super Admin wajib berisi alasan (3–255 karakter) dan tercatat di ledger.
- Reset password dibatasi frekuensinya; validasi upload diperketat.

### Profil dan galeri — Selesai
- Form profil dikelompokkan, dengan pesan error di dekat field (`aria-describedby`) dan fokus ke field pertama yang salah.
- Foto profil/galeri divalidasi dari isinya, diputar sesuai EXIF, diubah ukurannya, lalu dikonversi ke WebP q82 di `public/uploads/`.
- Caption galeri hingga 255 karakter; pilihan 1/2 foto per baris.
- **12 Oktober:** caption kosong atau berisi spasi tidak lagi merender kotak/garis caption pada template putih, gelap, maupun semua layout baru. Caption yang berisi teks tetap tampil. Sudah dicek dengan Playwright pada kartu `dannylim` (5 foto, 0 caption kosong, gambar asli tidak diubah).

### Dashboard — Selesai
- Halaman Dashboard, Profil, Templates, User, Admin, Setting, Billing, Statistik, Master Data, dan Activity Logs memakai layout baru.
- Header, sidebar (menu aktif, grup lipat), dan footer tetap mengikuti desain lama; footer tidak menutupi konten.
- **12 Oktober:** notifikasi sukses/gagal kini berupa **toast di pojok kanan bawah**, menggantikan modal. Toast sukses (`role=status`) hilang setelah 6 detik; toast gagal (`role=alert`) bertahan sampai ditutup. Teks selalu dirender sebagai teks, bukan HTML. Field yang salah langsung mendapat fokus. Alur AJAX profil (simpan/hapus caption dan foto, simpan/hapus tautan sosial) memakai `window.nfcToast()` sebagai pengganti `alert()`. Login tidak memakai komponen ini.

### Dua bahasa IND/ENG — Sebagian (12 Oktober)
- **Kartu publik**: semua layout (termasuk template putih/gelap lama dan template unggahan) serta halaman kartu tidak aktif/suspended. Dropdown bendera ada di pojok kanan atas.
- **Dashboard (semua halaman)**: header (dropdown bendera di kiri menu akun), sidebar, toast, Dashboard, Personal Info, Templates, Statistik (termasuk grafik, tabel, dan refresh otomatis), Billing, Pengaturan akun, User/Admin table beserta form dan dialog, Master Data (Harga Paket, Templates), dan Activity Logs (label aksi/hasil). Pesan server, error per field, konfirmasi hapus, serta tanggal dan nama bulan juga mengikuti bahasa yang dipilih.
- Teks berasal dari `app/Language/{id,en}/*.php` (14 berkas per bahasa); unit test memastikan kedua bahasa punya key yang sama dan setiap aksi audit punya label. Data pengguna tidak diterjemahkan.
- Pilihan disimpan di cookie `nfc_lang` selama 1 tahun; `?lang=` di-redirect ke URL bersih, jadi URL kartu dan QR tidak berubah.
- Bahasa awal ditentukan berurutan dari: pilihan/cookie → negara dari header proxy tepercaya (`Config\Locale::$countryHeader`, misalnya `CF-IPCountry`; kosong secara default) → `Accept-Language` → Indonesia (`LocaleResolver`, `LocaleFilter`).
- **Login, lupa sandi, tautan terkirim, reset sandi** (12 Oktober): teks label, placeholder, judul, modal, pesan error/sukses, dan email reset dua bahasa (`app/Language/{id,en}/Auth.php`), dengan dropdown bendera di pojok kanan atas. Struktur, gaya, dan alur tidak berubah. Label Indonesia sengaja sama persis dengan teks lama. Dibandingkan piksel per piksel dengan screenshot sebelum perubahan (locale ID, 390/1280 px): selisih hanya di area tombol bahasa, ditambah ≤3 piksel antialias di tepi tombol login/reset pada 390 px.
- Belum: preferensi bahasa yang tersimpan di akun (sekarang cookie) dan pesan sanitizer upload HTML template (masih Indonesia).

### Badge Pro dan foto profil diperbesar — Selesai (12 Oktober, PRD CARD-01/02)
- Badge **Pro** berwarna emas/oranye dengan ikon mahkota dan teks gelap (kontras ≥5,9:1). Dipakai di Dashboard, Billing, Pengaturan akun, dan katalog Templates lewat `partials/shared/plan-badge.php`. Badge Basic tidak berubah.
- Teks "Kartu profesional" dihapus dari kartu publik.
- Foto profil di semua kartu publik (layout baru, template unggahan, putih/gelap lama) bisa diklik/disentuh untuk dibuka lebih besar. Dialog memakai foto tersimpan yang sama, punya tombol Tutup, bisa ditutup dengan Escape atau klik di luar foto, dan fokus kembali ke foto. Kartu tanpa foto, atau dengan file foto yang hilang, tidak menampilkan tombol maupun dialog.

### Katalog 15 slot dan sembilan template Pro — Selesai lokal (12 Oktober, PRD NT-01/02/05/06/07)
- Batas katalog naik dari 2 Basic / 6 total menjadi **2 Basic / 15 total**. Server menolak template aktif ke-16 dengan pesan yang jelas.
- Sembilan desain Pro (Aurora, Meridian, Atelier, Vista, Ledger, Kanvas, Halo, Rona, Sorot) dibuat sebagai layout bawaan aplikasi (`digital_card/pro.php`, `public/assets/cards/css/pro.css`). Desain mengikuti rancangan di `docs/ui-ux-2026/templates/pro/README.md`, dengan warna, bentuk tombol, latar isi, dan urutan konten berbeda per desain. Atelier/Kanvas membuka dengan galeri; Rona/Sorot memakai foto profil sebagai hero.
- Semua desain tetap memuat foto (bisa diperbesar), nama, perusahaan, tombol kontak, rincian, sosial, galeri, simpan kontak, dan bagikan. Tombol "Lihat lokasi" ada di dalam blok alamat (NT-06). Tinggi tombol minimal 46 px, tanpa overflow di 390/1280 px.
- Katalog Templates menampilkan pratinjau warna per desain dengan grid dan animasi yang sama seperti sebelumnya.
- Migrasi `2026-10-12-090000_AddNineProTemplates` menambah kesembilannya dalam keadaan Nonaktif; rollback ditolak bila masih ada kartu yang memakai desain tersebut. **Di DB lokal, kesembilannya sudah diaktifkan** (12 Oktober, setelah backup, lewat pemeriksaan slot dan audit yang sama dengan UI), sehingga katalog berisi 15/15 template aktif.
- **Sumber susunan = berkas HTML di `app/Views/digital_card/pro/`** (salinan ganda di `docs/` dihapus 7 November 2026; dokumentasi susunannya tetap di `docs/ui-ux-2026/templates/pro/README.md`). Saat dirender, berkas dilewatkan ke sanitizer yang sama dengan unggahan. Allowlist diperluas secara spesifik: class `tpl-pro-*` yang terdaftar dan placeholder opsional `job_title`, `brand_logo`, `header_background`. Berkas yang sama bisa diunggah lewat Master Templates; aplikasi mengenali desainnya, memakai tampilan Pro, dan membuka slot merek sesuai placeholder di berkas.
- Tanpa logo/latar, area logo menampilkan inisial perusahaan dan header memakai warna desain (fallback NT-03).

### Jabatan, logo/latar merek, dan pratinjau langsung — Selesai lokal (12 Oktober, PRD BR-01/BR-02/PV-01)
- **Jabatan** (opsional, maks. 100 karakter) di Personal Info untuk Basic dan Pro. Tampil di bawah nama pada semua tampilan kartu, dan hilang rapi bila kosong. Ikut masuk ke vCard (`TITLE`), bersama nama perusahaan (`ORG`).
- **Logo merek dan latar header** disimpan per pasangan profil–template (`card_brand_assets`). Tujuh desain Pro punya kedua slot, sedangkan Rona/Sorot hanya logo; POST latar langsung ke Rona/Sorot ditolak. Hanya Pro aktif yang bisa mengunggah; Basic melihat catatan dan tautan paket. Saat ganti template, kontrol menampilkan aset template tujuan, dan aset template lama kembali bila dipilih ulang. Gambar diverifikasi dari isinya lalu dikonversi ke WebP di `public/uploads/brand_assets/`. Bila simpan gagal, file baru dihapus dan aset lama tetap. Setelah downgrade, aset tidak dirender.
- **Pratinjau langsung** di Personal Info: form dan kartu sejajar 50:50 di desktop, bertumpuk di ponsel. Ketikan nama/perusahaan/jabatan serta foto, logo, dan latar yang dipilih langsung tampil di pratinjau dengan penanda "Belum disimpan". Kartu publik tetap memakai data tersimpan sampai disimpan. "Lihat kartu penuh" membuka draf yang sama; "Ganti tampilan" menuju katalog. Pratinjau `no-store`/`noindex` dan tidak dihitung sebagai kunjungan.
- Migrasi `2026-10-12-100000_AddJobTitleAndBrandAssets` sudah diterapkan ke DB lokal setelah backup.

### Halaman share QR — Selesai lokal (12 Oktober, PRD QR-01..04)
- **Tombol QR melayang** di pojok kanan bawah kartu publik Pro ("Buka QR dan bagikan", 56 px, tidak menutup konten). Kartu Basic tetap memakai tombol bagikan tautan.
- **`/username/share`**: tombol tutup kembali ke kartu, foto (atau monogram), nama, jabatan, perusahaan, QR besar, Salin tautan, Bagikan, Simpan ke Galeri, Unduh QR saja, dan panduan Tambahkan ke layar utama. Tidak ada Wallet. Halaman `no-store` dan `noindex`, serta tidak dihitung sebagai kunjungan.
- **QR berisi URL kartu kanonik saja**, tanpa token atau data kontak. Username lama di-redirect 301 ke `/username-baru/share`. Basic dan kartu tidak dikenal mendapat 404, suspended 410, expired 403, tanpa data profil.
- **QR dibuat di server** oleh `App\Libraries\QrCode` (tanpa layanan pihak ketiga dan tanpa dependensi baru). Hasilnya identik bit per bit dengan libqrencode 4.1.1 (fixture di `tests/_support/Fixtures`). Semua 8 mask dan versi 1–10 terbukti terbaca oleh decoder zxing.
- **Gambar PNG** (kartu berbagi 1080×1500 dan QR saja 1200×1200) dibuat di browser dari matriks QR yang sama. Di ponsel, gambar ditawarkan lewat share sheet; selain itu diunduh. Status sukses hanya tampil setelah browser menerima aksi. Kedua PNG terbukti terbaca kembali sebagai URL kartu.
- Belum: logo merek di tengah QR (opsional di PRD; QR polos dipakai agar selalu mudah dipindai).

### Deteksi negara dengan GeoLite2 — Selesai lokal (13 Oktober, PRD LANG-03, hosting cPanel)
- Pengunjung yang belum memilih bahasa mendapat **IND bila IP-nya dari Indonesia, ENG bila dari negara lain**. Urutan: pilihan/cookie → negara (header tepercaya bila diatur, kalau tidak berkas GeoLite2 lokal) → bahasa browser → Indonesia. IP tidak disimpan maupun dicatat.
- `App\Libraries\MaxMindReader`: pembaca format MaxMind DB tanpa dependensi baru. Diverifikasi dengan database uji resmi MaxMind (484/484 rentang negara cocok; record 24/28/32-bit, IPv4/IPv6, semua tipe data) dan fixture sintetis di `tests/_support/Fixtures`.
- Berkas tidak ada, rusak, IP privat, atau rentang tidak dikenal → halaman tetap tampil dan bahasa ditebak dari browser.
- `php spark geoip:update` mengunduh GeoLite2 Country resmi dengan akun MaxMind di `.env`, mencocokkan SHA-256, mencoba satu pencarian, lalu memasang berkas secara atomik. Bila gagal, berkas lama tetap. Panduan cPanel dan cron mingguan ada di `guides/DEVELOPMENT.md`.
- Belum dijalankan dengan akun MaxMind asli: berkas `writable/geoip/GeoLite2-Country.mmdb` belum ada, sehingga lokal masih memakai bahasa browser.

### Midtrans Snap — Selesai di sandbox (13 Oktober, PRD BI-01..03, AD-01, EX-01)
- **Checkout** di *Paket & pembayaran*: pemilik memilih Basic/Pro dan diarahkan ke halaman Snap Midtrans. Harga, durasi, dan status akun dibaca server; browser hanya mengirim kode paket dan *request key*. Tagihan menyimpan snapshot harga/versi. Klik ganda atau muat ulang memakai tagihan yang sama, dan tagihan terbuka untuk paket yang sama dilanjutkan, bukan dibuat ulang. Batas bayar 24 jam. Hanya username yang dikirim ke Midtrans (tanpa email/telepon).
- **Lunas hanya dari server**: notifikasi `POST /payments/midtrans/notification` (CSRF dikecualikan khusus URI ini, signature SHA-512 diperiksa dengan perbandingan waktu-konstan; tanda tangan salah → 403 + audit) atau pemeriksaan status ke Midtrans (tombol *Periksa status*, halaman admin, `php spark payments:reconcile`). Halaman kembali dari Midtrans hanya menampilkan "menunggu konfirmasi".
- **Tepat satu kali**: akun dan tagihan dikunci dalam satu transaksi; event dicatat dengan fingerprint unik; status hanya maju (lunas tidak turun ke pending; pembayaran terlambat setelah expire tetap dihitung); `subscription_id` unik per tagihan. Perpanjangan dimulai saat periode terakhir berakhir. Jumlah tidak cocok, refund, atau akun ditangguhkan → *perlu tinjauan*, tanpa memberi akses. Audit `payment.applied` wajib dalam transaksi yang sama; bila gagal, semua dibatalkan dan Midtrans mencoba ulang.
- **Admin → Pembayaran** (Admin & Super Admin): ringkasan lunas bulan ini (gross terverifikasi), menunggu, perlu tinjauan; filter status/periode/no. tagihan/username; detail snapshot + riwayat status; *Periksa ke Midtrans* (rate limit). Tidak ada tombol tandai lunas.
- Tabel baru `payment_orders` dan `payment_events` (migrasi `2026-10-13-090000_CreatePaymentOrders`), **sudah diterapkan ke DB lokal utama** (13 Oktober, setelah backup). Harga paket di DB lokal utama masih kosong, jadi tombol bayar belum muncul sampai diisi di Master Data.
- Tunnel sandbox (cloudflared) sekarang hanya meneruskan `POST /payments/midtrans/notification` ke aplikasi lokal utama `localhost:8080`; semua alamat lain dijawab 404, sehingga dashboard, kartu, dan debug toolbar lokal tidak terbuka ke internet.
- **Diuji**: unit (status, signature) dan integrasi MySQL (sekali terapkan saat notifikasi/status berulang, perpanjangan dimulai setelah periode berjalan, jumlah salah/akun ditangguhkan/order tak dikenal, rollback bila audit gagal, scope pemilik dan *request key* unik). End-to-end di **sandbox Midtrans** dengan DB uji sintetis lewat tunnel cloudflared: checkout → halaman Snap → bayar kartu uji → *Periksa status* → lunas dan masa aktif Pro 12 bulan ditambahkan setelah periode berjalan; notifikasi bertanda tangan sah lewat tunnel diterima (duplikat tidak menambah periode), yang dimanipulasi ditolak 403, isi rusak 400. Tidak ada uang sungguhan.
- **Riwayat pembayaran** di halaman Paket & pembayaran hanya berisi tagihan yang **lunas** (kolom Dibayar dan Metode). Tagihan yang masih menunggu ada di kotak terpisah **Menunggu pembayaran** dengan tombol *Lanjutkan pembayaran*, *Periksa status*, dan **Batalkan**; tagihan gagal/kedaluwarsa/dibatalkan tidak ditampilkan.
- **Batalkan tagihan** (untuk ganti metode bayar): dibatalkan dulu di Midtrans (`/v2/{order}/cancel`), baru ditutup lokal; bila Midtrans menolak karena sudah dibayar, status lunas diterapkan dan masa aktif ditambahkan. Gangguan jaringan tidak menutup tagihan. Diuji end-to-end di sandbox (GoPay QRIS → dibatalkan, Midtrans melaporkan `cancel`).
- Tagihan yang tidak pernah sampai ke Midtrans (gangguan jaringan saat checkout) dicoba ulang sekali, lalu ditutup otomatis saat dicek bila Midtrans tidak punya transaksinya.
- Belum: notifikasi otomatis dari dashboard Midtrans ke tunnel (perlu Anda isi Notification URL sandbox), pengujian VA/QRIS lewat simulator, dan konfigurasi produksi.

### Template kartu — Sebagian
- Enam slot aktif: 2 Basic + 4 layout tambahan. Pilihan template dibatasi menurut paket di server.
- Pratinjau di halaman Templates memakai data akun sendiri (`no-store`, `noindex`, tidak dihitung sebagai kunjungan).
- Master Templates: Super Admin dapat mengunggah HTML yang disanitasi dengan allowlist tag/class dan tujuh slot wajib. Hasilnya disimpan di `template_versions` (versi immutable + checksum SHA-256) dan disanitasi ulang setiap kali dirender. Kapasitas slot dicek dengan lock transaksi.
- Batasan: thumbnail katalog untuk template unggahan masih skematis, belum hasil render HTML (MA-02). HTML tersimpan yang dirusak akan ditolak tanpa fallback yang ramah. Belum ada fitur "unggah versi berikutnya".

### Statistik Pro — Selesai (lokal)
- `card_daily_metrics` dan `card_daily_visitors` mencatat tayangan, perkiraan pengunjung unik harian (HMAC harian, tanpa menyimpan IP/UA mentah), dan klik kontak (WhatsApp/telepon/email lewat `sendBeacon`).
- Preview pemilik/staff, HEAD, bot, dan user-agent kosong tidak dihitung. Basic tidak melihat angka dan mendapat respons JSON 403.
- Dashboard dan Statistik membaca agregat yang sama dan diperbarui otomatis setiap 60 detik.
- Retensi: hash harian 30 hari, agregat 12 bulan, dibersihkan dengan `php spark analytics:purge`.

### Audit log — Selesai
- `audit_events` bersifat append-only dan dapat dilihat di halaman Activity Logs (Super Admin, dengan filter).
- Mutasi berisiko tinggi (harga paket, status/unggah template, suspend, grant/revoke, hapus user/admin) dibatalkan jika audit gagal ditulis.
- Log tidak menyimpan sandi, token, isi HTML, URL, email, atau IP mentah.

### Username dan URL kartu — Selesai
- `card_aliases`: nama lama tetap mengarah (301) ke username baru, sehingga QR/NFC lama tetap berfungsi. Nama route reserved ditolak.

## 4. Migrasi database

Semua migrasi berikut sudah diterapkan ke database lokal `qrcode_system`. Backup dibuat sebelum penerapan di `/tmp/nfc-backup` (lokal, izin 600).

| Migrasi | Isi |
| --- | --- |
| 2026-10-02-090000 | Kedaluwarsa reset sandi memakai timestamp |
| 2026-10-11-090000 | `account_status`, `session_version` |
| 2026-10-11-100000 | `plans`, `subscriptions`, `subscription_grants` |
| 2026-10-11-110000 | Kolom tata letak galeri |
| 2026-10-11-120000 | `audit_events` |
| 2026-10-11-130000 | Paket dan status template |
| 2026-10-11-140000 | `plan_price_versions` |
| 2026-10-11-150000 | Tabel analytics kartu |
| 2026-10-11-160000 | `card_aliases` |
| 2026-10-11-170000 | Caption 30→255, hapus baris galeri yatim, FK cascade |
| 2026-10-11-180000 | Kolom tautan sosial 64→500 |
| 2026-10-11-190000 | `template_versions`, `templates.current_version_id` |
| 2026-10-12-090000 | Sembilan template Pro (nonaktif) |
| 2026-10-12-100000 | `job_title`, tabel `card_brand_assets` |

MySQL strict mode aktif. Penulisan tanggal nol legacy hanya dilonggarkan sementara di titik yang memerlukannya.

## 5. Data lokal yang dipulihkan

- 38 gambar dari `~/Downloads/uploads` disalin tanpa menimpa file yang sudah ada ke `public/uploads/` (profile, multiple_images, template). Semua referensi database terselesaikan, dan Playwright memuat 17/17 gambar.
- Secret analytics lokal ditambahkan ke `.env` (privat, tidak disalin ke dokumentasi). Hitungan statistik lokal berisi lalu lintas uji, bukan data produksi, dan belum direset.

## 6. Pengujian yang tercatat

| Jenis | Hasil terakhir | Kapan |
| --- | --- | --- |
| Unit (`tests/unit`) | **139 test, 445 assertion, lulus** | 12 Oktober 2026 |
| Unit + integrasi MySQL (`tests`) | 201 test: 199 lulus, 2 contoh SQLite dilewati | 13 Oktober 2026 (setelah batal tagihan) |
| Integrasi repository | 15 test pada DB uji sintetis `nfc_repo_test` | 11 Oktober 2026 |
| HTTP regresi | 28 cek (route, CSRF, role, upload, alias, template) | 11 Oktober 2026 |
| Browser (Playwright, Chromium) | Login 18/18 identik, shell dashboard 11 halaman, galeri, statistik, template | 11 Oktober 2026 |
| Browser toast/caption | Toast 390/1280 px: posisi kanan bawah, role, 6 detik, tutup manual, tanpa injeksi HTML, fokus field; caption `dannylim` 390/1280 px | 12 Oktober 2026 |

Semua pengujian dilakukan secara lokal. **Belum ada** validasi staging/produksi, Safari/Firefox/perangkat fisik, uji beban, maupun uji konkurensi analytics.

## 7. Pekerjaan tersisa

### Non-pembayaran (sebelum Midtrans)
1. **PRD 1.2 bagian 12**:
   - LANG-02 sisa: simpan preferensi bahasa di akun (sekarang cookie).
2. Thumbnail katalog template unggahan dirender dari HTML (MA-02).
3. Pesan error di dekat field untuk form yang belum tercakup; sidebar masih memeriksa role di dalam partial.
4. FK untuk `templates.current_version_id`; rollback migrasi template setelah ada unggahan nyata.

### Deployment
- Pindahkan document root ke `public/`. Setelah terverifikasi, baru hapus `assets/qrcode_system.sql`, `assets/digital_card/index_*.php`, dan `assets/.htaccess`.
- Jalankan migrasi staging/produksi setelah backup, gunakan secret analytics tersendiri, pasang cron `analytics:purge` dan `geoip:update` (cPanel Cron Jobs), isi akun MaxMind di `.env`, dan pakai limiter bersama bila server lebih dari satu.

### Pembayaran — produksi
- Isi harga paket nyata di Master Data, kunci produksi di `.env` server, Notification/Redirect URL di dashboard Midtrans Production, cron `payments:reconcile`, lalu migrasi `CreatePaymentOrders` setelah backup.
