<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QrSession extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'device_id',
        'nonce',
        'token_signature',
        'issued_at',
        'expires_at',
        'is_consumed',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_consumed' => 'boolean',
    ];

    public function device()
    {
        return $this->belongsTo(AttendanceDevice::class, 'device_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }
}

