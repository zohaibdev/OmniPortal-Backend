<?php

namespace Database\Seeders;

use App\Models\Owner;
use App\Models\Store;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Super Admin (Platform Admin)
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@omniportal.com',
            'password' => Hash::make('password'),
            'type' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Create Demo Store Owner
        $owner = Owner::create([
            'name' => 'John Restaurant Owner',
            'email' => 'owner@demo.com',
            'password' => Hash::make('password'),
            'phone' => '+1234567890',
            'company_name' => 'Demo Restaurant Group',
            'status' => 'active',
            'is_verified' => true,
            'verified_at' => now(),
            'email_verified_at' => now(),
        ]);

        // Create Demo Store for the Owner
        Store::create([
            'owner_id' => $owner->id,
            'name' => 'Burger Lab',
            'slug' => 'burger-lab',
            'description' => 'The best burgers in town',
            'email' => 'contact@burgerlab.com',
            'phone' => '+1234567890',
            'address' => '123 Main Street',
            'city' => 'New York',
            'state' => 'NY',
            'country' => 'US',
            'postal_code' => '10001',
            'timezone' => 'America/New_York',
            'currency' => 'USD',
            'status' => 'active',
            'is_active' => true,
        ]);

        // Create Subscription Plan
        SubscriptionPlan::create([
            'name' => 'OmniPortal Pro',
            'slug' => 'omniportal-pro',
            'description' => 'Full access to all OmniPortal features for your restaurant',
            'price' => 300.00,
            'currency' => 'USD',
            'interval' => 'monthly',
            'trial_days' => 7,
            'features' => [
                'Unlimited Products',
                'Unlimited Orders',
                'Unlimited Employees',
                'POS System',
                'Multi-Currency Support',
                'Custom Domain',
                'Advanced Analytics',
                'API Access',
                '24/7 Priority Support',
            ],
            'max_products' => null,
            'max_orders_per_month' => null,
            'max_employees' => null,
            'custom_domain_allowed' => true,
            'pos_enabled' => true,
            'multi_currency_enabled' => true,
            'advanced_analytics' => true,
            'priority_support' => true,
            'is_active' => true,
            'is_featured' => true,
            'sort_order' => 1,
        ]);
    }
}
