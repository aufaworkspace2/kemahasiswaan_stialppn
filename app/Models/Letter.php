<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['application_id', 'letter_template_version_id', 'user_id', 'number', 'snapshot', 'path', 'status', 'replaces_id'])]
class Letter extends Model
{
    protected $table = 'letters';

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }
}
