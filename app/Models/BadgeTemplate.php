<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BadgeTemplate extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'orientation',
        'width_mm',
        'height_mm',
        'background_image_path',
        'layout_config',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'layout_config' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function badges()
    {
        return $this->hasMany(Badge::class);
    }
}

