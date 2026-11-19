<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['application_id', 'channel', 'kind', 'destination', 'message', 'status', 'attempts', 'sent_at', 'last_error', 'access_url'])]
class NotificationDelivery extends Model
{
    protected $table = 'notification_deliveries';

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'access_url' => 'encrypted'];
    }
}
