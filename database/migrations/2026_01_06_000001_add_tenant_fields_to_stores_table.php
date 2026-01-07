<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('encrypted_id', 50)->unique()->nullable()->after('id');
            $table->string('database_name', 100)->unique()->nullable()->after('meta');
            $table->timestamp('database_created_at')->nullable()->after('database_name');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['encrypted_id', 'database_name', 'database_created_at']);
        });
    }
};
