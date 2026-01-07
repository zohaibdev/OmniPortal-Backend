<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Meta fields table for storing custom data on various resources (like Shopify metafields)
     * Supports: products, customers, orders, and can be extended to other models
     */
    public function up(): void
    {
        Schema::create('meta_fields', function (Blueprint $table) {
            $table->id();
            
            // Polymorphic relationship to parent model
            $table->morphs('metafieldable');
            
            // Namespace for grouping related fields (e.g., 'custom', 'shipping', 'loyalty')
            $table->string('namespace', 100)->default('custom');
            
            // Key identifier (e.g., 'color', 'size', 'loyalty_points')
            $table->string('key', 100);
            
            // Value - stored as JSON to support multiple types
            $table->json('value')->nullable();
            
            // Type hint for frontend rendering
            // Supported: string, integer, decimal, boolean, json, date, datetime, url, email, color, file, rich_text
            $table->string('type', 50)->default('string');
            
            // Human-readable name for display
            $table->string('name', 191)->nullable();
            
            // Description/help text
            $table->text('description')->nullable();
            
            // Validation rules (JSON format)
            $table->json('validation')->nullable();
            
            // Display order
            $table->integer('sort_order')->default(0);
            
            // Visibility settings
            $table->boolean('is_visible_to_customer')->default(false);
            $table->boolean('is_required')->default(false);
            
            $table->timestamps();
            
            // Unique constraint: one key per namespace per resource
            $table->unique(['metafieldable_type', 'metafieldable_id', 'namespace', 'key'], 'meta_fields_unique');
            
            // Index for common queries - morphs() already creates an index, so we just need namespace.key
            $table->index(['namespace', 'key']);
        });
        
        // Meta field definitions table - defines available meta fields for each resource type
        Schema::create('meta_field_definitions', function (Blueprint $table) {
            $table->id();
            
            // Resource type (e.g., 'App\Models\Tenant\Product', 'App\Models\Tenant\Customer')
            $table->string('resource_type', 191);
            
            // Namespace and key
            $table->string('namespace', 100)->default('custom');
            $table->string('key', 100);
            
            // Display name
            $table->string('name', 191);
            
            // Description
            $table->text('description')->nullable();
            
            // Data type
            $table->string('type', 50)->default('string');
            
            // Default value (JSON)
            $table->json('default_value')->nullable();
            
            // Validation rules (JSON)
            $table->json('validation')->nullable();
            
            // Options for select/multiselect types (JSON array)
            $table->json('options')->nullable();
            
            // Display settings
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible_to_customer')->default(false);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            
            // Pin to specific location in UI (e.g., 'sidebar', 'main', 'bottom')
            $table->string('ui_position', 50)->nullable();
            
            $table->timestamps();
            
            // Unique constraint
            $table->unique(['resource_type', 'namespace', 'key'], 'meta_field_defs_unique');
            
            // Index for lookups
            $table->index('resource_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_field_definitions');
        Schema::dropIfExists('meta_fields');
    }
};
