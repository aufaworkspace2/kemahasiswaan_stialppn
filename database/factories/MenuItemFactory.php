<?php

namespace Database\Factories;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MenuItem> */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return ['label' => fake()->words(2, true), 'target_type' => 'url', 'url' => '/', 'position' => 0, 'is_visible' => true];
    }
}
