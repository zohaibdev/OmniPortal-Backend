# OmniPortal API Reference

## 🔐 Authentication
All endpoints require `Authorization: Bearer {token}` header (except public routes)

---

## 🏪 STORE ENDPOINTS

### Create Store
```
POST /api/admin/stores
Content-Type: application/json

{
  "owner_id": 1,
  "name": "My Restaurant",
  "business_type": "restaurant|clothing|electronics|grocery|services|other",
  "description": "A great restaurant",
  "email": "store@example.com",
  "phone": "+92301234567",
  "whatsapp_business_number": "+92301234567",
  "whatsapp_business_id": "WABA123456789",
  "address": "123 Main St",
  "city": "Karachi",
  "state": "Sindh",
  "country": "PK",
  "postal_code": "75000",
  "timezone": "Asia/Karachi",
  "currency": "PKR"
}

Response: 201
{
  "message": "Store created successfully",
  "store": {
    "id": 1,
    "owner_id": 1,
    "name": "My Restaurant",
    "slug": "my-restaurant-abc123",
    "business_type": "restaurant",
    "whatsapp_business_number": "+92301234567",
    "whatsapp_business_id": "WABA123456789",
    "status": "active",
    "is_active": true,
    "currency": "PKR",
    "timezone": "Asia/Karachi",
    "created_at": "2026-01-09T20:30:00Z"
  }
}
```

### List Stores
```
GET /api/admin/stores?search=&status=&is_active=&page=1

Query Parameters:
- search: Search by name, slug, subdomain, custom domain
- status: pending|active|suspended|closed
- is_active: true|false
- page: Page number (default 1)

Response: 200
{
  "data": [
    {
      "id": 1,
      "owner_id": 1,
      "name": "My Restaurant",
      "slug": "my-restaurant-abc123",
      "business_type": "restaurant",
      "email": "store@example.com",
      "phone": "+92301234567",
      "whatsapp_business_number": "+92301234567",
      "currency": "PKR",
      "status": "active",
      "is_active": true,
      "created_at": "2026-01-09T20:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 95
  }
}
```

### Get Store Details
```
GET /api/admin/stores/{id}

Response: 200
{
  "data": {
    "id": 1,
    "owner_id": 1,
    "owner": {
      "id": 1,
      "name": "John Doe",
      "email": "owner@example.com"
    },
    "name": "My Restaurant",
    "slug": "my-restaurant-abc123",
    "business_type": "restaurant",
    "whatsapp_business_number": "+92301234567",
    "whatsapp_business_id": "WABA123456789",
    "address": "123 Main St",
    "city": "Karachi",
    "country": "PK",
    "currency": "PKR",
    "timezone": "Asia/Karachi",
    "status": "active",
    "is_active": true,
    "payment_methods": [
      {
        "id": 1,
        "name": "Cash on Delivery",
        "type": "offline",
        "is_enabled": true,
        "display_order": 0
      }
    ],
    "delivery_agents": [
      {
        "id": 1,
        "name": "Ahmed Khan",
        "phone": "+92301234567",
        "is_active": true
      }
    ],
    "created_at": "2026-01-09T20:30:00Z"
  }
}
```

### Update Store
```
PUT /api/admin/stores/{id}

{
  "name": "Updated Restaurant Name",
  "description": "Updated description",
  "email": "newemail@example.com",
  "phone": "+92302345678",
  "whatsapp_business_number": "+92302345678",
  "address": "456 New St",
  "city": "Lahore",
  "currency": "PKR",
  "timezone": "Asia/Karachi"
}

Response: 200
{
  "message": "Store updated successfully",
  "store": { ... }
}
```

### Activate Store
```
POST /api/admin/stores/{id}/activate

Response: 200
{
  "message": "Store activated",
  "store": { ... }
}
```

### Suspend Store
```
POST /api/admin/stores/{id}/suspend

{
  "reason": "Optional suspension reason"
}

Response: 200
{
  "message": "Store suspended",
  "store": { ... }
}
```

### Delete Store
```
DELETE /api/admin/stores/{id}

Response: 200
{
  "message": "Store deleted successfully"
}
```

---

## 💳 PAYMENT METHODS ENDPOINTS

### Get All Payment Methods
```
GET /api/admin/payment-methods

Response: 200
{
  "data": [
    {
      "id": 1,
      "name": "Cash on Delivery",
      "type": "offline",
      "description": "Pay when your order arrives",
      "is_active": true
    },
    {
      "id": 2,
      "name": "EasyPaisa",
      "type": "online",
      "description": "Mobile payment via EasyPaisa",
      "is_active": true
    }
  ]
}
```

### Get Store Payment Methods
```
GET /api/admin/stores/{store_id}/payment-methods

Response: 200
{
  "data": [
    {
      "id": 1,
      "name": "Cash on Delivery",
      "type": "offline",
      "is_enabled": true,
      "display_order": 0
    },
    {
      "id": 2,
      "name": "EasyPaisa",
      "type": "online",
      "is_enabled": true,
      "display_order": 1
    }
  ]
}
```

### Update Store Payment Methods
```
PUT /api/admin/stores/{store_id}/payment-methods

{
  "methods": [
    {
      "id": 1,
      "is_enabled": true,
      "display_order": 0
    },
    {
      "id": 2,
      "is_enabled": true,
      "display_order": 1
    },
    {
      "id": 3,
      "is_enabled": false,
      "display_order": 2
    }
  ]
}

Response: 200
{
  "message": "Payment methods updated successfully",
  "data": [ ... ]
}
```

---

## 🚚 DELIVERY AGENTS ENDPOINTS

### Get Delivery Agents
```
GET /api/admin/stores/{store_id}/delivery-agents

Only available for business_type = "restaurant"
Returns 422 error for other types

Response: 200
{
  "data": [
    {
      "id": 1,
      "store_id": 1,
      "name": "Ahmed Khan",
      "phone": "+92301234567",
      "email": "ahmed@example.com",
      "address": "123 Agent St",
      "is_active": true,
      "created_at": "2026-01-09T20:30:00Z"
    }
  ]
}
```

### Create Delivery Agent
```
POST /api/admin/stores/{store_id}/delivery-agents

{
  "name": "Ahmed Khan",
  "phone": "+92301234567",
  "email": "ahmed@example.com",
  "address": "123 Agent St"
}

Response: 201
{
  "message": "Delivery agent created",
  "data": {
    "id": 1,
    "store_id": 1,
    "name": "Ahmed Khan",
    "phone": "+92301234567",
    "email": "ahmed@example.com",
    "address": "123 Agent St",
    "is_active": true,
    "created_at": "2026-01-09T20:30:00Z"
  }
}
```

### Update Delivery Agent
```
PUT /api/admin/stores/{store_id}/delivery-agents/{agent_id}

{
  "name": "Ahmed Khan Updated",
  "phone": "+92302345678",
  "email": "newemail@example.com",
  "address": "456 New St",
  "is_active": true
}

Response: 200
{
  "message": "Delivery agent updated",
  "data": { ... }
}
```

### Delete Delivery Agent
```
DELETE /api/admin/stores/{store_id}/delivery-agents/{agent_id}

Response: 200
{
  "message": "Delivery agent deleted"
}
```

---

## 🔍 ERROR RESPONSES

### Validation Error
```
Response: 422
{
  "message": "The given data was invalid.",
  "errors": {
    "whatsapp_business_number": [
      "The whatsapp business number field is required."
    ],
    "business_type": [
      "The selected business type is invalid."
    ]
  }
}
```

### Unauthorized
```
Response: 401
{
  "message": "Unauthenticated."
}
```

### Forbidden
```
Response: 403
{
  "message": "This action is unauthorized."
}
```

### Not Found
```
Response: 404
{
  "message": "Resource not found"
}
```

### Business Logic Error
```
Response: 422
{
  "message": "Delivery agents are only available for restaurants"
}
```

---

## 📝 DATA TYPES

### Business Types
- `restaurant` - Restaurant/Food delivery
- `clothing` - Clothing/Fashion retail
- `electronics` - Electronics/Tech products
- `grocery` - Grocery/Food retail
- `services` - Services (haircut, plumbing, etc)
- `other` - Other business types

### Payment Method Types
- `offline` - Cash on Delivery (no screenshot needed)
- `online` - Online payment (EasyPaisa, JazzCash, Bank Transfer)

### Payment Status
- `pending_verification` - Awaiting owner verification
- `paid` - Payment verified
- `rejected` - Payment rejected by owner
- `cancelled` - Order cancelled

### Store Status
- `pending` - Awaiting activation
- `active` - Active and accepting orders
- `suspended` - Temporarily suspended
- `closed` - Permanently closed

### Agent Status
- `is_active: true` - Available for order assignment
- `is_active: false` - Not available

---

## ⚡ RATE LIMITS

- 60 requests per minute per IP
- 1000 requests per hour per API key

---

## 📊 PAGINATION

All list endpoints support pagination:

```
GET /api/admin/stores?page=1&per_page=20

Response includes:
{
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 95
  }
}
```

---

## 🔐 AUTHENTICATION

Get token via login:
```
POST /api/auth/login

{
  "email": "admin@example.com",
  "password": "password123"
}

Response: 200
{
  "token": "3|abcdef...",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "type": "super_admin"
  }
}
```

Use token in all subsequent requests:
```
Authorization: Bearer 3|abcdef...
```

---

## 🧪 EXAMPLE REQUESTS

### Create Full Store Setup
```bash
# 1. Create store
curl -X POST http://localhost:8000/api/admin/stores \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "owner_id": 1,
    "name": "Pizza Palace",
    "business_type": "restaurant",
    "email": "pizza@example.com",
    "phone": "+92301234567",
    "whatsapp_business_number": "+92301234567",
    "address": "123 Main St",
    "city": "Karachi",
    "country": "PK",
    "currency": "PKR",
    "timezone": "Asia/Karachi"
  }'

# 2. Add delivery agent
curl -X POST http://localhost:8000/api/admin/stores/1/delivery-agents \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Ahmed Khan",
    "phone": "+92301234567",
    "email": "ahmed@example.com",
    "address": "456 Agent Ave"
  }'

# 3. Configure payment methods
curl -X PUT http://localhost:8000/api/admin/stores/1/payment-methods \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "methods": [
      {"id": 1, "is_enabled": true, "display_order": 0},
      {"id": 2, "is_enabled": true, "display_order": 1},
      {"id": 3, "is_enabled": true, "display_order": 2}
    ]
  }'
```

---

## 📞 WEBHOOK EVENTS

WhatsApp webhook events for store {id}:

```
POST /api/webhooks/whatsapp/{store_id}
```

Events:
- `messages` - Incoming customer message
- `message_status` - Message delivery/read status
- `media_retrieved` - File downloaded

---

## ✅ VALIDATION RULES

### Store Creation
- `name` - Required, max 255 characters
- `business_type` - Required, must be valid enum
- `whatsapp_business_number` - Required, must match regex /^[0-9+]{1,20}$/
- `owner_id` - Required, must exist in owners table
- `email` - Optional, valid email format
- `phone` - Optional, max 20 characters
- `country` - Optional, max 2 characters (ISO code)
- `currency` - Optional, max 3 characters (ISO code)
- `timezone` - Optional, must be valid timezone

### Delivery Agent Creation
- `name` - Required, max 255 characters
- `phone` - Required, max 20 characters
- `email` - Optional, valid email
- `address` - Optional, string

### Payment Methods Update
- `methods` - Required, array of method objects
- `methods.*.id` - Required, must exist
- `methods.*.is_enabled` - Required, boolean
- `methods.*.display_order` - Required, integer

---

**All endpoints return proper HTTP status codes and error messages.**
**Timestamp format: ISO 8601 (2026-01-09T20:30:00Z)**
