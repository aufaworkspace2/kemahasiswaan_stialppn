<?php

use App\Models\Media;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Service;
use App\Models\User;
use App\Services\SectionContent;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

function sectionEditorUser(): User
{
    $user = User::factory()->create();
    DB::table('permission_user')->insert(['user_id' => $user->id, 'permission_id' => DB::table('permissions')->where('name', 'manage_content')->value('id')]);

    return $user;
}

function sectionPayload(string $type = 'text'): array
{
    return ['name' => 'Custom section', 'section_type' => $type, 'title' => 'Custom section heading', 'body' => '<p><strong>Formatted introduction</strong></p>', 'body_format' => 'html', 'detail' => '<p>Additional <em>details</em></p>', 'detail_format' => 'html', 'location' => 'home', 'position' => 10, 'is_visible' => 1, 'settings' => array_replace(app(SectionContent::class)->defaults($type), ['items' => [['title' => 'Sample item', 'text' => '<p><strong>Item details</strong></p>', 'text_format' => 'html', 'icon' => 'check']]])];
}

it('renders complete section create and edit forms behind content permission', function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(sectionEditorUser())->get('/admin/content/sections/create')->assertOk()->assertSee('Tombol & tautan', false)->assertSee('Gambar desktop')->assertSee('Daftar item')->assertSee('Penempatan & status', false);
    $this->get('/admin/content/sections/1/edit')->assertOk()->assertSee('Lihat Layanan')->assertSee('Tambah item');
    $this->actingAs(User::factory()->create())->post('/admin/content/sections', sectionPayload())->assertForbidden();
});

it('saves and renders configurable section types', function (string $type) {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(sectionEditorUser())->post('/admin/content/sections', sectionPayload($type))->assertRedirect();
    $section = PageSection::where('name', 'Custom section')->firstOrFail();
    expect($section->type)->toBe($type);
    $this->get('/')->assertOk()->assertSee('Custom section heading')->assertSee('<strong>Formatted introduction</strong>', false)->assertSee('<em>details</em>', false);
})->with(array_keys(SectionContent::TYPES));

it('allows repeated section types without changing the original section', function () {
    $this->seed(DatabaseSeeder::class);
    $before = PageSection::where('type', 'hero')->firstOrFail()->title;
    $this->actingAs(sectionEditorUser())->post('/admin/content/sections', sectionPayload('hero'))->assertRedirect();
    expect(PageSection::where('type', 'hero')->count())->toBe(2);
    expect(PageSection::where('type', 'hero')->oldest('id')->first()->title)->toBe($before);
});

it('places global sections on inner pages and applies visibility schedules in WIB', function () {
    $this->seed(DatabaseSeeder::class);
    $page = Page::factory()->create(['status' => 'published']);
    $this->actingAs(sectionEditorUser());
    $this->travelTo(now()->setDate(2026, 11, 19)->setTime(4, 0));
    $payload = sectionPayload();
    $payload['location'] = 'top';
    $payload['starts_at'] = now()->timezone('Asia/Jakarta')->subHour()->format('Y-m-d\TH:i');
    $payload['ends_at'] = now()->timezone('Asia/Jakarta')->addHour()->format('Y-m-d\TH:i');
    $this->post('/admin/content/sections', $payload)->assertRedirect();
    $section = PageSection::where('name', 'Custom section')->firstOrFail();
    $this->get('/halaman/'.$page->slug)->assertOk()->assertSee('Custom section heading');
    $this->travel(2)->hours();
    $this->get('/halaman/'.$page->slug)->assertOk()->assertDontSee('Custom section heading');
    $section->update(['ends_at' => null, 'starts_at' => now()->addDay()]);
    $this->get('/')->assertDontSee('Custom section heading');
    $section->update(['starts_at' => null, 'is_visible' => false]);
    $this->get('/')->assertDontSee('Custom section heading');
});

it('follows selected page and service targets and hides unavailable destinations', function () {
    $this->seed(DatabaseSeeder::class);
    $page = Page::factory()->create(['status' => 'published', 'slug' => 'first-target']);
    $service = Service::firstOrFail();
    $payload = sectionPayload('links');
    $payload['settings']['button_label'] = 'Open selected page';
    $payload['settings']['button_target'] = 'page:'.$page->id;
    $payload['settings']['secondary_button_label'] = 'Open selected service';
    $payload['settings']['secondary_button_target'] = 'service:'.$service->id;
    $this->actingAs(sectionEditorUser())->post('/admin/content/sections', $payload)->assertRedirect();
    $page->update(['slug' => 'changed-target']);
    $this->get('/')->assertSee('/halaman/changed-target', false)->assertSee('Open selected page')->assertSee('Open selected service');
    $page->update(['status' => 'draft']);
    $service->update(['is_active' => false]);
    Cache::forget('portal.public.v2');
    $this->get('/')->assertDontSee('Open selected page')->assertDontSee('Open selected service');
});

it('sanitizes rich section and item content', function () {
    $this->seed(DatabaseSeeder::class);
    $payload = sectionPayload();
    $payload['body'] .= '<script>alert(1)</script>';
    $payload['detail'] .= '<img src=x onerror=alert(1)>';
    $payload['settings']['items'][0]['text'] .= '<a href="javascript:alert(1)">Unsafe</a>';
    $this->actingAs(sectionEditorUser())->post('/admin/content/sections', $payload)->assertRedirect();
    $this->get('/')->assertOk()->assertSee('<strong>Item details</strong>', false)->assertDontSee('<script>alert', false)->assertDontSee('onerror=', false)->assertDontSee('javascript:alert', false);
});

it('rejects invalid section settings without persisting content', function (string $invalid) {
    $this->seed(DatabaseSeeder::class);
    $payload = sectionPayload();
    if ($invalid === 'url') {
        $payload['settings']['button_url'] = 'javascript:alert(1)';
    } elseif ($invalid === 'target') {
        $payload['settings']['button_target'] = 'page:999999';
    } elseif ($invalid === 'schedule') {
        $payload['starts_at'] = '2026-11-20T12:00';
        $payload['ends_at'] = '2026-11-19T12:00';
    } elseif ($invalid === 'items') {
        $payload['settings']['items'] = array_fill(0, 21, ['title' => 'Too many']);
    } else {
        $payload['media_id'] = 999999;
    }
    $this->actingAs(sectionEditorUser())->post('/admin/content/sections', $payload)->assertSessionHasErrors();
    $this->assertDatabaseMissing('page_sections', ['name' => 'Custom section']);
})->with(['url', 'target', 'schedule', 'items', 'media']);

it('renders separate desktop and mobile media with lazy loading', function () {
    $this->seed(DatabaseSeeder::class);
    $user = sectionEditorUser();
    $image = Media::create(['name' => 'Desktop', 'path' => 'desktop.webp', 'mime_type' => 'image/webp', 'size' => 100, 'user_id' => $user->id]);
    $mobile = Media::create(['name' => 'Mobile', 'path' => 'mobile.webp', 'mime_type' => 'image/webp', 'size' => 100, 'user_id' => $user->id]);
    $payload = sectionPayload('image');
    $payload['media_id'] = $image->id;
    $payload['mobile_media_id'] = $mobile->id;
    $this->actingAs($user)->post('/admin/content/sections', $payload)->assertRedirect();
    $this->get('/')->assertOk()->assertSee('srcset="http://localhost:8000/storage/mobile.webp"', false)->assertSee('loading="lazy"', false);
});

it('accepts schedules outside the MySQL timestamp range', function () {
    $this->seed(DatabaseSeeder::class);
    $payload = sectionPayload();
    $payload['starts_at'] = '2099-01-01T09:00';
    $this->actingAs(sectionEditorUser())->post('/admin/content/sections', $payload)->assertRedirect();
    expect(PageSection::where('name', 'Custom section')->firstOrFail()->starts_at->year)->toBe(2099);
    $this->get('/')->assertDontSee('Custom section heading');
});

it('reorders sections only within their placement', function () {
    $this->seed(DatabaseSeeder::class);
    $first = PageSection::where('location', 'home')->orderBy('position')->firstOrFail();
    $global = PageSection::create(['name' => 'Global bar', 'type' => 'banner', 'title' => 'Global bar', 'location' => 'top', 'position' => 0, 'is_visible' => true]);
    $this->actingAs(sectionEditorUser())->post('/admin/sections/'.$first->id.'/move', ['direction' => 'down'])->assertRedirect();
    expect($first->fresh()->position)->toBe(1);
    expect($global->fresh()->position)->toBe(0);
});
