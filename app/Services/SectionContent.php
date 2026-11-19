<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SectionContent
{
    public const TYPES = ['hero' => 'Hero', 'services' => 'Daftar layanan', 'steps' => 'Alur / langkah', 'news' => 'Berita & pengumuman', 'faq' => 'FAQ', 'contact' => 'Kontak', 'text' => 'Teks', 'image' => 'Gambar & teks', 'cta' => 'Ajakan / CTA', 'links' => 'Tautan cepat', 'stats' => 'Statistik', 'gallery' => 'Galeri', 'banner' => 'Pengumuman'];

    public const LOCATIONS = ['home' => 'Beranda', 'top' => 'Di atas navigasi', 'bottom' => 'Di atas footer (semua halaman)', 'footer' => 'Footer (semua halaman)'];

    public const ICONS = ['arrow' => 'Panah', 'graduation' => 'Pendidikan', 'heart' => 'Dukungan', 'users' => 'Orang', 'shield' => 'Perlindungan', 'file' => 'Dokumen', 'clock' => 'Waktu', 'mail' => 'Email', 'pin' => 'Lokasi', 'grid' => 'Kotak', 'check' => 'Centang'];

    public function __construct(private RichText $richText) {}

    public function defaults(string $type): array
    {
        $base = ['eyebrow' => '', 'detail' => '', 'detail_format' => 'plain', 'button_label' => '', 'button_url' => '', 'button_target' => 'url', 'secondary_button_label' => '', 'secondary_button_url' => '', 'secondary_button_target' => 'url', 'layout' => 'split', 'theme' => 'default', 'alignment' => 'left', 'spacing' => 'normal', 'item_limit' => 3, 'source_mode' => 'all', 'service_ids' => [], 'items' => [], 'note' => '', 'show_decoration' => true];

        return array_replace($base, match ($type) {
            'hero' => ['eyebrow' => 'PORTAL KEMAHASISWAAN STIA LPPN', 'button_label' => 'Lihat Layanan', 'button_url' => '/#services', 'secondary_button_label' => 'Cara mengajukan', 'secondary_button_url' => '/#steps', 'note' => 'BEASISWA · KONSELING · ORMAWA · ASURANSI', 'items' => [['title' => 'Tanpa akun mahasiswa', 'icon' => 'check'], ['title' => 'Bukti pengajuan PDF', 'icon' => 'file']]],
            'services' => ['eyebrow' => 'LAYANAN MAHASISWA', 'note' => 'Data pengajuan hanya dapat diakses oleh petugas yang berwenang.', 'item_limit' => 20],
            'steps' => ['eyebrow' => 'ALUR PENGAJUAN', 'button_label' => 'Pilih layanan', 'button_url' => '/#services', 'items' => [['title' => 'Pilih layanan', 'text' => 'Pilih layanan dan formulir yang sesuai dengan kebutuhan.'], ['title' => 'Lengkapi formulir', 'text' => 'Isi identitas, kontak yang aktif, dan dokumen persyaratan.'], ['title' => 'Terima informasi hasil', 'text' => 'Simpan bukti pengajuan. Petugas menyampaikan perkembangan atau hasil melalui email atau WhatsApp yang dipilih.']]],
            'news' => ['eyebrow' => 'INFORMASI KAMPUS', 'button_label' => 'Semua informasi', 'button_url' => '/berita'],
            'faq' => ['eyebrow' => 'PERTANYAAN UMUM', 'button_label' => 'Hubungi bagian kemahasiswaan', 'button_url' => '/#contact'],
            'contact' => ['eyebrow' => 'KONTAK KEMAHASISWAAN'],
            default => [],
        });
    }

    public function settings(PageSection $section, array $siteSettings = []): array
    {
        $settings = array_replace($this->defaults($section->type), $section->settings ?? []);
        if ($section->type === 'faq' && ! array_key_exists('items', $section->settings ?? [])) {
            $settings['items'] = array_map(fn (array $item): array => ['title' => $item[0], 'text' => $item[1]], json_decode($siteSettings['faq_items'] ?? '[]', true) ?: []);
        }

        return $settings;
    }

    private function safeUrl(?string $url, string $field): void
    {
        if ($url !== null && $url !== '' && (! preg_match('~^(https?://|/(?!/)|#|mailto:|tel:)~i', $url) || preg_match('/[\x00-\x20\x7f\\\\]/', $url))) {
            throw ValidationException::withMessages([$field => 'Gunakan https://, path /halaman, anchor #bagian, mailto:, atau tel: yang valid.']);
        }
    }

    public function save(Request $request, ?PageSection $section): PageSection
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'], 'section_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'title' => ['required', 'string', 'max:180'], 'anchor' => ['nullable', 'regex:/^[a-zA-Z][a-zA-Z0-9_-]*$/', 'max:100', Rule::unique('page_sections', 'anchor')->ignore($section?->id)],
            'body' => ['nullable', 'string', 'max:20000'], 'body_format' => ['nullable', Rule::in(['plain', 'html'])],
            'position' => ['required', 'integer', 'between:0,10000'], 'is_visible' => ['nullable', 'boolean'], 'location' => ['required', Rule::in(array_keys(self::LOCATIONS))],
            'media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where(fn ($q) => $q->where('mime_type', 'like', 'image/%'))], 'mobile_media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where(fn ($q) => $q->where('mime_type', 'like', 'image/%'))],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', Rule::when($request->filled('starts_at'), 'after_or_equal:starts_at')],
            'detail' => ['nullable', 'string', 'max:30000'], 'detail_format' => ['nullable', Rule::in(['plain', 'html'])],
            'settings' => ['required', 'array:eyebrow,detail,detail_format,button_label,button_url,button_target,secondary_button_label,secondary_button_url,secondary_button_target,layout,theme,alignment,spacing,item_limit,source_mode,service_ids,items,note,show_decoration'],
            'settings.eyebrow' => ['nullable', 'string', 'max:150'], 'settings.detail' => ['nullable', 'string', 'max:30000'], 'settings.detail_format' => ['nullable', Rule::in(['plain', 'html'])], 'settings.note' => ['nullable', 'string', 'max:500'],
            'settings.layout' => ['required', Rule::in(['split', 'image'])], 'settings.theme' => ['required', Rule::in(['default', 'light', 'dark'])], 'settings.alignment' => ['required', Rule::in(['left', 'center'])], 'settings.spacing' => ['required', Rule::in(['compact', 'normal', 'wide'])],
            'settings.item_limit' => ['required', 'integer', 'between:1,20'], 'settings.source_mode' => ['required', Rule::in(['all', 'selected'])], 'settings.service_ids' => ['nullable', 'array', 'max:100'], 'settings.service_ids.*' => ['integer', 'distinct', 'exists:services,id'], 'settings.show_decoration' => ['nullable', 'boolean'],
            'settings.items' => ['nullable', 'array', 'max:20'], 'settings.items.*' => ['array:title,text,text_format,url,target,media_id,icon'], 'settings.items.*.title' => ['required', 'string', 'max:180'], 'settings.items.*.text_format' => ['nullable', Rule::in(['plain', 'html'])], 'settings.items.*.text' => ['nullable', 'string', 'max:3000'], 'settings.items.*.url' => ['nullable', 'string', 'max:500'], 'settings.items.*.target' => ['nullable', 'string', 'max:50'], 'settings.items.*.icon' => ['nullable', Rule::in(array_keys(self::ICONS))], 'settings.items.*.media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where(fn ($q) => $q->where('mime_type', 'like', 'image/%'))],
            'settings.button_label' => ['nullable', 'string', 'max:100'], 'settings.button_url' => ['nullable', 'string', 'max:500'], 'settings.button_target' => ['nullable', 'string', 'max:50'], 'settings.secondary_button_label' => ['nullable', 'string', 'max:100'], 'settings.secondary_button_url' => ['nullable', 'string', 'max:500'], 'settings.secondary_button_target' => ['nullable', 'string', 'max:50'],
        ]);
        $config = $data['settings'];
        $config['detail'] = $data['detail'] ?? ($config['detail'] ?? '');
        $config['detail_format'] = $data['detail_format'] ?? ($config['detail_format'] ?? 'plain');
        unset($data['detail'], $data['detail_format']);
        foreach (['starts_at', 'ends_at'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = Carbon::parse($data[$field], 'Asia/Jakarta')->setTimezone(config('app.timezone'));
            }
        }
        foreach (['button', 'secondary_button'] as $button) {
            $this->safeUrl($config[$button.'_url'] ?? null, 'settings.'.$button.'_url');
            $this->validateTarget($config[$button.'_target'] ?? 'url', 'settings.'.$button.'_target');
        }
        foreach ($config['items'] ?? [] as $index => $item) {
            $this->safeUrl($item['url'] ?? null, "settings.items.$index.url");
            $this->validateTarget($item['target'] ?? 'url', "settings.items.$index.target");
        }
        $config['items'] = array_values($config['items'] ?? []);
        foreach ($config['items'] as &$item) {
            $item['text_format'] = $item['text_format'] ?? 'plain';
            if ($item['text_format'] === 'html') {
                $item['text'] = $this->richText->sanitize($item['text'] ?? '');
            }
        }
        unset($item);
        $config['service_ids'] = $config['service_ids'] ?? [];
        $config['show_decoration'] = $request->boolean('settings.show_decoration');
        $config['detail_format'] = $config['detail_format'] ?? 'plain';
        $config['detail'] = $config['detail_format'] === 'html' ? $this->richText->sanitize($config['detail'] ?? '') : ($config['detail'] ?? '');
        $data['body_format'] = $data['body_format'] ?? 'plain';
        $data['body'] = $data['body_format'] === 'html' ? $this->richText->sanitize($data['body'] ?? '') : ($data['body'] ?? '');
        $data['type'] = $data['section_type'];
        unset($data['section_type']);
        $data['settings'] = $config;
        $data['is_visible'] = $request->boolean('is_visible');
        $section ??= new PageSection;
        $section->fill($data)->save();

        return $section;
    }

    private function validateTarget(string $target, string $field): void
    {
        if ($target === 'url') {
            return;
        }
        if (! preg_match('/^(page|service):([0-9]+)$/', $target, $matches) || ! ($matches[1] === 'page' ? Page::whereKey($matches[2])->exists() : Service::whereKey($matches[2])->exists())) {
            throw ValidationException::withMessages([$field => 'Tujuan halaman atau layanan tidak ditemukan.']);
        }
    }

    /** @param Collection<int, Service> $services */
    public function prepare(Collection $sections, Collection $services, array $siteSettings): Collection
    {
        $pageIds = $sections->flatMap(function (PageSection $section): Collection {
            $settings = $section->settings ?? [];

            return collect([$settings['button_target'] ?? '', $settings['secondary_button_target'] ?? ''])->merge(collect($settings['items'] ?? [])->pluck('target'));
        })->filter(fn ($target): bool => is_string($target) && str_starts_with($target, 'page:'))->map(fn (string $target): int => (int) substr($target, 5))->unique();
        $pages = $pageIds->isEmpty() ? collect() : Page::whereIn('id', $pageIds)->where('status', 'published')->get(['id', 'slug'])->keyBy('id');
        $prepared = $sections->map(function (PageSection $section) use ($services, $siteSettings, $pages): PageSection {
            $config = $this->settings($section, $siteSettings);
            $resolve = function (?string $target, ?string $url) use ($pages, $services): ?string {
                if (! $target || $target === 'url') {
                    return $url;
                }
                [$type, $id] = explode(':', $target);
                $record = $type === 'page' ? $pages->get($id) : $services->firstWhere('id', (int) $id);

                return $record ? route($type === 'page' ? 'pages.show' : 'services.show', $record->slug) : null;
            };
            foreach (['button', 'secondary_button'] as $button) {
                $config[$button.'_href'] = $resolve($config[$button.'_target'], $config[$button.'_url']);
            }
            foreach ($config['items'] as &$item) {
                $item['href'] = $resolve($item['target'] ?? 'url', $item['url'] ?? null);
                $item['text_html'] = $this->richText->render($item['text'] ?? '', $item['text_format'] ?? 'plain');
            }
            unset($item);
            $section->setAttribute('display_settings', $config);
            $section->setAttribute('body_html', $this->richText->render($section->body ?? '', $section->body_format));
            $section->setAttribute('detail_html', $this->richText->render($config['detail'] ?? '', $config['detail_format']));

            return $section;
        });
        $ids = $prepared->flatMap(fn ($section) => collect($section->display_settings['items'])->pluck('media_id'))->filter()->unique();
        $media = $ids->isEmpty() ? collect() : Media::whereIn('id', $ids)->where('mime_type', 'like', 'image/%')->get(['id', 'path', 'name'])->keyBy('id');

        return $prepared->each(fn ($section) => $section->setRelation('itemMedia', $media));
    }
}
