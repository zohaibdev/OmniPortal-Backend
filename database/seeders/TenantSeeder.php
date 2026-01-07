<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantSeeder extends Seeder
{
    /**
     * Seed the tenant database with default data
     */
    public function run(): void
    {
        $this->seedDefaultSettings();
        $this->seedOperatingHours();
        $this->seedDefaultMenus();
        $this->seedDefaultEmailTemplates();
        $this->seedDefaultTaxClasses();
    }

    /**
     * Seed default store settings
     */
    private function seedDefaultSettings(): void
    {
        $settings = [
            // General settings
            ['group' => 'general', 'key' => 'store_name', 'value' => 'My Store', 'type' => 'string', 'is_public' => true],
            ['group' => 'general', 'key' => 'store_tagline', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'general', 'key' => 'timezone', 'value' => 'America/New_York', 'type' => 'string', 'is_public' => false],
            ['group' => 'general', 'key' => 'currency', 'value' => 'USD', 'type' => 'string', 'is_public' => true],
            ['group' => 'general', 'key' => 'locale', 'value' => 'en_US', 'type' => 'string', 'is_public' => true],
            
            // Contact settings
            ['group' => 'contact', 'key' => 'email', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'contact', 'key' => 'phone', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'contact', 'key' => 'address', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'contact', 'key' => 'city', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'contact', 'key' => 'state', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'contact', 'key' => 'postal_code', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'contact', 'key' => 'country', 'value' => 'US', 'type' => 'string', 'is_public' => true],
            
            // Order settings
            ['group' => 'orders', 'key' => 'order_prefix', 'value' => 'ORD', 'type' => 'string', 'is_public' => false],
            ['group' => 'orders', 'key' => 'min_order_amount', 'value' => '0', 'type' => 'decimal', 'is_public' => true],
            ['group' => 'orders', 'key' => 'allow_guest_checkout', 'value' => 'true', 'type' => 'boolean', 'is_public' => true],
            ['group' => 'orders', 'key' => 'auto_confirm_orders', 'value' => 'false', 'type' => 'boolean', 'is_public' => false],
            ['group' => 'orders', 'key' => 'order_confirmation_email', 'value' => 'true', 'type' => 'boolean', 'is_public' => false],
            
            // Delivery settings
            ['group' => 'delivery', 'key' => 'delivery_enabled', 'value' => 'true', 'type' => 'boolean', 'is_public' => true],
            ['group' => 'delivery', 'key' => 'pickup_enabled', 'value' => 'true', 'type' => 'boolean', 'is_public' => true],
            ['group' => 'delivery', 'key' => 'dine_in_enabled', 'value' => 'false', 'type' => 'boolean', 'is_public' => true],
            ['group' => 'delivery', 'key' => 'default_delivery_fee', 'value' => '5.00', 'type' => 'decimal', 'is_public' => true],
            ['group' => 'delivery', 'key' => 'free_delivery_threshold', 'value' => '50.00', 'type' => 'decimal', 'is_public' => true],
            ['group' => 'delivery', 'key' => 'estimated_delivery_time', 'value' => '30-45', 'type' => 'string', 'is_public' => true],
            
            // Tax settings
            ['group' => 'tax', 'key' => 'tax_enabled', 'value' => 'true', 'type' => 'boolean', 'is_public' => false],
            ['group' => 'tax', 'key' => 'default_tax_rate', 'value' => '8.25', 'type' => 'decimal', 'is_public' => false],
            ['group' => 'tax', 'key' => 'prices_include_tax', 'value' => 'false', 'type' => 'boolean', 'is_public' => true],
            ['group' => 'tax', 'key' => 'tax_number', 'value' => '', 'type' => 'string', 'is_public' => false],
            
            // Payment settings
            ['group' => 'payment', 'key' => 'cash_enabled', 'value' => 'true', 'type' => 'boolean', 'is_public' => true],
            ['group' => 'payment', 'key' => 'card_enabled', 'value' => 'true', 'type' => 'boolean', 'is_public' => true],
            ['group' => 'payment', 'key' => 'stripe_enabled', 'value' => 'false', 'type' => 'boolean', 'is_public' => true],
            ['group' => 'payment', 'key' => 'tips_enabled', 'value' => 'true', 'type' => 'boolean', 'is_public' => true],
            ['group' => 'payment', 'key' => 'tip_options', 'value' => '["15","18","20","25"]', 'type' => 'json', 'is_public' => true],
            
            // Appearance settings
            ['group' => 'appearance', 'key' => 'primary_color', 'value' => '#16a34a', 'type' => 'string', 'is_public' => true],
            ['group' => 'appearance', 'key' => 'secondary_color', 'value' => '#f59e0b', 'type' => 'string', 'is_public' => true],
            ['group' => 'appearance', 'key' => 'logo', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'appearance', 'key' => 'favicon', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'appearance', 'key' => 'banner', 'value' => '', 'type' => 'string', 'is_public' => true],
            
            // SEO settings
            ['group' => 'seo', 'key' => 'meta_title', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'seo', 'key' => 'meta_description', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'seo', 'key' => 'meta_keywords', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'seo', 'key' => 'google_analytics_id', 'value' => '', 'type' => 'string', 'is_public' => false],
            ['group' => 'seo', 'key' => 'facebook_pixel_id', 'value' => '', 'type' => 'string', 'is_public' => false],
            
            // Social media
            ['group' => 'social', 'key' => 'facebook_url', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'social', 'key' => 'instagram_url', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'social', 'key' => 'twitter_url', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'social', 'key' => 'youtube_url', 'value' => '', 'type' => 'string', 'is_public' => true],
            
            // Notification settings
            ['group' => 'notifications', 'key' => 'new_order_email', 'value' => 'true', 'type' => 'boolean', 'is_public' => false],
            ['group' => 'notifications', 'key' => 'new_order_sound', 'value' => 'true', 'type' => 'boolean', 'is_public' => false],
            ['group' => 'notifications', 'key' => 'low_stock_alert', 'value' => 'true', 'type' => 'boolean', 'is_public' => false],
            ['group' => 'notifications', 'key' => 'daily_report_email', 'value' => 'false', 'type' => 'boolean', 'is_public' => false],
        ];

        $now = now();
        foreach ($settings as &$setting) {
            $setting['created_at'] = $now;
            $setting['updated_at'] = $now;
        }

        DB::table('settings')->insert($settings);
    }

    /**
     * Seed default operating hours
     */
    private function seedOperatingHours(): void
    {
        $hours = [];
        $now = now();
        
        for ($day = 0; $day <= 6; $day++) {
            $hours[] = [
                'day_of_week' => $day,
                'open_time' => '09:00:00',
                'close_time' => '21:00:00',
                'is_closed' => false,
                'breaks' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('operating_hours')->insert($hours);
    }

    /**
     * Seed default navigation menus
     */
    private function seedDefaultMenus(): void
    {
        $now = now();
        
        // Header menu
        DB::table('menus')->insert([
            'name' => 'Main Menu',
            'slug' => 'main-menu',
            'location' => 'header',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Footer menu
        DB::table('menus')->insert([
            'name' => 'Footer Menu',
            'slug' => 'footer-menu',
            'location' => 'footer',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Seed default email templates
     */
    private function seedDefaultEmailTemplates(): void
    {
        $now = now();
        
        $templates = [
            [
                'name' => 'order_confirmation',
                'subject' => 'Order Confirmation - #{order_number}',
                'body_html' => '<h1>Thank you for your order!</h1><p>Your order #{order_number} has been received.</p><p>Total: {total}</p>',
                'body_text' => 'Thank you for your order! Your order #{order_number} has been received. Total: {total}',
                'variables' => json_encode(['order_number', 'total', 'customer_name', 'items']),
                'is_active' => true,
            ],
            [
                'name' => 'order_status_update',
                'subject' => 'Order Update - #{order_number}',
                'body_html' => '<h1>Order Status Update</h1><p>Your order #{order_number} is now {status}.</p>',
                'body_text' => 'Your order #{order_number} is now {status}.',
                'variables' => json_encode(['order_number', 'status', 'customer_name']),
                'is_active' => true,
            ],
            [
                'name' => 'welcome_customer',
                'subject' => 'Welcome to {store_name}!',
                'body_html' => '<h1>Welcome, {customer_name}!</h1><p>Thank you for creating an account with us.</p>',
                'body_text' => 'Welcome, {customer_name}! Thank you for creating an account with us.',
                'variables' => json_encode(['customer_name', 'store_name']),
                'is_active' => true,
            ],
            [
                'name' => 'password_reset',
                'subject' => 'Reset Your Password',
                'body_html' => '<h1>Password Reset</h1><p>Click the link below to reset your password:</p><p><a href="{reset_link}">Reset Password</a></p>',
                'body_text' => 'Click the link to reset your password: {reset_link}',
                'variables' => json_encode(['reset_link', 'customer_name']),
                'is_active' => true,
            ],
        ];

        foreach ($templates as &$template) {
            $template['created_at'] = $now;
            $template['updated_at'] = $now;
        }

        DB::table('email_templates')->insert($templates);
    }

    /**
     * Seed default tax classes
     */
    private function seedDefaultTaxClasses(): void
    {
        $now = now();
        
        $taxClasses = [
            [
                'name' => 'Standard',
                'slug' => 'standard',
                'description' => 'Standard tax rate for most products',
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Reduced',
                'slug' => 'reduced',
                'description' => 'Reduced tax rate for essential items',
                'is_default' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Zero Rate',
                'slug' => 'zero-rate',
                'description' => 'Zero tax rate for exempt items',
                'is_default' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('tax_classes')->insert($taxClasses);
    }
}
