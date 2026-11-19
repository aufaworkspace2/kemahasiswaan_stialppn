<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'title', 'body', 'position', 'is_visible', 'name', 'anchor', 'location', 'body_format', 'settings', 'media_id', 'mobile_media_id', 'starts_at', 'ends_at'])]
class PageSection extends Model
{
    protected $table = 'page_sections';

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function mobileMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'mobile_media_id');
    }

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'settings' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
