<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['service_id', 'service_category_id', 'title', 'slug', 'status', 'active_version_id', 'opens_at', 'closes_at'])]
class Form extends Model
{
    use HasFactory;

    protected $table = 'forms';

    protected function casts(): array
    {
        return ['opens_at' => 'datetime', 'closes_at' => 'datetime'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'active_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'published' && $this->active_version_id && (! $this->opens_at || $this->opens_at->isPast()) && (! $this->closes_at || $this->closes_at->isFuture());
    }
}
