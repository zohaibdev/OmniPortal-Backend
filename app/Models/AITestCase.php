<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AITestCase extends Model
{
    protected $table = 'ai_test_cases';

    protected $fillable = [
        'store_id',
        'business_type',
        'user_message',
        'expected_intent',
        'expected_fields',
        'test_result',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_fields' => 'array',
            'test_result' => 'array',
        ];
    }

    // Relationships
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    // Scopes
    public function scopeByStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePassed($query)
    {
        return $query->where('status', 'pass');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'fail');
    }

    // Helpers
    public function markAsPass($result = null): void
    {
        $this->update([
            'status' => 'pass',
            'test_result' => $result,
        ]);
    }

    public function markAsFail($result = null, $notes = null): void
    {
        $this->update([
            'status' => 'fail',
            'test_result' => $result,
            'notes' => $notes,
        ]);
    }
}
