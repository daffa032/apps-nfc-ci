# NFC — Paket pembaruan 2 November 2026 (revisi 2)

Paket ini memperbarui kode aplikasi yang sudah terpasang, bukan instalasi database dari nol. Dasar perbandingan SQL: schema pada dump produksi terbaru yang disediakan, `qrcode_system (1).sql` (MariaDB 10.5.25), dengan 22 migration App sampai `AddPreferredLocaleToUsers`. Perbedaan migration yang diperlukan adalah `AddCardLocaleToProfiles`.

## Isi berkas

- `nfc-project-2026-11-02-r2.zip`: kode aplikasi terbaru (`app`, framework lokal `system`, aset publik dan salinan legacy, front controller, panduan, dan tes). Top-level folder di ZIP adalah `nfc/`.
- `upgrade-production.sql`: upgrade tambahan untuk database produksi yang SUDAH ADA. Tidak memuat baris akun/profil/pembayaran dan tidak membuat ulang tabel. Revisi 2 mengubah query verifikasi terakhir dari `information_schema.COLUMNS` menjadi `SHOW COLUMNS`; bagian mutasi tidak berubah.
- `verify-production.sql`: pemeriksaan baca-saja untuk database aktif, kolom bahasa kartu, dan catatan migration. Gunakan ini lebih dahulu bila import versi awal melaporkan error #1109 pada query terakhir.
- `INSTALL.md`: petunjuk ini. Juga ada di dalam ZIP pada `nfc/deploy/2026-11-02/INSTALL.md`.
- `release-manifest.json` dan `SHA256SUMS.txt`: daftar file dan checksum paket.

ZIP sengaja tidak menyertakan `.env`, kredensial, backup/dump produksi, upload pengguna, session, cache, log, debugbar, database GeoIP, konfigurasi IDE/agent, atau ZIP lama. Struktur folder writable/upload disediakan kosong beserta file pelindung. **Jangan menghapus atau mengganti file produksi yang dikecualikan tersebut.** Tidak ada file pribadi dari dump produksi yang dimasukkan ke paket.

## Perubahan aplikasi yang disertakan

- Bahasa dashboard/navbar terpisah dari bahasa kartu publik, dengan form simpan bahasa kartu sendiri di Personal Info. Profil yang belum memilih menggunakan bahasa Indonesia.
- Kolom tautan kartu pada tabel User.
- Favicon QR terbaru.
- White/Black: ikon kontak/sosial di tengah, tombol sosial ringkas, tooltip utuh, tanpa pembesaran hover 20%.
- Kode integrasi Midtrans dan pemulihan status pembayaran yang ada pada proyek saat paket dibuat. Pengaturan merchant/domain di dashboard Midtrans tidak tersimpan di ZIP.

## 1. Backup dan maintenance

1. Unduh backup database produksi dan backup file hosting ke lokasi privat di luar web root. Pastikan backup dapat dipulihkan.
2. Catat document root, versi PHP, cron, `.env` produksi, serta lokasi gambar/aset pengguna. Jangan menyalinnya ke folder aset publik atau chat.
3. Aktifkan maintenance yang tetap memungkinkan webhook pembayaran diproses atau dijadwalkan ulang; jangan deploy bersamaan dengan perubahan database lain. Jangan menganggap pembaruan schema dapat dibatalkan dengan transaksi: `ALTER TABLE` melakukan implicit commit.
4. Uji di staging dengan data sintetis terlebih dahulu. Dump yang dipakai untuk membandingkan schema bukan izin untuk menimpa database produksi.

## 2. Import SQL pada database produksi yang sudah ada

Di cPanel/phpMyAdmin:

1. Klik kembali **database aplikasi produksi yang benar** di sidebar phpMyAdmin, lalu buka tab Import pada database tersebut. Jangan pilih `information_schema`, `mysql`, `performance_schema`, atau `sys`. Script tidak berisi `CREATE DATABASE` atau `USE`, sehingga pemilihan database wajib benar. Header dump yang disediakan menyebut `qrcode_system`; gunakan nama aktual di hosting bila memiliki prefix cPanel.
2. Pilih **Import**, unggah HANYA `upgrade-production.sql` dari paket ini, lalu jalankan. Jangan mengimport ulang dump lama atau file schema pengujian.
3. Periksa tidak ada error. Hasil akhir harus menunjukkan:
   - `table_data_personal.card_locale`: `varchar(5)`, nullable.
   - Tepat satu catatan migration `2026-10-30-090000`, class `App\Database\Migrations\AddCardLocaleToProfiles`, group `default`, namespace `App`.
4. Script menambahkan kolom hanya bila belum ada, tidak menulis ulang nilai profil, dan menghindari duplikasi ledger saat diimport ulang. Jalankan tanpa import paralel. Jika kolom sudah ada tetapi tipe/nullability berbeda, berhenti dan cocokkan schema; jangan menganggap hasil import sukses hanya dari jumlah query.
5. **Jangan menjalankan rollback migration setelah import sebagai langkah rutin.** Rollback akan menghapus kolom dan pilihan bahasa yang sudah tersimpan.

Alternatif jika memiliki Terminal/SSH: sesudah kode terbaru tersedia, jalankan `php spark migrate:status -g default`, tinjau semua migration tertunda, lalu `php spark migrate -n App -g default` dengan PHP yang cocok. Ini ALTERNATIF terhadap import SQL, bukan langkah tambahan yang wajib. SQL mencatat identitas migration CI4 agar deploy berikutnya tidak mencoba menambah kolom yang sama lagi.

### Jika import awal menampilkan #1109 pada `information_schema.migrations`

Pesan ini berarti query yang gagal dijalankan dengan database aktif `information_schema`, bukan database aplikasi. Pada versi awal script, query verifikasi kolom membaca langsung `information_schema.COLUMNS` sebelum query tabel `migrations`; konteks tampilan phpMyAdmin mungkin berpindah saat menampilkan hasil. Laporan error pada SELECT terakhir tidak cukup untuk memastikan apakah penambahan kolom dan pencatatan migration sebelumnya sudah berhasil.

1. Jangan rollback, menghapus tabel/kolom, atau mengimport dump produksi lama.
2. Klik kembali database aplikasi di sidebar, buka SQL, lalu jalankan `verify-production.sql` revisi 2. File ini hanya `SELECT` dan `SHOW`, tidak mengubah data.
3. `current_database` harus database aplikasi. Harus ada satu kolom `card_locale` bertipe `varchar(5)` dengan `Null = YES`, serta satu baris migration dengan identitas yang disebutkan di atas. Bila keduanya ada, **tidak perlu mengimport ulang upgrade**; lanjutkan pemeriksaan aplikasi.
4. Jika kolom atau ledger belum ada, setelah backup dan memastikan database benar, import `upgrade-production.sql` revisi 2. Kolom/nilai yang sudah ada dipertahankan; ledger yang benar tidak diduplikasi. Bila tipe kolom berbeda atau ada lebih dari satu baris ledger, berhenti dan periksa schema sebelum melanjutkan.
5. Bila error masih menyebut `information_schema`, jangan membuat tabel `migrations` di sana. Pilih ulang database aplikasi dan jalankan pemeriksaan dari tab SQL database tersebut.

Perbaikan ini hanya menyentuh berkas SQL/petunjuk, bukan kode runtime PHP/CSS. Jika kode ZIP pertama sudah terpasang, tidak perlu upload ulang seluruh kode hanya untuk memperbaiki query verifikasi ini.

## 3. Pasang kode ZIP

1. Ekstrak ZIP **di folder staging/privat**, bukan langsung di atas aplikasi hidup. ZIP berisi folder `nfc/`; jangan membuat URL ganda `/nfc/nfc`.
2. Cocokkan struktur instalasi hosting sekarang. Untuk konfigurasi subfolder yang ada, folder aplikasi berisi `app`, `system`, `public`, `assets`, `writable`, dan `index.php`.
3. Merge kode dari folder `nfc/` ke instalasi yang sesuai. Pertahankan `.env`, upload/gambar/aset pengguna, `writable/geoip`, dan konfigurasi khusus hosting. Jangan menghapus seluruh folder hosting lalu menggantinya dengan ZIP karena upload pribadi tidak dibawa paket.
4. Jangan menimpa `.htaccess` khusus hosting secara membabi buta. Paket membawa aturan proyek saat ini; root `.htaccess` menggunakan `RewriteBase /nfc/`. Bandingkan dengan aturan existing, HTTPS, deny rules, dan PHP handler cPanel sebelum merge.
5. Document root paling aman mengarah ke `public/`; jika instalasi lama melayani root proyek, pertahankan penolakan akses ke `.env`, `app`, `system`, `writable`, `tests`, `deploy`, dan file internal/backup. Jangan meletakkan SQL atau ZIP pada folder yang dapat diunduh publik. URL `/assets/…` harus sesuai document root yang benar. Kedua salinan aset di ZIP sudah disamakan dari sumber publik terbaru.
6. Upload baru berada di `public/uploads`. Pertahankan seluruh gambar produksi pada lokasi yang dipakai server. Jangan memindahkan/menghapus upload lama tanpa memastikan URL gambar; root `.htaccess` proyek memetakan `/uploads/…` ke `public/uploads/…` untuk kompatibilitas.
7. Pastikan `writable` beserta `cache`, `logs`, `session`, `uploads`, dan `geoip` dapat ditulis oleh user PHP. Jangan memakai permission 777. Bila perlu, bersihkan hanya cache aplikasi dengan cara yang disetujui; jangan menghapus session/log/upload untuk memasang paket.
8. Gunakan PHP 8.3 yang sudah teruji di lokal, dengan `intl`, `mbstring`, `mysqli`, `curl`, `fileinfo`, `gd`, dan ekstensi lain yang dipakai hosting. Framework lokal `system/` disertakan; pertahankan dependensi vendor produksi bila deployment memilikinya. Jangan menjalankan `composer update` di produksi sebagai bagian pembaruan ini.

## 4. Konfigurasi produksi (jaga tetap privat)

Pertahankan `.env` hosting, jangan menggantinya dengan `.env.example`. Periksa di server:

```ini
CI_ENVIRONMENT = production
app.baseURL = 'https://qr-code.id/nfc/'
```

Database, email, analytics secret, kunci Midtrans, dan kredensial lain tetap memakai nilai produksi yang sah. Sandbox dan Production tidak boleh tertukar. `.env.example` hanyalah contoh lokal dan tidak diaktifkan oleh ZIP. Jangan mengubah kunci atau mode gateway tanpa rencana pengujian.

Midtrans, pada mode merchant yang sesuai:

- Payment Notification URL: `https://qr-code.id/nfc/payments/midtrans/notification`
- Finish/Unfinish/Error Redirect URL: `https://qr-code.id/nfc/dashboard/billing`
- Recurring dan account-linking tidak dipakai alur pembayaran saat ini; biarkan tidak dikonfigurasi.
- Jangan lagi menunjuk quick tunnel yang sudah tidak aktif. Mengunggah ZIP tidak otomatis mengubah pengaturan di dashboard merchant.
- Pertahankan cron `payments:reconcile` dan `billing:notify` yang sudah disetujui. Jangan menjalankan ulang checkout untuk memulihkan notifikasi yang hilang; gunakan pemeriksaan status terverifikasi.

## 5. Pemeriksaan setelah deploy

- Buka login; tampilan tetap sama dan favicon baru muncul.
- Login sebagai pemilik; Personal Info → pilih Indonesia/English → simpan, lalu reload dan periksa pilihan tetap tersimpan.
- Ganti bahasa navbar dashboard; bahasa kartu publik tidak ikut berubah. Periksa juga preview dan halaman share.
- White/Black: ikon center, tooltip tidak terpotong, tombol tidak membesar. Hard refresh/cache browser bila CSS lama masih tampil.
- Admin/Super Admin: kolom tautan kartu setiap user benar dan dapat dibuka.
- Profil/galeri/logo/latar yang sudah ada masih termuat dari upload produksi.
- Uji notifikasi Midtrans Sandbox yang sah di lingkungan yang sesuai: status tersimpan dan endpoint memberi HTTP 200; pengiriman berulang tidak memperpanjang dua kali. GET biasa atau JSON tanpa signature bukan uji webhook sukses dan tidak boleh dipaksa 200.
- Untuk order lama yang belum sinkron, periksa detail pembayaran → **Periksa ke Midtrans**, tanpa membayar ulang.
- Log tidak mengandung error `Unknown column 'card_locale'` setelah simpan. Jangan menyalin log/payload pribadi ke ruang publik.

## Verifikasi paket dan batas pengujian

Perbaikan ikon/hover telah diuji Chromium pada White/Black, lebar 320/390/1280 px, hover/fokus keyboard, reduced/default motion. Sebelum pengemasan, seluruh suite unit yang terlihat container lulus 217 tes/1112 assertion pada PHP 8.3 (`--no-coverage tests/unit`). Tes pembayaran/render terfokus sebelumnya juga lulus 20 tes/100 assertion. Lihat QA proyek untuk rincian dan batasnya.

SQL dibandingkan dengan schema produksi yang disediakan. **Pengujian import script final ke MySQL/MariaDB belum selesai karena izin eksekusi/sinkronisasi container diblokir.** Percobaan awal membaca versi script lama dari container dan gagal sintaks pada MySQL; file paket memakai versi kompatibel dengan prepared conditional statement, tetapi tidak diklaim sudah lulus import. Tes regresi untuk import pertama, reimport, preservasi data sintetis, dan ledger CI4 ada pada `tests/database/ProductionUpgradeSqlTest.php`; jalankan di database uji terpisah sebelum produksi. Revisi 2 telah diperiksa statis: query verifikasi akhir memakai tabel aplikasi, file recovery hanya SELECT/SHOW, dan seluruh statement mutasi sama dengan versi pertama. PHP lint tes baru lulus, tetapi PHPUnit revisi 2/import aktual/phpMyAdmin hosting belum diuji karena SQL yang terbaca container berbeda dari source. Tidak ada SQL dijalankan pada database produksi oleh assistant atau pembuatan paket ini.

## Rollback

Jika kode bermasalah, pulihkan kode dari backup yang cocok, sambil mempertahankan `.env` dan upload terbaru. Kolom `card_locale` nullable dapat ditinggalkan saat rollback kode; jangan drop karena menghapus pilihan pengguna. Pemulihan seluruh database dapat menghilangkan transaksi baru sejak backup, jadi putuskan dengan pemilik dan hentikan penulisan sebelum restore. Jangan memakai rollback otomatis semua migration untuk masalah CSS atau file.
