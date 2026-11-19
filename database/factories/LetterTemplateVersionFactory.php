<?php

namespace Database\Factories;

use App\Models\LetterTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterTemplateVersion> */
class LetterTemplateVersionFactory extends Factory
{
    protected $model = LetterTemplateVersion::class;

    public function definition(): array
    {
        return ['letter_template_id' => LetterTemplateFactory::new(), 'version' => 1, 'body' => 'Pengajuan {{reference}} atas nama {{full_name}}. {{decision}}', 'signatory' => 'Petugas pengujian', 'title' => 'Bagian Kemahasiswaan'];
    }
}
