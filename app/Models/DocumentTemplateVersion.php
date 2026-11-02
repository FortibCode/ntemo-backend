<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplateVersion extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'document_template_id',
        'version_number',
        'content',
        'file_template_path',
        'changelog',
        'created_by',
        'is_current',
    ];

    protected $casts = [
        'is_current' => 'boolean',
    ];

    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function generations()
    {
        return $this->hasMany(DocumentGeneration::class);
    }
}

