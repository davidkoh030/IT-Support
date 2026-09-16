<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'ticket_id', 'metric', 'target_at', 'paused_seconds', 'paused_at',
    'pause_reason', 'warned_at', 'breached_notified_at', 'achieved_at',
])]
class TicketSlaClock extends Model
{
    protected function casts(): array
    {
        return [
            'target_at' => 'datetime',
            'paused_at' => 'datetime',
            'warned_at' => 'datetime',
            'breached_notified_at' => 'datetime',
            'achieved_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function isPaused(): bool
    {
        return $this->paused_at !== null;
    }

    /**
     * Effective target after adding back any paused time, so pauses genuinely
     * extend the clock rather than just hiding a breach.
     */
    public function effectiveTargetAt(): Carbon
    {
        return $this->target_at->clone()->addSeconds($this->paused_seconds);
    }

    public function isBreached(): bool
    {
        if ($this->achieved_at !== null) {
            return false;
        }

        $deadline = $this->effectiveTargetAt();

        if ($this->isPaused()) {
            return false;
        }

        return now()->gt($deadline);
    }
}
