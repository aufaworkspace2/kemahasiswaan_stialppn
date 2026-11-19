<?php

use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

function navigationEditor(): User
{
    $user = User::factory()->create();
    DB::table('permission_user')->insert(['user_id' => $user->id, 'permission_id' => DB::table('permissions')->where('name', 'manage_content')->value('id')]);

    return $user;
}

it('renders navigation entirely from data and respects hidden parents', function () {
    $this->seed(DatabaseSeeder::class);
    MenuItem::query()->delete();
    Cache::forget('portal.public.v2');
    $response = $this->get('/')->assertOk();
    preg_match('/<nav id="main-nav"[^>]*>(.*?)<\/nav>/s', $response->getContent(), $matches);
    expect(trim($matches[1]))->toBe('');
    $parent = MenuItem::factory()->create(['label' => 'Student support', 'target_type' => 'group', 'url' => null]);
    $service = Service::firstOrFail();
    MenuItem::factory()->create(['label' => 'Start application', 'parent_id' => $parent->id, 'target_type' => 'service', 'target_id' => $service->id]);
    Cache::forget('portal.public.v2');
    $this->get('/')->assertSee('Student support')->assertSee('Start application')->assertSee(route('services.show', $service->slug), false);
    $parent->update(['is_visible' => false]);
    Cache::forget('portal.public.v2');
    $this->get('/')->assertDontSee('Student support')->assertDontSee('Start application');
});

it('saves selected services on a page and follows service changes', function () {
    $this->seed(DatabaseSeeder::class);
    $service = Service::firstOrFail();
    $this->actingAs(navigationEditor())->post('/admin/content/pages', ['title' => 'Student guide', 'slug' => 'student-guide', 'body' => 'Choose a service below.', 'status' => 'published', 'service_ids' => [$service->id]])->assertRedirect();
    $page = Page::where('slug', 'student-guide')->firstOrFail();
    expect($page->services->modelKeys())->toBe([$service->id]);
    $service->update(['slug' => 'updated-student-service', 'name' => 'Updated student service']);
    $this->get('/halaman/student-guide')->assertOk()->assertSee('Updated student service')->assertSee('/layanan/updated-student-service', false);
    $service->update(['is_active' => false]);
    $this->get('/halaman/student-guide')->assertOk()->assertDontSee('id="related-services-title"', false);
    $this->post('/admin/content/pages', ['id' => $page->id, 'title' => $page->title, 'slug' => $page->slug, 'body' => $page->body, 'status' => 'published'])->assertRedirect();
    expect($page->services()->count())->toBe(0);
});

it('rejects invalid service references before saving a page', function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(navigationEditor())->post('/admin/content/pages', ['title' => 'Invalid guide', 'slug' => 'invalid-guide', 'body' => 'Guide', 'status' => 'published', 'service_ids' => [999999]])->assertSessionHasErrors('service_ids.0');
    $this->assertDatabaseMissing('pages', ['slug' => 'invalid-guide']);
});

it('saves dropdown groups and service destinations with content permissions', function () {
    $this->seed(DatabaseSeeder::class);
    $user = navigationEditor();
    $this->actingAs($user)->post('/admin/content/menus', ['label' => 'Student links', 'target_type' => 'group', 'position' => 5, 'is_visible' => 1])->assertRedirect();
    $parent = MenuItem::where('label', 'Student links')->firstOrFail();
    $service = Service::firstOrFail();
    $this->post('/admin/content/menus', ['label' => 'Service destination', 'parent_id' => $parent->id, 'target_type' => 'service', 'service_target_id' => $service->id, 'position' => 0, 'is_visible' => 1])->assertRedirect();
    $this->assertDatabaseHas('menu_items', ['label' => 'Service destination', 'parent_id' => $parent->id, 'target_id' => $service->id, 'url' => null]);
    $this->post('/admin/content/menus', ['label' => 'Nested group', 'target_type' => 'group', 'parent_id' => $parent->id, 'position' => 0])->assertUnprocessable();
    $this->actingAs(User::factory()->create())->post('/admin/content/pages', ['service_ids' => [$service->id]])->assertForbidden();
});
