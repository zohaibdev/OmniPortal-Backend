# WhatsApp OAuth - Quick Reference

## Environment Setup

```env
# Add to .env
META_APP_ID=your_app_id
META_APP_SECRET=your_app_secret
META_REDIRECT_URI="${APP_URL}/api/whatsapp/callback"
```

## API Endpoints

### Owner Dashboard
```
GET  /api/owner/whatsapp/accounts           # List accounts
GET  /api/owner/whatsapp/oauth-url          # Get OAuth URL
POST /api/owner/whatsapp/accounts/{id}/disconnect
POST /api/owner/whatsapp/accounts/{id}/set-default
POST /api/owner/whatsapp/accounts/{id}/toggle-status
POST /api/owner/whatsapp/accounts/{id}/refresh
```

### Webhooks
```
GET  /api/webhooks/whatsapp                 # Verification
POST /api/webhooks/whatsapp                 # Incoming messages
GET  /api/whatsapp/callback                 # OAuth callback
```

## Usage

### Connect Account (Frontend)
```tsx
import WhatsAppAccounts from './pages/WhatsAppAccounts';

<Route path="/whatsapp" element={<WhatsAppAccounts />} />
```

### Send Message (Backend)
```php
$account = $store->getActiveWhatsappAccount();
$service = new WhatsAppService($account);
$service->sendTextMessage($to, $message);
```

### Handle Webhook
```php
// Automatic routing by phone_number_id
$phoneNumberId = $request->input('entry.0.changes.0.value.metadata.phone_number_id');
$account = WhatsappAccount::where('phone_number_id', $phoneNumberId)->first();
$store = $account->store; // Auto-switch tenant DB
```

## Meta App Configuration

1. **App Type:** Business
2. **Product:** WhatsApp
3. **OAuth Redirect:** `https://domain.com/api/whatsapp/callback`
4. **Scopes:** `whatsapp_business_management`, `whatsapp_business_messaging`
5. **Webhook URL:** `https://domain.com/api/webhooks/whatsapp`
6. **Webhook Fields:** `messages`, `message_status`

## Database

```php
// Get active account
$account = $store->getActiveWhatsappAccount();

// All accounts
$accounts = $store->whatsappAccounts;

// Active only
$accounts = $store->activeWhatsappAccounts;

// Default account
$default = $store->defaultWhatsappAccount;
```

## Testing

```bash
# Run migration
php artisan migrate

# Start servers
php artisan serve --port=8000
cd OmniPortal-Owner && npm run dev

# Test flow:
# 1. Login as owner
# 2. Go to /whatsapp
# 3. Click "Connect WhatsApp Account"
# 4. Complete OAuth
# 5. Send test message to WhatsApp number
# 6. Verify webhook received
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Account not found | Check `phone_number_id` matches |
| Token expired | Refresh or reconnect account |
| Webhook not receiving | Verify public URL & SSL |
| Quality rating RED | Review message templates |

## Key Models

```php
WhatsappAccount:
  - store_id
  - phone_number
  - phone_number_id (unique)
  - access_token (encrypted)
  - status (pending|active|inactive|failed)
  - quality_rating
  - verified_at

Store:
  - whatsapp_account_id (default account)
  - ai_enabled
  - storefront_enabled
```

## Security

- ✅ Encrypted access tokens
- ✅ Encrypted webhook tokens
- ✅ OAuth state validation
- ✅ HTTPS required
- ✅ Soft deletes

---

**Quick Start:** Set `META_APP_ID` & `META_APP_SECRET` → Run migration → Connect account → Test webhook
