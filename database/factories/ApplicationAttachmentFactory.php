<?php

namespace Database\Factories;

use App\Models\ApplicationAttachment;
use App\Models\ApplicationRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApplicationAttachment> */
class ApplicationAttachmentFactory extends Factory
{
    protected $model = ApplicationAttachment::class;

    public function definition(): array
    {
        return ['application_revision_id' => ApplicationRevisionFactory::new(), 'application_id' => fn (array $a): int => ApplicationRevision::findOrFail($a['application_revision_id'])->application_id, 'field_key' => 'document', 'original_name' => 'document.pdf', 'path' => 'applications/'.fake()->uuid().'.pdf', 'mime_type' => 'application/pdf', 'size' => 1024];
    }
}
