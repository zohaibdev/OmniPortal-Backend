<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppConversation extends Model
{
    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'store_id',
        'order_id',
        'customer_phone',
        'customer_name',
        'message_type',
        'message_content',
        'whatsapp_message_id',
        'direction',
        'ai_analysis',
    ];

    protected function casts(): array
    {
        return [
            'ai_analysis' => 'array',
        ];
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

    // Scopes
    public function scopeByStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    public function scopeByCustomer($query, $phone)
    {
        return $query->where('customer_phone', $phone);
    }

    // Helpers
    public function isVoiceMessage(): bool
    {
        return $this->message_type === 'voice';
    }

    public function isImageMessage(): bool
    {
        return $this->message_type === 'image';
    }
}
