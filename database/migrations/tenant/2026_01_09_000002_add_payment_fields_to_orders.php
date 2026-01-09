<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('payment_method')->constrained('payment_methods')->nullOnDelete();
            $table->string('payment_proof_path')->nullable()->after('payment_method_id');
            $table->string('conversation_state', 50)->nullable()->after('source');
            $table->json('conversation_context')->nullable()->after('conversation_state');
            
            // Change payment_status to support new values
            $table->string('payment_status', 30)->nullable()->change();
        });

        // Update existing payment_status column to match new enum values
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_status ENUM('pending', 'pending_verification', 'paid', 'rejected', 'failed', 'refunded') DEFAULT 'pending'");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn(['payment_method_id', 'payment_proof_path', 'conversation_state', 'conversation_context']);
        });
    }
};
