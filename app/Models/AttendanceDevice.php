<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceDevice extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'work_site_id',
        'name',
        'device_identifier',
        'secret_key',
        'type',
        'last_heartbeat_at',
        'is_active',
    ];

    protected $casts = [
        'last_heartbeat_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function workSite()
    {
        return $this->belongsTo(WorkSite::class);
    }

    public function qrSessions()
    {
        return $this->hasMany(QrSession::class, 'device_id');
    }
}

