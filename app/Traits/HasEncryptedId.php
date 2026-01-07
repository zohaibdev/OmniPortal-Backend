<?php

namespace App\Traits;

use App\Services\IdEncryptionService;
use Illuminate\Database\Eloquent\Model;

trait HasEncryptedId
{
    /**
     * Boot the trait
     */
    public static function bootHasEncryptedId(): void
    {
        static::created(function (Model $model) {
            if (empty($model->encrypted_id)) {
                $encryptionService = app(IdEncryptionService::class);
                $model->encrypted_id = $encryptionService->encodeWithType(
                    $model->id,
                    $model->getEncryptedIdType()
                );
                $model->saveQuietly();
            }
        });
    }

    /**
     * Get the encrypted ID type for this model
     */
    public function getEncryptedIdType(): string
    {
        return strtolower(class_basename($this));
    }

    /**
     * Find model by encrypted ID
     */
    public static function findByEncryptedId(string $encryptedId): ?static
    {
        return static::where('encrypted_id', $encryptedId)->first();
    }

    /**
     * Find model by encrypted ID or fail
     */
    public static function findByEncryptedIdOrFail(string $encryptedId): static
    {
        $model = static::findByEncryptedId($encryptedId);
        
        if (!$model) {
            abort(404, 'Resource not found');
        }

        return $model;
    }

    /**
     * Decode encrypted ID to get the actual ID
     */
    public static function decodeEncryptedId(string $encryptedId): ?int
    {
        $encryptionService = app(IdEncryptionService::class);
        $instance = new static();
        
        return $encryptionService->decodeWithType(
            $encryptedId,
            $instance->getEncryptedIdType()
        );
    }

    /**
     * Get the route key name for implicit model binding
     */
    public function getRouteKeyName(): string
    {
        return 'encrypted_id';
    }

    /**
     * Retrieve the model for a bound value
     */
    public function resolveRouteBinding($value, $field = null): ?static
    {
        if ($field === 'encrypted_id' || $field === null) {
            return static::findByEncryptedId($value);
        }

        return parent::resolveRouteBinding($value, $field);
    }
}
