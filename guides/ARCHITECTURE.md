# Arsitektur aplikasi NFC

Panduan ini disusun dari kode di `app/` dan prinsip pemisahan tanggung jawab di proyek Laravel `apps-mutugading`. Dokumen lama di `docs/` tidak dijadikan acuan. Tabel temuan menggambarkan kondisi sebelum refactor alur User; status terkini dicatat di bawah.

## Status implementasi

Route admin sekarang statis; filter `auth` membaca ulang role akun dari database dan `access` memeriksa argumen role. Manajemen User dan Admin memakai service/repository; pembuatan akun/profil/media sosial memakai transaksi. Admin terakhir dilindungi saat dihapus atau diturunkan role dengan penguncian baris database. Penyimpanan profil memakai `ProfileService` dan `ProfileRepository`; file baru dibersihkan jika simpan database gagal. Pengaturan akun memakai `AccountSettingsService`; reset password memakai `PasswordResetService` dengan hash token, batas 30 menit, dan konsumsi sekali pakai. Kartu publik hanya memilih view dari `TemplateCatalog`; URL, nama gambar, dan vCard diproses oleh `CardPresentation`. Pemilihan template memeriksa ID katalog dan kepemilikan profil; edit media sosial memakai `SocialLinkService` dengan daftar kanal tetap. Mutasi hapus memakai POST dan token CSRF telah dipasang pada form serta request AJAX yang ditemukan. `UserPersonalModel` memakai primary key `id_data` sesuai schema SQL yang tersedia. Perilaku database produksi belum diuji.

Aset statis yang dipakai view telah disalin ke `public/assets/`; script chart demo yang tidak punya elemen target di dashboard dihapus dari layout. Perincian CSS dan langkah perpindahan document root ada di [ASSETS.md](ASSETS.md).

## Temuan pada kode saat ini

| Area | Kondisi | Arah perbaikan |
| --- | --- | --- |
| Route | `Routes.php` memilih route admin dari session saat registrasi. | Daftarkan route secara statis; putuskan akses pada setiap request. |
| Role | `AccessFilter` menerima argumen tetapi hanya mengizinkan dua role admin tanpa membedakan level. | Hormati argumen role dan periksa otoritas terhadap target data. |
| CSRF | Alias tersedia tetapi filter global dikomentari; beberapa mutasi menggunakan GET. | Ubah mutasi ke POST/DELETE dan beri token CSRF pada form. |
| Logika | Controller membuat model langsung serta mengerjakan validasi, query, dan upload. | Pindahkan aturan bisnis ke service; query domain yang kompleks ke repository. |
| Query | `UserModel` memiliki query `orWhere()` dan `where()` tanpa grup eksplisit. | Kelompokkan kondisi agar scope role tidak bocor. |
| Relasi | `UserPersonalModel` memakai primary key `id_user`, sementara kode terkait memakai `id_data`. | Cocokkan dengan schema nyata sebelum migration atau perubahan relasi. |
| Reset password | Logika muncul di `Auth_login` dan `ResetPasswordPenggunaController`; nama kolom token belum konsisten. | Satukan alur dan uji kedaluwarsa serta sekali pakai. |
| Berkas | Aset di root; upload dan hapus memakai path berbeda (`FCPATH` dan `WRITEPATH`). | Tetapkan satu document root dan satu layanan penyimpanan. |
| Tes | Tes bisnis belum tersedia secara memadai. | Tambahkan tes fitur pada setiap alur yang diubah. |

## Pola target untuk CodeIgniter 4

```text
Route → Filter → Controller → Service → Repository → CI4 Model → Database
                                ↘ CI4 Model langsung (CRUD satu tabel tanpa scope)
                                ↘ Storage/Email/MidtransClient bila diperlukan
Controller → Layout + partial/View Cell → Response
```

- **Route:** URI dan HTTP method eksplisit; filter menjalankan autentikasi dan role saat request.
- **Controller:** ambil input, validasi format, panggil satu use case, kembalikan view/redirect/JSON. Tidak menjalankan query atau memindahkan file langsung.
- **Service:** aturan bisnis, otorisasi pada objek, transaksi, dan koordinasi data serta file/email.
- **Repository:** query domain dan scope kepemilikan yang dipakai ulang. Interface dibuat jika ada konsumen nyata yang memerlukan kontrak; CRUD sederhana boleh tetap memakai CI4 Model di service.
- **Model:** metadata tabel, `allowedFields`, dan query sederhana. CI4 Model bukan Eloquent.
- **View:** layout, partial, dan View Cell untuk presentasi dan escaping sesuai konteks; tanpa query, akses `session()`, atau keputusan hak akses.
- **Helper:** fungsi format murni (tanggal WIB, rupiah, URL aset/kartu, judul halaman, pesan field). Tanpa database, session, atau request.
- **Trait:** perilaku berulang di sedikitnya dua class yang bukan aturan bisnis, misalnya pembacaan user saat ini di controller atau pencarian berallowlist di repository.

Struktur target bertahap:

```text
app/Controllers/
app/Filters/
app/Models/
app/Services/Auth/
app/Services/Users/
app/Services/Cards/
app/Services/Uploads/
app/Repositories/
app/Contracts/       hanya jika interface dibutuhkan
app/Helpers/         {nama}_helper.php, fungsi murni
app/Traits/          perilaku berulang tanpa aturan bisnis
app/Cells/           View Cell untuk komponen berparameter
app/Views/layouts/   dashboard, card (login tetap memakai app/Views/login/layouts)
app/Views/partials/  dashboard/, feedback/, shared/
app/Views/…          isi halaman per area
```

Folder fitur di `Services/` cukup untuk skala kode sekarang. Sistem modul Laravel tidak perlu disalin sebelum ada kebutuhan dependensi antardomain yang jelas. `Helpers/`, `Traits/`, `Cells/`, `layouts/`, dan `partials/` selain `dashboard/partials/footer.php` belum ada; buat saat alur pertama membutuhkannya.

### Aturan repository

Rincian lengkap dan daftar repository usulan ada di [PRD bagian 9](../docs/ui-ux-2026/PRD.md#9-pola-repository); detail transaksi di [TECHNICAL](../docs/ui-ux-2026/TECHNICAL.md#repository-helper-trait-dan-komponen-view). Ringkasnya:

- Buat repository bila query membawa scope pemilik/role yang dipakai ulang, menulis beberapa tabel dalam transaksi, memerlukan lock baris, berupa agregat/filter admin berpaginasi, atau append-only (audit log).
- Class konkret di `App\Repositories`, `declare(strict_types=1)`, constructor menerima CI4 Model dan `BaseConnection` opsional dengan default `??=` seperti `AdminRepository`. Tanpa interface per tabel dan tanpa service provider.
- Nama metode menyebut scope (`findOwnedOrder($id, $userId)`, `findManageableUser`). Hasil berupa array atau `null`; daftar berpaginasi mengembalikan baris dan total. Query builder tidak keluar ke controller atau view.
- Repository tidak membaca request/session dan tidak memutuskan hak paket, harga, atau status pembayaran.
- Service membuka transaksi bila mengoordinasikan beberapa repository atau audit log; semua memakai koneksi yang sama agar audit ikut rollback.

Perbedaan dengan `apps-mutugading`: di sana setiap entitas memiliki `{Entitas}RepositoryInterface` + `Eloquent{Entitas}Repository` yang di-bind di provider dan mengekspos `getQuery()` berupa Builder. Pola itu tidak disalin. Yang diambil hanya pembagian tanggung jawab controller → service → repository → model.

### Komponen reusable

Rincian di [PRD bagian 10](../docs/ui-ux-2026/PRD.md#10-komponen-reusable-layout-partial-css-helper-trait). Ringkasnya:

| Jenis | Aturan | Contoh usulan |
| --- | --- | --- |
| Layout | Halaman memakai `$this->extend('layouts/dashboard')` dan `section('content')`; aset halaman lewat section `styles`/`scripts`. Pola `dashboard-home.php` + `$this->include($page)` diganti per alur, satu halaman tidak memakai keduanya. | `layouts/dashboard`, `auth`, `card` |
| Partial | Markup tanpa logika, data dikirim lewat argumen. Head, sidebar, dan footer hanya di-include layout. | `partials/feedback/flash`, `partials/shared/empty-state` |
| View Cell | Komponen berparameter yang dipakai lebih dari dua halaman. | `PageHeaderCell`, `PlanBadgeCell` |
| Helper | Fungsi murni dengan waktu acuan sebagai argumen agar dapat dites. | `asset_url()`, `format_wib()`, `format_idr()`, `field_error()` |
| Trait | Dipakai sedikitnya dua class, bukan aturan bisnis. | `ResolvesCurrentUser`, `RespondsWithFeedback`, `SearchesBuilder`, `UsesTransaction` |

Menu sidebar berasal dari satu daftar `[label, url, icon, roles, plan]` yang difilter server; menyembunyikan menu bukan pengganti filter route. Audit ditulis eksplisit lewat `AuditLogService`, bukan trait model seperti `LogsActivityWithDescription` di `apps-mutugading`.

### Contoh satu alur

Saat admin mengubah user, `UserController` memvalidasi format input. `UpdateUserService` memeriksa role pelaku, role target, dan aturan perubahan. `UserRepository` mengambil target dengan scope yang diizinkan, lalu menyimpan perubahan dalam transaksi jika lebih dari satu tabel terlibat. Controller mengubah hasil menjadi redirect. Metode repository harus bermakna, misalnya `findManageableUser`, bukan wrapper `findAll()` yang tidak menambah batas akses.

## Urutan refactor

1. **Baseline:** verifikasi schema MySQL yang benar, sediakan data uji anonim, dan tambahkan tes request untuk guest, User, Admin, Super Admin. Ini masih perlu dilakukan.
2. **Batas akses:** route statis, filter role, validasi status akun, dan CSRF sudah diterapkan; otorisasi target serta perilaku database masih perlu tes integrasi.
3. **Satu alur vertikal:** manajemen User sudah memakai controller, service, dan repository. Lanjutkan tes request serta transaksi pada database uji.
4. **Auth, profil, kartu:** reset password, validasi template, output kartu publik, penyimpanan profil, dan ownership upload sudah diperketat. Berikutnya konsolidasi URL upload saat document root beralih ke `public/`, serta tes request/database untuk alur profil.
5. **Infrastruktur:** pindahkan aset ke `public/`, buat migration dari schema yang terverifikasi, lalu tambah gate format dan tes.
6. **Komponen reusable:** siapkan hanya fondasi yang dibutuhkan alur berikutnya (layout `dashboard`, partial `feedback/`, `tokens.css`, helper `asset`/`date`, trait `ResolvesCurrentUser`/`RespondsWithFeedback`). Setiap alur yang dipindah ke layout baru sekaligus menghapus include footer manual, blok `getFlashdata` per halaman, dan `style=""` inline di halamannya.

Setiap tahap selesai ketika perilaku yang dimaksud dan kasus penolakan lulus tes. Perubahan schema, URI kartu publik, atau path berkas perlu strategi kompatibilitas dan rollback.
