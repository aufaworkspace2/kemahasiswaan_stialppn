<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['body_format', 'title', 'slug', 'body', 'status', 'published_at'])]
class Page extends Model
{
    use HasFactory;

    protected $table = 'pages';

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }
}
