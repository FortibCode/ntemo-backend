<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceDay extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'employee_id',
        'date',
        'first_check_in',
        'last_check_out',
        'scheduled_minutes',
        'worked_minutes',
        'break_minutes',
        'late_minutes',
        'early_departure_minutes',
        'overtime_minutes',
        'status',
        'has_anomaly',
        'anomaly_reason',
        'is_locked',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'first_check_in' => 'datetime',
        'last_check_out' => 'datetime',
        'has_anomaly' => 'boolean',
        'is_locked' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
