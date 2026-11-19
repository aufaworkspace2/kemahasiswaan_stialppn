<?php

use App\Jobs\SendApplicationNotification;
use App\Models\ApplicationAccessToken;
use App\Models\ApplicationAttachment;
use App\Models\ApplicationRevision;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\LetterTemplateVersion;
use App\Models\MenuItem;
use App\Models\NotificationDelivery;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SiteSetting;
use App\Models\StudentApplication;
use App\Models\User;
use App\Services\PdfDocument;
use App\Services\PortalWorkflow;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

function portalSeed(): void
{
    test()->seed(DatabaseSeeder::class);
}
function portalUser(array $permissions = [], array $services = [], bool $administrator = false): User
{
    $user = User::factory()->create();
    foreach ($permissions as $permission) {
        DB::table('permission_user')->insert(['user_id' => $user->id, 'permission_id' => DB::table('permissions')->where('name', $permission)->value('id')]);
    }
    foreach ($services as $slug) {
        DB::table('service_assignments')->insert(['user_id' => $user->id, 'service_id' => Service::where('slug', $slug)->value('id')]);
    }
    if ($administrator) {
        DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => DB::table('roles')->where('name', 'administrator')->value('id')]);
    }

    return $user;
}
function portalPayload(Form $form): array
{
    $answers = match ($form->service->slug) {
        'beasiswa' => ['program' => 'Prestasi', 'achievement' => 'Juara lomba kampus'],
        'konseling' => ['preferred_date' => '2026-12-01', 'contact_method' => 'Tatap muka', 'summary' => 'Informasi pribadi'],
        'ormawa' => ['organization' => 'BEM', 'activity' => 'Seminar', 'activity_date' => '2026-12-01', 'location' => 'Kampus', 'proposal' => UploadedFile::fake()->create('proposal.pdf', 50, 'application/pdf')],
        'asuransi' => ['incident_date' => '2026-11-01', 'incident' => 'Kejadian uji', 'claim_type' => 'Pelaporan awal'],
    };

    return ['student_number' => '00123456', 'full_name' => 'Mahasiswa José', 'study_program_id' => 1, 'cohort_id' => 1, 'reply_channel' => 'whatsapp', 'whatsapp' => '081234567890', 'answers' => $answers, 'consent' => '1', 'idempotency_key' => (string) Str::uuid(), 'form_version_id' => $form->active_version_id];
}
function portalSubmit(string $slug = 'beasiswa'): StudentApplication
{
    $form = Form::with('service')->where('slug', $slug.'-umum')->firstOrFail();
    test()->post(route('applications.store', [$slug, $form->slug]), portalPayload($form))->assertRedirect();

    return StudentApplication::latest('id')->firstOrFail();
}

it('renders the public portal and each published service and form', function () {
    portalSeed();
    $this->get('/')->assertOk()->assertSee('Layanan Kemahasiswaan STIA LPPN')->assertSee('STIA LPPN');
    foreach (Service::all() as $service) {
        $this->get('/layanan/'.$service->slug)->assertOk()->assertSee($service->name);
        $this->get('/layanan/'.$service->slug.'/'.$service->slug.'-umum')->assertOk()->assertSee('Identitas Mahasiswa');
    }
    $this->get('/berita')->assertOk();
    $this->get('/admin/login')->assertOk();
});

it('accepts each service without an account and stores a private revision and delivery', function (string $slug) {
    portalSeed();
    Storage::fake('local');
    Queue::fake();
    $application = portalSubmit($slug);
    expect($application->student_number)->toBe('00123456');
    expect($application->whatsapp)->toBe('6281234567890');
    $this->assertDatabaseHas('application_status_logs', ['application_id' => $application->id, 'to_status' => 'received']);
    $this->assertDatabaseHas('application_revisions', ['application_id' => $application->id]);
    $this->assertDatabaseHas('notification_deliveries', ['application_id' => $application->id, 'status' => 'pending', 'channel' => 'whatsapp']);
    if ($slug === 'ormawa') {
        $attachment = ApplicationAttachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
    }
    Queue::assertNothingPushed();
})->with(['beasiswa', 'konseling', 'ormawa', 'asuransi']);

it('rejects missing core identity even if browser validation is bypassed', function () {
    portalSeed();
    Queue::fake();
    $form = Form::with('service')->first();
    $payload = portalPayload($form);
    foreach (['student_number', 'full_name', 'study_program_id', 'cohort_id'] as $key) {
        unset($payload[$key]);
    }
    $this->post(route('applications.store', ['beasiswa', $form->slug]), $payload)->assertSessionHasErrors(['student_number', 'full_name', 'study_program_id', 'cohort_id']);
    $this->assertDatabaseCount('applications', 0);
    Queue::assertNothingPushed();
});

it('rejects invalid dynamic answers and executable attachments', function () {
    portalSeed();
    Storage::fake('local');
    $form = Form::with('service')->where('slug', 'ormawa-umum')->first();
    $payload = portalPayload($form);
    $payload['answers']['proposal'] = UploadedFile::fake()->create('script.php', 1, 'text/x-php');
    unset($payload['answers']['activity']);
    $this->post(route('applications.store', ['ormawa', $form->slug]), $payload)->assertSessionHasErrors(['answers.proposal', 'answers.activity']);
    $this->assertDatabaseCount('applications', 0);
});

it('stores one application when the same request is submitted twice', function () {
    portalSeed();
    $form = Form::with('service')->first();
    $payload = portalPayload($form);
    $url = route('applications.store', ['beasiswa', $form->slug]);
    $first = $this->post($url, $payload);
    $second = $this->post($url, $payload);
    $first->assertRedirect();
    $second->assertRedirect($first->headers->get('Location'));
    $this->assertDatabaseCount('applications', 1);
    $this->assertDatabaseCount('notification_deliveries', 1);
});

it('does not reveal another session application with a reused idempotency key', function () {
    portalSeed();
    $form = Form::with('service')->first();
    $payload = portalPayload($form);
    $url = route('applications.store', ['beasiswa', $form->slug]);
    $this->post($url, $payload)->assertRedirect();
    $this->flushSession();
    $this->post($url, $payload)->assertConflict();
    $this->assertDatabaseCount('applications', 1);
});

it('queues an email after accepting the application', function () {
    portalSeed();
    Queue::fake();
    $form = Form::with('service')->first();
    $payload = portalPayload($form);
    $payload['reply_channel'] = 'email';
    $payload['email'] = 'student@example.test';
    $this->post(route('applications.store', ['beasiswa', $form->slug]), $payload)->assertRedirect();
    Queue::assertPushed(SendApplicationNotification::class);
    $this->assertDatabaseHas('notification_deliveries', ['channel' => 'email', 'status' => 'pending']);
});

it('rejects a stale form version and closed forms', function () {
    portalSeed();
    $form = Form::with('service')->first();
    $payload = portalPayload($form);
    $payload['form_version_id'] = 999;
    $this->post(route('applications.store', ['beasiswa', $form->slug]), $payload)->assertSessionHasErrors('form_version_id');
    $form->update(['closes_at' => now()->subMinute()]);
    $this->post(route('applications.store', ['beasiswa', $form->slug]), portalPayload($form))->assertNotFound();
    $this->assertDatabaseCount('applications', 0);
});

it('requires authentication and action permissions for internal routes', function () {
    portalSeed();
    $this->get('/admin')->assertRedirect('/admin/login');
    $user = portalUser();
    $this->actingAs($user)->get('/admin/applications')->assertForbidden();
    $this->get('/admin/forms')->assertForbidden();
    $this->get('/admin/content/pages')->assertForbidden();
    $this->get('/admin/accounts')->assertForbidden();
    $this->get('/admin/templates')->assertForbidden();
});

it('isolates counseling details attachments and letters even from an unassigned administrator', function () {
    portalSeed();
    Storage::fake('local');
    $application = portalSubmit('konseling');
    $revision = ApplicationRevision::first();
    $attachment = ApplicationAttachment::factory()->create(['application_id' => $application->id, 'application_revision_id' => $revision->id, 'field_key' => 'support', 'original_name' => 'private.pdf', 'path' => 'private.pdf', 'mime_type' => 'application/pdf', 'size' => 10]);
    $user = portalUser(['process_applications', 'issue_letters'], [], true);
    $this->actingAs($user)->get('/admin/applications/'.$application->id)->assertNotFound();
    $this->get('/admin/attachments/'.$attachment->id)->assertNotFound();
    $this->get('/admin/applications')->assertOk()->assertDontSee($application->reference);
});

it('lets an assigned counselor read counseling details', function () {
    portalSeed();
    $application = portalSubmit('konseling');
    $user = portalUser(['process_applications'], ['konseling']);
    $this->actingAs($user)->get('/admin/applications/'.$application->id)->assertOk()->assertSee('Informasi pribadi');
});

it('renders all authorized admin pages', function () {
    portalSeed();
    $user = portalUser(['manage_accounts', 'manage_content', 'manage_forms', 'process_applications', 'issue_letters'], [], true);
    foreach (['/admin', '/admin/applications', '/admin/forms', '/admin/forms/1', '/admin/forms/1/preview', '/admin/content/pages', '/admin/content/posts', '/admin/content/sections', '/admin/content/menus', '/admin/content/media', '/admin/content/settings', '/admin/masters', '/admin/accounts', '/admin/templates'] as $url) {
        $this->actingAs($user)->get($url)->assertOk();
    }
});

it('revises the same application once with its original schema', function () {
    portalSeed();
    Storage::fake('local');
    $application = portalSubmit();
    $application->update(['status' => 'needs_revision']);
    $workflow = app(PortalWorkflow::class);
    $token = $workflow->token($application, 'revision');
    $payload = portalPayload(Form::with('service')->first());
    $payload['answers']['achievement'] = 'Perbaikan data';
    $this->get('/akses/'.$token)->assertOk();
    $this->post('/akses/'.$token.'/perbaikan', $payload)->assertRedirect();
    $this->assertDatabaseCount('applications', 1);
    $this->assertDatabaseCount('application_revisions', 2);
    $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'under_review']);
    $this->post('/akses/'.$token.'/perbaikan', $payload)->assertNotFound();
});

it('rejects expired revoked and wrong-purpose tokens', function () {
    portalSeed();
    $application = portalSubmit();
    $workflow = app(PortalWorkflow::class);
    $revision = $workflow->token($application, 'revision');
    $this->get('/akses/'.$revision.'/bukti')->assertNotFound();
    $token = $workflow->token($application, 'receipt');
    ApplicationAccessToken::where('purpose', 'receipt')->update(['expires_at' => now()->subMinute()]);
    $this->get('/akses/'.$token)->assertNotFound();
    $old = $workflow->token($application, 'receipt');
    $workflow->token($application, 'receipt');
    $this->get('/akses/'.$old)->assertNotFound();
});

it('downloads the private submission receipt as a real pdf', function () {
    portalSeed();
    Storage::fake('local');
    $application = portalSubmit();
    $token = app(PortalWorkflow::class)->token($application, 'receipt');
    $response = $this->get('/akses/'.$token.'/bukti')->assertOk()->assertDownload('bukti-'.$application->reference.'.pdf');
    expect($response->streamedContent())->toStartWith('%PDF-1.4');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('creates immutable form revisions and publishes only validated fields', function () {
    portalSeed();
    $application = portalSubmit();
    $user = portalUser(['manage_forms'], ['beasiswa']);
    $form = Form::first();
    $old = $form->active_version_id;
    $this->actingAs($user)->post('/admin/forms/'.$form->id, ['title' => $form->title, 'action' => 'revise'])->assertRedirect();
    $this->post('/admin/forms/'.$form->id, ['title' => $form->title, 'action' => 'publish', 'fields' => [['key' => 'new_question', 'label' => 'Pertanyaan baru', 'type' => 'text', 'required' => 1, 'max' => 100]]])->assertRedirect();
    expect($form->fresh()->active_version_id)->not->toBe($old);
    expect($application->fresh()->form_version_id)->toBe($old);
    expect(FormVersion::find($old)->fields[0]['label'])->toBe('Program beasiswa');
    $this->assertDatabaseHas('audit_logs', ['action' => 'form.published']);
});

it('rejects builder keys that could change core identity or inject validation paths', function () {
    portalSeed();
    $user = portalUser(['manage_forms'], ['beasiswa']);
    $this->actingAs($user)->post('/admin/forms/1', ['title' => 'Form', 'action' => 'publish', 'fields' => [['key' => 'name.*', 'label' => 'Unsafe', 'type' => 'text']]])->assertSessionHasErrors('fields.0.key');
});

it('requires a valid transition and records communication separately', function () {
    portalSeed();
    $application = portalSubmit();
    $user = portalUser(['process_applications'], ['beasiswa']);
    $this->actingAs($user)->post('/admin/applications/'.$application->id.'/status', ['status' => 'approved'])->assertUnprocessable();
    $this->post('/admin/applications/'.$application->id.'/status', ['status' => 'under_review'])->assertRedirect();
    $this->post('/admin/applications/'.$application->id.'/status', ['status' => 'needs_revision'])->assertSessionHasErrors('message');
    $this->post('/admin/applications/'.$application->id.'/status', ['status' => 'needs_revision', 'message' => 'Perbaiki dokumen.'])->assertRedirect();
    $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'needs_revision']);
    $this->assertDatabaseHas('notification_deliveries', ['kind' => 'status', 'status' => 'pending']);
});

it('marks manual whatsapp as sent only after officer confirmation', function () {
    portalSeed();
    $application = portalSubmit();
    $delivery = NotificationDelivery::first();
    $user = portalUser(['process_applications'], ['beasiswa']);
    $this->actingAs($user)->post('/admin/deliveries/'.$delivery->id)->assertSessionHasErrors('confirmed');
    expect($delivery->fresh()->status)->toBe('pending');
    $this->post('/admin/deliveries/'.$delivery->id, ['confirmed' => 1])->assertRedirect();
    expect($delivery->fresh()->status)->toBe('sent');
    expect($application->fresh()->status)->toBe('received');
});

it('sends an email job once and records transport acceptance', function () {
    portalSeed();
    Mail::fake();
    $application = portalSubmit();
    $delivery = NotificationDelivery::first();
    $delivery->update(['channel' => 'email', 'destination' => 'student@example.test']);
    $job = new SendApplicationNotification($delivery->id);
    $job->handle();
    $job->handle();
    expect($delivery->fresh()->status)->toBe('sent');
    expect($delivery->fresh()->attempts)->toBe(1);
});

it('allocates one letter number for repeated issuance and preserves the snapshot', function () {
    portalSeed();
    Storage::fake('local');
    $application = portalSubmit();
    $user = portalUser(['issue_letters'], ['beasiswa']);
    $this->actingAs($user);
    $workflow = app(PortalWorkflow::class);
    $template = LetterTemplate::first();
    $first = $workflow->issue($application, $template->id, 'Disetujui.');
    $second = $workflow->issue($application, $template->id, 'Changed text must not replace snapshot.');
    expect($second->id)->toBe($first->id);
    expect($second->snapshot['body'])->toContain('Disetujui.')->not->toContain('Changed text');
    $this->assertDatabaseCount('letters', 1);
    Storage::disk('local')->assertExists($first->path);
    $token = $workflow->token($application, 'document');
    $this->get('/akses/'.$token.'/surat/'.$first->id)->assertOk();
    $this->get('/akses/'.$token.'/bukti')->assertNotFound();
});

it('hides drafts and follows page slugs from menu targets', function () {
    portalSeed();
    $page = Page::factory()->create(['title' => 'Draft', 'slug' => 'draft', 'body' => 'Hidden body', 'status' => 'draft']);
    MenuItem::factory()->create(['label' => 'Hidden navigation', 'target_type' => 'page', 'target_id' => $page->id, 'position' => 1, 'is_visible' => true]);
    $this->get('/halaman/draft')->assertNotFound();
    $this->get('/')->assertDontSee('Hidden navigation');
    $page->update(['slug' => 'published-page', 'status' => 'published']);
    Cache::forget('portal.public.v2');
    $this->get('/')->assertSee('/halaman/published-page', false);
    $this->get('/halaman/published-page')->assertOk();
});

it('escapes cms content and disallows javascript menu targets', function () {
    portalSeed();
    $page = Page::factory()->create(['title' => 'Safe', 'slug' => 'safe', 'body' => '<script>alert(1)</script>', 'status' => 'published']);
    $this->get('/halaman/safe')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    $user = portalUser(['manage_content']);
    $this->actingAs($user)->post('/admin/content/menus', ['label' => 'Bad', 'target_type' => 'url', 'url' => 'javascript:alert(1)', 'position' => 0, 'is_visible' => 1])->assertUnprocessable();
    $this->assertDatabaseMissing('menu_items', ['label' => 'Bad']);
});

it('lets editors reorder sections with server actions', function () {
    portalSeed();
    $user = portalUser(['manage_content']);
    $first = PageSection::orderBy('position')->first();
    $this->actingAs($user)->post('/admin/sections/'.$first->id.'/move', ['direction' => 'down'])->assertRedirect();
    expect($first->fresh()->position)->toBe(1);
});

it('does not permit an officer to grant account permissions', function () {
    portalSeed();
    $user = portalUser(['process_applications'], ['beasiswa']);
    $this->actingAs($user)->post('/admin/accounts', ['name' => 'Intruder', 'email' => 'intruder@example.test', 'password' => 'longpassword123'])->assertForbidden();
    $this->assertDatabaseMissing('users', ['email' => 'intruder@example.test']);
});

it('survives repeated public requests using serialized database cache', function () {
    portalSeed();
    config(['cache.default' => 'database']);
    $this->get('/')->assertOk()->assertSee('Beasiswa');
    $this->get('/')->assertOk()->assertSee('Beasiswa');
    $this->get('/layanan/beasiswa')->assertOk();
    Cache::forget('portal.public.v2');
});

it('encrypts private access links in notification storage', function () {
    portalSeed();
    $application = portalSubmit();
    $delivery = NotificationDelivery::first();
    expect($delivery->access_url)->toContain('/akses/');
    expect(DB::table('notification_deliveries')->where('id', $delivery->id)->value('access_url'))->not->toContain('/akses/');
});

it('combines service category program cohort status and date filters', function () {
    portalSeed();
    $this->travelTo(now()->setDate(2026, 11, 18));
    $application = portalSubmit();
    $other = portalSubmit('asuransi');
    $user = portalUser(['process_applications'], [], true);
    $query = ['service_id' => $application->service_id, 'service_category_id' => $application->service_category_id, 'study_program_id' => $application->study_program_id, 'cohort_id' => $application->cohort_id, 'status' => 'received', 'from' => '2026-11-18', 'until' => '2026-11-18'];
    $this->actingAs($user)->get('/admin/applications?'.http_build_query($query))->assertOk()->assertSee($application->reference)->assertDontSee($other->reference);
});

it('retries a failed email from the panel without changing application status', function () {
    portalSeed();
    Queue::fake();
    $application = portalSubmit();
    $delivery = NotificationDelivery::first();
    $delivery->update(['channel' => 'email', 'status' => 'failed', 'destination' => 'student@example.test']);
    $user = portalUser(['process_applications'], ['beasiswa']);
    $this->actingAs($user)->post('/admin/deliveries/'.$delivery->id)->assertRedirect();
    Queue::assertPushed(SendApplicationNotification::class, fn ($job) => $job->deliveryId === $delivery->id);
    expect($delivery->fresh()->status)->toBe('pending');
    expect($application->fresh()->status)->toBe('received');
});

it('keeps allocated letter numbers after a pdf generation failure', function () {
    portalSeed();
    Storage::fake('local');
    $application = portalSubmit();
    $user = portalUser(['issue_letters'], ['beasiswa']);
    $this->actingAs($user);
    $template = LetterTemplate::first();
    $broken = Mockery::mock(PdfDocument::class);
    $broken->shouldReceive('render')->once()->andThrow(new RuntimeException('Synthetic PDF failure'));
    expect(fn () => (new PortalWorkflow($broken))->issue($application, $template->id, 'Approved'))->toThrow(RuntimeException::class);
    $pending = Letter::firstOrFail();
    expect($pending->status)->toBe('pending');
    $finished = (new PortalWorkflow(new PdfDocument))->issue($application, $template->id, 'Approved');
    expect($finished->number)->toBe($pending->number);
    expect($finished->status)->toBe('published');
    $this->assertDatabaseCount('letters', 1);
});

it('tracks replacement letters while keeping the earlier published file', function () {
    portalSeed();
    Storage::fake('local');
    $application = portalSubmit();
    $this->actingAs(portalUser(['issue_letters'], ['beasiswa']));
    $workflow = app(PortalWorkflow::class);
    $template = LetterTemplate::first();
    $first = $workflow->issue($application, $template->id, 'First decision');
    $oldVersion = LetterTemplateVersion::find($template->active_version_id);
    $newVersion = LetterTemplateVersion::factory()->create(['letter_template_id' => $template->id, 'version' => 2, 'body' => $oldVersion->body, 'signatory' => 'Officer', 'title' => 'Student affairs']);
    $template->update(['active_version_id' => $newVersion->id]);
    $replacement = $workflow->issue($application, $template->id, 'Corrected decision');
    expect($replacement->replaces_id)->toBe($first->id);
    expect($first->fresh()->status)->toBe('superseded');
    Storage::disk('local')->assertExists($first->path);
    expect($replacement->number)->not->toBe($first->number);
});

it('previews the same pdf renderer before letter issuance', function () {
    portalSeed();
    $application = portalSubmit();
    $application->update(['status' => 'approved']);
    $user = portalUser(['issue_letters'], ['beasiswa']);
    $response = $this->actingAs($user)->get('/admin/applications/'.$application->id.'/letter-preview?'.http_build_query(['template_id' => LetterTemplate::first()->id, 'decision' => 'Test decision', 'format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-1.4');
    $this->assertDatabaseCount('letters', 0);
});

it('rejects a deactivated category on public submission', function () {
    portalSeed();
    $form = Form::with('service')->first();
    ServiceCategory::whereKey($form->service_category_id)->update(['is_active' => false]);
    $this->post(route('applications.store', ['beasiswa', $form->slug]), portalPayload($form))->assertNotFound();
    $this->assertDatabaseCount('applications', 0);
});

it('keeps private page views separate from the public submission limiter', function () {
    portalSeed();
    $application = portalSubmit();
    $token = app(PortalWorkflow::class)->token($application, 'receipt');
    foreach (range(1, 8) as $attempt) {
        $this->get('/akses/'.$token)->assertOk();
    }
    $form = Form::with('service')->first();
    $this->post(route('applications.store', ['beasiswa', $form->slug]), portalPayload($form))->assertRedirect();
    $this->assertDatabaseCount('applications', 2);
});

it('generates receipt and service-specific letters for all four services', function (string $slug) {
    portalSeed();
    Storage::fake('local');
    $application = portalSubmit($slug);
    $this->actingAs(portalUser(['issue_letters'], [$slug]));
    $workflow = app(PortalWorkflow::class);
    $receipt = $workflow->receipt($application);
    $letter = $workflow->issue($application, LetterTemplate::where('service_id', $application->service_id)->value('id'), 'Konfirmasi layanan.');
    Storage::disk('local')->assertExists($receipt);
    Storage::disk('local')->assertExists($letter->path);
    expect(Storage::disk('local')->get($receipt))->toStartWith('%PDF-1.4');
    expect($letter->snapshot['body'])->toContain($application->reference)->toContain('Konfirmasi layanan.');
})->with(['beasiswa', 'konseling', 'ormawa', 'asuransi']);

it('rejects numbering formats without a service year and sequence', function () {
    portalSeed();
    $user = portalUser(['manage_content']);
    $setting = SiteSetting::where('key', 'letter_number_format')->firstOrFail();
    $this->actingAs($user)->post('/admin/content/settings', ['id' => $setting->id, 'value' => '{sequence}/STIA'])->assertUnprocessable();
    expect($setting->fresh()->value)->toBe('{sequence}/KM-{service}/STIA/{year}');
});

it('lets an editor update the public faq without exposing raw html', function () {
    portalSeed();
    $user = portalUser(['manage_content']);
    $setting = SiteSetting::where('key', 'faq_items')->firstOrFail();
    $this->actingAs($user)->post('/admin/content/settings', ['id' => $setting->id, 'faq' => [['question' => 'Pertanyaan baru?', 'answer' => 'Jawaban <script>unsafe</script>'], ['question' => '', 'answer' => '']]])->assertRedirect();
    $this->get('/')->assertOk()->assertSee('Pertanyaan baru?')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>unsafe</script>', false);
});

it('updates public service information without allowing a privacy override', function () {
    portalSeed();
    $user = portalUser(['manage_accounts']);
    $service = Service::where('slug', 'konseling')->first();
    $this->actingAs($user)->post('/admin/masters/services', ['id' => $service->id, 'name' => 'Konseling Mahasiswa', 'summary' => 'Ruang aman mahasiswa.', 'description' => 'Informasi layanan kampus.', 'is_active' => 1, 'is_sensitive' => 0])->assertRedirect();
    expect($service->fresh()->is_sensitive)->toBeTrue();
    $this->get('/layanan/konseling')->assertOk()->assertSee('Konseling Mahasiswa');
});

it('denies counseling letter downloads to an unassigned officer and a different application token', function () {
    portalSeed();
    Storage::fake('local');
    $application = portalSubmit('konseling');
    $other = portalSubmit('beasiswa');
    $counselor = portalUser(['issue_letters'], ['konseling']);
    $this->actingAs($counselor);
    $workflow = app(PortalWorkflow::class);
    $letter = $workflow->issue($application, LetterTemplate::where('service_id', $application->service_id)->value('id'), 'Konfirmasi tanpa isi konseling.');
    $unassigned = portalUser(['issue_letters'], [], true);
    $this->actingAs($unassigned)->get('/admin/letters/'.$letter->id)->assertNotFound();
    $token = $workflow->token($other, 'document');
    $this->get('/akses/'.$token.'/surat/'.$letter->id)->assertNotFound();
});

it('keeps an accepted application and attachment when email queue dispatch fails', function () {
    portalSeed();
    Storage::fake('local');
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Synthetic queue outage'));
    $form = Form::with('service')->where('slug', 'ormawa-umum')->first();
    $payload = portalPayload($form);
    $payload['reply_channel'] = 'email';
    $payload['email'] = 'student@example.test';
    $this->post(route('applications.store', ['ormawa', $form->slug]), $payload)->assertRedirect();
    $this->assertDatabaseCount('applications', 1);
    $this->assertDatabaseHas('notification_deliveries', ['status' => 'failed']);
    $attachment = ApplicationAttachment::firstOrFail();
    Storage::disk('local')->assertExists($attachment->path);
});
