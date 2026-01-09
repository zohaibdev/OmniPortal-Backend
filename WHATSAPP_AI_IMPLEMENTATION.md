# OmniPortal - WhatsApp AI Agent Implementation

## Overview

This document outlines the implementation of the WhatsApp-based AI ordering system with payment screenshot verification, delivery agent management, and AI testing capabilities.

## Features Implemented

### 1. **Voice Message Support** ✅
- WhatsApp voice messages are transcribed using OpenAI Whisper
- Transcriptions are processed by AI agent like text messages
- Voice replies are optional (text-only by default)

### 2. **Currency Configuration** ✅
- System currency set to PKR (Pakistani Rupee)
- Currency symbol: Rs
- All prices displayed in PKR
- Future-ready for multi-currency support

### 3. **Business Type Aware AI** ✅
- Admin selects business type when creating store
- AI adapts behavior based on business type:
  - Restaurant: quantity, address, delivery agent
  - Clothing: size, variant
  - Electronics: model, warranty
  - Grocery: quantity, delivery time
  - Services: appointment, requirements

### 4. **Owner-Defined Payment Methods** ✅
- Owners define available payment methods
- Types:
  - **Offline**: Cash on Delivery (no screenshot required)
  - **Online**: EasyPaisa, JazzCash, Bank Transfer (screenshot required)
- Each method can have custom instructions

### 5. **Online Payment Screenshot Flow** ✅
- Customer selects online payment method
- AI requests payment screenshot
- Conversation state: `awaiting_payment_screenshot`
- Order created only after screenshot is received
- Payment status: `pending_verification`
- Owner can approve/reject from dashboard

### 6. **Restaurant Delivery Agents** ✅
- Only for `business_type = restaurant`
- Owners manage delivery agents
- Auto-assignment based on availability
- Track current orders and capacity
- Agent statistics and performance tracking

### 7. **Owner Payment Confirmation** ✅
- Owner Dashboard shows orders pending verification
- View payment screenshot
- Actions:
  - **Approve**: Mark payment as paid, notify customer
  - **Reject**: Cancel order, notify customer with reason

### 8. **AI Test Cases** ✅
- Automated AI testing system
- Define test cases per business type
- Expected intent and fields validation
- Run tests manually or automatically
- Pass/fail reporting

## Database Migrations

### Main Database
- `2026_01_09_000001_add_business_type_to_stores.php` - Adds business_type to stores

### Tenant Database
- `2026_01_09_000001_create_payment_methods_tables.php` - Payment methods
- `2026_01_09_000002_add_payment_fields_to_orders.php` - Order payment fields
- `2026_01_09_000003_create_delivery_agents_table.php` - Delivery agents
- `2026_01_09_000004_create_ai_test_cases_table.php` - AI test cases
- `2026_01_09_000005_create_whatsapp_messages_table.php` - WhatsApp message log

## Models Created

### Tenant Models
- `PaymentMethod` - Owner-defined payment methods
- `DeliveryAgent` - Restaurant delivery agents
- `AiTestCase` - AI test case definitions
- `AiTestResult` - AI test execution results
- `WhatsappMessage` - WhatsApp message log

### Updated Models
- `Store` - Added `business_type`
- `Order` - Added payment and conversation fields

## Services

### WhatsAppService
- Send text messages
- Send button messages
- Download media (voice, images)
- Transcribe audio
- Mark messages as read
- Log all messages

### OpenAIService
- Chat completions with function calling
- Speech-to-text (Whisper)
- Text-to-speech (TTS)
- Structured data extraction

### AIAgentService
- Process customer messages
- Business-type aware responses
- Roman Urdu only
- Payment screenshot workflow
- Order creation with function calling

### PaymentVerificationService
- Process payment screenshots
- Approve/reject payments
- WhatsApp notifications

### AITestRunnerService
- Run test cases
- Validate AI responses
- Generate reports

## Controllers

### Owner Dashboard
- `PaymentMethodController` - Manage payment methods
- `DeliveryAgentController` - Manage delivery agents
- `OrderPaymentController` - Payment verification
- `AITestController` - AI testing
- `WhatsAppWebhookController` - WhatsApp webhook handler

## API Endpoints

### Payment Methods
```
GET    /api/owner/{store}/payment-methods
POST   /api/owner/{store}/payment-methods
PUT    /api/owner/{store}/payment-methods/{id}
DELETE /api/owner/{store}/payment-methods/{id}
GET    /api/owner/{store}/payment-methods-active
```

### Delivery Agents
```
GET    /api/owner/{store}/delivery-agents
POST   /api/owner/{store}/delivery-agents
PUT    /api/owner/{store}/delivery-agents/{id}
DELETE /api/owner/{store}/delivery-agents/{id}
GET    /api/owner/{store}/delivery-agents-active
GET    /api/owner/{store}/delivery-agents-available
GET    /api/owner/{store}/delivery-agents/{id}/stats
```

### Payment Verification
```
GET    /api/owner/{store}/orders/pending-payment-verification
GET    /api/owner/{store}/orders/{id}/payment-screenshot
POST   /api/owner/{store}/orders/{id}/approve-payment
POST   /api/owner/{store}/orders/{id}/reject-payment
```

### AI Testing
```
GET    /api/owner/{store}/ai-tests
POST   /api/owner/{store}/ai-tests
PUT    /api/owner/{store}/ai-tests/{id}
DELETE /api/owner/{store}/ai-tests/{id}
GET    /api/owner/{store}/ai-tests-summary
POST   /api/owner/{store}/ai-tests/{id}/run
POST   /api/owner/{store}/ai-tests-run-all
```

### WhatsApp Webhook
```
GET    /api/webhooks/whatsapp/{store}  (verification)
POST   /api/webhooks/whatsapp/{store}  (messages)
```

## Configuration

### Environment Variables (.env)
```env
# Currency
CURRENCY_CODE=PKR
CURRENCY_SYMBOL=Rs

# Feature Flags
STORE_FRONTEND_ENABLED=false
AI_ENABLED=true
AI_VOICE_ENABLED=true
PAYMENT_SCREENSHOT_REQUIRED=true

# OpenAI
OPENAI_API_KEY=your-key
OPENAI_MODEL=gpt-4o-mini
OPENAI_STT_MODEL=whisper-1
OPENAI_TTS_MODEL=tts-1

# WhatsApp
WHATSAPP_TOKEN=your-whatsapp-token
WHATSAPP_PHONE_ID=your-phone-id
WHATSAPP_WEBHOOK_VERIFY_TOKEN=your-verify-token
```

## Frontend Components (Owner Dashboard)

### PaymentMethods.tsx
- List all payment methods
- Add/edit/delete payment methods
- Toggle active status
- Set payment instructions

### PaymentVerification.tsx
- View orders pending payment verification
- View payment screenshots
- Approve/reject payments
- Send WhatsApp notifications

### DeliveryAgents.tsx
- Manage delivery agents
- View current orders and availability
- Set max order capacity
- Agent performance stats

## Workflow

### Order Creation Flow

1. **Customer sends message via WhatsApp**
   - Text or voice message
   - Voice is transcribed to text

2. **AI Agent processes message**
   - Understands intent
   - Asks relevant questions based on business type
   - Collects order details

3. **Payment method selection**
   - Shows available payment methods
   - Customer selects method

4. **Online Payment (EasyPaisa, JazzCash, etc.)**
   - AI provides payment instructions
   - Asks for payment screenshot
   - State changes to `awaiting_payment_screenshot`
   - Customer sends screenshot image
   - Order is created with `payment_status = pending_verification`

5. **Offline Payment (COD)**
   - Order created immediately
   - `payment_status = pending`

6. **Restaurant: Auto-assign delivery agent**
   - Finds available agent
   - Updates agent's current_orders

7. **Owner verifies payment (online only)**
   - Views screenshot
   - Approves or rejects
   - Customer gets WhatsApp notification

### AI Agent Rules

- **Language**: Roman Urdu only
- **Tone**: Short, polite, professional
- **Accuracy**: Never guess prices or availability
- **Context**: Maintains conversation state
- **Business-aware**: Adapts questions to business type

## Testing

### Manual Testing
1. Send test messages via WhatsApp
2. Test voice messages
3. Test payment screenshot flow
4. Test delivery agent assignment

### Automated Testing
1. Create AI test cases
2. Define expected intents and fields
3. Run tests from Owner Dashboard
4. Review pass/fail results

## Security

- Payment screenshots stored in private storage
- WhatsApp webhook verification
- Sanctum authentication for APIs
- Tenant isolation (separate databases)
- No hardcoded credentials

## Future Enhancements

1. Multi-currency support
2. AI voice replies (TTS)
3. Image-based product search
4. Delivery tracking
5. Customer ratings
6. Advanced analytics
7. WhatsApp catalog integration
8. Payment gateway integration (not just screenshots)

## Migration Commands

```bash
# Run migrations
php artisan tenant:migrate {store_slug}

# Run for all stores
php artisan tenant:migrate --all

# Fresh migration
php artisan tenant:migrate {store_slug} --fresh
```

## Troubleshooting

### WhatsApp webhook not receiving messages
- Verify webhook URL is correct
- Check WHATSAPP_WEBHOOK_VERIFY_TOKEN matches
- Ensure webhook is verified in Meta dashboard

### Voice transcription fails
- Check AI_VOICE_ENABLED=true
- Verify OPENAI_API_KEY is set
- Ensure audio file is downloaded correctly

### Payment screenshot not showing
- Check private disk configuration
- Verify file permissions
- Ensure media download is working

### AI not responding correctly
- Review conversation context
- Check OpenAI API quota
- Run AI test cases to validate

## Support

For issues or questions:
1. Check logs: `storage/logs/laravel.log`
2. Review WhatsApp message logs in database
3. Test AI with test cases
4. Contact support team

---

**Implementation Date**: January 9, 2026
**Version**: 1.0.0
**Status**: Production Ready ✅
