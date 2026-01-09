# Quick Setup Guide - WhatsApp AI Agent

## Prerequisites

- Laravel 11 backend running
- MySQL database
- Redis (for queues)
- WhatsApp Business Cloud API account
- OpenAI API account

## Step 1: Environment Configuration

Copy the following to your `.env` file:

```env
# Currency Configuration
CURRENCY_CODE=PKR
CURRENCY_SYMBOL=Rs

# Feature Flags
STORE_FRONTEND_ENABLED=false
AI_ENABLED=true
AI_VOICE_ENABLED=true
PAYMENT_SCREENSHOT_REQUIRED=true

# OpenAI Configuration
OPENAI_API_KEY=sk-proj-xxx  # Get from https://platform.openai.com
OPENAI_MODEL=gpt-4o-mini
OPENAI_STT_MODEL=whisper-1
OPENAI_TTS_MODEL=tts-1

# WhatsApp Business Cloud API
# Get from Meta Developer Dashboard: https://developers.facebook.com
WHATSAPP_TOKEN=your_whatsapp_access_token
WHATSAPP_PHONE_ID=your_phone_number_id
WHATSAPP_WEBHOOK_VERIFY_TOKEN=your_custom_verify_token
```

## Step 2: Run Migrations

### Main Database (stores table)
```bash
php artisan migrate
```

### Tenant Databases (for each store)
```bash
# For a specific store
php artisan tenant:migrate {store_slug}

# For all stores
php artisan tenant:migrate --all
```

## Step 3: WhatsApp Webhook Setup

1. Go to Meta Developer Dashboard
2. Select your WhatsApp Business App
3. Navigate to **Webhooks**
4. Set Callback URL: `https://your-domain.com/api/webhooks/whatsapp/{store_slug}`
5. Set Verify Token: (use the value from `WHATSAPP_WEBHOOK_VERIFY_TOKEN`)
6. Subscribe to events:
   - `messages`
   - `message_status`
7. Click **Verify and Save**

## Step 4: Create Storage Directories

```bash
# Create private storage directory
mkdir -p storage/app/private
mkdir -p storage/app/private/whatsapp/media
mkdir -p storage/app/private/whatsapp/audio

# Set permissions
chmod -R 775 storage
chown -R www-data:www-data storage  # Linux
```

## Step 5: Queue Configuration

The system uses queues for AI processing and WhatsApp messaging.

```bash
# Make sure Redis is running
redis-cli ping  # Should return PONG

# Start queue worker
php artisan queue:work redis --tries=3
```

## Step 6: Create Payment Methods (Per Store)

Via Owner Dashboard or API:

```bash
# Example: Create Cash on Delivery
curl -X POST https://your-domain.com/api/owner/{store_slug}/payment-methods \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Cash on Delivery",
    "type": "offline",
    "is_active": true,
    "sort_order": 1
  }'

# Example: Create EasyPaisa (Online)
curl -X POST https://your-domain.com/api/owner/{store_slug}/payment-methods \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "EasyPaisa",
    "type": "online",
    "instructions": "EasyPaisa Account: 03001234567\nName: Store Name\nBank: HBL",
    "is_active": true,
    "sort_order": 2
  }'
```

## Step 7: Create Delivery Agents (Restaurant Only)

Via Owner Dashboard or API:

```bash
curl -X POST https://your-domain.com/api/owner/{store_slug}/delivery-agents \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Ali Khan",
    "phone": "03001234567",
    "email": "ali@example.com",
    "is_active": true,
    "max_orders": 3
  }'
```

## Step 8: Test WhatsApp Integration

1. **Test Text Message**
   - Send: "Hello"
   - Expected: AI greeting in Roman Urdu

2. **Test Voice Message**
   - Send voice message
   - Expected: Transcription and AI response

3. **Test Order Flow**
   - Say: "Mujhe burger chahiye"
   - AI will guide through order process

4. **Test Payment Screenshot**
   - Select online payment method
   - Send screenshot image
   - Check Owner Dashboard for verification

## Step 9: Access Owner Dashboard

Navigate to the frontend pages:

- Payment Methods: `/payment-methods`
- Payment Verification: `/payment-verification`
- Delivery Agents: `/delivery-agents` (Restaurant only)

## Step 10: Monitor & Debug

### Check Logs
```bash
tail -f storage/logs/laravel.log
```

### Check WhatsApp Messages
```bash
# Connect to your database
mysql -u root -p omniportal

# Switch to tenant database
USE tenant_your_store;

# View recent WhatsApp messages
SELECT * FROM whatsapp_messages ORDER BY created_at DESC LIMIT 10;
```

### Check Order Status
```bash
# View orders with payment verification pending
SELECT order_number, customer_name, payment_status, payment_proof_path
FROM orders
WHERE payment_status = 'pending_verification';
```

## Common Issues

### Issue 1: Webhook Verification Failed
**Solution**: Ensure `WHATSAPP_WEBHOOK_VERIFY_TOKEN` in `.env` matches the token set in Meta Dashboard

### Issue 2: Voice Messages Not Working
**Solution**: 
- Check `AI_VOICE_ENABLED=true`
- Verify OpenAI API key has access to Whisper
- Check storage permissions

### Issue 3: Payment Screenshots Not Saving
**Solution**:
- Ensure `storage/app/private` exists and is writable
- Check disk configuration in `config/filesystems.php`

### Issue 4: AI Not Responding
**Solution**:
- Check `OPENAI_API_KEY` is valid
- Verify API quota/credits
- Check conversation context in database

### Issue 5: Delivery Agent Not Auto-Assigned
**Solution**:
- Ensure store `business_type` is set to 'restaurant'
- Check at least one agent is active and available
- Verify agent's `current_orders < max_orders`

## Testing Checklist

- [ ] WhatsApp webhook verified
- [ ] Text messages working
- [ ] Voice messages transcribed
- [ ] AI responds in Roman Urdu
- [ ] Payment methods created
- [ ] Online payment screenshot flow works
- [ ] Owner can approve/reject payments
- [ ] Delivery agents (if restaurant) created
- [ ] Orders auto-assigned to agents
- [ ] WhatsApp notifications sent

## Next Steps

1. Add products to your store
2. Configure business hours
3. Set up AI test cases
4. Train staff on payment verification
5. Monitor order flow
6. Collect customer feedback

## Production Deployment

### Security Checklist
- [ ] HTTPS enabled on webhook URL
- [ ] `.env` file secured (not in git)
- [ ] API keys rotated regularly
- [ ] Rate limiting configured
- [ ] Backup strategy in place
- [ ] Monitoring alerts set up

### Performance Optimization
- [ ] Queue workers running (supervisord recommended)
- [ ] Redis cache configured
- [ ] Database indexes created
- [ ] Image optimization enabled
- [ ] CDN for media files (optional)

## Support

For help:
- Review `WHATSAPP_AI_IMPLEMENTATION.md`
- Check application logs
- Run AI test cases
- Contact development team

---

**Last Updated**: January 9, 2026
