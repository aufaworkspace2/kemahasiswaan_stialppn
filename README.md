# Portal Kemahasiswaan STIA LPPN

Website informasi dan pengajuan layanan mahasiswa STIA LPPN, dilengkapi panel administrasi untuk mengelola konten, formulir, dan proses pengajuan.

## Fitur

- Layanan beasiswa, konseling, kegiatan Ormawa, dan asuransi.
- Pengajuan tanpa akun mahasiswa, dokumen privat, bukti PDF, dan tautan perbaikan pengajuan.
- Pengelolaan formulir, kategori, template surat, serta proses tindak lanjut petugas.
- Halaman, berita, media, dan navbar dari database; menu mendukung submenu dan pengaturan urutan.
- Editor section website: rich text, gambar desktop/ponsel, tombol ke halaman/layanan, daftar item, penempatan, dan jadwal tampil.
- Hak akses petugas berdasarkan izin dan penugasan layanan; konseling memiliki pembatasan akses tersendiri.
- Editor, ikon, dan font lokal tanpa API key editor atau CDN saat digunakan.

## Teknologi

Laravel 13, Blade, Tailwind CSS 4, Vite 8, JavaScript, MySQL, Pest, dan Playwright. Editor memakai Quill dan DOMPurify; ikon memakai Lucide; font memakai Inter dan Manrope.

Lingkungan pengembangan telah diuji dengan PHP 8.5 dan Node.js 26. Gunakan Composer 2 serta ekstensi PHP yang diminta `composer.lock`, termasuk dukungan database, DOM, dan ZIP. Tes otomatis memakai SQLite dalam memori sehingga memerlukan `pdo_sqlite`.

## Instalasi lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
npm ci --ignore-scripts
```

Buat database MySQL, lalu isi konfigurasi di `.env`:

```dotenv
APP_NAME="Kemahasiswaan STIA LPPN"
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kemahasiswaan_stia
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

Jalankan migrasi dan data awal, siapkan akses media publik, lalu build aset:

```bash
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve
```

Buka alamat yang ditampilkan Artisan. Untuk pengembangan frontend, jalankan `npm run dev` pada terminal terpisah.

## Akun administrator

Tidak ada password administrator bawaan di repository. Buat akun melalui:

```bash
php artisan portal:admin admin@example.com --name="Administrator"
```

Perintah menampilkan password sementara. Login melalui `/admin/login`. Penugasan konseling tidak diberikan otomatis; atur izin dan penugasan sesuai tanggung jawab petugas.

## Pengelolaan konten

- **Navigasi:** pilih tujuan halaman atau layanan, atur induk dan urutan, lalu simpan susunan. Hapus ditolak jika menu masih menjadi induk atau terkait layanan yang sudah memiliki pengajuan.
- **Halaman:** tulis konten, pilih layanan terkait bila perlu, lalu terbitkan.
- **Section Website:** tambah/edit section, isi konten dan item, pilih gambar dari Pustaka Media, kemudian atur penempatan, visibilitas, dan jadwal dalam WIB.
- **Formulir:** atur field dan versi sebelum menerbitkan formulir layanan.

Data seeder merupakan konfigurasi awal. Sesuaikan layanan, persyaratan, kontak resmi, dan template surat dengan kebutuhan kampus.

## Antrean dan notifikasi

Atur konfigurasi email serta antrean di `.env`, kemudian jalankan worker:

```bash
php artisan queue:work
```

WhatsApp menggunakan alur tindak lanjut manual; repository ini tidak menyertakan integrasi pengiriman WhatsApp otomatis. Aktifkan scheduler Laravel pada lingkungan operasional untuk menjalankan tugas terjadwal.

## Pengujian dan build

```bash
php artisan test --compact
npm run build
```

Tes browser berada di `tests/Browser`. Script QC menggunakan Playwright dan akun/data uji lokal; periksa kebutuhan environment variable masing-masing script sebelum menjalankannya. Playwright tidak dipasang sebagai dependency aplikasi produksi.

## Backup

```bash
php artisan portal:backup
php artisan portal:backup-verify storage/app/backups/nama-arsip.zip
```

Backup disimpan privat dan tidak masuk Git. Verifikasi arsip bukan pengganti uji pemulihan database pada lingkungan terpisah.

## Isi repository

Kode, migrasi, seeder, tes, aset sumber, dan lockfile disertakan. `.env`, dependency terpasang, hasil build, database lokal, upload, backup, konfigurasi agen lokal, folder referensi/QC, serta dokumen lain dikecualikan. README ini menjadi pengecualian dokumentasi untuk halaman GitHub. `robots.txt` dan lisensi font tetap disertakan sebagai bagian aset aplikasi.

Font disertai lisensi OFL di `resources/fonts`. Identitas dan logo kampus digunakan untuk proyek STIA LPPN.
