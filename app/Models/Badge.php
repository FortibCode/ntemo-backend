<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Badge extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'employee_id',
        'badge_template_id',
        'badge_number',
        'security_token',
        'status',
        'qr_code_path',
        'printable_pdf_path',
        'issued_at',
        'expires_at',
        'revoked_at',
        'revocation_reason',
        'revoked_by',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'revoked_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function template()
    {
        return $this->belongsTo(BadgeTemplate::class, 'badge_template_id');
    }

    public function events()
    {
        return $this->hasMany(BadgeEvent::class);
    }

    public function revoker()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE' && (!$this->expires_at || now()->toDateString() <= $this->expires_at->toDateString());
    }
}

