<?php

namespace App\Models\Tenant;

use App\Traits\BelongsToTenant;
use App\Traits\HasEncryptedId;
use App\Traits\HasMetaFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use BelongsToTenant, HasEncryptedId, HasMetaFields, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'order_number',
        'customer_id',
        'address_id',
        'type',
        'status',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'delivery_fee',
        'service_fee',
        'tip_amount',
        'total',
        'currency',
        'payment_status',
        'payment_method',
        'payment_method_id',
        'payment_proof_path',
        'paid_at',
        'coupon_id',
        'coupon_code',
        'customer_name',
        'customer_email',
        'customer_phone',
        'delivery_address',
        'delivery_instructions',
        'scheduled_at',
        'estimated_delivery_at',
        'delivered_at',
        'notes',
        'internal_notes',
        'source',
        'assigned_employee_id',
        'delivery_agent_id',
        'conversation_state',
        'conversation_context',
        'meta_data',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'tip_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'delivery_address' => 'array',
            'conversation_context' => 'array',
            'meta_data' => 'array',
            'paid_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'estimated_delivery_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY = 'ready';
    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    // Payment status constants
    public const PAYMENT_STATUS_PENDING = 'pending';
    public const PAYMENT_STATUS_PENDING_VERIFICATION = 'pending_verification';
    public const PAYMENT_STATUS_PAID = 'paid';
    public const PAYMENT_STATUS_REJECTED = 'rejected';
    public const PAYMENT_STATUS_FAILED = 'failed';
    public const PAYMENT_STATUS_REFUNDED = 'refunded';

    // Conversation state constants
    public const CONVERSATION_STATE_GREETING = 'greeting';
    public const CONVERSATION_STATE_BROWSING = 'browsing';
    public const CONVERSATION_STATE_ADDING_TO_ORDER = 'adding_to_order';
    public const CONVERSATION_STATE_CONFIRMING_ORDER = 'confirming_order';
    public const CONVERSATION_STATE_AWAITING_PAYMENT_SCREENSHOT = 'awaiting_payment_screenshot';
    public const CONVERSATION_STATE_ORDER_PLACED = 'order_placed';

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_REFUNDED,
        ]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    // Helpers
    public function updateStatus(string $status, ?string $notes = null, ?int $changedBy = null): void
    {
        $previousStatus = $this->status;
        
        $this->update(['status' => $status]);

        $this->statusHistory()->create([
            'status' => $status,
            'previous_status' => $previousStatus,
            'notes' => $notes,
            'changed_by' => $changedBy,
            'changed_by_type' => $changedBy ? 'user' : 'system',
        ]);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
        ]);
    }

    public function canBeRefunded(): bool
    {
        return $this->isPaid() && !in_array($this->status, [
            self::STATUS_REFUNDED,
        ]);
    }

    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $date = now()->format('ymd');
        $random = strtoupper(substr(uniqid(), -4));
        
        return "{$prefix}-{$date}-{$random}";
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }
        });
    }
}
