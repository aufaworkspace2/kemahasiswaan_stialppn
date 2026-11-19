<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['application_id', 'application_revision_id', 'field_key', 'original_name', 'path', 'mime_type', 'size'])]
class ApplicationAttachment extends Model
{
    use HasFactory;

    protected $table = 'application_attachments';

    protected function casts(): array
    {
        return [];
    }
}
