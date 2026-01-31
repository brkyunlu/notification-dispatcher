<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'key',
        'permissions',
        'last_used_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'permissions' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'key',
    ];

    /**
     * Available permission types
     */
    public const PERMISSION_READ = 'read';
    public const PERMISSION_WRITE = 'write';
    public const PERMISSION_ADMIN = 'admin';

    /**
     * Generate a new API key
     */
    public static function generateKey(): string
    {
        return 'ndk_' . Str::random(48);
    }

    /**
     * Check if the API key is valid (active and not expired)
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if the API key has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        // Admin has all permissions
        if ($this->hasAdminPermission()) {
            return true;
        }

        return in_array($permission, $this->permissions ?? []);
    }

    /**
     * Check if has admin permission
     */
    public function hasAdminPermission(): bool
    {
        return in_array(self::PERMISSION_ADMIN, $this->permissions ?? []);
    }

    /**
     * Check if can read (read or write or admin)
     */
    public function canRead(): bool
    {
        return $this->hasPermission(self::PERMISSION_READ) 
            || $this->hasPermission(self::PERMISSION_WRITE);
    }

    /**
     * Check if can write (write or admin)
     */
    public function canWrite(): bool
    {
        return $this->hasPermission(self::PERMISSION_WRITE);
    }

    /**
     * Update last used timestamp
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Scope for active keys
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for valid keys (active and not expired)
     */
    public function scopeValid($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
