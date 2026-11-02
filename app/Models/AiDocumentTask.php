<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiDocumentTask extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'user_id',
        'task_type',
        'status',
        'user_prompt',
        'generated_draft',
        'was_accepted_by_user',
    ];

    protected $casts = [
        'was_accepted_by_user' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

