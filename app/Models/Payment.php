<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Payment extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'order_id',
        'transaction_id',
        'gateway',
        'method',
        'amount',
        'currency',
        'status',
        'gateway_response_code',
        'gateway_response',
        'meta_data',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'gateway_response' => 'array',
            'meta_data' => 'array',
            'processed_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    // Boot
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (empty($payment->payment_number)) {
                $payment->payment_number = self::generatePaymentNumber();
            }
            if (empty($payment->net_amount)) {
                $payment->net_amount = $payment->amount - $payment->fee;
            }
        });
    }

    public static function generatePaymentNumber(): string
    {
        return 'PAY-' . strtoupper(Str::random(12));
    }

    // Relationships
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // Helpers
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function markAsCompleted(): void
    {
        $this->status = 'completed';
        $this->paid_at = now();
        $this->save();

        if ($this->order) {
            $this->order->update(['payment_status' => 'paid']);
        }
    }
}
