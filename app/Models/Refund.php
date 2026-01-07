<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'payment_id',
        'order_id',
        'refund_number',
        'amount',
        'reason',
        'notes',
        'status',
        'gateway_refund_id',
        'gateway_response',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'gateway_response' => 'array',
            'refunded_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
