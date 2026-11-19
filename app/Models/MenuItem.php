<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['parent_id', 'label', 'target_type', 'target_id', 'url', 'position', 'is_visible'])]
class MenuItem extends Model
{
    use HasFactory;

    protected $table = 'menu_items';

    protected function casts(): array
    {
        return ['is_visible' => 'boolean'];
    }
}
