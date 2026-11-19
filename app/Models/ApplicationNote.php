<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['application_id', 'user_id', 'body'])]
class ApplicationNote extends Model
{
    protected $table = 'application_notes';

    protected function casts(): array
    {
        return [];
    }
}
