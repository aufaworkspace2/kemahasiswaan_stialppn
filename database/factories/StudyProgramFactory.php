<?php

namespace Database\Factories;

use App\Models\StudyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudyProgram> */
class StudyProgramFactory extends Factory
{
    protected $model = StudyProgram::class;

    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('PR-####'), 'name' => fake()->words(2, true), 'is_active' => true];
    }
}
