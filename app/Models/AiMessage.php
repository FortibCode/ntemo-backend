<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiMessage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ai_conversation_id',
        'role',
        'content',
        'tokens_usage',
        'suggested_actions',
    ];

    protected $casts = [
        'tokens_usage' => 'array',
        'suggested_actions' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}

