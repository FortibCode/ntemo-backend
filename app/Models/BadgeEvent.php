<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BadgeEvent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'badge_id',
        'action',
        'performed_by',
        'details',
        'ip_address',
    ];

    public function badge()
    {
        return $this->belongsTo(Badge::class);
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}

