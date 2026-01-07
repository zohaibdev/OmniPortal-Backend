<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;

class Employee extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'store_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'avatar',
        'role',
        'permissions',
        'pin',
        'password',
        'status',
        'hourly_rate',
        'hire_date',
        'notes',
    ];

    protected $hidden = [
        'pin',
        'password',
    ];
    
    protected $appends = ['name'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'hire_date' => 'date',
            'hourly_rate' => 'decimal:2',
        ];
    }

    /**
     * Override tokens() to use the central database connection for personal_access_tokens
     */
    public function tokens()
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable')
            ->setConnection(config('database.default'));
    }

    /**
     * Override createToken to use central database and store the store_id
     * @param string $name
     * @param array $abilities
     * @param \DateTimeInterface|null $expiresAt
     * @param int|null $storeId The store ID to associate with this token
     */
    public function createToken(string $name, array $abilities = ['*'], ?\DateTimeInterface $expiresAt = null, ?int $storeId = null)
    {
        $plainTextToken = \Illuminate\Support\Str::random(40);
        
        // Use our custom PersonalAccessToken model
        $token = PersonalAccessToken::forceCreate([
            'tokenable_type' => static::class,
            'tokenable_id' => $this->getKey(),
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
            'store_id' => $storeId,
        ]);
        
        return new NewAccessToken($token, $token->getKey().'|'.$plainTextToken);
    }
    
    // Accessors
    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shifts()
    {
        return $this->hasMany(EmployeeShift::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Helpers
    public function hasPermission(string $permission): bool
    {
        if (!$this->permissions) {
            return false;
        }
        return in_array($permission, $this->permissions);
    }

    public function verifyPin(string $pin): bool
    {
        return $this->pin === $pin;
    }
}
