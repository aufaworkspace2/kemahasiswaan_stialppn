<?php

namespace Database\Factories;

use App\Models\FormVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FormVersion> */
class FormVersionFactory extends Factory
{
    protected $model = FormVersion::class;

    public function definition(): array
    {
        return ['form_id' => FormFactory::new(), 'version' => 1, 'status' => 'draft', 'fields' => []];
    }
}
