<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NavigationSeeder extends Seeder
{
    public function run(): void
    {
        $insert = function (string $label, string $type, ?string $url, int $position, ?int $parent = null, ?int $target = null): int {
            $query = DB::table('menu_items')->where('label', $label)->where('parent_id', $parent);
            if ($existing = $query->first()) {
                return $existing->id;
            }

            return DB::table('menu_items')->insertGetId(['label' => $label, 'target_type' => $type, 'url' => $url, 'position' => $position, 'parent_id' => $parent, 'target_id' => $target, 'is_visible' => true, 'created_at' => now(), 'updated_at' => now()]);
        };
        $insert('Beranda', 'url', '/', 0);
        $services = $insert('Layanan', 'group', null, 1);
        foreach (DB::table('services')->orderBy('position')->get() as $service) {
            $insert($service->name, 'service', null, $service->position, $services, $service->id);
        }
        $information = $insert('Informasi', 'group', null, 2);
        $insert('Berita & Pengumuman', 'url', '/berita', 0, $information);
        $insert('FAQ', 'url', '/#faq', 1, $information);
        $insert('Kontak', 'url', '/#contact', 3);
    }
}
