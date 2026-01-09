<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Payment Methods table
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Payment method name (Cash on Delivery, EasyPaisa, etc)');
            $table->enum('type', ['offline', 'online'])->comment('Offline or online payment');
            $table->text('description')->nullable();
            $table->json('settings')->nullable()->comment('Payment gateway settings');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Pivot table: Stores & Payment Methods
        Schema::create('store_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->cascadeOnDelete();
            $table->integer('display_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            
            $table->unique(['store_id', 'payment_method_id']);
        });

        // Delivery Agents table (for restaurants)
        Schema::create('delivery_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('store_id');
            $table->index('is_active');
        });

        // Orders table enhancements
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'payment_method_id')) {
                    $table->foreignId('payment_method_id')->nullable()->after('customer_id')->constrained('payment_methods');
                }
                if (!Schema::hasColumn('orders', 'payment_status')) {
                    $table->enum('payment_status', ['pending_verification', 'paid', 'rejected', 'cancelled'])->default('pending_verification')->after('payment_method_id');
                }
                if (!Schema::hasColumn('orders', 'payment_proof_path')) {
                    $table->string('payment_proof_path')->nullable()->after('payment_status')->comment('Path to payment screenshot');
                }
                if (!Schema::hasColumn('orders', 'delivery_agent_id')) {
                    $table->foreignId('delivery_agent_id')->nullable()->after('payment_proof_path')->constrained('delivery_agents')->nullOnDelete();
                }
                if (!Schema::hasColumn('orders', 'conversation_state')) {
                    $table->string('conversation_state')->default('created')->after('delivery_agent_id')->comment('WhatsApp conversation state for AI');
                }
                if (!Schema::hasColumn('orders', 'whatsapp_message_id')) {
                    $table->string('whatsapp_message_id')->nullable()->after('conversation_state')->comment('Last WhatsApp message ID in conversation');
                }
            });
        }

        // AI Test Cases table
        Schema::create('ai_test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('business_type');
            $table->text('user_message');
            $table->string('expected_intent')->comment('Expected AI intent/action');
            $table->json('expected_fields')->nullable()->comment('Expected extracted fields');
            $table->json('test_result')->nullable()->comment('Actual test result');
            $table->enum('status', ['pending', 'pass', 'fail'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'status']);
        });

        // Conversation logs for AI training
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('customer_phone', 20);
            $table->string('customer_name')->nullable();
            $table->enum('message_type', ['text', 'image', 'voice', 'document'])->default('text');
            $table->text('message_content');
            $table->string('whatsapp_message_id');
            $table->enum('direction', ['inbound', 'outbound'])->comment('Direction of message');
            $table->json('ai_analysis')->nullable()->comment('AI intent and extracted fields');
            $table->timestamps();
            
            $table->index(['store_id', 'customer_phone']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('ai_test_cases');
        Schema::dropIfExists('store_payment_methods');
        Schema::dropIfExists('delivery_agents');
        Schema::dropIfExists('payment_methods');
    }
};
