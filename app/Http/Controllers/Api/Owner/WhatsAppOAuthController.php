<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\WhatsappAccount;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppOAuthController extends Controller
{
    /**
     * Get all WhatsApp accounts for current store
     */
    public function index(Request $request)
    {
        $store = $request->user()->currentStore ?? $request->user()->stores()->first();

        if (!$store) {
            return response()->json(['message' => 'No store found'], 404);
        }

        $accounts = WhatsappAccount::where('store_id', $store->id)
            ->orderBy('status', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['accounts' => $accounts]);
    }

    /**
     * Get Meta OAuth URL
     */
    public function getOAuthUrl(Request $request)
    {
        $store = $request->user()->currentStore ?? $request->user()->stores()->first();

        if (!$store) {
            return response()->json(['message' => 'No store found'], 404);
        }

        $appId = config('services.meta.app_id');
        $redirectUri = config('services.meta.redirect_uri');
        $state = base64_encode(json_encode([
            'store_id' => $store->id,
            'owner_id' => $request->user()->id,
            'timestamp' => now()->timestamp,
        ]));

        $url = "https://www.facebook.com/v18.0/dialog/oauth?" . http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => 'whatsapp_business_management,whatsapp_business_messaging',
            'response_type' => 'code',
        ]);

        return response()->json([
            'oauth_url' => $url,
            'state' => $state,
        ]);
    }

    /**
     * Handle OAuth callback from Meta
     */
    public function handleCallback(Request $request)
    {
        $code = $request->input('code');
        $state = $request->input('state');

        if (!$code || !$state) {
            return response()->json(['message' => 'Invalid callback parameters'], 400);
        }

        try {
            // Decode state to get store_id
            $stateData = json_decode(base64_decode($state), true);
            $storeId = $stateData['store_id'] ?? null;

            if (!$storeId) {
                return response()->json(['message' => 'Invalid state'], 400);
            }

            // Exchange code for access token
            $tokenResponse = Http::get('https://graph.facebook.com/v18.0/oauth/access_token', [
                'client_id' => config('services.meta.app_id'),
                'client_secret' => config('services.meta.app_secret'),
                'redirect_uri' => config('services.meta.redirect_uri'),
                'code' => $code,
            ]);

            if (!$tokenResponse->successful()) {
                Log::error('Meta OAuth token exchange failed', [
                    'response' => $tokenResponse->json(),
                ]);
                return response()->json(['message' => 'Failed to exchange code for token'], 500);
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'];

            // Get WhatsApp Business Account info
            $wabaResponse = Http::withToken($accessToken)->get(
                'https://graph.facebook.com/v18.0/me/businesses',
                ['fields' => 'id,name,owned_whatsapp_business_accounts']
            );

            if (!$wabaResponse->successful()) {
                Log::error('Failed to fetch WABA info', [
                    'response' => $wabaResponse->json(),
                ]);
                return response()->json(['message' => 'Failed to fetch WhatsApp Business Account'], 500);
            }

            $wabaData = $wabaResponse->json();
            $whatsappAccounts = $wabaData['data'][0]['owned_whatsapp_business_accounts']['data'] ?? [];

            if (empty($whatsappAccounts)) {
                return response()->json(['message' => 'No WhatsApp Business Account found'], 404);
            }

            // Get the first WABA
            $waba = $whatsappAccounts[0];
            $wabaId = $waba['id'];

            // Get phone numbers for this WABA
            $phoneNumbersResponse = Http::withToken($accessToken)->get(
                "https://graph.facebook.com/v18.0/{$wabaId}/phone_numbers",
                ['fields' => 'id,display_phone_number,verified_name,quality_rating,messaging_limit_tier']
            );

            if (!$phoneNumbersResponse->successful()) {
                Log::error('Failed to fetch phone numbers', [
                    'response' => $phoneNumbersResponse->json(),
                ]);
                return response()->json(['message' => 'Failed to fetch phone numbers'], 500);
            }

            $phoneNumbers = $phoneNumbersResponse->json()['data'] ?? [];

            if (empty($phoneNumbers)) {
                return response()->json(['message' => 'No phone numbers found for this account'], 404);
            }

            // Create WhatsApp account records
            $createdAccounts = [];
            foreach ($phoneNumbers as $phoneNumber) {
                $account = WhatsappAccount::updateOrCreate(
                    [
                        'phone_number_id' => $phoneNumber['id'],
                    ],
                    [
                        'store_id' => $storeId,
                        'phone_number' => $phoneNumber['display_phone_number'],
                        'waba_id' => $wabaId,
                        'access_token' => $accessToken,
                        'display_name' => $phoneNumber['verified_name'] ?? null,
                        'quality_rating' => $phoneNumber['quality_rating'] ?? null,
                        'messaging_limits' => [
                            'tier' => $phoneNumber['messaging_limit_tier'] ?? 'TIER_1000',
                        ],
                        'status' => 'pending',
                        'webhook_verification_token' => Str::random(32),
                    ]
                );

                // Send verification message
                $this->sendVerificationMessage($account);

                $createdAccounts[] = $account;
            }

            return response()->json([
                'message' => 'WhatsApp account(s) connected successfully',
                'accounts' => $createdAccounts,
            ]);
        } catch (\Exception $e) {
            Log::error('OAuth callback error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Failed to process callback'], 500);
        }
    }

    /**
     * Send verification message to WhatsApp number
     */
    protected function sendVerificationMessage(WhatsappAccount $account): void
    {
        try {
            $verificationCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            
            $response = Http::withToken($account->access_token)
                ->post("https://graph.facebook.com/v18.0/{$account->phone_number_id}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $account->phone_number,
                    'type' => 'text',
                    'text' => [
                        'body' => "Your WhatsApp Business account has been connected to OmniPortal. Verification code: {$verificationCode}",
                    ],
                ]);

            if ($response->successful()) {
                $account->update([
                    'verified_at' => now(),
                    'status' => 'active',
                    'meta' => array_merge($account->meta ?? [], [
                        'verification_code' => $verificationCode,
                        'verification_sent_at' => now()->toIso8601String(),
                    ]),
                ]);

                Log::info('Verification message sent', [
                    'account_id' => $account->id,
                    'phone_number' => $account->phone_number,
                ]);
            } else {
                Log::warning('Failed to send verification message', [
                    'account_id' => $account->id,
                    'response' => $response->json(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Verification message exception', [
                'message' => $e->getMessage(),
                'account_id' => $account->id,
            ]);
        }
    }

    /**
     * Disconnect/remove WhatsApp account
     */
    public function disconnect(Request $request, WhatsappAccount $account)
    {
        $store = $request->user()->currentStore ?? $request->user()->stores()->first();

        if (!$store || $account->store_id !== $store->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // If this is the default account, clear it from store
        if ($store->whatsapp_account_id === $account->id) {
            $store->update(['whatsapp_account_id' => null]);
        }

        $account->delete();

        return response()->json(['message' => 'WhatsApp account disconnected successfully']);
    }

    /**
     * Set account as default for store
     */
    public function setDefault(Request $request, WhatsappAccount $account)
    {
        $store = $request->user()->currentStore ?? $request->user()->stores()->first();

        if (!$store || $account->store_id !== $store->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$account->isActive()) {
            return response()->json(['message' => 'Cannot set inactive account as default'], 400);
        }

        $store->update(['whatsapp_account_id' => $account->id]);

        return response()->json([
            'message' => 'Default WhatsApp account updated',
            'store' => $store->fresh(),
        ]);
    }

    /**
     * Toggle account status (active/inactive)
     */
    public function toggleStatus(Request $request, WhatsappAccount $account)
    {
        $store = $request->user()->currentStore ?? $request->user()->stores()->first();

        if (!$store || $account->store_id !== $store->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $newStatus = $account->status === 'active' ? 'inactive' : 'active';
        $account->update(['status' => $newStatus]);

        // If deactivating the default account, clear it
        if ($newStatus === 'inactive' && $store->whatsapp_account_id === $account->id) {
            $store->update(['whatsapp_account_id' => null]);
        }

        return response()->json([
            'message' => 'Account status updated',
            'account' => $account->fresh(),
        ]);
    }

    /**
     * Refresh account info from Meta
     */
    public function refresh(Request $request, WhatsappAccount $account)
    {
        $store = $request->user()->currentStore ?? $request->user()->stores()->first();

        if (!$store || $account->store_id !== $store->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $response = Http::withToken($account->access_token)->get(
                "https://graph.facebook.com/v18.0/{$account->phone_number_id}",
                ['fields' => 'display_phone_number,verified_name,quality_rating,messaging_limit_tier']
            );

            if (!$response->successful()) {
                return response()->json(['message' => 'Failed to refresh account info'], 500);
            }

            $data = $response->json();

            $account->update([
                'display_name' => $data['verified_name'] ?? $account->display_name,
                'quality_rating' => $data['quality_rating'] ?? $account->quality_rating,
                'messaging_limits' => [
                    'tier' => $data['messaging_limit_tier'] ?? 'TIER_1000',
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);

            return response()->json([
                'message' => 'Account info refreshed',
                'account' => $account->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('Account refresh failed', [
                'message' => $e->getMessage(),
                'account_id' => $account->id,
            ]);

            return response()->json(['message' => 'Failed to refresh account'], 500);
        }
    }
}
