<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceDefaults(false);
    }

    public function down(): void
    {
        $this->replaceDefaults(true);
    }

    private function replaceDefaults(bool $reverse): void
    {
        $changes = [
            ['page_sections', 'type', 'hero', 'title', 'Langkah besarmu, dimulai dari sini.', 'Layanan Kemahasiswaan STIA LPPN'],
            ['page_sections', 'type', 'hero', 'body', 'Satu tempat untuk seluruh kebutuhan kemahasiswaan. Lebih mudah mengajukan, lebih banyak waktu untuk berkembang.', 'Ajukan beasiswa, bimbingan konseling, kegiatan Ormawa, dan asuransi melalui satu portal. Pilih layanan, isi formulir, lalu simpan bukti pengajuan.'],
            ['page_sections', 'type', 'services', 'title', 'Dukungan untuk setiap langkahmu.', 'Layanan mahasiswa'],
            ['page_sections', 'type', 'services', 'body', 'Pilih layanan yang kamu butuhkan. Semuanya bisa diajukan tanpa membuat akun.', 'Pilih layanan untuk melihat persyaratan dan mengisi formulir pengajuan.'],
            ['page_sections', 'type', 'steps', 'title', 'Ajukan dengan mudah.', 'Cara mengajukan layanan'],
            ['page_sections', 'type', 'steps', 'body', 'Tiga langkah sederhana, dari mana saja.', 'Pengajuan dilakukan secara daring tanpa akun mahasiswa.'],
            ['page_sections', 'type', 'news', 'title', 'Kabar terbaru dari kampus.', 'Berita dan pengumuman'],
            ['page_sections', 'type', 'news', 'body', 'Jangan lewatkan informasi dan kesempatan untukmu.', 'Informasi terbaru tentang kegiatan dan layanan mahasiswa.'],
            ['page_sections', 'type', 'faq', 'title', 'Masih ada pertanyaan?', 'Pertanyaan umum'],
            ['page_sections', 'type', 'faq', 'body', 'Temukan jawaban sebelum memulai pengajuan.', 'Informasi tentang persyaratan, proses, dan hasil pengajuan.'],
            ['page_sections', 'type', 'contact', 'title', 'Kami siap membantu.', 'Hubungi bagian kemahasiswaan'],
            ['page_sections', 'type', 'contact', 'body', 'Hubungi bagian kemahasiswaan pada hari kerja.', 'Informasi jam layanan, alamat, dan kontak bagian kemahasiswaan.'],
            ['services', 'slug', 'beasiswa', 'summary', 'Raih kesempatan, wujudkan potensimu.', 'Pengajuan beasiswa prestasi dan bantuan pendidikan.'],
            ['services', 'slug', 'beasiswa', 'description', 'Temukan dukungan pendidikan untuk membantu perjalanan akademikmu. Lengkapi persyaratan program dan ajukan secara daring.', 'Ajukan beasiswa sesuai program yang tersedia. Periksa persyaratan dan lengkapi dokumen sebelum mengirim formulir.'],
            ['services', 'slug', 'konseling', 'summary', 'Ruang aman untuk cerita dan solusi.', 'Pengajuan bimbingan konseling akademik dan pribadi.'],
            ['services', 'slug', 'ormawa', 'summary', 'Dari ide jadi aksi yang berarti.', 'Pengajuan izin dan dukungan kegiatan organisasi mahasiswa.'],
            ['services', 'slug', 'asuransi', 'summary', 'Perlindungan untuk setiap langkah.', 'Pelaporan kejadian dan pengajuan klaim asuransi.'],
            ['site_settings', 'key', 'footer_text', 'value', 'Mendampingi perjalananmu, membuka ruang untuk tumbuh.', 'Informasi dan pengajuan layanan mahasiswa STIA LPPN.'],
        ];
        DB::transaction(function () use ($changes, $reverse): void {
            foreach ($changes as [$table,$key,$identity,$column,$old,$new]) {
                DB::table($table)->where($key, $identity)->where($column, $reverse ? $new : $old)->update([$column => $reverse ? $old : $new]);
            }
        });
        Cache::forget('portal.public.v2');
    }
};
