<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['application_id', 'answers', 'identity', 'submitted_by'])]
class ApplicationRevision extends Model
{
    use HasFactory;

    protected $table = 'application_revisions';

    protected function casts(): array
    {
        return ['answers' => 'array', 'identity' => 'array'];
    }
}
