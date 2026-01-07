<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add password column to employees table
        if (!Schema::hasColumn('employees', 'password')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('password')->nullable()->after('pin');
            });
        }

        // Add creator columns to orders table
        if (!Schema::hasColumn('orders', 'created_by_type')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('created_by_type', 20)->nullable()->after('assigned_employee_id');
                $table->string('created_by_name')->nullable()->after('created_by_type');
                $table->unsignedBigInteger('created_by_id')->nullable()->after('created_by_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'password')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('password');
            });
        }

        if (Schema::hasColumn('orders', 'created_by_type')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn(['created_by_type', 'created_by_name', 'created_by_id']);
            });
        }
    }
};
