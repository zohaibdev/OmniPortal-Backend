<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CMS Pages with versioning support
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('encrypted_id', 50)->unique()->nullable();
            $table->string('title', 191);
            $table->string('slug', 191)->unique();
            $table->longText('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('template', 50)->default('default');
            $table->enum('status', ['draft', 'published', 'scheduled', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            
            // SEO
            $table->string('meta_title', 191)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords', 191)->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('og_image')->nullable();
            $table->json('structured_data')->nullable(); // JSON-LD
            
            // Settings
            $table->boolean('show_in_menu')->default(false);
            $table->boolean('show_in_footer')->default(false);
            $table->integer('menu_order')->default(0);
            $table->boolean('is_homepage')->default(false);
            $table->boolean('requires_auth')->default(false);
            
            // Tracking
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['show_in_menu', 'menu_order']);
        });

        // Page versions for revision history
        Schema::create('page_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->integer('version_number');
            $table->string('title', 191);
            $table->longText('content')->nullable();
            $table->json('meta_data')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('change_summary')->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'version_number']);
        });

        // Media library
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('encrypted_id', 50)->unique()->nullable();
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type', 100);
            $table->string('disk', 20)->default('public');
            $table->string('path');
            $table->unsignedBigInteger('size'); // bytes
            $table->json('dimensions')->nullable(); // {width, height} for images
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->string('folder', 100)->default('/');
            $table->json('meta_data')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['folder']);
            $table->index(['mime_type']);
        });

        // Banners/Sliders
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('encrypted_id', 50)->unique()->nullable();
            $table->string('title')->nullable();
            $table->text('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('image');
            $table->string('mobile_image')->nullable();
            $table->string('video_url')->nullable();
            $table->string('link_url')->nullable();
            $table->string('link_text', 50)->nullable();
            $table->enum('link_target', ['_self', '_blank'])->default('_self');
            $table->string('position', 30)->default('hero'); // hero, sidebar, footer, popup
            $table->string('location', 50)->default('home'); // home, category, product, checkout
            $table->json('button_style')->nullable(); // {color, bg_color, size}
            $table->json('text_style')->nullable(); // {color, alignment, position}
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['position', 'location', 'is_active']);
            $table->index(['starts_at', 'ends_at']);
        });

        // Navigation menus
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 60)->unique();
            $table->string('location', 30)->default('header'); // header, footer, mobile
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('title', 100);
            $table->enum('type', ['page', 'category', 'product', 'custom', 'divider']);
            $table->unsignedBigInteger('target_id')->nullable(); // page_id, category_id, etc.
            $table->string('url')->nullable(); // for custom links
            $table->enum('target', ['_self', '_blank'])->default('_self');
            $table->string('icon', 50)->nullable();
            $table->string('css_class', 100)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sort_order']);
        });

        // Coupons/Promotions
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('encrypted_id', 50)->unique()->nullable();
            $table->string('code', 30)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed', 'free_shipping', 'buy_x_get_y'])->default('percentage');
            $table->decimal('value', 10, 2);
            $table->decimal('minimum_order', 10, 2)->nullable();
            $table->decimal('maximum_discount', 10, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_limit_per_user')->default(1);
            $table->integer('times_used')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(false); // Show on storefront
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('conditions')->nullable(); // Complex rules
            $table->json('applicable_products')->nullable();
            $table->json('applicable_categories')->nullable();
            $table->json('excluded_products')->nullable();
            $table->boolean('first_order_only')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'starts_at', 'expires_at']);
        });

        // Coupon usage tracking
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->decimal('discount_amount', 10, 2);
            $table->timestamps();

            $table->index(['coupon_id', 'customer_id']);
        });

        // Reviews and ratings
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('encrypted_id', 50)->unique()->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->tinyInteger('rating'); // 1-5
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->json('images')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('is_verified_purchase')->default(false);
            $table->integer('helpful_count')->default(0);
            $table->text('admin_response')->nullable();
            $table->timestamp('admin_responded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'status']);
            $table->index(['customer_id']);
            $table->index(['rating']);
        });

        // FAQ
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50)->default('general');
            $table->string('question');
            $table->text('answer');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->integer('helpful_count')->default(0);
            $table->timestamps();

            $table->index(['category', 'is_active', 'sort_order']);
        });

        // Announcements/Notifications bar
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('message');
            $table->string('link_url')->nullable();
            $table->string('link_text', 30)->nullable();
            $table->string('bg_color', 20)->default('#f59e0b');
            $table->string('text_color', 20)->default('#ffffff');
            $table->enum('position', ['top', 'bottom'])->default('top');
            $table->boolean('is_dismissible')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('media');
        Schema::dropIfExists('page_versions');
        Schema::dropIfExists('pages');
    }
};
