<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            [
                'name' => 'Cash on Delivery',
                'type' => 'offline',
                'description' => 'Pay when your order arrives',
                'is_active' => true,
            ],
            [
                'name' => 'EasyPaisa',
                'type' => 'online',
                'description' => 'Mobile payment via EasyPaisa',
                'settings' => [
                    'merchant_id' => null,
                    'store_id' => null,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'JazzCash',
                'type' => 'online',
                'description' => 'Mobile payment via JazzCash',
                'settings' => [
                    'merchant_id' => null,
                    'pp_key' => null,
                    'account' => null,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Bank Transfer',
                'type' => 'online',
                'description' => 'Direct bank transfer',
                'settings' => [
                    'account_title' => null,
                    'account_number' => null,
                    'bank_name' => null,
                    'iban' => null,
                ],
                'is_active' => true,
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::firstOrCreate(
                ['name' => $method['name']],
                $method
            );
        }
    }
}
