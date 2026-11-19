<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['body_format', 'letter_template_id', 'version', 'body', 'signatory', 'title'])]
class LetterTemplateVersion extends Model
{
    use HasFactory;

    protected $table = 'letter_template_versions';

    protected function casts(): array
    {
        return [];
    }
}
