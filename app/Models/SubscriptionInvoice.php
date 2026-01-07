<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'stripe_invoice_id',
        'number',
        'amount',
        'tax',
        'total',
        'currency',
        'status',
        'hosted_invoice_url',
        'pdf_url',
        'due_date',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'paid_at' => 'datetime',
            'amount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
