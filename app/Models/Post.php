<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['body_format', 'title', 'slug', 'excerpt', 'body', 'kind', 'status', 'published_at'])]
class Post extends Model
{
    protected $table = 'posts';

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }
}
