<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return ['slug' => fake()->unique()->slug(2), 'name' => fake()->words(2, true), 'summary' => fake()->sentence(), 'description' => fake()->paragraph(), 'icon' => 'graduation', 'position' => 0, 'is_active' => true, 'is_sensitive' => false];
    }
}
