<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number', 20)->comment('WhatsApp Business phone number');
            $table->string('phone_number_id')->unique()->comment('Meta phone_number_id');
            $table->string('waba_id')->nullable()->comment('WhatsApp Business Account ID');
            $table->text('access_token')->comment('Encrypted access token');
            $table->enum('status', ['pending', 'active', 'inactive', 'failed'])->default('pending');
            $table->string('display_name')->nullable();
            $table->string('quality_rating')->nullable()->comment('Green, Yellow, Red');
            $table->json('messaging_limits')->nullable()->comment('Rate limits from Meta');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->text('webhook_verification_token')->nullable();
            $table->json('meta')->nullable()->comment('Additional metadata from Meta');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'status']);
            $table->index('phone_number');
        });

        // Add default whatsapp_account_id to stores table
        Schema::table('stores', function (Blueprint $table) {
            $table->foreignId('whatsapp_account_id')->nullable()->after('whatsapp_webhook_url')->constrained('whatsapp_accounts')->nullOnDelete();
            $table->boolean('ai_enabled')->default(true)->after('whatsapp_account_id');
            $table->boolean('storefront_enabled')->default(false)->after('ai_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_account_id']);
            $table->dropColumn(['whatsapp_account_id', 'ai_enabled', 'storefront_enabled']);
        });

        Schema::dropIfExists('whatsapp_accounts');
    }
};
