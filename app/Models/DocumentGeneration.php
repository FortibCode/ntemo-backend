<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentGeneration extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'document_template_version_id',
        'employee_id',
        'requested_by',
        'title',
        'output_format',
        'status',
        'file_path',
        'file_hash_sha256',
        'file_size_bytes',
        'input_payload',
        'error_message',
        'generated_at',
    ];

    protected $casts = [
        'input_payload' => 'array',
        'generated_at' => 'datetime',
        'file_size_bytes' => 'integer',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function templateVersion()
    {
        return $this->belongsTo(DocumentTemplateVersion::class, 'document_template_version_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}

