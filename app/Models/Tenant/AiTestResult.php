<?php

namespace App\Models\Tenant;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AiTestResult extends Model
{
    use BelongsToTenant;

    protected $connection = 'tenant';

    protected $fillable = [
        'ai_test_case_id',
        'status',
        'actual_intent',
        'actual_fields',
        'ai_response',
        'error_details',
        'tested_at',
    ];

    protected function casts(): array
    {
        return [
            'actual_fields' => 'array',
            'error_details' => 'array',
            'tested_at' => 'datetime',
        ];
    }

    // Status constants
    public const STATUS_PASS = 'pass';
    public const STATUS_FAIL = 'fail';

    // Relationships
    public function testCase()
    {
        return $this->belongsTo(AiTestCase::class, 'ai_test_case_id');
    }
}
