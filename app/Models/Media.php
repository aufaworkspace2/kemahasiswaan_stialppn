<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'path', 'mime_type', 'size', 'user_id'])]
class Media extends Model
{
    protected $table = 'media';

    protected function casts(): array
    {
        return [];
    }
}
