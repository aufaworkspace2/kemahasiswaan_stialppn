<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Services\Navigation;
use App\Services\PortalWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminNavigationController extends Controller
{
    public function __construct(private Navigation $navigation, private PortalWorkflow $workflow) {}

    private function authorizeEditor(): void
    {
        abort_unless(auth()->user()?->hasPermission('manage_content'), 403);
    }

    public function index(): View
    {
        $this->authorizeEditor();
        $menus = MenuItem::orderBy('position')->orderBy('id')->get();

        return view('admin.navigation', ['menus' => $menus, 'revision' => $this->navigation->revision($menus), 'deletionBlocks' => $this->navigation->deletionBlocks($menus)]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeEditor();
        $data = $request->validate(['revision' => ['required', 'string', 'size:64'], 'items' => ['present', 'array', 'max:1000'], 'items.*' => ['required', 'array:id,parent_id'], 'items.*.id' => ['required', 'integer', 'distinct'], 'items.*.parent_id' => ['present', 'nullable', 'integer']]);
        $revision = DB::transaction(function () use ($data): string {
            $menus = MenuItem::orderBy('id')->lockForUpdate()->get();
            abort_unless(hash_equals($this->navigation->revision($menus), $data['revision']), 409, 'Menu berubah di sesi lain. Muat ulang halaman sebelum mengatur ulang.');
            $items = collect($data['items'])->keyBy('id');
            abort_unless($items->keys()->sort()->values()->all() === $menus->modelKeys(), 422, 'Susunan harus memuat seluruh menu tepat satu kali.');
            foreach ($items as $id => $item) {
                $parentId = $item['parent_id'];
                if ($parentId !== null) {
                    abort_unless($parentId !== $id && $items->has($parentId) && $items[$parentId]['parent_id'] === null, 422, 'Submenu hanya boleh satu tingkat dan tidak boleh menjadi induknya sendiri.');
                    abort_if($menus->find($id)->target_type === 'group', 422, 'Grup dropdown harus menjadi menu utama.');
                }
            }
            $positions = [];
            foreach ($items as $id => $item) {
                $parent = $item['parent_id'] ?? 0;
                $position = $positions[$parent] ?? 0;
                $menus->find($id)->update(['parent_id' => $item['parent_id'], 'position' => $position]);
                $positions[$parent] = $position + 1;
            }
            $this->workflow->audit('navigation.reordered', 'menu', 0);

            return $this->navigation->revision(MenuItem::orderBy('id')->get());
        });
        Cache::forget('portal.public.v2');

        return response()->json(['revision' => $revision, 'message' => 'Susunan menu disimpan.']);
    }

    public function destroy(MenuItem $menu): RedirectResponse
    {
        $this->authorizeEditor();
        DB::transaction(function () use ($menu): void {
            $menus = MenuItem::orderBy('id')->lockForUpdate()->get();
            abort_unless($menus->contains('id', $menu->id), 404);
            $reason = $this->navigation->deletionBlocks($menus)[$menu->id] ?? null;
            abort_if($reason !== null, 422, $reason ?? 'Menu masih digunakan.');
            $this->workflow->audit('navigation.deleted', 'menu', $menu->id);
            $menu->delete();
        });
        Cache::forget('portal.public.v2');

        return redirect()->route('admin.content.index', 'menus')->with('success', 'Menu dihapus.');
    }
}
