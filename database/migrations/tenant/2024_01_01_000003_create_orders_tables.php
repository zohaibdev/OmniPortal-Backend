<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orders table
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('encrypted_id', 50)->unique()->nullable();
            $table->string('order_number', 30)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('address_id')->nullable();
            
            // Order details
            $table->enum('type', ['delivery', 'pickup', 'dine_in', 'pos'])->default('delivery');
            $table->enum('status', [
                'pending', 'confirmed', 'preparing', 'ready', 
                'out_for_delivery', 'delivered', 'completed', 
                'cancelled', 'refunded'
            ])->default('pending');
            
            // Pricing
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('service_fee', 10, 2)->default(0);
            $table->decimal('tip_amount', 10, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('currency', 3)->default('USD');
            
            // Payment
            $table->enum('payment_status', ['pending', 'paid', 'partially_paid', 'refunded', 'failed'])->default('pending');
            $table->string('payment_method', 50)->nullable();
            $table->timestamp('paid_at')->nullable();
            
            // Coupon
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->string('coupon_code', 30)->nullable();
            
            // Customer info (for guest orders)
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 20)->nullable();
            
            // Delivery info
            $table->json('delivery_address')->nullable();
            $table->text('delivery_instructions')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            
            // Additional
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('source', 30)->default('web'); // web, app, pos, phone
            $table->unsignedBigInteger('assigned_employee_id')->nullable();
            $table->string('created_by_type', 20)->nullable(); // owner, employee
            $table->string('created_by_name')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->json('meta_data')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_number']);
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['payment_status']);
            $table->index(['type', 'status']);
            $table->index(['scheduled_at']);
        });

        // Order items
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->string('sku', 50)->nullable();
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->json('options')->nullable(); // Selected options
            $table->json('addons')->nullable(); // Selected addons with prices
            $table->text('special_instructions')->nullable();
            $table->enum('status', ['pending', 'preparing', 'ready', 'served', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['product_id']);
        });

        // Order status history
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->string('previous_status', 30)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable(); // Employee/User ID
            $table->string('changed_by_type', 20)->default('system');
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });

        // Payments table
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('encrypted_id', 50)->unique()->nullable();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_id')->unique()->nullable();
            $table->string('gateway', 30); // stripe, paypal, cash, card
            $table->string('method', 30); // credit_card, debit_card, cash, etc.
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'refunded', 'partially_refunded'])->default('pending');
            $table->string('gateway_response_code')->nullable();
            $table->text('gateway_response')->nullable();
            $table->json('meta_data')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['transaction_id']);
            $table->index(['status']);
        });

        // Refunds table
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('refund_id')->unique()->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->enum('reason', ['customer_request', 'order_issue', 'quality_issue', 'other']);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
