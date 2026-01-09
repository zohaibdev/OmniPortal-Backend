<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AI test cases
        Schema::create('ai_test_cases', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('business_type', 50)->nullable();
            $table->text('user_message');
            $table->string('expected_intent', 50);
            $table->json('expected_fields')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_type', 'is_active']);
        });

        // AI test results
        Schema::create('ai_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_test_case_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pass', 'fail']);
            $table->string('actual_intent', 50)->nullable();
            $table->json('actual_fields')->nullable();
            $table->text('ai_response')->nullable();
            $table->json('error_details')->nullable();
            $table->timestamp('tested_at');
            $table->timestamps();

            $table->index(['ai_test_case_id', 'status']);
            $table->index(['tested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_test_results');
        Schema::dropIfExists('ai_test_cases');
    }
};
