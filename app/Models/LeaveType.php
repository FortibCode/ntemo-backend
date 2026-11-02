<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'description',
        'default_days_per_year',
        'is_paid',
        'requires_attachment',
        'requires_manager_approval',
        'requires_hr_approval',
        'is_active',
    ];

    protected $casts = [
        'default_days_per_year' => 'decimal:2',
        'is_paid' => 'boolean',
        'requires_attachment' => 'boolean',
        'requires_manager_approval' => 'boolean',
        'requires_hr_approval' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}

