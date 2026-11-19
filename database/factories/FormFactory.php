<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Form> */
class FormFactory extends Factory
{
    protected $model = Form::class;

    public function definition(): array
    {
        return ['service_category_id' => ServiceCategoryFactory::new(), 'service_id' => fn (array $attributes): int => ServiceCategory::findOrFail($attributes['service_category_id'])->service_id, 'title' => fake()->sentence(3), 'slug' => fake()->unique()->slug(), 'status' => 'draft'];
    }
}
