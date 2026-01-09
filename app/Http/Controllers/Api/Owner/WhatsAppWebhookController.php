<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use App\Services\OpenAIService;
use App\Services\AIAgentService;
use App\Services\PaymentVerificationService;
use App\Models\WhatsappAccount;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\WhatsappMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppWebhookController extends Controller
{
    protected ?WhatsappAccount $whatsappAccount = null;

    public function __construct(
        protected WhatsAppService $whatsapp,
        protected OpenAIService $openai,
        protected AIAgentService $aiAgent,
        protected PaymentVerificationService $paymentVerification
    ) {}

    /**
     * Webhook verification (GET) - Global endpoint
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub.mode');
        $token = $request->query('hub.verify_token');
        $challenge = $request->query('hub.challenge');

        // Verify against any active WhatsApp account
        if ($mode === 'subscribe') {
            $account = WhatsappAccount::where('webhook_verification_token', $token)
                ->active()
                ->first();

            if ($account) {
                $account->updateWebhookTimestamp();
                return response($challenge, 200);
            }
        }

        return response('Forbidden', 403);
    }

    /**
     * Handle incoming WhatsApp messages (POST) - Global endpoint
     */
    public function webhook(Request $request)
    {
        try {
            $data = $request->all();

            Log::info('WhatsApp webhook received', ['data' => $data]);

            // Process webhook entry
            $entry = $data['entry'][0] ?? null;
            
            if (!$entry) {
                return response()->json(['status' => 'ok']);
            }

            $changes = $entry['changes'][0] ?? null;
            
            if (!$changes || !isset($changes['value'])) {
                return response()->json(['status' => 'ok']);
            }

            $value = $changes['value'];
            $metadata = $value['metadata'] ?? [];
            $phoneNumberId = $metadata['phone_number_id'] ?? null;

            if (!$phoneNumberId) {
                Log::warning('No phone_number_id in webhook', ['value' => $value]);
                return response()->json(['status' => 'ok']);
            }

            // Find WhatsApp account by phone_number_id
            $this->whatsappAccount = WhatsappAccount::where('phone_number_id', $phoneNumberId)
                ->active()
                ->first();

            if (!$this->whatsappAccount) {
                Log::warning('WhatsApp account not found or inactive', [
                    'phone_number_id' => $phoneNumberId,
                ]);
                return response()->json(['status' => 'ok']);
            }

            // Update last webhook timestamp
            $this->whatsappAccount->updateWebhookTimestamp();

            // Switch to store's tenant database
            $store = $this->whatsappAccount->store;
            if ($store && $store->database_name) {
                config(['database.connections.tenant.database' => $store->database_name]);
                app('db')->purge('tenant');
            }

            // Handle messages
            if (isset($value['messages'])) {
                foreach ($value['messages'] as $message) {
                    $this->processIncomingMessage($message, $metadata);
                }
            }

            // Handle status updates
            if (isset($value['statuses'])) {
                foreach ($value['statuses'] as $status) {
                    $this->processStatusUpdate($status);
                }
            }

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('WhatsApp webhook error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Process incoming message
     */
    protected function processIncomingMessage(array $message, array $metadata): void
    {
        try {
            $from = $message['from'] ?? null;
            $messageId = $message['id'] ?? null;
            $type = $message['type'] ?? 'text';

            if (!$from || !$messageId) {
                return;
            }

            // Get or create customer
            $customer = $this->getOrCreateCustomer($from);

            // Mark message as read
            $this->whatsapp->markAsRead($messageId);

            // Process based on message type
            switch ($type) {
                case 'text':
                    $this->processTextMessage($message, $customer);
                    break;

                case 'audio':
                    $this->processVoiceMessage($message, $customer);
                    break;

                case 'image':
                    $this->processImageMessage($message, $customer);
                    break;

                case 'button':
                    $this->processButtonReply($message, $customer);
                    break;

                default:
                    $this->whatsapp->sendTextMessage(
                        $from,
                        'Maaf, is type ka message supported nahi hai. Text ya voice message bhejein.'
                    );
            }
        } catch (\Exception $e) {
            Log::error('Process incoming message failed', [
                'message' => $e->getMessage(),
                'whatsapp_message' => $message,
            ]);
        }
    }

    /**
     * Process text message
     */
    protected function processTextMessage(array $message, Customer $customer): void
    {
        $text = $message['text']['body'] ?? '';

        if (empty($text)) {
            return;
        }

        // Log message
        $this->whatsapp->logMessage([
            'message_id' => Str::uuid(),
            'whatsapp_id' => $message['id'],
            'customer_id' => $customer->id,
            'direction' => WhatsappMessage::DIRECTION_INBOUND,
            'type' => WhatsappMessage::TYPE_TEXT,
            'message' => $text,
        ]);

        // Get active order (if any)
        $activeOrder = $this->getActiveOrder($customer);

        // Process with AI agent
        $response = $this->aiAgent->processMessage(
            app('current.store'),
            $customer,
            $text,
            $activeOrder
        );

        // Update or create order with conversation state
        if (isset($response['order'])) {
            $order = $response['order'];
        } elseif ($activeOrder) {
            $activeOrder->update([
                'conversation_state' => $response['state'],
                'conversation_context' => $response['context'],
            ]);
            $order = $activeOrder;
        } else {
            // Create new order in pending state
            $order = Order::create([
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'customer_email' => $customer->email,
                'status' => Order::STATUS_PENDING,
                'subtotal' => 0,
                'total' => 0,
                'currency' => config('app.currency_code', 'PKR'),
                'source' => 'whatsapp',
                'conversation_state' => $response['state'],
                'conversation_context' => $response['context'],
            ]);
        }

        // Send reply
        $this->whatsapp->sendTextMessage($customer->phone, $response['reply']);

        // Log outbound message
        $this->whatsapp->logMessage([
            'message_id' => Str::uuid(),
            'customer_id' => $customer->id,
            'order_id' => $order->id ?? null,
            'direction' => WhatsappMessage::DIRECTION_OUTBOUND,
            'type' => WhatsappMessage::TYPE_TEXT,
            'message' => $response['reply'],
        ]);
    }

    /**
     * Process voice message
     */
    protected function processVoiceMessage(array $message, Customer $customer): void
    {
        if (!config('services.openai.voice_enabled', false)) {
            $this->whatsapp->sendTextMessage(
                $customer->phone,
                'Maaf, voice messages abhi supported nahi hain. Text message bhejein.'
            );
            return;
        }

        $audio = $message['audio'] ?? null;
        
        if (!$audio || !isset($audio['id'])) {
            return;
        }

        // Download audio
        $mediaData = $this->whatsapp->downloadMedia($audio['id']);

        if (!$mediaData) {
            $this->whatsapp->sendTextMessage(
                $customer->phone,
                'Maaf, voice message download nahi ho saka. Phir se try karein.'
            );
            return;
        }

        // Transcribe audio
        $transcription = $this->openai->transcribeAudio($mediaData['path']);

        if (!$transcription) {
            $this->whatsapp->sendTextMessage(
                $customer->phone,
                'Maaf, voice message samajh nahi aayi. Clear bol kar phir se bhejein.'
            );
            return;
        }

        // Log voice message with transcription
        $this->whatsapp->logMessage([
            'message_id' => Str::uuid(),
            'whatsapp_id' => $message['id'],
            'customer_id' => $customer->id,
            'direction' => WhatsappMessage::DIRECTION_INBOUND,
            'type' => WhatsappMessage::TYPE_VOICE,
            'media_path' => $mediaData['path'],
            'media_url' => $mediaData['url'],
            'media_mime_type' => $mediaData['mime_type'],
            'transcription' => $transcription,
        ]);

        // Process transcribed text with AI
        $activeOrder = $this->getActiveOrder($customer);

        $response = $this->aiAgent->processMessage(
            app('current.store'),
            $customer,
            $transcription,
            $activeOrder
        );

        // Update order
        if (isset($response['order'])) {
            $order = $response['order'];
        } elseif ($activeOrder) {
            $activeOrder->update([
                'conversation_state' => $response['state'],
                'conversation_context' => $response['context'],
            ]);
            $order = $activeOrder;
        } else {
            $order = Order::create([
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'status' => Order::STATUS_PENDING,
                'subtotal' => 0,
                'total' => 0,
                'currency' => config('app.currency_code', 'PKR'),
                'source' => 'whatsapp',
                'conversation_state' => $response['state'],
                'conversation_context' => $response['context'],
            ]);
        }

        // Send text reply (not voice)
        $this->whatsapp->sendTextMessage($customer->phone, $response['reply']);

        // Log outbound message
        $this->whatsapp->logMessage([
            'message_id' => Str::uuid(),
            'customer_id' => $customer->id,
            'order_id' => $order->id ?? null,
            'direction' => WhatsappMessage::DIRECTION_OUTBOUND,
            'type' => WhatsappMessage::TYPE_TEXT,
            'message' => $response['reply'],
        ]);
    }

    /**
     * Process image message (payment screenshot)
     */
    protected function processImageMessage(array $message, Customer $customer): void
    {
        $image = $message['image'] ?? null;
        
        if (!$image || !isset($image['id'])) {
            return;
        }

        // Get active order
        $activeOrder = $this->getActiveOrder($customer);

        if (!$activeOrder) {
            $this->whatsapp->sendTextMessage(
                $customer->phone,
                'Koi active order nahi hai. Pehle order place karein.'
            );
            return;
        }

        // Check if waiting for payment screenshot
        if ($activeOrder->conversation_state !== Order::CONVERSATION_STATE_AWAITING_PAYMENT_SCREENSHOT) {
            $this->whatsapp->sendTextMessage(
                $customer->phone,
                'Payment screenshot ki zaroorat nahi hai abhi.'
            );
            return;
        }

        // Download image
        $mediaData = $this->whatsapp->downloadMedia($image['id']);

        if (!$mediaData) {
            $this->whatsapp->sendTextMessage(
                $customer->phone,
                'Maaf, image download nahi ho saki. Phir se bhejein.'
            );
            return;
        }

        // Log image message
        $this->whatsapp->logMessage([
            'message_id' => Str::uuid(),
            'whatsapp_id' => $message['id'],
            'customer_id' => $customer->id,
            'order_id' => $activeOrder->id,
            'direction' => WhatsappMessage::DIRECTION_INBOUND,
            'type' => WhatsappMessage::TYPE_IMAGE,
            'media_path' => $mediaData['path'],
            'media_url' => $mediaData['url'],
            'media_mime_type' => $mediaData['mime_type'],
            'message' => 'Payment screenshot',
        ]);

        // Process payment screenshot
        $context = $activeOrder->conversation_context ?? [];
        $pendingOrderData = $context['pending_order'] ?? null;

        if (!$pendingOrderData) {
            $this->whatsapp->sendTextMessage(
                $customer->phone,
                'Maaf, order data nahi mila. Phir se order karein.'
            );
            return;
        }

        // Create order now with screenshot
        $paymentMethod = \App\Models\Tenant\PaymentMethod::find($context['payment_method_id']);

        if (!$paymentMethod) {
            $this->whatsapp->sendTextMessage(
                $customer->phone,
                'Maaf, payment method nahi mila.'
            );
            return;
        }

        // Create the actual order
        $order = $this->createOrderWithScreenshot($pendingOrderData, $customer, $paymentMethod, $mediaData['path']);

        if ($order) {
            // Delete pending order
            $activeOrder->delete();

            $this->whatsapp->sendTextMessage(
                $customer->phone,
                "Shukriya! Aap ka order #{$order->order_number} confirm ho gaya hai.\n\nTotal: Rs {$order->total}\n\nPayment verify hone ke baad deliver karenge!"
            );

            // Log outbound message
            $this->whatsapp->logMessage([
                'message_id' => Str::uuid(),
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'direction' => WhatsappMessage::DIRECTION_OUTBOUND,
                'type' => WhatsappMessage::TYPE_TEXT,
                'message' => "Order confirmed: #{$order->order_number}",
            ]);
        }
    }

    /**
     * Process button reply
     */
    protected function processButtonReply(array $message, Customer $customer): void
    {
        $buttonReply = $message['button']['text'] ?? '';
        
        // Process as text message
        $message['text'] = ['body' => $buttonReply];
        $this->processTextMessage($message, $customer);
    }

    /**
     * Process status update
     */
    protected function processStatusUpdate(array $status): void
    {
        $messageId = $status['id'] ?? null;
        $statusValue = $status['status'] ?? null;

        if (!$messageId || !$statusValue) {
            return;
        }

        // Update message status in database
        $message = WhatsappMessage::where('whatsapp_id', $messageId)->first();

        if ($message) {
            $message->update(['status' => $statusValue]);
        }
    }

    /**
     * Get or create customer
     */
    protected function getOrCreateCustomer(string $phone): Customer
    {
        $customer = Customer::where('phone', $phone)->first();

        if (!$customer) {
            $customer = Customer::create([
                'phone' => $phone,
                'name' => 'WhatsApp Customer ' . substr($phone, -4),
                'source' => 'whatsapp',
            ]);
        }

        return $customer;
    }

    /**
     * Get active order for customer
     */
    protected function getActiveOrder(Customer $customer): ?Order
    {
        return Order::where('customer_id', $customer->id)
            ->whereNotIn('conversation_state', [Order::CONVERSATION_STATE_ORDER_PLACED, null])
            ->whereIn('status', [Order::STATUS_PENDING])
            ->latest()
            ->first();
    }

    /**
     * Create order with payment screenshot
     */
    protected function createOrderWithScreenshot(array $data, Customer $customer, $paymentMethod, string $screenshotPath): ?Order
    {
        try {
            $subtotal = 0;
            $items = [];

            foreach ($data['items'] as $itemData) {
                $product = \App\Models\Tenant\Product::find($itemData['product_id']);
                
                if (!$product) {
                    continue;
                }

                $quantity = $itemData['quantity'];
                $unitPrice = $product->price;
                $total = $unitPrice * $quantity;

                $subtotal += $total;

                $items[] = [
                    'product_id' => $product->id,
                    'variant_id' => $itemData['variant_id'] ?? null,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $total,
                ];
            }

            // Create order
            $order = Order::create([
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'delivery_address' => $data['delivery_address'],
                'type' => 'delivery',
                'status' => Order::STATUS_CONFIRMED,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'currency' => config('app.currency_code', 'PKR'),
                'payment_method_id' => $paymentMethod->id,
                'payment_method' => $paymentMethod->name,
                'payment_status' => Order::PAYMENT_STATUS_PENDING_VERIFICATION,
                'payment_proof_path' => $screenshotPath,
                'source' => 'whatsapp',
                'conversation_state' => Order::CONVERSATION_STATE_ORDER_PLACED,
            ]);

            // Create order items
            foreach ($items as $itemData) {
                $order->items()->create($itemData);
            }

            // Auto-assign delivery agent for restaurants
            if (app('current.store')->business_type === 'restaurant') {
                $agent = \App\Models\Tenant\DeliveryAgent::available()->first();
                if ($agent) {
                    $order->update(['delivery_agent_id' => $agent->id]);
                    $agent->incrementOrders();
                }
            }

            return $order;
        } catch (\Exception $e) {
            Log::error('Create order with screenshot failed', [
                'message' => $e->getMessage(),
                'data' => $data,
            ]);
            return null;
        }
    }
}
