<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'notification_id',
        'channel',
        'status',
        'error_details',
        'delivered_at',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
    ];

    public function notification()
    {
        return $this->belongsTo(AppNotification::class, 'notification_id');
    }
}

