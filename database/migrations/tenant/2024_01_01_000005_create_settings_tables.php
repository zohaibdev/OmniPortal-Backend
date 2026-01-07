<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Store settings (key-value)
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->default('general');
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string, boolean, integer, json, encrypted
            $table->boolean('is_public')->default(false); // Exposed to frontend
            $table->timestamps();

            $table->unique(['group', 'key']);
            $table->index(['group']);
        });

        // Operating hours
        Schema::create('operating_hours', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('day_of_week'); // 0 = Sunday, 6 = Saturday
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->json('breaks')->nullable(); // [{start: "14:00", end: "15:00"}]
            $table->timestamps();

            $table->unique(['day_of_week']);
        });

        // Special hours (holidays, etc.)
        Schema::create('special_hours', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name', 100)->nullable(); // "Christmas", "New Year"
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['date']);
        });

        // Delivery zones
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('type', ['radius', 'polygon', 'postal_codes']);
            $table->decimal('min_order', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('free_delivery_threshold', 10, 2)->nullable();
            $table->integer('estimated_time_min')->nullable(); // minutes
            $table->integer('estimated_time_max')->nullable();
            $table->json('zone_data')->nullable(); // radius/polygon/postal codes data
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // For overlapping zones
            $table->timestamps();

            $table->index(['is_active']);
        });

        // Tax rates
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->decimal('rate', 5, 2); // Percentage
            $table->string('country', 2)->default('US');
            $table->string('state', 50)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->boolean('is_compound')->default(false);
            $table->boolean('is_shipping_taxable')->default(false);
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['country', 'state', 'is_active']);
        });

        // Tax classes (for products)
        Schema::create('tax_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 60)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Employees (store staff)
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('encrypted_id', 50)->unique()->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // Link to main db
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('email', 100)->unique();
            $table->string('phone', 20)->nullable();
            $table->string('avatar')->nullable();
            $table->string('role', 30)->default('staff'); // manager, staff, cashier, delivery
            $table->json('permissions')->nullable();
            $table->string('pin', 10)->nullable(); // For POS login
            $table->string('password')->nullable(); // For dashboard login
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->date('hire_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id']);
            $table->index(['role', 'status']);
        });

        // Employee shifts
        Schema::create('employee_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamp('clock_in');
            $table->timestamp('clock_out')->nullable();
            $table->json('breaks')->nullable(); // [{start, end}]
            $table->decimal('total_hours', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'clock_in']);
        });

        // Activity logs
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('log_name', 50)->default('default');
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->json('properties')->nullable();
            $table->string('event', 50)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['log_name']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['causer_type', 'causer_id']);
            $table->index(['created_at']);
        });

        // Notifications
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
        });

        // Email templates
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('subject');
            $table->longText('body_html');
            $table->longText('body_text')->nullable();
            $table->json('variables')->nullable(); // Available placeholders
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Scheduled reports
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 50); // sales, inventory, customers
            $table->json('parameters')->nullable();
            $table->string('frequency', 20); // daily, weekly, monthly
            $table->string('recipients'); // Comma-separated emails
            $table->string('format', 10)->default('pdf'); // pdf, csv, xlsx
            $table->time('send_time')->default('08:00:00');
            $table->timestamp('last_sent_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Analytics/Stats cache
        Schema::create('analytics_cache', function (Blueprint $table) {
            $table->id();
            $table->string('metric', 50);
            $table->string('period', 20); // daily, weekly, monthly, yearly
            $table->date('date');
            $table->json('data');
            $table->timestamps();

            $table->unique(['metric', 'period', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_cache');
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('employee_shifts');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('tax_classes');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('special_hours');
        Schema::dropIfExists('operating_hours');
        Schema::dropIfExists('settings');
    }
};
