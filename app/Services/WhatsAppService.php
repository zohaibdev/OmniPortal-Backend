<?php

namespace App\Services;

use App\Models\WhatsappAccount;
use App\Models\Tenant\WhatsappMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WhatsAppService
{
    protected string $apiUrl;
    protected ?string $phoneId = null;
    protected ?string $token = null;
    protected ?WhatsappAccount $account = null;

    public function __construct(?WhatsappAccount $account = null)
    {
        $this->apiUrl = 'https://graph.facebook.com/v18.0';
        
        if ($account) {
            $this->setAccount($account);
        } else {
            // Fallback to config (for backward compatibility)
            $this->phoneId = config('services.whatsapp.phone_id');
            $this->token = config('services.whatsapp.token');
        }
    }

    /**
     * Set WhatsApp account to use
     */
    public function setAccount(WhatsappAccount $account): self
    {
        $this->account = $account;
        $this->phoneId = $account->phone_number_id;
        $this->token = $account->access_token;
        
        return $this;
    }

    /**
     * Get current account
     */
    public function getAccount(): ?WhatsappAccount
    {
        return $this->account;
    }

    /**
     * Send a text message
     */
    public function sendTextMessage(string $to, string $message): ?array
    {
        try {
            $response = Http::withToken($this->token)
                ->post("{$this->apiUrl}/{$this->phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'body' => $message,
                    ],
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('WhatsApp send message failed', [
                'to' => $to,
                'response' => $response->json(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('WhatsApp send message exception', [
                'message' => $e->getMessage(),
                'to' => $to,
            ]);
            return null;
        }
    }

    /**
     * Send a message with buttons
     */
    public function sendButtonMessage(string $to, string $bodyText, array $buttons): ?array
    {
        try {
            $response = Http::withToken($this->token)
                ->post("{$this->apiUrl}/{$this->phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'button',
                        'body' => [
                            'text' => $bodyText,
                        ],
                        'action' => [
                            'buttons' => $buttons,
                        ],
                    ],
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('WhatsApp send button message failed', [
                'to' => $to,
                'response' => $response->json(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('WhatsApp send button message exception', [
                'message' => $e->getMessage(),
                'to' => $to,
            ]);
            return null;
        }
    }

    /**
     * Download media from WhatsApp
     */
    public function downloadMedia(string $mediaId): ?array
    {
        try {
            // First, get media URL
            $mediaResponse = Http::withToken($this->token)
                ->get("{$this->apiUrl}/{$mediaId}");

            if (!$mediaResponse->successful()) {
                Log::error('Failed to get media URL', [
                    'media_id' => $mediaId,
                    'response' => $mediaResponse->json(),
                ]);
                return null;
            }

            $mediaData = $mediaResponse->json();
            $mediaUrl = $mediaData['url'] ?? null;
            $mimeType = $mediaData['mime_type'] ?? null;

            if (!$mediaUrl) {
                return null;
            }

            // Download the actual media file
            $fileResponse = Http::withToken($this->token)
                ->get($mediaUrl);

            if (!$fileResponse->successful()) {
                Log::error('Failed to download media file', [
                    'media_url' => $mediaUrl,
                ]);
                return null;
            }

            // Generate unique filename
            $extension = $this->getExtensionFromMimeType($mimeType);
            $filename = Str::uuid() . '.' . $extension;
            $path = 'whatsapp/media/' . now()->format('Y/m/d') . '/' . $filename;

            // Store file
            Storage::disk('private')->put($path, $fileResponse->body());

            return [
                'path' => $path,
                'url' => $mediaUrl,
                'mime_type' => $mimeType,
                'size' => strlen($fileResponse->body()),
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp download media exception', [
                'message' => $e->getMessage(),
                'media_id' => $mediaId,
            ]);
            return null;
        }
    }

    /**
     * Log WhatsApp message
     */
    public function logMessage(array $data): WhatsappMessage
    {
        return WhatsappMessage::create([
            'message_id' => $data['message_id'] ?? Str::uuid(),
            'whatsapp_id' => $data['whatsapp_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'order_id' => $data['order_id'] ?? null,
            'direction' => $data['direction'],
            'type' => $data['type'],
            'message' => $data['message'] ?? null,
            'media_url' => $data['media_url'] ?? null,
            'media_path' => $data['media_path'] ?? null,
            'media_mime_type' => $data['media_mime_type'] ?? null,
            'transcription' => $data['transcription'] ?? null,
            'status' => $data['status'] ?? WhatsappMessage::STATUS_SENT,
            'metadata' => $data['metadata'] ?? null,
            'sent_at' => $data['sent_at'] ?? now(),
        ]);
    }

    /**
     * Get extension from MIME type
     */
    protected function getExtensionFromMimeType(?string $mimeType): string
    {
        return match ($mimeType) {
            'audio/ogg', 'audio/ogg; codecs=opus' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/amr' => 'amr',
            'audio/mp4' => 'm4a',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'video/mp4' => 'mp4',
            default => 'bin',
        };
    }

    /**
     * Mark message as read
     */
    public function markAsRead(string $messageId): bool
    {
        try {
            $response = Http::withToken($this->token)
                ->post("{$this->apiUrl}/{$this->phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'status' => 'read',
                    'message_id' => $messageId,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('WhatsApp mark as read exception', [
                'message' => $e->getMessage(),
                'message_id' => $messageId,
            ]);
            return false;
        }
    }

    /**
     * Verify webhook
     */
    public function verifyWebhook(string $mode, string $token, string $challenge): ?string
    {
        $verifyToken = config('services.whatsapp.verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return $challenge;
        }

        return null;
    }
}
