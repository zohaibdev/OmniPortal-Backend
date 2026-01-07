<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

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
        'created_by_type',
        'created_by_name',
        'created_by_id',
        'meta_data',
    ];

    protected function casts(): array
    {
        return [
            'delivery_address' => 'array',
            'meta_data' => 'array',
            'scheduled_at' => 'datetime',
            'estimated_delivery_at' => 'datetime',
            'delivered_at' => 'datetime',
            'paid_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'tip_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY = 'ready';
    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    // Source constants
    public const SOURCE_ONLINE = 'online';
    public const SOURCE_POS = 'pos';
    public const SOURCE_PHONE = 'phone';
    public const SOURCE_MANUAL = 'manual';

    // Type constants
    public const TYPE_DINE_IN = 'dine_in';
    public const TYPE_TAKEAWAY = 'takeaway';
    public const TYPE_DELIVERY = 'delivery';

    // Boot
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }
        });
    }

    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $date = now()->format('ymd');
        $random = strtoupper(Str::random(6));
        return "{$prefix}-{$date}-{$random}";
    }

    // Relationships
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at', 'desc');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_REFUNDED]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    // Helpers
    public function updateStatus(string $newStatus, ?int $userId = null, ?string $notes = null): void
    {
        $oldStatus = $this->status;
        $this->status = $newStatus;
        
        // Update timestamp based on status (only for columns that exist)
        if ($newStatus === self::STATUS_COMPLETED && $this->type === 'delivery') {
            $this->delivered_at = now();
        }

        $this->save();

        // Log status change to history table
        try {
            \Illuminate\Support\Facades\DB::connection('tenant')->table('order_status_history')->insert([
                'order_id' => $this->id,
                'status' => $newStatus,
                'previous_status' => $oldStatus,
                'notes' => $notes,
                'changed_by' => $userId,
                'changed_by_type' => $userId ? 'user' : 'system',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Status history table might not exist, ignore
        }
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }
}
