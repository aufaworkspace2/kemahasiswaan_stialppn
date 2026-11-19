<?php

namespace Database\Factories;

use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceCategory> */
class ServiceCategoryFactory extends Factory
{
    protected $model = ServiceCategory::class;

    public function definition(): array
    {
        return ['service_id' => ServiceFactory::new(), 'name' => fake()->words(2, true), 'is_active' => true];
    }
}
