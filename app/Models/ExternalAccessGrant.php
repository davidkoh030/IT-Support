<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_id', 'sponsor_user_id', 'external_party_name', 'employer', 'resource',
    'access_level', 'business_purpose', 'expiry_date', 'revoked_at', 'revocation_evidence',
])]
class ExternalAccessGrant extends Model
{
    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'revoked_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_user_id');
    }

    public function isOverdueForRevocation(): bool
    {
        return $this->revoked_at === null && $this->expiry_date->toDateString() < now()->toDateString();
    }
}
