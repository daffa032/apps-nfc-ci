# Penataan aset dan CSS

Panduan ini berasal dari referensi aset pada view dan isi folder `assets/`, bukan dokumen lama di `docs/`.

## Keputusan struktur

```text
public/
  assets/
    login/       CSS, ikon, gambar, dan JS login
    dashboard/   CSS layout dan JS dashboard
    cards/       CSS kartu publik (dasar dan tema gelap)
    shared/      hanya komponen yang dipakai sedikitnya dua area (rencana: css/tokens.css, css/feedback.css)
  uploads/       file publik yang telah divalidasi
app/Views/       HTML/PHP untuk render halaman
writable/        file privat dan runtime
```

CSS login dan dashboard **tetap terpisah**. Keduanya punya layout, komponen, dan aturan responsif berbeda; menggabungkan seluruh CSS hanya memperbesar payload dan menambah konflik selector. Jika ada token warna, font, atau komponen yang benar-benar sama, keluarkan bagian kecil itu ke `shared/` dan muat sebelum CSS khusus halaman. Urutan yang diinginkan: library UI yang dipilih → shared → CSS halaman. Saat ini CSS login dan dashboard masih paket tema lama, sehingga pemecahan selector dilakukan sesudah pemeriksaan visual.

Gunakan `base_url('assets/login/...')`, `base_url('assets/dashboard/...')`, atau `base_url('assets/cards/...')` dalam view. URL itu sesuai ketika document root mengarah ke `public/`, termasuk instalasi pada subpath. Jangan gunakan path relatif seperti `src="assets/..."` pada route bersarang. Setelah helper `asset_url()` tersedia, view memakai `asset_url('dashboard/css/app.css')` yang membungkus `base_url()` dan menambahkan versi `filemtime`, menggantikan perhitungan versi inline di layout.

## Aturan CSS reusable

Rancangan lengkap ada di PRD bagian 10.2 (dokumen internal) dan urutan muat di TECHNICAL (dokumen internal). Per 10 Oktober 2026 `public/assets/shared/` berisi `css/tokens.css`, `css/feedback.css`, dan `js/feedback.js`, dimuat oleh `layouts/dashboard`. Salinannya ada di `assets/shared/` untuk deployment lama. Login dan kartu belum memuatnya.

```text
public/assets/
  shared/css/tokens.css      variabel :root: warna, radius, spasi, z-index, durasi animasi
  shared/css/feedback.css    toast, pesan field, focus ring (dashboard dan kartu; login tidak memakai, keputusan pemilik)
  dashboard/css/app.css      komponen dashboard di atas SB Admin/Bootstrap 5
  dashboard/css/pages/*.css  CSS khusus halaman (billing, analytics, master)
  login/css/…                tetap terpisah dari dashboard
  cards/css/layouts.css      dasar semua kartu (frame, slot, 4 layout)
  cards/css/classic.css      kartu White/Black (digital_card/classic.php)
  cards/css/pro.css          desain Pro
```

| Aturan | Ketentuan |
| --- | --- |
| Token | Warna, radius, spasi, dan durasi ditulis sebagai custom property berprefiks `--nfc-` di `tokens.css`. Nilai warna mengikuti palet Coffra (terang dan `[data-theme="dark"]`), lihat MOTION.md (dokumen internal). Komponen baru membaca token, bukan nilai hex langsung. |
| Prefiks class | `dash-`, `auth-`, `card-`, dan `nfc-` untuk shared, agar tidak bentrok dengan Bootstrap/SB Admin. Class Bootstrap tetap dipakai untuk grid dan utilitas. Class lama seperti `dashboard-welcome` dipertahankan sampai halamannya direfactor. |
| Inline style | Tidak menambah `style=""` atau `<style>` di view. Inline style lama dipindah ke CSS area saat halamannya direfactor. Nilai dinamis (misalnya galeri 1/2 kolom) dikirim sebagai class atau `data-*`. |
| Aset per halaman | Halaman pada layout baru mendaftarkan CSS/JS di section `styles`/`scripts`; layout tidak memeriksa nama `$page` untuk memilih aset. |
| Urutan muat dashboard | `dashboard/js/theme-init.js` (di `<head>`, sebelum CSS) → section `styles` halaman (CSS vendor) → `styles.css` → Font Awesome → `shared/css/fonts.css` → `shared/css/tokens.css` → `shared/css/feedback.css` → `dashboard/css/app.css` → `dashboard/css/theme.css`. Script layout di akhir body: Bootstrap → `scripts.js` → `feedback.js` → `lang-switch.js` → `theme.js` → `motion.js` → section `scripts` halaman. CSS vendor halaman wajib sebelum tema; bila dimuat sesudahnya, Simple DataTables menimpa gaya tabel SB Admin. Login dan kartu memuat file shared hanya bila komponennya dipakai. |
| Gerak dan tema | Animasi dibungkus `prefers-reduced-motion`. Tema kartu di `cards/css/themes/`, bukan skin dashboard. |

Pola token mengikuti gagasan `@theme` di `apps-mutugading`, tetapi tetap CSS biasa tanpa Tailwind atau build step.

## Hasil audit aset

- Folder `assets/` legacy memiliki 98 file, termasuk halaman HTML demo, dua file PHP contoh kartu, banyak salinan Bootstrap/Font Awesome, dan `qrcode_system.sql`. File tersebut tidak disalin semuanya ke folder publik.
- `public/assets/` sekarang berisi CSS/JS/gambar/font yang dirujuk view login dan dashboard. Path aset lokal yang dipakai view tersedia di sana.
- Dua file CSS login merujuk `bg2.jpg` yang tidak tersedia di sumber. Salinan di `public/assets/` memakai warna latar pengganti agar tidak memicu 404.
- Layout login sebelumnya memuat script dashboard dan Bootstrap dari beberapa versi. Script dashboard telah dilepas; JavaScript login kini dimuat sekali dari layout dengan bundle Bootstrap 4 yang sesuai CSS lokal.
- Layout dashboard sebelumnya memuat dua script chart demo tanpa elemen chart dan memakai API Chart.js lama. Script chart demo dan dependensinya tidak dimuat lagi. Plugin jQuery DataTables yang tidak dipakai juga dilepas dari page view; tabel tetap memakai Simple-DataTables dari layout dashboard.
- Dashboard kini memakai Bootstrap 5 yang sudah ada di `styles.css` dan satu bundle JS di layout. CSS aplikasi dipindah dari `<style>` layout ke `public/assets/dashboard/css/app.css`; script/CSS Bootstrap 4 pada dashboard dihapus dan atribut modal dimigrasikan. Interaksi profil memakai `profile-interactions.js` tanpa jQuery; file ini dimuat hanya pada halaman profil. Simple DataTables dimuat hanya pada dua halaman tabel.
- Bundle Bootstrap 5.2.3, Font Awesome Free 6.3.0, dan Simple DataTables 7.1.2 disimpan di `public/assets/dashboard/vendor/` beserta lisensinya. Layout dashboard memuat file lokal tersebut, termasuk font ikon lewat path relatif dari CSS. Salinan `assets/dashboard/vendor/` tersedia selama deployment lama masih dilayani dari root repository.
- Komponen Bootstrap 4 `jumbotron` pada halaman utama diganti `dashboard-welcome`, sedangkan breadcrumb halaman utama dan tabel pengguna memakai `dashboard-breadcrumb` di `app.css`. Utility lama `font-weight-light` dan `rounded-lg` diganti padanan Bootstrap 5. Tampilan ini dicocokkan dengan screenshot produksi; tetap periksa ulang di browser setelah deploy.
- Semua menu dashboard memakai footer bersama. Halaman Templates memakai grid/kartu Bootstrap 5 dengan preview gambar utuh, penanda pilihan yang jelas, dan CSS khusus; `select` dan input file yang terdampak sudah memakai pola Bootstrap 5. CSS dashboard hanya memberi aturan khusus pada halaman yang membutuhkannya.
- CSS kartu publik dipindah dari view ke `public/assets/cards/css/`. Per 28 Oktober 2026 kartu White/Black (`index-white.php`/`index-dark.php` di tabel `templates`) dirender `digital_card/classic.php` di atas frame dan slot bersama dengan `classic.css`; `base.css`, `dark.css`, view lama, serta CDN Bootstrap 4/Font Awesome 5/Font Awesome Kit dihapus. Script Bootstrap yang tidak dipakai di kartu dihapus. Tampilan kedua tema tetap perlu diperiksa pada browser nyata.
- Salinan sementara `assets/dashboard/css/app.css`, `assets/dashboard/js/profile-interactions.js`, dan `assets/cards/css/{base,dark}.css` tersedia untuk instalasi cPanel yang masih melayani folder root. `public/assets/` tetap sumber utama; saat mengubah aset sebelum cutover, sinkronkan salinan tersebut. Hapus salinan lama setelah document root `public/` teruji.
- Aturan Apache pada `assets/.htaccess` menolak akses HTTP ke dump, PHP, dan halaman demo; `public/uploads/.htaccess` menolak eksekusi script/HTML pada upload. Konfigurasi Nginx memerlukan aturan setara karena tidak membaca `.htaccess`.
- Upload profil dan galeri sekarang menyimpan file ke `ROOTPATH/public/uploads/` melalui `PublicImageStorage`, menerima JPEG/PNG/WebP maksimal 5 MB, dan memberi nama file acak. Hapus galeri membatasi gambar ke profil pengguna serta memakai path yang sama. URL gambar di view memakai `/uploads/...` saat document root mengarah ke `public/`. Dua gambar galeri lama lokal telah disalin dari `uploads/multiple_images/` ke `public/uploads/multiple_images/`; salin data lama lain sebelum cutover produksi.

## Urutan lanjutan

1. Pastikan web server staging memakai document root `public/` dan `app.baseURL` sesuai subpath yang sebenarnya. Uji login, dashboard, dan kartu publik; lalu hentikan serving folder `assets/` legacy.
2. Uji modal aktivasi, notifikasi, dropdown, form profil, pratinjau gambar, serta request AJAX profil di browser nyata, termasuk respons token CSRF kedaluwarsa.
3. Uji CSS kartu putih/gelap (`classic.css`) pada desktop dan ponsel; CSS kartu hanya dimuat oleh view kartu sehingga selektornya tidak mengenai dashboard/login.
4. Saat cutover, deploy perubahan URL `/uploads/...` bersama document root `public/`, salin gambar lama ke `public/uploads/`, uji gambar lama dan baru, dan terapkan larangan eksekusi script pada konfigurasi web server. Tambahkan pemeriksaan dimensi/re-encode gambar bila profil upload perlu proteksi lebih ketat.
5. Setelah deployment seluruhnya memakai `public/`, pindahkan dump SQL ke lokasi privat yang ditentukan dan bersihkan `assets/` legacy serta halaman demo. Jangan menghapusnya sebelum kompatibilitas deployment diverifikasi.
6. Saat alur pertama dipindah ke layout baru, buat `shared/css/tokens.css` dan `feedback.css`, pindahkan inline style halaman itu, dan muat aset lewat `asset_url()`. Sebelum cutover `public/`, sinkronkan file shared baru ke salinan `assets/` legacy bila deployment lama masih aktif.

Tidak perlu menambah Vite/npm hanya untuk memindahkan tiga tema statis ini. Build pipeline masuk akal ketika CSS/JS mulai mempunyai modul, bundling, atau dependensi yang perlu dikelola.
