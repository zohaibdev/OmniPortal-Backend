<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\PaymentMethod;
use App\Models\Tenant\DeliveryAgent;
use Illuminate\Support\Facades\Log;

class AIAgentService
{
    public function __construct(
        protected OpenAIService $openai,
        protected WhatsAppService $whatsapp
    ) {}

    /**
     * Process customer message and generate response
     */
    public function processMessage(
        Store $store,
        Customer $customer,
        string $message,
        ?Order $activeOrder = null
    ): array {
        $businessType = $store->business_type ?? 'general';
        $conversationState = $activeOrder->conversation_state ?? Order::CONVERSATION_STATE_GREETING;
        $conversationContext = $activeOrder->conversation_context ?? [];

        // Build conversation history
        $messages = $this->buildConversationMessages(
            $store,
            $customer,
            $message,
            $conversationState,
            $conversationContext,
            $businessType
        );

        // Get AI functions for this business type
        $functions = $this->getAIFunctions($businessType);

        // Call OpenAI
        $response = $this->openai->chat($messages, $functions);

        if (!$response) {
            return [
                'reply' => $this->getErrorMessage(),
                'state' => $conversationState,
                'context' => $conversationContext,
            ];
        }

        // Process AI response
        return $this->processAIResponse(
            $response,
            $store,
            $customer,
            $activeOrder,
            $conversationState,
            $conversationContext,
            $businessType
        );
    }

    /**
     * Build conversation messages for AI
     */
    protected function buildConversationMessages(
        Store $store,
        Customer $customer,
        string $message,
        string $state,
        array $context,
        string $businessType
    ): array {
        $systemPrompt = $this->getSystemPrompt($store, $businessType);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Add conversation history from context
        if (!empty($context['history'])) {
            foreach ($context['history'] as $msg) {
                $messages[] = $msg;
            }
        }

        // Add current message
        $messages[] = [
            'role' => 'user',
            'content' => $message,
        ];

        return $messages;
    }

    /**
     * Get system prompt based on business type
     */
    protected function getSystemPrompt(Store $store, string $businessType): string
    {
        $currencySymbol = config('app.currency_symbol', 'Rs');
        $storeName = $store->name;

        $basePrompt = <<<PROMPT
        You are a helpful AI assistant for {$storeName}, a {$businessType} business in Pakistan.

        CRITICAL RULES:
        - Communicate ONLY in Roman Urdu (Urdu written in English alphabet)
        - Keep responses SHORT and polite (2-3 sentences max)
        - Never guess prices or availability - always confirm from product list
        - Ask ONLY relevant questions based on business type
        - Use {$currencySymbol} for all prices
        - Be professional and courteous

        BUSINESS TYPE: {$businessType}

        CONVERSATION FLOW:
        1. Greet customer warmly
        2. Help them browse products/menu
        3. Collect order details (quantity, variants, address)
        4. Ask for payment method
        5. If online payment: request screenshot
        6. Confirm order

        NEVER:
        - Make up product information
        - Confirm orders without all required details
        - Be rude or dismissive
        - Use English (only Roman Urdu)

        Remember: You are representing a business. Be helpful and accurate.
        PROMPT;

        return $basePrompt;
    }

    /**
     * Get AI functions for business type
     */
    protected function getAIFunctions(string $businessType): array
    {
        $functions = [
            [
                'name' => 'create_order',
                'description' => 'Create a new order when customer has confirmed all details',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => [
                            'type' => 'array',
                            'description' => 'Array of order items',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'product_id' => ['type' => 'integer'],
                                    'quantity' => ['type' => 'integer'],
                                    'variant_id' => ['type' => 'integer', 'description' => 'Optional variant'],
                                ],
                                'required' => ['product_id', 'quantity'],
                            ],
                        ],
                        'delivery_address' => [
                            'type' => 'string',
                            'description' => 'Full delivery address',
                        ],
                        'payment_method_id' => [
                            'type' => 'integer',
                            'description' => 'Selected payment method ID',
                        ],
                    ],
                    'required' => ['items', 'delivery_address', 'payment_method_id'],
                ],
            ],
        ];

        // Add delivery agent assignment for restaurants
        if ($businessType === 'restaurant') {
            $functions[] = [
                'name' => 'assign_delivery_agent',
                'description' => 'Assign an available delivery agent to the order',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_id' => [
                            'type' => 'integer',
                            'description' => 'The order ID to assign agent to',
                        ],
                    ],
                    'required' => ['order_id'],
                ],
            ];
        }

        return $functions;
    }

    /**
     * Process AI response and execute function calls
     */
    protected function processAIResponse(
        array $response,
        Store $store,
        Customer $customer,
        ?Order $activeOrder,
        string $currentState,
        array $context,
        string $businessType
    ): array {
        $choice = $response['choices'][0] ?? null;
        
        if (!$choice) {
            return [
                'reply' => $this->getErrorMessage(),
                'state' => $currentState,
                'context' => $context,
            ];
        }

        $message = $choice['message'];

        // Check if AI wants to call a function
        if (isset($message['function_call'])) {
            return $this->executeFunctionCall(
                $message['function_call'],
                $store,
                $customer,
                $activeOrder,
                $currentState,
                $context,
                $businessType
            );
        }

        // Regular text response
        $reply = $message['content'] ?? $this->getErrorMessage();

        // Update context history
        $context['history'] = $context['history'] ?? [];
        $context['history'][] = ['role' => 'assistant', 'content' => $reply];

        // Limit history to last 10 messages
        if (count($context['history']) > 10) {
            $context['history'] = array_slice($context['history'], -10);
        }

        return [
            'reply' => $reply,
            'state' => $this->determineNextState($reply, $currentState),
            'context' => $context,
        ];
    }

    /**
     * Execute function call from AI
     */
    protected function executeFunctionCall(
        array $functionCall,
        Store $store,
        Customer $customer,
        ?Order $activeOrder,
        string $currentState,
        array $context,
        string $businessType
    ): array {
        $functionName = $functionCall['name'];
        $arguments = json_decode($functionCall['arguments'], true);

        Log::info('AI Function Call', [
            'function' => $functionName,
            'arguments' => $arguments,
        ]);

        switch ($functionName) {
            case 'create_order':
                return $this->createOrderFromAI($arguments, $customer, $context);
            
            case 'assign_delivery_agent':
                return $this->assignDeliveryAgent($arguments, $context);
            
            default:
                return [
                    'reply' => $this->getErrorMessage(),
                    'state' => $currentState,
                    'context' => $context,
                ];
        }
    }

    /**
     * Create order from AI function call
     */
    protected function createOrderFromAI(array $data, Customer $customer, array $context): array
    {
        try {
            // Check if payment method requires screenshot
            $paymentMethod = PaymentMethod::find($data['payment_method_id']);
            
            if (!$paymentMethod) {
                return [
                    'reply' => 'Maaf, payment method theek nahi hai. Phir se select karein.',
                    'state' => Order::CONVERSATION_STATE_CONFIRMING_ORDER,
                    'context' => $context,
                ];
            }

            // If online payment, wait for screenshot
            if ($paymentMethod->type === PaymentMethod::TYPE_ONLINE) {
                // Don't create order yet, wait for screenshot
                $context['pending_order'] = $data;
                $context['payment_method_id'] = $data['payment_method_id'];

                $instructions = $paymentMethod->instructions ?? 'Payment details send karein';

                return [
                    'reply' => "Theek hai! {$instructions}\n\nAap ka payment screenshot ya proof send karein.",
                    'state' => Order::CONVERSATION_STATE_AWAITING_PAYMENT_SCREENSHOT,
                    'context' => $context,
                ];
            }

            // For offline payment (COD), create order immediately
            $order = $this->createOrder($data, $customer, $paymentMethod);

            if (!$order) {
                return [
                    'reply' => 'Maaf, order create nahi ho saka. Admin se contact karein.',
                    'state' => Order::CONVERSATION_STATE_GREETING,
                    'context' => [],
                ];
            }

            return [
                'reply' => "Shukriya! Aap ka order #{$order->order_number} confirm ho gaya hai.\n\nTotal: Rs {$order->total}\n\nHum jald deliver karenge!",
                'state' => Order::CONVERSATION_STATE_ORDER_PLACED,
                'context' => ['order_id' => $order->id],
                'order' => $order,
            ];
        } catch (\Exception $e) {
            Log::error('Create order from AI failed', [
                'message' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'reply' => 'Maaf, order create nahi ho saka. Phir se try karein.',
                'state' => Order::CONVERSATION_STATE_GREETING,
                'context' => [],
            ];
        }
    }

    /**
     * Create actual order
     */
    protected function createOrder(array $data, Customer $customer, PaymentMethod $paymentMethod): ?Order
    {
        $subtotal = 0;
        $items = [];

        foreach ($data['items'] as $itemData) {
            $product = Product::find($itemData['product_id']);
            
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
            'payment_status' => $paymentMethod->type === PaymentMethod::TYPE_ONLINE 
                ? Order::PAYMENT_STATUS_PENDING_VERIFICATION 
                : Order::PAYMENT_STATUS_PENDING,
            'source' => 'whatsapp',
            'conversation_state' => Order::CONVERSATION_STATE_ORDER_PLACED,
        ]);

        // Create order items
        foreach ($items as $itemData) {
            $order->items()->create($itemData);
        }

        // Auto-assign delivery agent for restaurants
        if (config('app.business_type') === 'restaurant') {
            $this->autoAssignDeliveryAgent($order);
        }

        return $order;
    }

    /**
     * Auto-assign available delivery agent
     */
    protected function autoAssignDeliveryAgent(Order $order): void
    {
        $agent = DeliveryAgent::available()->first();

        if ($agent) {
            $order->update(['delivery_agent_id' => $agent->id]);
            $agent->incrementOrders();
        }
    }

    /**
     * Assign delivery agent from AI
     */
    protected function assignDeliveryAgent(array $data, array $context): array
    {
        $order = Order::find($data['order_id']);

        if (!$order) {
            return [
                'reply' => 'Order nahi mila.',
                'state' => Order::CONVERSATION_STATE_GREETING,
                'context' => $context,
            ];
        }

        $this->autoAssignDeliveryAgent($order);

        return [
            'reply' => 'Delivery agent assign ho gaya hai.',
            'state' => Order::CONVERSATION_STATE_ORDER_PLACED,
            'context' => $context,
        ];
    }

    /**
     * Determine next conversation state
     */
    protected function determineNextState(string $reply, string $currentState): string
    {
        // Simple state machine logic
        // In production, this could be more sophisticated
        return $currentState;
    }

    /**
     * Get error message in Roman Urdu
     */
    protected function getErrorMessage(): string
    {
        return 'Maaf, kuch problem ho gayi. Phir se try karein ya admin se contact karein.';
    }
}
