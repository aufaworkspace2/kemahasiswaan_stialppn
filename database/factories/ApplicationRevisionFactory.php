<?php

namespace Database\Factories;

use App\Models\ApplicationRevision;
use App\Models\StudentApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApplicationRevision> */
class ApplicationRevisionFactory extends Factory
{
    protected $model = ApplicationRevision::class;

    public function definition(): array
    {
        return ['application_id' => StudentApplicationFactory::new(), 'answers' => [], 'identity' => fn (array $a): array => StudentApplication::findOrFail($a['application_id'])->only(['student_number', 'full_name', 'study_program_id', 'cohort_id', 'reply_channel', 'email', 'whatsapp'])];
    }
}
