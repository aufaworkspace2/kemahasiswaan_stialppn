<?php

use App\Models\MenuItem;
use App\Models\Page;
use App\Models\StudentApplication;
use App\Models\User;
use App\Services\Navigation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

function navigationManagerUser(): User
{
    $user = User::factory()->create();
    DB::table('permission_user')->insert(['user_id' => $user->id, 'permission_id' => DB::table('permissions')->where('name', 'manage_content')->value('id')]);

    return $user;
}

function navigationLayout(): array
{
    return ['revision' => app(Navigation::class)->revision(MenuItem::orderBy('id')->get()), 'items' => MenuItem::orderBy('position')->orderBy('id')->get()->map(fn (MenuItem $menu): array => ['id' => $menu->id, 'parent_id' => $menu->parent_id])->all()];
}

it('shows a hierarchical navigation editor with parent and deletion explanations', function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(navigationManagerUser())->get('/admin/content/menus')->assertOk()->assertSee('navigation-table', false)->assertSee('Submenu dari Layanan')->assertSee('Masih menjadi induk submenu.')->assertSee('data-menu-handle', false);
});

it('saves ordering and parent changes and invalidates public navigation', function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(navigationManagerUser());
    $this->get('/');
    $menu = MenuItem::factory()->create(['label' => 'Ordered menu']);
    $parent = MenuItem::where('label', 'Informasi')->firstOrFail();
    $layout = navigationLayout();
    $layout['items'] = array_values(array_filter($layout['items'], fn (array $item): bool => $item['id'] !== $menu->id));
    array_unshift($layout['items'], ['id' => $menu->id, 'parent_id' => $parent->id]);
    $this->postJson('/admin/navigation/order', $layout)->assertOk()->assertJsonStructure(['revision', 'message']);
    $this->assertDatabaseHas('menu_items', ['id' => $menu->id, 'parent_id' => $parent->id, 'position' => 0]);
    $this->get('/')->assertSee('Ordered menu');
});

it('rejects invalid or incomplete tree layouts atomically', function (string $invalid) {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(navigationManagerUser());
    $layout = navigationLayout();
    $first = $layout['items'][0]['id'];
    $before = MenuItem::orderBy('id')->get()->toArray();
    if ($invalid === 'missing') {
        array_pop($layout['items']);
    } elseif ($invalid === 'duplicate') {
        $layout['items'][] = $layout['items'][0];
    } elseif ($invalid === 'self') {
        $layout['items'][0]['parent_id'] = $first;
    } elseif ($invalid === 'unknown') {
        $layout['items'][0]['parent_id'] = 999999;
    } else {
        $layout['items'][0]['parent_id'] = $layout['items'][1]['id'];
        $layout['items'][1]['parent_id'] = $first;
    }
    $this->postJson('/admin/navigation/order', $layout)->assertUnprocessable();
    expect(MenuItem::orderBy('id')->get()->toArray())->toBe($before);
})->with(['missing', 'duplicate', 'self', 'unknown', 'cycle']);

it('refuses a third level and refuses nesting dropdown groups', function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(navigationManagerUser());
    $layout = navigationLayout();
    $group = MenuItem::where('label', 'Informasi')->firstOrFail();
    $child = MenuItem::whereNotNull('parent_id')->firstOrFail();
    foreach ($layout['items'] as &$item) {
        if ($item['id'] === $group->id) {
            $item['parent_id'] = $child->id;
        }
    }
    unset($item);
    $this->postJson('/admin/navigation/order', $layout)->assertUnprocessable();
    $layout = navigationLayout();
    $otherGroup = MenuItem::where('label', 'Layanan')->firstOrFail();
    foreach ($layout['items'] as &$item) {
        if ($item['id'] === $group->id) {
            $item['parent_id'] = $otherGroup->id;
        }
    }
    unset($item);
    $this->postJson('/admin/navigation/order', $layout)->assertUnprocessable();
});

it('detects stale layouts rather than overwriting other edits', function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(navigationManagerUser());
    $layout = navigationLayout();
    MenuItem::firstOrFail()->update(['label' => 'Changed by another editor']);
    $this->postJson('/admin/navigation/order', $layout)->assertStatus(409);
    $this->assertDatabaseHas('menu_items', ['label' => 'Changed by another editor']);
});

it('deletes unused leaf menus without deleting their target page', function () {
    $this->seed(DatabaseSeeder::class);
    $page = Page::factory()->create();
    $menu = MenuItem::factory()->create(['target_type' => 'page', 'target_id' => $page->id, 'label' => 'Delete only the link']);
    $this->actingAs(navigationManagerUser());
    $this->get('/');
    $this->delete('/admin/navigation/'.$menu->id)->assertRedirect('/admin/content/menus');
    $this->assertDatabaseMissing('menu_items', ['id' => $menu->id]);
    $this->assertDatabaseHas('pages', ['id' => $page->id]);
    $this->get('/')->assertDontSee('Delete only the link');
});

it('protects menu parents and links to services with applications', function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(navigationManagerUser());
    $parent = MenuItem::where('label', 'Layanan')->firstOrFail();
    $this->delete('/admin/navigation/'.$parent->id)->assertUnprocessable();
    $application = StudentApplication::factory()->create();
    $menu = MenuItem::factory()->create(['target_type' => 'service', 'target_id' => $application->service_id]);
    $page = Page::factory()->create();
    $page->services()->attach($application->service_id);
    $pageLink = MenuItem::factory()->create(['target_type' => 'page', 'target_id' => $page->id]);
    foreach ([$menu, $pageLink] as $protected) {
        $this->delete('/admin/navigation/'.$protected->id)->assertUnprocessable();
        $this->assertDatabaseHas('menu_items', ['id' => $protected->id]);
    }
    $this->get('/admin/content/menus')->assertSee('Layanan tujuan memiliki pengajuan.');
    $this->assertDatabaseHas('applications', ['id' => $application->id]);
});

it('requires content permission for ordering and deletion', function () {
    $this->seed(DatabaseSeeder::class);
    $menu = MenuItem::factory()->create();
    $this->postJson('/admin/navigation/order', navigationLayout())->assertUnauthorized();
    $this->delete('/admin/navigation/'.$menu->id)->assertRedirect('/admin/login');
    $this->actingAs(User::factory()->create())->postJson('/admin/navigation/order', navigationLayout())->assertForbidden();
    $this->delete('/admin/navigation/'.$menu->id)->assertForbidden();
    $this->assertDatabaseHas('menu_items', ['id' => $menu->id]);
});
