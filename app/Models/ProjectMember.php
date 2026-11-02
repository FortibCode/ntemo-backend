<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectMember extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'employee_id',
        'role_in_project',
        'hourly_rate',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'joined_at' => 'date',
        'left_at' => 'date',
        'hourly_rate' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}

