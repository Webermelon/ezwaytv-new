<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

class CoreApiKey extends Model
{
    protected $fillable = [
        'name',
        'key_prefix',
        'token_hash',
        'secret_encrypted',
        'allowed_ips',
        'notes',
        'last_used_at',
        'expires_at',
        'revoked_at',
        'created_by',
        'revoked_by',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function getIsActiveAttribute(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function decryptedSecret(): ?string
    {
        if (empty($this->secret_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->secret_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function allowedIpList(): array
    {
        if (blank($this->allowed_ips)) {
            return [];
        }

        return collect(preg_split('/[\r\n,]+/', (string) $this->allowed_ips))
            ->map(fn ($ip) => trim($ip))
            ->filter()
            ->values()
            ->all();
    }

    public function markUsed(): void
    {
        $this->forceFill(['last_used_at' => Carbon::now()])->saveQuietly();
    }
}
