<?php

namespace Database\Seeders;

use App\Models\Cohort;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\LetterTemplate;
use App\Models\LetterTemplateVersion;
use App\Models\PageSection;
use App\Models\Post;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SiteSetting;
use App\Models\StudyProgram;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['administrator', 'editor', 'form_manager', 'service_officer', 'counselor'] as $role) {
            DB::table('roles')->updateOrInsert(['name' => $role], ['updated_at' => now(), 'created_at' => now()]);
        }
        foreach (['manage_accounts', 'manage_content', 'manage_forms', 'process_applications', 'issue_letters'] as $permission) {
            DB::table('permissions')->updateOrInsert(['name' => $permission], ['updated_at' => now(), 'created_at' => now()]);
        }
        foreach ([['ADM', 'Administrasi Publik'], ['AB', 'Administrasi Bisnis']] as [$code,$name]) {
            StudyProgram::firstOrCreate(['code' => $code], ['name' => $name]);
        }
        foreach (range((int) date('Y') - 6, (int) date('Y')) as $year) {
            Cohort::firstOrCreate(['year' => $year]);
        }
        $services = [
            ['beasiswa', 'Beasiswa', 'Pengajuan beasiswa prestasi dan bantuan pendidikan.', 'Ajukan beasiswa sesuai program yang tersedia. Periksa persyaratan dan lengkapi dokumen sebelum mengirim formulir.', 'graduation', 'Prestasi & bantuan pendidikan', [['key' => 'program', 'label' => 'Program beasiswa', 'type' => 'select', 'required' => true, 'options' => ['Prestasi', 'Bantuan pendidikan']], ['key' => 'achievement', 'label' => 'Prestasi atau informasi pendukung', 'type' => 'textarea', 'required' => false]]],
            ['konseling', 'Bimbingan Konseling', 'Pengajuan bimbingan konseling akademik dan pribadi.', 'Dukungan untuk persoalan akademik maupun pribadi. Informasi pengajuan bersifat privat dan hanya dapat diakses petugas yang ditugaskan.', 'heart', 'Konseling akademik & pribadi', [['key' => 'preferred_date', 'label' => 'Tanggal yang diharapkan', 'type' => 'date', 'required' => true], ['key' => 'contact_method', 'label' => 'Metode konseling', 'type' => 'select', 'required' => true, 'options' => ['Tatap muka', 'Daring']], ['key' => 'summary', 'label' => 'Ringkasan singkat (opsional)', 'type' => 'textarea', 'required' => false]]],
            ['ormawa', 'Kegiatan Ormawa', 'Pengajuan izin dan dukungan kegiatan organisasi mahasiswa.', 'Ajukan izin kegiatan dan dukungan organisasi mahasiswa. Siapkan rincian kegiatan serta dokumen pendukung yang diperlukan.', 'users', 'Izin & dukungan kegiatan', [['key' => 'organization', 'label' => 'Nama organisasi', 'type' => 'text', 'required' => true], ['key' => 'activity', 'label' => 'Nama kegiatan', 'type' => 'text', 'required' => true], ['key' => 'activity_date', 'label' => 'Tanggal kegiatan', 'type' => 'date', 'required' => true], ['key' => 'location', 'label' => 'Lokasi', 'type' => 'text', 'required' => true], ['key' => 'proposal', 'label' => 'Proposal kegiatan', 'type' => 'file', 'required' => true]]],
            ['asuransi', 'Asuransi', 'Pelaporan kejadian dan pengajuan klaim asuransi.', 'Sampaikan pelaporan awal atau pengajuan klaim. Kampus membantu proses administrasi; keputusan klaim ditetapkan pihak asuransi.', 'shield', 'Pelaporan & pengajuan klaim', [['key' => 'incident_date', 'label' => 'Tanggal kejadian', 'type' => 'date', 'required' => true], ['key' => 'incident', 'label' => 'Uraian singkat kejadian', 'type' => 'textarea', 'required' => true], ['key' => 'claim_type', 'label' => 'Jenis pengajuan', 'type' => 'select', 'required' => true, 'options' => ['Pelaporan awal', 'Pengajuan klaim']]]],
        ];
        foreach ($services as $i => [$slug,$name,$summary,$description,$icon,$categoryName,$fields]) {
            $service = Service::firstOrCreate(['slug' => $slug], compact('name', 'summary', 'description', 'icon') + ['position' => $i, 'is_sensitive' => $slug === 'konseling']);
            $category = ServiceCategory::firstOrCreate(['service_id' => $service->id, 'name' => $categoryName]);
            $form = Form::firstOrCreate(['slug' => $slug.'-umum'], ['service_id' => $service->id, 'service_category_id' => $category->id, 'title' => 'Pengajuan '.$name, 'status' => 'published']);
            $version = FormVersion::firstOrCreate(['form_id' => $form->id, 'version' => 1], ['status' => 'published', 'fields' => array_map(fn (array $f): array => $f + ['help' => '', 'group' => 'Detail pengajuan', 'max' => 2000], $fields), 'published_at' => now()]);
            if (! $form->active_version_id) {
                $form->update(['active_version_id' => $version->id]);
            }
            $template = LetterTemplate::firstOrCreate(['service_id' => $service->id], ['name' => $slug === 'konseling' ? 'Konfirmasi layanan konseling' : 'Surat tindak lanjut '.$name]);
            $tv = LetterTemplateVersion::firstOrCreate(['letter_template_id' => $template->id, 'version' => 1], ['body' => 'Dengan ini menerangkan bahwa {{full_name}}, NPM {{student_number}}, program studi {{study_program}}, telah mengajukan layanan {{service}} dengan nomor {{reference}}. {{decision}}', 'signatory' => 'Pejabat berwenang (atur sebelum penerbitan)', 'title' => 'Bagian Kemahasiswaan']);
            if (! $template->active_version_id) {
                $template->update(['active_version_id' => $tv->id]);
            }
        }
        foreach ([['hero', 'Layanan Kemahasiswaan STIA LPPN', 'Ajukan beasiswa, bimbingan konseling, kegiatan Ormawa, dan asuransi melalui satu portal. Pilih layanan, isi formulir, lalu simpan bukti pengajuan.'], ['services', 'Layanan mahasiswa', 'Pilih layanan untuk melihat persyaratan dan mengisi formulir pengajuan.'], ['steps', 'Cara mengajukan layanan', 'Pengajuan dilakukan secara daring tanpa akun mahasiswa.'], ['news', 'Berita dan pengumuman', 'Informasi terbaru tentang kegiatan dan layanan mahasiswa.'], ['faq', 'Pertanyaan umum', 'Informasi tentang persyaratan, proses, dan hasil pengajuan.'], ['contact', 'Hubungi bagian kemahasiswaan', 'Informasi jam layanan, alamat, dan kontak bagian kemahasiswaan.']] as $i => [$type,$title,$body]) {
            PageSection::firstOrCreate(['type' => $type], compact('title', 'body') + ['position' => $i]);
        }
        foreach ([['Panduan layanan kemahasiswaan daring', 'Kenali empat layanan dan langkah pengajuan melalui portal kemahasiswaan.', 'news'], ['Persiapkan dokumen sebelum mengajukan', 'Pastikan identitas dan dokumen pendukung sudah lengkap agar proses lebih lancar.', 'announcement'], ['Ruang untuk tumbuh bersama', 'Kenali dukungan konseling dan kegiatan organisasi untuk perjalanan kuliahmu.', 'news']] as $i => [$title,$excerpt,$kind]) {
            Post::firstOrCreate(['slug' => Str::slug($title)], compact('title', 'excerpt', 'kind') + ['body' => $excerpt."\n\nPilih layanan yang sesuai dan lengkapi formulir. Petugas akan menghubungi melalui email atau WhatsApp yang dipilih.", 'status' => 'published', 'published_at' => now()->subDays($i)]);
        }
        SiteSetting::firstOrCreate(['key' => 'faq_items'], ['value' => json_encode([['Apakah perlu membuat akun untuk mengajukan?', 'Tidak. Pilih layanan, lengkapi formulir, dan kirim pengajuan tanpa login. Akun hanya digunakan petugas internal kampus.'], ['Bagaimana cara mengetahui hasil pengajuan?', 'Petugas akan menghubungi melalui email atau WhatsApp yang dipilih. Simpan nomor pengajuan dan pastikan kontak yang diisi aktif.'], ['Bagaimana jika data saya perlu diperbaiki?', 'Petugas mengirim tautan privat untuk memperbaiki pengajuan yang sama. Buka tautan sebelum kedaluwarsa dan ikuti catatan perbaikannya.'], ['Apakah data pengajuan saya aman?', 'Lampiran dan pengajuan disimpan secara privat. Akses petugas dibatasi berdasarkan izin dan layanan yang ditugaskan, termasuk untuk konseling.']], JSON_UNESCAPED_UNICODE)]);
        foreach (['letter_number_format' => '{sequence}/KM-{service}/STIA/{year}', 'header_label' => 'KEMAHASISWAAN', 'site_name' => 'Kemahasiswaan STIA LPPN', 'address' => 'STIA LPPN · Padang, Sumatera Barat', 'email' => '', 'whatsapp' => '', 'office_hours' => 'Senin–Jumat, 08.00–16.00 WIB', 'footer_text' => 'Informasi dan pengajuan layanan mahasiswa STIA LPPN.', 'setup_notice' => 'Data program studi, kategori, template, dan jam layanan adalah konfigurasi awal. Verifikasi melalui pengelola kampus sebelum penggunaan resmi.'] as $key => $value) {
            SiteSetting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
        $this->call(NavigationSeeder::class);
    }
}
