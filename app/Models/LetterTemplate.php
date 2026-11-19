<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['service_id', 'name', 'active_version_id'])]
class LetterTemplate extends Model
{
    use HasFactory;

    protected $table = 'letter_templates';

    protected function casts(): array
    {
        return [];
    }
}
