<?php

namespace App\Services;

use Hashids\Hashids;
use Illuminate\Support\Facades\Crypt;

class IdEncryptionService
{
    private Hashids $hashids;
    private string $salt;
    private int $minLength;

    public function __construct()
    {
        $this->salt = config('app.key') . config('encryption.id_salt', 'omniportal_secure');
        $this->minLength = config('encryption.id_min_length', 10);
        $this->hashids = new Hashids($this->salt, $this->minLength);
    }

    /**
     * Encode a single ID
     */
    public function encode(int $id): string
    {
        return $this->hashids->encode($id);
    }

    /**
     * Encode multiple IDs
     */
    public function encodeMany(array $ids): string
    {
        return $this->hashids->encode(...$ids);
    }

    /**
     * Decode a hash to get the original ID
     */
    public function decode(string $hash): ?int
    {
        $decoded = $this->hashids->decode($hash);
        return $decoded[0] ?? null;
    }

    /**
     * Decode a hash to get multiple IDs
     */
    public function decodeMany(string $hash): array
    {
        return $this->hashids->decode($hash);
    }

    /**
     * Encode ID with type prefix for different entities
     */
    public function encodeWithType(int $id, string $type): string
    {
        $typeCode = $this->getTypeCode($type);
        return $this->hashids->encode($typeCode, $id);
    }

    /**
     * Decode ID with type validation
     */
    public function decodeWithType(string $hash, string $expectedType): ?int
    {
        $decoded = $this->hashids->decode($hash);
        
        if (count($decoded) !== 2) {
            return null;
        }

        $typeCode = $decoded[0];
        $id = $decoded[1];

        if ($typeCode !== $this->getTypeCode($expectedType)) {
            return null;
        }

        return $id;
    }

    /**
     * Get type code for different entities
     */
    private function getTypeCode(string $type): int
    {
        return match ($type) {
            'store' => 1,
            'product' => 2,
            'category' => 3,
            'order' => 4,
            'customer' => 5,
            'employee' => 6,
            'payment' => 7,
            'page' => 8,
            'banner' => 9,
            'coupon' => 10,
            'subscription' => 11,
            'user' => 12,
            default => 0,
        };
    }

    /**
     * Encrypt sensitive data (for database storage)
     */
    public function encryptData(mixed $data): string
    {
        return Crypt::encryptString(json_encode($data));
    }

    /**
     * Decrypt sensitive data
     */
    public function decryptData(string $encrypted): mixed
    {
        try {
            return json_decode(Crypt::decryptString($encrypted), true);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate a secure reference code
     */
    public function generateReferenceCode(string $prefix, int $id): string
    {
        $timestamp = now()->format('ymd');
        $hash = strtoupper(substr($this->encode($id), 0, 6));
        return "{$prefix}-{$timestamp}-{$hash}";
    }
}
