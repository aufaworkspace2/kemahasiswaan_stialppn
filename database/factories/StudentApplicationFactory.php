<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormVersion;
use App\Models\StudentApplication;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StudentApplication> */
class StudentApplicationFactory extends Factory
{
    protected $model = StudentApplication::class;

    public function definition(): array
    {
        return ['form_version_id' => FormVersionFactory::new(), 'form_id' => fn (array $a): int => FormVersion::findOrFail($a['form_version_id'])->form_id, 'service_id' => fn (array $a): int => Form::findOrFail($a['form_id'])->service_id, 'service_category_id' => fn (array $a): int => Form::findOrFail($a['form_id'])->service_category_id, 'study_program_id' => StudyProgramFactory::new(), 'cohort_id' => CohortFactory::new(), 'student_number' => fake()->numerify('00######'), 'full_name' => fake()->name(), 'reply_channel' => 'email', 'email' => fake()->safeEmail(), 'answers' => [], 'status' => 'received', 'reference' => 'KM-'.fake()->unique()->bothify('########??'), 'idempotency_key' => (string) Str::uuid(), 'submitted_at' => now()];
    }
}
