<?php

namespace App\Http\Controllers;

use App\Models\ApplicationAccessToken;
use App\Models\ApplicationAttachment;
use App\Models\ApplicationRevision;
use App\Models\ApplicationStatusLog;
use App\Models\Cohort;
use App\Models\Form;
use App\Models\Letter;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StudentApplication;
use App\Models\StudyProgram;
use App\Services\PortalWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationController extends Controller
{
    public function __construct(private PortalWorkflow $workflow) {}

    private function validateInput(Request $request, array $fields, bool $revision = false): array
    {
        $rules = ['student_number' => ['required', 'string', 'regex:/^[0-9]{4,30}$/'], 'full_name' => ['required', 'string', 'max:150'], 'study_program_id' => ['required', Rule::exists('study_programs', 'id')->where('is_active', true)], 'cohort_id' => ['required', Rule::exists('cohorts', 'id')->where('is_active', true)], 'reply_channel' => ['required', Rule::in(['email', 'whatsapp'])], 'email' => ['nullable', 'required_if:reply_channel,email', 'email', 'max:254'], 'whatsapp' => ['nullable', 'required_if:reply_channel,whatsapp', 'regex:/^(?:\+?62|0)[0-9\s-]{8,16}$/'], 'website' => ['prohibited'], 'consent' => ['accepted']];
        if (! $revision) {
            $rules['idempotency_key'] = ['required', 'uuid'];
            $rules['form_version_id'] = ['required', 'integer'];
        }
        foreach ($fields as $field) {
            $base = ($field['required'] ?? false) ? 'required' : 'nullable';
            if ($revision && $field['type'] === 'file') {
                $base = 'nullable';
            }
            $key = 'answers.'.$field['key'];
            $rules[$key] = match ($field['type']) {
                'number' => [$base, 'numeric', 'between:-1000000000,1000000000'], 'date' => [$base, 'date_format:Y-m-d'],
                'select' => [$base, Rule::in($field['options'] ?? [])], 'multiselect' => [$base, 'array', 'max:30'],
                'file' => [$base, 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], default => [$base, 'string', 'max:'.min((int) ($field['max'] ?? 2000), 10000)],
            };
            if ($field['type'] === 'multiselect') {
                $rules[$key.'.*'] = [Rule::in($field['options'] ?? [])];
            }
        }

        return $request->validate($rules, ['required' => 'Kolom :attribute wajib diisi.', 'accepted' => 'Persetujuan diperlukan sebelum mengirim.', 'email' => 'Masukkan alamat email yang valid.', 'regex' => 'Format :attribute tidak valid.', 'max' => 'Ukuran atau panjang :attribute melebihi batas.', 'prohibited' => 'Pengajuan tidak dapat diproses.'], ['student_number' => 'NPM', 'full_name' => 'nama lengkap', 'study_program_id' => 'program studi', 'cohort_id' => 'angkatan', 'whatsapp' => 'nomor WhatsApp']);
    }

    public function store(Request $request, Service $service, Form $form): RedirectResponse
    {
        abort_unless($service->is_active && $form->service_id === $service->id && $form->isOpen() && ServiceCategory::whereKey($form->service_category_id)->where('is_active', true)->exists(), 404);
        $form->load('activeVersion');
        $data = $this->validateInput($request, $form->activeVersion->fields);
        if ((int) $data['form_version_id'] !== $form->active_version_id) {
            return back()->withErrors(['form_version_id' => 'Formulir telah diperbarui. Muat ulang halaman dan tinjau versi terbaru.'])->withInput();
        }
        $stored = [];
        try {
            [$application,$raw] = DB::transaction(function () use ($request, $form, $data, &$stored): array {
                $locked = Form::whereKey($form->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->isOpen() && $locked->active_version_id === (int) $data['form_version_id'], 409, 'Formulir berubah. Muat ulang halaman.');
                $existing = StudentApplication::where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    abort_unless($request->session()->has('application_access.'.$existing->id), 409);

                    return [$existing, $request->session()->get('application_access.'.$existing->id)];
                }
                $identity = collect($data)->only(['student_number', 'full_name', 'study_program_id', 'cohort_id', 'reply_channel', 'email', 'whatsapp'])->all();
                if (! empty($identity['whatsapp'])) {
                    $number = preg_replace('/\D/', '', $identity['whatsapp']);
                    $identity['whatsapp'] = str_starts_with($number, '0') ? '62'.substr($number, 1) : $number;
                }
                $answers = collect($data['answers'] ?? [])->only(collect($form->activeVersion->fields)->where('type', '!=', 'file')->pluck('key')->all())->all();
                $application = StudentApplication::create($identity + ['reference' => 'KM-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(5))), 'form_id' => $form->id, 'form_version_id' => $form->active_version_id, 'service_id' => $form->service_id, 'service_category_id' => $form->service_category_id, 'answers' => $answers, 'status' => 'received', 'idempotency_key' => $data['idempotency_key'], 'submitted_at' => now()]);
                $revision = ApplicationRevision::create(['application_id' => $application->id, 'identity' => $identity, 'answers' => $answers]);
                $this->saveFiles($request, $form->activeVersion->fields, $application, $revision, $stored);
                ApplicationStatusLog::create(['application_id' => $application->id, 'to_status' => 'received', 'message' => 'Pengajuan diterima.']);
                $raw = $this->workflow->token($application, 'receipt');
                $this->workflow->notify($application, 'received', 'Pengajuan '.$application->reference.' telah diterima. Simpan nomor pengajuan untuk komunikasi berikutnya.', route('applications.access', $raw));

                return [$application, $raw];
            });
        } catch (\Throwable $e) {
            foreach ($stored as $path) {
                Storage::disk('local')->delete($path);
            } throw $e;
        }
        $request->session()->put('application_access.'.$application->id, $raw);

        return redirect()->route('applications.access', $raw);
    }

    private function saveFiles(Request $request, array $fields, StudentApplication $application, ApplicationRevision $revision, array &$stored): void
    {
        foreach ($fields as $field) {
            if ($field['type'] !== 'file' || ! $request->hasFile('answers.'.$field['key'])) {
                continue;
            }
            $file = $request->file('answers.'.$field['key']);
            $path = $file->store('applications/'.$application->id, 'local');
            $stored[] = $path;
            ApplicationAttachment::create(['application_id' => $application->id, 'application_revision_id' => $revision->id, 'field_key' => $field['key'], 'original_name' => $file->getClientOriginalName(), 'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);
        }
    }

    public function access(string $token): View
    {
        $access = $this->workflow->access($token);
        $application = StudentApplication::with('service', 'studyProgram', 'cohort', 'letters')->findOrFail($access->application_id);
        if ($access->purpose === 'revision') {
            abort_unless($application->status === 'needs_revision', 404);
            $application->load('version', 'form');

            return view('portal.form', PortalController::shared() + ['application' => $application, 'service' => $application->service, 'form' => $application->form, 'version' => $application->version, 'programs' => StudyProgram::where('is_active', true)->get(), 'cohorts' => Cohort::where('is_active', true)->orderByDesc('year')->get(), 'revisionToken' => $token, 'revisionMessage' => ApplicationStatusLog::where('application_id', $application->id)->where('to_status', 'needs_revision')->latest('id')->value('message')]);
        }

        return view('portal.confirmation', PortalController::shared() + compact('application', 'token') + ['purpose' => $access->purpose]);
    }

    public function revise(Request $request, string $token): RedirectResponse
    {
        $access = $this->workflow->access($token, 'revision');
        $application = StudentApplication::with('version')->findOrFail($access->application_id);
        $data = $this->validateInput($request, $application->version->fields, true);
        $stored = [];
        try {
            DB::transaction(function () use ($request, $access, $application, $data, &$stored): void {
                $locked = ApplicationAccessToken::whereKey($access->id)->lockForUpdate()->firstOrFail();
                abort_if($locked->used_at || $locked->revoked_at || $locked->expires_at->isPast(), 404);
                $application = StudentApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
                $application->load('version');
                abort_unless($application->status === 'needs_revision', 409);
                $identity = collect($data)->only(['student_number', 'full_name', 'study_program_id', 'cohort_id', 'reply_channel', 'email', 'whatsapp'])->all();
                if (! empty($identity['whatsapp'])) {
                    $number = preg_replace('/\D/', '', $identity['whatsapp']);
                    $identity['whatsapp'] = str_starts_with($number, '0') ? '62'.substr($number, 1) : $number;
                }
                $answers = collect($data['answers'] ?? [])->only(collect($application->version->fields)->where('type', '!=', 'file')->pluck('key')->all())->all();
                $application->update($identity + ['answers' => $answers, 'status' => 'under_review']);
                $revision = ApplicationRevision::create(['application_id' => $application->id, 'identity' => $identity, 'answers' => $answers]);
                $this->saveFiles($request, $application->version->fields, $application, $revision, $stored);
                $locked->update(['used_at' => now()]);
                ApplicationStatusLog::create(['application_id' => $application->id, 'from_status' => 'needs_revision', 'to_status' => 'under_review', 'message' => 'Mahasiswa mengirim perbaikan.']);
                $this->workflow->notify($application, 'revised', 'Perbaikan pengajuan '.$application->reference.' telah diterima.');
            });
        } catch (\Throwable $e) {
            foreach ($stored as $path) {
                Storage::disk('local')->delete($path);
            } throw $e;
        }

        return redirect()->route('applications.access', $this->workflow->token($application, 'receipt'))->with('success', 'Perbaikan berhasil dikirim.');
    }

    public function receipt(string $token): StreamedResponse
    {
        $access = $this->workflow->access($token, 'receipt');
        $application = StudentApplication::findOrFail($access->application_id);

        return Storage::disk('local')->download($this->workflow->receipt($application), 'bukti-'.$application->reference.'.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
    }

    public function letter(string $token, Letter $letter): StreamedResponse
    {
        $access = $this->workflow->access($token, 'document');
        abort_unless($letter->application_id === $access->application_id && $letter->status === 'published', 404);

        return Storage::disk('local')->download($letter->path, 'surat-'.str_replace('/', '-', $letter->number).'.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
    }
}
