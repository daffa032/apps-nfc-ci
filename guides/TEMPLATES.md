# Template kartu: cara kerja upload, CSS, dan membuat desain baru

Panduan ini dibaca dari kode per 28 Oktober 2026: `TemplateHtmlSanitizer`, `TemplateCatalog`, `TemplateRepository`, `digital_card/{custom,pro,frame-start}.php`, dan `public/assets/cards/css/`. Bila kode berubah, kode yang benar.

## 1. Ringkasnya

Template upload **hanya berisi susunan (struktur)**. Template tidak membawa CSS, warna, gambar, maupun script sendiri.

| Bagian | Asalnya | Bisa diubah lewat upload? |
| --- | --- | --- |
| Susunan blok (urutan, grid, panel) | File `.html` yang diupload | Ya, terbatas pada tag dan class yang diizinkan |
| Data kartu (nama, foto, kontak, sosial, galeri, tombol) | Partial `app/Views/digital_card/slots/*.php` lewat placeholder `{{...}}` | Tidak. Template hanya menentukan *letaknya* |
| Warna, font, radius, jarak, bentuk tombol | File CSS di `public/assets/cards/css/` | **Tidak.** Perlu rilis CSS oleh developer |
| Logo merek dan latar header | File milik profil (sudah divalidasi) lewat `{{brand_logo}}`, `{{header_background}}` | Hanya letaknya |

Inilah alasan file upload ditolak bila berisi `style=""`, `<style>`, `<link>`, `<img>`, `<a>`, `<script>`, atau atribut selain `class` (PRD MA-03). Tujuannya: Super Admin bisa menyusun ulang kartu tanpa bisa menyisipkan kode, mengubah aksi tombol, atau membocorkan data.

## 2. Alur upload (Master Data → Templates)

1. Super Admin memilih file `.html` (maks 100 KB). Form langsung meminta **pratinjau** ke `POST dashboard/master/templates/preview`. Server menjalankan sanitizer dan merender kartu dengan data contoh di iframe `sandbox`. Belum ada yang disimpan.
2. Saat dikirim, `MasterDataController::createTemplate` → `TemplateAdminService::create`:
   - nama 1–32 karakter, unik (tidak membedakan huruf besar/kecil); paket `basic`/`pro`; deskripsi ≤ 160;
   - `TemplateHtmlSanitizer::normalize()` mem-parse HTML lalu **membangun ulang** HTML dari DOM. Yang disimpan adalah hasil bangun ulang, bukan file aslinya;
   - di dalam lock katalog dicek batas slot aktif (Basic/total).
3. `TemplateRepository::createUploaded` menulis:
   - baris `templates` dengan `nama_file = custom-<id>` (bukan nama file upload), dan
   - baris `template_versions` (versi 1, HTML ternormalisasi, checksum SHA-256). Versi tidak pernah diubah.
4. Saat kartu publik dibuka, `TemplateCatalog::viewFor('custom-<id>')` memilih `digital_card/custom.php` (atau `pro.php` bila HTML memakai class `tpl-pro-<desain>`). HTML versi aktif **dinormalisasi ulang setiap render**, lalu dipecah menjadi potongan HTML aman dan slot (`segments()`).
5. Template tidak pernah dihapus. Menonaktifkan template menyembunyikannya dari pilihan baru; kartu yang sudah memakainya tetap tampil.

**Ubah nama** (baru, 28 Oktober 2026): tombol *Ubah nama* di tabel membuka dialog. Yang berubah hanya `nama_template`, yaitu label di katalog pengguna. `nama_file`, versi HTML, layout, dan kartu pengguna tidak tersentuh. Nama harus unik, dan setiap perubahan tercatat di Activity Logs (`template.renamed`). Berlaku untuk template bawaan maupun upload.

## 3. Aturan file HTML

| Aturan | Nilai |
| --- | --- |
| Tag | `section article header div h2 h3 p span strong em ul li figure figcaption` |
| Atribut | hanya `class` |
| Class umum | `tpl-stack tpl-grid tpl-hero tpl-panel tpl-center tpl-divider tpl-title` |
| Class desain Pro | `tpl-pro-<desain>`, `tpl-pro-<desain>-hero`, plus `tpl-pro-cover -brand -photo -identity -kicker -content -contact -social -gallery -actions` untuk 12 desain di `TemplateHtmlSanitizer::PRO_DESIGNS` |
| Placeholder wajib (tepat 1×) | `{{photo}} {{name}} {{company}} {{contact}} {{social}} {{gallery}} {{actions}}` |
| Placeholder opsional (maks 1×) | `{{job_title}} {{brand_logo}} {{header_background}}` |
| Placeholder blok | `photo contact social gallery actions brand_logo header_background` harus berada di dalam `section/article/header/div/figure/li` |
| Batas | 100 KB, 100 elemen, kedalaman 16, UTF-8 |

Teks biasa (misalnya judul "Hubungi saya") boleh ditulis. Teks itu di-escape dan tampil apa adanya, sama untuk semua bahasa.

Contoh minimal (sama dengan contoh di halaman Master Templates, `app/Views/digital_card/sample-template.html`):

```html
<section class="tpl-stack">
  <div class="tpl-hero tpl-stack tpl-center">
    <div>{{photo}}</div>
    <h2 class="tpl-title">{{name}}</h2>
    <p>{{company}}</p>
  </div>
  <div class="tpl-panel tpl-stack"><h3>Hubungi saya</h3><div>{{contact}}</div></div>
  <div class="tpl-panel tpl-stack"><h3>Galeri</h3><div>{{gallery}}</div></div>
  <div class="tpl-panel tpl-stack"><h3>Tautan sosial</h3><div>{{social}}</div></div>
  <div class="tpl-divider">{{actions}}</div>
</section>
```

## 4. Dari mana CSS-nya

Semua kartu publik dibuka oleh `digital_card/frame-start.php`, yang memuat CSS dan memberi `<body>` class `nc-<layout>`:

| File | Dimuat untuk | Isi |
| --- | --- | --- |
| `cards/css/layouts.css` | semua kartu | Dasar kartu dan variabel `--card-paper --card-ink --card-muted --card-accent --card-line --card-head`; komponen slot (`.nc-avatar`, `.nc-btn`, `.nc-detail`, `.nc-social`, `.nc-gallery`); 4 layout bawaan; **class `tpl-*` untuk template upload** (`.nc-template .tpl-stack`, `.tpl-panel`, dst.) |
| `cards/css/pro.css` | layout `pro-*` (body `nc-pro nc-pro-<desain>`) | Variabel `--pro-accent --pro-soft --pro-ink --pro-radius --pro-border --pro-body`, struktur `tpl-pro-*`, lalu satu blok per desain (`.nc-pro-aurora { … }`) |
| `cards/css/classic.css` | White/Black (`classic-*`) | Dua kartu lama |
| `share.css`, `photo-dialog.css`, `floating-actions.css`, `shared/css/lang-switch.css`, `brand-icons.css` | sesuai kebutuhan | Halaman share, dialog foto, tombol mengambang, bahasa, ikon X |

Jadi:
- Template upload **biasa** (class `tpl-*`) memakai tampilan netral dari `layouts.css`, dengan body `nc-custom`. Warna mengikuti `--card-*` default (putih, aksen biru). Semua template upload biasa terlihat "sekeluarga"; bedanya hanya susunan.
- Template upload yang memakai class **`tpl-pro-<desain>`** otomatis dirender oleh `pro.php` dengan body `nc-pro nc-pro-<desain>`, sehingga warnanya ikut desain Pro tersebut di `pro.css`. Ini cara membuat variasi susunan dengan palet desain yang sudah ada tanpa rilis kode.
- CSS kartu **terpisah** dari CSS dashboard dan login. Tema Coffra dashboard tidak memengaruhi kartu publik.

## 5. Membuat desain baru

### A. Hanya susunan baru, warna yang sudah ada (tanpa developer)

1. Salin salah satu `app/Views/digital_card/pro/<desain>.html` (atau contoh di bagian 3).
2. Ubah urutan dan pembungkus blok, tambah teks judul, pilih class `tpl-*` atau `tpl-pro-<desain>` yang diizinkan.
3. Upload di Master Templates, cek pratinjau, simpan sebagai Nonaktif, buka pratinjau di halaman Templates, lalu aktifkan.

### B. Warna, pola, atau bentuk baru (perlu rilis developer)

Karena upload tidak boleh membawa CSS, desain dengan tampilan baru ditambahkan sebagai **desain Pro bawaan**:

1. **HTML sumber**: buat `docs/ui-ux-2026/templates/pro/<desain>.html` dan salin ke `app/Views/digital_card/pro/<desain>.html`. Struktur mengikuti desain lain:
   ```html
   <section class="tpl-pro-<desain>">
     <header class="tpl-pro-<desain>-hero">
       <div class="tpl-pro-cover">{{header_background}}</div>
       <div class="tpl-pro-photo">{{photo}}</div>
       <div class="tpl-pro-identity">
         <div class="tpl-pro-brand">{{brand_logo}}</div>
         <p class="tpl-pro-kicker">{{company}}</p>
         <h2>{{name}}</h2>
         <p>{{job_title}}</p>
       </div>
     </header>
     <article class="tpl-pro-content">
       <div class="tpl-pro-contact">{{contact}}</div>
       <div class="tpl-pro-social">{{social}}</div>
       <div class="tpl-pro-gallery">{{gallery}}</div>
       <div class="tpl-pro-actions">{{actions}}</div>
     </article>
   </section>
   ```
2. **Allowlist**: tambah `<desain>` ke `TemplateHtmlSanitizer::PRO_DESIGNS`.
3. **Katalog**: tambah `'layout-pro-<desain>' => ['digital_card/pro', 'pro-<desain>']` di `TemplateCatalog::VIEWS`, bentuk logo di `LOGO_FRAMES`, dan masukkan ke `HEADER_BACKGROUND` bila desain punya latar header.
4. **CSS**: tambah blok di `public/assets/cards/css/pro.css` (dan salinan `assets/cards/css/pro.css`):
   ```css
   /* <Desain>: satu kalimat ciri desain. */
   .nc-pro-<desain> {
       --pro-accent: #…;   /* tombol utama, garis aksen */
       --pro-soft: #…;     /* latar hero */
       --pro-ink: #…;      /* teks */
       --pro-radius: 12px; /* bentuk tombol */
       --pro-border: #…;
       --pro-body: #…;     /* latar isi kartu */
   }
   .nc-pro-<desain> header[class$="-hero"] { /* komposisi hero: grid, gradien, pola */ }
   .nc-pro-<desain> .tpl-pro-photo .nc-avatar { /* bentuk foto */ }
   ```
   Ubah warna lewat variabel `--pro-*` dulu; tulis selector khusus hanya untuk komposisi. Jangan ubah markup slot. Pastikan tombol tetap ≥ 44 px, kontras teks ≥ 4.5:1, dan ada aturan `prefers-reduced-motion` bila memakai gerak.
5. **Preview katalog**: swatch `.dash-swatch-pro-<desain>` di `public/assets/dashboard/css/app.css` (variabel `--swatch-hero/-photo/-button`), atau gambar di `public/uploads/templates/`.
6. **Teks**: deskripsi `Templates.desc_pro_<desain>` di `app/Language/{id,en}/Templates.php`.
7. **Data**: migration baru yang menambah baris `templates` (`nama_file = layout-pro-<desain>`, `plan_code = pro`, nonaktif). Contohnya `2026-10-26-100000_AddOmbakSiluetBingkaiTemplates.php`.
8. **Tes**: jalankan `composer test`, cek pratinjau di Master Templates, kartu publik di 320/390/1280 px, foto kosong (monogram), tanpa logo/latar, dan kedua bahasa.

Setelah dirilis, Super Admin juga bisa mengupload variasi susunan desain itu (cara A) karena class-nya sudah ada di allowlist.

## 6. Yang tidak boleh

- Membuka `style`, `<style>`, URL, `<img>`, atau atribut lain di sanitizer agar admin bisa "mewarnai sendiri". Itu membuka jalan XSS dan penyalahgunaan tombol kontak.
- Menyalin HTML/CSS/gambar template pihak ketiga (PRD bagian 12).
- Mengubah `nama_file` atau HTML versi lama. Buat versi atau desain baru supaya kartu yang sudah memakai template tidak berubah tanpa sengaja.
