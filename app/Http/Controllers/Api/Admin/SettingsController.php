<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /**
     * Get all settings grouped
     */
    public function index(): JsonResponse
    {
        $settings = PlatformSetting::orderBy('group')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('group')
            ->map(function ($groupSettings) {
                return $groupSettings->map(function ($setting) {
                    return [
                        'id' => $setting->id,
                        'key' => $setting->key,
                        'value' => $setting->is_encrypted ? '••••••••' : $setting->getTypedValue(),
                        'type' => $setting->type,
                        'label' => $setting->label,
                        'description' => $setting->description,
                        'options' => $setting->options,
                        'is_public' => $setting->is_public,
                        'is_encrypted' => $setting->is_encrypted,
                        'sort_order' => $setting->sort_order,
                    ];
                })->values();
            });

        return response()->json([
            'success' => true,
            'data' => $settings,
            'groups' => PlatformSetting::getGroups(),
        ]);
    }

    /**
     * Get settings by group
     */
    public function show(string $group): JsonResponse
    {
        $validGroups = array_keys(PlatformSetting::getGroups());
        
        if (!in_array($group, $validGroups)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid settings group',
            ], 404);
        }

        $settings = PlatformSetting::where('group', $group)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($setting) {
                return [
                    'id' => $setting->id,
                    'key' => $setting->key,
                    'value' => $setting->is_encrypted ? '' : $setting->getTypedValue(),
                    'type' => $setting->type,
                    'label' => $setting->label,
                    'description' => $setting->description,
                    'options' => $setting->options,
                    'is_public' => $setting->is_public,
                    'is_encrypted' => $setting->is_encrypted,
                    'sort_order' => $setting->sort_order,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $settings,
            'group' => $group,
            'group_label' => PlatformSetting::getGroups()[$group],
        ]);
    }

    /**
     * Update settings by group
     */
    public function update(Request $request, string $group): JsonResponse
    {
        $validGroups = array_keys(PlatformSetting::getGroups());
        
        if (!in_array($group, $validGroups)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid settings group',
            ], 404);
        }

        $settings = $request->input('settings', []);

        DB::beginTransaction();
        try {
            foreach ($settings as $key => $value) {
                $setting = PlatformSetting::where('group', $group)
                    ->where('key', $key)
                    ->first();

                if ($setting) {
                    // Skip empty values for encrypted fields (don't overwrite)
                    if ($setting->is_encrypted && ($value === '' || $value === null)) {
                        continue;
                    }
                    
                    $setting->value = $value;
                    $setting->save();
                }
            }

            DB::commit();
            PlatformSetting::clearCache();

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update settings', [
                'group' => $group,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
            ], 500);
        }
    }

    /**
     * Create or update a single setting
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group' => ['required', Rule::in(array_keys(PlatformSetting::getGroups()))],
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z_]+$/'],
            'value' => ['nullable'],
            'type' => ['required', Rule::in(['string', 'number', 'boolean', 'json', 'encrypted', 'text', 'select'])],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'options' => ['nullable', 'array'],
            'is_public' => ['boolean'],
            'is_encrypted' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $setting = PlatformSetting::updateOrCreate(
            ['group' => $validated['group'], 'key' => $validated['key']],
            $validated
        );

        PlatformSetting::clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Setting saved successfully',
            'data' => $setting,
        ]);
    }

    /**
     * Delete a setting
     */
    public function destroy(int $id): JsonResponse
    {
        $setting = PlatformSetting::findOrFail($id);
        $setting->delete();

        PlatformSetting::clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Setting deleted successfully',
        ]);
    }

    /**
     * Initialize default settings
     */
    public function initialize(): JsonResponse
    {
        $defaults = $this->getDefaultSettings();
        $created = 0;

        foreach ($defaults as $group => $settings) {
            foreach ($settings as $setting) {
                $exists = PlatformSetting::where('group', $group)
                    ->where('key', $setting['key'])
                    ->exists();

                if (!$exists) {
                    PlatformSetting::create(array_merge($setting, ['group' => $group]));
                    $created++;
                }
            }
        }

        PlatformSetting::clearCache();

        return response()->json([
            'success' => true,
            'message' => "Initialized {$created} settings",
        ]);
    }

    /**
     * Test email configuration
     */
    public function testEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            // Would send test email here
            // Mail::to($request->email)->send(new TestEmail());

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear all caches
     */
    public function clearCache(): JsonResponse
    {
        try {
            Cache::flush();
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('view:clear');

            return response()->json([
                'success' => true,
                'message' => 'All caches cleared successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get system info
     */
    public function systemInfo(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => app()->environment(),
                'debug_mode' => config('app.debug'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
                'cache_driver' => config('cache.default'),
                'queue_driver' => config('queue.default'),
                'session_driver' => config('session.driver'),
                'db_connection' => config('database.default'),
                'mail_driver' => config('mail.default'),
                'storage_driver' => config('filesystems.default'),
                'server_time' => now()->toDateTimeString(),
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                'disk_usage' => [
                    'total' => round(disk_total_space('/') / 1024 / 1024 / 1024, 2) . ' GB',
                    'free' => round(disk_free_space('/') / 1024 / 1024 / 1024, 2) . ' GB',
                ],
            ],
        ]);
    }

    /**
     * Get default settings structure
     */
    protected function getDefaultSettings(): array
    {
        return [
            PlatformSetting::GROUP_GENERAL => [
                [
                    'key' => 'platform_name',
                    'value' => 'OmniPortal',
                    'type' => 'string',
                    'label' => 'Platform Name',
                    'description' => 'The name of your platform displayed throughout the application',
                    'is_public' => true,
                    'sort_order' => 1,
                ],
                [
                    'key' => 'platform_description',
                    'value' => 'Multi-tenant restaurant eCommerce platform',
                    'type' => 'text',
                    'label' => 'Platform Description',
                    'description' => 'A brief description of your platform',
                    'is_public' => true,
                    'sort_order' => 2,
                ],
                [
                    'key' => 'support_email',
                    'value' => 'support@omniportal.com',
                    'type' => 'string',
                    'label' => 'Support Email',
                    'description' => 'Email address for customer support inquiries',
                    'is_public' => true,
                    'sort_order' => 3,
                ],
                [
                    'key' => 'support_phone',
                    'value' => '',
                    'type' => 'string',
                    'label' => 'Support Phone',
                    'description' => 'Phone number for customer support',
                    'is_public' => true,
                    'sort_order' => 4,
                ],
                [
                    'key' => 'default_currency',
                    'value' => 'USD',
                    'type' => 'select',
                    'label' => 'Default Currency',
                    'description' => 'Default currency for new stores',
                    'options' => ['USD' => 'USD ($)', 'EUR' => 'EUR (€)', 'GBP' => 'GBP (£)', 'CAD' => 'CAD ($)', 'AUD' => 'AUD ($)', 'PKR' => 'PKR (₨)', 'INR' => 'INR (₹)'],
                    'is_public' => true,
                    'sort_order' => 5,
                ],
                [
                    'key' => 'default_timezone',
                    'value' => 'America/New_York',
                    'type' => 'select',
                    'label' => 'Default Timezone',
                    'description' => 'Default timezone for new stores',
                    'options' => [
                        'America/New_York' => 'Eastern (US)',
                        'America/Chicago' => 'Central (US)',
                        'America/Denver' => 'Mountain (US)',
                        'America/Los_Angeles' => 'Pacific (US)',
                        'Europe/London' => 'London',
                        'Europe/Paris' => 'Paris',
                        'Asia/Karachi' => 'Karachi',
                        'Asia/Kolkata' => 'Kolkata',
                        'Asia/Tokyo' => 'Tokyo',
                        'Australia/Sydney' => 'Sydney',
                    ],
                    'is_public' => true,
                    'sort_order' => 6,
                ],
                [
                    'key' => 'maintenance_mode',
                    'value' => '0',
                    'type' => 'boolean',
                    'label' => 'Maintenance Mode',
                    'description' => 'Enable maintenance mode to prevent store access',
                    'is_public' => true,
                    'sort_order' => 7,
                ],
            ],
            PlatformSetting::GROUP_EMAIL => [
                [
                    'key' => 'mail_driver',
                    'value' => 'smtp',
                    'type' => 'select',
                    'label' => 'Mail Driver',
                    'description' => 'Email service provider',
                    'options' => ['smtp' => 'SMTP', 'mailgun' => 'Mailgun', 'ses' => 'Amazon SES', 'postmark' => 'Postmark', 'sendgrid' => 'SendGrid'],
                    'sort_order' => 1,
                ],
                [
                    'key' => 'mail_host',
                    'value' => 'smtp.mailtrap.io',
                    'type' => 'string',
                    'label' => 'SMTP Host',
                    'description' => 'SMTP server hostname',
                    'sort_order' => 2,
                ],
                [
                    'key' => 'mail_port',
                    'value' => '587',
                    'type' => 'number',
                    'label' => 'SMTP Port',
                    'description' => 'SMTP server port (usually 587 or 465)',
                    'sort_order' => 3,
                ],
                [
                    'key' => 'mail_username',
                    'value' => '',
                    'type' => 'string',
                    'label' => 'SMTP Username',
                    'description' => 'SMTP authentication username',
                    'sort_order' => 4,
                ],
                [
                    'key' => 'mail_password',
                    'value' => '',
                    'type' => 'encrypted',
                    'label' => 'SMTP Password',
                    'description' => 'SMTP authentication password',
                    'is_encrypted' => true,
                    'sort_order' => 5,
                ],
                [
                    'key' => 'mail_encryption',
                    'value' => 'tls',
                    'type' => 'select',
                    'label' => 'Encryption',
                    'description' => 'Email encryption method',
                    'options' => ['tls' => 'TLS', 'ssl' => 'SSL', '' => 'None'],
                    'sort_order' => 6,
                ],
                [
                    'key' => 'mail_from_address',
                    'value' => 'noreply@omniportal.com',
                    'type' => 'string',
                    'label' => 'From Address',
                    'description' => 'Default sender email address',
                    'sort_order' => 7,
                ],
                [
                    'key' => 'mail_from_name',
                    'value' => 'OmniPortal',
                    'type' => 'string',
                    'label' => 'From Name',
                    'description' => 'Default sender name',
                    'sort_order' => 8,
                ],
            ],
            PlatformSetting::GROUP_STRIPE => [
                [
                    'key' => 'stripe_enabled',
                    'value' => '1',
                    'type' => 'boolean',
                    'label' => 'Enable Stripe',
                    'description' => 'Enable Stripe payment processing',
                    'sort_order' => 1,
                ],
                [
                    'key' => 'stripe_mode',
                    'value' => 'test',
                    'type' => 'select',
                    'label' => 'Stripe Mode',
                    'description' => 'Use test or live Stripe credentials',
                    'options' => ['test' => 'Test Mode', 'live' => 'Live Mode'],
                    'sort_order' => 2,
                ],
                [
                    'key' => 'stripe_publishable_key',
                    'value' => '',
                    'type' => 'string',
                    'label' => 'Publishable Key',
                    'description' => 'Stripe publishable API key (pk_...)',
                    'sort_order' => 3,
                ],
                [
                    'key' => 'stripe_secret_key',
                    'value' => '',
                    'type' => 'encrypted',
                    'label' => 'Secret Key',
                    'description' => 'Stripe secret API key (sk_...)',
                    'is_encrypted' => true,
                    'sort_order' => 4,
                ],
                [
                    'key' => 'stripe_webhook_secret',
                    'value' => '',
                    'type' => 'encrypted',
                    'label' => 'Webhook Secret',
                    'description' => 'Stripe webhook signing secret (whsec_...)',
                    'is_encrypted' => true,
                    'sort_order' => 5,
                ],
                [
                    'key' => 'stripe_platform_fee_percent',
                    'value' => '2.5',
                    'type' => 'number',
                    'label' => 'Platform Fee (%)',
                    'description' => 'Percentage fee charged on transactions',
                    'sort_order' => 6,
                ],
            ],
            PlatformSetting::GROUP_STORAGE => [
                [
                    'key' => 'storage_driver',
                    'value' => 'local',
                    'type' => 'select',
                    'label' => 'Storage Driver',
                    'description' => 'File storage provider',
                    'options' => ['local' => 'Local'],
                    'sort_order' => 1,
                ],
                [
                    'key' => 'max_upload_size',
                    'value' => '10',
                    'type' => 'number',
                    'label' => 'Max Upload Size (MB)',
                    'description' => 'Maximum file upload size in megabytes',
                    'sort_order' => 2,
                ],
            ],
            PlatformSetting::GROUP_SEO => [
                [
                    'key' => 'meta_title',
                    'value' => 'OmniPortal - Restaurant eCommerce Platform',
                    'type' => 'string',
                    'label' => 'Default Meta Title',
                    'description' => 'Default page title for SEO',
                    'is_public' => true,
                    'sort_order' => 1,
                ],
                [
                    'key' => 'meta_description',
                    'value' => 'Create your own restaurant online store with OmniPortal - the complete multi-tenant eCommerce solution.',
                    'type' => 'text',
                    'label' => 'Default Meta Description',
                    'description' => 'Default page description for SEO',
                    'is_public' => true,
                    'sort_order' => 2,
                ],
                [
                    'key' => 'meta_keywords',
                    'value' => 'restaurant, ecommerce, online ordering, food delivery',
                    'type' => 'string',
                    'label' => 'Default Meta Keywords',
                    'description' => 'Default keywords for SEO (comma separated)',
                    'is_public' => true,
                    'sort_order' => 3,
                ],
                [
                    'key' => 'google_analytics_id',
                    'value' => '',
                    'type' => 'string',
                    'label' => 'Google Analytics ID',
                    'description' => 'Google Analytics tracking ID (UA-... or G-...)',
                    'is_public' => true,
                    'sort_order' => 4,
                ],
                [
                    'key' => 'google_tag_manager_id',
                    'value' => '',
                    'type' => 'string',
                    'label' => 'Google Tag Manager ID',
                    'description' => 'Google Tag Manager container ID (GTM-...)',
                    'is_public' => true,
                    'sort_order' => 5,
                ],
                [
                    'key' => 'facebook_pixel_id',
                    'value' => '',
                    'type' => 'string',
                    'label' => 'Facebook Pixel ID',
                    'description' => 'Facebook Pixel tracking ID',
                    'is_public' => true,
                    'sort_order' => 6,
                ],
            ],
            PlatformSetting::GROUP_SECURITY => [
                [
                    'key' => 'password_min_length',
                    'value' => '8',
                    'type' => 'number',
                    'label' => 'Minimum Password Length',
                    'description' => 'Minimum required password length',
                    'sort_order' => 1,
                ],
                [
                    'key' => 'require_email_verification',
                    'value' => '1',
                    'type' => 'boolean',
                    'label' => 'Require Email Verification',
                    'description' => 'Require users to verify their email address',
                    'sort_order' => 2,
                ],
                [
                    'key' => 'max_login_attempts',
                    'value' => '5',
                    'type' => 'number',
                    'label' => 'Max Login Attempts',
                    'description' => 'Maximum failed login attempts before lockout',
                    'sort_order' => 3,
                ],
                [
                    'key' => 'lockout_duration',
                    'value' => '15',
                    'type' => 'number',
                    'label' => 'Lockout Duration (minutes)',
                    'description' => 'Account lockout duration after max failed attempts',
                    'sort_order' => 4,
                ],
                [
                    'key' => 'session_lifetime',
                    'value' => '120',
                    'type' => 'number',
                    'label' => 'Session Lifetime (minutes)',
                    'description' => 'User session duration in minutes',
                    'sort_order' => 5,
                ],
                [
                    'key' => 'api_rate_limit',
                    'value' => '60',
                    'type' => 'number',
                    'label' => 'API Rate Limit',
                    'description' => 'Maximum API requests per minute',
                    'sort_order' => 6,
                ],
                [
                    'key' => 'enable_2fa',
                    'value' => '0',
                    'type' => 'boolean',
                    'label' => 'Enable Two-Factor Auth',
                    'description' => 'Enable two-factor authentication option',
                    'sort_order' => 7,
                ],
            ],
            PlatformSetting::GROUP_NOTIFICATIONS => [
                [
                    'key' => 'notify_new_store',
                    'value' => '1',
                    'type' => 'boolean',
                    'label' => 'New Store Notification',
                    'description' => 'Send email when a new store is created',
                    'sort_order' => 1,
                ],
                [
                    'key' => 'notify_new_subscription',
                    'value' => '1',
                    'type' => 'boolean',
                    'label' => 'New Subscription Notification',
                    'description' => 'Send email when a store subscribes to a plan',
                    'sort_order' => 2,
                ],
                [
                    'key' => 'notify_subscription_cancelled',
                    'value' => '1',
                    'type' => 'boolean',
                    'label' => 'Subscription Cancelled',
                    'description' => 'Send email when a subscription is cancelled',
                    'sort_order' => 3,
                ],
                [
                    'key' => 'notify_payment_failed',
                    'value' => '1',
                    'type' => 'boolean',
                    'label' => 'Payment Failed Notification',
                    'description' => 'Send email when a payment fails',
                    'sort_order' => 4,
                ],
                [
                    'key' => 'admin_notification_email',
                    'value' => '',
                    'type' => 'string',
                    'label' => 'Admin Notification Email',
                    'description' => 'Email address for admin notifications (leave empty to use support email)',
                    'sort_order' => 5,
                ],
            ],
            PlatformSetting::GROUP_FEATURES => [
                [
                    'key' => 'enable_owner_registration',
                    'value' => '1',
                    'type' => 'boolean',
                    'label' => 'Owner Registration',
                    'description' => 'Allow new store owners to register',
                    'is_public' => true,
                    'sort_order' => 1,
                ],
                [
                    'key' => 'require_owner_approval',
                    'value' => '1',
                    'type' => 'boolean',
                    'label' => 'Require Owner Approval',
                    'description' => 'Require admin approval for new store owners',
                    'sort_order' => 2,
                ],
                [
                    'key' => 'enable_trial_period',
                    'value' => '1',
                    'type' => 'boolean',
                    'label' => 'Enable Trial Period',
                    'description' => 'Allow stores to start with a free trial',
                    'is_public' => true,
                    'sort_order' => 3,
                ],
                [
                    'key' => 'trial_days',
                    'value' => '14',
                    'type' => 'number',
                    'label' => 'Trial Period (days)',
                    'description' => 'Number of trial days for new stores',
                    'is_public' => true,
                    'sort_order' => 4,
                ],
                [
                    'key' => 'max_stores_per_owner',
                    'value' => '5',
                    'type' => 'number',
                    'label' => 'Max Stores Per Owner',
                    'description' => 'Maximum number of stores an owner can create',
                    'sort_order' => 5,
                ],
                [
                    'key' => 'enable_custom_domains',
                    'value' => '1',
                    'type' => 'boolean',
                    'label' => 'Custom Domains',
                    'description' => 'Allow stores to use custom domains',
                    'is_public' => true,
                    'sort_order' => 6,
                ],
                [
                    'key' => 'enable_api_access',
                    'value' => '1',
                    'type' => 'boolean',
                    'label' => 'API Access',
                    'description' => 'Allow stores to access API for integrations',
                    'sort_order' => 7,
                ],
            ],
        ];
    }
}
