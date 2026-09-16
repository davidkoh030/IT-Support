<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'scope_type', 'scope_id', 'role', 'effective_date',
    'expiry_date', 'revoked_at', 'granted_by_id', 'notes',
])]
class AccessGrant extends Model
{
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'expiry_date' => 'date',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_id');
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        $today = now()->toDateString();

        if ($this->effective_date->toDateString() > $today) {
            return false;
        }

        return $this->expiry_date === null || $this->expiry_date->toDateString() >= $today;
    }
}
