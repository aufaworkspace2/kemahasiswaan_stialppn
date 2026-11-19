<?php

namespace Database\Factories;

use App\Models\LetterTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterTemplate> */
class LetterTemplateFactory extends Factory
{
    protected $model = LetterTemplate::class;

    public function definition(): array
    {
        return ['service_id' => ServiceFactory::new(), 'name' => 'Konfirmasi layanan'];
    }
}
