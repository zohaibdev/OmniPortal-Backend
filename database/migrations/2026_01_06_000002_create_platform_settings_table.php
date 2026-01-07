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
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->index(); // general, email, stripe, storage, seo, security
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string, number, boolean, json, encrypted
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->json('options')->nullable(); // For select fields
            $table->boolean('is_public')->default(false); // Can be exposed to frontend
            $table->boolean('is_encrypted')->default(false); // Should value be encrypted
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
