<?php

use App\Models\LetterTemplate;
use App\Models\Page;
use App\Models\Service;
use App\Models\User;
use App\Services\PdfDocument;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

function managementUser(array $permissions = ['manage_accounts', 'manage_content', 'manage_forms', 'issue_letters']): User
{
    $user = User::factory()->create();
    foreach ($permissions as $permission) {
        DB::table('permission_user')->insert(['user_id' => $user->id, 'permission_id' => DB::table('permissions')->where('name', $permission)->value('id')]);
    }
    foreach (Service::pluck('id') as $serviceId) {
        DB::table('service_assignments')->insert(['user_id' => $user->id, 'service_id' => $serviceId]);
    }

    return $user;
}

it('renders separate create and edit forms for management modules', function () {
    $this->seed(DatabaseSeeder::class);
    $user = managementUser();
    $page = Page::factory()->create();
    $this->actingAs($user);
    foreach (['/admin/content/pages/create', '/admin/content/pages/'.$page->id.'/edit', '/admin/content/posts/create', '/admin/content/menus/create', '/admin/content/media/create', '/admin/content/sections/1/edit', '/admin/content/settings/1/edit', '/admin/forms/create', '/admin/accounts/create', '/admin/accounts/'.$user->id.'/edit', '/admin/masters/programs/create', '/admin/masters/programs/1/edit', '/admin/masters/cohorts/create', '/admin/masters/cohorts/1/edit', '/admin/masters/categories/create', '/admin/masters/categories/1/edit', '/admin/masters/services/1/edit', '/admin/templates/create', '/admin/templates/1/edit'] as $url) {
        $this->get($url)->assertOk()->assertSee('aria-label="Breadcrumb"', false);
    }
});

it('filters the page table without embedding content editing forms', function () {
    $this->seed(DatabaseSeeder::class);
    $user = managementUser(['manage_content']);
    $page = Page::factory()->create(['title' => 'Selected draft', 'status' => 'draft']);
    $other = Page::factory()->create(['title' => 'Other published page', 'status' => 'published']);
    $this->actingAs($user)->get('/admin/content/pages?q=Selected&status=draft')->assertOk()->assertSee('Selected draft')->assertDontSee('Other published page')->assertSee(route('admin.content.edit', ['pages', $page->id]), false)->assertDontSee('name="body"', false)->assertDontSee('content-tabs', false);
});

it('saves sanitized rich html and renders formatting on the public page', function () {
    $this->seed(DatabaseSeeder::class);
    $user = managementUser(['manage_content']);
    $this->actingAs($user)->post('/admin/content/pages', ['title' => 'Rich content', 'slug' => 'rich-content', 'status' => 'published', 'body_format' => 'html', 'body' => '<h2>Heading</h2><p><strong>Important</strong> <em>Details</em></p><ul><li>First item</li></ul><script>alert(1)</script><a href="javascript:alert(2)" onclick="alert(3)">Unsafe link</a>'])->assertRedirect();
    $page = Page::where('slug', 'rich-content')->firstOrFail();
    expect($page->body_format)->toBe('html');
    expect($page->body)->toContain('<strong>Important</strong>')->not->toContain('<script', 'onclick=', 'javascript:');
    $this->get('/halaman/rich-content')->assertOk()->assertSee('<h2>Heading</h2>', false)->assertSee('<strong>Important</strong>', false)->assertSee('<ul><li>First item</li></ul>', false)->assertDontSee('javascript:alert', false);
});

it('rejects visually empty rich html after sanitization', function () {
    $this->seed(DatabaseSeeder::class);
    $user = managementUser(['manage_content']);
    $this->actingAs($user)->post('/admin/content/pages', ['title' => 'Empty', 'slug' => 'empty', 'status' => 'draft', 'body_format' => 'html', 'body' => '<p><br></p><script>alert(1)</script>'])->assertSessionHasErrors('body');
    $this->assertDatabaseMissing('pages', ['slug' => 'empty']);
});

it('keeps previously stored plain content escaped', function () {
    $this->seed(DatabaseSeeder::class);
    $page = Page::factory()->create(['slug' => 'legacy', 'status' => 'published', 'body' => '<strong>Literal text</strong>']);
    $this->get('/halaman/legacy')->assertOk()->assertSee('&lt;strong&gt;Literal text&lt;/strong&gt;', false)->assertDontSee('<strong>Literal text</strong>', false);
});

it('requires permissions on every separate management editor', function (string $path) {
    $this->seed(DatabaseSeeder::class);
    $user = User::factory()->create();
    $this->actingAs($user)->get($path)->assertForbidden();
})->with(['/admin/content/pages/create', '/admin/content/pages/1/edit', '/admin/forms/create', '/admin/accounts/create', '/admin/accounts/1/edit', '/admin/masters/programs/create', '/admin/masters/programs/1/edit', '/admin/templates/create', '/admin/templates/1/edit']);

it('forbids editing categories and templates outside assigned services', function () {
    $this->seed(DatabaseSeeder::class);
    $user = User::factory()->create();
    foreach (['manage_forms', 'issue_letters'] as $permission) {
        DB::table('permission_user')->insert(['user_id' => $user->id, 'permission_id' => DB::table('permissions')->where('name', $permission)->value('id')]);
    }
    $this->actingAs($user)->get('/admin/masters/categories/1/edit')->assertNotFound();
    $this->get('/admin/templates/1/edit')->assertNotFound();
});

it('creates a versioned template from rich text and keeps formatting in pdf output', function () {
    $this->seed(DatabaseSeeder::class);
    $user = managementUser();
    $this->actingAs($user)->post('/admin/templates', ['name' => 'Rich letter', 'service_id' => 1, 'body_format' => 'html', 'body' => '<p><strong>Important {{full_name}}</strong></p><p><em>Instructions</em></p><ol><li>First</li><li>Second</li></ol>', 'signatory' => 'Authorized Officer', 'title' => 'Student Affairs'])->assertRedirect();
    $template = LetterTemplate::where('name', 'Rich letter')->firstOrFail();
    $this->assertDatabaseHas('letter_template_versions', ['letter_template_id' => $template->id, 'body_format' => 'html', 'version' => 1]);
    $pdf = (new PdfDocument)->render(['STIA', 'CAMPUS', ['html' => '<p><strong>Bold</strong> and <em>Italic</em></p><ol><li>First</li><li>Second</li></ol>']]);
    expect($pdf)->toStartWith('%PDF-1.4')->toContain('/F2 11 Tf (Bold)', '/F3 11 Tf (Italic)', '1. First', '2. Second')->not->toContain('<strong>', '<ol>');
});

it('sanitizes rich service descriptions and does not change the sensitive flag', function () {
    $this->seed(DatabaseSeeder::class);
    $user = managementUser();
    $service = Service::where('slug', 'konseling')->first();
    $this->actingAs($user)->post('/admin/masters/services', ['id' => $service->id, 'name' => $service->name, 'summary' => $service->summary, 'description_format' => 'html', 'description' => '<p><strong>Private service</strong></p><img src=x onerror=alert(1)>', 'is_active' => 1, 'is_sensitive' => 0])->assertRedirect();
    expect($service->fresh()->is_sensitive)->toBeTrue();
    $this->get('/layanan/konseling')->assertOk()->assertSee('<strong>Private service</strong>', false)->assertDontSee('onerror=', false);
});

it('keeps filters across paginated page listings', function () {
    $this->seed(DatabaseSeeder::class);
    $user = managementUser(['manage_content']);
    Page::factory()->count(16)->create(['title' => 'Searchable content', 'status' => 'draft']);
    $this->actingAs($user)->get('/admin/content/pages?q=Searchable&status=draft')->assertOk()->assertSee('q=Searchable', false)->assertSee('status=draft', false)->assertSee('page=2', false);
});

it('preserves long unicode rich text within the configured character limit', function () {
    $this->seed(DatabaseSeeder::class);
    $user = managementUser(['manage_content']);
    $text = str_repeat('界', 34000);
    $this->actingAs($user)->post('/admin/content/pages', ['title' => 'Unicode content', 'slug' => 'unicode-content', 'status' => 'draft', 'body_format' => 'html', 'body' => '<p>'.$text.'</p>'])->assertRedirect();
    $page = Page::where('slug', 'unicode-content')->firstOrFail();
    expect(strip_tags($page->body))->toBe($text);
});
