<?php

namespace Database\Factories;

use App\Models\Cohort;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cohort> */
class CohortFactory extends Factory
{
    protected $model = Cohort::class;

    public function definition(): array
    {
        return ['year' => fake()->unique()->numberBetween(1990, 2100), 'is_active' => true];
    }
}
