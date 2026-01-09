<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OpenAIService
{
    protected string $apiKey;
    protected string $apiUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
    }

    /**
     * Generate chat completion
     */
    public function chat(array $messages, ?array $functions = null, ?string $model = null): ?array
    {
        try {
            $model = $model ?? config('services.openai.model', 'gpt-4o-mini');

            $payload = [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 500,
            ];

            // Add function calling if provided
            if ($functions) {
                $payload['functions'] = $functions;
                $payload['function_call'] = 'auto';
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$this->apiUrl}/chat/completions", $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('OpenAI chat failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('OpenAI chat exception', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Transcribe audio to text using Whisper
     */
    public function transcribeAudio(string $audioPath): ?string
    {
        try {
            if (!config('services.openai.voice_enabled', false)) {
                Log::warning('Voice transcription disabled in config');
                return null;
            }

            $audioContent = Storage::disk('private')->get($audioPath);
            $tempPath = sys_get_temp_dir() . '/' . basename($audioPath);
            file_put_contents($tempPath, $audioContent);

            $model = config('services.openai.stt_model', 'whisper-1');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])
            ->attach('file', fopen($tempPath, 'r'), basename($audioPath))
            ->post("{$this->apiUrl}/audio/transcriptions", [
                'model' => $model,
                'language' => 'ur', // Urdu (includes Roman Urdu)
            ]);

            // Clean up temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            if ($response->successful()) {
                $data = $response->json();
                return $data['text'] ?? null;
            }

            Log::error('OpenAI transcription failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('OpenAI transcription exception', [
                'message' => $e->getMessage(),
                'audio_path' => $audioPath,
            ]);
            return null;
        }
    }

    /**
     * Generate speech from text using TTS
     */
    public function textToSpeech(string $text): ?string
    {
        try {
            if (!config('services.openai.voice_enabled', false)) {
                Log::warning('Voice generation disabled in config');
                return null;
            }

            $model = config('services.openai.tts_model', 'tts-1');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$this->apiUrl}/audio/speech", [
                'model' => $model,
                'input' => $text,
                'voice' => 'nova', // Female voice, suitable for customer service
                'response_format' => 'opus', // WhatsApp compatible
            ]);

            if ($response->successful()) {
                // Store audio file
                $filename = 'tts_' . time() . '_' . uniqid() . '.ogg';
                $path = 'whatsapp/audio/' . now()->format('Y/m/d') . '/' . $filename;
                
                Storage::disk('private')->put($path, $response->body());

                return $path;
            }

            Log::error('OpenAI TTS failed', [
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('OpenAI TTS exception', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract structured data from text
     */
    public function extractStructuredData(string $prompt, array $schema): ?array
    {
        try {
            $messages = [
                [
                    'role' => 'system',
                    'content' => 'You are a data extraction assistant. Extract information according to the provided schema and return valid JSON.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ];

            $response = $this->chat($messages);

            if ($response && isset($response['choices'][0]['message']['content'])) {
                $content = $response['choices'][0]['message']['content'];
                
                // Try to parse JSON
                $data = json_decode($content, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('OpenAI extract structured data exception', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generate embedding for text
     */
    public function createEmbedding(string $text): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->apiUrl}/embeddings", [
                'model' => 'text-embedding-3-small',
                'input' => $text,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'][0]['embedding'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('OpenAI embedding exception', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
