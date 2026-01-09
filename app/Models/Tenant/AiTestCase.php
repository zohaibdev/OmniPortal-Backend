<?php

namespace App\Models\Tenant;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiTestCase extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'business_type',
        'user_message',
        'expected_intent',
        'expected_fields',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expected_fields' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function results()
    {
        return $this->hasMany(AiTestResult::class);
    }

    public function latestResult()
    {
        return $this->hasOne(AiTestResult::class)->latestOfMany('tested_at');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForBusinessType($query, string $businessType)
    {
        return $query->where(function ($q) use ($businessType) {
            $q->where('business_type', $businessType)
              ->orWhereNull('business_type');
        });
    }
}
