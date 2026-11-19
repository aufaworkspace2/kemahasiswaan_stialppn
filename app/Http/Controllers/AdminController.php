<?php

namespace App\Http\Controllers;

use App\Jobs\SendApplicationNotification;
use App\Models\ApplicationAttachment;
use App\Models\ApplicationNote;
use App\Models\ApplicationRevision;
use App\Models\ApplicationStatusLog;
use App\Models\Cohort;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\LetterTemplateVersion;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\NotificationDelivery;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Post;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SiteSetting;
use App\Models\StudentApplication;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\PdfDocument;
use App\Services\PortalWorkflow;
use App\Services\RichText;
use App\Services\SectionContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function __construct(private PortalWorkflow $workflow, private RichText $richText) {}

    private function permit(string $permission): void
    {
        abort_unless(auth()->user()?->hasPermission($permission), 403);
    }

    private function serviceAccess(int $id): void
    {
        abort_unless(auth()->user()?->canAccessService($id), 404);
    }

    private function scopedApplications(): Builder
    {
        $ids = Service::get()->filter(fn (Service $s): bool => auth()->user()->canAccessService($s->id))->pluck('id');

        return StudentApplication::whereIn('service_id', $ids);
    }

    public function login(): View
    {
        return view('admin.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials)) {
            return back()->withErrors(['email' => 'Email atau kata sandi tidak sesuai.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function dashboard(Request $request): View
    {
        $applications = auth()->user()->hasPermission('process_applications') ? $this->scopedApplications()->select(['id', 'reference', 'service_id', 'study_program_id', 'student_number', 'full_name', 'status', 'submitted_at'])->with('service', 'studyProgram')->latest('id')->limit(6)->get() : collect();
        $counts = auth()->user()->hasPermission('process_applications') ? $this->scopedApplications()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status') : collect();

        return view('admin.dashboard', compact('applications', 'counts'));
    }

    public function applications(Request $request): View
    {
        $this->permit('process_applications');
        $q = $this->scopedApplications()->select(['id', 'reference', 'service_id', 'service_category_id', 'study_program_id', 'cohort_id', 'student_number', 'full_name', 'status', 'submitted_at'])->with('service', 'studyProgram', 'cohort');
        foreach (['service_id', 'service_category_id', 'study_program_id', 'cohort_id', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $q->where($filter, $request->input($filter));
            }
        }
        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $q->where(fn ($query) => $query->where('reference', 'like', '%'.$search.'%')->orWhere('student_number', 'like', '%'.$search.'%'));
        }
        $request->validate(['from' => ['nullable', 'date'], 'until' => ['nullable', 'date', 'after_or_equal:from']]);
        if ($request->filled('from')) {
            $q->where('submitted_at', '>=', $request->date('from')->startOfDay());
        }
        if ($request->filled('until')) {
            $q->where('submitted_at', '<=', $request->date('until')->endOfDay());
        }

        return view('admin.applications', ['applications' => $q->latest('id')->paginate(15)->withQueryString(), 'services' => Service::get()->filter(fn ($s) => auth()->user()->canAccessService($s->id)), 'categories' => ServiceCategory::all(), 'programs' => StudyProgram::all(), 'cohorts' => Cohort::orderByDesc('year')->get()]);
    }

    public function application(StudentApplication $application): View
    {
        $this->permit('process_applications');
        $this->serviceAccess($application->service_id);
        $application->load('service', 'version', 'studyProgram', 'cohort', 'attachments', 'statusLogs', 'notes', 'deliveries', 'letters');

        return view('admin.application', compact('application') + ['templates' => LetterTemplate::where('service_id', $application->service_id)->get(), 'breadcrumbs' => [['label' => 'Pengajuan', 'url' => route('admin.applications.index')]], 'revisions' => ApplicationRevision::where('application_id', $application->id)->latest('id')->paginate(10, ['*'], 'revisions_page')]);
    }

    public function updateApplication(Request $request, StudentApplication $application): RedirectResponse
    {
        $this->permit('process_applications');
        $this->serviceAccess($application->service_id);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(PortalWorkflow::STATUSES))], 'message' => ['nullable', 'required_if:status,needs_revision', 'string', 'max:2000']]);
        DB::transaction(function () use ($application, $data): void {
            $locked = StudentApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            $allowed = ['received' => ['under_review'], 'under_review' => ['needs_revision', 'approved', 'rejected'], 'needs_revision' => ['under_review'], 'approved' => [], 'rejected' => []];
            abort_unless(in_array($data['status'], $allowed[$locked->status], true), 422, 'Perubahan status tidak diizinkan.');
            $old = $locked->status;
            $locked->update(['status' => $data['status']]);
            ApplicationStatusLog::create(['application_id' => $locked->id, 'user_id' => auth()->id(), 'from_status' => $old, 'to_status' => $data['status'], 'message' => $data['message'] ?? null]);
            $url = $data['status'] === 'needs_revision' ? route('applications.access', $this->workflow->token($locked, 'revision')) : null;
            $this->workflow->notify($locked, 'status', 'Status pengajuan '.$locked->reference.': '.PortalWorkflow::STATUSES[$data['status']].'. Silakan buka tautan privat jika tersedia.', $url);
            $this->workflow->audit('application.status_changed', 'application', $locked->id);
        });

        return back()->with('success', 'Status dan riwayat berhasil diperbarui.');
    }

    public function note(Request $request, StudentApplication $application): RedirectResponse
    {
        $this->permit('process_applications');
        $this->serviceAccess($application->service_id);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        ApplicationNote::create($data + ['application_id' => $application->id, 'user_id' => auth()->id()]);

        return back()->with('success', 'Catatan internal disimpan.');
    }

    public function attachment(ApplicationAttachment $attachment): StreamedResponse
    {
        $this->permit('process_applications');
        $application = StudentApplication::findOrFail($attachment->application_id);
        $this->serviceAccess($application->service_id);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function delivery(Request $request, NotificationDelivery $delivery): RedirectResponse
    {
        $this->permit('process_applications');
        $application = StudentApplication::findOrFail($delivery->application_id);
        $this->serviceAccess($application->service_id);
        if ($delivery->channel === 'whatsapp') {
            $request->validate(['confirmed' => ['accepted']]);
            $delivery->update(['status' => 'sent', 'sent_at' => now(), 'attempts' => $delivery->attempts + 1]);
        } elseif ($delivery->status === 'failed') {
            $delivery->update(['status' => 'pending']);
            SendApplicationNotification::dispatch($delivery->id);
        }

        return back()->with('success', 'Komunikasi diperbarui.');
    }

    public function resendLink(Request $request, StudentApplication $application): RedirectResponse
    {
        $this->permit('process_applications');
        $this->serviceAccess($application->service_id);
        $data = $request->validate(['purpose' => ['required', Rule::in(['revision', 'document', 'receipt'])]]);
        abort_if($data['purpose'] === 'revision' && $application->status !== 'needs_revision', 422);
        abort_if($data['purpose'] === 'document' && ! $application->letters()->where('status', 'published')->exists(), 422);
        $url = route('applications.access', $this->workflow->token($application, $data['purpose']));
        $this->workflow->notify($application, 'access_link', 'Tautan privat baru untuk pengajuan '.$application->reference.'. Tautan sebelumnya telah dicabut.', $url);

        return back()->with('success', 'Tautan privat baru dibuat dan masuk antrean komunikasi.');
    }

    public function forms(Request $request): View
    {
        $this->permit('manage_forms');
        $services = Service::get()->filter(fn (Service $service): bool => auth()->user()->canAccessService($service->id));
        $request->validate(['q' => ['nullable', 'string', 'max:150'], 'service_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])]]);
        $query = Form::with('service', 'category', 'activeVersion')->whereIn('service_id', $services->pluck('id'));
        if ($request->filled('q')) {
            $query->where('title', 'like', '%'.$request->string('q').'%');
        }
        if ($request->filled('service_id')) {
            $query->where('service_id', $request->integer('service_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return view('admin.forms', ['forms' => $query->latest('id')->paginate(15)->withQueryString(), 'services' => $services]);
    }

    public function newForm(): View
    {
        $this->permit('manage_forms');
        $services = Service::get()->filter(fn (Service $service): bool => auth()->user()->canAccessService($service->id));

        return view('admin.form-create', ['services' => $services, 'categories' => ServiceCategory::whereIn('service_id', $services->pluck('id'))->where('is_active', true)->get(), 'breadcrumbs' => [['label' => 'Formulir', 'url' => route('admin.forms.index')]]]);
    }

    public function createForm(Request $request): RedirectResponse
    {
        $this->permit('manage_forms');
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'service_id' => ['required', 'exists:services,id'], 'service_category_id' => ['required', Rule::exists('service_categories', 'id')->where('service_id', $request->integer('service_id'))], 'slug' => ['required', 'alpha_dash', 'max:100', 'unique:forms,slug']]);
        $this->serviceAccess((int) $data['service_id']);
        $form = DB::transaction(function () use ($data): Form {
            $form = Form::create($data);
            FormVersion::create(['form_id' => $form->id, 'version' => 1, 'status' => 'draft', 'fields' => []]);

            return $form;
        });

        return redirect()->route('admin.forms.edit', $form);
    }

    public function editForm(Form $form): View
    {
        $this->permit('manage_forms');
        $this->serviceAccess($form->service_id);
        $form->load('service', 'category', 'versions');

        return view('admin.form-builder', ['form' => $form, 'version' => $form->versions->where('status', 'draft')->sortByDesc('version')->first() ?? $form->versions->sortByDesc('version')->first(), 'breadcrumbs' => [['label' => 'Formulir', 'url' => route('admin.forms.index')]], 'versionCounts' => StudentApplication::where('form_id', $form->id)->selectRaw('form_version_id, COUNT(*) as total')->groupBy('form_version_id')->pluck('total', 'form_version_id')]);
    }

    public function saveForm(Request $request, Form $form): RedirectResponse
    {
        $this->permit('manage_forms');
        $this->serviceAccess($form->service_id);
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'opens_at' => ['nullable', 'date'], 'closes_at' => ['nullable', 'date', 'after:opens_at'], 'action' => ['required', Rule::in(['save', 'publish', 'revise', 'archive'])], 'fields' => ['nullable', 'array', 'max:40'], 'fields.*.key' => ['required', 'regex:/^[a-z][a-z0-9_]{0,49}$/', 'distinct'], 'fields.*.label' => ['required', 'string', 'max:150'], 'fields.*.type' => ['required', Rule::in(['text', 'textarea', 'number', 'date', 'select', 'multiselect', 'file'])], 'fields.*.required' => ['nullable', 'boolean'], 'fields.*.help' => ['nullable', 'string', 'max:500'], 'fields.*.group' => ['nullable', 'string', 'max:100'], 'fields.*.max' => ['nullable', 'integer', 'between:1,10000'], 'fields.*.options_text' => ['nullable', 'string', 'max:3000']]);
        DB::transaction(function () use ($form, $data): void {
            $locked = Form::whereKey($form->id)->lockForUpdate()->firstOrFail();
            if ($data['action'] === 'archive') {
                $locked->update(['status' => 'archived']);

                return;
            }
            $draft = FormVersion::where('form_id', $locked->id)->where('status', 'draft')->latest('version')->first();
            if (! $draft) {
                $active = FormVersion::find($locked->active_version_id);
                $draft = FormVersion::create(['form_id' => $locked->id, 'version' => FormVersion::where('form_id', $locked->id)->max('version') + 1, 'status' => 'draft', 'fields' => $active?->fields ?? []]);
            }
            if ($data['action'] !== 'revise') {
                $fields = [];
                foreach ($data['fields'] ?? [] as $field) {
                    $field['options'] = array_values(array_filter(array_map('trim', explode("\n", $field['options_text'] ?? ''))));
                    unset($field['options_text']);
                    $field['required'] = (bool) ($field['required'] ?? false);
                    if (in_array($field['type'], ['select', 'multiselect'], true)) {
                        abort_if(count($field['options']) === 0, 422, 'Pilihan field harus diisi.');
                    } $fields[] = $field;
                }
                $draft->update(['fields' => $fields]);
            }
            $locked->update(['title' => $data['title'], 'opens_at' => $data['opens_at'] ?? null, 'closes_at' => $data['closes_at'] ?? null]);
            if ($data['action'] === 'publish') {
                FormVersion::where('form_id', $locked->id)->where('status', 'published')->update(['status' => 'archived']);
                $draft->update(['status' => 'published', 'published_at' => now()]);
                $locked->update(['status' => 'published', 'active_version_id' => $draft->id]);
                $this->workflow->audit('form.published', 'form', $locked->id);
            }
        });

        return back()->with('success', 'Formulir berhasil diperbarui.');
    }

    public function previewForm(Form $form): View
    {
        $this->permit('manage_forms');
        $this->serviceAccess($form->service_id);
        $form->load('service');

        return view('portal.form', PortalController::shared() + ['service' => $form->service, 'form' => $form, 'version' => FormVersion::where('form_id', $form->id)->latest('version')->firstOrFail(), 'programs' => StudyProgram::where('is_active', true)->get(), 'cohorts' => Cohort::where('is_active', true)->get(), 'application' => null, 'revisionToken' => null, 'preview' => true]);
    }

    public function saveContent(Request $request, string $type): RedirectResponse
    {
        $this->permit('manage_content');
        $id = $request->integer('id');
        if (in_array($type, ['pages', 'posts'], true)) {
            $class = $type === 'pages' ? Page::class : Post::class;
            $data = $request->validate(['title' => ['required', 'string', 'max:180'], 'slug' => ['required', 'alpha_dash', 'max:150', Rule::unique($type, 'slug')->ignore($id)], 'body' => ['required', 'string', 'max:50000'], 'body_format' => ['nullable', Rule::in(['plain', 'html'])], 'status' => ['required', Rule::in(['draft', 'published', 'archived'])], 'excerpt' => [$type === 'posts' ? 'required' : 'nullable', 'string', 'max:500'], 'kind' => ['nullable', Rule::in(['news', 'announcement'])]]);
            $data['body_format'] = $data['body_format'] ?? 'plain';
            if ($data['body_format'] === 'html') {
                $data['body'] = $this->richText->sanitize($data['body']);
                if (trim($this->richText->text($data['body'], 'html')) === '') {
                    throw ValidationException::withMessages(['body' => 'Isi konten wajib diisi.']);
                }
            }
            $data['published_at'] = $data['status'] === 'published' ? now() : null;
            if ($type === 'pages') {
                unset($data['excerpt'],$data['kind']);
            }
            $record = $id ? $class::findOrFail($id) : new $class;
            $serviceData = $type === 'pages' ? $request->validate(['service_ids' => ['nullable', 'array', 'max:100'], 'service_ids.*' => ['integer', 'distinct', 'exists:services,id']]) : [];
            DB::transaction(function () use ($record, $data, $type, $serviceData): void {
                $record->fill($data)->save();
                if ($type === 'pages') {
                    $record->services()->sync($serviceData['service_ids'] ?? []);
                }
            });
        } elseif ($type === 'sections') {
            $record = app(SectionContent::class)->save($request, $id ? PageSection::findOrFail($id) : null);
        } elseif ($type === 'menus') {
            if (! $request->has('target_id') && in_array($request->input('target_type'), ['page', 'service'], true)) {
                $request->merge(['target_id' => $request->input($request->input('target_type').'_target_id')]);
            }
            $data = $request->validate(['label' => ['required', 'string', 'max:80'], 'target_type' => ['required', Rule::in(['url', 'page', 'service', 'group'])], 'target_id' => ['nullable', 'integer'], 'url' => ['nullable', 'string', 'max:500'], 'parent_id' => ['nullable', Rule::exists('menu_items', 'id')->whereNull('parent_id')], 'position' => ['required', 'integer', 'between:0,100'], 'is_visible' => ['nullable', 'boolean']]);
            if ($data['target_type'] === 'url') {
                abort_unless(preg_match('~^(https?://|/(?!/)|#)~', $data['url'] ?? ''), 422, 'Gunakan URL https, path lokal, atau anchor.');
            } elseif ($data['target_type'] !== 'group') {
                $target = $data['target_type'] === 'page' ? Page::find($data['target_id'] ?? 0) : Service::find($data['target_id'] ?? 0);
                abort_unless($target, 422, 'Target tidak ditemukan.');
            }
            if (in_array($data['target_type'], ['group', 'url'], true)) {
                $data['target_id'] = null;
            }
            if ($data['target_type'] !== 'url') {
                $data['url'] = null;
            }
            abort_if($data['target_type'] === 'group' && ! empty($data['parent_id']), 422, 'Grup dropdown harus menjadi menu utama.');
            abort_if($id && (int) ($data['parent_id'] ?? 0) === $id, 422);
            abort_if($id && ! empty($data['parent_id']) && MenuItem::where('parent_id', $id)->exists(), 422, 'Menu yang mempunyai submenu tidak dapat dijadikan submenu.');
            $data['is_visible'] = $request->boolean('is_visible');
            $record = $id ? MenuItem::findOrFail($id) : new MenuItem;
            $record->fill($data)->save();
        } elseif ($type === 'media' && $id) {
            $record = Media::findOrFail($id);
            $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
            $record->update($data);
        } elseif ($type === 'media') {
            $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120']]);
            $file = $request->file('file');
            $path = $file->store('media', 'public');
            $record = Media::create(['name' => $file->getClientOriginalName(), 'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'user_id' => auth()->id()]);
        } elseif ($type === 'settings') {
            $data = $request->validate(['value' => ['nullable', 'string', 'max:2000']]);
            $record = SiteSetting::findOrFail($id);
            if ($record->key === 'faq_items') {
                $faq = $request->validate(['faq' => ['required', 'array', 'max:20'], 'faq.*.question' => ['nullable', 'required_with:faq.*.answer', 'string', 'max:250'], 'faq.*.answer' => ['nullable', 'required_with:faq.*.question', 'string', 'max:1500']]);
                $items = collect($faq['faq'])->filter(fn (array $item): bool => ! empty($item['question']) && ! empty($item['answer']))->map(fn (array $item): array => [$item['question'], $item['answer']])->values()->all();
                abort_if(count($items) === 0, 422, 'Isi minimal satu FAQ.');
                $data = ['value' => json_encode($items, JSON_UNESCAPED_UNICODE)];
            }
            if ($record->key === 'email' && ! empty($data['value'])) {
                validator($data, ['value' => 'email'])->validate();
            }
            if ($record->key === 'whatsapp' && ! empty($data['value'])) {
                validator($data, ['value' => 'regex:/^62[0-9]{8,14}$/'])->validate();
            }
            if ($record->key === 'letter_number_format') {
                $format = $data['value'] ?? '';
                abort_unless(mb_strlen($format) <= 100 && preg_match('~^[A-Za-z0-9{}/_ -]+$~', $format), 422, 'Format nomor surat tidak valid.');
                foreach (['{sequence}', '{service}', '{year}'] as $placeholder) {
                    abort_unless(str_contains($format, $placeholder), 422, 'Format nomor harus memuat sequence, service, dan year.');
                }
                preg_match_all('/\{(.*?)\}/', $format, $matches);
                abort_if(count(array_diff($matches[1], ['sequence', 'service', 'year'])) > 0, 422, 'Placeholder nomor tidak valid.');
            }
            $record->update($data);
        } else {
            abort(404);
        }
        Cache::forget('portal.public.v2');
        $this->workflow->audit('content.saved', $type, $record->id);

        return redirect()->route('admin.content.edit', [$type, $record->id])->with('success', 'Perubahan disimpan.');
    }

    public function moveSection(Request $request, PageSection $section): RedirectResponse
    {
        $this->permit('manage_content');
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        DB::transaction(function () use ($section, $data): void {
            $sections = PageSection::where('location', $section->location)->orderBy('position')->orderBy('id')->lockForUpdate()->get();
            $index = $sections->search(fn ($s) => $s->id === $section->id);
            $other = $data['direction'] === 'up' ? $index - 1 : $index + 1;
            if ($other >= 0 && $other < $sections->count()) {
                $items = $sections->all();
                [$items[$index],$items[$other]] = [$items[$other], $items[$index]];
                foreach ($items as $position => $item) {
                    $item->update(['position' => $position]);
                }
            }
        });

        return back()->with('success', 'Urutan section diperbarui.');
    }

    private function masterConfig(string $type): array
    {
        $this->permit($type === 'categories' ? 'manage_forms' : 'manage_accounts');
        $config = match ($type) {
            'programs' => ['label' => 'Program Studi', 'model' => StudyProgram::class, 'column' => 'name'],
            'cohorts' => ['label' => 'Angkatan', 'model' => Cohort::class, 'column' => 'year'],
            'services' => ['label' => 'Layanan', 'model' => Service::class, 'column' => 'name'],
            'categories' => ['label' => 'Kategori Layanan', 'model' => ServiceCategory::class, 'column' => 'name'],
            default => abort(404),
        };

        return $config;
    }

    public function masters(Request $request, string $type = 'programs'): View
    {
        $config = $this->masterConfig($type);
        $request->validate(['q' => ['nullable', 'string', 'max:150'], 'active' => ['nullable', Rule::in(['yes', 'no'])], 'service_id' => ['nullable', 'integer']]);
        $query = $config['model']::query();
        if ($request->filled('q')) {
            $query->where($config['column'], 'like', '%'.$request->string('q').'%');
        }
        if ($request->filled('active')) {
            $query->where('is_active', $request->input('active') === 'yes');
        }
        $services = Service::get()->filter(fn (Service $service): bool => auth()->user()->canAccessService($service->id));
        if ($type === 'categories') {
            $query->whereIn('service_id', $services->pluck('id'));
            if ($request->filled('service_id')) {
                $query->where('service_id', $request->integer('service_id'));
            }
        }

        return view('admin.masters', ['type' => $type, 'label' => $config['label'], 'records' => $query->orderByDesc('id')->paginate(15)->withQueryString(), 'services' => $services]);
    }

    public function newMaster(string $type): View
    {
        $config = $this->masterConfig($type);
        abort_if($type === 'services', 404);

        return $this->masterEditor($type, $config, null);
    }

    public function editMaster(string $type, int $id): View
    {
        $config = $this->masterConfig($type);
        $record = $config['model']::findOrFail($id);
        if ($type === 'categories') {
            $this->serviceAccess($record->service_id);
        }

        return $this->masterEditor($type, $config, $record);
    }

    private function masterEditor(string $type, array $config, ?Model $record): View
    {
        return view('admin.master-edit', ['type' => $type, 'label' => $config['label'], 'record' => $record, 'services' => Service::get()->filter(fn (Service $service): bool => auth()->user()->canAccessService($service->id)), 'breadcrumbs' => [['label' => $config['label'], 'url' => route('admin.masters.index', $type)]]]);
    }

    public function saveMaster(Request $request, string $type): RedirectResponse
    {
        if ($type === 'categories') {
            $this->permit('manage_forms');
        } else {
            $this->permit('manage_accounts');
        }
        $id = $request->integer('id');
        if ($type === 'programs') {
            $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('study_programs', 'code')->ignore($id)], 'is_active' => ['nullable', 'boolean']]);
            $class = StudyProgram::class;
        } elseif ($type === 'services') {
            abort_unless($id, 422);
            $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'summary' => ['required', 'string', 'max:200'], 'description' => ['required', 'string', 'max:5000'], 'description_format' => ['nullable', Rule::in(['plain', 'html'])], 'is_active' => ['nullable', 'boolean']]);
            $data['description_format'] = $data['description_format'] ?? 'plain';
            if ($data['description_format'] === 'html') {
                $data['description'] = $this->richText->sanitize($data['description']);
                if (trim($this->richText->text($data['description'], 'html')) === '') {
                    throw ValidationException::withMessages(['description' => 'Deskripsi wajib diisi.']);
                }
            }
            $class = Service::class;
        } elseif ($type === 'cohorts') {
            $data = $request->validate(['year' => ['required', 'integer', 'between:1990,2100', Rule::unique('cohorts', 'year')->ignore($id)], 'is_active' => ['nullable', 'boolean']]);
            $class = Cohort::class;
        } elseif ($type === 'categories') {
            $data = $request->validate(['service_id' => ['required', 'exists:services,id'], 'name' => ['required', 'string', 'max:150'], 'is_active' => ['nullable', 'boolean']]);
            $this->serviceAccess((int) $data['service_id']);
            if ($id) {
                $this->serviceAccess(ServiceCategory::findOrFail($id)->service_id);
            }
            $class = ServiceCategory::class;
        } else {
            abort(404);
        }
        $data['is_active'] = $request->boolean('is_active');
        $record = $id ? $class::findOrFail($id) : new $class;
        $record->fill($data)->save();

        Cache::forget('portal.public.v2');
        $this->workflow->audit('master.updated', $type, $record->id);

        return redirect()->route('admin.masters.edit', [$type, $record->id])->with('success', 'Perubahan disimpan.');
    }

    public function accounts(Request $request): View
    {
        $this->permit('manage_accounts');
        $request->validate(['q' => ['nullable', 'string', 'max:150'], 'role_id' => ['nullable', 'integer', 'exists:roles,id']]);
        $query = User::query();
        if ($request->filled('q')) {
            $query->where(fn ($search) => $search->where('name', 'like', '%'.$request->string('q').'%')->orWhere('email', 'like', '%'.$request->string('q').'%'));
        }
        if ($request->filled('role_id')) {
            $query->whereIn('id', DB::table('role_user')->where('role_id', $request->integer('role_id'))->select('user_id'));
        }
        $users = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('admin.accounts', ['users' => $users, 'roles' => DB::table('roles')->get(), 'userRoles' => DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')->whereIn('user_id', $users->pluck('id'))->select('user_id', 'roles.name')->get()->groupBy('user_id')]);
    }

    public function newAccount(): View
    {
        $this->permit('manage_accounts');

        return $this->accountEditor(null);
    }

    public function editAccount(User $user): View
    {
        $this->permit('manage_accounts');

        return $this->accountEditor($user);
    }

    private function accountEditor(?User $user): View
    {
        return view('admin.account-edit', ['user' => $user, 'permissions' => DB::table('permissions')->get(), 'services' => Service::all(), 'roles' => DB::table('roles')->get(), 'selectedPermissions' => $user ? DB::table('permission_user')->where('user_id', $user->id)->pluck('permission_id')->all() : [], 'selectedServices' => $user ? DB::table('service_assignments')->where('user_id', $user->id)->pluck('service_id')->all() : [], 'selectedRoles' => $user ? DB::table('role_user')->where('user_id', $user->id)->pluck('role_id')->all() : [], 'breadcrumbs' => [['label' => 'Akun & Hak Akses', 'url' => route('admin.accounts.index')]]]);
    }

    public function saveAccount(Request $request): RedirectResponse
    {
        $this->permit('manage_accounts');
        $id = $request->integer('id');
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:254', Rule::unique('users', 'email')->ignore($id)], 'password' => [$id ? 'nullable' : 'required', 'string', 'min:12'], 'permissions' => ['nullable', 'array'], 'permissions.*' => ['integer', 'exists:permissions,id'], 'services' => ['nullable', 'array'], 'services.*' => ['integer', 'exists:services,id'], 'roles' => ['required', 'array', 'min:1'], 'roles.*' => ['integer', 'exists:roles,id']]);
        if ($id === auth()->id()) {
            $required = DB::table('permissions')->where('name', 'manage_accounts')->value('id');
            abort_unless(in_array($required, $data['permissions'] ?? []), 422, 'Izin pengelolaan akun sendiri harus dipertahankan.');
        }
        $user = DB::transaction(function () use ($data, $id): User {
            $user = $id ? User::findOrFail($id) : new User;
            $user->fill(collect($data)->only(['name', 'email'])->all());
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
            foreach (['permission_user' => ['permissions', 'permission_id'], 'service_assignments' => ['services', 'service_id'], 'role_user' => ['roles', 'role_id']] as $table => [$key,$column]) {
                DB::table($table)->where('user_id', $user->id)->delete();
                foreach ($data[$key] ?? [] as $value) {
                    DB::table($table)->insert(['user_id' => $user->id, $column => $value]);
                }
            }
            $this->workflow->audit('account.permissions_updated', 'user', $user->id);

            return $user;
        });

        return redirect()->route('admin.accounts.edit', $user)->with('success', 'Akun dan izin disimpan.');
    }

    public function templates(Request $request): View
    {
        $this->permit('issue_letters');
        $request->validate(['q' => ['nullable', 'string', 'max:150'], 'service_id' => ['nullable', 'integer']]);
        $services = Service::get()->filter(fn (Service $service): bool => auth()->user()->canAccessService($service->id));
        $query = LetterTemplate::whereIn('service_id', $services->pluck('id'));
        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.$request->string('q').'%');
        }
        if ($request->filled('service_id')) {
            $query->where('service_id', $request->integer('service_id'));
        }
        $templates = $query->latest('id')->paginate(15)->withQueryString();

        return view('admin.templates', ['templates' => $templates, 'versions' => LetterTemplateVersion::whereIn('id', $templates->pluck('active_version_id'))->get(['id', 'version'])->keyBy('id'), 'services' => $services]);
    }

    public function newTemplate(): View
    {
        $this->permit('issue_letters');

        return $this->templateEditor(null);
    }

    public function editTemplate(LetterTemplate $template): View
    {
        $this->permit('issue_letters');
        $this->serviceAccess($template->service_id);

        return $this->templateEditor($template);
    }

    private function templateEditor(?LetterTemplate $template): View
    {
        return view('admin.template-edit', ['template' => $template, 'version' => $template ? LetterTemplateVersion::findOrFail($template->active_version_id) : null, 'services' => Service::get()->filter(fn (Service $service): bool => auth()->user()->canAccessService($service->id)), 'breadcrumbs' => [['label' => 'Template Surat', 'url' => route('admin.templates.index')]]]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        return $this->persistTemplate($request, null);
    }

    public function saveTemplate(Request $request, LetterTemplate $template): RedirectResponse
    {
        return $this->persistTemplate($request, $template);
    }

    private function persistTemplate(Request $request, ?LetterTemplate $template): RedirectResponse
    {
        $this->permit('issue_letters');
        if ($template) {
            $this->serviceAccess($template->service_id);
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'service_id' => [$template ? 'nullable' : 'required', 'integer', 'exists:services,id'], 'body' => ['required', 'string', 'max:10000'], 'body_format' => ['nullable', Rule::in(['plain', 'html'])], 'signatory' => ['required', 'string', 'max:150'], 'title' => ['required', 'string', 'max:150']]);
        $serviceId = $template?->service_id ?? (int) $data['service_id'];
        $this->serviceAccess($serviceId);
        $data['body_format'] = $data['body_format'] ?? 'plain';
        if ($data['body_format'] === 'html') {
            $data['body'] = $this->richText->sanitize($data['body']);
            if (trim($this->richText->text($data['body'], 'html')) === '') {
                throw ValidationException::withMessages(['body' => 'Isi surat wajib diisi.']);
            }
        }
        abort_if(mb_strlen($this->richText->text($data['body'], $data['body_format'])) > 2500, 422, 'Isi surat maksimal 2500 karakter.');
        preg_match_all('/\{\{(.*?)\}\}/', $data['body'], $matches);
        abort_if(count(array_diff($matches[1], ['full_name', 'student_number', 'study_program', 'service', 'reference', 'date', 'decision'])) > 0, 422, 'Placeholder tidak diizinkan.');
        $template = DB::transaction(function () use ($template, $data, $serviceId): LetterTemplate {
            $locked = $template ? LetterTemplate::whereKey($template->id)->lockForUpdate()->firstOrFail() : LetterTemplate::create(['service_id' => $serviceId, 'name' => $data['name']]);
            $version = LetterTemplateVersion::create(collect($data)->only(['body', 'body_format', 'signatory', 'title'])->all() + ['letter_template_id' => $locked->id, 'version' => (LetterTemplateVersion::where('letter_template_id', $locked->id)->max('version') ?? 0) + 1]);
            $locked->update(['name' => $data['name'], 'active_version_id' => $version->id]);
            $this->workflow->audit('letter_template.saved', 'letter_template', $locked->id);

            return $locked;
        });

        return redirect()->route('admin.templates.edit', $template)->with('success', 'Versi template disimpan.');
    }

    public function previewLetter(Request $request, StudentApplication $application): View|Response
    {
        $this->permit('issue_letters');
        $this->serviceAccess($application->service_id);
        $application->load('service', 'studyProgram');
        $data = $request->validate(['template_id' => ['required', 'integer'], 'decision' => ['required', 'string', 'max:1000']]);
        $template = LetterTemplate::whereKey($data['template_id'])->where('service_id', $application->service_id)->firstOrFail();
        $version = LetterTemplateVersion::findOrFail($template->active_version_id);
        $replacements = ['{{full_name}}' => $application->full_name, '{{student_number}}' => $application->student_number, '{{study_program}}' => $application->studyProgram->name, '{{service}}' => $application->service->name, '{{reference}}' => $application->reference, '{{date}}' => now()->format('d/m/Y'), '{{decision}}' => $data['decision']];
        if ($version->body_format === 'html') {
            $replacements = array_map(fn (string $value): string => e($value), $replacements);
        }
        $body = strtr($version->body, $replacements);
        $bodyHtml = $this->richText->render($body, $version->body_format);
        if ($request->query('format') === 'pdf') {
            $pdf = app(PdfDocument::class)->render(['STIA LPPN', 'BAGIAN KEMAHASISWAAN', '', 'SURAT TINDAK LANJUT', 'Nomor: dialokasikan saat penerbitan', 'Tanggal: '.now()->format('d/m/Y'), '', $version->body_format === 'html' ? ['html' => $body] : $body, '', $version->title, '', $version->signatory]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename=preview.pdf']);
        }

        return view('admin.letter-preview', compact('application', 'template', 'version', 'body', 'bodyHtml', 'data') + ['breadcrumbs' => [['label' => 'Pengajuan', 'url' => route('admin.applications.index')], ['label' => $application->reference, 'url' => route('admin.applications.show', $application)]]]);
    }

    public function issueLetter(Request $request, StudentApplication $application): RedirectResponse
    {
        $this->permit('issue_letters');
        $this->serviceAccess($application->service_id);
        abort_unless(in_array($application->status, ['approved', 'rejected'], true), 422, 'Simpan keputusan sebelum menerbitkan surat.');
        $data = $request->validate(['template_id' => ['required', 'integer'], 'decision' => ['required', 'string', 'max:1000'], 'confirmed' => ['accepted']]);
        $template = LetterTemplate::whereKey($data['template_id'])->where('service_id', $application->service_id)->firstOrFail();
        $version = LetterTemplateVersion::findOrFail($template->active_version_id);
        abort_if(str_contains($version->signatory, 'atur sebelum'), 422, 'Atur pejabat penandatangan pada template terlebih dahulu.');
        $letter = $this->workflow->issue($application, (int) $data['template_id'], $data['decision']);
        $url = route('applications.access', $this->workflow->token($application, 'document'));
        $this->workflow->notify($application, 'document', 'Dokumen untuk pengajuan '.$application->reference.' sudah tersedia.', $url);

        return redirect()->route('admin.applications.show', $application)->with('success', 'Surat '.$letter->number.' berhasil diterbitkan.');
    }

    public function letter(Letter $letter): StreamedResponse
    {
        $this->permit('issue_letters');
        $application = StudentApplication::findOrFail($letter->application_id);
        $this->serviceAccess($application->service_id);
        abort_unless(in_array($letter->status, ['published', 'superseded'], true), 404);

        return Storage::disk('local')->download($letter->path, 'surat-'.str_replace('/', '-', $letter->number).'.pdf', ['Cache-Control' => 'private, no-store']);
    }
}
