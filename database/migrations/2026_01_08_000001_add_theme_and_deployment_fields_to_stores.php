<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Theme configuration
            $table->string('theme')->default('default')->after('meta');
            $table->json('theme_config')->nullable()->after('theme');
            
            // Forge deployment tracking
            $table->unsignedBigInteger('forge_site_id')->nullable()->after('theme_config');
            $table->string('forge_site_status')->nullable()->after('forge_site_id');
            $table->timestamp('forge_site_created_at')->nullable()->after('forge_site_status');
            
            // Deployment paths (production paths on server)
            $table->string('deployment_path')->nullable()->after('forge_site_created_at');
            $table->timestamp('last_deployed_at')->nullable()->after('deployment_path');
            
            // SSL certificate status
            $table->boolean('ssl_enabled')->default(false)->after('last_deployed_at');
            $table->timestamp('ssl_expires_at')->nullable()->after('ssl_enabled');
            
            // Index for faster lookups
            $table->index(['forge_site_id']);
            $table->index(['theme']);
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['forge_site_id']);
            $table->dropIndex(['theme']);
            
            $table->dropColumn([
                'theme',
                'theme_config',
                'forge_site_id',
                'forge_site_status',
                'forge_site_created_at',
                'deployment_path',
                'last_deployed_at',
                'ssl_enabled',
                'ssl_expires_at',
            ]);
        });
    }
};
