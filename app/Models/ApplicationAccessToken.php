<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['application_id', 'token_hash', 'purpose', 'expires_at', 'used_at', 'revoked_at'])]
class ApplicationAccessToken extends Model
{
    protected $table = 'application_access_tokens';

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
