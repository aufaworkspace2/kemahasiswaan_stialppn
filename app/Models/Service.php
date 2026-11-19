<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['description_format', 'slug', 'name', 'summary', 'description', 'icon', 'position', 'is_sensitive', 'is_active'])]
class Service extends Model
{
    use HasFactory;

    protected $table = 'services';

    protected function casts(): array
    {
        return ['is_sensitive' => 'boolean', 'is_active' => 'boolean'];
    }

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ServiceCategory::class);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }
}
