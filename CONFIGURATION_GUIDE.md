# OmniPortal Configuration Guide

## 🔧 ENVIRONMENT SETUP

### 1. Database Configuration
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=omniportal
DB_USERNAME=root
DB_PASSWORD=
```

### 2. Currency & Localization
```env
CURRENCY_CODE=PKR
CURRENCY_SYMBOL=Rs
APP_TIMEZONE=Asia/Karachi
```

### 3. OpenAI Configuration
```env
OPENAI_API_KEY=your-api-key-here
OPENAI_MODEL=gpt-4o-mini
OPENAI_STT_MODEL=whisper-1
OPENAI_TTS_MODEL=tts-1
```

### 4. WhatsApp Business Configuration
```env
WHATSAPP_TOKEN=your-bearer-token
WHATSAPP_PHONE_ID=your-phone-id
WHATSAPP_WEBHOOK_VERIFY_TOKEN=your-webhook-token
```

Get these from:
- [Meta Business Platform](https://developers.facebook.com/docs/whatsapp/cloud-api)
- WhatsApp Business API Dashboard

### 5. Feature Flags
```env
STORE_FRONTEND_ENABLED=false
AI_ENABLED=true
AI_VOICE_ENABLED=true
PAYMENT_SCREENSHOT_REQUIRED=true
```

### 6. Stripe Configuration (for subscriptions)
```env
STRIPE_KEY=pk_live_xxxxx
STRIPE_SECRET=sk_live_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
CASHIER_CURRENCY=usd
```

### 7. Redis Configuration (for queues)
```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
```

### 8. Mail Configuration (for notifications)
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@omniportal.com
```

---

## 🚀 SETUP INSTRUCTIONS

### Step 1: Clone & Install
```bash
cd OmniPortal-Backend
composer install
cp .env.example .env
php artisan key:generate
```

### Step 2: Configure Database
```bash
# Create databases
mysql -u root -e "CREATE DATABASE omniportal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE DATABASE main CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations
php artisan migrate
```

### Step 3: Seed Default Data
```bash
# Seed payment methods (required)
php artisan db:seed --class=PaymentMethodSeeder

# Optional: Seed demo data
php artisan db:seed
```

### Step 4: Start Services
```bash
# Terminal 1: Backend
php artisan serve --port=8000

# Terminal 2: Queue Worker (for jobs)
php artisan queue:work --queue=default,ai,whatsapp

# Terminal 3: Admin Dashboard
cd ../OmniPortal-Admin
npm install
npm run dev

# Terminal 4: Owner Dashboard
cd ../OmniPortal-Owner
npm install
npm run dev
```

---

## 📋 ADMIN DASHBOARD FEATURES

### Create Store
1. Go to **Stores** → **Create New Store**
2. Fill in:
   - **Owner**: Select from dropdown
   - **Store Name**: Auto-generates slug
   - **Business Type**: Select from dropdown (affects AI behavior)
   - **Description**: Optional
   - **WhatsApp Business Configuration**:
     - Phone Number (required) - Format: +92301234567
     - Business ID (optional)
   - **Contact Info**: Email, phone, address
   - **Location**: City, country, postal code
   - **Timezone**: Auto-set to Asia/Karachi
   - **Currency**: Auto-set to PKR

3. Click **Create Store**
4. Default payment methods automatically added (Cash on Delivery, EasyPaisa, JazzCash)

### View Store
- Click on store to view details
- See all configured payment methods
- For restaurants: View & manage delivery agents

---

## 🏪 OWNER DASHBOARD FEATURES

### Configure Payment Methods
1. Go to **Settings** → **Payment Methods**
2. **Enable/Disable**: Toggle each payment method
3. **Reorder**: Drag methods to change display order
4. **Save**: Changes auto-save

**Payment Types**:
- **Offline**: Cash on Delivery (no screenshot needed)
- **Online**: EasyPaisa, JazzCash, Bank Transfer (screenshot required)

### Manage Delivery Agents (Restaurants Only)
1. Go to **Settings** → **Delivery Agents**
2. **Add Agent**: Click "Add Agent"
   - Name, phone, email, address
3. **Edit**: Click edit icon (coming soon)
4. **Delete**: Click trash icon
5. **Status**: View active/inactive status

---

## 🤖 AI AGENT CONFIGURATION

### Business Type Behavior

#### 🍽️ Restaurant
- Asks for: Quantity, address, delivery agent assignment
- Auto-assigns available delivery agent
- Supports food-specific questions
- Requires delivery address

#### 👕 Clothing
- Asks for: Size, color, variant
- No delivery agent assignment
- Supports style/fit questions
- Price validation for items

#### 💻 Electronics
- Asks for: Model, specifications, warranty preference
- Technical support questions
- Model availability checking
- Extended warranty options

#### 🛒 Grocery
- Asks for: Quantity, delivery address
- Bulk order support
- Subscription options
- Delivery slot selection

#### 🔧 Services
- Asks for: Service type, scheduling
- No online payments (offline only)
- Appointment scheduling
- Service area verification

### Voice Message Support

When `AI_VOICE_ENABLED=true`:
1. Customer sends voice message via WhatsApp
2. System downloads audio from WhatsApp
3. Whisper STT transcribes to text
4. AI agent processes as normal text
5. Optional: Reply with TTS (voice message)

---

## 💳 PAYMENT SCREENSHOT FLOW

### For Online Payments
```
Customer: "I want to order"
   ↓
AI: "Great! Items total Rs. 2,500"
   ↓
AI: "How will you pay?"
   ↓
Customer: "EasyPaisa"
   ↓
AI: "Please send payment screenshot"
   ↓
Conversation State: awaiting_payment_screenshot
   ↓
Customer: [sends image]
   ↓
System: Downloads & stores image
   ↓
Order Created: payment_status = pending_verification
   ↓
Owner: Reviews screenshot in orders
   ↓
Owner: Mark as Paid / Reject
   ↓
Customer: Notified via WhatsApp
```

### For Offline Payments
```
Customer: "I want to order"
   ↓
AI: "How will you pay?"
   ↓
Customer: "Cash on Delivery"
   ↓
Order Created: payment_status = paid
   ↓
No screenshot needed
```

---

## 🔌 WHATSAPP WEBHOOK SETUP

### 1. Get Credentials
- Go to [Meta for Developers](https://developers.facebook.com/)
- Create App → WhatsApp
- Get: Phone ID, Access Token

### 2. Configure .env
```env
WHATSAPP_TOKEN=EAAbL...
WHATSAPP_PHONE_ID=123456789
WHATSAPP_WEBHOOK_VERIFY_TOKEN=my_random_token_123
```

### 3. Setup Webhook
- **URL**: `https://your-domain.com/api/webhooks/whatsapp/{store-id}`
- **Verify Token**: Same as `WHATSAPP_WEBHOOK_VERIFY_TOKEN`
- **Subscribe to**: messages, message_status

### 4. Test
```bash
curl -X POST "https://your-api.com/api/webhooks/whatsapp/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "object": "whatsapp_business_account",
    "entry": [{"changes": [{"field": "messages"}]}]
  }'
```

---

## 📊 DATABASE SCHEMA

### Stores Table
```sql
id, owner_id, name, slug, business_type, 
email, phone, 
whatsapp_business_number, whatsapp_business_id,
address, city, country,
timezone, currency,
is_active, status, created_at, updated_at
```

### Store Payment Methods
```sql
store_id (FK), payment_method_id (FK),
is_enabled, display_order
```

### Delivery Agents
```sql
id, store_id (FK), name, phone, email, address,
is_active, created_at, updated_at, deleted_at
```

### WhatsApp Conversations
```sql
id, store_id (FK), order_id (FK),
customer_phone, customer_name,
message_type, message_content,
direction (inbound|outbound),
ai_analysis (JSON), created_at
```

### AI Test Cases
```sql
id, store_id (FK), business_type,
user_message, expected_intent, expected_fields,
test_result, status (pending|pass|fail),
notes, created_at, updated_at
```

---

## 🧪 TESTING

### Test Store Creation
```bash
curl -X POST http://localhost:8000/api/admin/stores \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "owner_id": 1,
    "name": "Test Restaurant",
    "business_type": "restaurant",
    "email": "test@example.com",
    "phone": "+923001234567",
    "whatsapp_business_number": "+923001234567",
    "whatsapp_business_id": "WABA123",
    "address": "123 Main St",
    "city": "Karachi",
    "country": "PK",
    "currency": "PKR",
    "timezone": "Asia/Karachi"
  }'
```

### Test Payment Methods
```bash
curl -X GET http://localhost:8000/api/admin/stores/1/payment-methods \
  -H "Authorization: Bearer TOKEN"
```

### Test Delivery Agents
```bash
curl -X GET http://localhost:8000/api/admin/stores/1/delivery-agents \
  -H "Authorization: Bearer TOKEN"
```

---

## 🔒 SECURITY CHECKLIST

- [ ] API keys in .env (not in code)
- [ ] CORS properly configured
- [ ] Sanctum authentication enabled
- [ ] Authorization checks in place
- [ ] Rate limiting configured
- [ ] Input validation on all endpoints
- [ ] SQL injection protection (Laravel ORM)
- [ ] XSS protection (React escaping)
- [ ] CSRF tokens for forms
- [ ] HTTPS in production
- [ ] Payment proofs stored in private disk
- [ ] Soft deletes for audit trail

---

## 📚 USEFUL COMMANDS

```bash
# Database
php artisan migrate
php artisan migrate:rollback
php artisan db:seed --class=PaymentMethodSeeder

# Cache
php artisan cache:clear
php artisan config:clear

# Routes
php artisan route:list | grep store
php artisan route:list | grep payment

# Queue
php artisan queue:work
php artisan queue:failed

# Optimization
php artisan optimize
php artisan config:cache
php artisan route:cache
```

---

## ❓ TROUBLESHOOTING

### Migration Fails
- Check MySQL is running
- Verify DB credentials in .env
- Run: `php artisan migrate --step`

### WhatsApp Webhook Not Working
- Verify token in .env
- Check webhook URL is public & accessible
- Check Meta app has correct permissions
- Check logs: `storage/logs/laravel.log`

### Payment Methods Not Showing
- Run seeder: `php artisan db:seed --class=PaymentMethodSeeder`
- Check database: `select * from payment_methods;`

### Queue Not Processing
- Check Redis is running
- Check queue worker: `php artisan queue:work`
- Check logs for errors

---

## 📞 SUPPORT

For issues or questions:
1. Check logs: `storage/logs/laravel.log`
2. Run migrations: `php artisan migrate`
3. Clear cache: `php artisan cache:clear`
4. Check environment variables in .env
5. Verify database connectivity

**All features are backwards compatible - no breaking changes!**
