<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['year', 'is_active'])]
class Cohort extends Model
{
    use HasFactory;

    protected $table = 'cohorts';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
