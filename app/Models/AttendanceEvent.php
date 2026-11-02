<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceEvent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'employee_id',
        'device_id',
        'work_site_id',
        'qr_session_id',
        'recorded_at',
        'type',
        'source',
        'latitude',
        'longitude',
        'device_meta',
        'is_verified',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'is_verified' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function device()
    {
        return $this->belongsTo(AttendanceDevice::class, 'device_id');
    }

    public function qrSession()
    {
        return $this->belongsTo(QrSession::class, 'qr_session_id');
    }
}

