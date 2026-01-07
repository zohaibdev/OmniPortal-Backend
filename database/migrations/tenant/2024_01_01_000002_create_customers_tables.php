<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customers table
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('encrypted_id', 50)->unique()->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // Link to main db user
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('email', 100);
            $table->string('phone', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('avatar')->nullable();
            $table->text('notes')->nullable();
            $table->json('preferences')->nullable(); // Dietary preferences, communication
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->integer('orders_count')->default(0);
            $table->decimal('loyalty_points', 10, 2)->default(0);
            $table->string('loyalty_tier', 20)->default('bronze');
            $table->timestamp('last_order_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('verification_token')->nullable();
            $table->json('marketing_consent')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['email']);
            $table->index(['user_id']);
            $table->index(['status']);
            $table->index(['loyalty_tier']);
            $table->index(['last_order_at']);
        });

        // Customer addresses
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50)->default('Home'); // Home, Work, etc.
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('phone', 20)->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->default('US');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('delivery_instructions')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['customer_id', 'is_default']);
        });

        // Customer loyalty history
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->enum('type', ['earned', 'redeemed', 'expired', 'adjusted']);
            $table->decimal('points', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->string('description')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'type']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
