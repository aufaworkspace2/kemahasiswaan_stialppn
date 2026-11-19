<?php

namespace App\Services;

use App\Jobs\SendApplicationNotification;
use App\Models\ApplicationAccessToken;
use App\Models\AuditLog;
use App\Models\Letter;
use App\Models\LetterSequence;
use App\Models\LetterTemplate;
use App\Models\LetterTemplateVersion;
use App\Models\NotificationDelivery;
use App\Models\SiteSetting;
use App\Models\StudentApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PortalWorkflow
{
    public const STATUSES = ['received' => 'Diterima', 'under_review' => 'Dalam Peninjauan', 'needs_revision' => 'Perlu Perbaikan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];

    public function __construct(private PdfDocument $pdf) {}

    public function token(StudentApplication $application, string $purpose): string
    {
        ApplicationAccessToken::where('application_id', $application->id)->where('purpose', $purpose)->whereNull('used_at')->update(['revoked_at' => now()]);
        $raw = Str::random(64);
        ApplicationAccessToken::create(['application_id' => $application->id, 'token_hash' => hash('sha256', $raw), 'purpose' => $purpose, 'expires_at' => now()->addDays($purpose === 'revision' ? 7 : 30)]);

        return $raw;
    }

    public function access(string $raw, ?string $purpose = null): ApplicationAccessToken
    {
        $token = ApplicationAccessToken::where('token_hash', hash('sha256', $raw))->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now())->firstOrFail();
        abort_if($purpose && $token->purpose !== $purpose, 404);

        return $token;
    }

    public function notify(StudentApplication $application, string $kind, string $message, ?string $url = null): NotificationDelivery
    {
        $delivery = NotificationDelivery::create(['application_id' => $application->id, 'channel' => $application->reply_channel, 'kind' => $kind, 'destination' => $application->reply_channel === 'email' ? $application->email : $application->whatsapp, 'message' => $message, 'access_url' => $url, 'status' => 'pending']);
        if ($delivery->channel === 'email') {
            DB::afterCommit(function () use ($delivery): void {
                try {
                    SendApplicationNotification::dispatch($delivery->id);
                } catch (\Throwable $exception) {
                    $delivery->update(['status' => 'failed', 'last_error' => 'Antrean email tidak tersedia. Coba kirim ulang dari panel.']);
                    report($exception);
                }
            });
        }

        return $delivery;
    }

    public function receipt(StudentApplication $application): string
    {
        if ($application->receipt_path && Storage::disk('local')->exists($application->receipt_path)) {
            return $application->receipt_path;
        }
        $application->loadMissing('service');
        $path = 'receipts/'.$application->id.'.pdf';
        Storage::disk('local')->put($path, $this->pdf->render(['STIA LPPN', 'BAGIAN KEMAHASISWAAN', '', 'BUKTI PENGAJUAN', 'Nomor: '.$application->reference, 'Diterima: '.$application->submitted_at->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB', '', 'Nama: '.$application->full_name, 'NPM: '.$application->student_number, 'Layanan: '.$application->service->name, '', 'Pengajuan telah diterima dan akan diperiksa oleh petugas.', 'Bukti ini bukan surat persetujuan atau keputusan klaim.']));
        $application->update(['receipt_path' => $path]);

        return $path;
    }

    public function audit(string $action, string $type, ?int $id): void
    {
        AuditLog::create(['user_id' => auth()->id(), 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'metadata' => []]);
    }

    public function issue(StudentApplication $application, int $templateId, string $decision): Letter
    {
        $template = LetterTemplate::whereKey($templateId)->where('service_id', $application->service_id)->firstOrFail();
        $version = LetterTemplateVersion::findOrFail($template->active_version_id);
        $letter = DB::transaction(function () use ($application, $version, $decision): Letter {
            StudentApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            $existing = Letter::where('application_id', $application->id)->where('letter_template_version_id', $version->id)->first();
            if ($existing) {
                return $existing;
            }
            LetterSequence::firstOrCreate(['service_id' => $application->service_id, 'year' => now()->year], ['next_number' => 1]);
            $sequence = LetterSequence::where('service_id', $application->service_id)->where('year', now()->year)->lockForUpdate()->firstOrFail();
            $format = SiteSetting::where('key', 'letter_number_format')->value('value') ?: '{sequence}/KM-{service}/STIA/{year}';
            $number = strtr($format, ['{sequence}' => sprintf('%04d', $sequence->next_number), '{service}' => sprintf('%02d', $application->service_id), '{year}' => (string) now()->year]);
            $sequence->increment('next_number');
            $application->loadMissing('studyProgram', 'service');
            $data = ['full_name' => $application->full_name, 'student_number' => $application->student_number, 'study_program' => $application->studyProgram->name, 'service' => $application->service->name, 'reference' => $application->reference, 'date' => now()->format('d/m/Y'), 'decision' => $decision];
            $body = $version->body;
            foreach ($data as $key => $value) {
                $body = str_replace('{{'.$key.'}}', $version->body_format === 'html' ? e($value) : $value, $body);
            }

            return Letter::create(['application_id' => $application->id, 'letter_template_version_id' => $version->id, 'user_id' => auth()->id(), 'number' => $number, 'snapshot' => ['body' => $body, 'body_format' => $version->body_format ?? 'plain', 'signatory' => $version->signatory, 'title' => $version->title, 'name' => $application->full_name, 'date' => now()->format('d/m/Y')], 'status' => 'pending']);
        });
        if ($letter->status !== 'published') {
            $path = 'letters/'.$letter->id.'.pdf';
            Storage::disk('local')->put($path, $this->pdf->render(['STIA LPPN', 'BAGIAN KEMAHASISWAAN', '', 'SURAT TINDAK LANJUT', 'Nomor: '.$letter->number, 'Tanggal: '.$letter->snapshot['date'], '', ($letter->snapshot['body_format'] ?? 'plain') === 'html' ? ['html' => $letter->snapshot['body']] : $letter->snapshot['body'], '', $letter->snapshot['title'], '', $letter->snapshot['signatory']]));
            $previous = Letter::where('application_id', $application->id)->where('status', 'published')->where('id', '!=', $letter->id)->latest('id')->first();
            if ($previous) {
                $previous->update(['status' => 'superseded']);
            }
            $letter->update(['path' => $path, 'status' => 'published', 'replaces_id' => $previous?->id]);
            $this->audit('letter.published', 'letter', $letter->id);
        }

        return $letter;
    }
}
