<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Page;
use App\Models\StudentApplication;
use Illuminate\Support\Collection;

class Navigation
{
    /** @param Collection<int, MenuItem> $menus */
    public function revision(Collection $menus): string
    {
        return hash('sha256', $menus->sortBy('id')->map(fn (MenuItem $menu): array => $menu->getAttributes())->values()->toJson());
    }

    /**
     * @param  Collection<int, MenuItem>  $menus
     * @return array<int, string>
     */
    public function deletionBlocks(Collection $menus): array
    {
        $usedServices = StudentApplication::distinct()->pluck('service_id')->all();
        $usedPages = Page::whereHas('services', fn ($query) => $query->whereIn('services.id', $usedServices))->pluck('id')->all();
        $parents = $menus->pluck('parent_id')->filter()->all();
        $blocks = [];
        foreach ($menus as $menu) {
            if (in_array($menu->id, $parents)) {
                $blocks[$menu->id] = 'Masih menjadi induk submenu. Pindahkan atau hapus submenu terlebih dahulu.';
            } elseif (($menu->target_type === 'service' && in_array($menu->target_id, $usedServices)) || ($menu->target_type === 'page' && in_array($menu->target_id, $usedPages))) {
                $blocks[$menu->id] = 'Layanan tujuan memiliki pengajuan. Sembunyikan menu jika tidak ingin ditampilkan.';
            }
        }

        return $blocks;
    }
}
