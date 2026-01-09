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
        Schema::table('stores', function (Blueprint $table) {
            // WhatsApp Business Configuration
            if (!Schema::hasColumn('stores', 'whatsapp_business_number')) {
                $table->string('whatsapp_business_number')->nullable()->after('phone')->comment('WhatsApp Business Phone Number');
            }
            if (!Schema::hasColumn('stores', 'whatsapp_business_id')) {
                $table->string('whatsapp_business_id')->nullable()->after('whatsapp_business_number')->comment('WhatsApp Business Account ID');
            }
            if (!Schema::hasColumn('stores', 'whatsapp_webhook_url')) {
                $table->string('whatsapp_webhook_url')->nullable()->after('whatsapp_business_id')->comment('Webhook URL for WhatsApp events');
            }
            
            // Business Type (should exist from 2026_01_09_000001)
            if (!Schema::hasColumn('stores', 'business_type')) {
                $table->enum('business_type', ['restaurant', 'clothing', 'electronics', 'grocery', 'services', 'other'])
                    ->default('other')
                    ->after('slug')
                    ->comment('Type of business for AI behavior adaptation');
            }
            
            // Currency defaults
            if (Schema::hasColumn('stores', 'currency')) {
                $table->string('currency')->default('PKR')->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_business_number',
                'whatsapp_business_id',
                'whatsapp_webhook_url',
            ]);
        });
    }
};
