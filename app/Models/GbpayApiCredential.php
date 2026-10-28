<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class GbpayApiCredential extends Model
{
    use HasFactory;

    protected $table = 'gbpay_api_credentials';
    protected $primaryKey = 'credential_id';

    protected $fillable = [
        'username',
        'password',
        'description',
        'is_active',
        'last_used_at',
        'last_ip_address',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Set password with hashing (only if not already hashed)
     */
    public function setPasswordAttribute($value): void
    {
        if (! empty($value) && strlen($value) < 60) {
            $this->attributes['password'] = Hash::make($value);
        } else {
            $this->attributes['password'] = $value;
        }
    }

    /**
     * Verify password
     */
    public function verifyPassword(string $password): bool
    {
        return Hash::check($password, $this->password);
    }

    /**
     * Get active credential by username
     */
    public static function getActiveByUsername(string $username): ?self
    {
        return static::where('username', $username)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Update last used timestamp and IP
     */
    public function updateLastUsed(?string $ipAddress = null): void
    {
        $this->update([
            'last_used_at' => now(),
            'last_ip_address' => $ipAddress,
        ]);
    }
}

