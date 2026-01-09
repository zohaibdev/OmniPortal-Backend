<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Delivery agents (restaurant only)
        Schema::create('delivery_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('current_orders')->default(0);
            $table->integer('max_orders')->default(3);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active']);
        });

        // Add delivery agent to orders
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('delivery_agent_id')->nullable()->after('assigned_employee_id')->constrained('delivery_agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['delivery_agent_id']);
            $table->dropColumn('delivery_agent_id');
        });
        
        Schema::dropIfExists('delivery_agents');
    }
};
