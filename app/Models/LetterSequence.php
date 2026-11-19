<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['service_id', 'year', 'next_number'])]
class LetterSequence extends Model
{
    protected $table = 'letter_sequences';

    protected function casts(): array
    {
        return [];
    }
}
