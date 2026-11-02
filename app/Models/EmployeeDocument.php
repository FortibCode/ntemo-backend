<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDocument extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'employee_id',
        'title',
        'category',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'expiration_date',
        'is_confidential',
        'uploaded_by',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'is_confidential' => 'boolean',
        'file_size' => 'integer',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

