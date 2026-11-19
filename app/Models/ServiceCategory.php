<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['service_id', 'name', 'is_active'])]
class ServiceCategory extends Model
{
    use HasFactory;

    protected $table = 'service_categories';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
