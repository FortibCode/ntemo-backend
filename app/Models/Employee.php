<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'user_id',
        'matricule',
        'first_name',
        'last_name',
        'gender',
        'birth_date',
        'birth_place',
        'nationality',
        'marital_status',
        'dependent_children',
        'email',
        'phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'address',
        'city',
        'national_id_number',
        'social_security_number',
        'department_id',
        'position_id',
        'manager_id',
        'hire_date',
        'probation_end_date',
        'termination_date',
        'status',
        'photo_path',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'hire_date' => 'date',
        'probation_end_date' => 'date',
        'termination_date' => 'date',
        'dependent_children' => 'integer',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function directReports()
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function assignments()
    {
        return $this->hasMany(EmployeeAssignment::class);
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}

