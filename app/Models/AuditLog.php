<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'action', 'subject_type', 'subject_id', 'metadata'])]
class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
