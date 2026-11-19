<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['form_id', 'version', 'status', 'fields', 'published_at'])]
class FormVersion extends Model
{
    use HasFactory;

    protected $table = 'form_versions';

    protected function casts(): array
    {
        return ['fields' => 'array', 'published_at' => 'datetime'];
    }
}
