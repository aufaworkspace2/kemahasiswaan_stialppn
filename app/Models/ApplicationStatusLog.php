<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['application_id', 'user_id', 'from_status', 'to_status', 'message'])]
class ApplicationStatusLog extends Model
{
    protected $table = 'application_status_logs';

    protected function casts(): array
    {
        return [];
    }
}
