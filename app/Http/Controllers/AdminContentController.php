<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Post;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Services\SectionContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminContentController extends Controller
{
    public const LABELS = ['pages' => 'Halaman', 'posts' => 'Berita & Pengumuman', 'sections' => 'Section Website', 'menus' => 'Navigasi', 'media' => 'Pustaka Media', 'settings' => 'Pengaturan Situs'];

    public const SETTINGS = ['faq_items' => 'Pertanyaan Umum (FAQ)', 'letter_number_format' => 'Format Nomor Surat', 'header_label' => 'Label Header', 'site_name' => 'Nama Situs', 'address' => 'Alamat', 'email' => 'Email Resmi', 'whatsapp' => 'WhatsApp Resmi', 'office_hours' => 'Jam Layanan', 'footer_text' => 'Teks Footer', 'setup_notice' => 'Catatan Konfigurasi'];

    private const MODELS = ['pages' => Page::class, 'posts' => Post::class, 'sections' => PageSection::class, 'menus' => MenuItem::class, 'media' => Media::class, 'settings' => SiteSetting::class];

    private function authorizeType(string $type): string
    {
        abort_unless(auth()->user()?->hasPermission('manage_content'), 403);
        abort_unless(isset(self::MODELS[$type]), 404);

        return self::MODELS[$type];
    }

    public function index(Request $request, string $type = 'pages'): View
    {
        $class = $this->authorizeType($type);
        if ($type === 'menus') {
            return app(AdminNavigationController::class)->index();
        }
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])], 'kind' => ['nullable', Rule::in(['news', 'announcement'])], 'visibility' => ['nullable', Rule::in(['visible', 'hidden'])], 'file_type' => ['nullable', Rule::in(['image', 'pdf'])]]);
        $query = $class::query();
        if ($request->filled('q')) {
            $column = match ($type) {
                'menus' => 'label','media' => 'name','settings' => 'key',default => 'title'
            };
            $query->where(function ($search) use ($data, $column, $type): void {
                $search->where($column, 'like', '%'.$data['q'].'%');
                if (in_array($type, ['pages', 'posts'], true)) {
                    $search->orWhere('slug', 'like', '%'.$data['q'].'%');
                }
                if ($type === 'sections') {
                    $search->orWhere('name', 'like', '%'.$data['q'].'%');
                }
                if ($type === 'settings') {
                    $keys = array_keys(array_filter(self::SETTINGS, fn (string $label): bool => mb_stripos($label, $data['q']) !== false));
                    $search->orWhereIn('key', $keys);
                }
            });
        }
        if (in_array($type, ['pages', 'posts'], true) && $request->filled('status')) {
            $query->where('status', $data['status']);
        }
        if ($type === 'posts' && $request->filled('kind')) {
            $query->where('kind', $data['kind']);
        }
        if (in_array($type, ['sections', 'menus'], true) && $request->filled('visibility')) {
            $query->where('is_visible', $data['visibility'] === 'visible');
        }
        if ($type === 'media' && $request->filled('file_type')) {
            $query->where('mime_type', 'like', $data['file_type'] === 'image' ? 'image/%' : 'application/pdf');
        }
        if (in_array($type, ['sections', 'menus'], true)) {
            $query->orderBy('position');
        }
        $columns = match ($type) {
            'sections' => ['id', 'name', 'title', 'type', 'location', 'position', 'is_visible', 'starts_at', 'ends_at', 'updated_at'], 'pages' => ['id', 'title', 'slug', 'status', 'updated_at'],'posts' => ['id', 'title', 'slug', 'kind', 'status', 'updated_at'],'settings' => ['id', 'key', 'updated_at'],default => ['*']
        };

        return view('admin.content', ['type' => $type, 'label' => self::LABELS[$type], 'records' => $query->orderByDesc('id')->paginate(15, $columns)->withQueryString(), 'settingLabels' => self::SETTINGS]);
    }

    public function create(string $type): View
    {
        $this->authorizeType($type);
        abort_unless(in_array($type, ['pages', 'posts', 'menus', 'media', 'sections'], true), 404);

        return $this->editor($type, null);
    }

    public function edit(string $type, int $id): View
    {
        $class = $this->authorizeType($type);

        return $this->editor($type, $class::findOrFail($id));
    }

    private function editor(string $type, ?Model $record): View
    {
        if ($record instanceof Page) {
            $record->load('services');
        }

        if ($type === 'sections') {
            return view('admin.section-edit', ['record' => $record, 'sectionSettings' => $record ? app(SectionContent::class)->settings($record, SiteSetting::pluck('value', 'key')->all()) : app(SectionContent::class)->defaults('text'), 'images' => Media::where('mime_type', 'like', 'image/%')->latest()->get(['id', 'name', 'path']), 'pages' => Page::orderBy('title')->get(['id', 'title', 'status']), 'services' => Service::orderBy('name')->get(['id', 'name', 'is_active']), 'breadcrumbs' => [['label' => 'Section Website', 'url' => route('admin.content.index', 'sections')]]]);
        }

        return view('admin.content-edit', ['type' => $type, 'label' => self::LABELS[$type], 'record' => $record, 'settingLabels' => self::SETTINGS, 'pages' => Page::orderBy('title')->get(['id', 'title', 'status']), 'services' => Service::orderBy('name')->get(['id', 'name', 'is_active']), 'parents' => MenuItem::whereNull('parent_id')->when($record, fn ($q) => $q->where('id', '!=', $record->id))->get(), 'breadcrumbs' => [['label' => self::LABELS[$type], 'url' => route('admin.content.index', $type)]]]);
    }
}
